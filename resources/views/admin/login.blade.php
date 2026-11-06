<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#050a18">
    <title>Log in · DocsChat admin</title>
    @vite('resources/css/app.css')
</head>
<body class="flex min-h-screen items-center justify-center p-4 text-mist antialiased">
    <main class="flex w-full max-w-sm flex-col gap-4">
        <h1 class="flex items-center justify-center gap-3 text-3xl font-light tracking-tight">
            <span class="admin-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            DocsChat admin
        </h1>

        <x-admin.card>
            <form method="post" action="{{ route('admin.login') }}" class="flex flex-col gap-4">
                @csrf

                <x-admin.field name="email" label="Email">
                    <input id="email" name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}" class="admin-input">
                </x-admin.field>

                <x-admin.field name="password" label="Password">
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="admin-input">
                </x-admin.field>

                <x-admin.button>Log in</x-admin.button>
            </form>
        </x-admin.card>

        @if (filled($demoAccount['email']) && filled($demoAccount['password']))
            <p class="text-center text-sm text-haze">
                Read-only demo: <strong>{{ $demoAccount['email'] }}</strong> / <strong>{{ $demoAccount['password'] }}</strong>
            </p>
        @endif

        <a href="{{ route('home') }}" class="self-center rounded-lg text-sm font-medium text-haze transition-colors hover:text-mist">Back to the landing page</a>
    </main>
</body>
</html>
