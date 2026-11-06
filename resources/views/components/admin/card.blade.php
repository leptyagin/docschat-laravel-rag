@props(['flush' => false])

<section {{ $attributes->class(['rounded-2xl border border-line bg-panel/85 shadow-xl shadow-black/30 backdrop-blur', 'p-5' => ! $flush, 'overflow-hidden' => $flush]) }}>
    {{ $slot }}
</section>
