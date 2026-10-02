@extends('layouts.app')

@section('title', 'Ramat Hesabı')

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php
        $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]);
        $f = fn (int $milli) => Amount::formatMilli($milli, $gr, false);
        $rate = fn (float $value) => '%'.number_format($value, 1, ',', '.');
        $dateQuery = request()->only(['baslangic', 'bitis']);
    @endphp

    <x-page-header title="Ramat Hesabı" subtitle="Atölyeye gelip müşteriye geri çıkmayan, ramatta kalan altın">
        <x-slot:actions>
            <form method="GET" class="flex flex-wrap items-center gap-2">
                @if ($selected)
                    <input type="hidden" name="cari" value="{{ $selected->id }}">
                @endif
                <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="input w-auto py-1.5" aria-label="Başlangıç">
                <span class="text-stone-400">–</span>
                <input type="date" name="bitis" value="{{ request('bitis') }}" class="input w-auto py-1.5" aria-label="Bitiş">
                <button class="btn btn-secondary py-1.5">Göster</button>
                @if (request()->hasAny(['baslangic', 'bitis']))
                    <a href="{{ route('ramat.index', $selected ? ['cari' => $selected->id] : []) }}" class="btn py-1.5 text-stone-500 hover:text-stone-800">Tümü</a>
                @endif
            </form>
        </x-slot:actions>
    </x-page-header>

    {{-- Toplamlar --}}
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Ramatta kalan</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-amber-800">{{ $f($total['ramat_gram']) }} gr</div>
            <div class="mt-1 text-xs text-stone-500">{{ $rate($total['oran']) }} · {{ $f($total['giris_gram']) }} gr girişin</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Ramattaki has</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-gold-800">{{ $f($total['ramat_has']) }} gr</div>
            <div class="mt-1 text-xs text-stone-500">Kalan gram × giriş milyemi</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Müşterilere has borcu</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums text-sky-800">{{ $f($total['giris_has'] - $total['cikis_has']) }} gr</div>
            <div class="mt-1 text-xs text-stone-500">Giriş has − çıkış has</div>
        </div>
        <div class="card p-5">
            <div class="text-sm font-medium text-stone-500">Fiş / Müşteri</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $total['fis'] }} / {{ $customers->count() }}</div>
            <div class="mt-1 text-xs text-stone-500">
                @if (request()->hasAny(['baslangic', 'bitis']))
                    Seçili tarih aralığında giren fişler
                @else
                    Tüm zamanlar
                @endif
            </div>
        </div>
    </div>

    {{-- Müşteri bazlı --}}
    <div class="card mt-6 overflow-hidden">
        <div class="card-header">
            <h3 class="font-semibold text-stone-900">Müşteri bazında ramat</h3>
            <span class="text-xs text-stone-500">Detay için müşteriye tıklayın</span>
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th>Müşteri</th>
                        <th class="text-right">Fiş</th>
                        <th class="text-right">Giren gr</th>
                        <th class="text-right">Çıkan gr</th>
                        <th class="text-right">Ramat gr</th>
                        <th class="text-right">Ramat has</th>
                        <th class="text-right">Has borcu</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $row)
                        @php $isSelected = $selected?->id === $row['account']->id; @endphp
                        <tr @class(['cursor-pointer', 'bg-gold-50/60' => $isSelected])
                            onclick="window.location='{{ route('ramat.index', $dateQuery + ['cari' => $row['account']->id]) }}'">
                            <td>
                                <a href="{{ route('ramat.index', $dateQuery + ['cari' => $row['account']->id]) }}" class="font-medium text-stone-900 hover:text-gold-700">{{ $row['account']->name }}</a>
                            </td>
                            <td class="text-right tabular-nums text-stone-600">{{ $row['fis'] }}</td>
                            <td class="text-right tabular-nums">{{ $f($row['giris_gram']) }}</td>
                            <td class="text-right tabular-nums">{{ $f($row['cikis_gram']) }}</td>
                            <td class="text-right tabular-nums whitespace-nowrap">
                                <span class="font-semibold text-amber-800">{{ $f($row['ramat_gram']) }}</span>
                                <div class="text-xs text-stone-500">{{ $rate($row['oran']) }}</div>
                            </td>
                            <td class="text-right font-medium tabular-nums text-gold-800">{{ $f($row['ramat_has']) }}</td>
                            <td class="text-right font-medium tabular-nums text-sky-800">{{ $f($row['giris_has'] - $row['cikis_has']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-stone-500">Bu aralıkta atölye girişi yok.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($customers->count() > 1)
                    <tfoot>
                        <tr class="bg-stone-50 font-semibold">
                            <td class="px-4 py-2.5">Toplam</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $total['fis'] }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $f($total['giris_gram']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $f($total['cikis_gram']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-amber-800">{{ $f($total['ramat_gram']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-gold-800">{{ $f($total['ramat_has']) }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums text-sky-800">{{ $f($total['giris_has'] - $total['cikis_has']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Seçili müşterinin fişleri --}}
    @if ($selected)
        <div class="card mt-6 overflow-hidden">
            <div class="card-header">
                <h3 class="font-semibold text-stone-900">{{ $selected->name }} · fiş bazında ramat</h3>
                <a href="{{ route('ramat.index', $dateQuery) }}" class="text-sm text-stone-500 hover:text-stone-800">Kapat ✕</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Fiş</th>
                            <th>Giriş</th>
                            <th>Ürün</th>
                            <th class="text-right">Giren gr</th>
                            <th class="text-right">Milyem</th>
                            <th class="text-right">Çıkan gr</th>
                            <th class="text-right">Ramat gr</th>
                            <th class="text-right">Ramat has</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($selectedOrders as $order)
                            <tr class="cursor-pointer" onclick="window.location='{{ route('work-orders.show', $order) }}'">
                                <td>
                                    <a href="{{ route('work-orders.show', $order) }}" class="font-mono text-xs font-medium text-stone-700 hover:text-gold-700">{{ $order->number }}</a>
                                </td>
                                <td class="whitespace-nowrap text-stone-600">{{ $order->received_at->format('d.m.Y H:i') }}</td>
                                <td class="max-w-56 truncate text-stone-600">{{ $order->product }}</td>
                                <td class="text-right tabular-nums">{{ Amount::format($order->gross_in, $gr, false) }}</td>
                                <td class="text-right tabular-nums text-stone-600">{{ Workshop::formatPurity($order->purity) }}</td>
                                <td class="text-right tabular-nums">{{ $f($order->deliveredMilli()) }}</td>
                                <td class="text-right tabular-nums whitespace-nowrap">
                                    <span class="font-semibold text-amber-800">{{ $f($order->remainingMilli()) }}</span>
                                    <div class="text-xs text-stone-500">{{ $rate($order->remainingRate()) }}</div>
                                </td>
                                <td class="text-right font-medium tabular-nums text-gold-800">{{ $f($order->remainingHasMilli()) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-10 text-center text-stone-500">Bu aralıkta fiş yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
