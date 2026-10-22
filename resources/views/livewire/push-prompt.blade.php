<div>
    @if ($offer)
        <div wire:ignore x-data="pushPrompt(@js(config('webpush.vapid.public_key')))" x-show="show" x-cloak x-transition
            class="fixed inset-x-4 bottom-4 z-40 sm:left-auto sm:w-96" role="dialog" aria-labelledby="push-prompt-heading">
            <flux:card class="space-y-3 bg-white shadow-lg dark:bg-zinc-800">
                <div class="flex items-start gap-3">
                    <flux:icon.bell-alert class="mt-0.5 size-5 shrink-0 text-accent-content" />
                    <div>
                        <flux:heading id="push-prompt-heading">{{ __('Get notified about ticket updates?') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('See a popup on this device when a ticket changes status or gets a reply, even when this app is closed. You can turn it off any time in Settings → Notifications.') }}</flux:text>
                    </div>
                </div>
                <div class="flex justify-end gap-2">
                    <flux:button size="sm" variant="ghost" x-on:click="later()" x-bind:disabled="busy">{{ __('Not now') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="bell" x-on:click="enable()" x-bind:disabled="busy">{{ __('Enable') }}</flux:button>
                </div>
            </flux:card>
        </div>
    @endif
</div>
