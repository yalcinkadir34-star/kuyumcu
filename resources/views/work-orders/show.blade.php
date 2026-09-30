@extends('layouts.app')

@section('title', $order->number)

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php
        $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]);
        $remaining = $order->remainingMilli();
    @endphp

    <x-page-header :title="$order->number.' · '.$order->product" :back="route('work-orders.index')">
        <x-slot:subtitle>
            <a href="{{ route('accounts.show', $order->account) }}" class="font-medium text-stone-700 hover:text-gold-700">{{ $order->account->name }}</a>
            · Kaydeden: {{ $order->creator?->name ?? '—' }}
        </x-slot:subtitle>
        <x-slot:actions>
            @if ($order->isClosed())
                <span class="badge bg-stone-100 px-3 py-1 text-sm text-stone-600 ring-stone-300">Tamamlandı · {{ $order->closed_at->format('d.m.Y') }}</span>
            @else
                <span class="badge bg-amber-50 px-3 py-1 text-sm text-amber-800 ring-amber-600/20">Atölyede</span>
            @endif
            <a href="{{ route('work-orders.edit', $order) }}" class="btn btn-secondary">Girişi Düzenle</a>
        </x-slot:actions>
    </x-page-header>

    {{-- Özet: giriş, çıkan, kalan --}}
    <div class="card">
        <dl class="grid divide-y divide-stone-100 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
            <div class="p-5">
                <dt class="text-xs font-medium text-stone-500">Giriş · {{ $order->received_at->format('d.m.Y') }}</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Amount::format($order->gross_in, $gr) }}</dd>
                <dd class="mt-0.5 text-xs text-stone-500">Milyem {{ Workshop::formatPurity($order->purity) }}</dd>
            </div>
            <div class="bg-gold-50/60 p-5">
                <dt class="text-xs font-medium text-gold-800">Has karşılığı</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-gold-900">{{ Amount::format($order->has_in, $gr) }}</dd>
                <dd class="mt-0.5 text-xs text-gold-800">Carisine alacak yazıldı</dd>
            </div>
            <div class="p-5">
                <dt class="text-xs font-medium text-stone-500">Çıkan ({{ $order->deliveries->count() }} çıkış)</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Amount::formatMilli($order->deliveredMilli(), $gr) }}</dd>
                <dd class="mt-0.5 text-xs text-stone-500">{{ Amount::formatMilli($order->deliveries->sum(fn ($d) => Amount::toMilli($d->has_out)), $gr) }} has</dd>
            </div>
            <div @class(['p-5', 'bg-amber-50/60' => ! $order->isClosed(), 'bg-red-50/50' => $order->isClosed()])>
                <dt @class(['text-xs font-medium', 'text-amber-800' => ! $order->isClosed(), 'text-red-700' => $order->isClosed()])>
                    {{ $order->isClosed() ? 'Fire' : 'Atölyede kalan' }}
                </dt>
                <dd @class(['mt-1 text-xl font-semibold tabular-nums', 'text-amber-900' => ! $order->isClosed(), 'text-red-700' => $order->isClosed()])>
                    {{ Amount::formatMilli($remaining, $gr) }}
                </dd>
                <dd class="mt-0.5 text-xs text-stone-500">
                    %{{ number_format($order->remainingRate(), 2, ',', '.') }} · {{ Amount::formatMilli($order->remainingHasMilli(), $gr) }} has
                </dd>
            </div>
        </dl>
        @if ($order->notes)
            <div class="border-t border-stone-100 px-5 py-3 text-sm whitespace-pre-line text-stone-600">{{ $order->notes }}</div>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-5">
        {{-- Çıkışlar --}}
        <div class="card overflow-hidden lg:col-span-3">
            <div class="card-header">
                <h3 class="font-semibold text-stone-900">Çıkışlar</h3>
            </div>
            @if ($order->deliveries->isEmpty())
                <div class="px-5 py-10 text-center text-sm text-stone-500">Henüz çıkış yapılmadı.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th class="text-right">Gram</th>
                                <th class="text-right">Has</th>
                                <th class="text-right">İşçilik</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->deliveries as $delivery)
                                <tr>
                                    <td class="whitespace-nowrap text-stone-600">
                                        {{ $delivery->delivered_at->format('d.m.Y') }}
                                        @if ($delivery->notes)
                                            <div class="text-xs text-stone-400">{{ $delivery->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right font-medium tabular-nums whitespace-nowrap">{{ Amount::format($delivery->gross_out, $gr) }}</td>
                                    <td class="text-right tabular-nums whitespace-nowrap text-gold-800">{{ Amount::format($delivery->has_out, $gr) }}</td>
                                    <td class="text-right tabular-nums whitespace-nowrap">
                                        {{ Amount::format($delivery->labor_total, $delivery->laborCurrency) }}
                                        @if ($delivery->labor_basis === 'gram')
                                            <div class="text-xs text-stone-400">gr başı {{ Amount::format($delivery->labor_rate, $delivery->laborCurrency) }}</div>
                                        @endif
                                    </td>
                                    <td class="text-right">
                                        @if (auth()->user()->isAdmin())
                                            <form method="POST" action="{{ route('work-orders.deliveries.destroy', [$order, $delivery]) }}"
                                                  data-confirm="Bu çıkış silinsin mi? Cari kayıtları da geri alınacak.">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded p-1 text-stone-400 hover:bg-red-50 hover:text-red-600" title="Çıkışı sil" aria-label="Çıkışı sil">
                                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Yeni çıkış formu --}}
        <div class="lg:col-span-2">
            @if (! $order->isClosed() && $remaining > 0)
                <form method="POST" action="{{ route('work-orders.deliver', $order) }}" class="card" data-deliver-form
                      data-remaining="{{ Amount::fromMilli($remaining) }}" data-purity="{{ $order->purity }}">
                    @csrf
                    <div class="card-header">
                        <h3 class="font-semibold text-stone-900">Yeni Çıkış</h3>
                        <span class="text-xs text-stone-500">Kalan: {{ Amount::formatMilli($remaining, $gr) }}</span>
                    </div>
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <div>
                            <label for="gross_out" class="label">Tartıdaki gram <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input id="gross_out" name="gross_out" inputmode="decimal" autocomplete="off" required data-gross-out
                                       value="{{ old('gross_out') }}"
                                       class="input pr-10 text-right tabular-nums @error('gross_out') input-error @enderror" placeholder="0,000">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-400">gr</span>
                            </div>
                            @error('gross_out') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="delivered_at" class="label">Çıkış tarihi <span class="text-red-500">*</span></label>
                            <input id="delivered_at" name="delivered_at" type="date" required
                                   value="{{ old('delivered_at', now()->format('Y-m-d')) }}"
                                   class="input @error('delivered_at') input-error @enderror">
                            @error('delivered_at') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <span class="label">İşçilik tipi</span>
                            <div class="flex rounded-lg border border-stone-300 p-0.5 text-sm">
                                @foreach (['gram' => 'Gram başı', 'toplam' => 'Toplam tutar'] as $value => $label)
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="labor_basis" value="{{ $value }}" data-labor-basis class="peer sr-only"
                                               @checked(old('labor_basis', $defaults['labor_basis']) === $value)>
                                        <span class="block rounded-md px-3 py-1.5 text-center text-stone-600 peer-checked:bg-stone-900 peer-checked:text-white">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="labor_rate" class="label"><span data-labor-label>İşçilik</span> <span class="text-red-500">*</span></label>
                            <div class="flex">
                                <input id="labor_rate" name="labor_rate" inputmode="decimal" autocomplete="off" required data-labor-rate
                                       value="{{ old('labor_rate', Amount::forInput($defaults['labor_rate'])) }}"
                                       class="input rounded-r-none text-right tabular-nums @error('labor_rate') input-error @enderror" placeholder="0">
                                <select name="labor_currency_id" data-labor-currency aria-label="İşçilik birimi" class="input w-auto rounded-l-none border-l-0 bg-stone-50">
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency->id }}" data-symbol="{{ $currency->symbol }}" data-decimals="{{ $currency->decimals }}"
                                                @selected((int) old('labor_currency_id', $defaults['labor_currency_id']) === $currency->id)>{{ $currency->code === 'HAS' ? 'Has gr' : $currency->code }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @error('labor_rate') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <label for="notes" class="label">Not</label>
                            <input id="notes" name="notes" value="{{ old('notes') }}" class="input" placeholder="İsteğe bağlı">
                        </div>
                    </div>

                    {{-- Canlı hesap önizlemesi --}}
                    <div class="grid grid-cols-3 gap-px border-y border-stone-200 bg-stone-200 text-sm">
                        <div class="bg-white p-3">
                            <div class="text-xs text-stone-500">Çıkan has</div>
                            <div class="mt-0.5 font-semibold tabular-nums text-gold-800"><span data-preview-has>—</span> gr</div>
                        </div>
                        <div class="bg-white p-3">
                            <div class="text-xs text-stone-500">Kalacak</div>
                            <div class="mt-0.5 font-semibold tabular-nums"><span data-preview-remaining>—</span> gr</div>
                        </div>
                        <div class="bg-white p-3">
                            <div class="text-xs text-stone-500">İşçilik</div>
                            <div class="mt-0.5 font-semibold tabular-nums" data-preview-labor>—</div>
                        </div>
                    </div>

                    <div class="flex justify-end px-5 py-4">
                        <button class="btn btn-gold">Çıkışı Kaydet ve Cariye İşle</button>
                    </div>
                </form>
            @endif

            {{-- Kapat / tekrar aç --}}
            @if ($order->isClosed())
                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('work-orders.reopen', $order) }}" class="card flex items-center justify-between gap-4 p-4">
                        @csrf
                        @method('DELETE')
                        <p class="text-sm text-stone-600">Fiş kapalı. Kalan miktar fire olarak raporlanıyor.</p>
                        <button class="btn btn-secondary">Tekrar Aç</button>
                    </form>
                @endif
            @else
                <form method="POST" action="{{ route('work-orders.close', $order) }}" @class(['card flex items-center justify-between gap-4 p-4', 'mt-4' => $remaining > 0])
                      data-confirm="Fiş kapatılsın mı? Atölyede kalan {{ Amount::formatMilli($remaining, $gr) }} fire olarak sayılacak.">
                    @csrf
                    <p class="text-sm text-stone-600">
                        İş bittiyse fişi kapatın. Kalan <span class="font-medium">{{ Amount::formatMilli($remaining, $gr) }}</span> fire olarak raporlanır (cariye işlenmez).
                    </p>
                    <button class="btn btn-secondary">Fişi Kapat</button>
                </form>
            @endif
        </div>
    </div>
@endsection
