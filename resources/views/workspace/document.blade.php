<x-workspace.layout
    :title="$document->title.' · '.$bot->name"
    :bot="$bot"
    :library="$library"
    :chat="$chat"
    :current="$document"
    :seed="$document->id"
    :is-indexable="false"
>
    <header class="ws-hero ws-hero--document" data-parallax>
        <p class="ws-eyebrow ws-enter">{{ $bot->name }}</p>
        <h1 class="ws-hero__title ws-enter" style="--step: 1">{{ $document->title }}</h1>

        <dl class="ws-facts ws-enter" style="--step: 2">
            <div><dt>Format</dt><dd>{{ $document->typeLabel }}</dd></div>
            @if ($document->pageCount)
                <div><dt>Pages</dt><dd>{{ $document->pageCount }}</dd></div>
            @endif
            <div><dt>Reading time</dt><dd>{{ $document->readingMinutes }} min</dd></div>
            @if ($document->updatedAt)
                <div><dt>Updated</dt><dd><time datetime="{{ $document->updatedAt->toDateString() }}">{{ $document->updatedAt->format('M j, Y') }}</time></dd></div>
            @endif
        </dl>

        <div class="ws-actions ws-enter" style="--step: 3">
            <button type="button" class="ws-button ws-button--light" data-prefill="Give me a short summary of the {{ $document->title }}.">Ask about this document</button>
            <a class="ws-button" href="{{ $document->originalUrl }}" target="_blank" rel="noopener noreferrer">Open the original</a>
        </div>
    </header>

    <article class="ws-sheet ws-enter" style="--step: 4" aria-label="{{ $document->title }}">
        @foreach ($document->sections as $section)
            <section @class(['ws-section', 'ws-section--lead' => $section->isLead(), "ws-section--level-{$section->level}"])>
                @unless ($section->isLead())
                    <div class="ws-section__heading">
                        <{{ $section->headingTag() }} id="{{ $section->anchor }}">{{ $section->title }}</{{ $section->headingTag() }}>
                        <button
                            type="button"
                            class="ws-ask"
                            data-prefill="What does the {{ $document->title }} say about “{{ $section->title }}”?"
                            aria-label="Ask the assistant about {{ $section->title }}"
                        >Ask</button>
                    </div>
                @endunless

                @foreach ($section->passages as $passage)
                    <div id="{{ $passage->anchor() }}" class="passage">
                        @if ($passage->startsPage)
                            <p class="passage__page">Page {{ $passage->startsPage }}</p>
                        @endif

                        {{-- Sanitized by PassageRenderer: document text cannot carry markup here. --}}
                        <div class="ws-prose">{!! $passage->html !!}</div>
                    </div>
                @endforeach
            </section>
        @endforeach
    </article>
</x-workspace.layout>
