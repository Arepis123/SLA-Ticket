<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use App\Models\Project;
use App\Models\Ticket;
use App\Services\SlaReport;
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

    #[Computed]
    public function report(): SlaReport
    {
        return SlaReport::fromInput($this->range['start'] ?? null, $this->range['end'] ?? null, $this->company, $this->project);
    }

    /**
     * Start and end of the reporting period.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function period(): array
    {
        return [$this->report->start, $this->report->end];
    }

    /**
     * Tickets opened in the reporting period.
     *
     * @return Collection<int, Ticket>
     */
    #[Computed]
    public function tickets(): Collection
    {
        return $this->report->tickets();
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function summary(): array
    {
        return $this->report->summary();
    }

    /**
     * The same figures split by client, system, priority, assignee and category.
     *
     * @return array<string, array{label: string, rows: list<array<string, mixed>>}>
     */
    #[Computed]
    public function breakdowns(): array
    {
        return $this->report->breakdowns();
    }

    /**
     * Tickets opened over the period, by day, week or month depending on its length.
     *
     * @return list<array{label: string, opened: int, breached: int}>
     */
    #[Computed]
    public function trend(): array
    {
        return $this->report->trend();
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
                __('Reference'), __('Client'), __('Reported by'), __('System'), __('Title'), __('Category'), __('Priority'), __('Status'),
                __('Assignee'), __('Opened'), __('First response'), __('Response due'), __('Resolved'), __('Fix type'), __('Resolution due'),
                __('SLA'), __('Resolution time (minutes)'),
            ], escape: '');

            foreach ($tickets as $ticket) {
                fputcsv($output, array_map($this->csvSafe(...), [
                    $ticket->reference,
                    $ticket->company->name,
                    $ticket->reporter->name ?? '',
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
                    $ticket->resolution_type?->label() ?? '',
                    $ticket->resolution_due_at?->format('Y-m-d H:i') ?? '',
                    $ticket->slaState()->label(),
                    (string) (SlaReport::resolutionMinutes($ticket) ?? ''),
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
     * Stop spreadsheet apps from running client-entered text as a formula.
     */
    protected function csvSafe(?string $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
