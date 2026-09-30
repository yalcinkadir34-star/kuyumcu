@csrf

<div class="card">
    <div class="grid gap-5 p-5 sm:grid-cols-2">
        <div class="sm:col-span-2">
            <label for="name" class="label">Cari adı / Ünvan <span class="text-red-500">*</span></label>
            <input id="name" name="name" value="{{ old('name', $account->name) }}" required autofocus
                   class="input @error('name') input-error @enderror">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="type" class="label">Tür <span class="text-red-500">*</span></label>
            <select id="type" name="type" class="input @error('type') input-error @enderror">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $account->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('type') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="contact_person" class="label">Yetkili kişi</label>
            <input id="contact_person" name="contact_person" value="{{ old('contact_person', $account->contact_person) }}"
                   class="input @error('contact_person') input-error @enderror">
            @error('contact_person') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="phone" class="label">Telefon</label>
            <input id="phone" name="phone" type="tel" value="{{ old('phone', $account->phone) }}"
                   class="input @error('phone') input-error @enderror" placeholder="0555 555 55 55">
            @error('phone') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="label">E-posta</label>
            <input id="email" name="email" type="email" value="{{ old('email', $account->email) }}"
                   class="input @error('email') input-error @enderror">
            @error('email') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="tax_office" class="label">Vergi dairesi</label>
            <input id="tax_office" name="tax_office" value="{{ old('tax_office', $account->tax_office) }}"
                   class="input @error('tax_office') input-error @enderror">
            @error('tax_office') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="tax_number" class="label">Vergi / TC kimlik no</label>
            <input id="tax_number" name="tax_number" value="{{ old('tax_number', $account->tax_number) }}"
                   class="input @error('tax_number') input-error @enderror">
            @error('tax_number') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="address" class="label">Adres</label>
            <textarea id="address" name="address" rows="2" class="input @error('address') input-error @enderror">{{ old('address', $account->address) }}</textarea>
            @error('address') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="notes" class="label">Notlar</label>
            <textarea id="notes" name="notes" rows="2" class="input @error('notes') input-error @enderror">{{ old('notes', $account->notes) }}</textarea>
            @error('notes') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-700 sm:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active)) class="size-4 accent-gold-600">
            Aktif <span class="text-stone-400">(pasif cariler yeni harekette listelenmez)</span>
        </label>
    </div>

    <div class="flex justify-end gap-2 border-t border-stone-200 px-5 py-4">
        <a href="{{ $account->exists ? route('accounts.show', $account) : route('accounts.index') }}" class="btn btn-secondary">Vazgeç</a>
        <button class="btn btn-primary">Kaydet</button>
    </div>
</div>
