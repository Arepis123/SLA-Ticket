{{-- System Admins: who has had this entry on screen, and when. --}}
@can('viewEntrySeen', $ticket)
    @php($views = $this->entrySeenBy->get(\App\Models\TicketEntryView::keyFor($entry), collect()))

    <flux:dropdown position="bottom" align="start">
        <flux:button
            size="xs"
            variant="subtle"
            icon="eye"
            class="text-zinc-400!"
            :aria-label="trans_choice('Seen by :count person|Seen by :count people', $views->count())"
        >{{ $views->count() }}</flux:button>

        <flux:popover class="w-72 space-y-3">
            <div>
                <flux:heading size="sm">{{ __('Seen by') }}</flux:heading>
                <flux:text size="sm">{{ __('First time on screen, with the ticket status then.') }}</flux:text>
            </div>

            @forelse ($views as $view)
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <flux:heading class="truncate">{{ $view->user->name }}</flux:heading>
                        <flux:text size="sm">{{ $view->seen_at->format('d M Y, H:i') }}</flux:text>
                    </div>
                    <x-ticket.status-badge :status="$view->ticket_status" />
                </div>
            @empty
                <flux:text size="sm">{{ __('Nobody else has seen this yet.') }}</flux:text>
            @endforelse
        </flux:popover>
    </flux:dropdown>
@endcan
