@php
    // During the tour a new client would only see empty cards, so show what a dashboard in use looks like.
    $stats = $showTour ? \App\Support\TourSample::stats() : $this->stats;
    $recentTickets = $showTour ? \App\Support\TourSample::tickets(auth()->user()) : $this->recentTickets;
@endphp

<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1" class="flex flex-wrap items-center gap-2">
                {{ __('Welcome, :name', ['name' => auth()->user()->name]) }}
                @if ($showTour)
                    <flux:badge size="sm" color="amber" icon="sparkles">{{ __('Example data') }}</flux:badge>
                @endif
            </flux:heading>
            <flux:text class="mt-1">{{ auth()->user()->company?->name }}</flux:text>
        </div>
        <flux:button variant="primary" icon="bug" icon:variant="solid" icon:class="size-4" :href="route('tickets.create')" data-tour="report-bug" wire:navigate>{{ __('Report a bug') }}</flux:button>
    </div>

    @if ($stats['pending_client'] > 0 || $stats['awaiting_confirmation'] > 0)
        <div class="flex flex-col gap-6" data-tour="notices">
            @if ($stats['pending_client'] > 0)
                <flux:callout icon="chat-bubble-left-ellipsis" color="orange">
                    <flux:callout.heading>{{ trans_choice('{1} 1 ticket is waiting for your reply|[2,*] :count tickets are waiting for your reply', $stats['pending_client']) }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Our team needs more information to continue.') }}
                        <flux:callout.link :href="route('tickets.index', ['status' => 'pending_client'])" wire:navigate>{{ __('View tickets') }}</flux:callout.link>
                    </flux:callout.text>
                </flux:callout>
            @endif

            @if ($stats['awaiting_confirmation'] > 0)
                <flux:callout icon="check-badge" color="green">
                    <flux:callout.heading>{{ trans_choice('{1} 1 fix is ready for you to verify|[2,*] :count fixes are ready for you to verify', $stats['awaiting_confirmation']) }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Please confirm the fix or reopen the ticket.') }}
                        <flux:callout.link :href="route('tickets.index', ['status' => 'resolved'])" wire:navigate>{{ __('Review') }}</flux:callout.link>
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    @endif

    @php
        $cards = [
            [
                'label' => __('Open tickets'),
                'value' => $stats['open'],
                'icon' => 'inbox-stack',
                'color' => 'sky',
                'href' => route('tickets.index', ['status' => 'unresolved']),
            ],
            [
                'label' => __('Waiting for your reply'),
                'value' => $stats['pending_client'],
                'icon' => 'chat-bubble-left-ellipsis',
                'color' => 'orange',
                'href' => route('tickets.index', ['status' => 'pending_client']),
            ],
            [
                'label' => __('Fixed in last 30 days'),
                'value' => $stats['resolved_30d'],
                'icon' => 'check-circle',
                'color' => 'green',
            ],
            [
                'label' => __('Resolved within SLA'),
                'value' => $stats['compliance'] !== null ? $stats['compliance'].'%' : '—',
                'icon' => 'shield-check',
            ],
        ];
    @endphp

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" data-tour="stats">
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

    <flux:card data-tour="recent-tickets">
        <div class="mb-4 flex items-center justify-between">
            <flux:heading size="lg">{{ __('Recent tickets') }}</flux:heading>
            <flux:button size="sm" variant="ghost" icon-trailing="arrow-right" :href="route('tickets.index')" wire:navigate>{{ __('View all') }}</flux:button>
        </div>

        @if ($recentTickets->isEmpty())
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
                    @foreach ($recentTickets as $ticket)
                        <flux:table.row :key="$ticket->id">
                            <flux:table.cell class="max-w-80">
                                <div class="truncate">
                                    <flux:link :href="route('tickets.show', $ticket)" variant="ghost" class="font-medium" wire:navigate>{{ $ticket->title }}</flux:link>
                                </div>
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

    @if ($showTour)
        <x-tour :steps="[
            [
                'title' => __('Welcome to :app', ['app' => config('app.name')]),
                'body' => __('Here is a quick look around. It takes under a minute. We have filled your dashboard with example tickets so you can see how it works; your own tickets appear as soon as the tour ends.'),
            ],
            [
                'target' => 'report-bug',
                'title' => __('Report a bug'),
                'body' => __('Found a problem? Start here. Choose the system, describe what happened and attach screenshots. You get a reference number straight away.'),
            ],
            [
                'target' => 'notices',
                'title' => __('When we need you'),
                'body' => __('An orange notice means we are waiting for your reply. A green one means a fix is ready for you to check. Click the link to go straight to those tickets.'),
            ],
            [
                'target' => 'stats',
                'title' => __('Your tickets at a glance'),
                'body' => __('How many of your tickets are open, how many are waiting for your reply, and how many we fixed recently. Click a number to see those tickets.'),
            ],
            [
                'target' => 'recent-tickets',
                'side' => 'top',
                'title' => __('Recent tickets'),
                'body' => __('Your latest tickets with their status and deadline. Click a title to open the ticket, read our replies and answer us.'),
            ],
            [
                'target' => ['nav-tickets', 'nav-toggle'],
                'side' => 'right',
                'title' => auth()->user()->isClientAdmin() ? __('Company Tickets') : __('My Tickets'),
                'body' => auth()->user()->isClientAdmin()
                    ? __('Every ticket reported by anyone in your company. Search, filter and sort the list to find one quickly.')
                    : __('Every ticket you have reported. Search, filter and sort the list to find one quickly.'),
            ],
            [
                'target' => ['nav-manual', 'nav-toggle'],
                'side' => 'right',
                'title' => __('Need more detail?'),
                'body' => __('The User Manual explains every step, from choosing a severity to confirming a fix. You can replay this tour from there.'),
            ],
            [
                'target' => ['user-menu', 'user-menu-mobile'],
                'side' => 'right',
                'title' => __('Your account'),
                'body' => __('Click your name to open Settings: change your password, add a passkey, or switch on notifications for ticket updates.'),
            ],
            [
                'title' => __('You are all set'),
                'body' => __('Click Finish to see your own dashboard. We will email you whenever a ticket changes or gets a reply.'),
            ],
        ]" />
    @endif
</div>
