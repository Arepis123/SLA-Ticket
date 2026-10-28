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
        __('Fix type') => $ticket->resolution_type?->label(),
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

{{-- Guards unsaved reply / work log text when leaving the page, and confirms deletes. --}}
<div
    class="flex flex-col gap-6"
    wire:poll.60s
    x-data="{
        pendingUrl: null,
        pendingDelete: { action: null, id: null, message: '' },
        deleting: false,
        confirmDelete(action, id, message) {
            this.pendingDelete = { action, id, message };
            this.$flux.modal('confirm-delete').show();
        },
        async runDelete() {
            if (this.deleting) return;

            const { action, id } = this.pendingDelete;
            // Not this.$root: the Delete button sits inside the modal's own Alpine component, so $root is the dialog.
            const entry = document.querySelector(`[data-entry='${action}-${id}']`);

            this.deleting = true;
            this.$flux.modal('confirm-delete').close();

            // Fade and drift the entry away, then fold its space so the rest of the timeline glides up.
            if (entry && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                // Let the modal and its backdrop finish closing first, or they hide the start of the animation.
                await new Promise((resolve) => setTimeout(resolve, 250));

                entry.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                entry.style.overflow = 'hidden';

                await entry.animate(
                    [
                        { opacity: 1, transform: 'none', filter: 'none' },
                        { opacity: 0.6, transform: 'translateX(0.5rem)', filter: 'blur(0px)', offset: 0.3 },
                        { opacity: 0, transform: 'translateX(3rem) scale(0.96)', filter: 'blur(6px)' },
                    ],
                    { duration: 450, easing: 'cubic-bezier(0.4, 0, 1, 1)', fill: 'forwards' },
                ).finished;

                const style = getComputedStyle(entry);

                await entry.animate(
                    [
                        { height: `${entry.offsetHeight}px`, paddingTop: style.paddingTop, paddingBottom: style.paddingBottom, marginTop: style.marginTop, marginBottom: style.marginBottom },
                        { height: '0px', paddingTop: '0px', paddingBottom: '0px', marginTop: '0px', marginBottom: '0px' },
                    ],
                    { duration: 350, easing: 'cubic-bezier(0.4, 0, 0.2, 1)', fill: 'forwards' },
                ).finished;
            }

            await this.$wire.call(action, id);

            // Still on the page means the delete was refused; bring the entry back.
            if (entry?.isConnected) {
                entry.getAnimations().forEach((animation) => animation.cancel());
                entry.style.overflow = '';
            }

            this.deleting = false;
        },
        leaving: false,
        saving: false,
        hasText(value) {
            return String(value ?? '').replace(/<[^>]*>/g, '').replace(/&nbsp;/g, ' ').trim() !== '';
        },
        replyDirty() {
            return this.hasText(this.$wire.body) || (this.$wire.files ?? []).length > 0;
        },
        workLogDirty() {
            return this.hasText(this.$wire.workLogBody) || (this.$wire.workLogFiles ?? []).length > 0;
        },
        dirty() {
            return this.replyDirty() || this.workLogDirty();
        },
        intercept(event) {
            if (this.leaving || event.detail.history || ! this.dirty()) return;

            event.preventDefault();
            this.pendingUrl = event.detail.url;
            this.$flux.modal('unsaved-changes').show();
        },
        leave() {
            this.leaving = true;
            this.$flux.modal('unsaved-changes').close();
            Livewire.navigate(this.pendingUrl);
        },
        async saveAndLeave() {
            this.saving = true;

            if (this.replyDirty()) await this.$wire.reply();
            if (this.workLogDirty()) await this.$wire.addWorkLog();

            this.saving = false;

            // A failed validation leaves the text in place; stay so the errors are visible.
            this.dirty() ? this.$flux.modal('unsaved-changes').close() : this.leave();
        },
    }"
    x-on:livewire:navigate.document="intercept($event)"
    x-on:beforeunload.window="if (! leaving && dirty()) { $event.preventDefault(); $event.returnValue = ''; }"
>
    {{-- Header --}}
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('tickets.index')" wire:navigate>{{ $user->isClientAdmin() ? __('Company Tickets') : ($user->isClient() ? __('My Tickets') : __('Tickets')) }}</flux:breadcrumbs.item>
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
                @if (! $ticket->assignee_id && ! $ticket->isMerged())
                    <flux:button icon="hand-raised" wire:click="assignToMe">{{ __('Assign to me') }}</flux:button>
                @endif
            @endcan
        </div>
    </div>

    @if ($ticket->isMerged())
        <flux:callout icon="arrows-pointing-in" color="zinc">
            <flux:callout.heading>{{ __('Merged into :reference', ['reference' => $ticket->mergedInto->reference]) }}</flux:callout.heading>
            <flux:callout.text>
                {{ __('This report describes the same issue as ":title", so the work continues there. Updates on that ticket are sent to you too.', ['title' => $ticket->mergedInto->title]) }}
            </flux:callout.text>
            <x-slot name="actions">
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="primary" icon-trailing="arrow-right" :href="route('tickets.show', $ticket->mergedInto)" wire:navigate>{{ __('Open original') }}</flux:button>
                    @can('merge', $ticket)
                        <flux:button size="sm" variant="ghost" icon="arrows-pointing-out" wire:click="unmerge" wire:confirm="{{ __('Unmerge this ticket? It will reopen and its SLA clock will resume.') }}">{{ __('Unmerge') }}</flux:button>
                    @endcan
                </div>
            </x-slot>
        </flux:callout>
    @endif

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

            {{-- Reports entries that stay on screen long enough to count as seen. --}}
            <div x-data="entrySeen">
            <flux:tab.group>
                <flux:tabs>
                    <flux:tab name="activity">{{ __('Activity') }}</flux:tab>
                    <flux:tab
                        name="work-log"
                        x-data="{ unseen: {{ $this->unseenWorkLogCount }} }"
                        x-on:click="if (unseen > 0) { unseen = 0; $wire.markWorkLogsSeen() }"
                    >
                        {{ __('Work Log') }}

                        <template x-if="unseen > 0">
                            <flux:badge
                                rounded
                                size="sm"
                                color="green"
                                variant="solid"
                                x-text="unseen"
                                :title="__('New work log entries')"
                            />
                        </template>
                    </flux:tab>
                </flux:tabs>

                <flux:tab.panel name="activity" class="space-y-6">
                {{-- Timeline --}}
                <div>
                <flux:timeline>
                    @foreach ($this->timeline as $item)
                        @if ($item instanceof \App\Models\TicketComment)
                            <flux:timeline.item wire:key="comment-{{ $item->id }}" data-entry="deleteComment-{{ $item->id }}" :data-seen="$this->seenMarker($item)" align="baseline">
                                <flux:timeline.indicator >
                                    <flux:avatar size="sm" circle class="text-xs after:hidden" :name="$item->user->name" :color="$item->user->isStaff() ? 'sky' : null" />                                    
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading>{{ $item->user->name }}</flux:heading>
                                        @if ($item->user->isStaff())
                                            <flux:badge size="sm" color="sky" icon="wrench-screwdriver">{{ __('Support team') }}</flux:badge>
                                        @endif
                                        @if ($item->is_internal)
                                            <flux:badge size="sm" color="amber" icon="lock-closed">{{ __('Internal note') }}</flux:badge>
                                        @endif
                                        <flux:tooltip :content="$item->created_at->format('d M Y H:i')">
                                            <flux:text>{{ $item->created_at->diffForHumans() }}</flux:text>
                                        </flux:tooltip>
                                        @include('livewire.tickets.partials.seen-by', ['entry' => $item])
                                        @can('deleteComment', [$ticket, $item])
                                            <flux:button
                                                size="xs"
                                                variant="subtle"
                                                icon="trash"
                                                class="ms-auto opacity-50 hover:opacity-100 focus-visible:opacity-100"
                                                x-on:click="confirmDelete('deleteComment', {{ $item->id }}, @js($item->is_internal ? __('This internal note and its attachments will be permanently deleted.') : __('This reply and its attachments will be permanently deleted. The client may already have been emailed a copy.')))"
                                                :aria-label="__('Delete')"
                                            />
                                        @endcan
                                    </div>

                                    @if ($item->is_internal)
                                        <flux:card size="sm" class="mt-2">
                                            <flux:text class="whitespace-pre-line text-zinc-800! dark:text-zinc-200!">{{ $item->body }}</flux:text>
                                        </flux:card>
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
                            <flux:timeline.item wire:key="activity-{{ $item->id }}" :data-seen="$this->seenMarker($item)">
                                <flux:timeline.indicator :color="$item->type === 'at_risk' ? 'amber' : ($item->isSlaEvent() ? 'red' : null)">
                                    <flux:icon size="xs" :name="$item->icon()" variant="micro" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <div class="flex flex-wrap items-baseline gap-x-1">
                                        @if ($item->user)
                                            <flux:heading>{{ $item->user->name }}</flux:heading>
                                        @endif
                                        <flux:text>{{ $item->description() }}</flux:text>
                                        <flux:tooltip :content="$item->created_at->format('d M Y H:i')">
                                            <flux:text>· {{ $item->created_at->diffForHumans() }}</flux:text>
                                        </flux:tooltip>
                                        @include('livewire.tickets.partials.seen-by', ['entry' => $item])
                                    </div>
                                    
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endif
                    @endforeach
                </flux:timeline>
            </div>

            {{-- Reply --}}
            @can('comment', $ticket)
                <form wire:submit="reply">
                    <flux:card body="inset" variant="filled">
                        <flux:card.header>
                            <flux:card.heading>{{ $isStaff ? __('Reply') : __('Add a reply') }}</flux:card.heading>
                        </flux:card.header>
                        
                        <flux:composer
                            wire:model="body"
                            :aria-label="$isStaff ? __('Reply') : __('Add a reply')"
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
                        

                        <flux:card.footer class="space-y-3">
                            @if ($isStaff)
                                {{-- Closing a ticket that was already resolved keeps its fix type, so only ask when it has none. --}}
                                <div
                                    x-show="$wire.replyStatus === 'resolved' || ($wire.replyStatus === 'closed' && @js($ticket->needsResolutionType(\App\Enums\TicketStatus::Closed)))"
                                    x-cloak
                                >
                                    <flux:radio.group wire:model="resolutionType" variant="cards" :label="__('How was this ticket dealt with?')" class="max-sm:flex-col">
                                        @foreach (\App\Enums\ResolutionType::cases() as $type)
                                            @if ($type === \App\Enums\ResolutionType::NotRelated)
                                                {{-- Only closing can be "not related"; resolving means something was fixed. --}}
                                                <flux:radio :value="$type->value" :label="$type->label()" :description="$type->description()" x-show="$wire.replyStatus === 'closed'" />
                                            @else
                                                <flux:radio :value="$type->value" :label="$type->label()" :description="$type->description()" />
                                            @endif
                                        @endforeach
                                    </flux:radio.group>
                                </div>

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
                        </flux:card.footer>
                    </flux:card>
                </form>
            @else
                <flux:callout icon="lock-closed">
                    <flux:callout.text>{{ __('This ticket is closed. Please open a new ticket if the problem comes back.') }}</flux:callout.text>
                </flux:callout>
            @endcan
                </flux:tab.panel>

                <flux:tab.panel name="work-log" class="space-y-4">
                    <flux:timeline>
                        @foreach ($this->workLogs as $log)
                            <flux:timeline.item wire:key="work-log-{{ $log->id }}" data-entry="deleteWorkLog-{{ $log->id }}" :data-seen="$this->seenMarker($log)" align="baseline">
                                <flux:timeline.indicator variant="bare">
                                    <flux:avatar size="sm" circle class="text-xs after:hidden" :name="$log->user->name" color="violet" />
                                </flux:timeline.indicator>

                                <flux:timeline.content>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <flux:heading>{{ $log->user->name }}</flux:heading>
                                        <flux:tooltip :content="$log->created_at->format('d M Y H:i')">
                                            <flux:text>{{ $log->created_at->diffForHumans() }}</flux:text>
                                        </flux:tooltip>
                                        @include('livewire.tickets.partials.seen-by', ['entry' => $log])
                                        @can('deleteWorkLog', [$ticket, $log])
                                            <flux:button
                                                size="xs"
                                                variant="ghost"
                                                icon="trash"
                                                class="ms-auto opacity-50 hover:opacity-100 focus-visible:opacity-100"
                                                x-on:click="confirmDelete('deleteWorkLog', {{ $log->id }}, @js(__('This work log entry and its attachments will be permanently deleted.')))"
                                                :aria-label="__('Delete')"
                                            />
                                        @endcan
                                    </div>

                                    <flux:card size="sm" class="mt-2">
                                        <div class="rich-text text-sm text-zinc-800! dark:text-zinc-200!">{!! $log->body !!}</div>
                                    </flux:card>

                                    @if ($log->attachments->isNotEmpty())
                                        <div class="mt-2">
                                            @include('livewire.tickets.partials.attachments', ['attachments' => $log->attachments])
                                        </div>
                                    @endif
                                </flux:timeline.content>
                            </flux:timeline.item>
                        @endforeach
                    </flux:timeline>

                    @if ($this->workLogs->isEmpty())
                        <flux:text class="text-zinc-400">{{ __('No work log entries yet.') }}</flux:text>
                    @endif

                    @can('addWorkLog', $ticket)
                        <form wire:submit="addWorkLog">
                            <flux:card body="inset" variant="filled">
                                <flux:card.header>
                                    <flux:card.heading>{{ __('Add work log entry') }}</flux:card.heading>
                                </flux:card.header>
                               
                                <flux:editor wire:model="workLogBody" toolbar="heading | bold italic underline strike highlight | bullet ordered blockquote | link | align" :aria-label="__('Add work log entry')" placeholder="{{ __('Describe the work done…') }}" />
                               
                                <flux:card.footer class="space-y-3">
                                    <flux:file-upload wire:model="workLogFiles" multiple>
                                        <flux:file-upload.dropzone :heading="__('Attach files')" :text="__('Up to 5 files, max 10 MB each')" with-progress inline />
                                    </flux:file-upload>

                                    @foreach ($workLogFiles as $index => $file)
                                        <flux:file-item
                                            wire:key="work-log-file-{{ $index }}"
                                            :heading="$file->getClientOriginalName()"
                                            :image="$file->isPreviewable() ? $file->temporaryUrl() : null"
                                            :size="$file->getSize()"
                                            :invalid="$errors->has('workLogFiles.'.$index)"
                                        >
                                            <x-slot name="actions">
                                                <flux:file-item.remove wire:click="removeWorkLogFile({{ $index }})" :aria-label="__('Remove file')" />
                                            </x-slot>
                                        </flux:file-item>
                                    @endforeach

                                    <flux:error name="workLogFiles" />
                                    <flux:error name="workLogFiles.*" />

                                    <div class="flex justify-end">
                                        <flux:button type="submit" size="sm" variant="primary" icon="plus">{{ __('Add entry') }}</flux:button>
                                    </div>
                                </flux:card.footer>
                            </flux:card>
                        </form>
                    @endcan
                </flux:tab.panel>
            </flux:tab.group>
            </div>
        </div>

        {{-- Sidebar --}}
        <div class="flex min-w-0 flex-col gap-6">
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
                    @elseif ($ticket->isOutsideBusinessHours())
                        <flux:text size="sm" class="mt-1 flex items-center gap-1"><flux:icon name="moon" variant="micro" /> {{ __('Clock stopped outside business hours') }}</flux:text>
                    @endif

                    @if ($ticket->usesBusinessHours())
                        <flux:text size="sm" class="mt-1">{{ __('Counted in business hours only.') }}</flux:text>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3">
                    @foreach ($slaDates as $label => $value)
                        <div>
                            <flux:text size="sm">{{ $label }}</flux:text>
                            <flux:text variant="strong" :color="$label === __('First response') && $ticket->isResponseBreached() ? 'red' : null">{{ $value }}</flux:text>
                        </div>
                    @endforeach

                    @php($pausedMinutes = $ticket->usesBusinessHours() ? $ticket->paused_business_minutes : $ticket->paused_minutes)
                    @if ($pausedMinutes > 0)
                        <div class="col-span-2">
                            <flux:text size="sm">{{ __('Time paused') }}</flux:text>
                            <flux:text variant="strong">{{ \App\Models\SlaPolicy::formatMinutes($pausedMinutes) }}</flux:text>
                        </div>
                    @endif
                </div>
            </flux:card>

            {{-- Staff management --}}
            @can('manage', $ticket)
                <flux:card class="space-y-4">
                    <flux:heading size="lg">{{ __('Manage') }}</flux:heading>

                    @if ($ticket->isMerged())
                        <flux:text>{{ __('Status, priority, assignee, system and type are managed on :reference while this ticket is merged.', ['reference' => $ticket->mergedInto->reference]) }}</flux:text>
                    @else
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

                    <flux:select variant="listbox" searchable wire:model.live="project_id" :label="__('System')">
                        @foreach ($this->projects as $project)
                            <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select variant="listbox" wire:model.live="category" :label="__('Type')">
                        @foreach (\App\Enums\TicketCategory::cases() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    @can('merge', $ticket)
                        <flux:modal.trigger name="merge-ticket">
                            <flux:button icon="arrows-pointing-in" variant="subtle" size="sm" class="w-full">{{ __('Merge into another ticket…') }}</flux:button>
                        </flux:modal.trigger>
                    @endcan
                    @endif
                </flux:card>
            @endcan

            {{-- Duplicates merged into this ticket --}}
            @if ($isStaff && $this->duplicates->isNotEmpty())
                <flux:card class="space-y-3">
                    <flux:heading size="lg">{{ __('Merged duplicates') }} <flux:badge size="sm" class="ms-1">{{ $this->duplicates->count() }}</flux:badge></flux:heading>
                    <flux:text size="sm">{{ __('Their reporters get this ticket\'s updates.') }}</flux:text>

                    <div class="space-y-2">
                        @foreach ($this->duplicates as $duplicate)
                            <div wire:key="duplicate-{{ $duplicate->id }}" class="min-w-0">
                                <flux:link :href="route('tickets.show', $duplicate)" variant="ghost" class="font-mono text-sm" wire:navigate>{{ $duplicate->reference }}</flux:link>
                                <flux:text size="sm" class="truncate">{{ $duplicate->title }} · {{ $duplicate->reporter->name }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            @endif

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

    @can('merge', $ticket)
        @unless ($ticket->isMerged())
            <flux:modal name="merge-ticket" class="w-full max-w-lg">
                <form wire:submit="merge" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Merge :reference', ['reference' => $ticket->reference]) }}</flux:heading>
                        <flux:text class="mt-2">{{ __('Use this when another ticket already tracks the same issue. This ticket closes, its SLA clock stops, and its reporter receives the other ticket\'s updates from now on. You can unmerge later.') }}</flux:text>
                    </div>

                    <flux:select variant="listbox" searchable wire:model="mergeTarget" :label="__('Original ticket')" :placeholder="__('Search :client tickets…', ['client' => $ticket->company->name])">
                        @forelse ($this->mergeCandidates as $candidate)
                            <flux:select.option :value="$candidate->id">{{ $candidate->reference }} — {{ \Illuminate\Support\Str::limit($candidate->title, 60) }}</flux:select.option>
                        @empty
                            <flux:select.option value="" disabled>{{ __('No other open tickets for this client.') }}</flux:select.option>
                        @endforelse
                    </flux:select>

                    <div class="flex justify-end gap-2">
                        <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                        <flux:button type="submit" variant="primary" icon="arrows-pointing-in">{{ __('Merge') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        @endunless
    @endcan

    @can('manage', $ticket)
        <flux:modal name="resolve-ticket" class="w-full max-w-lg" x-on:close="$wire.cancelResolve()">
            @php($closing = $status === \App\Enums\TicketStatus::Closed->value)
            <form wire:submit="resolve" class="space-y-6">
                <div>
                    <flux:heading size="lg">{{ $closing ? __('Close :reference', ['reference' => $ticket->reference]) : __('Resolve :reference', ['reference' => $ticket->reference]) }}</flux:heading>
                    <flux:text class="mt-2">{{ $closing ? __('Tell us how this ticket was dealt with before closing it.') : __('Tell us how the issue was fixed before marking it resolved.') }}</flux:text>
                </div>

                <flux:radio.group wire:model="resolutionType" variant="cards" :label="__('How was this ticket dealt with?')" class="flex-col">
                    @foreach (\App\Enums\ResolutionType::for($closing ? \App\Enums\TicketStatus::Closed : \App\Enums\TicketStatus::Resolved) as $type)
                        <flux:radio :value="$type->value" :label="$type->label()" :description="$type->description()" />
                    @endforeach
                </flux:radio.group>

                <div class="flex justify-end gap-2">
                    <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary" :icon="$closing ? 'lock-closed' : 'check-circle'">{{ $closing ? __('Close ticket') : __('Mark resolved') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endcan

    <flux:modal name="confirm-delete" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Delete entry?') }}</flux:heading>
                <flux:text class="mt-2" x-text="pendingDelete.message"></flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" icon="trash" x-on:click="runDelete()" x-bind:disabled="deleting">{{ __('Delete') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="unsaved-changes" class="w-full max-w-md">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Unsaved changes') }}</flux:heading>
                <flux:text class="mt-2">{{ __('You have text in the reply or work log that hasn\'t been saved. Save it before leaving?') }}</flux:text>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close><flux:button variant="ghost">{{ __('Keep editing') }}</flux:button></flux:modal.close>
                <flux:button variant="danger" x-on:click="leave()">{{ __('Discard') }}</flux:button>
                <flux:button variant="primary" x-on:click="saveAndLeave()" x-bind:disabled="saving">{{ __('Save & leave') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
