@props(['variant' => 'primary'])

@php
    $isReadOnly = auth()->user()?->isReadOnly() === true;
@endphp

<button
    {{ $attributes->class([
        'inline-flex cursor-pointer items-center justify-center rounded-xl px-4 py-2 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-45',
        'bg-linear-to-br from-brand to-[#0a62ad] text-white shadow-lg shadow-brand/20 enabled:hover:brightness-115' => $variant === 'primary',
        'border border-line bg-raised text-mist enabled:hover:border-brand' => $variant === 'secondary',
        'border border-red-500/35 bg-red-500/10 text-red-300 enabled:hover:bg-red-500/20' => $variant === 'danger',
    ]) }}
    @disabled($isReadOnly)
    @if ($isReadOnly) title="Switched off in the read-only demo" @endif
>{{ $slot }}</button>
