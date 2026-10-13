<div class="flex max-w-4xl flex-col gap-6">
    <div>
        <flux:heading size="xl" level="1">{{ __('SLA Policies') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Targets per priority, in hours. The clock runs 24/7 and pauses while a ticket is waiting on the client, resolved or closed.') }}</flux:text>
    </div>

    <form wire:submit="save" class="flex flex-col gap-6">
        <flux:card class="p-0! overflow-hidden">
            <flux:table class="[&_th:first-child]:ps-4 [&_td:first-child]:ps-4">
                <flux:table.columns>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('First response (hours)') }}</flux:table.column>
                    <flux:table.column>{{ __('Resolution (hours)') }}</flux:table.column>
                    <flux:table.column>{{ __('Equals') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach (\App\Enums\TicketPriority::cases() as $priority)
                        <flux:table.row :key="$priority->value">
                            <flux:table.cell>
                                <x-ticket.priority-badge :priority="$priority" />
                                <flux:text size="sm" class="mt-1">{{ $priority->description() }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input type="number" step="0.25" min="0.25" wire:model.live.debounce.500ms="targets.{{ $priority->value }}.response" class="max-w-32" />
                                <flux:error name="targets.{{ $priority->value }}.response" />
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:input type="number" step="0.25" min="0.25" wire:model.live.debounce.500ms="targets.{{ $priority->value }}.resolution" class="max-w-32" />
                                <flux:error name="targets.{{ $priority->value }}.resolution" />
                            </flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap text-sm text-zinc-500">
                                {{ \App\Models\SlaPolicy::formatMinutes((int) round((float) $targets[$priority->value]['response'] * 60)) }}
                                /
                                {{ \App\Models\SlaPolicy::formatMinutes((int) round((float) $targets[$priority->value]['resolution'] * 60)) }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>

        <flux:callout icon="information-circle">
            <flux:callout.text>
                {{ __('Tickets are flagged "at risk" after :pct% of the resolution window is used, and resolved tickets close automatically after :days days without client response. Change these in config/sla.php or .env (SLA_AT_RISK_THRESHOLD, SLA_AUTO_CLOSE_DAYS).', ['pct' => config('sla.at_risk_threshold'), 'days' => config('sla.auto_close_days')]) }}
            </flux:callout.text>
        </flux:callout>

        <div class="flex justify-end">
            <flux:button type="submit" variant="primary">{{ __('Save policies') }}</flux:button>
        </div>
    </form>
</div>
