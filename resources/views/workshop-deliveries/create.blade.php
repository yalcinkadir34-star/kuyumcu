@extends('layouts.app')

@section('title', 'Atölyeden Çıkış')

@section('content')
    @php
        // Hatalı form dönüşünde girilen satırlar korunur; ilk açılışta tek boş satır
        $lines = old('lines', [['product' => '', 'gross_out' => '', 'purity_out' => '']]);
    @endphp

    <div class="max-w-4xl">
        <x-page-header title="Atölyeden Çıkış" subtitle="Müşteriye teslim edilen ürünleri satır satır girin: her satırın kendi ürünü, gramı ve milyemi olabilir." :back="route('workshop-deliveries.index')" />

        <form method="POST" action="{{ route('workshop-deliveries.store') }}" class="card" data-delivery-form data-info='@json($info)'>
            @csrf
            <div class="grid gap-5 p-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="account_id" class="label">Müşteri <span class="text-red-500">*</span></label>
                    <select id="account_id" name="account_id" required data-delivery-account class="input @error('account_id') input-error @enderror">
                        <option value="">Müşteri seçin…</option>
                        @foreach ($accounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('account_id', $selected) === $account->id)>{{ $account->name }} ({{ $account->code }})</option>
                        @endforeach
                    </select>
                    @error('account_id') <p class="field-error">{{ $message }}</p> @enderror
                    <p class="mt-1 text-sm text-stone-600" data-delivery-remaining></p>
                </div>

                <div class="sm:col-span-2">
                    <span class="label">Çıkış türü <span class="text-red-500">*</span></span>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ([
                            'atolye' => ['Atölyedeki ürününden', 'Müşterinin getirdiği ürün teslim ediliyor. Ramattan düşer.'],
                            'satis' => ['Kendi ürünüm (satış)', 'Atölyenin kendi ürünü (ör. müşterinin verdiği has karşılığı). Ramatı etkilemez.'],
                        ] as $value => [$label, $hint])
                            <label class="cursor-pointer">
                                <input type="radio" name="kind" value="{{ $value }}" data-delivery-kind class="peer sr-only"
                                       @checked(old('kind', 'atolye') === $value)>
                                <span class="block h-full rounded-lg border border-stone-300 px-3 py-2 text-sm peer-checked:border-gold-500 peer-checked:bg-gold-50 peer-checked:ring-2 peer-checked:ring-gold-500/20">
                                    <span class="font-medium text-stone-900">{{ $label }}</span>
                                    <span class="block text-xs text-stone-500">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('kind') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Satırlar --}}
            <div class="border-t border-stone-200">
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Ürün <span class="font-normal normal-case text-stone-400">(isteğe bağlı)</span></th>
                                <th class="w-36 text-right">Gram *</th>
                                <th class="w-32 text-right">Milyem * <span class="font-normal normal-case text-stone-400">(işç. dahil)</span></th>
                                <th class="w-32 text-right">Has</th>
                                <th class="w-10"></th>
                            </tr>
                        </thead>
                        <tbody data-lines>
                            @foreach ($lines as $i => $line)
                                <tr data-line>
                                    <td class="text-stone-400" data-line-no>{{ $loop->iteration }}</td>
                                    <td>
                                        <input name="lines[{{ $i }}][product]" value="{{ $line['product'] ?? '' }}" data-field="product"
                                               class="input py-1.5 @error("lines.$i.product") input-error @enderror" placeholder="Örn: 14 ayar zincir">
                                    </td>
                                    <td>
                                        <input name="lines[{{ $i }}][gross_out]" value="{{ $line['gross_out'] ?? '' }}" data-field="gross_out" inputmode="decimal" autocomplete="off"
                                               class="input py-1.5 text-right tabular-nums @error("lines.$i.gross_out") input-error @enderror" placeholder="0,000">
                                        @error("lines.$i.gross_out") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td>
                                        <input name="lines[{{ $i }}][purity_out]" value="{{ $line['purity_out'] ?? '' }}" data-field="purity_out" inputmode="decimal" autocomplete="off"
                                               class="input py-1.5 text-right tabular-nums @error("lines.$i.purity_out") input-error @enderror" placeholder="0,625">
                                        @error("lines.$i.purity_out") <p class="field-error">{{ $message }}</p> @enderror
                                    </td>
                                    <td class="text-right font-medium tabular-nums text-gold-800" data-line-has>—</td>
                                    <td class="text-right">
                                        <button type="button" data-line-remove class="rounded p-1 text-stone-400 hover:bg-red-50 hover:text-red-600" title="Satırı sil" aria-label="Satırı sil">
                                            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                    <button type="button" data-line-add class="btn btn-secondary py-1.5">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                        Satır ekle
                    </button>
                    @error('lines') <p class="field-error mt-0">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Toplamlar --}}
            <div class="grid grid-cols-2 gap-px border-y border-stone-200 bg-stone-200 text-sm sm:grid-cols-3">
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Toplam gram</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums"><span data-total-gram>—</span> gr</div>
                </div>
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Cariden düşecek toplam has</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums text-gold-800"><span data-preview-has>—</span> gr</div>
                </div>
                <div class="bg-white p-3" data-preview-remaining-box>
                    <div class="text-xs text-stone-500">Müşterinin atölyede kalacak ürünü</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums"><span data-preview-remaining>—</span> gr</div>
                </div>
            </div>

            <div class="grid gap-5 p-5 sm:grid-cols-2">
                <div>
                    <label for="delivered_at" class="label">Çıkış tarihi ve saati <span class="text-red-500">*</span></label>
                    <input id="delivered_at" name="delivered_at" type="datetime-local" step="1" required
                           value="{{ old('delivered_at', now()->format('Y-m-d\TH:i:s')) }}"
                           class="input @error('delivered_at') input-error @enderror">
                    @error('delivered_at') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="notes" class="label">Not</label>
                    <input id="notes" name="notes" value="{{ old('notes') }}" class="input" placeholder="İsteğe bağlı">
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-stone-200 px-5 py-4">
                <a href="{{ route('workshop-deliveries.index') }}" class="btn btn-secondary">Vazgeç</a>
                <button class="btn btn-gold">Çıkışı Kaydet ve Cariye İşle</button>
            </div>
        </form>
    </div>
@endsection
