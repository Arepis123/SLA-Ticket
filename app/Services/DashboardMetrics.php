<?php

namespace App\Services;

use App\Enums\SlaState;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Company;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use App\Support\Duration;
use Illuminate\Support\Collection;

/**
 * The dashboard's figures for one user, shared by the dashboard page and the
 * mobile API. Queries run once per instance.
 */
class DashboardMetrics
{
    /** @var Collection<int, Ticket>|null */
    protected ?Collection $unresolved = null;

    /** @var Collection<int, Ticket>|null */
    protected ?Collection $recentlyResolved = null;

    public function __construct(protected User $user) {}

    /**
     * All unresolved tickets visible to the user.
     *
     * @return Collection<int, Ticket>
     */
    public function unresolved(): Collection
    {
        return $this->unresolved ??= Ticket::query()
            ->visibleTo($this->user)
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
    public function recentlyResolved(): Collection
    {
        return $this->recentlyResolved ??= Ticket::query()
            ->visibleTo($this->user)
            ->whereIn('status', [TicketStatus::Resolved, TicketStatus::Closed])
            ->whereNull('merged_into_id')
            ->where('resolved_at', '>=', now()->subDays(30))
            ->get();
    }

    /**
     * Headline counts, with averages both in minutes and as a short label.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $unresolved = $this->unresolved();
        $states = $unresolved->map(fn (Ticket $ticket) => $ticket->slaState());
        $resolved = $this->recentlyResolved();
        $met = $resolved->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Met)->count();

        $responded = Ticket::query()
            ->visibleTo($this->user)
            ->whereNotNull('first_responded_at')
            ->where('created_at', '>=', now()->subDays(30))
            ->get(['created_at', 'first_responded_at']);

        $avgResponse = $responded->isEmpty() ? null : $responded->avg(
            fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_responded_at),
        );
        $avgResolution = $resolved->isEmpty() ? null : $resolved->avg(
            fn (Ticket $ticket) => max(0, $ticket->created_at->diffInMinutes($ticket->resolved_at) - $ticket->paused_minutes),
        );

        return [
            'open' => $unresolved->count(),
            'unassigned' => $unresolved->whereNull('assignee_id')->count(),
            'at_risk' => $states->filter(fn (SlaState $state) => $state === SlaState::AtRisk)->count(),
            'breached' => $states->filter(fn (SlaState $state) => $state === SlaState::Breached)->count(),
            'pending_client' => $unresolved->where('status', TicketStatus::PendingClient)->count(),
            'awaiting_confirmation' => Ticket::query()->visibleTo($this->user)->where('status', TicketStatus::Resolved)->count(),
            'resolved_30d' => $resolved->count(),
            'compliance' => $resolved->isEmpty() ? null : round($met / $resolved->count() * 100),
            'avg_response' => $avgResponse === null ? null : Duration::human($avgResponse),
            'avg_response_minutes' => $avgResponse === null ? null : (int) round($avgResponse),
            'avg_resolution' => $avgResolution === null ? null : Duration::human($avgResolution),
            'avg_resolution_minutes' => $avgResolution === null ? null : (int) round($avgResolution),
        ];
    }

    /**
     * Unresolved tickets that need attention first: breached, then closest to their deadline.
     *
     * @return Collection<int, Ticket>
     */
    public function attention(int $limit = 5): Collection
    {
        return $this->unresolved()
            ->filter(fn (Ticket $ticket) => $ticket->status !== TicketStatus::PendingClient)
            ->sortBy(fn (Ticket $ticket) => [
                $ticket->slaState() === SlaState::Breached ? 0 : 1,
                $ticket->resolution_due_at->getTimestamp(),
            ])
            ->take($limit)
            ->values();
    }

    /**
     * Unresolved tickets per priority, most urgent first.
     *
     * @return array<string, int>
     */
    public function byPriority(): array
    {
        return collect(TicketPriority::cases())
            ->mapWithKeys(fn (TicketPriority $priority) => [
                $priority->value => $this->unresolved()->where('priority', $priority)->count(),
            ])
            ->all();
    }

    /**
     * Systems with the most unresolved tickets.
     *
     * @return Collection<int, array{project: Project, company: Company, count: int}>
     */
    public function byProject(int $limit = 6): Collection
    {
        return $this->unresolved()
            ->groupBy('project_id')
            ->map(fn (Collection $tickets) => [
                'project' => $tickets->first()->project,
                'company' => $tickets->first()->company,
                'count' => $tickets->count(),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values();
    }

    /**
     * Tickets opened per day over the last $days days.
     *
     * @return array<int, array{date: string, label: string, count: int}>
     */
    public function trend(int $days = 14): array
    {
        $counts = Ticket::query()
            ->visibleTo($this->user)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->pluck('created_at')
            ->countBy(fn ($date) => $date->format('Y-m-d'));

        return collect(range($days - 1, 0))->map(function (int $daysAgo) use ($counts) {
            $day = now()->subDays($daysAgo);

            return [
                'date' => $day->format('Y-m-d'),
                'label' => $day->format('d M'),
                'count' => $counts[$day->format('Y-m-d')] ?? 0,
            ];
        })->all();
    }
}
