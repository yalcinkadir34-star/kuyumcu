@props(['milli' => 0, 'currency'])
{{-- Cari bakiyesi: pozitif = cari bize borçlu (B), negatif = biz cariye borçluyuz (A) --}}
@use('App\Support\Amount')

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 tabular-nums whitespace-nowrap']) }}>
    @if ($milli === 0)
        <span class="text-stone-400">{{ Amount::formatMilli(0, $currency) }}</span>
    @else
        <span class="font-medium text-stone-900">{{ Amount::formatMilli(abs($milli), $currency) }}</span>
        @if ($milli > 0)
            <span class="rounded bg-amber-100 px-1 text-[10px] font-bold text-amber-800" title="Borçlu: cari bize borçlu">B</span>
        @else
            <span class="rounded bg-sky-100 px-1 text-[10px] font-bold text-sky-800" title="Alacaklı: biz cariye borçluyuz">A</span>
        @endif
    @endif
</span>
