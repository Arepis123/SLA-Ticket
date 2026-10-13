@props(['status'])

<flux:badge :color="$status->color()" size="sm" {{ $attributes }}>{{ $status->label() }}</flux:badge>
