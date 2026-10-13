@props([
    'label',
    'value',
    'icon' => null,
    'hint' => null,
    'color' => 'zinc',
    'href' => null,
])

@php
    $iconClasses = match ($color) {
        'red' => 'bg-red-100 text-red-600 dark:bg-red-500/15 dark:text-red-400',
        'amber' => 'bg-amber-100 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400',
        'green' => 'bg-green-100 text-green-600 dark:bg-green-500/15 dark:text-green-400',
        'sky' => 'bg-sky-100 text-sky-600 dark:bg-sky-500/15 dark:text-sky-400',
        'orange' => 'bg-orange-100 text-orange-600 dark:bg-orange-500/15 dark:text-orange-400',
        default => 'bg-zinc-100 text-zinc-600 dark:bg-white/10 dark:text-zinc-300',
    };
@endphp

<flux:card {{ $attributes->class('flex items-start gap-4') }}>
    @if ($icon)
        <div class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $iconClasses }}">
            <flux:icon :name="$icon" variant="outline" class="size-5" />
        </div>
    @endif

    <div class="min-w-0 flex-1">
        <flux:text>{{ $label }}</flux:text>
        <flux:heading size="xl" class="mt-1 tabular-nums">
            @if ($href)
                <a href="{{ $href }}" wire:navigate class="hover:underline">{{ $value }}</a>
            @else
                {{ $value }}
            @endif
        </flux:heading>
        @if ($hint)
            <flux:text size="sm" class="mt-1">{{ $hint }}</flux:text>
        @endif
    </div>
</flux:card>
