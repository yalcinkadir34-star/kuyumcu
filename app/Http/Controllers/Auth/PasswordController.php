<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Kullanıcının kendi şifresini değiştirmesi (giriş ve kilit ekranı bu şifreyi ister).
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:4', 'confirmed'],
        ], [
            'current_password.required' => 'Mevcut şifre zorunludur.',
            'current_password.current_password' => 'Mevcut şifre hatalı.',
            'password.required' => 'Yeni şifre zorunludur.',
            'password.min' => 'Yeni şifre en az 4 karakter olmalı.',
            'password.confirmed' => 'Yeni şifre ile tekrarı aynı değil.',
        ]);

        $request->user()->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => null,
        ])->save();

        return redirect()->route('dashboard')->with('success', 'Şifreniz değiştirildi. Giriş ve kilit ekranında yeni şifreyi kullanın.');
    }
}
