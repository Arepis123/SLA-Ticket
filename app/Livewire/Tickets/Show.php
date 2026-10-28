<?php

namespace App\Livewire\Tickets;

use App\Enums\ResolutionType;
use App\Enums\TicketCategory;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
use App\Models\TicketEntryView;
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

    // Corrections for when the client filed the ticket under the wrong system or type
    public string $project_id = '';

    public string $category = '';

    // Reply form
    public string $body = '';

    public bool $internal = false;

    public string $replyStatus = '';

    // How the ticket was dealt with; required when staff resolve or close it
    public string $resolutionType = '';

    /** @var array<int, UploadedFile> */
    public array $files = [];

    // Work log form
    public string $workLogBody = '';

    /** @var array<int, UploadedFile> */
    public array $workLogFiles = [];

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
        $this->project_id = (string) $this->ticket->project_id;
        $this->category = $this->ticket->category->value;
    }

    public function updatedStatus(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $status = TicketStatus::tryFrom($this->status);

        // Resolving or closing needs the fix type first; the modal calls resolve() to finish.
        if ($status && $status !== $this->ticket->status && $this->ticket->needsResolutionType($status)) {
            $this->reset('resolutionType');
            $this->resetValidation('resolutionType');
            Flux::modal('resolve-ticket')->show();

            return;
        }

        if ($status) {
            $manager->changeStatus($this->ticket, $status, Auth::user());
            $this->notify(__('Status changed to :status.', ['status' => $status->label()]));
        }

        $this->refreshTicket();
    }

    /**
     * Finish resolving or closing from the status dropdown once the fix type is chosen.
     */
    public function resolve(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $status = TicketStatus::tryFrom($this->status);

        if (! $status || ! $this->ticket->needsResolutionType($status)) {
            $this->cancelResolve();

            return;
        }

        $this->validate(
            ['resolutionType' => ['required', Rule::in(array_column(ResolutionType::for($status), 'value'))]],
            ['resolutionType.required' => __('Choose how this ticket was dealt with.')],
        );

        $manager->changeStatus($this->ticket, $status, Auth::user(), ResolutionType::from($this->resolutionType));

        Flux::modal('resolve-ticket')->close();
        $this->reset('resolutionType');
        $this->notify(__('Status changed to :status.', ['status' => $status->label()]));
        $this->refreshTicket();
    }

    /**
     * The resolve modal was dismissed: put the status dropdown back.
     */
    public function cancelResolve(): void
    {
        $this->reset('resolutionType');
        $this->resetValidation('resolutionType');
        $this->syncPanel();
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

    public function updatedProjectId(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $project = $this->projects->firstWhere('id', (int) $this->project_id);

        if ($project) {
            $manager->changeProject($this->ticket, $project, Auth::user());
            $this->notify(__('System changed to :system.', ['system' => $project->name]));
        }

        $this->refreshTicket();
    }

    public function updatedCategory(TicketManager $manager): void
    {
        Gate::authorize('manage', $this->ticket);
        $this->ensureNotMerged();

        $category = TicketCategory::tryFrom($this->category);

        if ($category) {
            $manager->changeCategory($this->ticket, $category, Auth::user());
            $this->notify(__('Type changed to :type.', ['type' => $category->label()]));
        }

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
        $replyStatus = $user->isStaff() ? TicketStatus::tryFrom($this->replyStatus) : null;
        $needsResolutionType = $replyStatus && $replyStatus !== $this->ticket->status && $this->ticket->needsResolutionType($replyStatus);

        $this->validate([
            'body' => ['required', 'string', 'max:10000'],
            'internal' => ['boolean'],
            'replyStatus' => ['nullable', Rule::in($user->isStaff() ? array_column(TicketStatus::staffSelectable(), 'value') : [])],
            'resolutionType' => [
                Rule::requiredIf($needsResolutionType),
                'nullable',
                Rule::in($replyStatus ? array_column(ResolutionType::for($replyStatus), 'value') : []),
            ],
            'files' => ['array', 'max:5'],
            'files.*' => ['file', 'max:'.config('sla.attachments.max_kb'), 'mimes:'.config('sla.attachments.mimes')],
        ], [
            'resolutionType.required' => __('Choose how this ticket was dealt with.'),
        ], ['body' => __('message'), 'files.*' => __('attachment'), 'resolutionType' => __('fix type')]);

        $manager->comment($this->ticket, $user, $this->body, $this->internal, $this->files);

        if ($replyStatus) {
            $manager->changeStatus($this->ticket->refresh(), $replyStatus, $user, ResolutionType::tryFrom($this->resolutionType));
        }

        $wasInternal = $user->isStaff() && $this->internal;

        $this->reset('body', 'internal', 'replyStatus', 'resolutionType', 'files');
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
            'workLogFiles' => ['array', 'max:5'],
            'workLogFiles.*' => ['file', 'max:'.config('sla.attachments.max_kb'), 'mimes:'.config('sla.attachments.mimes')],
        ], attributes: ['workLogBody' => __('work log entry'), 'workLogFiles.*' => __('attachment')]);

        $manager->logWork($this->ticket, Auth::user(), $this->workLogBody, $this->workLogFiles);

        $this->reset('workLogBody', 'workLogFiles');
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

    /**
     * The page reports entries that sat on screen long enough to count as seen
     * (see entrySeen in resources/js/app.js). Only keys this user could be
     * shown and has not been recorded for are kept. Skips rendering for the
     * same reason as markWorkLogsSeen().
     *
     * @param  array<int, mixed>  $keys
     */
    public function markEntriesSeen(array $keys): void
    {
        $this->skipRender();

        $keys = array_values(array_filter(
            array_slice($keys, 0, 200),
            fn (mixed $key) => is_string($key) && isset($this->unseenEntryKeys[$key]),
        ));

        if ($keys !== []) {
            $this->ticket->markEntriesSeenBy(Auth::user(), $keys);
        }
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function deleteComment(int $commentId, TicketManager $manager): void
    {
        $comment = $this->ticket->comments()->findOrFail($commentId);

        Gate::authorize('deleteComment', [$this->ticket, $comment]);

        $manager->deleteComment($comment);
        $this->notify($comment->is_internal ? __('Internal note deleted.') : __('Reply deleted.'));
        $this->refreshTicket();
    }

    public function deleteWorkLog(int $workLogId, TicketManager $manager): void
    {
        $workLog = $this->ticket->workLogs()->findOrFail($workLogId);

        Gate::authorize('deleteWorkLog', [$this->ticket, $workLog]);

        $manager->deleteWorkLog($workLog);
        $this->notify(__('Work log entry deleted.'));
        $this->refreshTicket();
    }

    public function removeWorkLogFile(int $index): void
    {
        unset($this->workLogFiles[$index]);
        $this->workLogFiles = array_values($this->workLogFiles);
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
        unset($this->timeline, $this->ticketAttachments, $this->workLogs, $this->unseenWorkLogCount, $this->unseenEntryKeys, $this->entrySeenBy, $this->duplicates, $this->mergeCandidates);
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
                'created', 'status_changed', 'project_changed', 'category_changed', 'auto_closed', 'merged_into', 'merged_from', 'unmerged', 'duplicate_removed',
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
        return $this->ticket->attachments()->whereNull('ticket_comment_id')->whereNull('ticket_work_log_id')->get();
    }

    /**
     * Work log entries, visible to everyone who can view the ticket.
     *
     * @return EloquentCollection<int, TicketWorkLog>
     */
    #[Computed]
    public function workLogs(): EloquentCollection
    {
        return $this->ticket->workLogs()->with(['user', 'attachments'])->get();
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
     * Entries on this page the current user has not been recorded as seeing,
     * keyed like "work_log-12". Their own entries are left out, and nothing is
     * tracked once the ticket is closed.
     *
     * @return array<string, true>
     */
    #[Computed]
    public function unseenEntryKeys(): array
    {
        if ($this->ticket->status === TicketStatus::Closed) {
            return [];
        }

        $user = Auth::user();

        $seen = $this->ticket->entryViews()
            ->where('user_id', $user->id)
            ->get(['entry_type', 'entry_id'])
            ->mapWithKeys(fn (TicketEntryView $view) => [$view->key() => true]);

        return $this->timeline->toBase()
            ->merge($this->workLogs)
            ->reject(fn (TicketComment|TicketActivity|TicketWorkLog $entry) => $entry->user_id === $user->id)
            ->mapWithKeys(fn (TicketComment|TicketActivity|TicketWorkLog $entry) => [TicketEntryView::keyFor($entry) => true])
            ->diffKeys($seen)
            ->all();
    }

    /**
     * The data-seen value for an entry the page should watch, or null to leave it alone.
     */
    public function seenMarker(TicketComment|TicketActivity|TicketWorkLog $entry): ?string
    {
        $key = TicketEntryView::keyFor($entry);

        return isset($this->unseenEntryKeys[$key]) ? $key : null;
    }

    /**
     * Who has seen each entry, keyed like unseenEntryKeys. System Admins only.
     *
     * @return Collection<string, EloquentCollection<int, TicketEntryView>>
     */
    #[Computed]
    public function entrySeenBy(): Collection
    {
        if (Gate::denies('viewEntrySeen', $this->ticket)) {
            return collect();
        }

        return $this->ticket->entryViews()
            ->with('user')
            ->orderBy('seen_at')
            ->get()
            ->groupBy(fn (TicketEntryView $view) => $view->key());
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
     * Systems this ticket can be moved to: the client's active ones, plus the
     * current one so the picker always shows it.
     *
     * @return EloquentCollection<int, Project>
     */
    #[Computed]
    public function projects(): EloquentCollection
    {
        return Project::query()
            ->where('company_id', $this->ticket->company_id)
            ->where(fn ($query) => $query->where('is_active', true)->orWhereKey($this->ticket->project_id))
            ->orderBy('name')
            ->get(['id', 'company_id', 'name']);
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
