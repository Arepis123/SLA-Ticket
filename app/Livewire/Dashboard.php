<?php

namespace App\Livewire;

use App\Concerns\HasGuidedTour;
use App\Enums\TicketPriority;
use App\Enums\Tour;
use App\Models\Ticket;
use App\Services\DashboardMetrics;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    use HasGuidedTour;

    protected function guidedTour(): Tour
    {
        return Tour::Dashboard;
    }

    #[Computed]
    public function metrics(): DashboardMetrics
    {
        return new DashboardMetrics(Auth::user());
    }

    /**
     * All unresolved tickets visible to the user (evaluated once per render).
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function unresolved(): Collection
    {
        return $this->metrics->unresolved();
    }

    /**
     * Tickets resolved in the last 30 days, used for SLA performance.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function recentlyResolved(): Collection
    {
        return $this->metrics->recentlyResolved();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        return $this->metrics->stats();
    }

    /**
     * Unresolved tickets that need attention first: breached, then closest to their deadline.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function attention(): Collection
    {
        return $this->metrics->attention();
    }

    /**
     * @return array<int, array{label: string, value: int, color: string, href: string}>
     */
    #[Computed]
    public function byPriority(): array
    {
        $colors = [
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'zinc',
        ];

        $counts = $this->metrics->byPriority();

        return collect(TicketPriority::cases())->map(fn (TicketPriority $priority) => [
            'label' => $priority->label(),
            'value' => $counts[$priority->value],
            'color' => $colors[$priority->value],
            'href' => route('tickets.index', ['status' => 'unresolved', 'priority' => $priority->value]),
        ])->all();
    }

    /**
     * @return array<int, array{label: string, value: int, color: string, href: string}>
     */
    #[Computed]
    public function byProject(): array
    {
        return $this->metrics->byProject()
            ->map(fn (array $row) => [
                'label' => $row['project']->name.(Auth::user()->isStaff() ? ' · '.$row['company']->name : ''),
                'value' => $row['count'],
                'color' => 'sky',
                'href' => route('tickets.index', ['status' => 'unresolved', 'project' => $row['project']->id]),
            ])
            ->all();
    }

    /**
     * Tickets opened per day over the last 14 days.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    #[Computed]
    public function trend(): array
    {
        return $this->metrics->trend();
    }

    /**
     * Recent tickets for the client view.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function recentTickets(): Collection
    {
        return Ticket::query()
            ->visibleTo(Auth::user())
            ->with('project')
            ->latest('updated_at')
            ->take(8)
            ->get();
    }

    public function render(): View
    {
        return view(Auth::user()->isClient() ? 'livewire.dashboard.client' : 'livewire.dashboard.staff');
    }
}
