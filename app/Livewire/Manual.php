<?php

namespace App\Livewire;

use App\Enums\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('User Manual')]
class Manual extends Component
{
    #[Url(except: '')]
    public string $guide = '';

    public function mount(): void
    {
        if (! in_array($this->guide, array_column($this->guides, 'value'), true)) {
            $this->guide = $this->guides[0]->value;
        }
    }

    /**
     * Guides the current user may read, their own role's first. Each role
     * also sees the guides of the roles below it.
     *
     * @return list<UserRole>
     */
    #[Computed]
    public function guides(): array
    {
        return match (Auth::user()->role) {
            UserRole::Admin => [UserRole::Admin, UserRole::Internal, UserRole::Client],
            UserRole::Internal => [UserRole::Internal, UserRole::Client],
            UserRole::Client => [UserRole::Client],
        };
    }

    public function render(): View
    {
        return view('livewire.manual');
    }
}
