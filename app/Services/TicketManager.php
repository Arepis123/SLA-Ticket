<?php

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Notifications\NewTicketComment;
use App\Notifications\NewTicketWorkLog;
use App\Notifications\SlaAlert;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketCreated;
use App\Notifications\TicketStatusChanged;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

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

        // Leaving a stopped state: credit the paused time and push due dates back.
        if (! $from->isActive() && $status->isActive() && $ticket->paused_at) {
            $ticket->paused_minutes += (int) $ticket->paused_at->diffInMinutes($now);
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

        $ticket->status = $status;
        $ticket->save();

        $this->log($ticket, $actor, 'status_changed', ['from' => $from->value, 'to' => $status->value]);

        if ($ticket->reporter_id !== $actor->id) {
            $ticket->reporter->notify(new TicketStatusChanged($ticket, $from));
        }

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
            : ($internal ? $this->staffWatchers($ticket) : collect([$ticket->reporter]));

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
     */
    public function logWork(Ticket $ticket, User $author, string $body): TicketWorkLog
    {
        $body = HtmlSanitizer::clean($body);

        $workLog = DB::transaction(function () use ($ticket, $author, $body) {
            $workLog = $ticket->workLogs()->create([
                'user_id' => $author->id,
                'body' => $body,
            ]);

            $ticket->touch();

            return $workLog;
        });

        Notification::send(
            collect([$ticket->reporter])->reject(fn (User $user) => $user->id === $author->id),
            new NewTicketWorkLog($workLog),
        );

        return $workLog;
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
     * @param  array<int, UploadedFile>  $files
     */
    protected function storeAttachments(Ticket $ticket, User $user, array $files, ?TicketComment $comment = null): void
    {
        $disk = config('sla.attachments.disk');

        foreach ($files as $file) {
            $ticket->attachments()->create([
                'ticket_comment_id' => $comment?->id,
                'user_id' => $user->id,
                'disk' => $disk,
                'path' => $file->store("tickets/{$ticket->id}", $disk),
                'original_name' => $file->getClientOriginalName(),
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
