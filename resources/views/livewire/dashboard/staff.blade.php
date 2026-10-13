<div class="flex flex-col gap-6" wire:poll.60s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('SLA Dashboard') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Live status of bug fixes across all client systems.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" :href="route('tickets.create')" wire:navigate>{{ __('New ticket') }}</flux:button>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Open tickets')" :value="$this->stats['open']" icon="inbox-stack" color="sky"
            :hint="trans_choice(':count unassigned|:count unassigned', $this->stats['unassigned'])"
            :href="route('tickets.index', ['status' => 'unresolved'])" />
        <x-stat-card :label="__('SLA breached')" :value="$this->stats['breached']" icon="x-circle" color="red"
            :hint="__('Open tickets past their target')"
            :href="route('tickets.index', ['status' => 'unresolved', 'sla' => 'breached'])" />
        <x-stat-card :label="__('At risk')" :value="$this->stats['at_risk']" icon="exclamation-triangle" color="amber"
            :hint="__('Over :pct% of time used', ['pct' => config('sla.at_risk_threshold')])"
            :href="route('tickets.index', ['status' => 'unresolved', 'sla' => 'at_risk'])" />
        <x-stat-card :label="__('SLA compliance (30d)')" :value="$this->stats['compliance'] !== null ? $this->stats['compliance'].'%' : '—'" icon="shield-check" color="green"
            :hint="trans_choice(':count ticket resolved|:count tickets resolved', $this->stats['resolved_30d'])" />
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Avg. first response (30d)')" :value="$this->stats['avg_response'] ?? '—'" icon="chat-bubble-left-right" />
        <x-stat-card :label="__('Avg. resolution time (30d)')" :value="$this->stats['avg_resolution'] ?? '—'" icon="wrench-screwdriver" />
        <x-stat-card :label="__('Waiting on client')" :value="$this->stats['pending_client']" icon="pause-circle" color="orange"
            :hint="__('SLA clock paused')"
            :href="route('tickets.index', ['status' => 'pending_client'])" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <flux:card class="xl:col-span-2">
            <div class="mb-4 flex items-center justify-between">
                <flux:heading size="lg">{{ __('Needs attention') }}</flux:heading>
                <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="route('tickets.index', ['status' => 'unresolved', 'sort' => 'resolution_due_at', 'direction' => 'asc'])" wire:navigate>{{ __('All open') }}</flux:button>
            </div>

            @if ($this->attention->isEmpty())
                <div class="py-10 text-center">
                    <flux:icon name="face-smile" class="mx-auto size-10 text-green-500" />
                    <flux:text class="mt-2">{{ __('Nothing waiting on your team. Nice work!') }}</flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Ticket') }}</flux:table.column>
                        <flux:table.column>{{ __('Priority') }}</flux:table.column>
                        <flux:table.column>{{ __('Assignee') }}</flux:table.column>
                        <flux:table.column>{{ __('SLA') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($this->attention as $ticket)
                            <flux:table.row :key="$ticket->id">
                                <flux:table.cell class="max-w-72">
                                    <a href="{{ route('tickets.show', $ticket) }}" wire:navigate class="block truncate font-medium text-zinc-800 hover:underline dark:text-white">{{ $ticket->title }}</a>
                                    <flux:text size="sm" class="truncate">{{ $ticket->reference }} · {{ $ticket->company->name }}</flux:text>
                                </flux:table.cell>
                                <flux:table.cell><x-ticket.priority-badge :priority="$ticket->priority" /></flux:table.cell>
                                <flux:table.cell>
                                    @if ($ticket->assignee)
                                        <span class="text-sm">{{ $ticket->assignee->name }}</span>
                                    @else
                                        <flux:badge size="sm" color="red" variant="outline">{{ __('Unassigned') }}</flux:badge>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell><x-ticket.sla-badge :ticket="$ticket" /></flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <div class="flex flex-col gap-6">
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Open by priority') }}</flux:heading>
                <x-bar-list :items="$this->byPriority" />
            </flux:card>

            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Open by system') }}</flux:heading>
                <x-bar-list :items="$this->byProject" :empty="__('No open tickets.')" />
            </flux:card>
        </div>
    </div>

    <flux:card>
        <flux:heading size="lg">{{ __('Tickets opened — last 14 days') }}</flux:heading>
        @php($trendMax = max(1, ...array_column($this->trend, 'count')))
        <div class="mt-6 flex h-40 items-end gap-1.5 sm:gap-3">
            @foreach ($this->trend as $day)
                <div class="group flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ $day['label'] }}: {{ $day['count'] }}">
                    <span class="text-xs tabular-nums text-zinc-500 opacity-0 group-hover:opacity-100">{{ $day['count'] }}</span>
                    <div class="w-full rounded-t bg-sky-500/80 transition group-hover:bg-sky-500" style="height: {{ max(2, $day['count'] / $trendMax * 100) }}%"></div>
                </div>
            @endforeach
        </div>
        <div class="mt-2 flex gap-1.5 sm:gap-3">
            @foreach ($this->trend as $day)
                <span class="flex-1 truncate text-center text-[10px] text-zinc-500 sm:text-xs">{{ $loop->index % 2 === 0 ? $day['label'] : '' }}</span>
            @endforeach
        </div>
    </flux:card>
</div>
