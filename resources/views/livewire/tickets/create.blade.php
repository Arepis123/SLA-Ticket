<div class="mx-auto flex w-full max-w-3xl flex-col gap-6">
    <div>
        <flux:breadcrumbs class="mb-2">
            <flux:breadcrumbs.item :href="route('tickets.index')" wire:navigate>{{ __('Tickets') }}</flux:breadcrumbs.item>
            <flux:breadcrumbs.item>{{ __('New') }}</flux:breadcrumbs.item>
        </flux:breadcrumbs>
        <flux:heading size="xl" level="1">{{ __('Report a bug') }}</flux:heading>
        <flux:text class="mt-1">{{ __('Tell us what went wrong. The more detail you give, the faster we can fix it.') }}</flux:text>
    </div>

    @if ($this->projects->isEmpty())
        <flux:callout icon="exclamation-triangle" color="amber">
            <flux:callout.heading>{{ __('No systems available') }}</flux:callout.heading>
            <flux:callout.text>{{ __('There are no active systems linked to your account yet. Please contact the administrator.') }}</flux:callout.text>
        </flux:callout>
    @else
        <form wire:submit="save" class="flex flex-col gap-6">
            <flux:card class="space-y-6">
                <flux:select variant="listbox" searchable wire:model="project_id" :label="__('Which system has the problem?')" :placeholder="__('Choose a system…')" required>
                    @foreach ($this->projects as $project)
                        <flux:select.option :value="$project->id">{{ $project->name }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="title" :label="__('Summary')" :placeholder="__('e.g. Invoice PDF shows wrong total')" required maxlength="200" />

                <div class="grid gap-6 sm:grid-cols-2">
                    <flux:select variant="listbox" wire:model="category" :label="__('Type of issue')">
                        @foreach (\App\Enums\TicketCategory::cases() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select variant="listbox" wire:model="environment" :label="__('Environment')">
                        @foreach (\App\Enums\TicketEnvironment::cases() as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:radio.group wire:model.live="priority" :label="__('How severe is it?')" variant="cards" class="grid! grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach (\App\Enums\TicketPriority::cases() as $option)
                        <flux:radio :value="$option->value" :label="$option->label()" :description="$option->description()" />
                    @endforeach
                </flux:radio.group>

                <flux:text size="sm" class="-mt-3 flex items-center gap-1.5">
                    <flux:icon name="clock" variant="micro" />
                    {{ __('Target: first response within :response, fix within :resolution.', $this->targets) }}
                </flux:text>
            </flux:card>

            <flux:card class="space-y-6">
                <flux:textarea wire:model="description" :label="__('What happened?')" :description="__('Describe the problem and what you expected to happen instead. Include any error message.')" rows="5" required />

                <flux:textarea wire:model="steps_to_reproduce" :label="__('Steps to reproduce')" :badge="__('Optional')" :placeholder="implode(PHP_EOL, ['1. Go to …', '2. Click …', '3. See error'])" rows="4" />

                <div class="space-y-3">
                    <flux:file-upload wire:model="attachments" multiple :label="__('Screenshots or files')" :badge="__('Optional')">
                        <flux:file-upload.dropzone
                            :heading="__('Drop files here or click to browse')"
                            :text="__('Up to 5 files, max 10 MB each. Images, PDF, logs, Office documents, ZIP or video.')"
                            with-progress
                            inline
                        />
                    </flux:file-upload>

                    @foreach ($attachments as $index => $file)
                        <flux:file-item
                            wire:key="upload-{{ $index }}"
                            :heading="$file->getClientOriginalName()"
                            :image="$file->isPreviewable() ? $file->temporaryUrl() : null"
                            :size="$file->getSize()"
                            :invalid="$errors->has('attachments.'.$index)"
                        >
                            <x-slot name="actions">
                                <flux:file-item.remove wire:click="removeAttachment({{ $index }})" :aria-label="__('Remove file')" />
                            </x-slot>
                        </flux:file-item>
                    @endforeach

                    <flux:error name="attachments" />
                    <flux:error name="attachments.*" />
                </div>
            </flux:card>

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="ghost" :href="route('tickets.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
                <flux:button type="submit" variant="primary" icon="paper-airplane">{{ __('Submit ticket') }}</flux:button>
            </div>
        </form>
    @endif
</div>
