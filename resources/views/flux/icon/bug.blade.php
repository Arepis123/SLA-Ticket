@blaze(fold: true)

{{-- Credit: Lucide (https://lucide.dev) --}}

@props([
    'variant' => 'outline',
])

@php
$classes = Flux::classes('shrink-0')
    ->add(match($variant) {
        'outline' => '[:where(&)]:size-6',
        'solid' => '[:where(&)]:size-6',
        'mini' => '[:where(&)]:size-5',
        'micro' => '[:where(&)]:size-4',
    });

$strokeWidth = match ($variant) {
    'outline', 'solid' => 2,
    'mini' => 2.25,
    'micro' => 2.5,
};
@endphp

<svg
    {{ $attributes->class($classes) }}
    data-flux-icon
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $strokeWidth }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    data-slot="icon"
>
  @if ($variant !== 'solid')
  <path d="M12 20v-9" />
  @endif
  <path d="M14 7a4 4 0 0 1 4 4v3a6 6 0 0 1-12 0v-3a4 4 0 0 1 4-4z" @if ($variant === 'solid') fill="currentColor" @endif />
  <path d="M14.12 3.88 16 2" />
  <path d="M21 21a4 4 0 0 0-3.81-4" />
  <path d="M21 5a4 4 0 0 1-3.55 3.97" />
  <path d="M22 13h-4" />
  <path d="M3 21a4 4 0 0 1 3.81-4" />
  <path d="M3 5a4 4 0 0 0 3.55 3.97" />
  <path d="M6 13H2" />
  <path d="m8 2 1.88 1.88" />
  <path d="M9 7.13V6a3 3 0 1 1 6 0v1.13" @if ($variant === 'solid') fill="currentColor" @endif />
</svg>
