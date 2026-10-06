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
                    @if ($isCari)
                        <th class="text-center">Mutabakat</th>
                    @endif
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
                        @if ($isCari) <td></td> @endif
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
                        @if ($isCari)
                            {{-- Müşteriyle bu satıra kadar mutabık kalındığını işaretler; tarih kalıcı olarak saklanır --}}
                            <td class="text-center whitespace-nowrap">
                                @if ($row->isReconciled())
                                    <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold tracking-wide text-emerald-700 ring-1 ring-emerald-600/20"
                                          title="İşaretleyen: {{ $row->reconciler?->name ?? '—' }}">
                                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
                                        MUTABIK
                                    </span>
                                    <div class="mt-0.5 flex items-center justify-center gap-1 text-[11px] text-stone-500">
                                        {{ $row->reconciled_at->format('d.m.Y H:i') }}
                                        @if (auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('transactions.unreconcile', $row) }}" class="inline"
                                                  data-confirm="Mutabık işareti kaldırılsın mı?">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded px-0.5 text-stone-400 hover:text-red-600" title="İşareti kaldır" aria-label="Mutabık işaretini kaldır">✕</button>
                                            </form>
                                        @endif
                                    </div>
                                @else
                                    <form method="POST" action="{{ route('transactions.reconcile', $row) }}"
                                          data-confirm="Müşteriyle bu satıra kadar mutabık kalındı olarak işaretlensin mi? Bugünün tarihi kaydedilecek.">
                                        @csrf
                                        <button class="rounded-md border border-stone-300 px-2 py-0.5 text-xs font-semibold tracking-wide text-stone-500 transition hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-700">
                                            MUTABIK
                                        </button>
                                    </form>
                                @endif
                            </td>
                        @endif
                        <td class="text-right">
                            <a href="{{ route('transactions.edit', $row) }}" class="rounded p-1 text-stone-400 hover:bg-stone-100 hover:text-stone-700" title="Düzenle" aria-label="Düzenle">
                                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isCari ? 9 : 8 }}" class="py-12 text-center text-stone-500">
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
                            @if ($isCari) <td></td> @endif
                            <td></td>
                        </tr>
                    @endforeach
                </tfoot>
            @endif
        </table>
    </div>
</div>
