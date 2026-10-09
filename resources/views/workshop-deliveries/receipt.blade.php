@use('App\Support\Amount')
@use('App\Support\Workshop')
@php
    $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]);
    $p = fn ($value) => Workshop::formatPurity($value);
    $no = $delivery->number;
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fiş {{ $no }} · {{ $delivery->account->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        /* 80 mm termal fiş yazıcısı */
        @page { size: 80mm auto; margin: 3mm; }
        /* Yazdırırken her şey siyah: termal fiş yazıcıları griyi soluk basar */
        @media print { body { background: #fff !important; } * { color: #000 !important; border-color: #000 !important; } }
    </style>
</head>
<body class="min-h-screen bg-stone-200 font-sans text-stone-900 antialiased print:min-h-0">
    {{-- Ekran araç çubuğu (yazdırılmaz) --}}
    <div class="sticky top-0 z-10 flex flex-wrap items-center justify-center gap-2 border-b border-stone-300 bg-white px-4 py-3 shadow-sm print:hidden">
        <a href="{{ route('workshop-deliveries.index') }}" class="btn btn-secondary">← Çıkışlara dön</a>
        <button type="button" onclick="window.print()" class="btn btn-gold">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 14h12v8H6z"/></svg>
            Yazdır
        </button>
    </div>

    {{-- Fiş --}}
    <div class="mx-auto my-6 w-[74mm] bg-white p-[3mm] text-[11px] leading-snug shadow-lg print:m-0 print:w-auto print:p-0 print:shadow-none">
        {{-- Başlık --}}
        <div class="border-b-2 border-stone-900 pb-2 text-center">
            <div class="text-sm font-bold tracking-wide">{{ $firma['name'] }}</div>
            @if ($firma['address'])
                <div class="text-stone-600">{{ $firma['address'] }}</div>
            @endif
            @if ($firma['phone'])
                <div class="text-stone-600">Tel: {{ $firma['phone'] }}</div>
            @endif
        </div>

        <div class="mt-2 text-center font-bold tracking-widest uppercase">Atölye Çıkış Fişi</div>

        <dl class="mt-2 space-y-0.5">
            <div class="flex justify-between gap-2"><dt class="text-stone-500">Fiş No</dt><dd class="font-mono font-semibold">{{ $no }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-stone-500">Tarih</dt><dd>{{ $delivery->delivered_at->format('d.m.Y H:i') }}</dd></div>
            <div class="flex justify-between gap-2"><dt class="text-stone-500">Müşteri</dt><dd class="text-right font-semibold">{{ $delivery->account->name }}</dd></div>
        </dl>

        {{-- Çıkan ürünler: her satır ayrı --}}
        <div class="mt-3 border-t border-dashed border-stone-400 pt-2">
            <div class="font-semibold">ÇIKAN ÜRÜNLER</div>
            <table class="mt-1 w-full border-collapse">
                <thead>
                    <tr class="border-b border-stone-400 text-left text-stone-600">
                        <th class="py-0.5 pr-1 font-normal">Ürün</th>
                        <th class="py-0.5 pr-1 text-right font-normal">Gram</th>
                        <th class="py-0.5 pr-1 text-right font-normal">Milyem</th>
                        <th class="py-0.5 text-right font-normal">Has</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($delivery->lines as $line)
                        <tr class="border-b border-dotted border-stone-300 align-top">
                            <td class="py-0.5 pr-1">{{ $line->product ?: $loop->iteration.'.' }}</td>
                            <td class="py-0.5 pr-1 text-right whitespace-nowrap tabular-nums">{{ Amount::format($line->gross_out, $gr, false) }}</td>
                            <td class="py-0.5 pr-1 text-right tabular-nums">{{ $p($line->purity_out) }}</td>
                            <td class="py-0.5 text-right whitespace-nowrap tabular-nums">{{ Amount::format($line->has_out, $gr, false) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-y-2 border-stone-900 text-xs font-bold">
                        <td class="py-1 pr-1">Toplam</td>
                        <td class="py-1 pr-1 text-right whitespace-nowrap tabular-nums">{{ Amount::formatMilli($delivery->grossOutMilli(), $gr, false) }}</td>
                        <td class="py-1 pr-1"></td>
                        <td class="py-1 text-right whitespace-nowrap tabular-nums">{{ Amount::formatMilli($delivery->hasOutMilli(), $gr) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        {{-- Son durum: bu çıkıştan sonraki bakiye --}}
        <div class="mt-3 border-t border-dashed border-stone-400 pt-2">
            <div class="font-semibold">SON DURUM</div>
            <dl class="mt-1 space-y-0.5">
                @forelse (collect($currencies)->filter(fn ($c, $id) => ($balances[$id] ?? 0) !== 0) as $currencyId => $currency)
                    @php $milli = $balances[$currencyId]; @endphp
                    <div class="flex justify-between text-xs font-bold">
                        <dt>{{ $milli < 0 ? 'Alacağınız' : 'Borcunuz' }} ({{ $currency->code === 'HAS' ? 'Has' : $currency->code }})</dt>
                        <dd class="tabular-nums">{{ Amount::formatMilli(abs($milli), $currency) }}</dd>
                    </div>
                @empty
                    <div class="font-semibold">Hesabınız kapalı (bakiye yok)</div>
                @endforelse
            </dl>
        </div>

        <div class="mt-4 text-center text-stone-500">Bu fiş bilgi amaçlıdır.</div>
    </div>
</body>
</html>
