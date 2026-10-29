<?php

namespace App\Livewire\Tickets;

use App\Concerns\HasGuidedTour;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\Tour;
use App\Models\Project;
use App\Models\SavedView;
use App\Models\Ticket;
use App\Models\User;
use App\Support\TicketFilters;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Tickets')]
class Index extends Component
{
    use HasGuidedTour, WithPagination;

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

    /**
     * List state a saved view captures, with each value's default.
     *
     * @var array<string, string>
     */
    protected const VIEW_DEFAULTS = [
        'search' => '', 'status' => '', 'priority' => '', 'project' => '',
        'assignee' => '', 'sla' => '', 'sort' => 'created_at', 'direction' => 'desc',
    ];

    protected const MAX_SAVED_VIEWS = 20;

    // Save-view form
    public string $viewName = '';

    protected function guidedTour(): Tour
    {
        return Tour::Tickets;
    }

    /**
     * The support team lands on its own queue by default, and can still reach every
     * ticket by clearing the filters. Admins oversee the whole backlog and clients
     * have no assignee filter at all, so neither is narrowed down to "me".
     */
    public function mount(): void
    {
        if (! Auth::user()->isInternal()) {
            if ($this->assignee === 'me') {
                $this->assignee = '';
            }

            return;
        }

        if (! request()->has('assignee')) {
            $this->assignee = 'me';
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'priority', 'project', 'assignee', 'sla'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        if (! in_array($column, TicketFilters::SORTABLE, true)) {
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

    /**
     * The current filters as a query string, leaving out defaults. The assignee is
     * always kept: an empty one means "Anyone", which must survive the support
     * team's "Assigned to me" default when the view is opened again.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function currentFilters(): array
    {
        $filters = [];

        foreach (self::VIEW_DEFAULTS as $key => $default) {
            if ($key === 'assignee' || $this->{$key} !== $default) {
                $filters[$key] = $this->{$key};
            }
        }

        return $filters;
    }

    /**
     * @return Collection<int, SavedView>
     */
    #[Computed]
    public function savedViews(): Collection
    {
        return Auth::user()->savedViews()->orderBy('name')->get();
    }

    /**
     * The current filters in words, for the save dialog.
     *
     * @return list<string>
     */
    #[Computed]
    public function filterSummary(): array
    {
        $user = Auth::user();

        $summary = array_filter([
            $this->search !== '' ? __('Search “:term”', ['term' => $this->search]) : null,
            match ($this->status) {
                '' => null,
                'unresolved' => __('All unresolved'),
                default => TicketStatus::tryFrom($this->status)?->label(),
            },
            TicketPriority::tryFrom($this->priority)?->label(),
            $this->project !== '' ? $this->projects->firstWhere('id', (int) $this->project)?->name : null,
            match ($this->sla) {
                'breached' => __('SLA breached'),
                'at_risk' => __('SLA at risk'),
                default => null,
            },
            ! $user->isStaff() ? null : match ($this->assignee) {
                '' => __('Anyone'),
                'me' => __('Assigned to me'),
                'none' => __('Unassigned'),
                default => $this->staff->firstWhere('id', (int) $this->assignee)?->name,
            },
            $this->sort !== 'created_at' || $this->direction !== 'desc'
                ? __('Sorted by :column, :direction', [
                    'column' => str_replace('_', ' ', $this->sort === 'resolution_due_at' ? 'SLA' : $this->sort),
                    'direction' => $this->direction === 'asc' ? __('ascending') : __('descending'),
                ])
                : null,
        ]);

        return array_values($summary);
    }

    /**
     * The saved view matching the list as it is right now, if any.
     */
    #[Computed]
    public function activeView(): ?SavedView
    {
        $current = $this->currentFilters;
        ksort($current);

        return $this->savedViews->first(function (SavedView $view) use ($current) {
            $filters = $view->filters;
            ksort($filters);

            return $filters === $current;
        });
    }

    public function saveView(): void
    {
        $user = Auth::user();

        $this->validate([
            'viewName' => ['required', 'string', 'max:60', Rule::unique('saved_views', 'name')->where('user_id', $user->id)],
        ], [
            'viewName.unique' => __('You already have a view with that name.'),
        ], ['viewName' => __('name')]);

        if ($user->savedViews()->count() >= self::MAX_SAVED_VIEWS) {
            $this->addError('viewName', __('You can keep up to :max views. Delete one to make room.', ['max' => self::MAX_SAVED_VIEWS]));

            return;
        }

        $user->savedViews()->create(['name' => trim($this->viewName), 'filters' => $this->currentFilters]);

        $this->reset('viewName');
        unset($this->savedViews, $this->activeView);

        Flux::modal('save-view')->close();
        Flux::toast(variant: 'success', text: __('View saved.'));
    }

    public function deleteView(int $id): void
    {
        Auth::user()->savedViews()->findOrFail($id)->delete();

        unset($this->savedViews, $this->activeView);

        Flux::toast(variant: 'success', text: __('View deleted.'));
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

        $filters = new TicketFilters(
            search: $this->search,
            status: $this->status,
            priority: $this->priority,
            project: $this->project,
            assignee: $this->assignee,
            sla: $this->sla,
            sort: $this->sort,
            direction: $this->direction,
        );

        return $filters->apply(Ticket::query(), $user)
            ->with(['project', 'company', 'assignee', 'reporter', 'mergedInto:id,reference'])
            ->paginate(10);
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
