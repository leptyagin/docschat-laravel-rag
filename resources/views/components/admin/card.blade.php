@props(['flush' => false])

<section {{ $attributes->class(['rounded-lg border border-slate-200 bg-white', 'p-4' => ! $flush]) }}>
    {{ $slot }}
</section>
