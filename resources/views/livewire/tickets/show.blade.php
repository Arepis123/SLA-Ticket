@php
    $user = auth()->user();
    $isStaff = $user->isStaff();
    $sla = $ticket->slaState();
    $progress = min(100, $ticket->resolutionProgress());
    $progressColor = match (true) {
        $progress >= 100 => 'red',
        $progress >= config('sla.at_risk_threshold') => 'amber',
        default => 'green',
    };
    $details = array_filter([
        __('Client') => $isStaff ? $ticket->company->name : null,
        __('System') => $ticket->project->name,
        __('Type') => $ticket->category->label(),
        __('Environment') => $ticket->environment->label(),
        __('Reporter') => $ticket->reporter->name,
        __('Assignee') => $ticket->assignee?->name ?? __('Unassigned'),
        __('Opened') => $ticket->created_at->format('d M Y, H:i'),
        __('Last update') => $ticket->updated_at->diffForHumans(),
    ]);
    $slaDates = [
        __('Response due') => $ticket->response_due_at->format('d M, H:i'),
        __('First response') => $ticket->first_responded_at?->format('d M, H:i') ?? ($ticket->isResponseBreached() ? __('Overdue') : __('Pending')),
        __('Resolution due') => $ticket->resolution_due_at->format('d M, H:i'),
        __('Resolved') => $ticket->resolved_at?->format('d M, H:i') ?? '—',
    ];
@endphp

<div class="flex flex-col gap-6" wire:poll.60s>
    {{-- Header --}}
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('tickets.index')" wire:navigate>{{ $user->isClient() ? __('My Tickets') : __('Tickets') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ $ticket->reference }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <flux:heading size="xl" level="1" class="break-words">{{ $ticket->title }}</flux:heading>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <flux:badge size="sm" variant="outline" class="font-mono">{{ $ticket->reference }}</flux:badge>
                    <x-ticket.status-badge :status="$ticket->status" />
                    <x-ticket.priority-badge :priority="$ticket->priority" />
                    <flux:badge size="sm" :color="$sla->color()" :icon="$sla->icon()">{{ __('SLA') }}: {{ $sla->label() }}</flux:badge>
                </div>
            </div>

            @can('manage', $ticket)
                @if (! $ticket->assignee_id)
                    <flux:button icon="hand-raised" wire:click="assignToMe">{{ __('Assign to me') }}</flux:button>
                @endif
            @endcan
        </div>
    </div>

    {{-- Client: verify resolution --}}
    @can('respondToResolution', $ticket)
        <flux:callout icon="check-badge" color="green">
            <flux:callout.heading>{{ __('Our team has marked this issue as fixed') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Please test it on your side. If it works, confirm below. If not, write what is still wrong in the reply box and click "Still not fixed".') }}</flux:callout.text>
            <x-slot name="actions">
                <flux:button size="sm" variant="primary" icon="check" wire:click="confirmFix" wire:confirm="{{ __('Confirm the issue is fixed and close this ticket?') }}">{{ __('Confirm fix') }}</flux:button>
            </x-slot>
        </flux:callout>
    @endcan

    @if ($ticket->status === \App\Enums\TicketStatus::PendingClient && $user->isClient())
        <flux:callout icon="chat-bubble-left-ellipsis" color="orange">
            <flux:callout.heading>{{ __('We need your input') }}</flux:callout.heading>
            <flux:callout.text>{{ __('Please reply below with the requested information. The ticket goes back to our team automatically when you reply.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="flex min-w-0 flex-col gap-6 lg:col-span-2">
            <flux:card class="space-y-5">
                <div class="flex items-center gap-3">
                    <flux:avatar size="sm" :name="$ticket->reporter->name" :initials="$ticket->reporter->initials()" />
                    <div>
                        <flux:heading>{{ $ticket->reporter->name }}</flux:heading>
                        <flux:text size="sm">{{ __('reported :time', ['time' => $ticket->created_at->diffForHumans()]) }} · {{ $ticket->created_at->format('d M Y, H:i') }}</flux:text>
                    </div>
                </div>

                <div>
                    <flux:heading size="sm" class="mb-1">{{ __('Description') }}</flux:heading>
                    <flux:text class="whitespace-pre-line">{{ $ticket->description }}</flux:text>
                </div>

                @if ($ticket->steps_to_reproduce)
                    <div>
                        <flux:heading size="sm" class="mb-1">{{ __('Steps to reproduce') }}</flux:heading>
                        <flux:card size="sm" variant="filled">
                            <flux:text class="whitespace-pre-line font-mono">{{ $ticket->steps_to_reproduce }}</flux:text>
                        </flux:card>
                    </div>
                @endif

                @if ($this->ticketAttachments->isNotEmpty())
                    <div>
                        <flux:heading size="sm" class="mb-2">{{ __('Attachments') }}</flux:heading>
                        @include('livewire.tickets.partials.attachments', ['attachments' => $this->ticketAttachments])
                    </div>
                @endif
            </flux:card>

            {{-- Timeline --}}
            <div>
                <flux:heading size="lg" class="mb-4">{{ __('Activity') }}</flux:heading>

                <flux:timeline>
                    @foreach ($this->timeline as $item)
                        @if ($item instanceof \App\Models\TicketComment)
                            <flux:timeline.item wire:key="comment-{{ $item->id }}">
                                <flux:timeline.indicator variant="bare">
                                    <flux:avatar size="xs" circle :name="$item->user->name" :initials="$item->user->initials()" :color="$item->is_internal ? 'amber' : ($item->user->isStaff() ? 'sky' : null)" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading>{{ $item->user->name }}</flux:heading>
                                        @if ($item->user->isStaff())
                                            <flux:badge size="sm" color="sky">{{ __('Support team') }}</flux:badge>
                                        @endif
                                        @if ($item->is_internal)
                                            <flux:badge size="sm" color="amber" icon="lock-closed">{{ __('Internal note') }}</flux:badge>
                                        @endif
                                        <flux:tooltip :content="$item->created_at->format('d M Y H:i')">
                                            <flux:text size="sm">{{ $item->created_at->diffForHumans() }}</flux:text>
                                        </flux:tooltip>
                                    </div>

                                    @if ($item->is_internal)
                                        <flux:callout color="amber" class="mt-2">
                                            <flux:callout.text class="whitespace-pre-line">{{ $item->body }}</flux:callout.text>
                                        </flux:callout>
                                    @else
                                        <flux:card size="sm" class="mt-2">
                                            <flux:text class="whitespace-pre-line text-zinc-800! dark:text-zinc-200!">{{ $item->body }}</flux:text>
                                        </flux:card>
                                    @endif

                                    @if ($item->attachments->isNotEmpty())
                                        <div class="mt-2">
                                            @include('livewire.tickets.partials.attachments', ['attachments' => $item->attachments])
                                        </div>
                                    @endif
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @else
                            <flux:timeline.item wire:key="activity-{{ $item->id }}">
                                <flux:timeline.indicator :color="$item->type === 'at_risk' ? 'amber' : ($item->isSlaEvent() ? 'red' : null)">
                                    <flux:icon :name="$item->icon()" variant="micro" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <flux:text>
                                        @if ($item->user)
                                            <flux:text inline variant="strong">{{ $item->user->name }}</flux:text>
                                        @endif
                                        {{ $item->description() }}
                                    </flux:text>
                                    <flux:tooltip :content="$item->created_at->format('d M Y H:i')">
                                        <flux:text size="sm">{{ $item->created_at->diffForHumans() }}</flux:text>
                                    </flux:tooltip>
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endif
                    @endforeach
                </flux:timeline>
            </div>

            {{-- Reply --}}
            @can('comment', $ticket)
                <form wire:submit="reply" class="space-y-3">
                    <flux:composer
                        wire:model="body"
                        :label="$isStaff ? __('Reply') : __('Add a reply')"
                        :placeholder="$isStaff ? __('Write a reply to the client, or switch on “Internal note” for team-only notes…') : __('Add more details or answer our questions…')"
                        rows="3"
                        max-rows="12"
                    >
                        @if ($isStaff)
                            <x-slot name="actionsLeading">
                                <flux:select variant="listbox" size="sm" wire:model="replyStatus" class="w-52">
                                    <flux:select.option value="">{{ __('Keep status') }}</flux:select.option>
                                    @foreach (\App\Enums\TicketStatus::staffSelectable() as $option)
                                        @if ($option !== $ticket->status)
                                            <flux:select.option :value="$option->value">{{ __('Set to :status', ['status' => $option->label()]) }}</flux:select.option>
                                        @endif
                                    @endforeach
                                </flux:select>
                            </x-slot>
                        @endif

                        <x-slot name="actionsTrailing">
                            @can('respondToResolution', $ticket)
                                <flux:button size="sm" variant="danger" icon="arrow-uturn-left" wire:click="reopen">{{ __('Still not fixed') }}</flux:button>
                            @endcan
                            <flux:button type="submit" size="sm" variant="primary" icon="paper-airplane">
                                {{ $internal ? __('Add note') : __('Send reply') }}
                            </flux:button>
                        </x-slot>
                    </flux:composer>

                    @if ($isStaff)
                        <flux:switch wire:model.live="internal" :label="__('Internal note')" :description="__('Only your team can see internal notes.')" align="left" />
                    @endif

                    <flux:file-upload wire:model="files" multiple>
                        <flux:file-upload.dropzone :heading="__('Attach files')" :text="__('Up to 5 files, max 10 MB each')" with-progress inline />
                    </flux:file-upload>

                    @foreach ($files as $index => $file)
                        <flux:file-item
                            wire:key="reply-file-{{ $index }}"
                            :heading="$file->getClientOriginalName()"
                            :image="$file->isPreviewable() ? $file->temporaryUrl() : null"
                            :size="$file->getSize()"
                            :invalid="$errors->has('files.'.$index)"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="removeFile({{ $index }})" :aria-label="__('Remove file')" />
                            </x-slot>
                        </flux:file-item>
                    @endforeach

                    <flux:error name="files" />
                    <flux:error name="files.*" />
                </form>
            @else
                <flux:callout icon="lock-closed">
                    <flux:callout.text>{{ __('This ticket is closed. Please open a new ticket if the problem comes back.') }}</flux:callout.text>
                </flux:callout>
            @endcan
        </div>

        {{-- Sidebar --}}
        <div class="flex flex-col gap-6">
            {{-- SLA panel --}}
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Service level') }}</flux:heading>
                    <flux:badge :color="$sla->color()" :icon="$sla->icon()" size="sm">{{ $sla->label() }}</flux:badge>
                </div>

                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <flux:text>{{ __('Resolution') }}</flux:text>
                        <flux:text variant="strong" :color="$sla === \App\Enums\SlaState::Breached ? 'red' : null">{{ $ticket->resolutionCountdown() }}</flux:text>
                    </div>

                    <flux:progress :value="$progress" :color="$progressColor" />

                    @if ($ticket->isPaused() && ! $ticket->status->isFinished())
                        <flux:text size="sm" class="mt-1 flex items-center gap-1"><flux:icon name="pause" variant="micro" /> {{ __('Clock paused while waiting on client') }}</flux:text>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    @foreach ($slaDates as $label => $value)
                        <div>
                            <flux:text size="sm">{{ $label }}</flux:text>
                            <flux:text variant="strong" :color="$label === __('First response') && $ticket->isResponseBreached() ? 'red' : null">{{ $value }}</flux:text>
                        </div>
                    @endforeach

                    @if ($ticket->paused_minutes > 0)
                        <div class="col-span-2">
                            <flux:text size="sm">{{ __('Time paused') }}</flux:text>
                            <flux:text variant="strong">{{ \App\Models\SlaPolicy::formatMinutes($ticket->paused_minutes) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Staff management --}}
            @can('manage', $ticket)
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Manage') }}</flux:heading>

                    <flux:select variant="listbox" wire:model.live="status" :label="__('Status')">
                        @foreach (\App\Enums\TicketStatus::cases() as $option)
                            @if (in_array($option, \App\Enums\TicketStatus::staffSelectable(), true) || $option === $ticket->status)
                                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>

                    <flux:select variant="listbox" wire:model.live="priority" :label="__('Priority')">
                        @foreach (\App\Enums\TicketPriority::cases() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select variant="listbox" searchable wire:model.live="assignee_id" :label="__('Assignee')">
                        <flux:select.option value="">{{ __('Unassigned') }}</flux:select.option>
                        @foreach ($this->staff as $member)
                            <flux:select.option :value="$member->id">{{ $member->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </flux:card>
            @endcan

            {{-- Details --}}
            <flux:card>
                <flux:heading size="lg" class="mb-4">{{ __('Details') }}</flux:heading>

                <div class="space-y-3">
                    @foreach ($details as $label => $value)
                        <div class="flex justify-between gap-4">
                            <flux:text>{{ $label }}</flux:text>
                            <flux:text variant="strong" class="text-end">{{ $value }}</flux:text>
                        </div>
                    @endforeach
                </div>
            </flux:card>
        </div>
    </div>
</div>
