<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Clients') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Companies you build and support systems for.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" wire:click="create">{{ __('Add client') }}</flux:button>
    </div>

    <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search clients…')" clearable class="max-w-sm" />

    <flux:card class="p-0! overflow-hidden">
        <flux:table class="[&_th:first-child]:ps-4 [&_td:first-child]:ps-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Contact') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Systems') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Users') }}</flux:table.column>
                <flux:table.column align="center">{{ __('Open / total tickets') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->companies as $company)
                    <flux:table.row :key="$company->id">
                        <flux:table.cell variant="strong">{{ $company->name }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:text variant="strong">{{ $company->contact_person ?: '—' }}</flux:text>
                            <flux:text size="sm">{{ $company->email }}{{ $company->email && $company->phone ? ' · ' : '' }}{{ $company->phone }}</flux:text>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:link :href="route('admin.projects', ['company' => $company->id])" variant="ghost" wire:navigate>{{ $company->projects_count }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:link :href="route('admin.users', ['company' => $company->id])" variant="ghost" wire:navigate>{{ $company->users_count }}</flux:link>
                        </flux:table.cell>
                        <flux:table.cell align="center" class="tabular-nums">{{ $company->open_tickets_count }} / {{ $company->tickets_count }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$company->is_active ? 'green' : 'zinc'">{{ $company->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="center">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $company->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="squares-2x2" :href="route('admin.projects', ['company' => $company->id])" wire:navigate>{{ __('Systems') }}</flux:menu.item>
                                    <flux:menu.separator />
                                    <flux:menu.item icon="trash" variant="danger" wire:click="delete({{ $company->id }})" wire:confirm="{{ __('Delete this client? This cannot be undone.') }}">{{ __('Delete') }}</flux:menu.item>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="7" class="py-10 text-center"><flux:text>{{ __('No clients yet.') }}</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>

        <flux:pagination :paginator="$this->companies" class="px-4 pb-3" />
    </flux:card>

    <flux:modal name="company-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit client') : __('New client') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Company name')" required />
            <flux:input wire:model="contact_person" :label="__('Contact person')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="email" type="email" :label="__('Email')" />
                <flux:input wire:model="phone" :label="__('Phone')" />
            </div>
            <flux:switch wire:model="is_active" :label="__('Active')" :description="__('Inactive clients cannot submit new tickets.')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
