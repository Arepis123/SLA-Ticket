<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        @php
            $background ??= null;
            $backgroundDark ??= $background;
        @endphp

        {{-- 60/40 split: five columns, three for the info panel and two for the form. --}}
        <div class="grid min-h-svh lg:grid-cols-5">
            <div class="relative hidden overflow-hidden p-8 lg:col-span-3 lg:flex lg:flex-col xl:p-14">
                @if ($background)
    {{-- Artwork and scrim both follow the theme; only one set is ever painted. --}}
                    <div class="absolute inset-0 bg-cover bg-center dark:hidden" style="background-image: url('{{ $background }}')"></div>
                    <div class="absolute inset-0 hidden bg-cover bg-center dark:block" style="background-image: url('{{ $backgroundDark }}')"></div>

                    <div class="absolute inset-0 dark:hidden">
                        <div class="absolute inset-0 bg-white/10"></div>
                        <div class="absolute inset-0 bg-linear-to-r from-white/80 via-white/30 to-transparent"></div>
                        <div class="absolute inset-0 bg-linear-to-t from-white/30 to-transparent"></div>
                    </div>

                    <div class="absolute inset-0 hidden dark:block">
                        <div class="absolute inset-0 bg-neutral-950/10"></div>
                        <div class="absolute inset-0 bg-linear-to-r from-neutral-950/80 via-neutral-950/30 to-transparent"></div>
                        <div class="absolute inset-0 bg-linear-to-t from-neutral-950/30 to-transparent"></div>
                    </div>
                @else
                    <div class="absolute inset-0 bg-neutral-100 dark:bg-neutral-900"></div>
                @endif

                <div class="relative z-10 flex min-h-0 flex-1 flex-col">
                    {{ $info ?? '' }}
                </div>
            </div>

            <div class="flex flex-col justify-center p-6 md:p-10 lg:col-span-2">
                <div class="mx-auto flex w-full max-w-sm flex-col gap-6">
                    <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium lg:hidden" wire:navigate>
                        <span class="flex h-9 w-9 items-center justify-center rounded-md">
                            <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                        </span>
                        <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                    </a>

                    <div class="flex flex-col gap-6">
                        {{ $slot }}
                    </div>
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
