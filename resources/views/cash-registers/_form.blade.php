@csrf

<div class="card">
    <div class="grid gap-5 p-5 sm:grid-cols-2">
        <div>
            <label for="name" class="label">Kasa adı <span class="text-red-500">*</span></label>
            <input id="name" name="name" value="{{ old('name', $register->name) }}" required autofocus
                   class="input @error('name') input-error @enderror" placeholder="Merkez Kasa, Ziraat Bankası…">
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="type" class="label">Tür <span class="text-red-500">*</span></label>
            <select id="type" name="type" class="input @error('type') input-error @enderror">
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(old('type', $register->type?->value) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
            @error('type') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <div class="sm:col-span-2">
            <label for="notes" class="label">Notlar</label>
            <textarea id="notes" name="notes" rows="2" class="input @error('notes') input-error @enderror">{{ old('notes', $register->notes) }}</textarea>
            @error('notes') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <label class="flex items-center gap-2 text-sm text-stone-700 sm:col-span-2">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $register->is_active)) class="size-4 accent-gold-600">
            Aktif
        </label>
    </div>

    <div class="flex justify-end gap-2 border-t border-stone-200 px-5 py-4">
        <a href="{{ $register->exists ? route('cash-registers.show', $register) : route('cash-registers.index') }}" class="btn btn-secondary">Vazgeç</a>
        <button class="btn btn-primary">Kaydet</button>
    </div>
</div>
