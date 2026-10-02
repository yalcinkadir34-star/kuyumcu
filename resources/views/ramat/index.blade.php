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
            <div class="text-sm font-medium text-stone-500">Giriş fişi / Müşteri</div>
            <div class="mt-2 text-2xl font-semibold tabular-nums">{{ $total['giris_adet'] }} / {{ $customers->count() }}</div>
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
                        <th class="text-right">Giriş</th>
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
                            <td class="text-right tabular-nums text-stone-600">{{ $row['giris_adet'] }}</td>
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
                            <td class="px-4 py-2.5 text-right tabular-nums">{{ $total['giris_adet'] }}</td>
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

    {{-- Seçili müşterinin giriş ve çıkışları --}}
    @if ($selected)
        @php $running = 0; @endphp
        <div class="card mt-6 overflow-hidden">
            <div class="card-header">
                <h3 class="font-semibold text-stone-900">{{ $selected->name }} · giriş ve çıkışlar</h3>
                <a href="{{ route('ramat.index', $dateQuery) }}" class="text-sm text-stone-500 hover:text-stone-800">Kapat ✕</a>
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>İşlem</th>
                            <th>Ürün</th>
                            <th class="text-right">Gram</th>
                            <th class="text-right">Milyem</th>
                            <th class="text-right">Has</th>
                            <th class="text-right">Ramat gr</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($movements as $m)
                            @php $running += ($m->type === 'giris' ? 1 : -1) * Amount::toMilli($m->gram); @endphp
                            <tr class="cursor-pointer" onclick="window.location='{{ $m->url }}'">
                                <td class="whitespace-nowrap text-stone-600">{{ $m->date->format('d.m.Y H:i') }}</td>
                                <td class="whitespace-nowrap">
                                    @if ($m->type === 'giris')
                                        <span class="badge bg-sky-50 text-sky-700 ring-sky-600/20">Giriş</span>
                                    @else
                                        <span class="badge bg-emerald-50 text-emerald-700 ring-emerald-600/20">Çıkış</span>
                                    @endif
                                    <span class="ml-1 font-mono text-xs text-stone-500">{{ $m->number }}</span>
                                </td>
                                <td class="max-w-56 truncate text-stone-600">{{ $m->product ?: '—' }}</td>
                                <td class="text-right tabular-nums">{{ ($m->type === 'giris' ? '+' : '−').Amount::format($m->gram, $gr, false) }}</td>
                                <td class="text-right tabular-nums text-stone-600">{{ Workshop::formatPurity($m->purity) }}</td>
                                <td class="text-right tabular-nums text-gold-800">{{ Amount::format($m->has, $gr, false) }}</td>
                                <td class="text-right font-semibold tabular-nums text-amber-800">{{ $f($running) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-10 text-center text-stone-500">Bu aralıkta hareket yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (request()->hasAny(['baslangic', 'bitis']))
                <p class="border-t border-stone-100 px-5 py-2 text-xs text-stone-500">Ramat sütunu sadece seçili tarih aralığındaki hareketlerden hesaplanır.</p>
            @endif
        </div>
    @endif
@endsection
