@extends('layouts.app')

@section('title', 'Şifre Değiştir')

@section('content')
    <div class="max-w-lg">
        <x-page-header title="Şifre Değiştir" subtitle="Giriş ekranında ve kilit ekranında bu şifre istenir." :back="route('dashboard')" />

        <form method="POST" action="{{ route('password.update') }}" class="card">
            @csrf
            @method('PUT')

            <div class="space-y-5 p-5">
                <div>
                    <label for="current_password" class="label">Mevcut şifre <span class="text-red-500">*</span></label>
                    <input id="current_password" name="current_password" type="text" data-secret spellcheck="false" autocapitalize="off" required autofocus autocomplete="off"
                           class="input @error('current_password') input-error @enderror">
                    @error('current_password') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="password" class="label">Yeni şifre <span class="text-red-500">*</span></label>
                    <input id="password" name="password" type="text" data-secret spellcheck="false" autocapitalize="off" required minlength="4" autocomplete="off"
                           class="input @error('password') input-error @enderror">
                    @error('password') <p class="field-error">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-stone-500">En az 4 karakter.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="label">Yeni şifre (tekrar) <span class="text-red-500">*</span></label>
                    <input id="password_confirmation" name="password_confirmation" type="text" data-secret spellcheck="false" autocapitalize="off" required autocomplete="off" class="input">
                </div>
            </div>

            <div class="flex justify-end gap-2 border-t border-stone-200 px-5 py-4">
                <a href="{{ route('dashboard') }}" class="btn btn-secondary">Vazgeç</a>
                <button class="btn btn-gold">Şifreyi Kaydet</button>
            </div>
        </form>
    </div>
@endsection
