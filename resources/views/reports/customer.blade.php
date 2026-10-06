@extends('layouts.app')

@section('title', 'Müşteri Raporu')

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php
        $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]);
        $g = fn (?int $milli) => $milli ? Amount::formatMilli($milli, $gr, false) : '—';
        // Müşteri gözünden bakiye: negatif = atölye müşteriye borçlu → "Alacağınız"
        $balance = fn (int $milli) => $milli === 0
            ? '0,000 gr'
            : ($milli < 0 ? 'Alacağınız ' : 'Borcunuz ').Amount::formatMilli(abs($milli), $gr);
        $period = $custom
            ? $from->format('d.m.Y').' – '.$to->format('d.m.Y')
            : $year.' yılı (01.01.'.$year.' – 31.12.'.$year.')';
    @endphp

    <style>
        @media print {
            @page { size: A4; margin: 12mm; }
            tr { break-inside: avoid; }
            * { color: #000 !important; }
        }
    </style>

    {{-- Seçim (yazdırılmaz) --}}
    <div class="print:hidden">
        <x-page-header title="Müşteri Raporu" subtitle="Müşteriye verilebilecek has hesap dökümü: yıllık ya da seçilen tarih aralığı" />

        <form method="GET" class="card mb-6 flex flex-wrap items-end gap-3 p-4">
            <div class="min-w-56 flex-1">
                <label for="cari" class="label">Müşteri</label>
                <select id="cari" name="cari" class="input" required>
                    <option value="">Müşteri seçin…</option>
                    @foreach ($accounts as $item)
                        <option value="{{ $item->id }}" @selected($account?->id === $item->id)>{{ $item->name }} ({{ $item->code }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="yil" class="label">Yıl</label>
                <select id="yil" name="yil" class="input">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected(! $custom && $year === $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <details class="group" @if ($custom) open @endif>
                <summary class="btn btn-secondary cursor-pointer list-none">Özel tarih aralığı</summary>
                <div class="mt-2 flex items-center gap-2">
                    <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="input w-auto" aria-label="Başlangıç">
                    <span class="text-stone-400">–</span>
                    <input type="date" name="bitis" value="{{ request('bitis') }}" class="input w-auto" aria-label="Bitiş">
                </div>
            </details>
            <button class="btn btn-primary">Raporu Göster</button>
            @if ($account)
                <button type="button" onclick="window.print()" class="btn btn-gold">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
                    Yazdır
                </button>
            @endif
        </form>
    </div>

    @if (! $account)
        <div class="card px-5 py-14 text-center text-stone-500 print:hidden">
            Raporu görmek için müşteri ve yıl seçip <b>Raporu Göster</b>'e basın.
        </div>
    @else
        {{-- Rapor (yazdırılan kısım) --}}
        <div class="card mx-auto max-w-5xl p-8 text-sm print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none">
            {{-- Başlık --}}
            <div class="flex flex-wrap items-start justify-between gap-4 border-b-2 border-stone-900 pb-3">
                <div>
                    <div class="text-lg font-bold tracking-wide">{{ $firma['name'] }}</div>
                    @if ($firma['address']) <div class="text-stone-600">{{ $firma['address'] }}</div> @endif
                    @if ($firma['phone']) <div class="text-stone-600">Tel: {{ $firma['phone'] }}</div> @endif
                </div>
                <div class="text-right">
                    <div class="font-bold tracking-widest uppercase">Müşteri Hesap Raporu</div>
                    <div class="text-stone-600">Dönem: {{ $period }}</div>
                    <div class="text-stone-500">Düzenlenme: {{ now()->format('d.m.Y H:i') }}</div>
                </div>
            </div>

            <div class="mt-3 text-base">
                Müşteri: <span class="font-semibold">{{ $account->name }}</span> <span class="text-stone-500">({{ $account->code }})</span>
            </div>

            {{-- Özet --}}
            <div class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-lg border border-stone-300 bg-stone-300 md:grid-cols-4 print:grid-cols-4">
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Dönem başı</div>
                    <div class="mt-1 font-semibold tabular-nums">{{ $balance($opening) }}</div>
                </div>
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Atölyeye verilen</div>
                    <div class="mt-1 font-semibold tabular-nums">{{ $g($totals['in_has']) }} gr has</div>
                    <div class="text-xs text-stone-500 tabular-nums">{{ $g($totals['in_gram']) }} gr ürün</div>
                </div>
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Size teslim edilen</div>
                    <div class="mt-1 font-semibold tabular-nums">{{ $g($totals['out_has']) }} gr has</div>
                    <div class="text-xs text-stone-500 tabular-nums">{{ $g($totals['out_gram']) }} gr ürün</div>
                </div>
                <div class="bg-gold-50 p-3 print:bg-white">
                    <div class="text-xs text-stone-500">Dönem sonu</div>
                    <div class="mt-1 text-base font-bold tabular-nums">{{ $balance($closing) }}</div>
                </div>
            </div>

            {{-- Aylık özet (yıllık raporda) --}}
            @if ($months)
                <h3 class="mt-6 mb-2 font-semibold uppercase">Aylık Özet</h3>
                <table class="w-full border-collapse text-sm">
                    <thead>
                        <tr class="border-y border-stone-400 text-left text-xs text-stone-600">
                            <th class="py-1.5 pr-2">Ay</th>
                            <th class="py-1.5 pr-2 text-right">Verilen ürün (gr)</th>
                            <th class="py-1.5 pr-2 text-right">Verilen has</th>
                            <th class="py-1.5 pr-2 text-right">Teslim ürün (gr)</th>
                            <th class="py-1.5 text-right">Teslim has</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($months as $m)
                            <tr @class(['border-b border-stone-200', 'text-stone-400' => $m['count'] === 0])>
                                <td class="py-1 pr-2">{{ $m['name'] }}</td>
                                <td class="py-1 pr-2 text-right tabular-nums">{{ $g($m['in_gram']) }}</td>
                                <td class="py-1 pr-2 text-right tabular-nums">{{ $g($m['in_has']) }}</td>
                                <td class="py-1 pr-2 text-right tabular-nums">{{ $g($m['out_gram']) }}</td>
                                <td class="py-1 text-right tabular-nums">{{ $g($m['out_has']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-y-2 border-stone-900 font-semibold">
                            <td class="py-1.5 pr-2">Toplam</td>
                            <td class="py-1.5 pr-2 text-right tabular-nums">{{ $g($totals['in_gram']) }}</td>
                            <td class="py-1.5 pr-2 text-right tabular-nums">{{ $g($totals['in_has']) }}</td>
                            <td class="py-1.5 pr-2 text-right tabular-nums">{{ $g($totals['out_gram']) }}</td>
                            <td class="py-1.5 text-right tabular-nums">{{ $g($totals['out_has']) }}</td>
                        </tr>
                    </tbody>
                </table>
            @endif

            {{-- Hareket dökümü --}}
            <h3 class="mt-6 mb-2 font-semibold uppercase">Hareket Dökümü</h3>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr class="border-y border-stone-400 text-left text-xs text-stone-600">
                        <th class="py-1.5 pr-2">Tarih</th>
                        <th class="py-1.5 pr-2">Fiş</th>
                        <th class="py-1.5 pr-2">Açıklama</th>
                        <th class="py-1.5 pr-2 text-right">Gram</th>
                        <th class="py-1.5 pr-2 text-right">Milyem</th>
                        <th class="py-1.5 pr-2 text-right">Verilen has</th>
                        <th class="py-1.5 pr-2 text-right">Teslim has</th>
                        <th class="py-1.5 text-right">Bakiye</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-stone-200 text-stone-600">
                        <td class="py-1 pr-2 whitespace-nowrap">{{ $from->format('d.m.Y') }}</td>
                        <td colspan="6" class="py-1 pr-2">Devreden bakiye</td>
                        <td class="py-1 text-right whitespace-nowrap tabular-nums">{{ $balance($opening) }}</td>
                    </tr>
                    @forelse ($rows as $row)
                        <tr class="border-b border-stone-200">
                            <td class="py-1 pr-2 whitespace-nowrap">{{ $row->date->format('d.m.Y H:i') }}</td>
                            <td class="py-1 pr-2 font-mono text-xs whitespace-nowrap">{{ $row->document_no ?: '—' }}</td>
                            <td class="py-1 pr-2">{{ $row->label }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums">{{ $g($row->gram_milli) }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums">{{ $row->purity ? Workshop::formatPurity($row->purity) : '—' }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums">{{ $row->is_in ? $g(abs($row->effect_milli)) : '' }}</td>
                            <td class="py-1 pr-2 text-right tabular-nums">{{ $row->is_in ? '' : $g(abs($row->effect_milli)) }}</td>
                            <td class="py-1 text-right whitespace-nowrap tabular-nums">{{ $balance($row->running_milli) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-6 text-center text-stone-500">Bu dönemde hareket yok.</td>
                        </tr>
                    @endforelse
                    <tr class="border-y-2 border-stone-900 font-semibold">
                        <td colspan="5" class="py-1.5 pr-2">Dönem toplamı ({{ $totals['count'] }} hareket)</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ $g($totals['in_has']) }}</td>
                        <td class="py-1.5 pr-2 text-right tabular-nums">{{ $g($totals['out_has']) }}</td>
                        <td class="py-1.5 text-right whitespace-nowrap tabular-nums">{{ $balance($closing) }}</td>
                    </tr>
                </tbody>
            </table>

            <div class="mt-6 grid grid-cols-2 gap-8 text-center">
                <div>
                    <div class="h-12 border-b border-stone-400"></div>
                    <div class="mt-1 text-stone-600">{{ $firma['name'] }}</div>
                </div>
                <div>
                    <div class="h-12 border-b border-stone-400"></div>
                    <div class="mt-1 text-stone-600">{{ $account->name }}</div>
                </div>
            </div>

            <p class="mt-6 text-xs text-stone-500">
                "Alacağınız": atölyenin size olan has borcu. "Borcunuz": sizin atölyeye olan has borcunuz.
                Has = gram × milyem. Bu rapor bilgi amaçlıdır.
            </p>
        </div>
    @endif
@endsection
