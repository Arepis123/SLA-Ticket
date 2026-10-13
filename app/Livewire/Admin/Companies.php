<?php

namespace App\Livewire\Admin;

use App\Models\Company;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Clients')]
class Companies extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $contact_person = '';

    public string $email = '';

    public string $phone = '';

    public bool $is_active = true;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, Company>
     */
    #[Computed]
    public function companies(): LengthAwarePaginator
    {
        return Company::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', "%{$this->search}%"))
            ->withCount(['projects', 'users', 'tickets', 'tickets as open_tickets_count' => fn ($query) => $query->unresolved()])
            ->orderBy('name')
            ->paginate(15);
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('company-form')->show();
    }

    public function edit(Company $company): void
    {
        $this->resetForm();
        $this->editingId = $company->id;
        $this->name = $company->name;
        $this->is_active = $company->is_active;
        $this->contact_person = (string) $company->contact_person;
        $this->email = (string) $company->email;
        $this->phone = (string) $company->phone;

        Flux::modal('company-form')->show();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ]);

        Company::updateOrCreate(['id' => $this->editingId], $validated);

        Flux::modal('company-form')->close();
        $this->dispatch('notify', message: $this->editingId ? __('Client updated.') : __('Client created.'));
        $this->resetForm();
    }

    public function delete(Company $company): void
    {
        if ($company->tickets()->exists()) {
            $this->dispatch('notify', message: __('This client has tickets. Deactivate it instead.'), variant: 'danger');

            return;
        }

        $company->delete();
        $this->dispatch('notify', message: __('Client deleted.'));
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'name', 'contact_person', 'email', 'phone', 'is_active');
        $this->resetValidation();
    }
}
