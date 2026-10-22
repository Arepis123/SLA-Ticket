<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Notifications\ClientTicketsReminder;
use App\Notifications\NewTicketComment;
use App\Notifications\NewTicketWorkLog;
use App\Notifications\SlaAlert;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCreated;
use App\Notifications\TicketMerged;
use App\Notifications\TicketStatusChanged;
use App\Support\BusinessCalendar;
use App\Support\HtmlSanitizer;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

class TicketManager
{
    /**
     * Open a new ticket.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function create(User $reporter, array $data, array $files = []): Ticket
    {
        $ticket = DB::transaction(function () use ($reporter, $data, $files) {
            $ticket = Ticket::create([...$data, 'reporter_id' => $reporter->id, 'status' => TicketStatus::Open]);

            $this->storeAttachments($ticket, $reporter, $files);
            $this->log($ticket, $reporter, 'created');

            return $ticket;
        });

        Notification::send(
            User::admins()->whereKeyNot($reporter->id)->get(),
            new TicketCreated($ticket),
        );

        return $ticket;
    }

    /**
     * Move a ticket to a new status, pausing or resuming the SLA clock.
     */
    public function changeStatus(Ticket $ticket, TicketStatus $status, User $actor): void
    {
        $from = $ticket->status;

        if ($from === $status) {
            return;
        }

        $now = now();

        if ($actor->isStaff()) {
            $ticket->first_responded_at ??= $now;
        }

        // Leaving a stopped state: credit the paused time and push due dates back. Both clocks
        // are credited so a later priority change can switch between them without losing it.
        if (! $from->isActive() && $status->isActive() && $ticket->paused_at) {
            $ticket->paused_minutes += (int) $ticket->paused_at->diffInMinutes($now);
            $ticket->paused_business_minutes += app(BusinessCalendar::class)->minutesBetween($ticket->paused_at, $now);
            $ticket->paused_at = null;
            $ticket->recalculateDueDates();
        }

        // Entering a stopped state: freeze the clock.
        if ($from->isActive() && ! $status->isActive()) {
            $ticket->paused_at = $now;
        }

        if ($status === TicketStatus::Resolved) {
            $ticket->resolved_at = $now;
        } elseif ($status === TicketStatus::Closed) {
            $ticket->closed_at = $now;
            $ticket->resolved_at ??= $now;
        } elseif ($status->isActive()) {
            $ticket->resolved_at = null;
            $ticket->closed_at = null;
        }

        if ($status->isFinished() && $ticket->resolution_breached_at === null && $ticket->isResolutionBreached()) {
            $ticket->resolution_breached_at = $now;
        }

        // The status email sent below already tells the client, so it stands in for
        // today's reminder and the digest picks the ticket up from tomorrow.
        if (in_array($status, [TicketStatus::PendingClient, TicketStatus::Resolved], true)) {
            $ticket->client_reminded_at = $now;
            $ticket->client_reminded_status = $status;
        }

        $ticket->status = $status;
        $ticket->save();

        $this->log($ticket, $actor, 'status_changed', ['from' => $from->value, 'to' => $status->value]);

        Notification::send(
            $this->clientWatchers($ticket)->reject(fn (User $user) => $user->id === $actor->id),
            new TicketStatusChanged($ticket, $from),
        );

        if ($actor->isClient()) {
            $this->staffWatchers($ticket)->each->notify(new TicketStatusChanged($ticket, $from));
        }
    }

    public function changePriority(Ticket $ticket, TicketPriority $priority, User $actor): void
    {
        $from = $ticket->priority;

        if ($from === $priority) {
            return;
        }

        $ticket->priority = $priority;
        $ticket->recalculateDueDates();
        $ticket->at_risk_notified_at = null;
        $ticket->save();

        $this->log($ticket, $actor, 'priority_changed', ['from' => $from->value, 'to' => $priority->value]);
    }

    public function assign(Ticket $ticket, ?User $assignee, User $actor): void
    {
        if ($ticket->assignee_id === $assignee?->id) {
            return;
        }

        $ticket->assignee()->associate($assignee);
        $ticket->save();

        $this->log($ticket, $actor, 'assigned', [
            'to' => $assignee?->id,
            'to_name' => $assignee?->name,
        ]);

        if ($assignee && $assignee->id !== $actor->id) {
            $assignee->notify(new TicketAssigned($ticket));
        }
    }

    /**
     * Add a reply or internal note.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function comment(Ticket $ticket, User $author, string $body, bool $internal = false, array $files = []): TicketComment
    {
        $internal = $internal && $author->isStaff();

        $comment = DB::transaction(function () use ($ticket, $author, $body, $internal, $files) {
            $comment = $ticket->comments()->create([
                'user_id' => $author->id,
                'body' => $body,
                'is_internal' => $internal,
            ]);

            $this->storeAttachments($ticket, $author, $files, $comment);

            if ($author->isStaff() && ! $internal && $ticket->first_responded_at === null) {
                $ticket->first_responded_at = now();
                $ticket->save();
            }

            $ticket->touch();

            return $comment;
        });

        $recipients = $author->isClient()
            ? $this->staffWatchers($ticket)
            : ($internal ? $this->staffWatchers($ticket) : $this->clientWatchers($ticket));

        Notification::send(
            $recipients->reject(fn (User $user) => $user->id === $author->id),
            new NewTicketComment($comment),
        );

        // A client answering a "pending client" ticket hands it back to the team.
        if ($author->isClient() && $ticket->status === TicketStatus::PendingClient) {
            $this->changeStatus($ticket, TicketStatus::InProgress, $author);
        }

        return $comment;
    }

    /**
     * Log work done on a ticket. Visible to everyone who can view the ticket.
     * The body is rich text; it is sanitized here since it is later rendered unescaped.
     *
     * @param  array<int, UploadedFile>  $files
     */
    public function logWork(Ticket $ticket, User $author, string $body, array $files = []): TicketWorkLog
    {
        $body = HtmlSanitizer::clean($body);

        $workLog = DB::transaction(function () use ($ticket, $author, $body, $files) {
            $workLog = $ticket->workLogs()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $this->storeAttachments($ticket, $author, $files, workLog: $workLog);

            $ticket->touch();

            return $workLog;
        });

        Notification::send(
            $this->clientWatchers($ticket)->reject(fn (User $user) => $user->id === $author->id),
            new NewTicketWorkLog($workLog),
        );

        return $workLog;
    }

    /**
     * Why a duplicate cannot be merged into the given ticket, or null when it can.
     */
    public function mergeError(Ticket $duplicate, Ticket $primary): ?string
    {
        return match (true) {
            $duplicate->is($primary) => __('A ticket cannot be merged into itself.'),
            $duplicate->isMerged() => __('This ticket is already merged into :reference.', ['reference' => $duplicate->mergedInto->reference]),
            $primary->isMerged() => __(':reference was itself merged into :target. Merge into :target instead.', [
                'reference' => $primary->reference,
                'target' => $primary->mergedInto->reference,
            ]),
            $duplicate->company_id !== $primary->company_id => __('Tickets can only be merged within the same client.'),
            $primary->status === TicketStatus::Closed => __(':reference is closed. Reopen it first, or merge into an open ticket.', ['reference' => $primary->reference]),
            default => null,
        };
    }

    /**
     * Fold a duplicate report into the ticket that carries the work. The duplicate
     * closes with its clock stopped, anything already merged into it moves along,
     * and its reporter follows the primary ticket from then on.
     */
    public function merge(Ticket $duplicate, Ticket $primary, User $actor): void
    {
        if ($error = $this->mergeError($duplicate, $primary)) {
            throw new InvalidArgumentException($error);
        }

        DB::transaction(function () use ($duplicate, $primary, $actor) {
            $now = now();

            // Keep it one level deep: earlier duplicates of this ticket follow it to the primary.
            $duplicate->merges()->update(['merged_into_id' => $primary->id]);

            $duplicate->merged_into_id = $primary->id;

            if (! $duplicate->status->isFinished()) {
                if ($duplicate->status->isActive()) {
                    $duplicate->paused_at = $now;
                }

                $duplicate->status = TicketStatus::Closed;
                $duplicate->closed_at = $now;
                $duplicate->resolved_at ??= $now;
            }

            $duplicate->save();
            $primary->touch();

            $this->log($duplicate, $actor, 'merged_into', ['reference' => $primary->reference]);
            $this->log($primary, $actor, 'merged_from', ['reference' => $duplicate->reference]);
        });

        if ($duplicate->reporter_id !== $actor->id) {
            $duplicate->reporter->notify(new TicketMerged($duplicate, $primary));
        }
    }

    /**
     * Undo a merge: the ticket stands on its own again and reopens.
     */
    public function unmerge(Ticket $duplicate, User $actor): void
    {
        $primary = $duplicate->mergedInto;

        if (! $primary) {
            return;
        }

        DB::transaction(function () use ($duplicate, $primary, $actor) {
            $duplicate->merged_into_id = null;
            $duplicate->save();

            $this->log($duplicate, $actor, 'unmerged', ['reference' => $primary->reference]);
            $this->log($primary, $actor, 'duplicate_removed', ['reference' => $duplicate->reference]);
        });

        $this->changeStatus($duplicate, TicketStatus::Open, $actor);
    }

    /**
     * Flag breached / at-risk tickets and auto-close stale resolved ones.
     *
     * @return array{response_breached: int, resolution_breached: int, at_risk: int, auto_closed: int}
     */
    public function checkSla(): array
    {
        $stats = ['response_breached' => 0, 'resolution_breached' => 0, 'at_risk' => 0, 'auto_closed' => 0];
        $now = now();

        Ticket::running()
            ->with(['assignee', 'project'])
            ->where(fn ($query) => $query
                ->whereNull('resolution_breached_at')
                ->orWhere(fn ($query) => $query->whereNull('first_responded_at')->whereNull('response_breached_at')))
            ->lazyById()
            ->each(function (Ticket $ticket) use (&$stats, $now) {
                if ($ticket->first_responded_at === null && $ticket->response_breached_at === null && $ticket->response_due_at->lessThan($now)) {
                    $ticket->response_breached_at = $now;
                    $ticket->save();
                    $this->raiseAlert($ticket, 'response_breached');
                    $stats['response_breached']++;
                }

                if ($ticket->resolution_breached_at !== null) {
                    return;
                }

                if ($ticket->resolution_due_at->lessThan($now)) {
                    $ticket->resolution_breached_at = $now;
                    $ticket->save();
                    $this->raiseAlert($ticket, 'resolution_breached');
                    $stats['resolution_breached']++;
                } elseif ($ticket->at_risk_notified_at === null && $ticket->resolutionProgress() >= config('sla.at_risk_threshold')) {
                    $ticket->at_risk_notified_at = $now;
                    $ticket->save();
                    $this->raiseAlert($ticket, 'at_risk');
                    $stats['at_risk']++;
                }
            });

        if (($days = config('sla.auto_close_days')) > 0) {
            Ticket::query()
                ->where('status', TicketStatus::Resolved)
                ->where('resolved_at', '<=', $now->subDays($days))
                ->lazyById()
                ->each(function (Ticket $ticket) use (&$stats, $now) {
                    $ticket->status = TicketStatus::Closed;
                    $ticket->closed_at = $now;
                    $ticket->save();
                    $this->log($ticket, null, 'auto_closed');
                    $stats['auto_closed']++;
                });
        }

        return $stats;
    }

    /**
     * Email each client a digest of their tickets waiting on them. Client admins
     * get every such ticket of their company, other clients only their own.
     * A ticket is included at most once a day per status, so one already sent
     * in the morning is left out of the afternoon digest unless its status
     * changed in between. Returns how many clients were emailed.
     */
    public function sendClientReminders(): int
    {
        // Midnight in the business timezone, converted back to the app timezone
        // the timestamps are stored in.
        $startOfToday = now()->setTimezone(config('sla.timezone') ?: config('app.timezone'))->startOfDay()->setTimezone(config('app.timezone'));

        $tickets = Ticket::query()
            ->whereIn('status', [TicketStatus::PendingClient, TicketStatus::Resolved])
            ->whereNull('merged_into_id')
            ->where(fn ($query) => $query
                ->whereNull('client_reminded_at')
                ->orWhere('client_reminded_at', '<', $startOfToday)
                ->orWhereColumn('client_reminded_status', '!=', 'status'))
            ->with('project')
            ->orderBy('status')
            ->orderBy('created_at')
            ->get();

        if ($tickets->isEmpty()) {
            return 0;
        }

        $sent = 0;
        $reminded = collect();

        User::query()
            ->where('role', UserRole::Client)
            ->where('is_active', true)
            ->whereIn('company_id', $tickets->pluck('company_id')->unique())
            ->each(function (User $client) use ($tickets, &$sent, $reminded) {
                $own = $tickets->filter(fn (Ticket $ticket) => $client->isClientAdmin()
                    ? $ticket->company_id === $client->company_id
                    : $ticket->reporter_id === $client->id);

                if ($own->isNotEmpty()) {
                    $client->notify(new ClientTicketsReminder($own->values()));
                    $reminded->push(...$own->modelKeys());
                    $sent++;
                }
            });

        // Grouped by status so each ticket records the status it was reminded about.
        $tickets->whereIn('id', $reminded->unique())
            ->groupBy(fn (Ticket $ticket) => $ticket->status->value)
            ->each(fn ($group, string $status) => Ticket::query()
                ->whereKey($group->modelKeys())
                ->update(['client_reminded_at' => now(), 'client_reminded_status' => $status]));

        return $sent;
    }

    /**
     * The reminder slot that should be sent now, or null if none is due. Lets a
     * cron that ticks every minute send each slot exactly once, and still send it
     * if the tick that fell on the slot itself was missed. Nothing is due on
     * weekends.
     */
    public function dueReminderSlot(?CarbonInterface $now = null): ?CarbonImmutable
    {
        $now = CarbonImmutable::instance($now ?? now())->setTimezone(config('sla.timezone') ?: config('app.timezone'));

        $slot = collect(config('sla.reminders.times'))
            ->flatMap(fn (string $time) => [
                $now->setTimeFromTimeString($time)->startOfMinute(),
                $now->subDay()->setTimeFromTimeString($time)->startOfMinute(),
            ])
            ->filter(fn (CarbonImmutable $slot) => $slot->lessThanOrEqualTo($now))
            ->sortDesc()
            ->first();

        // Give up on a slot missed by more than this, rather than sending a
        // morning digest in the afternoon.
        if (! $slot || $slot->isWeekend() || $slot->diffInMinutes($now) > 120) {
            return null;
        }

        return $slot;
    }

    /**
     * Re-time open tickets after the office hours or holidays change, so deadlines
     * reflect the new calendar. Tickets that already breached keep their deadline,
     * since the breach has been recorded. Returns how many tickets moved.
     */
    public function recalculateOpenDueDates(): int
    {
        $moved = 0;

        Ticket::query()
            ->unresolved()
            ->whereNull('resolution_breached_at')
            ->lazyById()
            ->each(function (Ticket $ticket) use (&$moved) {
                $ticket->recalculateDueDates();

                if ($ticket->isDirty(['response_due_at', 'resolution_due_at'])) {
                    Ticket::withoutTimestamps(fn () => $ticket->save());
                    $moved++;
                }
            });

        return $moved;
    }

    protected function raiseAlert(Ticket $ticket, string $type): void
    {
        $this->log($ticket, null, $type);

        Notification::send($this->staffWatchers($ticket), new SlaAlert($ticket, $type));
    }

    /**
     * Staff that should hear about a ticket: its assignee plus all admins.
     *
     * @return Collection<int, User>
     */
    protected function staffWatchers(Ticket $ticket): Collection
    {
        return User::admins()->get()
            ->when($ticket->assignee?->is_active, fn (Collection $users) => $users->push($ticket->assignee))
            ->unique('id')
            ->values();
    }

    /**
     * Clients following a ticket: its reporter plus the reporters of duplicates merged into it.
     *
     * @return Collection<int, User>
     */
    protected function clientWatchers(Ticket $ticket): Collection
    {
        return collect([$ticket->reporter])
            ->merge(User::query()->whereIn('id', $ticket->merges()->select('reporter_id'))->get())
            ->unique('id')
            ->values();
    }

    /**
     * Attachments are deleted one by one so their stored files are removed too.
     */
    public function deleteComment(TicketComment $comment): void
    {
        DB::transaction(function () use ($comment) {
            $comment->attachments->each->delete();
            $comment->delete();
        });
    }

    /**
     * Attachments are deleted one by one so their stored files are removed too.
     */
    public function deleteWorkLog(TicketWorkLog $workLog): void
    {
        DB::transaction(function () use ($workLog) {
            $workLog->attachments->each->delete();
            $workLog->delete();
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeAttachments(Ticket $ticket, User $user, array $files, ?TicketComment $comment = null, ?TicketWorkLog $workLog = null): void
    {
        $disk = config('sla.attachments.disk');
        $sequence = TicketAttachment::lastSequence($ticket);

        foreach ($files as $file) {
            $sequence++;

            $ticket->attachments()->create([
                'ticket_comment_id' => $comment?->id,
                'ticket_work_log_id' => $workLog?->id,
                'user_id' => $user->id,
                'disk' => $disk,
                'path' => $file->store("tickets/{$ticket->id}", $disk),
                'original_name' => TicketAttachment::displayName(
                    $ticket,
                    $sequence,
                    TicketAttachment::extensionFor($file->getClientOriginalExtension(), $file->guessExtension()),
                ),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    protected function log(Ticket $ticket, ?User $actor, string $type, array $properties = []): void
    {
        $ticket->activities()->create([
            'user_id' => $actor?->id,
            'type' => $type,
            'properties' => $properties ?: null,
        ]);
    }
}
