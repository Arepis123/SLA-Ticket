@props(['ticket', 'countdown' => true])

@php($state = $ticket->slaState())

<div {{ $attributes->class('flex flex-col items-start gap-1') }}>
    <flux:badge :color="$state->color()" :icon="$state->icon()" size="sm">{{ $state->label() }}</flux:badge>

    @if ($countdown)
        <flux:text size="sm" @class([
            'whitespace-nowrap',
            'text-red-600! dark:text-red-400!' => $state === \App\Enums\SlaState::Breached && ! $ticket->status->isFinished(),
        ])>{{ $ticket->resolutionCountdown() }}</flux:text>
    @endif
</div>
