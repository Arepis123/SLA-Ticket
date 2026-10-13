<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use App\Models\Project;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Projects')]
class Projects extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $company = '';

    public ?int $editingId = null;

    public string $company_id = '';

    public string $name = '';

    public string $code = '';

    public string $url = '';

    public string $description = '';

    public bool $is_active = true;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'company'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Project>
     */
    #[Computed]
    public function projects(): LengthAwarePaginator
    {
        return Project::query()
            ->with('company')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('code', 'like', "%{$this->search}%")))
            ->when($this->company !== '', fn ($query) => $query->where('company_id', (int) $this->company))
            ->withCount(['tickets', 'tickets as open_tickets_count' => fn ($query) => $query->unresolved()])
            ->orderBy('name')
            ->paginate(15);
    }

    /**
     * @return Collection<int, Company>
     */
    #[Computed]
    public function companies(): Collection
    {
        return Company::query()->orderBy('name')->get();
    }

    public function create(): void
    {
        $this->resetForm();
        $this->company_id = $this->company;
        Flux::modal('project-form')->show();
    }

    public function edit(Project $project): void
    {
        $this->resetForm();
        $this->editingId = $project->id;
        $this->company_id = (string) $project->company_id;
        $this->name = $project->name;
        $this->code = (string) $project->code;
        $this->url = (string) $project->url;
        $this->description = (string) $project->description;
        $this->is_active = $project->is_active;

        Flux::modal('project-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:20'],
            'url' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ], attributes: ['company_id' => __('client')]);

        Project::updateOrCreate(['id' => $this->editingId], $validated);

        Flux::modal('project-form')->close();
        Flux::toast(variant: 'success', text: $this->editingId ? __('System updated.') : __('System created.'));
        $this->resetForm();
    }

    public function delete(Project $project): void
    {
        if ($project->tickets()->exists()) {
            Flux::toast(variant: 'danger', text: __('This system has tickets. Deactivate it instead.'));

            return;
        }

        $project->delete();
        Flux::toast(variant: 'success', text: __('System deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'company_id', 'name', 'code', 'url', 'description', 'is_active');
        $this->resetValidation();
    }
}
