{{-- Progress list: $items is an array of ['label' => string, 'value' => int, 'color' => Flux color name, 'href' => ?string] --}}
@props(['items', 'empty' => __('No data yet.')])

@php($max = max(1, ...array_column($items, 'value') ?: [1]))

<div {{ $attributes->class('space-y-3') }}>
    @forelse ($items as $item)
        <div>
            <div class="mb-1 flex items-center justify-between gap-2">
                @if (! empty($item['href']))
                    <flux:link :href="$item['href']" variant="subtle" class="truncate text-sm" wire:navigate>{{ $item['label'] }}</flux:link>
                @else
                    <flux:text class="truncate">{{ $item['label'] }}</flux:text>
                @endif
                <flux:heading class="tabular-nums">{{ $item['value'] }}</flux:heading>
            </div>
            <flux:progress :value="round($item['value'] / $max * 100)" :color="$item['color'] ?? null" />
        </div>
    @empty
        <flux:text>{{ $empty }}</flux:text>
    @endforelse
</div>
