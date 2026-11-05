@props(['label', 'value'])

<x-admin.card>
    <p class="text-sm text-slate-500">{{ $label }}</p>
    <p class="mt-1 text-2xl font-semibold">{{ $value }}</p>
</x-admin.card>
