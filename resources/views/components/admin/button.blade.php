@props(['variant' => 'primary'])

@php
    $isReadOnly = auth()->user()?->isReadOnly() === true;
@endphp

<button
    {{ $attributes->class([
        'inline-flex items-center justify-center rounded-md px-3 py-2 text-sm font-medium focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50',
        'bg-brand text-white hover:bg-brand-dark' => $variant === 'primary',
        'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50' => $variant === 'secondary',
        'border border-red-300 bg-white text-red-700 hover:bg-red-50' => $variant === 'danger',
    ]) }}
    @disabled($isReadOnly)
    @if ($isReadOnly) title="Switched off in the read-only demo" @endif
>{{ $slot }}</button>
