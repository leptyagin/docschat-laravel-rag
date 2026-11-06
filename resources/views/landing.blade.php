<x-workspace.layout
    title="DocsChat: an AI chatbot that answers only from your documents"
    description="A website chatbot that answers visitors from a company's own documents, shows its sources, and says so when it does not know."
    :bot="$bot"
    :library="$library"
    :chat="$chat"
>
    <p class="ws-notice ws-enter">Demo project. Seabreeze Hotels is a fictional company. {{ $visitorNotice }}</p>

    <header class="ws-hero" data-parallax>
        <p class="ws-eyebrow ws-enter" style="--step: 1">DocsChat</p>
        <h1 class="ws-hero__title ws-hero__title--display ws-enter" style="--step: 2">A chatbot that answers only from your documents.</h1>
        <p class="ws-hero__lede ws-enter" style="--step: 3">
            It reads your PDFs and web pages, cites the source of every answer, and says “I don’t know” instead of making things up. It joins any website with one script tag.
        </p>
    </header>

    @if ($library->isNotEmpty())
        <section class="ws-block ws-enter" style="--step: 4" aria-labelledby="library-title">
            <h2 id="library-title" class="ws-block__title">Open a document</h2>
            <ul class="ws-library">
                @foreach ($library as $item)
                    <li>
                        <a class="ws-card" href="{{ route('reader.show', [$bot->public_key, $item->id]) }}" data-soft>
                            <span class="ws-card__type">{{ $item->source_type->label() }}</span>
                            <span class="ws-card__title">{{ $item->title }}</span>
                            <span class="ws-card__meta">{{ trans_choice('{1} :count passage|[2,*] :count passages', $item->chunk_count) }} · {{ $item->updated_at?->format('M j, Y') }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($bot && $bot->suggested_questions !== [])
        <section class="ws-block" aria-labelledby="try-title">
            <h2 id="try-title" class="ws-block__title">Try it on the Seabreeze Hotels documents</h2>
            <p class="ws-block__lede">Ask one of these. The last one is not in the documents, so watch it refuse.</p>
            <ul class="ws-questions">
                @foreach ($bot->suggested_questions as $question)
                    <li><button type="button" class="ws-question" data-ask="{{ $question }}">{{ $question }}</button></li>
                @endforeach
            </ul>
        </section>
    @endif

    <section class="ws-block" aria-labelledby="how-title">
        <h2 id="how-title" class="ws-block__title">How it works</h2>
        <ol class="ws-steps">
            @foreach ([
                ['Index', 'Documents are split by heading into passages, and each passage is stored with a vector of its meaning.'],
                ['Search', 'A question is matched by meaning and by exact words, and the two rankings are merged.'],
                ['Check', 'If nothing relevant is found, the bot refuses without calling the language model at all.'],
                ['Answer', 'The model answers from those passages only and cites them. An answer without a citation is flagged.'],
            ] as $index => [$step, $description])
                <li class="ws-step">
                    <span class="ws-step__number">{{ $index + 1 }}</span>
                    <span class="ws-step__title">{{ $step }}</span>
                    <span class="ws-step__text">{{ $description }}</span>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="ws-actions ws-actions--end">
        @if ($links['repository_url'])
            <a href="{{ $links['repository_url'] }}" class="ws-button ws-button--light">Source code</a>
        @endif
        <a href="{{ route('admin.login') }}" class="ws-button">Read-only admin panel</a>
        @if ($links['author_url'])
            <a href="{{ $links['author_url'] }}" class="ws-button">{{ $links['author_label'] }}</a>
        @endif
    </section>
</x-workspace.layout>
