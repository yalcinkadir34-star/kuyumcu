@extends('layouts.app')

@section('title', 'Atölye')

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

    <x-page-header title="Atölye" subtitle="Fason işçilik: giriş, çıkışlar ve atölyede kalan">
        <x-slot:actions>
            <a href="{{ route('work-orders.create') }}" class="btn btn-primary">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                Atölyeye Giriş
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
            <div class="mt-1 text-xs text-stone-500">Kalan gram × giriş milyemi</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Bu ay giriş</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ Amount::formatMilli($summary['ay_giris_gram'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">{{ $summary['ay_giris_adet'] }} fiş · {{ Amount::formatMilli($summary['ay_giris_has'], $gr) }} has</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Bu ay çıkış</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ Amount::formatMilli($summary['ay_cikis_gram'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">{{ $summary['ay_cikis_adet'] }} çıkış · {{ Amount::formatMilli($summary['ay_cikis_has'], $gr) }} has</div>
        </div>
    </div>

    <div class="mt-6 mb-4 flex flex-wrap items-end justify-end gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input name="q" value="{{ request('q') }}" class="input w-48 py-1.5" placeholder="Fiş no, ürün, firma…" aria-label="Ara">
            <select name="cari" class="input w-auto py-1.5" aria-label="Firma">
                <option value="">Tüm firmalar</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((int) request('cari') === $account->id)>{{ $account->name }}</option>
                @endforeach
            </select>
            <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="input w-auto py-1.5" aria-label="Giriş başlangıç">
            <input type="date" name="bitis" value="{{ request('bitis') }}" class="input w-auto py-1.5" aria-label="Giriş bitiş">
            <button class="btn btn-secondary py-1.5">Filtrele</button>
        </form>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Fiş</th>
                        <th>Giriş</th>
                        <th>Firma / Ürün</th>
                        <th class="text-right">Giriş gr</th>
                        <th class="text-right">Milyem</th>
                        <th class="text-right">Has</th>
                        <th class="text-right">Çıkan gr</th>
                        <th class="text-right">Ramat gr</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('work-orders.show', $order) }}'">
                            <td>
                                <a href="{{ route('work-orders.show', $order) }}" class="font-mono text-xs font-medium text-stone-700 hover:text-gold-700">{{ $order->number }}</a>
                            </td>
                            <td class="whitespace-nowrap text-stone-600">{{ $order->received_at->format('d.m.Y H:i:s') }}</td>
                            <td>
                                <div class="font-medium text-stone-900">{{ $order->account->name }}</div>
                                <div class="max-w-64 truncate text-xs text-stone-500">{{ $order->product }}</div>
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Amount::format($order->gross_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums text-stone-600">
                                {{ Workshop::formatPurity($order->purity) }}
                            </td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap text-gold-800">{{ Amount::format($order->has_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                {{ $order->deliveries_count ? Amount::formatMilli($order->deliveredMilli(), $gr, false) : '—' }}
                                @if ($order->deliveries_count > 1)
                                    <div class="text-xs text-stone-400">{{ $order->deliveries_count }} çıkış</div>
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                <span class="font-medium text-amber-800">{{ Amount::formatMilli($order->remainingMilli(), $gr, false) }}</span>
                                <div class="text-xs text-stone-500">%{{ number_format($order->remainingRate(), 1, ',', '.') }}</div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-stone-500">
                                @if (request()->hasAny(['q', 'cari', 'baslangic', 'bitis']))
                                    Kayıt bulunamadı.
                                @else
                                    Henüz atölye girişi yok. <a href="{{ route('work-orders.create') }}" class="font-medium text-gold-700 hover:underline">Yeni giriş yapın</a>.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">{{ $orders->links() }}</div>
@endsection
