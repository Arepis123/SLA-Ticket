<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketActivity;
use App\Models\TicketAttachment;
use App\Models\TicketComment;
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

        $assignee = $this->assignee_id === '' ? null : User::staff()->find((int) $this->assignee_id);
        $manager->assign($this->ticket, $assignee, Auth::user());
        $this->notify($assignee ? __('Assigned to :name.', ['name' => $assignee->name]) : __('Ticket unassigned.'));

        $this->refreshTicket();
    }

    public function assignToMe(TicketManager $manager): void
    {
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

    protected function refreshTicket(): void
    {
        $this->ticket->refresh();
        $this->syncPanel();
        unset($this->timeline, $this->ticketAttachments);
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
            ->when(! $staff, fn ($query) => $query->whereIn('type', ['created', 'status_changed', 'auto_closed']))
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
     * @return EloquentCollection<int, User>
     */
    #[Computed]
    public function staff(): EloquentCollection
    {
        return User::staff()->orderBy('name')->get();
    }

    public function render(): View
    {
        $this->ticket->loadMissing(['project', 'company', 'reporter', 'assignee']);

        return view('livewire.tickets.show')->title($this->ticket->reference.' · '.$this->ticket->title);
    }
}
