<?php

namespace App\Services;

use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Undangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mencatat tayangan halaman publik. IP tidak disimpan: hanya dipakai sebagai kunci cache berasin (HMAC)
 * selama jendela dedupe, jadi satu pengunjung dihitung sekali per jendela.
 */
class CatatTayangan
{
    public const JENDELA_MENIT = 30;

    /** Baris tayangan baru per IP per undangan per jam (jaringan bersama di lokasi acara tetap longgar). */
    public const BATAS_PER_JAM = 200;

    private const POLA_BOT = '/bot|crawl|spider|slurp|facebookexternalhit|whatsapp|telegram|preview|monitor|uptime|curl|wget|python-requests|httpclient|go-http|java\/|okhttp|libwww|scrapy|lighthouse|pingdom|skypeuripreview|embedly|discord|slack|linkedin|pinterest|vkshare|bingpreview|phantomjs/i';

    public function bot(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' || (bool) preg_match(self::POLA_BOT, $userAgent);
    }

    /** @return bool true bila baris tayangan baru dibuat */
    public function catat(Request $request, Undangan $u, ?Tamu $tamu = null): bool
    {
        if ($this->bot($request->userAgent())) {
            return false;
        }
        // Pemilik yang sedang masuk (pratinjau, cek hasil) tidak boleh menggembungkan angka.
        if ($request->user() && (int) $request->user()->id === (int) $u->user_id) {
            return false;
        }

        $sidik = hash_hmac('sha256', (string) $request->ip().'|'.(string) $request->userAgent(), (string) config('app.key'));
        $kunci = 'tayangan:'.$u->id.':'.($tamu?->id ?? 0).':'.substr($sidik, 0, 32);
        if (! Cache::add($kunci, 1, now()->addMinutes(self::JENDELA_MENIT))) {
            return false;
        }

        // Mengganti User-Agent melewati dedupe di atas; batasi pembuatan baris per IP + undangan agar tabel tidak dibanjiri.
        $batas = 'tayangan-ip:'.$u->id.':'.substr(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 0, 24);
        if (RateLimiter::tooManyAttempts($batas, self::BATAS_PER_JAM)) {
            return false;
        }
        RateLimiter::hit($batas, 3600);

        Tayangan::create([
            'undangan_id' => $u->id,
            'tamu_id' => $tamu?->id,
            'kode_tamu' => $tamu?->kode,
            // Waktu aplikasi (bukan default basis data, zona sesi bisa beda) supaya statistik per jam/hari benar.
            'created_at' => now(),
        ]);

        return true;
    }
}
