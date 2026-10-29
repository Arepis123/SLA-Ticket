<?php

namespace App\Services;

use App\Enums\SlaState;
use App\Models\Ticket;
use App\Support\Duration;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * SLA figures for tickets opened in a period, optionally for one client or
 * system. Shared by the reports page and the mobile API.
 */
class SlaReport
{
    /** @var Collection<int, Ticket>|null */
    protected ?Collection $tickets = null;

    public function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly ?int $companyId = null,
        public readonly ?int $projectId = null,
    ) {}

    /**
     * Build a report from loosely typed input such as Y-m-d strings. A missing
     * end means today, a missing start 30 days before the end, and swapped
     * dates are put back in order.
     */
    public static function fromInput(mixed $start, mixed $end, mixed $companyId = null, mixed $projectId = null): self
    {
        $end = self::parseDate($end) ?? now();
        $start = self::parseDate($start) ?? $end->subDays(29);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return new self(
            $start->startOfDay(),
            $end->endOfDay(),
            filled($companyId) ? (int) $companyId : null,
            filled($projectId) ? (int) $projectId : null,
        );
    }

    /**
     * Tickets opened in the reporting period.
     *
     * @return Collection<int, Ticket>
     */
    public function tickets(): Collection
    {
        return $this->tickets ??= Ticket::query()
            ->with(['company', 'project', 'reporter', 'assignee'])
            ->whereBetween('created_at', [$this->start, $this->end])
            ->whereNull('merged_into_id') // a duplicate is the same issue, already counted once
            ->when($this->companyId !== null, fn (Builder $query) => $query->where('company_id', $this->companyId))
            ->when($this->projectId !== null, fn (Builder $query) => $query->where('project_id', $this->projectId))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $tickets = $this->tickets();

        $responded = $tickets->filter(fn (Ticket $ticket) => $ticket->first_responded_at !== null);
        $responseDecided = $tickets->filter(fn (Ticket $ticket) => $ticket->first_responded_at !== null || $ticket->isResponseBreached());
        $responseMet = $responseDecided->reject(fn (Ticket $ticket) => $ticket->isResponseBreached())->count();
        $avgResponse = $responded->isEmpty() ? null : $responded->avg(
            fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_responded_at),
        );

        return [
            ...$this->summarise($tickets),
            'response_compliance' => $responseDecided->isEmpty() ? null : (int) round($responseMet / $responseDecided->count() * 100),
            'avg_response' => $avgResponse === null ? null : Duration::human($avgResponse),
            'avg_response_minutes' => $avgResponse === null ? null : (int) round($avgResponse),
        ];
    }

    /**
     * The same figures split by client, system, priority, assignee and category.
     *
     * @return array<string, array{label: string, rows: list<array<string, mixed>>}>
     */
    public function breakdowns(): array
    {
        return [
            'company' => [
                'label' => __('Client'),
                'rows' => $this->rows('company_id', fn (Ticket $ticket) => $ticket->company->name),
            ],
            'project' => [
                'label' => __('System'),
                'rows' => $this->rows('project_id', fn (Ticket $ticket) => $ticket->project->name.' · '.$ticket->company->name),
            ],
            'priority' => [
                'label' => __('Priority'),
                'rows' => $this->rows(fn (Ticket $ticket) => $ticket->priority->value, fn (Ticket $ticket) => $ticket->priority->label()),
            ],
            'assignee' => [
                'label' => __('Assignee'),
                'rows' => $this->rows('assignee_id', fn (Ticket $ticket) => $ticket->assignee->name ?? __('Unassigned')),
            ],
            'category' => [
                'label' => __('Category'),
                'rows' => $this->rows(fn (Ticket $ticket) => $ticket->category->value, fn (Ticket $ticket) => $ticket->category->label()),
            ],
        ];
    }

    /**
     * Tickets opened over the period, by day, week or month depending on its length.
     *
     * @return list<array{label: string, opened: int, breached: int}>
     */
    public function trend(): array
    {
        $days = (int) $this->start->diffInDays($this->end) + 1;

        [$unit, $keyFormat, $labelFormat] = match (true) {
            $days <= 31 => ['day', 'Y-m-d', 'd M'],
            $days <= 183 => ['week', 'o-W', 'd M'],
            default => ['month', 'Y-m', 'M Y'],
        };

        $buckets = $this->tickets()->groupBy(fn (Ticket $ticket) => $ticket->created_at->format($keyFormat));
        $points = [];

        for ($cursor = $this->start->startOf($unit); $cursor->lessThanOrEqualTo($this->end); $cursor = $cursor->add($unit, 1)) {
            $bucket = $buckets->get($cursor->format($keyFormat), collect());

            $points[] = [
                'label' => $cursor->format($labelFormat),
                'opened' => $bucket->count(),
                'breached' => $bucket->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Breached)->count(),
            ];
        }

        return $points;
    }

    /**
     * Minutes from opening to resolution, excluding time the SLA clock was paused.
     */
    public static function resolutionMinutes(Ticket $ticket): ?int
    {
        if ($ticket->resolved_at === null) {
            return null;
        }

        return (int) max(0, $ticket->created_at->diffInMinutes($ticket->resolved_at) - $ticket->paused_minutes);
    }

    /**
     * Group the period's tickets and summarise each group, busiest first.
     *
     * @param  (callable(Ticket): (int|string|null))|string  $key
     * @param  callable(Ticket): string  $label
     * @return list<array<string, mixed>>
     */
    protected function rows(callable|string $key, callable $label): array
    {
        return $this->tickets()
            ->groupBy($key)
            ->map(fn (Collection $tickets) => ['label' => $label($tickets->first()), ...$this->summarise($tickets)])
            ->sortByDesc('opened')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array{opened: int, resolved: int, open: int, breached: int, compliance: int|null, avg_resolution: string|null, avg_resolution_minutes: int|null}
     */
    protected function summarise(Collection $tickets): array
    {
        $finished = $tickets->filter(fn (Ticket $ticket) => $ticket->status->isFinished());
        $met = $finished->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Met)->count();
        $resolutionMinutes = $finished->map(self::resolutionMinutes(...))->filter(fn (?int $minutes) => $minutes !== null);

        return [
            'opened' => $tickets->count(),
            'resolved' => $finished->count(),
            'open' => $tickets->count() - $finished->count(),
            'breached' => $tickets->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Breached)->count(),
            'compliance' => $finished->isEmpty() ? null : (int) round($met / $finished->count() * 100),
            'avg_resolution' => $resolutionMinutes->isEmpty() ? null : Duration::human($resolutionMinutes->avg()),
            'avg_resolution_minutes' => $resolutionMinutes->isEmpty() ? null : (int) round($resolutionMinutes->avg()),
        ];
    }

    protected static function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(fn () => CarbonImmutable::parse($value), report: false);
    }
}
