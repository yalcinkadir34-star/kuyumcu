<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    /** Bir dakika içinde izin verilen hatalı deneme sayısı. */
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ], [
            'username.required' => 'Kullanıcı adı zorunludur.',
            'password.required' => 'Şifre zorunludur.',
        ]);

        $throttleKey = Str::transliterate(Str::lower($credentials['username']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'username' => "Çok fazla hatalı deneme yapıldı. Lütfen {$seconds} saniye sonra tekrar deneyin.",
            ]);
        }

        $attempt = Auth::attempt(
            [...$credentials, 'is_active' => true],
            $request->boolean('remember'),
        );

        if (! $attempt) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'username' => 'Kullanıcı adı veya şifre hatalı.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $request->user()->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
