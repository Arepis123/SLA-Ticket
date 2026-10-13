{{-- Horizontal bar list: $items is an array of ['label' => string, 'value' => int, 'color' => tailwind bg class, 'href' => ?string] --}}
@props(['items', 'empty' => __('No data yet.')])

@php($max = max(1, ...array_column($items, 'value') ?: [1]))

<div {{ $attributes->class('space-y-3') }}>
    @forelse ($items as $item)
        <div>
            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                @if (! empty($item['href']))
                    <a href="{{ $item['href'] }}" wire:navigate class="truncate text-zinc-700 hover:underline dark:text-zinc-300">{{ $item['label'] }}</a>
                @else
                    <span class="truncate text-zinc-700 dark:text-zinc-300">{{ $item['label'] }}</span>
                @endif
                <span class="font-medium tabular-nums text-zinc-900 dark:text-white">{{ $item['value'] }}</span>
            </div>
            <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10">
                <div class="h-full rounded-full {{ $item['color'] ?? 'bg-zinc-800 dark:bg-zinc-200' }}" style="width: {{ round($item['value'] / $max * 100, 1) }}%"></div>
            </div>
        </div>
    @empty
        <flux:text>{{ $empty }}</flux:text>
    @endforelse
</div>
