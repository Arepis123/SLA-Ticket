<?php

namespace App\Livewire;

use App\Concerns\ManagesPushSubscriptions;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Card offering browser notifications to users who have not switched them on
 * on any device. The browser then hides it where notifications are blocked or
 * the user said "Not now" (see `pushPrompt` in resources/js/app.js).
 */
class PushPrompt extends Component
{
    use ManagesPushSubscriptions;

    public function render(): View
    {
        return view('livewire.push-prompt', [
            'offer' => filled(config('webpush.vapid.public_key')) && ! Auth::user()->pushSubscriptions()->exists(),
        ]);
    }
}
