<div class="flex flex-col gap-6" wire:poll.60s>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('SLA Dashboard') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Live status of bug fixes across all client systems.') }}</flux:text>
        </div>
    </div>

    @php
        $stats = $this->stats;

        $primaryCards = [
            [
                'label' => __('Open tickets'),
                'value' => $stats['open'],
                'icon' => 'inbox-stack',
                'color' => 'sky',
                'hint' => trans_choice(':count unassigned|:count unassigned', $stats['unassigned']),
                'href' => route('tickets.index', ['status' => 'unresolved']),
            ],
            [
                'label' => __('SLA breached'),
                'value' => $stats['breached'],
                'icon' => 'x-circle',
                'color' => 'red',
                'hint' => __('Open tickets past their target'),
                'href' => route('tickets.index', ['status' => 'unresolved', 'sla' => 'breached']),
            ],
            [
                'label' => __('At risk'),
                'value' => $stats['at_risk'],
                'icon' => 'exclamation-triangle',
                'color' => 'amber',
                'hint' => __('Over :pct% of time used', ['pct' => config('sla.at_risk_threshold')]),
                'href' => route('tickets.index', ['status' => 'unresolved', 'sla' => 'at_risk']),
            ],
            [
                'label' => __('SLA compliance (30d)'),
                'value' => $stats['compliance'] !== null ? $stats['compliance'].'%' : '—',
                'icon' => 'shield-check',
                'color' => 'green',
                'hint' => trans_choice(':count ticket resolved|:count tickets resolved', $stats['resolved_30d']),
            ],
        ];

        $secondaryCards = [
            [
                'label' => __('Avg. first response (30d)'),
                'value' => $stats['avg_response'] ?? '—',
                'icon' => 'chat-bubble-left-right',
                'color' => 'amber',
            ],
            [
                'label' => __('Avg. resolution time (30d)'),
                'value' => $stats['avg_resolution'] ?? '—',
                'icon' => 'wrench-screwdriver',
                'color' => 'cyan',
            ],
            [
                'label' => __('Waiting on client'),
                'value' => $stats['pending_client'],
                'icon' => 'pause-circle',
                'color' => 'orange',
                'hint' => __('SLA clock paused'),
                'href' => route('tickets.index', ['status' => 'pending_client']),
            ],
        ];
    @endphp

    @foreach ([['sm:grid-cols-2 xl:grid-cols-4', $primaryCards], ['sm:grid-cols-3', $secondaryCards]] as [$columns, $cards])
        <div class="grid gap-4 {{ $columns }}">
            @foreach ($cards as $card)
                <flux:card size="sm" wire:key="stat-{{ $card['icon'] }}" class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <flux:text size="sm" class="truncate">{{ $card['label'] }}</flux:text>

                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <flux:heading size="lg" class="tabular-nums">
                                @isset($card['href'])
                                    <flux:link :href="$card['href']" variant="ghost" wire:navigate>{{ $card['value'] }}</flux:link>
                                @else
                                    {{ $card['value'] }}
                                @endisset
                            </flux:heading>

                            @isset($card['hint'])
                                <flux:text size="sm" class="truncate">{{ $card['hint'] }}</flux:text>
                            @endisset
                        </div>
                    </div>

                    <flux:avatar size="sm" :icon="$card['icon']" icon:variant="outline" :color="$card['color'] ?? null" />
                </flux:card>
            @endforeach
        </div>
    @endforeach

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
                                    <div class="truncate">
                                        <flux:link :href="route('tickets.show', $ticket)" variant="ghost" class="font-medium" wire:navigate>{{ $ticket->title }}</flux:link>
                                    </div>
                                    <flux:text size="sm" class="truncate">{{ $ticket->reference }} · {{ $ticket->company->name }}</flux:text>
                                </flux:table.cell>
                                <flux:table.cell><x-ticket.priority-badge :priority="$ticket->priority" /></flux:table.cell>
                                <flux:table.cell>
                                    @if ($ticket->assignee)
                                        <flux:text>{{ $ticket->assignee->name }}</flux:text>
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

        <flux:chart :value="$this->trend" class="mt-6 w-full aspect-[4/1] min-h-48">
            <flux:chart.svg>
                <flux:chart.bar field="count" class="text-sky-500 dark:text-sky-400" radius="4 0" width="60%" />

                <flux:chart.axis axis="x" field="label" scale="categorical">
                    <flux:chart.axis.line />
                    <flux:chart.axis.tick />
                </flux:chart.axis>

                <flux:chart.axis axis="y" tick-start="0">
                    <flux:chart.axis.grid />
                    <flux:chart.axis.tick />
                </flux:chart.axis>

                <flux:chart.cursor />
            </flux:chart.svg>

            <flux:chart.tooltip>
                <flux:chart.tooltip.heading field="label" />
                <flux:chart.tooltip.value field="count" :label="__('Tickets opened')" />
            </flux:chart.tooltip>
        </flux:chart>
    </flux:card>
</div>
