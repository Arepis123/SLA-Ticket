<?php

namespace App\Livewire\Admin;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Users')]
class Users extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $role = '';

    #[Url(except: '')]
    public string $company = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $userRole = 'client';

    public string $company_id = '';

    public bool $is_client_admin = false;

    public string $password = '';

    public bool $is_active = true;

    public bool $sendResetLink = false;

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'company'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function users(): LengthAwarePaginator
    {
        return User::query()
            ->with('company')
            ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$this->search}%")
                ->orWhere('email', 'like', "%{$this->search}%")))
            ->when(UserRole::tryFrom($this->role), fn ($query, UserRole $role) => $query->where('role', $role))
            ->when($this->company !== '', fn ($query) => $query->where('company_id', (int) $this->company))
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
        Flux::modal('user-form')->show();
    }

    public function edit(User $user): void
    {
        $this->resetForm();
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->userRole = $user->role->value;
        $this->company_id = (string) $user->company_id;
        $this->is_client_admin = $user->is_client_admin;
        $this->is_active = $user->is_active;

        Flux::modal('user-form')->show();
    }

    public function save(): void
    {
        $isClient = $this->userRole === UserRole::Client->value;
        $isSelf = $this->editingId === Auth::id();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'userRole' => ['required', Rule::enum(UserRole::class)],
            'company_id' => [Rule::requiredIf($isClient), 'nullable', 'exists:companies,id'],
            'password' => [$this->editingId || $this->sendResetLink ? 'nullable' : 'required', 'string', Password::default()],
            'is_client_admin' => ['boolean'],
            'is_active' => ['boolean'],
        ], attributes: ['userRole' => __('role'), 'company_id' => __('client')]);

        $data = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'role' => $isSelf ? UserRole::Admin : UserRole::from($validated['userRole']),
            'company_id' => $isClient ? (int) $validated['company_id'] : null,
            'is_client_admin' => $isClient && $validated['is_client_admin'],
            'is_active' => $isSelf ? true : $validated['is_active'],
        ];

        if (filled($this->password)) {
            $data['password'] = $this->password;
        }

        if ($this->editingId) {
            User::findOrFail($this->editingId)->update($data);
        } else {
            $user = User::create([...$data, 'password' => $data['password'] ?? str()->random(40)]);
            $user->forceFill(['email_verified_at' => now()])->save();

            if ($this->sendResetLink) {
                PasswordBroker::sendResetLink(['email' => $user->email]);
            }
        }

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: $this->editingId ? __('User updated.') : __('User created.'));
        $this->resetForm();
    }

    public function sendPasswordReset(User $user): void
    {
        PasswordBroker::sendResetLink(['email' => $user->email]);
        Flux::toast(variant: 'success', text: __('Password reset link sent to :email.', ['email' => $user->email]));
    }

    public function toggleActive(User $user): void
    {
        if ($user->id === Auth::id()) {
            return;
        }

        $user->update(['is_active' => ! $user->is_active]);
        Flux::toast(variant: 'success', text: $user->is_active ? __('User activated.') : __('User deactivated.'));
    }

    protected function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'userRole', 'company_id', 'is_client_admin', 'password', 'is_active', 'sendResetLink');
        $this->resetValidation();
    }
}
