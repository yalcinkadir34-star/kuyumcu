@php
    // Yan menü. 'route' => null olan modüller henüz geliştirilmedi.
    // 'active': hangi sayfalarda bu menünün seçili görüneceği.
    $menu = [
        ['label' => 'Ana Sayfa', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'M3 12l9-8 9 8M5 10v10h5v-6h4v6h5V10'],
        ['label' => 'Cariler', 'route' => 'accounts.index', 'active' => 'accounts.*', 'icon' => 'M16 19v-1a4 4 0 00-4-4H6a4 4 0 00-4 4v1M9 10a3 3 0 100-6 3 3 0 000 6zM22 19v-1a4 4 0 00-3-3.87M16 4.13a3 3 0 010 5.74'],
        ['label' => 'Kasalar', 'route' => 'cash-registers.index', 'active' => 'cash-registers.*', 'icon' => 'M3 7h18v12H3zM3 7l2-3h14l2 3M12 11v4M10 13h4'],
        ['label' => 'Hareketler', 'route' => 'transactions.index', 'active' => 'transactions.*', 'icon' => 'M7 4v16M7 4L3 8M7 4l4 4M17 20V4M17 20l-4-4M17 20l4-4'],
        ['label' => 'Atölye', 'route' => null, 'icon' => 'M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z'],
        ['label' => 'Stok', 'route' => null, 'icon' => 'M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8'],
        ['label' => 'Raporlar', 'route' => null, 'icon' => 'M4 20V10M10 20V4M16 20v-7M22 20H2'],
        ['label' => 'Ayarlar', 'route' => null, 'icon' => 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 110-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z'],
    ];
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-stone-100 font-sans text-stone-800 antialiased">
    {{-- Mobil menü arka planı --}}
    <div id="sidebar-backdrop" data-sidebar-toggle class="fixed inset-0 z-30 hidden bg-stone-900/50 lg:hidden"></div>

    {{-- Yan menü --}}
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col bg-stone-900 text-stone-300 transition-transform lg:translate-x-0">
        <div class="flex h-16 items-center gap-3 border-b border-white/5 px-5">
            <x-logo class="size-9" />
            <span class="font-semibold tracking-wide text-white">{{ config('app.name') }}</span>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto p-3">
            @foreach ($menu as $item)
                @if ($item['route'])
                    @php $active = request()->routeIs($item['active']); @endphp
                    <a href="{{ route($item['route']) }}"
                       @class([
                           'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition',
                           'bg-gold-500/15 text-gold-300' => $active,
                           'hover:bg-white/5 hover:text-white' => ! $active,
                       ])>
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium text-stone-500" title="Bu modül henüz geliştirilmedi">
                        <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $item['icon'] }}"/></svg>
                        {{ $item['label'] }}
                        <span class="ml-auto rounded bg-white/5 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-stone-500">Yakında</span>
                    </span>
                @endif
            @endforeach
        </nav>

        <div class="border-t border-white/5 p-4 text-xs text-stone-500">
            Sürüm 0.2
        </div>
    </aside>

    <div class="lg:pl-64">
        {{-- Üst bar --}}
        <header class="sticky top-0 z-20 flex h-16 items-center gap-4 border-b border-stone-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button type="button" data-sidebar-toggle class="-ml-1 rounded-lg p-2 text-stone-600 hover:bg-stone-100 lg:hidden" aria-label="Menüyü aç">
                <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <h1 class="truncate text-lg font-semibold text-stone-900">@yield('title')</h1>

            <div class="ml-auto flex items-center gap-3">
                <div class="hidden text-right sm:block">
                    <div class="text-sm font-medium text-stone-900">{{ auth()->user()->name }}</div>
                    <div class="text-xs text-stone-500">{{ auth()->user()->roleLabel() }}</div>
                </div>
                <div class="flex size-9 items-center justify-center rounded-full bg-gold-100 text-sm font-semibold text-gold-800">
                    {{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-lg p-2 text-stone-500 transition hover:bg-stone-100 hover:text-stone-900" title="Çıkış yap" aria-label="Çıkış yap">
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                    </button>
                </form>
            </div>
        </header>

        <main class="p-4 sm:p-6">
            <x-flash />
            @yield('content')
        </main>
    </div>
</body>
</html>
