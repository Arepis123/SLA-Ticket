{{-- Numbered (default) or bulleted list used inside the user manual. --}}
@props(['bullets' => false])

<{{ $bullets ? 'ul' : 'ol' }} {{ $attributes->class([
    'my-2 space-y-1.5 ps-5 text-sm text-zinc-600 marker:text-zinc-400 dark:text-zinc-300',
    'list-disc' => $bullets,
    'list-decimal' => ! $bullets,
]) }}>
    {{ $slot }}
</{{ $bullets ? 'ul' : 'ol' }}>
