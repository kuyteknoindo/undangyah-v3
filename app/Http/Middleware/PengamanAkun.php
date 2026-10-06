<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\SesiImpersonasi;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dua tugas pada setiap permintaan web:
 *  1. Akun yang ditangguhkan admin dikeluarkan seketika (juga yang sedang login atau memakai cookie "ingat saya").
 *  2. Selama admin "masuk sebagai" pengguna: batas waktu, blokir aksi akun/pembelian, dan banner tombol kembali.
 */
class PengamanAkun
{
    /** Komponen Livewire yang tidak boleh dijalankan saat "masuk sebagai" (ubah sandi/email/profil, beli paket). */
    private const KOMPONEN_TERBLOKIR = ['akun.', 'auth.', 'checkout', 'konfirmasi-pembayaran', 'paket-billing'];

    private const RUTE_BEBAS = ['impersonasi.akhiri', 'keluar'];

    public function __construct(private SesiImpersonasi $impersonasi) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->ditangguhkan_at) {
            return $this->keluarkan($request);
        }

        $imp = $this->impersonasi->data();
        if ($user && $imp) {
            if ($this->impersonasi->sisaDetik() <= 0) {
                $this->impersonasi->akhiri('kedaluwarsa');

                return $this->arahkan($request, 'Sesi "masuk sebagai" habis (maksimal '.SesiImpersonasi::MENIT_MAKS.' menit). Kamu kembali ke akun admin.', true);
            }
            if (! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) && ! $this->diizinkan($request)) {
                abort(403, 'Aksi ini dinonaktifkan selama sesi "masuk sebagai". Kembali ke Admin dulu.');
            }
        }

        $respons = $next($request);

        if ($user && $imp && ! $request->hasHeader('X-Livewire')) {
            $this->sisipkanBanner($respons, $user);
        }

        return $respons;
    }

    private function diizinkan(Request $request): bool
    {
        if ($request->routeIs(...self::RUTE_BEBAS)) {
            return true;
        }
        // Rute pembaruan Livewire bernama `default-livewire.update`; unggah berkas dan rute lain tetap diblokir.
        if (! $request->routeIs('*livewire.update')) {
            return false;
        }

        foreach ((array) $request->input('components', []) as $komponen) {
            $snapshot = json_decode((string) ($komponen['snapshot'] ?? ''), true);
            $nama = (string) ($snapshot['memo']['name'] ?? '');
            foreach (self::KOMPONEN_TERBLOKIR as $awalan) {
                if ($nama === '' || str_starts_with($nama, $awalan)) {
                    return false;
                }
            }
        }

        return true;
    }

    private function keluarkan(Request $request): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return $this->arahkan($request, 'Akun kamu ditangguhkan. Hubungi dukungan bila kamu merasa ini keliru.', false);
    }

    private function arahkan(Request $request, string $pesan, bool $keAdmin): Response
    {
        if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
            abort(419);
        }
        $request->session()->flash('toast', ['pesan' => $pesan, 'tipe' => 'info']);

        return redirect()->route($keAdmin && Auth::check() ? 'admin.users' : 'masuk');
    }

    private function sisipkanBanner(Response $respons, User $user): void
    {
        $tipe = (string) $respons->headers->get('Content-Type');
        $isi = $respons->getContent();
        if (! str_contains($tipe, 'text/html') || ! is_string($isi) || ! str_contains($isi, '</body>')) {
            return;
        }

        $menit = (int) ceil($this->impersonasi->sisaDetik() / 60);
        $banner = '<style>body{padding-top:44px}#menu-utama{top:44px!important;height:calc(100vh - 44px)!important}</style>'
            .'<div id="banner-impersonasi" role="status" style="position:fixed;top:0;left:0;right:0;z-index:2147483000;display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:6px 14px;min-height:44px;padding:6px 12px;background:#B54708;color:#fff;font:600 13px/18px system-ui,sans-serif">'
            .'<span>Masuk sebagai '.e($user->name).' ('.e($user->email).'). Sisa sekitar '.$menit.' menit. Aksi dicatat; ubah sandi/email dan pembelian dinonaktifkan.</span>'
            .'<form method="POST" action="'.e(route('impersonasi.akhiri')).'" style="margin:0"><input type="hidden" name="_token" value="'.e(csrf_token()).'">'
            .'<button type="submit" style="min-height:30px;padding:0 12px;border:0;border-radius:8px;background:#fff;color:#7A2E0E;font:700 13px system-ui,sans-serif;cursor:pointer">Kembali ke Admin</button></form></div>';

        $respons->setContent(preg_replace('/<\/body>/i', $banner.'</body>', $isi, 1) ?? $isi);
    }
}
