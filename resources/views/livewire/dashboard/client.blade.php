<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Welcome, :name', ['name' => auth()->user()->name]) }}</flux:heading>
            <flux:text class="mt-1">{{ auth()->user()->company?->name }}</flux:text>
        </div>
        <flux:button variant="primary" icon="bug-ant" :href="route('tickets.create')" wire:navigate>{{ __('Report a bug') }}</flux:button>
    </div>

    @if ($this->stats['pending_client'] > 0)
        <flux:callout icon="chat-bubble-left-ellipsis" color="orange">
            <flux:callout.heading>{{ trans_choice('{1} 1 ticket is waiting for your reply|[2,*] :count tickets are waiting for your reply', $this->stats['pending_client']) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Our team needs more information to continue.') }}
                <flux:callout.link :href="route('tickets.index', ['status' => 'pending_client'])" wire:navigate>{{ __('View tickets') }}</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($this->stats['awaiting_confirmation'] > 0)
        <flux:callout icon="check-badge" color="green">
            <flux:callout.heading>{{ trans_choice('{1} 1 fix is ready for you to verify|[2,*] :count fixes are ready for you to verify', $this->stats['awaiting_confirmation']) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('Please confirm the fix or reopen the ticket.') }}
                <flux:callout.link :href="route('tickets.index', ['status' => 'resolved'])" wire:navigate>{{ __('Review') }}</flux:callout.link>
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Open tickets')" :value="$this->stats['open']" icon="inbox-stack" color="sky" :href="route('tickets.index', ['status' => 'unresolved'])" />
        <x-stat-card :label="__('Waiting for your reply')" :value="$this->stats['pending_client']" icon="chat-bubble-left-ellipsis" color="orange" :href="route('tickets.index', ['status' => 'pending_client'])" />
        <x-stat-card :label="__('Fixed in last 30 days')" :value="$this->stats['resolved_30d']" icon="check-circle" color="green" />
        <x-stat-card :label="__('Resolved within SLA')" :value="$this->stats['compliance'] !== null ? $this->stats['compliance'].'%' : '—'" icon="shield-check" />
    </div>

    <flux:card>
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Recent tickets') }}</flux:heading>
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="route('tickets.index')" wire:navigate>{{ __('View all') }}</flux:button>
        </div>

        @if ($this->recentTickets->isEmpty())
            <div class="py-10 text-center">
                <flux:icon name="bug-ant" class="mx-auto size-10 text-zinc-400" />
                <flux:text class="mt-2">{{ __('You have not reported any issues yet.') }}</flux:text>
            </div>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Ticket') }}</flux:table.column>
                    <flux:table.column>{{ __('Priority') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Target') }}</flux:table.column>
                    <flux:table.column>{{ __('Updated') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->recentTickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell class="max-w-80">
                                <a href="{{ route('tickets.show', $ticket) }}" wire:navigate class="block truncate font-medium text-zinc-800 hover:underline dark:text-white">{{ $ticket->title }}</a>
                                <flux:text size="sm">{{ $ticket->reference }} · {{ $ticket->project->name }}</flux:text>
                            </flux:table.cell>
                            <flux:table.cell><x-ticket.priority-badge :priority="$ticket->priority" /></flux:table.cell>
                            <flux:table.cell><x-ticket.status-badge :status="$ticket->status" /></flux:table.cell>
                            <flux:table.cell><x-ticket.sla-badge :ticket="$ticket" /></flux:table.cell>
                            <flux:table.cell class="whitespace-nowrap">{{ $ticket->updated_at->diffForHumans() }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>
</div>
