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
            <div class="text-sm font-medium text-stone-500">Atölyede kalan</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ Amount::formatMilli($summary['atolyede_gram'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">{{ $summary['atolyede_adet'] }} açık fiş</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Atölyede kalan (has)</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-gold-800">{{ Amount::formatMilli($summary['atolyede_has'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">Firmalara has borcumuz olarak duruyor</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Bu ay çıkış</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ Amount::formatMilli($summary['ay_cikis_gram'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">{{ $summary['ay_cikis_adet'] }} çıkış · {{ Amount::formatMilli($summary['ay_cikis_has'], $gr) }} has</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Bu ay kapanan fişlerin firesi</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-red-700">{{ Amount::formatMilli($summary['ay_fire'], $gr) }}</div>
            <div class="mt-1 text-xs text-stone-500">
                {{ $summary['ay_kapanan'] }} fiş · %{{ number_format($summary['ay_fire_orani'], 2, ',', '.') }} · {{ Amount::formatMilli($summary['ay_fire_has'], $gr) }} has
            </div>
        </div>
    </div>

    <div class="mt-6 mb-4 flex flex-wrap items-end justify-between gap-3">
        <div class="flex rounded-lg border border-stone-300 bg-white p-0.5 text-sm shadow-sm">
            @foreach (['atolyede' => 'Atölyede', 'tamamlandi' => 'Tamamlanan', 'tumu' => 'Tümü'] as $value => $label)
                <a href="{{ request()->fullUrlWithQuery(['durum' => $value, 'page' => null]) }}"
                   @class(['rounded-md px-3 py-1.5', 'bg-stone-900 text-white' => $status === $value, 'text-stone-600 hover:bg-stone-100' => $status !== $value])>{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="hidden" name="durum" value="{{ $status }}">
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
                        <th class="text-right">Kalan gr</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="cursor-pointer" onclick="window.location='{{ route('work-orders.show', $order) }}'">
                            <td>
                                <a href="{{ route('work-orders.show', $order) }}" class="font-mono text-xs font-medium text-stone-700 hover:text-gold-700">{{ $order->number }}</a>
                                <div>
                                    @if ($order->isClosed())
                                        <span class="badge bg-stone-100 text-stone-600 ring-stone-300">Tamamlandı</span>
                                    @else
                                        <span class="badge bg-amber-50 text-amber-800 ring-amber-600/20">Atölyede</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap text-stone-600">{{ $order->received_at->format('d.m.Y') }}</td>
                            <td>
                                <div class="font-medium text-stone-900">{{ $order->account->name }}</div>
                                <div class="max-w-64 truncate text-xs text-stone-500">{{ $order->product }}</div>
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">{{ Amount::format($order->gross_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums text-stone-600">
                                {{ Workshop::formatPurity($order->inPurity()) }}
                                @if ((float) $order->labor_purity_in > 0)
                                    <div class="text-xs text-stone-400">{{ Workshop::formatPurity($order->purity) }} + {{ Workshop::formatPurity($order->labor_purity_in) }}</div>
                                @endif
                            </td>
                            <td class="text-right font-medium tabular-nums whitespace-nowrap text-gold-800">{{ Amount::format($order->has_in, $gr, false) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                {{ $order->deliveries_count ? Amount::formatMilli($order->deliveredMilli(), $gr, false) : '—' }}
                                @if ($order->deliveries_count > 1)
                                    <div class="text-xs text-stone-400">{{ $order->deliveries_count }} çıkış</div>
                                @endif
                            </td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                <span @class(['font-medium', 'text-amber-800' => ! $order->isClosed(), 'text-red-700' => $order->isClosed()])>
                                    {{ Amount::formatMilli($order->remainingMilli(), $gr, false) }}
                                </span>
                                <div class="text-xs text-stone-500">
                                    {{ $order->isClosed() ? 'fire' : '' }} %{{ number_format($order->remainingRate(), 1, ',', '.') }}
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-stone-500">
                                @if ($status === 'atolyede')
                                    Atölyede bekleyen ürün yok. <a href="{{ route('work-orders.create') }}" class="font-medium text-gold-700 hover:underline">Yeni giriş yapın</a>.
                                @else
                                    Kayıt bulunamadı.
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
