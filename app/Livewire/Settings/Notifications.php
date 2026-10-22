<?php

namespace App\Livewire\Settings;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use NotificationChannels\WebPush\PushSubscription;

#[Title('Notification settings')]
class Notifications extends Component
{
    /**
     * Save the browser's push subscription, as returned by PushSubscription.toJSON().
     *
     * @param  array<string, mixed>  $subscription
     */
    public function subscribe(array $subscription, string $contentEncoding = 'aes128gcm'): void
    {
        $data = Validator::make([...$subscription, 'contentEncoding' => $contentEncoding], [
            'endpoint' => ['required', 'url:https', 'max:'.PushSubscription::ENDPOINT_MAX_LENGTH],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['required', Rule::in(['aesgcm', 'aes128gcm'])],
        ])->validate();

        Auth::user()->updatePushSubscription($data['endpoint'], $data['keys']['p256dh'], $data['keys']['auth'], $data['contentEncoding']);

        unset($this->deviceCount);

        Flux::toast(variant: 'success', text: __('Browser notifications enabled on this device.'));
    }

    /**
     * Whether this browser's subscription is still on file. It may not be after
     * "turn off on all devices" or once the push service expired it.
     */
    public function isSubscribed(string $endpoint): bool
    {
        return Auth::user()->pushSubscriptions()->where('endpoint', $endpoint)->exists();
    }

    public function unsubscribe(string $endpoint): void
    {
        Auth::user()->deletePushSubscription($endpoint);

        unset($this->deviceCount);

        Flux::toast(text: __('Browser notifications turned off on this device.'));
    }

    /**
     * Turn off push everywhere, for devices the user no longer has to hand.
     */
    public function unsubscribeAll(): void
    {
        Auth::user()->pushSubscriptions()->delete();

        unset($this->deviceCount);
        $this->dispatch('push-subscriptions-cleared');

        Flux::toast(text: __('Browser notifications turned off on all devices.'));
    }

    #[Computed]
    public function deviceCount(): int
    {
        return Auth::user()->pushSubscriptions()->count();
    }
}
