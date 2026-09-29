<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Giriş · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 font-sans text-stone-800 antialiased">
    <div class="flex min-h-screen">
        {{-- Sol tanıtım paneli (geniş ekranlarda) --}}
        <div class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-stone-900 p-12 text-stone-100 lg:flex">
            <div class="absolute -right-24 -top-24 size-96 rounded-full bg-gold-500/10 blur-3xl"></div>
            <div class="absolute -bottom-32 -left-16 size-96 rounded-full bg-gold-400/10 blur-3xl"></div>

            <div class="relative flex items-center gap-3">
                <x-logo class="size-11" />
                <span class="text-lg font-semibold tracking-wide">{{ config('app.name') }}</span>
            </div>

            <div class="relative max-w-md">
                <h1 class="text-4xl font-semibold leading-tight">
                    Atölye hesaplarınız<br><span class="text-gold-400">tek yerde, düzenli.</span>
                </h1>
                <p class="mt-4 text-stone-400">
                    Cari hesaplar, kasa ve atölye hareketlerini güvenle takip edin.
                </p>
            </div>

            <p class="relative text-sm text-stone-500">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>

        {{-- Giriş formu --}}
        <div class="flex w-full items-center justify-center px-4 py-12 lg:w-1/2">
            <div class="w-full max-w-sm">
                <div class="mb-8 flex flex-col items-center text-center lg:hidden">
                    <x-logo class="size-14" />
                    <span class="mt-3 text-lg font-semibold">{{ config('app.name') }}</span>
                </div>

                <h2 class="text-2xl font-semibold text-stone-900">Hoş geldiniz</h2>
                <p class="mt-1 text-sm text-stone-500">Devam etmek için hesabınıza giriş yapın.</p>

                @if ($errors->any())
                    <div class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <label for="username" class="mb-1.5 block text-sm font-medium text-stone-700">Kullanıcı adı</label>
                        <input id="username" name="username" type="text" value="{{ old('username') }}"
                               required autofocus autocomplete="username"
                               class="block w-full rounded-lg border border-stone-300 bg-white px-3.5 py-2.5 text-stone-900 shadow-sm outline-none transition focus:border-gold-500 focus:ring-4 focus:ring-gold-500/15">
                    </div>

                    <div>
                        <label for="password" class="mb-1.5 block text-sm font-medium text-stone-700">Şifre</label>
                        <input id="password" name="password" type="password"
                               required autocomplete="current-password"
                               class="block w-full rounded-lg border border-stone-300 bg-white px-3.5 py-2.5 text-stone-900 shadow-sm outline-none transition focus:border-gold-500 focus:ring-4 focus:ring-gold-500/15">
                    </div>

                    <label class="flex items-center gap-2 text-sm text-stone-600">
                        <input type="checkbox" name="remember" value="1" @checked(old('remember'))
                               class="size-4 rounded border-stone-300 accent-gold-600">
                        Beni hatırla
                    </label>

                    <button type="submit"
                            class="w-full rounded-lg bg-stone-900 px-4 py-2.5 font-medium text-white shadow-sm transition hover:bg-stone-800 focus:outline-none focus:ring-4 focus:ring-gold-500/30">
                        Giriş yap
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
