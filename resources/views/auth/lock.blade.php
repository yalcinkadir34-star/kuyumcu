<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kilitli · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-900 font-sans text-stone-800 antialiased">
    <div class="flex min-h-screen items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 shadow-xl">
            <div class="flex flex-col items-center text-center">
                <div class="flex size-14 items-center justify-center rounded-full bg-gold-100 text-gold-800">
                    <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                </div>
                <h2 class="mt-4 text-xl font-semibold text-stone-900">Ekran kilitli</h2>
                <p class="mt-1 text-sm text-stone-500">{{ $user->name }} · devam etmek için şifrenizi girin.</p>
            </div>

            @if ($errors->any())
                <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('lock.unlock') }}" class="mt-6 space-y-4">
                @csrf
                <input type="text" name="username" value="{{ $user->username }}" autocomplete="username" class="hidden" tabindex="-1" aria-hidden="true">
                <div>
                    <label for="password" class="mb-1.5 block text-sm font-medium text-stone-700">Şifre</label>
                    <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                           class="block w-full rounded-lg border border-stone-300 bg-white px-3.5 py-2.5 text-stone-900 shadow-sm outline-none transition focus:border-gold-500 focus:ring-4 focus:ring-gold-500/15">
                </div>
                <button type="submit"
                        class="w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus:ring-4 focus:ring-gold-500/30">
                    Kilidi aç
                </button>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
                @csrf
                <button class="text-sm text-stone-500 hover:text-stone-900 hover:underline">Farklı kullanıcıyla giriş yap</button>
            </form>
        </div>
    </div>
</body>
</html>
