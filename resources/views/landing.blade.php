<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DocsChat: an AI chatbot that answers only from your documents</title>
    <meta name="description" content="A website chatbot that answers visitors from a company's own documents, shows its sources, and says so when it does not know.">
    @vite('resources/css/app.css')
</head>
<body class="bg-white text-slate-900 antialiased">
    <p class="bg-slate-900 px-4 py-2 text-center text-sm text-white">
        Demo project. Seabreeze Hotels is a fictional company. Questions asked here appear in the public read-only admin panel, so please don’t share personal details.
    </p>

    <main class="mx-auto flex max-w-3xl flex-col gap-14 px-4 py-12 lg:mr-[28rem] lg:ml-auto xl:mr-[32rem]">
        <section class="flex flex-col gap-4">
            <p class="font-semibold text-brand-dark">DocsChat</p>
            <h1 class="text-4xl font-semibold tracking-tight sm:text-5xl">A chatbot that answers only from your documents.</h1>
            <p class="text-lg text-slate-600">
                It reads your PDFs and web pages, cites the source of every answer, and says “I don’t know” instead of making things up. It joins any website with one script tag.
            </p>
        </section>

        @if ($bot && $bot->suggested_questions !== [])
            <section class="flex flex-col gap-3">
                <h2 class="text-xl font-semibold">Try it on the Seabreeze Hotels documents</h2>
                <p class="text-slate-600">Open the chat and ask one of these. The last one is not in the documents, so watch it refuse.</p>
                <ul class="flex flex-col gap-2">
                    @foreach ($bot->suggested_questions as $question)
                        <li class="rounded-md border border-slate-200 px-3 py-2">{{ $question }}</li>
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="flex flex-col gap-4">
            <h2 class="text-xl font-semibold">How it works</h2>
            <ol class="grid gap-3 sm:grid-cols-2">
                @foreach ([
                    ['Index', 'Documents are split by heading into passages, and each passage is stored with a vector of its meaning.'],
                    ['Search', 'A question is matched by meaning and by exact words, and the two rankings are merged.'],
                    ['Check', 'If nothing relevant is found, the bot refuses without calling the language model at all.'],
                    ['Answer', 'The model answers from those passages only and cites them. An answer without a citation is flagged.'],
                ] as $index => [$step, $description])
                    <li class="flex flex-col gap-1 rounded-lg bg-slate-50 p-4">
                        <span class="text-sm font-semibold text-brand-dark">{{ $index + 1 }}. {{ $step }}</span>
                        <span class="text-sm text-slate-600">{{ $description }}</span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="flex flex-wrap gap-3">
            @if ($links['repository_url'])
                <a href="{{ $links['repository_url'] }}" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700">Source code</a>
            @endif
            <a href="{{ route('admin.login') }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Read-only admin panel</a>
            @if ($links['author_url'])
                <a href="{{ $links['author_url'] }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">{{ $links['author_label'] }}</a>
            @endif
        </section>
    </main>

    @if ($bot)
        <script src="{{ asset('widget.js') }}" data-bot="{{ $bot->public_key }}" data-open="true" async></script>
    @endif
</body>
</html>
