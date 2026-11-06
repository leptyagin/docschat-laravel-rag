@props([
    'title',
    'description' => null,
    'bot' => null,
    'library',
    'chat' => null,
    'current' => null,
    'seed' => 0,
    'isIndexable' => true,
])

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="theme-color" content="#0593c6">
    <title>{{ $title }}</title>
    @if ($description)
        <meta name="description" content="{{ $description }}">
    @endif
    @unless ($isIndexable)
        <meta name="robots" content="noindex">
    @endunless
    <script>document.documentElement.classList.add('js')</script>
    @vite(['resources/css/workspace.css', 'resources/workspace/main.js'])
</head>
<body class="ws" data-view="document" data-assistant="{{ $chat ? 'open' : 'none' }}">
    <div class="ws-backdrop" aria-hidden="true"></div>
    <canvas id="ws-field" class="ws-field" aria-hidden="true"></canvas>

    <a class="ws-skip" href="#ws-stage">Skip to content</a>

    <header class="ws-topbar">
        <a class="ws-brand" href="{{ route('home') }}" data-soft>
            <span class="ws-brand__mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            DocsChat
        </a>

        @if ($bot)
            <span class="ws-topbar__context">{{ $bot->name }}</span>
        @endif

        <div class="ws-topbar__actions">
            @if ($library->isNotEmpty())
                <button type="button" class="ws-chip ws-chip--rail" data-rail-toggle aria-controls="ws-rail" aria-expanded="false">Library</button>
            @endif

            @if ($chat)
                <button type="button" class="ws-chip ws-chip--assistant" data-assistant-toggle aria-controls="ws-assistant" aria-expanded="true">
                    <span class="ws-pulse" aria-hidden="true"></span>
                    AI Assistant
                </button>
            @endif
        </div>

        <span class="ws-topbar__progress" aria-hidden="true"></span>
    </header>

    <div class="ws-shell">
        @if ($library->isNotEmpty())
            <aside id="ws-rail" class="ws-rail" aria-label="Library" data-open="false">
                <x-workspace.rail :bot="$bot" :library="$library" :current="$current" />
            </aside>
            <button type="button" class="ws-scrim" data-rail-close aria-label="Close the library" tabindex="-1"></button>
        @endif

        <main id="ws-stage" class="ws-stage" tabindex="-1" data-field-seed="{{ $seed }}">
            {{ $slot }}
        </main>

        @if ($chat)
            <aside id="ws-assistant" class="ws-assistant" aria-label="AI Assistant" data-bot="{{ $bot->public_key }}" data-open="true">
                <div id="docschat-app" class="ws-assistant__mount"></div>
            </aside>
        @endif
    </div>

    @if ($chat)
        <nav class="ws-tabbar" aria-label="Switch view">
            <button type="button" data-view-button="document" aria-pressed="true">Read</button>
            <button type="button" data-view-button="assistant" aria-pressed="false">Ask AI</button>
        </nav>

        <script id="docschat-config" type="application/json">@json($chat, JSON_HEX_TAG | JSON_HEX_AMP)</script>
    @endif
</body>
</html>
