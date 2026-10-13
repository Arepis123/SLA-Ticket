<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <flux:sidebar.group :heading="__('Support')" class="grid">
                    <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="ticket" :href="route('tickets.index')" :current="request()->routeIs('tickets.index', 'tickets.show')" wire:navigate>
                        {{ auth()->user()->isClient() ? __('My Tickets') : __('Tickets') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="plus-circle" :href="route('tickets.create')" :current="request()->routeIs('tickets.create')" wire:navigate>
                        {{ __('Report a Bug') }}
                    </flux:sidebar.item>
                </flux:sidebar.group>

                @can('admin')
                    <flux:sidebar.group :heading="__('Administration')" class="grid">
                        <flux:sidebar.item icon="building-office-2" :href="route('admin.companies')" :current="request()->routeIs('admin.companies')" wire:navigate>
                            {{ __('Clients') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="squares-2x2" :href="route('admin.projects')" :current="request()->routeIs('admin.projects')" wire:navigate>
                            {{ __('Projects') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="users" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>
                            {{ __('Users') }}
                        </flux:sidebar.item>
                        <flux:sidebar.item icon="clock" :href="route('admin.sla-policies')" :current="request()->routeIs('admin.sla-policies')" wire:navigate>
                            {{ __('SLA Policies') }}
                        </flux:sidebar.item>
                    </flux:sidebar.group>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="auth()->user()->name"
                                    :initials="auth()->user()->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                    <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        {{-- Lightweight notice: Livewire components dispatch('notify', message: '...') or flash session('notify'). --}}
        <div
            x-data="{ show: false, message: '', variant: 'success', timer: null,
                open(detail) { this.message = detail.message; this.variant = detail.variant ?? 'success'; this.show = true; clearTimeout(this.timer); this.timer = setTimeout(() => this.show = false, 4000) } }"
            x-init="@if (session('notify')) $nextTick(() => open({ message: @js(session('notify')) })) @endif"
            x-on:notify.window="open($event.detail)"
            x-show="show"
            x-transition.opacity
            x-cloak
            class="fixed bottom-4 end-4 z-50 w-80"
        >
            <div class="flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                <flux:icon x-show="variant === 'success'" name="check-circle" class="size-5 shrink-0 text-green-500" />
                <flux:icon x-show="variant === 'danger'" name="exclamation-circle" class="size-5 shrink-0 text-red-500" />
                <flux:text class="flex-1 text-zinc-800! dark:text-white!" x-text="message"></flux:text>
                <button type="button" x-on:click="show = false" class="text-zinc-400 hover:text-zinc-600">
                    <flux:icon name="x-mark" variant="micro" />
                </button>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
