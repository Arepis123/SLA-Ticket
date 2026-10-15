<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketWorkLog;
use App\Models\User;
use App\Services\TicketManager;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class Show extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    // Staff management panel
    public string $status = '';

    public string $priority = '';

    public string $assignee_id = '';

    // Reply form
    public string $body = '';

    public bool $internal = false;

    public string $replyStatus = '';

    /** @var array<int, UploadedFile> */
    public array $files = [];

    // Work log form
    public string $workLogBody = '';

    // Merge form: id of the ticket this one duplicates
    public string $mergeTarget = '';

    public function mount(Ticket $ticket): void
    {
        Gate::authorize('view', $ticket);

        $this->ticket = $ticket;
        $this->syncPanel();
    }

    protected function syncPanel(): void
    {
        $this->status = $this->ticket->status->value;
        $this->priority = $this->ticket->priority->value;
        $this->assignee_id = (string) $this->ticket->assignee_id;
    }

    public function updatedStatus(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $status = TicketStatus::tryFrom($this->status);

        if ($status) {
            $manager->changeStatus($this->ticket, $status, Auth::user());
            $this->notify(__('Status changed to :status.', ['status' => $status->label()]));
        }

        $this->refreshTicket();
    }

    public function updatedPriority(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $priority = TicketPriority::tryFrom($this->priority);

        if ($priority) {
            $manager->changePriority($this->ticket, $priority, Auth::user());
            $this->notify(__('Priority changed to :priority. SLA targets recalculated.', ['priority' => $priority->label()]));
        }

        $this->refreshTicket();
    }

    public function updatedAssigneeId(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $assignee = $this->assignee_id === '' ? null : User::staff()->find((int) $this->assignee_id);
        $manager->assign($this->ticket, $assignee, Auth::user());
        $this->notify($assignee ? __('Assigned to :name.', ['name' => $assignee->name]) : __('Ticket unassigned.'));

        $this->refreshTicket();
    }

    public function assignToMe(TicketManager $manager): void
    {
        $this->ensureNotMerged();

        $this->assignee_id = (string) Auth::id();
        $this->updatedAssigneeId($manager);
    }

    public function reply(TicketManager $manager): void
    {
        Gate::authorize('comment', $this->ticket);

        $user = Auth::user();

        $this->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
            'replyStatus' => ['nullable', Rule::in($user->isStaff() ? array_column(TicketStatus::staffSelectable(), 'value') : [])],
            'files' => ['array', 'max:5'],
            'files.*' => ['file', 'max:'.config('sla.attachments.max_kb'), 'mimes:'.config('sla.attachments.mimes')],
        ], attributes: ['body' => __('message'), 'files.*' => __('attachment')]);

        $manager->comment($this->ticket, $user, $this->body, $this->internal, $this->files);

        if ($user->isStaff() && ($status = TicketStatus::tryFrom($this->replyStatus))) {
            $manager->changeStatus($this->ticket->refresh(), $status, $user);
        }

        $wasInternal = $user->isStaff() && $this->internal;

        $this->reset('body', 'internal', 'replyStatus', 'files');
        $this->notify($wasInternal ? __('Internal note added.') : __('Reply sent.'));
        $this->refreshTicket();
    }

    public function addWorkLog(TicketManager $manager): void
    {
        Gate::authorize('addWorkLog', $this->ticket);

        $this->validate([
            'workLogBody' => [
                'required',
                'string',
                'max:10000',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (trim(strip_tags((string) $value)) === '') {
                        $fail(__('Please enter a work log entry.'));
                    }
                },
            ],
        ], attributes: ['workLogBody' => __('work log entry')]);

        $manager->logWork($this->ticket, Auth::user(), $this->workLogBody);

        $this->reset('workLogBody');
        $this->notify(__('Work log entry added.'));
        $this->refreshTicket();
    }

    /**
     * Opening the work log tab clears its unread badge. Rendering is skipped on
     * purpose: Flux keeps the selected tab in JS state and only writes it to the
     * DOM, so letting Livewire morph here would re-hide the panel the user just
     * opened. The badge is cleared client-side instead.
     */
    public function markWorkLogsSeen(): void
    {
        $this->skipRender();

        if ($this->unseenWorkLogCount === 0) {
            return;
        }

        $this->ticket->markWorkLogReadBy(Auth::user());

        unset($this->unseenWorkLogCount);
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    /**
     * Client confirms the fix works.
     */
    public function confirmFix(TicketManager $manager): void
    {
        Gate::authorize('respondToResolution', $this->ticket);

        $manager->changeStatus($this->ticket, TicketStatus::Closed, Auth::user());
        $this->notify(__('Thanks for confirming! The ticket is now closed.'));
        $this->refreshTicket();
    }

    /**
     * Client reports the issue is not fixed.
     */
    public function reopen(TicketManager $manager): void
    {
        Gate::authorize('respondToResolution', $this->ticket);

        $this->validate(['body' => ['required', 'string', 'max:10000']], [
            'body.required' => __('Please tell us what is still not working before reopening.'),
        ]);

        $manager->comment($this->ticket, Auth::user(), $this->body, files: $this->files);
        $manager->changeStatus($this->ticket->refresh(), TicketStatus::Reopened, Auth::user());

        $this->reset('body', 'files');
        $this->notify(__('Ticket reopened. Our team has been notified.'));
        $this->refreshTicket();
    }

    /**
     * Fold this ticket into the one it duplicates.
     */
    public function merge(TicketManager $manager): void
    {
        Gate::authorize('merge', $this->ticket);

        $this->validate(['mergeTarget' => ['required', 'integer']], [
            'mergeTarget.required' => __('Pick the ticket this one duplicates.'),
        ]);

        $primary = Ticket::find((int) $this->mergeTarget);
        $error = $primary ? $manager->mergeError($this->ticket, $primary) : __('That ticket no longer exists.');

        if ($error) {
            $this->addError('mergeTarget', $error);

            return;
        }

        $manager->merge($this->ticket, $primary, Auth::user());

        Flux::modal('merge-ticket')->close();
        $this->reset('mergeTarget');
        $this->notify(__('Merged into :reference.', ['reference' => $primary->reference]));
        $this->refreshTicket();
    }

    public function unmerge(TicketManager $manager): void
    {
        Gate::authorize('merge', $this->ticket);

        $manager->unmerge($this->ticket, Auth::user());

        $this->notify(__('Unmerged. The ticket is open again.'));
        $this->refreshTicket();
    }

    /**
     * A merged duplicate is frozen; its work happens on the primary ticket.
     */
    protected function ensureNotMerged(): void
    {
        if ($this->ticket->isMerged()) {
            $this->syncPanel();

            abort(422, __('This ticket is merged. Unmerge it first.'));
        }
    }

    protected function refreshTicket(): void
    {
        $this->ticket->refresh();
        $this->syncPanel();
        unset($this->timeline, $this->ticketAttachments, $this->workLogs, $this->unseenWorkLogCount, $this->duplicates, $this->mergeCandidates);
    }

    protected function notify(string $message): void
    {
        Flux::toast(variant: 'success', text: $message);
    }

    /**
     * Comments and activity merged in chronological order.
     *
     * @return Collection<int, TicketComment|TicketActivity>
     */
    #[Computed]
    public function timeline(): Collection
    {
        $staff = Auth::user()->isStaff();

        $comments = $this->ticket->comments()
            ->with(['user', 'attachments'])
            ->when(! $staff, fn ($query) => $query->where('is_internal', false))
            ->get();

        $activities = $this->ticket->activities()
            ->with('user')
            ->when(! $staff, fn ($query) => $query->whereIn('type', [
                'created', 'status_changed', 'auto_closed', 'merged_into', 'merged_from', 'unmerged', 'duplicate_removed',
            ]))
            ->get();

        return $comments->toBase()
            ->merge($activities)
            ->sortBy(fn (TicketComment|TicketActivity $item) => [$item->created_at->getTimestamp(), $item instanceof TicketActivity ? 1 : 0])
            ->values();
    }

    /**
     * Files attached to the original report.
     *
     * @return EloquentCollection<int, TicketAttachment>
     */
    #[Computed]
    public function ticketAttachments(): EloquentCollection
    {
        return $this->ticket->attachments()->whereNull('ticket_comment_id')->get();
    }

    /**
     * Work log entries, visible to everyone who can view the ticket.
     *
     * @return EloquentCollection<int, TicketWorkLog>
     */
    #[Computed]
    public function workLogs(): EloquentCollection
    {
        return $this->ticket->workLogs()->with('user')->get();
    }

    /**
     * Entries added by someone else since this user last opened the work log
     * tab. Drives the tab badge, since the activity tab is the one shown first.
     */
    #[Computed]
    public function unseenWorkLogCount(): int
    {
        return $this->ticket->unreadWorkLogCountFor(Auth::user());
    }

    /**
     * Duplicate reports merged into this ticket.
     *
     * @return EloquentCollection<int, Ticket>
     */
    #[Computed]
    public function duplicates(): EloquentCollection
    {
        return $this->ticket->merges()->with('reporter')->orderBy('created_at')->get();
    }

    /**
     * Tickets this one could be merged into: same client, still open (or awaiting
     * confirmation), and not merged themselves.
     *
     * @return EloquentCollection<int, Ticket>
     */
    #[Computed]
    public function mergeCandidates(): EloquentCollection
    {
        return Ticket::query()
            ->where('company_id', $this->ticket->company_id)
            ->whereKeyNot($this->ticket->id)
            ->whereNull('merged_into_id')
            ->where('status', '!=', TicketStatus::Closed)
            ->latest()
            ->limit(200)
            ->get(['id', 'reference', 'title', 'status', 'project_id']);
    }

    /**
     * @return EloquentCollection<int, User>
     */
    #[Computed]
    public function staff(): EloquentCollection
    {
        return User::staff()->orderBy('name')->get();
    }

    public function render(): View
    {
        $this->ticket->loadMissing(['project', 'company', 'reporter', 'assignee', 'mergedInto']);

        return view('livewire.tickets.show')->title($this->ticket->reference.' · '.$this->ticket->title);
    }
}
