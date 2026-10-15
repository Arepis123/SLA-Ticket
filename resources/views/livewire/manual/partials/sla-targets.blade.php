{{-- Live SLA targets and clocks, read from the SLA Policies an admin has configured. --}}
<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Priority') }}</flux:table.column>
        <flux:table.column>{{ __('Use it when') }}</flux:table.column>
        <flux:table.column>{{ __('First response within') }}</flux:table.column>
        <flux:table.column>{{ __('Fixed within') }}</flux:table.column>
        <flux:table.column>{{ __('Counted') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach (\App\Enums\TicketPriority::cases() as $priority)
            @php($targets = \App\Models\SlaPolicy::targetsFor($priority))
            <flux:table.row :key="$priority->value">
                <flux:table.cell><x-ticket.priority-badge :priority="$priority" /></flux:table.cell>
                <flux:table.cell class="whitespace-normal">{{ $priority->description() }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ \App\Models\SlaPolicy::formatMinutes($targets['response']) }}</flux:table.cell>
                <flux:table.cell variant="strong">{{ \App\Models\SlaPolicy::formatMinutes($targets['resolution']) }}</flux:table.cell>
                <flux:table.cell>{{ \App\Models\SlaPolicy::usesBusinessHours($priority) ? __('Business hours') : __('24/7') }}</flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>

@if (collect(\App\Enums\TicketPriority::cases())->contains(fn ($priority) => \App\Models\SlaPolicy::usesBusinessHours($priority)))
    @php($calendar = app(\App\Support\BusinessCalendar::class))
    <flux:text size="sm" class="mt-2">
        {{ __('Business hours are :hours (:timezone time), except public holidays. Outside them, the clock of a business-hours priority does not run.', ['hours' => $calendar->describe(), 'timezone' => $calendar->timezone()]) }}
    </flux:text>
@endif
