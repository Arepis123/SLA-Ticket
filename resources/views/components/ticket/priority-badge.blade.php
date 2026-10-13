@props(['priority'])

<flux:badge :color="$priority->color()" size="sm" icon="flag" {{ $attributes }}>{{ $priority->label() }}</flux:badge>
