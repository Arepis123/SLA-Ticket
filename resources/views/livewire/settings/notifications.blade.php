<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Notification settings') }}</flux:heading>

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Choose how you hear about ticket updates')">
        <div class="space-y-6">
            <div>
                <flux:heading>{{ __('Email') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Ticket updates are always sent to :email.', ['email' => auth()->user()->email]) }}</flux:text>
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>{{ __('Browser notifications') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Get a popup on this device when a ticket changes status, gets a reply or nears its SLA, even when the app is not open.') }}</flux:text>

                @if (blank(config('webpush.vapid.public_key')))
                    <flux:callout class="mt-4" variant="warning" icon="exclamation-triangle" :text="__('Browser notifications are not set up on this server yet.')" />
                @else
                    <div class="mt-4" wire:ignore x-data="pushToggle(@js(config('webpush.vapid.public_key')))"
                        x-on:push-subscriptions-cleared.window="cleared()">
                        <div x-show="state === 'off'" x-cloak>
                            <flux:button variant="primary" icon="bell" x-on:click="enable()" x-bind:disabled="busy">
                                {{ __('Enable on this device') }}
                            </flux:button>
                        </div>

                        <div x-show="state === 'on'" x-cloak class="flex flex-wrap items-center gap-3">
                            <flux:badge color="green" icon="check">{{ __('On for this device') }}</flux:badge>
                            <flux:button size="sm" x-on:click="disable()" x-bind:disabled="busy">{{ __('Turn off') }}</flux:button>
                        </div>

                        <flux:callout x-show="state === 'denied'" x-cloak variant="warning" icon="bell-slash"
                            :text="__('Notifications are blocked for this site. Allow them in your browser\'s site settings, then reload this page.')" />

                        <flux:callout x-show="state === 'ios-install'" x-cloak icon="device-phone-mobile"
                            :text="__('On iPhone and iPad, tap Share → Add to Home Screen, then open the app from your home screen and enable notifications here.')" />

                        <flux:callout x-show="state === 'unsupported'" x-cloak variant="warning" icon="exclamation-triangle"
                            :text="__('This browser does not support push notifications.')" />

                        <flux:callout x-show="state === 'insecure'" x-cloak variant="warning" icon="lock-open"
                            :text="__('Browser notifications need a secure (https) connection.')" />
                    </div>
                @endif

                @if ($this->deviceCount > 0)
                    <div class="mt-4 flex flex-wrap items-center gap-3">
                        <flux:text>{{ trans_choice('{1} Enabled on 1 device.|[2,*] Enabled on :count devices.', $this->deviceCount) }}</flux:text>
                        <flux:button size="sm" variant="ghost" wire:click="unsubscribeAll"
                            wire:confirm="{{ __('Turn off browser notifications on every device?') }}">
                            {{ __('Turn off on all devices') }}
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>
    </x-settings.layout>
</section>
