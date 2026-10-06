@csrf
@use('App\Support\Amount')
@use('App\Support\Workshop')

<div class="card" data-workorder-form data-last-purities='@json($lastPurities)' data-ayar-milyem='@json(config("kuyumcu.ayar_milyem"))'>
    <div class="grid gap-5 p-5 sm:grid-cols-2">
        <div class="sm:col-span-2" data-quick-account data-url="{{ route('accounts.quick-store') }}">
            <label for="account_id" class="label">Firma <span class="text-red-500">*</span></label>
            <div class="flex gap-2">
                <select id="account_id" name="account_id" data-account required class="input @error('account_id') input-error @enderror">
                    <option value="">Firma seçin…</option>
                    @foreach ($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('account_id', $order->account_id) === $account->id)>{{ $account->name }} ({{ $account->code }})</option>
                    @endforeach
                </select>
                <button type="button" data-quick-account-toggle class="btn btn-secondary shrink-0">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
                    Yeni Cari
                </button>
            </div>
            @error('account_id') <p class="field-error">{{ $message }}</p> @enderror

            {{-- Hızlı cari ekleme (sayfadan çıkmadan) --}}
            <div data-quick-account-panel class="mt-3 hidden rounded-lg border border-gold-200 bg-gold-50/60 p-4">
                <div class="mb-3 text-sm font-medium text-stone-800">Yeni cari ekle</div>
                <div class="grid gap-3 sm:grid-cols-[1fr_12rem_auto]">
                    <input data-quick-account-name class="input" placeholder="Cari adı / Ünvan" aria-label="Cari adı" autocomplete="off">
                    <input data-quick-account-phone type="tel" class="input" placeholder="Telefon (isteğe bağlı)" aria-label="Telefon" autocomplete="off">
                    <button type="button" data-quick-account-save class="btn btn-primary">Ekle</button>
                </div>
                <p data-quick-account-error class="field-error hidden"></p>
            </div>
            <p data-quick-account-success class="mt-1 hidden text-xs font-medium text-emerald-700"></p>
        </div>

        <div class="sm:col-span-2">
            <label for="product" class="label">Ürün <span class="text-red-500">*</span></label>
            {{-- Tıklayınca ürüne "14 ayar" yazılır ve milyem dolar (config/kuyumcu.php → ayar_milyem) --}}
            <div class="mb-2 flex flex-wrap gap-2">
                @foreach (config('kuyumcu.ayar_milyem') as $ayar => $milyem)
                    <button type="button" data-ayar-pick="{{ $ayar }}"
                            class="rounded-lg border border-stone-300 bg-white px-3 py-1.5 text-sm text-stone-700 shadow-sm transition hover:border-gold-400 hover:bg-gold-50 data-[active]:border-gold-500 data-[active]:bg-gold-500 data-[active]:text-white">
                        <span class="font-semibold">{{ $ayar }} ayar</span>
                        <span class="ml-1 tabular-nums opacity-70">{{ $milyem }}</span>
                    </button>
                @endforeach
            </div>
            <input id="product" name="product" data-product value="{{ old('product', $order->product) }}" required
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

        <div>
            <label for="purity" class="label">Milyem <span class="font-normal text-stone-400">(işçilik dahil)</span> <span class="text-red-500">*</span></label>
            <input id="purity" name="purity" inputmode="decimal" autocomplete="off" required data-purity
                   value="{{ old('purity', $order->purity ? Workshop::formatPurity($order->purity) : '') }}"
                   class="input text-right tabular-nums @error('purity') input-error @enderror" placeholder="0,595">
            @error('purity') <p class="field-error">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-stone-500" data-purity-hint></p>
        </div>

        <div class="rounded-lg bg-gold-50 px-4 py-3 sm:col-span-2">
            <div class="text-xs font-medium text-gold-800">Has karşılığı (gram × milyem)</div>
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
