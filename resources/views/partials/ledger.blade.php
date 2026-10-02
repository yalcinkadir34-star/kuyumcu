{{--
    Ekstre tablosu.
    $ledger: Ledger::build() sonucu | $mode: 'cari' veya 'kasa' | $currencies: id => Currency
--}}
@use('App\Support\Amount')
@php
    $isCari = $mode === 'cari';
    $inLabel = $isCari ? 'Borç' : 'Giriş';
    $outLabel = $isCari ? 'Alacak' : 'Çıkış';
    $filtered = request()->hasAny(['baslangic', 'bitis']);
@endphp

<div class="card overflow-hidden">
    <div class="card-header flex-wrap">
        <h3 class="font-semibold text-stone-900">{{ $title }}</h3>
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="baslangic" value="{{ request('baslangic') }}" class="input w-auto py-1.5" aria-label="Başlangıç tarihi">
            <span class="text-stone-400">–</span>
            <input type="date" name="bitis" value="{{ request('bitis') }}" class="input w-auto py-1.5" aria-label="Bitiş tarihi">
            <button class="btn btn-secondary py-1.5">Göster</button>
            @if ($filtered)
                <a href="{{ url()->current() }}" class="btn py-1.5 text-stone-500 hover:text-stone-800">Tümü</a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Tarih</th>
                    <th>İşlem</th>
                    <th>{{ $isCari ? 'Kasa' : 'Cari' }}</th>
                    <th>Açıklama</th>
                    <th class="text-right">{{ $inLabel }}</th>
                    <th class="text-right">{{ $outLabel }}</th>
                    <th class="text-right">Bakiye</th>
                    <th class="w-10"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($ledger['opening'] as $currencyId => $milli)
                    @continue($milli === 0)
                    <tr class="bg-stone-50/70">
                        <td class="text-stone-500">{{ \Illuminate\Support\Carbon::parse(request('baslangic'))->format('d.m.Y') }}</td>
                        <td colspan="5" class="font-medium text-stone-600">Devreden bakiye ({{ $currencies[$currencyId]->code }})</td>
                        <td class="text-right">
                            @if ($isCari)
                                <x-cari-bakiye :milli="$milli" :currency="$currencies[$currencyId]" />
                            @else
                                <x-kasa-bakiye :milli="$milli" :currency="$currencies[$currencyId]" />
                            @endif
                        </td>
                        <td></td>
                    </tr>
                @endforeach

                @forelse ($ledger['rows'] as $row)
                    <tr>
                        <td class="whitespace-nowrap text-stone-600">{{ $row->date->format('d.m.Y H:i:s') }}</td>
                        <td><span class="badge {{ $row->type->badgeClass() }}">{{ $row->type->label() }}</span></td>
                        <td class="text-stone-600">
                            @if ($isCari)
                                {{ $row->cashRegister?->name ?? '—' }}
                            @elseif ($row->account)
                                <a href="{{ route('accounts.show', $row->account) }}" class="hover:text-gold-700">{{ $row->account->name }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="max-w-72 text-stone-600">
                            <div class="truncate" title="{{ $row->description }}">{{ $row->description ?: '—' }}</div>
                            @if ($row->document_no)
                                <div class="text-xs text-stone-400">Belge: {{ $row->document_no }}</div>
                            @endif
                        </td>
                        <td class="text-right tabular-nums whitespace-nowrap">
                            @if ($row->effect_milli > 0) {{ Amount::formatMilli($row->effect_milli, $row->currency) }} @endif
                        </td>
                        <td class="text-right tabular-nums whitespace-nowrap">
                            @if ($row->effect_milli < 0) {{ Amount::formatMilli(-$row->effect_milli, $row->currency) }} @endif
                        </td>
                        <td class="text-right">
                            @if ($isCari)
                                <x-cari-bakiye :milli="$row->running_milli" :currency="$row->currency" />
                            @else
                                <x-kasa-bakiye :milli="$row->running_milli" :currency="$row->currency" />
                            @endif
                        </td>
                        <td class="text-right">
                            <a href="{{ route('transactions.edit', $row) }}" class="rounded p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" title="Düzenle" aria-label="Düzenle">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="py-12 text-center text-stone-500">
                            {{ $filtered ? 'Bu tarih aralığında hareket yok.' : 'Henüz hareket yok.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>

            @if (array_filter($ledger['closing']))
                <tfoot>
                    @foreach ($currencies as $currencyId => $currency)
                        @php $milli = $ledger['closing'][$currencyId] ?? 0; @endphp
                        @continue($milli === 0)
                        <tr class="bg-stone-50">
                            <td colspan="6" class="px-4 py-2.5 text-right text-sm font-semibold text-stone-600">
                                {{ $filtered && request('bitis') ? \Illuminate\Support\Carbon::parse(request('bitis'))->format('d.m.Y').' itibarıyla' : 'Güncel' }} bakiye ({{ $currency->code }})
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                @if ($isCari)
                                    <x-cari-bakiye :milli="$milli" :currency="$currency" />
                                @else
                                    <x-kasa-bakiye :milli="$milli" :currency="$currency" />
                                @endif
                            </td>
                            <td></td>
                        </tr>
                    @endforeach
                </tfoot>
            @endif
        </table>
    </div>
</div>
