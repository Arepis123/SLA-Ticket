<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-linear-to-b dark:from-neutral-950 dark:to-neutral-900">
        @php($background ??= null)

        <div
            @class(['relative flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10', 'bg-background' => ! $background, 'bg-cover bg-center' => $background])
            @style(["background-image: url('{$background}')" => $background])
        >
            @if ($background)
                <div class="absolute inset-0 bg-black/50 backdrop-blur-[6px]"></div>
            @endif

            <div @class(['relative flex w-full flex-col gap-2', 'max-w-sm' => ! $background, 'max-w-md rounded-2xl border border-white/10 bg-zinc-900/80 p-8 shadow-2xl backdrop-blur-md' => $background])>
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2 font-medium" wire:navigate>
                    <span class="flex h-9 w-9 mb-1 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 fill-current text-black dark:text-white" />
                    </span>
                    <span class="sr-only">{{ config('app.name', 'Laravel') }}</span>
                </a>
                <div class="flex flex-col gap-6">
                    {{ $slot }}
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
