<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Token ber-ability terbatas (mis. finance:read untuk integrasi Keuangan) hanya boleh memanggil API integrasi.
 * Tanpa ini, token itu bisa dipakai di seluruh API staf dengan hak akun pemiliknya. Token login (abilities ['*']) tidak terpengaruh.
 */
class DenyScopedTokens
{
    public function handle(Request $request, Closure $next)
    {
        $abilities = $request->user()?->currentAccessToken()?->abilities;

        if (is_array($abilities) && ! in_array('*', $abilities, true)) {
            abort(403, 'Token ini hanya berlaku untuk API integrasi.');
        }

        return $next($request);
    }
}
