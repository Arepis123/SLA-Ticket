<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ auth()->user()->isClient() ? __('My Tickets') : __('Tickets') }}</flux:heading>
            <flux:text class="mt-1">{{ trans_choice(':count ticket|:count tickets', $this->tickets->total()) }}</flux:text>
        </div>
        @can('create', \App\Models\Ticket::class)
            <flux:button variant="primary" icon="bug-ant" :href="route('tickets.create')" wire:navigate>{{ __('Report a bug') }}</flux:button>
        @endcan
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search ref or title…')" clearable class="xl:col-span-2" />

        <flux:select variant="listbox" wire:model.live="status">
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            <flux:select.option value="unresolved">{{ __('All unresolved') }}</flux:select.option>
            @foreach (\App\Enums\TicketStatus::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" wire:model.live="priority">
            <flux:select.option value="">{{ __('All priorities') }}</flux:select.option>
            @foreach (\App\Enums\TicketPriority::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" searchable wire:model.live="project">
            <flux:select.option value="">{{ __('All systems') }}</flux:select.option>
            @foreach ($this->projects as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}{{ auth()->user()->isStaff() ? ' — '.$option->company->name : '' }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:select variant="listbox" wire:model.live="sla">
            <flux:select.option value="">{{ __('Any SLA state') }}</flux:select.option>
            <flux:select.option value="breached">{{ __('Breached') }}</flux:select.option>
            <flux:select.option value="at_risk">{{ __('At risk') }}</flux:select.option>
        </flux:select>

        @can('staff')
            <flux:select variant="listbox" searchable wire:model.live="assignee">
                <flux:select.option value="">{{ __('Anyone') }}</flux:select.option>
                <flux:select.option value="me">{{ __('Assigned to me') }}</flux:select.option>
                <flux:select.option value="none">{{ __('Unassigned') }}</flux:select.option>
                @foreach ($this->staff as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endcan

        @if ($this->hasFilters)
            <div class="flex items-center">
                <flux:button variant="ghost" size="sm" icon="x-mark" wire:click="resetFilters">{{ __('Clear filters') }}</flux:button>
            </div>
        @endif
    </div>

    <flux:card class="p-0! overflow-hidden">
        <flux:table class="[&_th:first-child]:ps-4 [&_td:first-child]:ps-4">
            <flux:table.columns>
                <flux:table.column sortable :sorted="$sort === 'reference'" :direction="$direction" wire:click="sortBy('reference')">{{ __('Ref') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'title'" :direction="$direction" wire:click="sortBy('title')">{{ __('Issue') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'priority'" :direction="$direction" wire:click="sortBy('priority')">{{ __('Priority') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'status'" :direction="$direction" wire:click="sortBy('status')">{{ __('Status') }}</flux:table.column>
                @can('staff')
                    <flux:table.column>{{ __('Assignee') }}</flux:table.column>
                @endcan
                <flux:table.column sortable :sorted="$sort === 'resolution_due_at'" :direction="$direction" wire:click="sortBy('resolution_due_at')">{{ __('SLA') }}</flux:table.column>
                <flux:table.column sortable :sorted="$sort === 'created_at'" :direction="$direction" wire:click="sortBy('created_at')">{{ __('Opened') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @forelse ($this->tickets as $ticket)
                    <flux:table.row :key="$ticket->id">
                        <flux:table.cell class="whitespace-nowrap font-mono text-xs">
                            <flux:link :href="route('tickets.show', $ticket)" variant="subtle" wire:navigate>{{ $ticket->reference }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell class="max-w-96">
                            <flux:link :href="route('tickets.show', $ticket)" variant="ghost" class="block truncate font-medium" wire:navigate>{{ $ticket->title }}</flux:link>
                            <flux:text size="sm" class="truncate">
                                {{ $ticket->project->name }}@can('staff') · {{ $ticket->company->name }}@endcan
                            </flux:text>
                        </flux:table.cell>
                        <flux:table.cell><x-ticket.priority-badge :priority="$ticket->priority" /></flux:table.cell>
                        <flux:table.cell><x-ticket.status-badge :status="$ticket->status" /></flux:table.cell>
                        @can('staff')
                            <flux:table.cell class="whitespace-nowrap">
                                @if ($ticket->assignee)
                                    <div class="flex items-center gap-2">
                                        <flux:avatar size="xs" :name="$ticket->assignee->name" :initials="$ticket->assignee->initials()" />
                                        <flux:text>{{ $ticket->assignee->name }}</flux:text>
                                    </div>
                                @else
                                    <flux:text size="sm" class="italic">{{ __('Unassigned') }}</flux:text>
                                @endif
                            </flux:table.cell>
                        @endcan
                        <flux:table.cell><x-ticket.sla-badge :ticket="$ticket" /></flux:table.cell>
                        <flux:table.cell class="whitespace-nowrap" title="{{ $ticket->created_at->format('d M Y H:i') }}">{{ $ticket->created_at->diffForHumans() }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-12 text-center">
                            <flux:icon name="inbox" class="mx-auto size-8 text-zinc-400" />
                            <flux:text class="mt-2">{{ __('No tickets match your filters.') }}</flux:text>
                        </flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:pagination :paginator="$this->tickets" class="px-4 pb-3" />
    </flux:card>
</div>
