<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading level="2" class="sr-only">{{ __('Appearance settings') }}</flux:heading>

    <x-settings.layout :heading="__('Appearance')" :subheading=" __('Update the appearance settings for your account')">
        <div class="space-y-6">
            <div>
                <flux:heading>{{ __('Theme') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Saved on this device.') }}</flux:text>

                <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="mt-4">
                    <flux:radio value="light" icon="sun">{{ __('Light') }}</flux:radio>
                    <flux:radio value="dark" icon="moon">{{ __('Dark') }}</flux:radio>
                    <flux:radio value="system" icon="computer-desktop">{{ __('System') }}</flux:radio>
                </flux:radio.group>
            </div>

            <flux:separator variant="subtle" />

            <div>
                <flux:heading>{{ __('Text size') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Make text and controls smaller or larger. Saved to your account, so it follows you to every device.') }}</flux:text>

                <flux:radio.group variant="segmented" wire:model.live="fontSize" class="mt-4">
                    @foreach (\App\Enums\FontSize::cases() as $size)
                        <flux:radio :value="$size->value">{{ $size->label() }}</flux:radio>
                    @endforeach
                </flux:radio.group>
            </div>
        </div>
    </x-settings.layout>
</section>
