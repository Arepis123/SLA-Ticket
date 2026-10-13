<?php

namespace App\Livewire\Tickets;

use App\Enums\TicketCategory;
use App\Enums\TicketEnvironment;
use App\Enums\TicketPriority;
use App\Models\Project;
use App\Models\SlaPolicy;
use App\Models\Ticket;
use App\Services\TicketManager;
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
    use WithFileUploads;

    public string $project_id = '';

    public string $title = '';

    public string $category = 'bug';

    public string $priority = 'medium';

    public string $environment = 'production';

    public string $description = '';

    public string $steps_to_reproduce = '';

    /** @var array<int, UploadedFile> */
    public array $attachments = [];

    public function mount(): void
    {
        Gate::authorize('create', Ticket::class);

        if ($this->projects->count() === 1) {
            $this->project_id = (string) $this->projects->first()->id;
        }
    }

    /**
     * Active projects the user may report against.
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
            ->when($user->isClient(), fn (Builder $query) => $query->where('company_id', $user->company_id))
            ->with('company')
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

        session()->flash('notify', __('Ticket :ref submitted. We will respond shortly.', ['ref' => $ticket->reference]));

        $this->redirectRoute('tickets.show', $ticket, navigate: true);
    }
}
