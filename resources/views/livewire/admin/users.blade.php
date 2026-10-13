<div class="flex flex-col gap-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">{{ __('Users') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Client accounts submit tickets. Internal accounts work on them. Only admins can manage settings.') }}</flux:text>
        </div>
        <flux:button variant="primary" icon="user-plus" wire:click="create">{{ __('Add user') }}</flux:button>
    </div>

    <div class="flex flex-wrap gap-3">
        <flux:input wire:model.live.debounce.400ms="search" icon="magnifying-glass" :placeholder="__('Search name or email…')" clearable class="max-w-sm" />
        <flux:select wire:model.live="role" class="max-w-44">
            <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
            @foreach (\App\Enums\UserRole::cases() as $option)
                <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="company" class="max-w-xs">
            <flux:select.option value="">{{ __('All clients') }}</flux:select.option>
            @foreach ($this->companies as $option)
                <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <flux:card class="p-0! overflow-hidden">
        <flux:table :paginate="$this->users" class="[&_th:first-child]:ps-4 [&_td:first-child]:ps-4">
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Client') }}</flux:table.column>
                <flux:table.column>{{ __('Status') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($this->users as $user)
                    <flux:table.row :key="$user->id">
                        <flux:table.cell>
                            <div class="flex items-center gap-3">
                                <flux:avatar size="sm" :name="$user->name" :initials="$user->initials()" />
                                <div>
                                    <div class="font-medium text-zinc-800 dark:text-white">{{ $user->name }}</div>
                                    <flux:text size="sm">{{ $user->email }}</flux:text>
                                </div>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell><flux:badge size="sm" :color="$user->role->color()">{{ $user->role->label() }}</flux:badge></flux:table.cell>
                        <flux:table.cell>{{ $user->company?->name ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm" :color="$user->is_active ? 'green' : 'zinc'">{{ $user->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell align="end">
                            <flux:dropdown position="bottom" align="end">
                                <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                                <flux:menu>
                                    <flux:menu.item icon="pencil-square" wire:click="edit({{ $user->id }})">{{ __('Edit') }}</flux:menu.item>
                                    <flux:menu.item icon="envelope" wire:click="sendPasswordReset({{ $user->id }})">{{ __('Send password reset link') }}</flux:menu.item>
                                    @if ($user->id !== auth()->id())
                                        <flux:menu.separator />
                                        <flux:menu.item :icon="$user->is_active ? 'no-symbol' : 'check-circle'" :variant="$user->is_active ? 'danger' : null" wire:click="toggleActive({{ $user->id }})">
                                            {{ $user->is_active ? __('Deactivate') : __('Activate') }}
                                        </flux:menu.item>
                                    @endif
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="5" class="py-10 text-center"><flux:text>{{ __('No users found.') }}</flux:text></flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </flux:card>

    <flux:modal name="user-form" class="w-full max-w-lg">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __('Edit user') : __('New user') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" required />

            @if ($editingId !== auth()->id())
                <flux:radio.group wire:model.live="userRole" :label="__('Role')" variant="segmented">
                    @foreach (\App\Enums\UserRole::cases() as $option)
                        <flux:radio :value="$option->value" :label="$option->label()" />
                    @endforeach
                </flux:radio.group>
            @endif

            @if ($userRole === 'client')
                <flux:select wire:model="company_id" :label="__('Client company')" :placeholder="__('Choose client…')" required>
                    @foreach ($this->companies as $option)
                        <flux:select.option :value="$option->id">{{ $option->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            @unless ($editingId)
                <flux:checkbox wire:model.live="sendResetLink" :label="__('Email the user a link to set their own password')" />
            @endunless

            @if ($editingId || ! $sendResetLink)
                <flux:input wire:model="password" type="password" viewable
                    :label="$editingId ? __('New password') : __('Password')"
                    :description="$editingId ? __('Leave blank to keep the current password.') : null"
                    :required="! $editingId" autocomplete="new-password" />
            @endif

            @if ($editingId !== auth()->id())
                <flux:switch wire:model="is_active" :label="__('Active')" :description="__('Inactive users cannot sign in.')" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
