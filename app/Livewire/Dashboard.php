<?php

namespace App\Livewire;

use App\Enums\SlaState;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Support\Duration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * All unresolved tickets visible to the user (evaluated once per render).
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function unresolved(): Collection
    {
        return Ticket::query()
            ->visibleTo(Auth::user())
            ->unresolved()
            ->with(['project', 'company', 'assignee'])
            ->orderBy('resolution_due_at')
            ->get();
    }

    /**
     * Tickets resolved in the last 30 days, used for SLA performance.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function recentlyResolved(): Collection
    {
        return Ticket::query()
            ->visibleTo(Auth::user())
            ->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->where('resolved_at', '>=', now()->subDays(30))
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function stats(): array
    {
        $states = $this->unresolved->map(fn (Ticket $ticket) => $ticket->slaState());
        $resolved = $this->recentlyResolved;
        $met = $resolved->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Met)->count();

        $responded = Ticket::query()
            ->visibleTo(Auth::user())
            ->whereNotNull('first_responded_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['created_at', 'first_responded_at']);

        return [
            'open' => $this->unresolved->count(),
            'unassigned' => $this->unresolved->whereNull('assignee_id')->count(),
            'at_risk' => $states->filter(fn (SlaState $state) => $state === SlaState::AtRisk)->count(),
            'breached' => $states->filter(fn (SlaState $state) => $state === SlaState::Breached)->count(),
            'pending_client' => $this->unresolved->where('status', TicketStatus::PendingClient)->count(),
            'awaiting_confirmation' => Ticket::query()->visibleTo(Auth::user())->where('status', TicketStatus::Resolved)->count(),
            'resolved_30d' => $resolved->count(),
            'compliance' => $resolved->isEmpty() ? null : round($met / $resolved->count() * 100),
            'avg_response' => $responded->isEmpty() ? null : Duration::human(
                $responded->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_responded_at)),
            ),
            'avg_resolution' => $resolved->isEmpty() ? null : Duration::human(
                $resolved->avg(fn (Ticket $ticket) => max(0, $ticket->created_at->diffInMinutes($ticket->resolved_at) - $ticket->paused_minutes)),
            ),
        ];
    }

    /**
     * Unresolved tickets that need attention first: breached, then closest to their deadline.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function attention(): Collection
    {
        return $this->unresolved
            ->filter(fn (Ticket $ticket) => $ticket->status !== TicketStatus::PendingClient)
            ->sortBy(fn (Ticket $ticket) => [
                $ticket->slaState() === SlaState::Breached ? 0 : 1,
                $ticket->resolution_due_at->getTimestamp(),
            ])
            ->take(8)
            ->values();
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

        return collect(TicketPriority::cases())->map(fn (TicketPriority $priority) => [
            'label' => $priority->label(),
            'value' => $this->unresolved->where('priority', $priority)->count(),
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
        return $this->unresolved
            ->groupBy('project_id')
            ->map(fn (Collection $tickets) => [
                'label' => $tickets->first()->project->name.(Auth::user()->isStaff() ? ' · '.$tickets->first()->company->name : ''),
                'value' => $tickets->count(),
                'color' => 'sky',
                'href' => route('tickets.index', ['status' => 'unresolved', 'project' => $tickets->first()->project_id]),
            ])
            ->sortByDesc('value')
            ->take(6)
            ->values()
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
        $counts = Ticket::query()
            ->visibleTo(Auth::user())
            ->where('created_at', '>=', now()->subDays(13)->startOfDay())
            ->pluck('created_at')
            ->countBy(fn ($date) => $date->format('Y-m-d'));

        return collect(range(13, 0))->map(function (int $daysAgo) use ($counts) {
            $day = now()->subDays($daysAgo);

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('d M'),
                'count' => $counts[$day->format('Y-m-d')] ?? 0,
            ];
        })->all();
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
