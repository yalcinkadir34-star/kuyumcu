@extends('layouts.app')

@section('title', $order->number)

@section('content')
    @use('App\Support\Amount')
    @use('App\Support\Workshop')
    @php $gr = new \App\Models\Currency(['symbol' => 'gr', 'decimals' => 3]); @endphp

    <x-page-header :title="$order->number.' · '.$order->product" :back="route('work-orders.index')">
        <x-slot:subtitle>
            <a href="{{ route('accounts.show', $order->account) }}" class="font-medium text-stone-700 hover:text-gold-700">{{ $order->account->name }}</a>
            · Kaydeden: {{ $order->creator?->name ?? '—' }}
        </x-slot:subtitle>
        <x-slot:actions>
            @if ($order->isDelivered())
                <span class="badge bg-emerald-50 px-3 py-1 text-sm text-emerald-700 ring-emerald-600/20">Teslim edildi</span>
            @else
                <span class="badge bg-amber-50 px-3 py-1 text-sm text-amber-800 ring-amber-600/20">Atölyede</span>
            @endif
            <a href="{{ route('work-orders.edit', $order) }}" class="btn btn-secondary">Girişi Düzenle</a>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Giriş --}}
        <div class="card">
            <div class="card-header">
                <h3 class="font-semibold text-stone-900">Giriş</h3>
                <span class="text-sm text-stone-500">{{ $order->received_at->format('d.m.Y') }}</span>
            </div>
            <dl class="grid grid-cols-3 divide-x divide-stone-100">
                <div class="p-5">
                    <dt class="text-xs font-medium text-stone-500">Brüt gram</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Amount::format($order->gross_in, $gr) }}</dd>
                </div>
                <div class="p-5">
                    <dt class="text-xs font-medium text-stone-500">Milyem</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Workshop::formatPurity($order->purity) }}</dd>
                </div>
                <div class="bg-gold-50/60 p-5">
                    <dt class="text-xs font-medium text-gold-800">Has karşılığı</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-gold-900">{{ Amount::format($order->has_in, $gr) }}</dd>
                </div>
            </dl>
            @if ($order->notes)
                <div class="border-t border-stone-100 px-5 py-3 text-sm whitespace-pre-line text-stone-600">{{ $order->notes }}</div>
            @endif
        </div>

        {{-- Çıkış sonucu --}}
        @if ($order->isDelivered())
            <div class="card">
                <div class="card-header">
                    <h3 class="font-semibold text-stone-900">Çıkış</h3>
                    <span class="text-sm text-stone-500">{{ $order->delivered_at->format('d.m.Y') }}</span>
                </div>
                <dl class="grid grid-cols-3 divide-x divide-stone-100">
                    <div class="p-5">
                        <dt class="text-xs font-medium text-stone-500">Net gram</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Amount::format($order->gross_out, $gr) }}</dd>
                        <dd class="mt-0.5 text-xs text-stone-500">{{ Amount::format($order->has_out, $gr) }} has</dd>
                    </div>
                    <div class="bg-red-50/50 p-5">
                        <dt class="text-xs font-medium text-red-700">Fire</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums text-red-700">{{ Amount::format($order->fire_gram, $gr) }}</dd>
                        <dd class="mt-0.5 text-xs text-red-600">%{{ number_format($order->fireRate(), 2, ',', '.') }} · {{ Amount::format($order->fire_has, $gr) }} has</dd>
                    </div>
                    <div class="p-5">
                        <dt class="text-xs font-medium text-stone-500">İşçilik</dt>
                        <dd class="mt-1 text-xl font-semibold tabular-nums">{{ Amount::format($order->labor_total, $order->laborCurrency) }}</dd>
                        <dd class="mt-0.5 text-xs text-stone-500">
                            @if ($order->labor_basis === 'gram')
                                Gram başı {{ Amount::format($order->labor_rate, $order->laborCurrency) }}
                            @else
                                Toplam tutar
                            @endif
                        </dd>
                    </div>
                </dl>
                <div class="border-t border-stone-100 px-5 py-3 text-sm text-stone-600">
                    @if ($order->transaction)
                        İşçilik <a href="{{ route('accounts.show', $order->account) }}" class="font-medium text-gold-700 hover:underline">{{ $order->account->name }}</a> carisine borç olarak işlendi.
                    @else
                        İşçilik tutarı olmadığı için cariye kayıt yapılmadı.
                    @endif
                </div>
            </div>
        @endif

        {{-- Teslim formu (atölyedeyse açık, teslim edildiyse düzeltme için katlanır) --}}
        <div @class(['lg:col-span-2' => $order->isDelivered()])>
            <details @if (! $order->isDelivered() || $errors->any()) open @endif class="card group">
                <summary class="card-header cursor-pointer list-none">
                    <h3 class="font-semibold text-stone-900">{{ $order->isDelivered() ? 'Teslim bilgilerini düzelt' : 'Teslim Et (Çıkış)' }}</h3>
                    @if ($order->isDelivered())
                        <svg class="size-5 text-stone-400 transition group-open:rotate-180" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
                    @endif
                </summary>

                <form method="POST" action="{{ route('work-orders.deliver', $order) }}" data-deliver-form
                      data-gross-in="{{ $order->gross_in }}" data-purity="{{ $order->purity }}">
                    @csrf
                    <div class="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <label for="gross_out" class="label">Tartıdaki net gram <span class="text-red-500">*</span></label>
                            <div class="relative">
                                <input id="gross_out" name="gross_out" inputmode="decimal" autocomplete="off" required data-gross-out
                                       value="{{ old('gross_out', Amount::forInput($order->gross_out)) }}"
                                       class="input pr-10 text-right tabular-nums @error('gross_out') input-error @enderror" placeholder="160,000">
                                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-400">gr</span>
                            </div>
                            @error('gross_out') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="delivered_at" class="label">Çıkış tarihi <span class="text-red-500">*</span></label>
                            <input id="delivered_at" name="delivered_at" type="date" required
                                   value="{{ old('delivered_at', $order->delivered_at?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                                   class="input @error('delivered_at') input-error @enderror">
                            @error('delivered_at') <p class="field-error">{{ $message }}</p> @enderror
                        </div>

                        <div>
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

                        <div>
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
                    </div>

                    {{-- Canlı hesap önizlemesi --}}
                    <div class="grid grid-cols-2 gap-px border-y border-stone-200 bg-stone-200 sm:grid-cols-4">
                        <div class="bg-white p-4">
                            <div class="text-xs text-stone-500">Fire</div>
                            <div class="mt-1 font-semibold tabular-nums text-red-700"><span data-preview-fire>—</span> gr</div>
                        </div>
                        <div class="bg-white p-4">
                            <div class="text-xs text-stone-500">Fire oranı</div>
                            <div class="mt-1 font-semibold tabular-nums text-red-700" data-preview-rate>—</div>
                        </div>
                        <div class="bg-white p-4">
                            <div class="text-xs text-stone-500">Fire (has)</div>
                            <div class="mt-1 font-semibold tabular-nums"><span data-preview-fire-has>—</span> gr</div>
                        </div>
                        <div class="bg-white p-4">
                            <div class="text-xs text-stone-500">Cariye yazılacak işçilik</div>
                            <div class="mt-1 font-semibold tabular-nums text-gold-800" data-preview-labor>—</div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 px-5 py-4">
                        <button class="btn btn-gold">{{ $order->isDelivered() ? 'Güncelle' : 'Teslim Et ve Cariye İşle' }}</button>
                    </div>
                </form>
            </details>

            @if ($order->isDelivered() && auth()->user()->isAdmin())
                <form method="POST" action="{{ route('work-orders.undeliver', $order) }}" class="mt-4 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50/50 p-4"
                      data-confirm="Teslim geri alınsın mı? İşçilik cari kaydı silinecek.">
                    @csrf
                    @method('DELETE')
                    <p class="text-sm text-stone-600">Yanlışlıkla teslim edildiyse ürünü tekrar atölyeye alabilirsiniz.</p>
                    <button class="btn btn-danger">Teslimi Geri Al</button>
                </form>
            @endif
        </div>
    </div>
@endsection
