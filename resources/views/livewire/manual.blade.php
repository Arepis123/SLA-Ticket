@php
    $guideMeta = [
        'admin' => ['label' => __('Administrator guide'), 'icon' => 'shield-check'],
        'internal' => ['label' => __('Support team guide'), 'icon' => 'wrench-screwdriver'],
        'client' => ['label' => __('Client guide'), 'icon' => 'user-group'],
    ];
@endphp

<div class="mx-auto flex w-full max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('User Manual') }}</flux:heading>
        <flux:text class="mt-1">
            {{ __('How to use :app, step by step.', ['app' => config('app.name')]) }}
            @if (count($this->guides) > 1)
                {{ __('Use the tabs to switch between the guides available to your role.') }}
            @endif
        </flux:text>
    </div>

    @if (count($this->guides) > 1)
        <flux:tab.group>
            <flux:tabs wire:model="guide" scrollable scrollable:fade scrollable:scrollbar="hide">
                @foreach ($this->guides as $role)
                    <flux:tab :name="$role->value" :icon="$guideMeta[$role->value]['icon']">{{ $guideMeta[$role->value]['label'] }}</flux:tab>
                @endforeach
            </flux:tabs>

            @foreach ($this->guides as $role)
                <flux:tab.panel :name="$role->value">
                    @include('livewire.manual.'.$role->value)
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    @else
        <flux:heading size="lg" class="flex items-center gap-2">
            <flux:icon :name="$guideMeta[$this->guides[0]->value]['icon']" variant="mini" />
            {{ $guideMeta[$this->guides[0]->value]['label'] }}
        </flux:heading>

        @include('livewire.manual.'.$this->guides[0]->value)
    @endif
</div>
