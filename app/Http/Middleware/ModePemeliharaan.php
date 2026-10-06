<?php

namespace App\Http\Middleware;

use App\Models\Pengaturan;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Saat pemeliharaan aktif, semua orang kecuali admin melihat halaman 503; undangan publik tetap terbuka bagi tamu (sesuai janji di halaman pemeliharaan). */
class ModePemeliharaan
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Pengaturan::ambil('pemeliharaan.aktif', false)
            || $request->user()?->isAdmin()
            || $request->routeIs('masuk', 'keluar', 'offline', 'syarat', 'privasi', 'undangan.publik*', 'livewire.*', '*livewire.update')
            || $request->is('up')) {
            return $next($request);
        }

        return response()->view('errors.pemeliharaan', ['info' => Pengaturan::ambil('pemeliharaan', [])], 503, ['Retry-After' => 300]);
    }
}
