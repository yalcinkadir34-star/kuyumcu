@extends('layouts.app')

@section('title', 'Ana Sayfa')

@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-semibold text-stone-900">Merhaba, {{ auth()->user()->name }}</h2>
        <p class="mt-1 text-sm text-stone-500">{{ $today }}</p>
    </div>

    {{-- Özet kartları --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($stats as $stat)
            <div class="rounded-xl border border-stone-200 bg-white p-5 shadow-sm">
                <div class="text-sm font-medium text-stone-500">{{ $stat['label'] }}</div>
                <div class="mt-2 text-3xl font-semibold tabular-nums text-stone-900">
                    {{ $stat['value'] ?? '—' }}
                </div>
                <div class="mt-2 text-xs text-stone-400">{{ $stat['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        {{-- Son hareketler --}}
        <div class="rounded-xl border border-stone-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-stone-200 px-5 py-4">
                <h3 class="font-semibold text-stone-900">Son Hareketler</h3>
            </div>
            <div class="flex flex-col items-center justify-center px-5 py-14 text-center">
                <div class="flex size-12 items-center justify-center rounded-full bg-stone-100 text-stone-400">
                    <svg class="size-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
                </div>
                <p class="mt-3 font-medium text-stone-700">Henüz hareket yok</p>
                <p class="mt-1 max-w-sm text-sm text-stone-500">Cari ve kasa modülleri eklendiğinde son işlemler burada listelenecek.</p>
            </div>
        </div>

        {{-- Sistem bilgisi --}}
        <div class="rounded-xl border border-stone-200 bg-white shadow-sm">
            <div class="border-b border-stone-200 px-5 py-4">
                <h3 class="font-semibold text-stone-900">Oturum Bilgisi</h3>
            </div>
            <dl class="divide-y divide-stone-100 text-sm">
                <div class="flex justify-between px-5 py-3">
                    <dt class="text-stone-500">Kullanıcı</dt>
                    <dd class="font-medium text-stone-900">{{ auth()->user()->username }}</dd>
                </div>
                <div class="flex justify-between px-5 py-3">
                    <dt class="text-stone-500">Yetki</dt>
                    <dd class="font-medium text-stone-900">{{ auth()->user()->roleLabel() }}</dd>
                </div>
                <div class="flex justify-between px-5 py-3">
                    <dt class="text-stone-500">Son giriş</dt>
                    <dd class="font-medium text-stone-900">{{ auth()->user()->last_login_at?->format('d.m.Y H:i') ?? '—' }}</dd>
                </div>
            </dl>
        </div>
    </div>
@endsection
