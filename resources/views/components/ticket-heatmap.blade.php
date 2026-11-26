{{-- Calendar heatmap of tickets opened per day. $weeks comes from DashboardMetrics::heatmap(). --}}
@props(['weeks'])

<flux:card {{ $attributes }}>
    @php
        $heatmapLevels = [
            0 => 'bg-blue-100 dark:bg-blue-200',
            1 => 'bg-blue-300 dark:bg-blue-300',
            2 => 'bg-blue-500 dark:bg-blue-500',
            3 => 'bg-blue-900 dark:bg-blue-600',
        ];
        $heatmapTotal = collect($weeks)->pluck('days')->flatten(1)->filter()->sum('count');
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <flux:heading size="lg">{{ __('Tickets opened — last 12 weeks') }}</flux:heading>
            <flux:text class="mt-1">{{ trans_choice(':count ticket opened|:count tickets opened', $heatmapTotal, ['count' => $heatmapTotal]) }}</flux:text>
        </div>

        <div class="flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400" aria-hidden="true">
            <span>{{ __('Less') }}</span>
            @foreach ($heatmapLevels as $class)
                <span class="size-3 rounded-sm {{ $class }}"></span>
            @endforeach
            <span>{{ __('More') }}</span>
        </div>
    </div>

    <div class="mt-6 overflow-x-auto">
        <div class="grid min-w-[28rem] grid-cols-[auto_repeat(12,minmax(0,1fr))] gap-1 text-xs text-zinc-500 dark:text-zinc-400">
            {{-- Month labels: shown on the first week of each month --}}
            <span></span>
            @foreach ($weeks as $i => $week)
                <span class="truncate pb-1">{{ $i === 0 || $week['month'] !== $weeks[$i - 1]['month'] ? $week['month'] : '' }}</span>
            @endforeach

            @foreach ([__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')] as $d => $weekday)
                <span class="pe-2 text-end leading-6">{{ $weekday }}</span>

                @foreach ($weeks as $week)
                    @php($day = $week['days'][$d])
                    @if ($day)
                        <flux:tooltip>
                            <div class="group flex h-6 w-full cursor-default items-center justify-center rounded {{ $heatmapLevels[$day['level']] }}">
                                @php($isToday = $day['date'] === now()->format('Y-m-d'))
                                {{-- Dot marks today, and the tile under the pointer. White would vanish on the palest tile. --}}
                                <span @class([
                                    'size-1.5 rounded-full shadow-sm transition-opacity',
                                    'bg-blue-500' => $day['level'] === 0,
                                    'bg-white' => $day['level'] > 0,
                                    'opacity-100' => $isToday,
                                    'opacity-0 group-hover:opacity-100' => ! $isToday,
                                ])></span>
                                <span class="sr-only">{{ $isToday ? __('Today').', ' : '' }}{{ $day['label'] }}: {{ $day['count'] }}</span>
                            </div>

                            <flux:tooltip.content class="rounded-lg! border! border-zinc-200! bg-white! px-3! py-2! text-zinc-900! shadow-lg! dark:border-white/10! dark:bg-zinc-800! dark:text-white!">
                                <div class="text-sm font-semibold">{{ $day['label'] }}@if ($isToday) <span class="font-normal text-zinc-500 dark:text-zinc-400">· {{ __('Today') }}</span>@endif</div>
                                <div class="mt-1 flex items-center gap-2 text-sm font-normal">
                                    <span class="size-3 rounded-sm bg-blue-900 dark:bg-blue-600"></span>
                                    <span>{{ __('Tickets opened') }}</span>
                                    <span class="ms-auto ps-3 font-medium tabular-nums">{{ $day['count'] }}</span>
                                </div>
                            </flux:tooltip.content>
                        </flux:tooltip>
                    @else
                        <div class="h-6 rounded {{ $heatmapLevels[0] }}"></div>
                    @endif
                @endforeach
            @endforeach

            {{-- X axis: ISO week numbers, oldest first --}}
            <span></span>
            @foreach ($weeks as $week)
                <span class="pt-1 text-center">W{{ $week['week'] }}</span>
            @endforeach

            <span></span>
            <span class="col-span-12 text-center font-medium text-zinc-400 dark:text-zinc-500">{{ __('Week') }}</span>
        </div>
    </div>
</flux:card>
