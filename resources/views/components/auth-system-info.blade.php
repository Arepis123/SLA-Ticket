{{-- Marketing-free summary of what FixWatch does, shown beside the sign-in form. --}}
@php
    $features = [
        ['icon' => 'bug-ant', 'label' => __('Report a fault against the system it affects')],
        ['icon' => 'clock', 'label' => __('A response and resolution clock on every ticket')],
        ['icon' => 'bell-alert', 'label' => __('Warned at :percent% of the window, before a breach', ['percent' => config('sla.at_risk_threshold')])],
        ['icon' => 'chat-bubble-left-right', 'label' => __('Replies, work logs and files on one timeline')],
    ];
@endphp

<div class="flex h-full flex-col gap-8 overflow-y-auto xl:gap-10">
    <a href="{{ route('home') }}" class="flex items-center gap-3" wire:navigate>
        <x-app-logo-icon class="h-9 fill-current text-neutral-900 dark:text-white" />
        <span class="flex flex-col leading-tight">
            <span class="text-lg font-semibold text-neutral-900 dark:text-white">{{ config('app.name', 'Laravel') }}</span>
            <span class="text-xs text-neutral-600 dark:text-white/70">{{ __('by :company', ['company' => config('sla.company.name')]) }}</span>
        </span>
    </a>

    {{-- Centres in whatever space the logo and the targets leave; falls back to top-aligned when the panel is too short to centre without clipping. --}}
    <div class="flex flex-1 flex-col justify-center-safe gap-6">
        <h2 class="text-3xl font-semibold tracking-tight text-balance text-neutral-900 xl:text-4xl dark:text-white">
            {{ __('Bug reports with a deadline attached.') }}
        </h2>

        <ul class="flex flex-col gap-3">
            @foreach ($features as $feature)
                <li class="flex items-center gap-3">
                    <flux:icon :icon="$feature['icon']" variant="mini" class="shrink-0 text-neutral-500 dark:text-white/60" />
                    <span class="text-sm text-neutral-700 dark:text-white/80">{{ $feature['label'] }}</span>
                </li>
            @endforeach
        </ul>
    </div>

    <div class="flex flex-col gap-3">
        <div class="flex items-baseline justify-between gap-4">
            <span class="text-xs font-semibold tracking-wider text-neutral-500 uppercase dark:text-white/60">{{ __('Current response targets') }}</span>
            <span class="text-xs text-neutral-500 dark:text-white/50">{{ __('first reply / resolution') }}</span>
        </div>

        <dl class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach (\App\Enums\TicketPriority::cases() as $priority)
                @php
                    $targets = rescue(
                        fn () => \App\Models\SlaPolicy::targetsFor($priority),
                        $priority->defaultTargets(),
                        report: false,
                    );
                @endphp
                <div class="flex flex-col gap-0.5 rounded-lg bg-white/70 px-3 py-2.5 ring-1 ring-neutral-900/10 dark:bg-white/5 dark:ring-white/10">
                    <dt class="text-xs font-medium text-neutral-600 dark:text-white/70">{{ $priority->label() }}</dt>
                    <dd class="font-mono text-sm text-neutral-900 dark:text-white">
                        {{ \App\Models\SlaPolicy::formatMinutes($targets['response']) }}
                        <span class="text-neutral-400 dark:text-white/30">/</span>
                        {{ \App\Models\SlaPolicy::formatMinutes($targets['resolution']) }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
