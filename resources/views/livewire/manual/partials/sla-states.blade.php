@php
    $meanings = [
        'on_track' => __('The ticket is open and there is still time left.'),
        'at_risk' => __('The ticket is open and :pct% or more of the allowed time has been used.', ['pct' => config('sla.at_risk_threshold')]),
        'breached' => __('The deadline was missed: the ticket is still open past its deadline, the first reply was late, or it was fixed late.'),
        'paused' => __('The ticket is waiting on the client, so the clock is stopped.'),
        'met' => __('The ticket was fixed within the deadline.'),
        'merged' => __('The ticket reported the same issue as another one and was merged into it. Follow that ticket instead.'),
    ];
@endphp

<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Badge') }}</flux:table.column>
        <flux:table.column>{{ __('What it means') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach (\App\Enums\SlaState::cases() as $state)
            <flux:table.row :key="$state->value">
                <flux:table.cell><flux:badge size="sm" :color="$state->color()" :icon="$state->icon()">{{ $state->label() }}</flux:badge></flux:table.cell>
                <flux:table.cell class="whitespace-normal">{{ $meanings[$state->value] }}</flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
