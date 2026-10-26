<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky collapsible class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <div class="px-3 py-2 text-sm text-zinc-400 font-medium leading-none truncate in-data-flux-sidebar-collapsed-desktop:hidden">{{ __('Support') }}</div>

                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="ticket" :href="route('tickets.index')" :current="request()->routeIs('tickets.index', 'tickets.show')" data-tour="nav-tickets" wire:navigate>
                    {{ auth()->user()->isClientAdmin() ? __('Company Tickets') : (auth()->user()->isClient() ? __('My Tickets') : __('Tickets')) }}
                </flux:sidebar.item>
                @can('create', \App\Models\Ticket::class)
                    <flux:sidebar.item icon="plus-circle" :href="route('tickets.create')" :current="request()->routeIs('tickets.create')" wire:navigate>
                        {{ __('Report a Bug') }}
                    </flux:sidebar.item>
                @endcan

                @can('admin')
                    <div class="mt-4 px-3 py-2 text-sm text-zinc-400 font-medium leading-none truncate in-data-flux-sidebar-collapsed-desktop:hidden">{{ __('Administration') }}</div>

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
                    <flux:sidebar.item icon="calendar-days" :href="route('admin.business-hours')" :current="request()->routeIs('admin.business-hours')" wire:navigate>
                        {{ __('Business Hours') }}
                    </flux:sidebar.item>
                    <flux:sidebar.item icon="chart-bar" :href="route('admin.reports')" :current="request()->routeIs('admin.reports')" wire:navigate>
                        {{ __('Reports') }}
                    </flux:sidebar.item>
                @endcan
            </flux:sidebar.nav>

            <flux:spacer />

            <flux:sidebar.nav>
                <flux:sidebar.item icon="book-open-text" :href="route('manual')" :current="request()->routeIs('manual')" data-tour="nav-manual" wire:navigate>
                    {{ __('User Manual') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" data-tour="nav-toggle" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                    data-tour="user-menu-mobile"
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

        @unless (request()->routeIs('notifications.edit'))
            <livewire:push-prompt />
        @endunless

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
