@props(['steps'])

{{--
    Guided tour over the current page, driven by `tour` in resources/js/app.js.
    Each step: ['title' => ..., 'body' => ..., 'target' => data-tour name, or a list
    of them where the first one on screen wins, 'side' => 'bottom'|'top'|'right'|'left'].
    Must sit inside a Livewire component with a finishTour() action.
--}}
<div wire:ignore x-data="tour(@js($steps))" x-show="open" x-cloak x-trap="open"
    x-on:keydown.escape.window="open && finish()"
    x-on:keydown.right.window="open && next()"
    x-on:keydown.left.window="open && back()"
    class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="tour-title" aria-describedby="tour-body">
    {{-- Catches clicks so the page cannot be used mid-tour, and darkens it when nothing is highlighted. --}}
    <div class="absolute inset-0 transition-colors" x-bind:class="spot ? 'bg-transparent' : 'bg-black/60'"></div>

    {{-- The highlight: a clear box whose huge shadow darkens the rest of the screen. --}}
    <div x-show="spot" class="pointer-events-none absolute rounded-lg shadow-[0_0_0_9999px_rgb(0_0_0/0.6)] ring-2 ring-white/80"
        x-bind:class="ready && 'transition-all duration-300'"
        x-bind:style="spot && { top: `${spot.top}px`, left: `${spot.left}px`, width: `${spot.width}px`, height: `${spot.height}px` }"></div>

    <div x-ref="card" class="absolute w-[min(22rem,calc(100vw-2rem))]"
        x-bind:class="ready && 'transition-all duration-300'"
        x-bind:style="{ top: `${card.top}px`, left: `${card.left}px` }">
        <flux:card class="space-y-3 bg-white shadow-xl dark:bg-zinc-800">
            <div class="flex gap-1" aria-hidden="true">
                <template x-for="(_, i) in steps" :key="i">
                    <span class="h-1.5 rounded-full transition-all" x-bind:class="i === index ? 'w-4 bg-accent' : 'w-1.5 bg-zinc-300 dark:bg-zinc-600'"></span>
                </template>
            </div>

            <div>
                <flux:heading id="tour-title" size="lg" x-text="step.title"></flux:heading>
                <flux:text id="tour-body" class="mt-1" x-text="step.body"></flux:text>
            </div>

            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="ghost" x-show="!last" x-on:click="finish()">{{ __('Skip tour') }}</flux:button>
                <flux:spacer />
                <flux:button size="sm" x-show="index > 0" x-on:click="back()">{{ __('Back') }}</flux:button>
                <flux:button size="sm" variant="primary" x-ref="next" x-on:click="next()">
                    <span x-show="!last">{{ __('Next') }}</span>
                    <span x-show="last">{{ __('Finish') }}</span>
                </flux:button>
            </div>
        </flux:card>
    </div>
</div>
