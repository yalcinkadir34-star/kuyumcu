<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Sadece yönetici rolündeki kullanıcıların erişebildiği sayfalar. */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Bu sayfa sadece yöneticiler içindir.');

        return $next($request);
    }
}
