<?php

namespace App\Concerns;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use NotificationChannels\WebPush\PushSubscription;

/**
 * The Livewire end of the `pushToggle` / `pushPrompt` Alpine components in
 * resources/js/app.js, which hand over the browser's push subscription.
 */
trait ManagesPushSubscriptions
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

        Flux::toast(text: __('Browser notifications turned off on this device.'));
    }
}
