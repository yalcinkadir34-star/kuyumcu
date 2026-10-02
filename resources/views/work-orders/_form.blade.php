@csrf
@use('App\Support\Amount')
@use('App\Support\Workshop')

<div class="card" data-workorder-form data-last-purities='@json($lastPurities)'>
    <div class="grid gap-5 p-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="account_id" class="label">Firma <span class="text-red-500">*</span></label>
            <select id="account_id" name="account_id" data-account required class="input @error('account_id') input-error @enderror">
                <option value="">Firma seçin…</option>
                @foreach ($accounts as $account)
                    <option value="{{ $account->id }}" @selected((int) old('account_id', $order->account_id) === $account->id)>{{ $account->name }} ({{ $account->code }})</option>
                @endforeach
            </select>
            @error('account_id') <p class="field-error">{{ $message }}</p> @enderror
            @if ($accounts->isEmpty())
                <p class="mt-1 text-xs text-stone-500">Önce <a href="{{ route('accounts.create') }}" class="font-medium text-gold-700 hover:underline">cari ekleyin</a>.</p>
            @endif
        </div>

        <div class="sm:col-span-2">
            <label for="product" class="label">Ürün <span class="text-red-500">*</span></label>
            <input id="product" name="product" value="{{ old('product', $order->product) }}" required
                   class="input @error('product') input-error @enderror" placeholder="Örn: 14 ayar döküm bilezik, 25 adet">
            @error('product') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="gross_in" class="label">Giriş gramı (brüt) <span class="text-red-500">*</span></label>
            <div class="relative">
                <input id="gross_in" name="gross_in" inputmode="decimal" autocomplete="off" required data-gram
                       value="{{ old('gross_in', Amount::forInput($order->gross_in)) }}"
                       class="input pr-10 text-right tabular-nums @error('gross_in') input-error @enderror" placeholder="200,000">
                <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-stone-400">gr</span>
            </div>
            @error('gross_in') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label for="purity" class="label">Ayar milyemi <span class="text-red-500">*</span></label>
                <input id="purity" name="purity" inputmode="decimal" autocomplete="off" required data-purity list="purity-list"
                       value="{{ old('purity', $order->purity ? Workshop::formatPurity($order->purity) : '') }}"
                       class="input text-right tabular-nums @error('purity') input-error @enderror" placeholder="0,585">
                <datalist id="purity-list">
                    @foreach (['0,333', '0,375', '0,585', '0,750', '0,875', '0,916', '0,995'] as $p)
                        <option value="{{ $p }}">
                    @endforeach
                </datalist>
                @error('purity') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="labor_purity_in" class="label">Giriş işçiliği</label>
                <input id="labor_purity_in" name="labor_purity_in" inputmode="decimal" autocomplete="off" data-labor-in
                       value="{{ old('labor_purity_in', $order->exists ? Workshop::formatPurity($order->labor_purity_in) : '') }}"
                       class="input text-right tabular-nums @error('labor_purity_in') input-error @enderror" placeholder="0,010">
                @error('labor_purity_in') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <p class="col-span-2 -mt-1 text-xs text-stone-500" data-purity-hint></p>
        </div>

        <div class="rounded-lg bg-gold-50 px-4 py-3 sm:col-span-2">
            <div class="text-xs font-medium text-gold-800">
                Has karşılığı: gram × (ayar + işçilik) = gram × <span data-in-purity>—</span>
            </div>
            <div class="mt-1 text-2xl font-semibold tabular-nums text-gold-900"><span data-has-out>—</span> <span class="text-base font-normal">gr has</span></div>
            <div class="mt-0.5 text-xs text-gold-800">Firmanın carisine alacak yazılır</div>
        </div>

        <div>
            <label for="received_at" class="label">Giriş tarihi ve saati <span class="text-red-500">*</span></label>
            <input id="received_at" name="received_at" type="datetime-local" step="1" required
                   value="{{ old('received_at', $order->received_at?->format('Y-m-d\TH:i:s')) }}"
                   class="input @error('received_at') input-error @enderror">
            @error('received_at') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="notes" class="label">Notlar</label>
            <textarea id="notes" name="notes" rows="2" class="input @error('notes') input-error @enderror">{{ old('notes', $order->notes) }}</textarea>
            @error('notes') <p class="field-error">{{ $message }}</p> @enderror
        </div>
    </div>

    <div class="flex flex-wrap justify-end gap-2 border-t border-stone-200 px-5 py-4">
        <a href="{{ $order->exists ? route('work-orders.show', $order) : route('work-orders.index') }}" class="btn btn-secondary">Vazgeç</a>
        @unless ($order->exists)
            <button name="yeni" value="1" class="btn btn-secondary">Kaydet ve Yeni</button>
        @endunless
        <button class="btn btn-primary">Kaydet</button>
    </div>
</div>
