@php
    $user = auth()->user();
    $isStaff = $user->isStaff();
    $sla = $ticket->slaState();
    $progress = min(100, $ticket->resolutionProgress());
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
                    <span class="font-mono text-sm text-zinc-500">{{ $ticket->reference }}</span>
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
                    <div class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $ticket->description }}</div>
                </div>

                @if ($ticket->steps_to_reproduce)
                    <div>
                        <flux:heading size="sm" class="mb-1">{{ __('Steps to reproduce') }}</flux:heading>
                        <div class="whitespace-pre-line rounded-lg bg-zinc-50 p-3 font-mono text-sm text-zinc-700 dark:bg-white/5 dark:text-zinc-300">{{ $ticket->steps_to_reproduce }}</div>
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
            <div class="flex flex-col gap-4">
                <flux:heading size="lg">{{ __('Activity') }}</flux:heading>

                @foreach ($this->timeline as $item)
                    @if ($item instanceof \App\Models\TicketComment)
                        <div wire:key="comment-{{ $item->id }}" @class([
                            'rounded-xl border p-4',
                            'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' => $item->is_internal,
                            'border-sky-200 bg-sky-50/60 dark:border-sky-500/30 dark:bg-sky-500/5' => ! $item->is_internal && $item->user->isStaff(),
                            'border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900' => ! $item->is_internal && ! $item->user->isStaff(),
                        ])>
                            <div class="mb-2 flex flex-wrap items-center gap-2">
                                <flux:avatar size="xs" :name="$item->user->name" :initials="$item->user->initials()" />
                                <span class="text-sm font-medium text-zinc-800 dark:text-white">{{ $item->user->name }}</span>
                                @if ($item->user->isStaff())
                                    <flux:badge size="sm" color="sky">{{ __('Support team') }}</flux:badge>
                                @endif
                                @if ($item->is_internal)
                                    <flux:badge size="sm" color="amber" icon="lock-closed">{{ __('Internal note') }}</flux:badge>
                                @endif
                                <flux:text size="sm" class="ms-auto" title="{{ $item->created_at->format('d M Y H:i') }}">{{ $item->created_at->diffForHumans() }}</flux:text>
                            </div>
                            <div class="whitespace-pre-line text-sm text-zinc-700 dark:text-zinc-300">{{ $item->body }}</div>
                            @if ($item->attachments->isNotEmpty())
                                <div class="mt-3">
                                    @include('livewire.tickets.partials.attachments', ['attachments' => $item->attachments])
                                </div>
                            @endif
                        </div>
                    @else
                        <div wire:key="activity-{{ $item->id }}" class="flex items-center gap-2 px-2 text-sm">
                            <flux:icon :name="$item->icon()" variant="micro" @class([
                                'shrink-0',
                                'text-red-500' => $item->isSlaEvent() && $item->type !== 'at_risk',
                                'text-amber-500' => $item->type === 'at_risk',
                                'text-zinc-400' => ! $item->isSlaEvent(),
                            ]) />
                            <flux:text size="sm">
                                @if ($item->user)
                                    <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $item->user->name }}</span>
                                @endif
                                {{ $item->description() }}
                                · <span title="{{ $item->created_at->format('d M Y H:i') }}">{{ $item->created_at->diffForHumans() }}</span>
                            </flux:text>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- Reply --}}
            @can('comment', $ticket)
                <flux:card>
                    <form wire:submit="reply" class="space-y-4">
                        <flux:textarea wire:model="body" :label="$isStaff ? __('Reply') : __('Add a reply')" rows="4"
                            :placeholder="$isStaff ? __('Write a reply to the client, or tick “Internal note” for team-only notes…') : __('Add more details or answer our questions…')" />

                        <flux:input type="file" wire:model="files" multiple size="sm" />
                        <div wire:loading wire:target="files"><flux:text size="sm">{{ __('Uploading…') }}</flux:text></div>
                        @if ($files)
                            <ul class="space-y-1 text-sm">
                                @foreach ($files as $index => $file)
                                    <li class="flex items-center gap-2" wire:key="reply-file-{{ $index }}">
                                        <flux:icon name="paper-clip" variant="micro" class="text-zinc-400" />
                                        <span class="truncate">{{ $file->getClientOriginalName() }}</span>
                                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeFile({{ $index }})" />
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <flux:error name="files.*" />

                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex flex-wrap items-center gap-4">
                                @if ($isStaff)
                                    <flux:checkbox wire:model.live="internal" :label="__('Internal note (hidden from client)')" />
                                    <flux:select wire:model="replyStatus" size="sm" class="w-48">
                                        <flux:select.option value="">{{ __('Keep status') }}</flux:select.option>
                                        @foreach (\App\Enums\TicketStatus::staffSelectable() as $option)
                                            @if ($option !== $ticket->status)
                                                <flux:select.option :value="$option->value">{{ __('Set to :status', ['status' => $option->label()]) }}</flux:select.option>
                                            @endif
                                        @endforeach
                                    </flux:select>
                                @endif
                            </div>

                            <div class="flex items-center gap-2">
                                @can('respondToResolution', $ticket)
                                    <flux:button variant="danger" icon="arrow-uturn-left" wire:click="reopen">{{ __('Still not fixed') }}</flux:button>
                                @endcan
                                <flux:button type="submit" variant="primary" icon="paper-airplane">
                                    {{ $internal ? __('Add note') : __('Send reply') }}
                                </flux:button>
                            </div>
                        </div>
                    </form>
                </flux:card>
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
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="text-zinc-600 dark:text-zinc-400">{{ __('Resolution') }}</span>
                        <span @class([
                            'font-medium',
                            'text-red-600 dark:text-red-400' => $sla === \App\Enums\SlaState::Breached,
                            'text-zinc-800 dark:text-white' => $sla !== \App\Enums\SlaState::Breached,
                        ])>{{ $ticket->resolutionCountdown() }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-white/10">
                        <div @class([
                            'h-full rounded-full',
                            'bg-red-500' => $progress >= 100,
                            'bg-amber-500' => $progress >= config('sla.at_risk_threshold') && $progress < 100,
                            'bg-green-500' => $progress < config('sla.at_risk_threshold'),
                        ]) style="width: {{ $progress }}%"></div>
                    </div>
                    @if ($ticket->isPaused() && ! $ticket->status->isFinished())
                        <flux:text size="sm" class="mt-1 flex items-center gap-1"><flux:icon name="pause" variant="micro" /> {{ __('Clock paused while waiting on client') }}</flux:text>
                    @endif
                </div>

                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <dt class="text-zinc-500">{{ __('Response due') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">{{ $ticket->response_due_at->format('d M, H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ __('First response') }}</dt>
                        <dd @class(['font-medium', 'text-red-600 dark:text-red-400' => $ticket->isResponseBreached(), 'text-zinc-800 dark:text-white' => ! $ticket->isResponseBreached()])>
                            {{ $ticket->first_responded_at?->format('d M, H:i') ?? ($ticket->isResponseBreached() ? __('Overdue') : __('Pending')) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ __('Resolution due') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">{{ $ticket->resolution_due_at->format('d M, H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">{{ __('Resolved') }}</dt>
                        <dd class="font-medium text-zinc-800 dark:text-white">{{ $ticket->resolved_at?->format('d M, H:i') ?? '—' }}</dd>
                    </div>
                    @if ($ticket->paused_minutes > 0)
                        <div class="col-span-2">
                            <dt class="text-zinc-500">{{ __('Time paused') }}</dt>
                            <dd class="font-medium text-zinc-800 dark:text-white">{{ \App\Models\SlaPolicy::formatMinutes($ticket->paused_minutes) }}</dd>
                        </div>
                    @endif
                </dl>
            </flux:card>

            {{-- Staff management --}}
            @can('manage', $ticket)
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Manage') }}</flux:heading>

                    <flux:select wire:model.live="status" :label="__('Status')">
                        @foreach (\App\Enums\TicketStatus::cases() as $option)
                            @if (in_array($option, \App\Enums\TicketStatus::staffSelectable(), true) || $option === $ticket->status)
                                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                            @endif
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="priority" :label="__('Priority')">
                        @foreach (\App\Enums\TicketPriority::cases() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model.live="assignee_id" :label="__('Assignee')">
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
                <dl class="space-y-3 text-sm">
                    @if ($isStaff)
                        <div class="flex justify-between gap-4">
                            <dt class="text-zinc-500">{{ __('Client') }}</dt>
                            <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->company->name }}</dd>
                        </div>
                    @endif
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('System') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->project->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Type') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->category->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Environment') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->environment->label() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Reporter') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->reporter->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Assignee') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->assignee?->name ?? __('Unassigned') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Opened') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->created_at->format('d M Y, H:i') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">{{ __('Last update') }}</dt>
                        <dd class="text-end font-medium text-zinc-800 dark:text-white">{{ $ticket->updated_at->diffForHumans() }}</dd>
                    </div>
                </dl>
            </flux:card>
        </div>
    </div>
</div>
