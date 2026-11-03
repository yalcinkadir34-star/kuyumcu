<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureSessionUnlocked;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kilit ekranı: boşta kalınca ya da "Kilitle" tuşuyla açılır, kullanıcının şifresiyle açılır.
 */
class LockController extends Controller
{
    /** Bir dakika içinde izin verilen hatalı deneme sayısı. */
    private const MAX_ATTEMPTS = 5;

    public function show(Request $request): View
    {
        // Bu sayfaya gelmek ekranı kilitler ("Kilitle" tuşu ve boşta kalma buraya yönlendirir)
        $request->session()->put(EnsureSessionUnlocked::LOCKED, true);

        return view('auth.lock', ['user' => $request->user()]);
    }

    public function unlock(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']], ['password.required' => 'Şifre zorunludur.']);

        $throttleKey = 'kilit|'.$request->user()->id.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'password' => "Çok fazla hatalı deneme yapıldı. Lütfen {$seconds} saniye sonra tekrar deneyin.",
            ]);
        }

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['password' => 'Şifre hatalı.']);
        }

        RateLimiter::clear($throttleKey);

        $session = $request->session();
        $session->forget(EnsureSessionUnlocked::LOCKED);
        $session->put(EnsureSessionUnlocked::LAST_ACTIVITY, now()->timestamp);

        return redirect()->to($session->pull(EnsureSessionUnlocked::INTENDED, route('dashboard')));
    }
}
