@props(['title', 'refreshSeconds' => null])

@php
    $links = [
        'Dashboard' => ['admin.dashboard', 'admin.dashboard'],
        'Documents' => ['admin.documents.index', 'admin.documents.*'],
        'Conversations' => ['admin.conversations.index', 'admin.conversations.*'],
        'Unanswered' => ['admin.unanswered', 'admin.unanswered'],
        'Settings' => ['admin.settings.edit', 'admin.settings.*'],
    ];
@endphp

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    @if ($refreshSeconds)
        <meta http-equiv="refresh" content="{{ $refreshSeconds }}">
    @endif
    <title>{{ $title }} · DocsChat admin</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-x-6 gap-y-2 px-4 py-3">
            <a href="{{ route('admin.dashboard') }}" class="font-semibold text-brand-dark">DocsChat</a>

            <form method="post" action="{{ route('admin.logout') }}" class="md:order-last">
                @csrf
                <button class="text-sm text-slate-600 hover:text-slate-900">Log out</button>
            </form>

            <nav aria-label="Admin" class="flex w-full flex-wrap gap-1 text-sm md:w-auto md:flex-1">
                @foreach ($links as $label => [$route, $pattern])
                    <a
                        href="{{ route($route) }}"
                        @class([
                            'rounded-md px-3 py-1.5 whitespace-nowrap',
                            'bg-brand/10 font-medium text-brand-dark' => request()->routeIs($pattern),
                            'text-slate-600 hover:bg-slate-100' => ! request()->routeIs($pattern),
                        ])
                        @if (request()->routeIs($pattern)) aria-current="page" @endif
                    >{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    @if (auth()->user()?->isReadOnly())
        <p class="bg-amber-100 px-4 py-2 text-center text-sm text-amber-900">
            You are viewing a read-only demo. Changes are switched off.
        </p>
    @endif

    <main class="mx-auto flex max-w-6xl flex-col gap-6 px-4 py-6">
        <h1 class="text-2xl font-semibold">{{ $title }}</h1>

        @if (session('status'))
            <p role="status" class="rounded-md bg-emerald-50 px-4 py-2 text-sm text-emerald-900">{{ session('status') }}</p>
        @endif

        @if (session('notice'))
            <p role="status" class="rounded-md bg-amber-50 px-4 py-2 text-sm text-amber-900">{{ session('notice') }}</p>
        @endif

        {{ $slot }}
    </main>
</body>
</html>
