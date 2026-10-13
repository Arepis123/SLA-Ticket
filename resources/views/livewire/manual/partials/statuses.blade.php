@php
    $meanings = [
        'open' => [__('Submitted and waiting for the support team to pick it up.'), __('Running')],
        'acknowledged' => [__('The team has seen the ticket and will start on it.'), __('Running')],
        'in_progress' => [__('Someone is actively working on the fix.'), __('Running')],
        'pending_client' => [__('The team needs more information from the client before continuing.'), __('Paused')],
        'resolved' => [__('A fix has been delivered and is waiting for the client to confirm.'), __('Stopped')],
        'closed' => [__('Finished. The client confirmed the fix, or did not respond in time.'), __('Stopped')],
        'reopened' => [__('The client reported the fix did not work, so the team is working on it again.'), __('Running')],
    ];
@endphp

<flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Status') }}</flux:table.column>
        <flux:table.column>{{ __('What it means') }}</flux:table.column>
        <flux:table.column>{{ __('SLA clock') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @foreach (\App\Enums\TicketStatus::cases() as $status)
            <flux:table.row :key="$status->value">
                <flux:table.cell><x-ticket.status-badge :status="$status" /></flux:table.cell>
                <flux:table.cell class="whitespace-normal">{{ $meanings[$status->value][0] }}</flux:table.cell>
                <flux:table.cell>{{ $meanings[$status->value][1] }}</flux:table.cell>
            </flux:table.row>
        @endforeach
    </flux:table.rows>
</flux:table>
