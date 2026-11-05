<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Log in · DocsChat admin</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen items-center justify-center bg-slate-50 p-4 text-slate-900 antialiased">
    <main class="flex w-full max-w-sm flex-col gap-4">
        <h1 class="text-center text-2xl font-semibold text-brand-dark">DocsChat admin</h1>

        <x-admin.card>
            <form method="post" action="{{ route('admin.login') }}" class="flex flex-col gap-4">
                @csrf

                <x-admin.field name="email" label="Email">
                    <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}" class="rounded-md border border-slate-300 px-3 py-2">
                </x-admin.field>

                <x-admin.field name="password" label="Password">
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="rounded-md border border-slate-300 px-3 py-2">
                </x-admin.field>

                <x-admin.button>Log in</x-admin.button>
            </form>
        </x-admin.card>

        @if (filled($demoAccount['email']) && filled($demoAccount['password']))
            <p class="text-center text-sm text-slate-600">
                Read-only demo: <strong>{{ $demoAccount['email'] }}</strong> / <strong>{{ $demoAccount['password'] }}</strong>
            </p>
        @endif
    </main>
</body>
</html>
