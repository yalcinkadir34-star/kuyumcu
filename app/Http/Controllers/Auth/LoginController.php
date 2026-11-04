<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionUnlocked;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
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
            'password' => ['required', 'string'],
        ], [
            'password.required' => 'Şifre zorunludur.',
        ]);

        $throttleKey = 'giris|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'password' => "Çok fazla hatalı deneme yapıldı. Lütfen {$seconds} saniye sonra tekrar deneyin.",
            ]);
        }

        // Kullanıcı adı sorulmuyor: şifresi tutan ilk aktif kullanıcı giriş yapar
        $user = User::where('is_active', true)->orderBy('id')->get()
            ->first(fn (User $user) => Hash::check($credentials['password'], $user->password));

        if (! $user) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'password' => 'Şifre hatalı.',
            ]);
        }

        // "Beni hatırla" yok: tarayıcı kapanınca oturum biter, boşta kalınca ekran kilitlenir
        Auth::login($user);

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        $request->session()->put(EnsureSessionUnlocked::LAST_ACTIVITY, now()->timestamp);

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
