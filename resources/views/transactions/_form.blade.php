@csrf
@use('App\Support\Amount')

<div class="card">
    <div class="grid gap-5 p-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="type" class="label">İşlem türü <span class="text-red-500">*</span></label>
            <select id="type" name="type" data-transaction-type class="input @error('type') input-error @enderror">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}"
                            data-account="{{ $type->requiresAccount() ? 1 : 0 }}"
                            data-register="{{ $type->requiresCashRegister() ? 1 : 0 }}"
                            data-hint="{{ $type->hint() }}"
                            @selected(old('type', $transaction->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            <p class="mt-1.5 text-xs text-stone-500" data-type-hint>{{ $transaction->type?->hint() }}</p>
            @error('type') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div data-field="account" class="sm:col-span-2">
            <label for="account_id" class="label">Cari <span class="text-red-500">*</span></label>
            <select id="account_id" name="account_id" class="input @error('account_id') input-error @enderror">
                <option value="">Cari seçin…</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((int) old('account_id', $transaction->account_id) === $account->id)>{{ $account->name }} ({{ $account->code }})</option>
                @endforeach
            </select>
            @error('account_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div data-field="register" class="sm:col-span-2">
            <label for="cash_register_id" class="label">Kasa <span class="text-red-500">*</span></label>
            <select id="cash_register_id" name="cash_register_id" class="input @error('cash_register_id') input-error @enderror">
                <option value="">Kasa seçin…</option>
                @foreach ($registers as $register)
                    <option value="{{ $register->id }}" @selected((int) old('cash_register_id', $transaction->cash_register_id) === $register->id)>{{ $register->name }}</option>
                @endforeach
            </select>
            @error('cash_register_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="amount" class="label">Tutar / Miktar <span class="text-red-500">*</span></label>
            <div class="flex">
                <input id="amount" name="amount" inputmode="decimal" autocomplete="off" required
                       value="{{ old('amount', Amount::forInput($transaction->amount)) }}"
                       class="input rounded-r-none text-right tabular-nums @error('amount') input-error @enderror" placeholder="0,00">
                <select name="currency_id" aria-label="Birim" class="input w-auto rounded-l-none border-l-0 bg-stone-50 @error('currency_id') input-error @enderror">
                    @foreach ($currencies as $currency)
                        <option value="{{ $currency->id }}" @selected((int) old('currency_id', $transaction->currency_id) === $currency->id)>{{ $currency->code === 'HAS' ? 'Has gr' : $currency->code }}</option>
                    @endforeach
                </select>
            </div>
            <p class="mt-1 text-xs text-stone-500">Ondalık için virgül: 1.250,50 · Has altın: 12,345 gr</p>
            @error('amount') <p class="field-error">{{ $message }}</p> @enderror
            @error('currency_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="date" class="label">Tarih ve saat <span class="text-red-500">*</span></label>
            <input id="date" name="date" type="datetime-local" step="1" required value="{{ old('date', $transaction->date?->format('Y-m-d\TH:i:s')) }}"
                   class="input @error('date') input-error @enderror">
            @error('date') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2 grid gap-5 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label for="description" class="label">Açıklama</label>
                <input id="description" name="description" value="{{ old('description', $transaction->description) }}"
                       class="input @error('description') input-error @enderror" placeholder="Örn: 22 ayar bilezik işçiliği">
                @error('description') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="document_no" class="label">Belge no</label>
                <input id="document_no" name="document_no" value="{{ old('document_no', $transaction->document_no) }}"
                       class="input @error('document_no') input-error @enderror" placeholder="Fiş / fatura no">
                @error('document_no') <p class="field-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-stone-200 px-5 py-4">
        <a href="{{ $back }}" class="btn btn-secondary">Vazgeç</a>
        @unless ($transaction->exists)
            <button name="yeni" value="1" class="btn btn-secondary">Kaydet ve Yeni</button>
        @endunless
        <button class="btn btn-primary">Kaydet</button>
    </div>
</div>
