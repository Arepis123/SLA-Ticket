<?php

namespace App\Livewire\Settings;

use App\Concerns\ManagesPushSubscriptions;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Notification settings')]
class Notifications extends Component
{
    use ManagesPushSubscriptions;

    /**
     * Turn off push everywhere, for devices the user no longer has to hand.
     */
    public function unsubscribeAll(): void
    {
        Auth::user()->pushSubscriptions()->delete();

        $this->dispatch('push-subscriptions-cleared');

        Flux::toast(text: __('Browser notifications turned off on all devices.'));
    }

    #[Computed]
    public function deviceCount(): int
    {
        return Auth::user()->pushSubscriptions()->count();
    }
}
