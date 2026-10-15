<?php

namespace App\Livewire\Admin;

use App\Enums\SlaState;
use App\Models\Company;
use App\Models\Project;
use App\Models\Ticket;
use App\Support\Duration;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Title('Reports')]
class Reports extends Component
{
    /**
     * Reporting period as Y-m-d dates. Tickets are included by the date they were opened.
     *
     * @var array{start?: string|null, end?: string|null}
     */
    public array $range = [];

    #[Url(except: '')]
    public string $company = '';

    #[Url(except: '')]
    public string $project = '';

    public string $tab = 'company';

    public function mount(): void
    {
        Gate::authorize('admin');

        $this->range = [
            'start' => now()->subDays(29)->toDateString(),
            'end' => now()->toDateString(),
        ];
    }

    public function updatedCompany(): void
    {
        $this->reset('project');
    }

    /**
     * Start and end of the reporting period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function period(): array
    {
        $end = $this->parseDate($this->range['end'] ?? null) ?? now();
        $start = $this->parseDate($this->range['start'] ?? null) ?? $end->subDays(29);

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->endOfDay()];
    }

    /**
     * Tickets opened in the reporting period.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function tickets(): Collection
    {
        return Ticket::query()
            ->with(['company', 'project', 'assignee'])
            ->whereBetween('created_at', $this->period)
            ->whereNull('merged_into_id') // a duplicate is the same issue, already counted once
            ->when($this->company !== '', fn (Builder $query) => $query->where('company_id', (int) $this->company))
            ->when($this->project !== '', fn (Builder $query) => $query->where('project_id', (int) $this->project))
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        $tickets = $this->tickets;

        $responded = $tickets->filter(fn (Ticket $ticket) => $ticket->first_responded_at !== null);
        $responseDecided = $tickets->filter(fn (Ticket $ticket) => $ticket->first_responded_at !== null || $ticket->isResponseBreached());
        $responseMet = $responseDecided->reject(fn (Ticket $ticket) => $ticket->isResponseBreached())->count();

        return [
            ...$this->summarise($tickets),
            'response_compliance' => $responseDecided->isEmpty() ? null : (int) round($responseMet / $responseDecided->count() * 100),
            'avg_response' => $responded->isEmpty() ? null : Duration::human(
                $responded->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->first_responded_at)),
            ),
        ];
    }

    /**
     * The same figures split by client, system, priority, assignee and category.
     *
     * @return array<string, array{label: string, rows: list<array<string, mixed>>}>
     */
    #[Computed]
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
    #[Computed]
    public function trend(): array
    {
        [$start, $end] = $this->period;
        $days = (int) $start->diffInDays($end) + 1;

        [$unit, $keyFormat, $labelFormat] = match (true) {
            $days <= 31 => ['day', 'Y-m-d', 'd M'],
            $days <= 183 => ['week', 'o-W', 'd M'],
            default => ['month', 'Y-m', 'M Y'],
        };

        $buckets = $this->tickets->groupBy(fn (Ticket $ticket) => $ticket->created_at->format($keyFormat));
        $points = [];

        for ($cursor = $start->startOf($unit); $cursor->lessThanOrEqualTo($end); $cursor = $cursor->add($unit, 1)) {
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
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::query()->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        return Project::query()
            ->when($this->company !== '', fn (Builder $query) => $query->where('company_id', (int) $this->company))
            ->with('company')
            ->orderBy('name')
            ->get();
    }

    /**
     * Download the tickets behind the report as a CSV file.
     */
    public function export(): StreamedResponse
    {
        Gate::authorize('admin');

        $tickets = $this->tickets;

        return response()->streamDownload(function () use ($tickets) {
            $output = fopen('php://output', 'w');

            // Byte order mark so Excel reads the file as UTF-8.
            fwrite($output, "\xEF\xBB\xBF");

            fputcsv($output, [
                __('Reference'), __('Client'), __('System'), __('Title'), __('Category'), __('Priority'), __('Status'),
                __('Assignee'), __('Opened'), __('First response'), __('Response due'), __('Resolved'), __('Resolution due'),
                __('SLA'), __('Resolution time (minutes)'),
            ], escape: '');

            foreach ($tickets as $ticket) {
                fputcsv($output, array_map($this->csvSafe(...), [
                    $ticket->reference,
                    $ticket->company->name,
                    $ticket->project->name,
                    $ticket->title,
                    $ticket->category->label(),
                    $ticket->priority->label(),
                    $ticket->status->label(),
                    $ticket->assignee->name ?? '',
                    $ticket->created_at->format('Y-m-d H:i'),
                    $ticket->first_responded_at?->format('Y-m-d H:i') ?? '',
                    $ticket->response_due_at?->format('Y-m-d H:i') ?? '',
                    $ticket->resolved_at?->format('Y-m-d H:i') ?? '',
                    $ticket->resolution_due_at?->format('Y-m-d H:i') ?? '',
                    $ticket->slaState()->label(),
                    (string) ($this->resolutionMinutes($ticket) ?? ''),
                ]), escape: '');
            }

            fclose($output);
        }, $this->exportFilename('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Download the report as a PDF with the company letterhead.
     */
    public function exportPdf(): StreamedResponse
    {
        Gate::authorize('admin');

        $logo = public_path(config('sla.company.logo'));

        $pdf = Pdf::loadView('pdf.report', [
            'companyName' => config('sla.company.name'),
            'logo' => is_file($logo) ? 'data:'.mime_content_type($logo).';base64,'.base64_encode(file_get_contents($logo)) : null,
            'period' => $this->period,
            'client' => $this->company !== '' ? $this->companies->firstWhere('id', (int) $this->company)?->name : null,
            'system' => $this->project !== '' ? $this->projects->firstWhere('id', (int) $this->project)?->name : null,
            'summary' => $this->summary,
            'breakdowns' => $this->breakdowns,
            'tickets' => $this->tickets,
            'generatedBy' => Auth::user()->name,
        ])->setPaper('a4', 'landscape')->setOption('isFontSubsettingEnabled', true);

        return response()->streamDownload(fn () => print ($pdf->output()), $this->exportFilename('pdf'), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    protected function exportFilename(string $extension): string
    {
        [$start, $end] = $this->period;

        return sprintf('%s-report-%s-to-%s.%s', Str::slug(config('app.name')), $start->toDateString(), $end->toDateString(), $extension);
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
        return $this->tickets
            ->groupBy($key)
            ->map(fn (Collection $tickets) => ['label' => $label($tickets->first()), ...$this->summarise($tickets)])
            ->sortByDesc('opened')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Ticket>  $tickets
     * @return array{opened: int, resolved: int, open: int, breached: int, compliance: int|null, avg_resolution: string|null}
     */
    protected function summarise(Collection $tickets): array
    {
        $finished = $tickets->filter(fn (Ticket $ticket) => $ticket->status->isFinished());
        $met = $finished->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Met)->count();
        $resolutionMinutes = $finished->map($this->resolutionMinutes(...))->filter(fn (?int $minutes) => $minutes !== null);

        return [
            'opened' => $tickets->count(),
            'resolved' => $finished->count(),
            'open' => $tickets->count() - $finished->count(),
            'breached' => $tickets->filter(fn (Ticket $ticket) => $ticket->slaState() === SlaState::Breached)->count(),
            'compliance' => $finished->isEmpty() ? null : (int) round($met / $finished->count() * 100),
            'avg_resolution' => $resolutionMinutes->isEmpty() ? null : Duration::human($resolutionMinutes->avg()),
        ];
    }

    /**
     * Minutes from opening to resolution, excluding time the SLA clock was paused.
     */
    protected function resolutionMinutes(Ticket $ticket): ?int
    {
        if ($ticket->resolved_at === null) {
            return null;
        }

        return (int) max(0, $ticket->created_at->diffInMinutes($ticket->resolved_at) - $ticket->paused_minutes);
    }

    protected function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return rescue(fn () => CarbonImmutable::parse($value), report: false);
    }

    /**
     * Stop spreadsheet apps from running client-entered text as a formula.
     */
    protected function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
