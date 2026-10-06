<?php

namespace App\Services;

use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Undangan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Mencatat tayangan halaman publik. IP tidak disimpan: hanya dipakai sebagai kunci cache berasin (HMAC)
 * selama jendela dedupe, jadi satu pengunjung dihitung sekali per jendela.
 */
class CatatTayangan
{
    public const JENDELA_MENIT = 30;

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

        Tayangan::create([
            'undangan_id' => $u->id,
            'tamu_id' => $tamu?->id,
            'kode_tamu' => $tamu?->kode,
        ]);

        return true;
    }
}
