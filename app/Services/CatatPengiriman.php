<?php

namespace App\Services;

use App\Models\RiwayatKirim;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/** Mencatat bahwa pengguna membuka WhatsApp untuk seorang tamu (bukan konfirmasi pesan sampai). */
class CatatPengiriman
{
    public const BATAS_PER_MENIT = 120;

    /** @return ?RiwayatKirim null bila terlalu sering atau data bukan milik pengguna */
    public function catat(User $user, Undangan $undangan, Tamu $tamu, string $jenis = 'undangan'): ?RiwayatKirim
    {
        if ((int) $undangan->user_id !== (int) $user->id || (int) $tamu->undangan_id !== (int) $undangan->id) {
            return null;
        }
        $kunci = 'catat-kirim:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PER_MENIT)) {
            return null;
        }
        RateLimiter::hit($kunci, 60);

        $jenis = $jenis === 'pengingat' ? 'pengingat' : 'undangan';
        $riwayat = RiwayatKirim::create([
            'user_id' => $user->id,
            'undangan_id' => $undangan->id,
            'tamu_id' => $tamu->id,
            'nama' => mb_substr($tamu->nama, 0, 255),
            'hp' => $tamu->hp,
            'jenis' => $jenis,
            'kanal' => 'whatsapp',
            'status' => 'dibuka',
        ]);
        if ($jenis === 'undangan') {
            $tamu->forceFill(['dikirim_at' => now()])->save();
        }

        return $riwayat;
    }
}
