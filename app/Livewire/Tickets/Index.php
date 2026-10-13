<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Tickets')]
class Index extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $priority = '';

    #[Url(except: '')]
    public string $project = '';

    #[Url(except: '')]
    public string $assignee = '';

    #[Url(except: '')]
    public string $sla = '';

    #[Url(except: 'created_at')]
    public string $sort = 'created_at';

    #[Url(except: 'desc')]
    public string $direction = 'desc';

    /** @var list<string> */
    protected array $sortable = ['reference', 'title', 'priority', 'status', 'resolution_due_at', 'created_at', 'updated_at'];

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'priority', 'project', 'assignee', 'sla'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, $this->sortable, true)) {
            return;
        }

        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'status', 'priority', 'project', 'assignee', 'sla');
        $this->resetPage();
    }

    #[Computed]
    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->status !== '' || $this->priority !== ''
            || $this->project !== '' || $this->assignee !== '' || $this->sla !== '';
    }

    /**
     * @return LengthAwarePaginator<int, Ticket>
     */
    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        $user = Auth::user();

        return Ticket::query()
            ->visibleTo($user)
            ->with(['project', 'company', 'assignee', 'reporter'])
            ->when($this->search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('reference', 'like', "%{$this->search}%")
                ->orWhere('title', 'like', "%{$this->search}%")))
            ->when($this->status === 'unresolved', fn (Builder $query) => $query->unresolved())
            ->when(TicketStatus::tryFrom($this->status), fn (Builder $query, TicketStatus $status) => $query->where('status', $status))
            ->when(TicketPriority::tryFrom($this->priority), fn (Builder $query, TicketPriority $priority) => $query->where('priority', $priority))
            ->when($this->project !== '', fn (Builder $query) => $query->where('project_id', (int) $this->project))
            ->when($user->isStaff() && $this->assignee !== '', fn (Builder $query) => match ($this->assignee) {
                'me' => $query->where('assignee_id', $user->id),
                'none' => $query->whereNull('assignee_id'),
                default => $query->where('assignee_id', (int) $this->assignee),
            })
            ->when($this->sla === 'breached', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereNotNull('resolution_breached_at')
                ->orWhereNotNull('response_breached_at')
                ->orWhere(fn (Builder $query) => $query->running()->where('resolution_due_at', '<', now()))))
            ->when($this->sla === 'at_risk', fn (Builder $query) => $query
                ->running()
                ->whereNotNull('at_risk_notified_at')
                ->whereNull('resolution_breached_at')
                ->whereNull('response_breached_at')
                ->where('resolution_due_at', '>=', now()))
            ->when($this->sort === 'priority', fn (Builder $query) => $query->orderByRaw(
                "case priority when 'critical' then 1 when 'high' then 2 when 'medium' then 3 else 4 end ".($this->direction === 'asc' ? 'asc' : 'desc'),
            ), fn (Builder $query) => $query->orderBy(
                in_array($this->sort, $this->sortable, true) ? $this->sort : 'created_at',
                $this->direction === 'asc' ? 'asc' : 'desc',
            ))
            ->orderByDesc('id')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        $user = Auth::user();

        return Project::query()
            ->when($user->isClient(), fn (Builder $query) => $query->where('company_id', $user->company_id))
            ->with('company')
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function staff(): Collection
    {
        return User::staff()->orderBy('name')->get();
    }

    public function render(): View
    {
        return view('livewire.tickets.index');
    }
}
