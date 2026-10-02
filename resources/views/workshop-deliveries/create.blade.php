@extends('layouts.app')

@section('title', 'Atölyeden Çıkış')

@section('content')
    <div class="max-w-3xl">
        <x-page-header title="Atölyeden Çıkış" subtitle="Müşteriye teslim edilen ürünü tartıp çıkış milyemiyle kaydedin" :back="route('workshop-deliveries.index')" />

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
                    @if ($accounts->isEmpty())
                        <p class="mt-1 text-xs text-stone-500">Atölyede ürünü olan müşteri yok. Önce <a href="{{ route('work-orders.create') }}" class="font-medium text-gold-700 hover:underline">atölyeye giriş</a> yapın.</p>
                    @endif
                </div>

                <div class="sm:col-span-2">
                    <label for="product" class="label">Ürün <span class="font-normal text-stone-400">(isteğe bağlı)</span></label>
                    <input id="product" name="product" value="{{ old('product') }}" class="input @error('product') input-error @enderror" placeholder="Örn: 14 ayar zincir">
                    @error('product') <p class="field-error">{{ $message }}</p> @enderror
                </div>

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
                    <label for="purity_out" class="label">Çıkış milyemi <span class="font-normal text-stone-400">(işçilik dahil)</span> <span class="text-red-500">*</span></label>
                    <input id="purity_out" name="purity_out" inputmode="decimal" autocomplete="off" required data-purity-out
                           value="{{ old('purity_out') }}"
                           class="input text-right tabular-nums @error('purity_out') input-error @enderror" placeholder="0,625">
                    @error('purity_out') <p class="field-error">{{ $message }}</p> @enderror
                </div>

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

            {{-- Canlı hesap önizlemesi --}}
            <div class="grid grid-cols-2 gap-px border-y border-stone-200 bg-stone-200 text-sm">
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Cariden düşecek has</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums text-gold-800"><span data-preview-has>—</span> gr</div>
                </div>
                <div class="bg-white p-3">
                    <div class="text-xs text-stone-500">Müşterinin atölyede kalacak ürünü</div>
                    <div class="mt-0.5 text-lg font-semibold tabular-nums"><span data-preview-remaining>—</span> gr</div>
                </div>
            </div>

            <div class="flex justify-end gap-2 px-5 py-4">
                <a href="{{ route('workshop-deliveries.index') }}" class="btn btn-secondary">Vazgeç</a>
                <button class="btn btn-gold">Çıkışı Kaydet ve Cariye İşle</button>
            </div>
        </form>
    </div>
@endsection
