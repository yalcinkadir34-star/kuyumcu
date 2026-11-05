<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Programda şifre yok: oturum açık değilse ilk aktif kullanıcıyla otomatik giriş yapılır.
 */
class LogInAutomatically
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            $user = User::where('is_active', true)->orderBy('id')->first();

            abort_unless($user, 503, 'Programda aktif kullanıcı yok. `php artisan db:seed` ile yönetici hesabını oluşturun.');

            Auth::login($user);
            $user->forceFill(['last_login_at' => now()])->save();
        }

        return $next($request);
    }
}
