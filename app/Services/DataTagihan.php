<?php

namespace App\Services;

use App\Models\Pengaturan;
use App\Models\User;
use App\Support\NomorWa;

/** Data penagihan tersimpan per pengguna (dipakai mengisi Checkout dan tertera di invoice). */
class DataTagihan
{
    /** @return array{nama:string,email:string,hp:string,alamat:string} */
    public function ambil(User $user): array
    {
        $simpan = Pengaturan::ambil('tagihan_u'.$user->id, []);
        $simpan = is_array($simpan) ? $simpan : [];
        $hpUser = $user->phone ? '0'.NomorWa::ringkas($user->phone) : '';

        return [
            'nama' => (string) ($simpan['nama'] ?? $user->name),
            'email' => (string) ($simpan['email'] ?? $user->email),
            'hp' => (string) ($simpan['hp'] ?? $hpUser),
            'alamat' => (string) ($simpan['alamat'] ?? ''),
        ];
    }

    /** @param  array{nama:string,email:string,hp?:?string,alamat?:?string}  $data */
    public function simpan(User $user, array $data): void
    {
        Pengaturan::simpan('tagihan_u'.$user->id, [
            'nama' => trim((string) $data['nama']),
            'email' => trim((string) $data['email']),
            'hp' => trim((string) ($data['hp'] ?? '')),
            'alamat' => trim((string) ($data['alamat'] ?? '')),
        ]);
    }

    /** Aturan validasi bersama (halaman Paket & Billing dan Checkout). */
    public static function aturan(string $awalan = ''): array
    {
        return [
            $awalan.'nama' => ['required', 'string', 'max:80'],
            $awalan.'email' => ['required', 'email:rfc', 'max:120'],
            $awalan.'hp' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/', function ($atribut, $nilai, $gagal) {
                $digit = strlen(preg_replace('/\D/', '', (string) $nilai) ?? '');
                if ($nilai !== null && $nilai !== '' && ($digit < 9 || $digit > 15)) {
                    $gagal('Nomor HP tidak valid. Gunakan 9 sampai 15 digit.');
                }
            }],
            $awalan.'alamat' => ['nullable', 'string', 'max:200'],
        ];
    }

    public static function pesan(string $awalan = ''): array
    {
        return [
            $awalan.'nama.required' => 'Nama wajib diisi.',
            $awalan.'nama.max' => 'Nama maksimal 80 karakter.',
            $awalan.'email.required' => 'Email wajib diisi.',
            $awalan.'email.email' => 'Masukkan email yang valid, misalnya nama@contoh.com.',
            $awalan.'email.max' => 'Email maksimal 120 karakter.',
            $awalan.'hp.regex' => 'Nomor HP tidak valid. Gunakan 9 sampai 15 digit.',
            $awalan.'hp.max' => 'Nomor HP tidak valid. Gunakan 9 sampai 15 digit.',
            $awalan.'alamat.max' => 'Alamat maksimal 200 karakter.',
        ];
    }
}
