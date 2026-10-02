@extends('layouts.app')

@section('title', $order->number)

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

    <x-page-header :title="$order->number.' · '.$order->product" :back="route('work-orders.index')">
        <x-slot:subtitle>
            Atölyeye giriş ·
            <a href="{{ route('accounts.show', $order->account) }}" class="font-medium text-stone-700 hover:text-gold-700">{{ $order->account->name }}</a>
            · Kaydeden: {{ $order->creator?->name ?? '—' }}
        </x-slot:subtitle>
        <x-slot:actions>
            <a href="{{ route('work-orders.edit', $order) }}" class="btn btn-secondary">Girişi Düzenle</a>
            <a href="{{ route('workshop-deliveries.create', ['cari' => $order->account_id]) }}" class="btn btn-gold">Bu Müşteriye Çıkış Yap</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Giriş bilgileri --}}
    <div class="card">
        <dl class="grid divide-y divide-stone-100 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
            <div class="p-5">
                <dt class="text-xs font-medium text-stone-500">Tarih</dt>
                <dd class="mt-1 text-lg font-semibold">{{ $order->received_at->format('d.m.Y H:i:s') }}</dd>
            </div>
            <div class="p-5">
                <dt class="text-xs font-medium text-stone-500">Gram</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Amount::format($order->gross_in, $gr) }}</dd>
            </div>
            <div class="p-5">
                <dt class="text-xs font-medium text-stone-500">Milyem</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Workshop::formatPurity($order->purity) }}</dd>
            </div>
            <div class="bg-gold-50/60 p-5">
                <dt class="text-xs font-medium text-gold-800">Has</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-gold-900">{{ Amount::format($order->has_in, $gr) }}</dd>
                <dd class="mt-0.5 text-xs text-gold-800">Müşterinin carisine alacak yazıldı</dd>
            </div>
        </dl>
        @if ($order->notes)
            <div class="border-t border-stone-100 px-5 py-3 text-sm whitespace-pre-line text-stone-600">{{ $order->notes }}</div>
        @endif
    </div>

    {{-- Müşterinin atölyedeki genel durumu --}}
    <div class="card mt-6">
        <div class="card-header">
            <h3 class="font-semibold text-stone-900">{{ $order->account->name }} · atölye durumu</h3>
            <a href="{{ route('ramat.index', ['cari' => $order->account_id]) }}" class="text-sm font-medium text-gold-700 hover:underline">Tüm giriş ve çıkışlar →</a>
        </div>
        <dl class="grid grid-cols-2 divide-stone-100 sm:grid-cols-4 sm:divide-x">
            <div class="p-5">
                <dt class="text-xs text-stone-500">Toplam giriş</dt>
                <dd class="mt-1 font-semibold tabular-nums">{{ Amount::formatMilli($totals['giris_gram'], $gr) }}</dd>
                <dd class="text-xs text-stone-500">{{ $totals['giris_adet'] }} giriş</dd>
            </div>
            <div class="p-5">
                <dt class="text-xs text-stone-500">Toplam çıkış</dt>
                <dd class="mt-1 font-semibold tabular-nums">{{ Amount::formatMilli($totals['cikis_gram'], $gr) }}</dd>
                <dd class="text-xs text-stone-500">{{ $totals['cikis_adet'] }} çıkış</dd>
            </div>
            <div class="bg-amber-50/60 p-5">
                <dt class="text-xs text-amber-800">Atölyede (ramatta) kalan</dt>
                <dd class="mt-1 font-semibold tabular-nums text-amber-900">{{ Amount::formatMilli($totals['ramat_gram'], $gr) }}</dd>
                <dd class="text-xs text-stone-500">%{{ number_format($totals['oran'], 1, ',', '.') }}</dd>
            </div>
            <div class="p-5">
                <dt class="text-xs text-stone-500">Müşteriye has borcu</dt>
                <dd class="mt-1 font-semibold tabular-nums text-sky-800">{{ Amount::formatMilli($totals['has_borcu'], $gr) }}</dd>
            </div>
        </dl>
    </div>
@endsection
