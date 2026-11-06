@props(['label', 'value'])

<x-admin.card {{ $attributes->class('relative overflow-hidden') }}>
    <span class="absolute inset-x-0 top-0 h-px bg-linear-to-r from-brand via-brand-light/40 to-transparent" aria-hidden="true"></span>
    <p class="text-sm text-haze">{{ $label }}</p>
    <p class="mt-2 text-3xl font-light tracking-tight text-mist tabular-nums">{{ $value }}</p>
</x-admin.card>
