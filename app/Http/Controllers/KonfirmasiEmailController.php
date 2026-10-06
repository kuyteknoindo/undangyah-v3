<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** Membuka tautan bertanda tangan dari email baru: email akun berganti bila permintaan masih sah. */
class KonfirmasiEmailController extends Controller
{
    public function __invoke(Request $request, int $id): RedirectResponse
    {
        $user = User::find($id);
        $baru = $user?->email_baru;

        // Sah bila: permintaan belum dibatalkan/dipakai, dan tautan memang untuk alamat yang sedang menunggu.
        $sah = $user && $baru && hash_equals(sha1(mb_strtolower($baru)), (string) $request->query('h'));

        if (! $sah) {
            return $this->selesai($user, 'Tautan konfirmasi sudah tidak berlaku. Minta tautan baru dari halaman Informasi Akun.', 'galat');
        }

        if (User::where('email', $baru)->where('id', '!=', $user->id)->exists()) {
            $user->forceFill(['email_baru' => null, 'email_baru_dikirim_at' => null])->save();

            return $this->selesai($user, 'Email itu sudah dipakai akun lain, perubahan dibatalkan.', 'galat');
        }

        $user->forceFill([
            'email' => $baru,
            'email_verified_at' => now(),
            'email_baru' => null,
            'email_baru_dikirim_at' => null,
        ])->save();

        return $this->selesai($user, 'Email berhasil diubah menjadi '.$baru.'.', 'sukses');
    }

    private function selesai(?User $user, string $pesan, string $tipe): RedirectResponse
    {
        $tujuan = match (true) {
            ! Auth::check() => route('masuk'),
            Auth::id() === $user?->id => route('akun.informasi'),
            default => route('beranda'),
        };

        return redirect($tujuan)->with('toast', ['pesan' => $pesan, 'tipe' => $tipe]);
    }
}
