{{-- Atölye üst kısmı: giriş/çıkış butonları, özet kartları, Girişler/Çıkışlar sekmeleri. $summary, $active ('giris'|'cikis') --}}
@use('App\Support\Amount')
@php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

<x-page-header title="Atölye" subtitle="Müşteriden gelen ürünler (giriş) ve müşteriye teslimler (çıkış)">
    <x-slot:actions>
        <a href="{{ route('work-orders.create') }}" class="btn btn-primary">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
            Atölyeye Giriş
        </a>
        <a href="{{ route('workshop-deliveries.create') }}" class="btn btn-gold">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            Atölyeden Çıkış
        </a>
    </x-slot:actions>
</x-page-header>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="card p-5">
        <div class="text-sm font-medium text-stone-500">Ramatta kalan</div>
        <div class="mt-2 text-2xl font-semibold tabular-nums text-amber-800">{{ Amount::formatMilli($summary['ramat_gram'], $gr) }}</div>
        <div class="mt-1 text-xs text-stone-500">%{{ number_format($summary['ramat_orani'], 1, ',', '.') }} · <a href="{{ route('ramat.index') }}" class="font-medium text-gold-700 hover:underline">Ramat hesabı →</a></div>
    </div>
    <div class="card p-5">
        <div class="text-sm font-medium text-stone-500">Ramattaki has</div>
        <div class="mt-2 text-2xl font-semibold tabular-nums text-gold-800">{{ Amount::formatMilli($summary['ramat_has'], $gr) }}</div>
        <div class="mt-1 text-xs text-stone-500">Ortalama giriş milyemiyle</div>
    </div>
    <div class="card p-5">
        <div class="text-sm font-medium text-stone-500">Bu ay giriş</div>
        <div class="mt-2 text-2xl font-semibold tabular-nums">{{ Amount::formatMilli($summary['ay_giris_gram'], $gr) }}</div>
        <div class="mt-1 text-xs text-stone-500">{{ $summary['ay_giris_adet'] }} giriş · {{ Amount::formatMilli($summary['ay_giris_has'], $gr) }} has</div>
    </div>
    <div class="card p-5">
        <div class="text-sm font-medium text-stone-500">Bu ay çıkış</div>
        <div class="mt-2 text-2xl font-semibold tabular-nums">{{ Amount::formatMilli($summary['ay_cikis_gram'], $gr) }}</div>
        <div class="mt-1 text-xs text-stone-500">{{ $summary['ay_cikis_adet'] }} çıkış · {{ Amount::formatMilli($summary['ay_cikis_has'], $gr) }} has</div>
    </div>
</div>

<div class="mt-6 flex rounded-lg border border-stone-300 bg-white p-0.5 text-sm shadow-sm sm:inline-flex">
    <a href="{{ route('work-orders.index') }}"
       @class(['flex-1 rounded-md px-4 py-1.5 text-center', 'bg-stone-900 text-white' => $active === 'giris', 'text-stone-600 hover:bg-stone-100' => $active !== 'giris'])>Girişler</a>
    <a href="{{ route('workshop-deliveries.index') }}"
       @class(['flex-1 rounded-md px-4 py-1.5 text-center', 'bg-stone-900 text-white' => $active === 'cikis', 'text-stone-600 hover:bg-stone-100' => $active !== 'cikis'])>Çıkışlar</a>
</div>
