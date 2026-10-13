<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Projects') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Systems you developed. Clients can only report bugs against their own systems.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add system') }}</flux:button>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search systems…')" clearable class="max-w-sm" />
        <flux:select variant="listbox" searchable wire:model.live="company" class="max-w-xs">
            <flux:select.option value="">{{ __('All clients') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:card class="p-0! overflow-hidden">
        <flux:table class="[&_th:first-child]:ps-4 [&_td:first-child]:ps-4">
            <flux:table.columns>
                <flux:table.column>{{ __('System') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Open / total tickets') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->projects as $project)
                    <flux:table.row :key="$project->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                <flux:heading>{{ $project->name }}</flux:heading>
                                @if ($project->code)
                                    <flux:badge size="sm">{{ $project->code }}</flux:badge>
                                @endif
                            </div>
                            @if ($project->url)
                                <flux:link :href="$project->url" variant="subtle" external class="text-xs">{{ $project->url }}</flux:link>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>{{ $project->company->name }}</flux:table.cell>
                        <flux:table.cell align="center" class="tabular-nums">
                            <flux:link :href="route('tickets.index', ['project' => $project->id])" variant="ghost" wire:navigate>{{ $project->open_tickets_count }} / {{ $project->tickets_count }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$project->is_active ? 'green' : 'zinc'">{{ $project->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $project->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $project->id }})" wire:confirm="{{ __('Delete this system?') }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center"><flux:text>{{ __('No systems yet.') }}</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:pagination :paginator="$this->projects" class="px-4 pb-3" />
    </flux:card>

    <flux:modal name="project-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit system') : __('New system') }}</flux:heading>

            <flux:select variant="listbox" searchable wire:model="company_id" :label="__('Client')" :placeholder="__('Choose client…')" required>
                @foreach ($this->companies as $option)
                    <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="name" :label="__('System name')" required class="sm:col-span-2" />
                <flux:input wire:model="code" :label="__('Code')" :placeholder="__('e.g. HRP')" />
            </div>
            <flux:input wire:model="url" type="url" :label="__('URL')" placeholder="https://" />
            <flux:textarea wire:model="description" :label="__('Description')" rows="3" />
            <flux:switch wire:model="is_active" :label="__('Active')" :description="__('Inactive systems are hidden from the bug report form.')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
