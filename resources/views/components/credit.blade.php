<p {{ $attributes->class('credit') }}>
    <span><span class="credit__prefix">Built by </span>{{ $name }}</span>

    @if (filled($githubUrl))
        <span class="credit__separator" aria-hidden="true">·</span>
        <a class="credit__link" href="{{ $githubUrl }}" target="_blank" rel="noopener noreferrer">GitHub</a>
    @endif

    @if (filled($upworkUrl))
        <span class="credit__separator" aria-hidden="true">·</span>
        <a class="credit__link credit__link--hire" href="{{ $upworkUrl }}" target="_blank" rel="noopener noreferrer">Hire me on Upwork</a>
    @endif
</p>
