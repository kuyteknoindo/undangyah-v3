<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL tidak mengindeks foreign key otomatis; kolom ini dipakai untuk cascade/set null, hitungan per tema,
     * dan urutan terbaru. Idempoten (aman dijalankan ulang) dan kompatibel SQLite + PostgreSQL.
     *
     * @return array<string, array{0:string, 1:list<string>}> nama indeks => [tabel, kolom]
     */
    private function daftar(): array
    {
        return [
            'rsvp_undangan_created_idx' => ['rsvp', ['undangan_id', 'created_at']],
            'ucapan_undangan_created_idx' => ['ucapan', ['undangan_id', 'created_at']],
            'ucapan_tamu_id_idx' => ['ucapan', ['tamu_id']],
            'riwayat_kirim_tamu_id_idx' => ['riwayat_kirim', ['tamu_id']],
            'undangan_tema_id_idx' => ['undangan', ['tema_id']],
            'item_pesanan_pesanan_id_idx' => ['item_pesanan', ['pesanan_id']],
            'pesanan_created_at_idx' => ['pesanan', ['created_at']],
            'pesanan_voucher_id_idx' => ['pesanan', ['voucher_id']],
            'pesanan_metode_id_idx' => ['pesanan', ['metode_id']],
            'bantuan_suara_user_id_idx' => ['bantuan_suara', ['user_id']],
            'users_login_terakhir_idx' => ['users', ['login_terakhir_at']],
        ];
    }

    public function up(): void
    {
        foreach ($this->daftar() as $nama => [$tabel, $kolom]) {
            if (Schema::hasTable($tabel) && ! Schema::hasIndex($tabel, $nama)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom, $nama));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->daftar() as $nama => [$tabel]) {
            if (Schema::hasTable($tabel) && Schema::hasIndex($tabel, $nama)) {
                Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($nama));
            }
        }
    }
};
