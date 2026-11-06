@props(['title', 'refreshSeconds' => null])

@php
    $links = [
        'Dashboard' => ['admin.dashboard', 'admin.dashboard', 'M3 12h7V3H3zM14 21h7v-9h-7zM14 8h7V3h-7zM3 21h7v-5H3z'],
        'Documents' => ['admin.documents.index', 'admin.documents.*', 'M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13h8M8 17h6'],
        'Conversations' => ['admin.conversations.index', 'admin.conversations.*', 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
        'Unanswered' => ['admin.unanswered', 'admin.unanswered', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9a2.5 2.5 0 1 1 3.6 2.3c-.7.4-1.1 1-1.1 1.7M12 17h.01'],
        'Settings' => ['admin.settings.edit', 'admin.settings.*', 'M4 7h10M18 7h2M4 17h2M10 17h10M16 9.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM8 19.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5z'],
    ];
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#050a18">
    @if ($refreshSeconds)
        <meta http-equiv="refresh" content="{{ $refreshSeconds }}">
    @endif
    <title>{{ $title }} · DocsChat admin</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen text-mist antialiased">
    <div class="lg:grid lg:min-h-screen lg:grid-cols-[15.5rem_minmax(0,1fr)]">
        <header class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-line bg-panel/90 px-4 py-3 backdrop-blur lg:h-screen lg:flex-col lg:flex-nowrap lg:items-stretch lg:justify-start lg:gap-8 lg:border-r lg:border-b-0 lg:p-5">
            <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center gap-2.5 text-lg font-semibold tracking-tight">
                <span class="admin-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                DocsChat
            </a>

            <form method="post" action="{{ route('admin.logout') }}" class="lg:order-last lg:mt-auto">
                @csrf
                <button class="cursor-pointer rounded-lg text-sm font-medium text-haze transition-colors hover:text-mist">Log out</button>
            </form>

            <nav aria-label="Admin" class="-mx-1 flex w-full gap-1 overflow-x-auto px-1 text-sm lg:flex-col lg:overflow-visible">
                @foreach ($links as $label => [$route, $pattern, $icon])
                    <a
                        href="{{ route($route) }}"
                        @class([
                            'flex items-center gap-2.5 rounded-xl px-3 py-2 font-medium whitespace-nowrap transition-colors',
                            'bg-brand/20 text-mist shadow-[inset_2px_0_0] shadow-brand-light' => request()->routeIs($pattern),
                            'text-haze hover:bg-raised hover:text-mist' => ! request()->routeIs($pattern),
                        ])
                        @if (request()->routeIs($pattern)) aria-current="page" @endif
                    >
                        <svg class="size-4.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon }}" /></svg>
                        {{ $label }}
                    </a>
                @endforeach
            </nav>
        </header>

        <div class="min-w-0">
            @if (auth()->user()?->isReadOnly())
                <p class="border-b border-amber-400/20 bg-amber-400/10 px-4 py-2 text-center text-sm text-amber-200">
                    You are viewing a read-only demo. Changes are switched off.
                </p>
            @endif

            <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6 lg:px-8 lg:py-10">
                <h1 class="text-3xl font-light tracking-tight lg:text-4xl">{{ $title }}</h1>

                @if (session('status'))
                    <p role="status" class="rounded-xl border border-emerald-400/25 bg-emerald-400/10 px-4 py-2.5 text-sm text-emerald-200">{{ session('status') }}</p>
                @endif

                @if (session('notice'))
                    <p role="status" class="rounded-xl border border-amber-400/25 bg-amber-400/10 px-4 py-2.5 text-sm text-amber-200">{{ session('notice') }}</p>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>
</body>
</html>
