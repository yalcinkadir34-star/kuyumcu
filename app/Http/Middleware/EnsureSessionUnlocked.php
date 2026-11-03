<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Belirli süre hiçbir işlem yapılmazsa ekranı kilitler (config/kuyumcu.php → kilit_dakika).
 * Kilitliyken her sayfa kilit ekranına gider; şifre girilince kalınan sayfaya dönülür.
 */
class EnsureSessionUnlocked
{
    public const LAST_ACTIVITY = 'kilit.son_islem';

    public const LOCKED = 'kilit.kilitli';

    public const INTENDED = 'kilit.donus';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $last = $session->get(self::LAST_ACTIVITY);
        $limit = (int) config('kuyumcu.kilit_dakika') * 60;

        $locked = $session->get(self::LOCKED, false)
            // Eski "beni hatırla" çerezinden açılan oturum da şifre ister
            || ($last === null && Auth::viaRemember())
            || ($last !== null && now()->timestamp - $last > $limit);

        if ($locked) {
            $session->put(self::LOCKED, true);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Ekran kilitli. Sayfayı yenileyip şifrenizi girin.'], 423);
            }

            if ($request->isMethod('GET')) {
                $session->put(self::INTENDED, $request->fullUrl());
            }

            return redirect()->route('lock.show');
        }

        $session->put(self::LAST_ACTIVITY, now()->timestamp);

        return $next($request);
    }
}
