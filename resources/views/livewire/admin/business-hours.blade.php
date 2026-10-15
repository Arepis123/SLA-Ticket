@php
    $calendar = $this->calendar;
    $localNow = $this->localNow;
    $isOpen = $calendar->isOpenAt($localNow);
@endphp

<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('Business Hours') }}</flux:heading>
        <flux:text class="mt-1">{{ __('When your team is working. Priorities set to "Business hours" in SLA Policies only use up their time inside these hours, and never on holidays.') }}</flux:text>
    </div>

    <flux:callout :icon="$isOpen ? 'building-office' : 'moon'" :color="$isOpen ? 'green' : 'zinc'">
        <flux:callout.heading>
            {{ $isOpen ? __('The office is open now') : __('The office is closed now') }}
            · {{ $localNow->format('D d M, H:i') }} ({{ $calendar->timezone() }})
        </flux:callout.heading>
        <flux:callout.text>
            @if ($this->businessPriorities)
                {{ __('Used by: :priorities.', ['priorities' => collect($this->businessPriorities)->map->label()->join(', ')]) }}
            @else
                {{ __('No priority uses business hours yet, so every SLA clock still runs 24/7.') }}
            @endif
            <flux:link :href="route('admin.sla-policies')" wire:navigate>{{ __('Choose in SLA Policies') }}</flux:link>.
            {{ __('All times are in the timezone above; change it with SLA_TIMEZONE in .env.') }}
        </flux:callout.text>
    </flux:callout>

    {{-- Weekly schedule --}}
    <form wire:submit="saveHours">
        <flux:card class="space-y-4">
            <div>
                <flux:heading size="lg">{{ __('Weekly schedule') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Open tickets have their deadlines recalculated when you save.') }}</flux:text>
            </div>

            <div class="divide-y divide-zinc-800/10 dark:divide-white/10">
                @foreach ($days as $weekday => $day)
                    <div wire:key="day-{{ $weekday }}" class="flex flex-wrap items-center gap-x-6 gap-y-3 py-3">
                        <div class="w-36">
                            <flux:switch wire:model.live="days.{{ $weekday }}.open" :label="\Carbon\CarbonImmutable::create(2024, 1, $weekday)->translatedFormat('l')" align="left" />
                        </div>

                        @if ($day['open'])
                            {{-- On phones the pair shares the row; from sm up each picker keeps a fixed width. --}}
                            <div class="flex w-full items-start gap-2 sm:w-auto">
                                <div class="min-w-0 flex-1 sm:flex-none">
                                    <flux:time-picker wire:model="days.{{ $weekday }}.opens_at" :aria-label="__('Opens')" class="w-full sm:w-32" />
                                    <flux:error name="days.{{ $weekday }}.opens_at" />
                                </div>
                                <flux:text class="mt-2">–</flux:text>
                                <div class="min-w-0 flex-1 sm:flex-none">
                                    <flux:time-picker wire:model="days.{{ $weekday }}.closes_at" :aria-label="__('Closes')" class="w-full sm:w-32" />
                                    <flux:error name="days.{{ $weekday }}.closes_at" />
                                </div>
                            </div>
                        @else
                            <flux:text class="text-zinc-400">{{ __('Closed') }}</flux:text>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">{{ __('Save hours') }}</flux:button>
            </div>
        </flux:card>
    </form>

    {{-- Holidays --}}
    <flux:card class="space-y-4">
        <div>
            <flux:heading size="lg">{{ __('Holidays') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Days the office is closed all day, such as public holidays.') }}</flux:text>
        </div>

        <form wire:submit="addHoliday" class="flex flex-wrap items-start gap-3">
            <div>
                <flux:date-picker wire:model="holidayDate" :placeholder="__('Pick a date')" class="w-48" />
                <flux:error name="holidayDate" />
            </div>
            <div class="min-w-48 flex-1">
                <flux:input wire:model="holidayName" :placeholder="__('Name, e.g. Hari Raya Aidilfitri')" />
                <flux:error name="holidayName" />
            </div>
            <flux:button type="submit" icon="plus">{{ __('Add holiday') }}</flux:button>
        </form>

        @if ($this->holidays->isEmpty())
            <flux:text class="py-4 text-center">{{ __('No holidays yet.') }}</flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Holiday') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->holidays as $holiday)
                        @php($past = $holiday->date->format('Y-m-d') < $localNow->format('Y-m-d'))
                        <flux:table.row :key="$holiday->id" @class(['opacity-50' => $past])>
                            <flux:table.cell class="whitespace-nowrap tabular-nums">{{ $holiday->date->translatedFormat('D, d M Y') }}</flux:table.cell>
                            <flux:table.cell variant="strong">{{ $holiday->name }}</flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="deleteHoliday({{ $holiday->id }})" wire:confirm="{{ __('Remove :name?', ['name' => $holiday->name]) }}" :aria-label="__('Remove holiday')" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
