<?php

namespace App\Livewire\Tickets;

use App\Concerns\HasGuidedTour;
use App\Enums\TicketCategory;
use App\Enums\TicketEnvironment;
use App\Enums\TicketPriority;
use App\Enums\Tour;
use App\Models\Project;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Services\TicketManager;
use App\Support\TourSample;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Report a bug')]
class Create extends Component
{
    use HasGuidedTour, WithFileUploads;

    public string $project_id = '';

    public string $title = '';

    public string $category = 'bug';

    public string $priority = 'medium';

    public string $environment = 'production';

    public string $description = '';

    public string $steps_to_reproduce = '';

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    protected function guidedTour(): Tour
    {
        return Tour::ReportBug;
    }

    /**
     * Fill the form with an example report, so the tour shows what a helpful one looks like.
     */
    protected function tourStarted(): void
    {
        $this->fill(TourSample::bugReport((string) $this->projects->first()?->id));
    }

    protected function tourEnded(): void
    {
        $this->reset('project_id', 'title', 'category', 'priority', 'environment', 'description', 'steps_to_reproduce');
        $this->selectOnlyProject();
    }

    public function mount(): void
    {
        Gate::authorize('create', Ticket::class);

        $this->selectOnlyProject();
    }

    protected function selectOnlyProject(): void
    {
        if ($this->projects->count() === 1) {
            $this->project_id = (string) $this->projects->first()->id;
        }
    }

    /**
     * Active projects of the client's own company.
     *
     * @return Collection<int, Project>
     */
    #[Computed]
    public function projects(): Collection
    {
        $user = Auth::user();

        return Project::query()
            ->where('is_active', true)
            ->whereHas('company', fn (Builder $query) => $query->where('is_active', true))
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{response: string, resolution: string}
     */
    #[Computed]
    public function targets(): array
    {
        $targets = SlaPolicy::targetsFor(TicketPriority::tryFrom($this->priority) ?? TicketPriority::Medium);

        return [
            'response' => SlaPolicy::formatMinutes($targets['response']),
            'resolution' => SlaPolicy::formatMinutes($targets['resolution']),
        ];
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function save(TicketManager $manager): void
    {
        Gate::authorize('create', Ticket::class);

        $validated = $this->validate([
            'project_id' => ['required', Rule::in($this->projects->pluck('id')->map(fn ($id) => (string) $id))],
            'title' => ['required', 'string', 'max:200'],
            'category' => ['required', Rule::enum(TicketCategory::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'environment' => ['required', Rule::enum(TicketEnvironment::class)],
            'description' => ['required', 'string', 'max:10000'],
            'steps_to_reproduce' => ['nullable', 'string', 'max:5000'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:'.config('sla.attachments.max_kb'), 'mimes:'.config('sla.attachments.mimes')],
        ], attributes: [
            'project_id' => __('system'),
            'attachments.*' => __('attachment'),
        ]);

        $ticket = $manager->create(Auth::user(), collect($validated)->except('attachments')->all(), $this->attachments);

        Flux::toast(variant: 'success', text: __('Ticket :ref submitted. We will respond shortly.', ['ref' => $ticket->reference]));

        $this->redirectRoute('tickets.show', $ticket, navigate: true);
    }
}
