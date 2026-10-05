<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanPeran
{
    public function handle(Request $request, Closure $next, string ...$peran): Response
    {
        $pengguna = $request->user();

        abort_unless($pengguna && in_array($pengguna->role, $peran, true), 403, 'Kamu tidak punya akses ke halaman ini.');

        return $next($request);
    }
}
