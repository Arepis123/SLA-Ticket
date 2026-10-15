<div class="flex flex-col gap-6">
    @php
        [$start, $end] = $this->period;
        $summary = $this->summary;
        $percent = fn (?int $value) => $value !== null ? $value.'%' : '—';

        $primaryCards = [
            [
                'label' => __('Tickets opened'),
                'value' => $summary['opened'],
                'icon' => 'inbox-stack',
                'color' => 'sky',
                'hint' => trans_choice(':count still open|:count still open', $summary['open']),
            ],
            [
                'label' => __('Resolved'),
                'value' => $summary['resolved'],
                'icon' => 'check-circle',
                'color' => 'green',
                'hint' => __('Resolved or closed'),
            ],
            [
                'label' => __('SLA breached'),
                'value' => $summary['breached'],
                'icon' => 'x-circle',
                'color' => 'red',
                'hint' => __('Missed a response or resolution target'),
            ],
            [
                'label' => __('Resolution SLA compliance'),
                'value' => $percent($summary['compliance']),
                'icon' => 'shield-check',
                'color' => 'emerald',
                'hint' => __('Of resolved tickets'),
            ],
        ];

        $secondaryCards = [
            [
                'label' => __('First-response SLA compliance'),
                'value' => $percent($summary['response_compliance']),
                'icon' => 'bolt',
                'color' => 'indigo',
            ],
            [
                'label' => __('Avg. first response'),
                'value' => $summary['avg_response'] ?? '—',
                'icon' => 'chat-bubble-left-right',
                'color' => 'amber',
            ],
            [
                'label' => __('Avg. resolution time'),
                'value' => $summary['avg_resolution'] ?? '—',
                'icon' => 'wrench-screwdriver',
                'color' => 'cyan',
            ],
        ];
    @endphp

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Reports') }}</flux:heading>
            <flux:text class="mt-1">{{ __('SLA performance for tickets opened :from – :to.', ['from' => $start->format('d M Y'), 'to' => $end->format('d M Y')]) }}</flux:text>
        </div>
        <flux:dropdown position="bottom" align="end">
            <flux:button icon="arrow-down-tray" icon:trailing="chevron-down" :disabled="$summary['opened'] === 0">{{ __('Export') }}</flux:button>
            <flux:menu>
                <flux:menu.item icon="document-text" wire:click="exportPdf">{{ __('PDF report') }}</flux:menu.item>
                <flux:menu.item icon="table-cells" wire:click="export">{{ __('CSV (one row per ticket)') }}</flux:menu.item>
            </flux:menu>
        </flux:dropdown>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:date-picker mode="range" wire:model.live="range" with-presets presets="last7Days last30Days last3Months thisMonth lastMonth yearToDate lastYear" />

        <flux:select variant="listbox" searchable wire:model.live="company" class="max-w-xs">
            <flux:select.option value="">{{ __('All clients') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" searchable wire:model.live="project" class="max-w-xs">
            <flux:select.option value="">{{ __('All systems') }}</flux:select.option>
            @foreach ($this->projects as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}{{ $company === '' ? ' — '.$option->company->name : '' }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @foreach ([['sm:grid-cols-2 xl:grid-cols-4', $primaryCards], ['sm:grid-cols-3', $secondaryCards]] as [$columns, $cards])
        <div class="grid gap-4 {{ $columns }}">
            @foreach ($cards as $card)
                <flux:card size="sm" wire:key="stat-{{ $card['icon'] }}" class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <flux:text size="sm" class="truncate">{{ $card['label'] }}</flux:text>

                        <div class="flex flex-wrap items-baseline gap-x-2">
                            <flux:heading size="lg" class="tabular-nums">{{ $card['value'] }}</flux:heading>

                            @isset($card['hint'])
                                <flux:text size="sm" class="truncate">{{ $card['hint'] }}</flux:text>
                            @endisset
                        </div>
                    </div>

                    <flux:avatar size="sm" :icon="$card['icon']" icon:variant="outline" :color="$card['color']" />
                </flux:card>
            @endforeach
        </div>
    @endforeach

    <flux:card>
        <flux:heading size="lg">{{ __('Tickets opened over the period') }}</flux:heading>

        <flux:chart :value="$this->trend" wire:key="trend-{{ md5(json_encode($this->trend)) }}" class="mt-6 w-full aspect-[4/1] min-h-48">
            <flux:chart.svg>
                <flux:chart.bar field="opened" class="text-sky-500 dark:text-sky-400" radius="4 0" width="60%" />

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
                <flux:chart.tooltip.value field="opened" :label="__('Tickets opened')" />
                <flux:chart.tooltip.value field="breached" :label="__('SLA breached')" />
            </flux:chart.tooltip>
        </flux:chart>
    </flux:card>

    <flux:card>
        <flux:heading size="lg" class="mb-4">{{ __('Breakdown') }}</flux:heading>

        <flux:tab.group>
            <flux:tabs wire:model="tab" scrollable scrollable:fade scrollable:scrollbar="hide">
                @foreach ($this->breakdowns as $name => $breakdown)
                    <flux:tab :name="$name">{{ __('By :group', ['group' => Str::lower($breakdown['label'])]) }}</flux:tab>
                @endforeach
            </flux:tabs>

            @foreach ($this->breakdowns as $name => $breakdown)
                <flux:tab.panel :name="$name">
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ $breakdown['label'] }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Opened') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Resolved') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Still open') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Breached') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('SLA compliance') }}</flux:table.column>
                            <flux:table.column align="center">{{ __('Avg. resolution') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($breakdown['rows'] as $row)
                                <flux:table.row :key="$name.'-'.$loop->index">
                                    <flux:table.cell variant="strong" class="max-w-80 truncate">{{ $row['label'] }}</flux:table.cell>
                                    <flux:table.cell align="center" class="tabular-nums">{{ $row['opened'] }}</flux:table.cell>
                                    <flux:table.cell align="center" class="tabular-nums">{{ $row['resolved'] }}</flux:table.cell>
                                    <flux:table.cell align="center" class="tabular-nums">{{ $row['open'] }}</flux:table.cell>
                                    <flux:table.cell align="center" class="tabular-nums">{{ $row['breached'] }}</flux:table.cell>
                                    <flux:table.cell align="center">
                                        @if ($row['compliance'] === null)
                                            —
                                        @else
                                            <flux:badge size="sm" :color="$row['compliance'] >= 90 ? 'green' : ($row['compliance'] >= 70 ? 'amber' : 'red')">{{ $row['compliance'] }}%</flux:badge>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="center" class="tabular-nums">{{ $row['avg_resolution'] ?? '—' }}</flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row>
                                    <flux:table.cell colspan="7" class="py-10 text-center"><flux:text>{{ __('No tickets were opened in this period.') }}</flux:text></flux:table.cell>
                                </flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </flux:tab.panel>
            @endforeach
        </flux:tab.group>
    </flux:card>
</div>
