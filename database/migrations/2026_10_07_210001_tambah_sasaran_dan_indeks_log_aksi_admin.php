<?php

use App\Models\LogAksiAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Halaman Log Audit: kolom `sasaran` (ringkasan "apa yang dikenai aksi", bisa dicari) dan indeks untuk urut terbaru,
 * filter per admin, dan daftar admin. Idempoten; aman di SQLite dan PostgreSQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('log_aksi_admin', 'sasaran')) {
            Schema::table('log_aksi_admin', function (Blueprint $table) {
                $table->string('sasaran', 190)->nullable()->after('target_email');
            });
        }

        if (! Schema::hasIndex('log_aksi_admin', ['created_at'])) {
            Schema::table('log_aksi_admin', fn (Blueprint $table) => $table->index('created_at'));
        }
        if (! Schema::hasIndex('log_aksi_admin', ['admin_id', 'created_at'])) {
            Schema::table('log_aksi_admin', fn (Blueprint $table) => $table->index(['admin_id', 'created_at'], 'log_aksi_admin_admin_waktu_idx'));
        }
        if (! Schema::hasIndex('log_aksi_admin', ['admin_email', 'created_at'])) {
            Schema::table('log_aksi_admin', fn (Blueprint $table) => $table->index(['admin_email', 'created_at'], 'log_aksi_admin_email_waktu_idx'));
        }

        // Isi `sasaran` untuk baris lama dengan aturan yang sama seperti LogAksiAdmin::catat.
        DB::table('log_aksi_admin')->whereNull('sasaran')->orderBy('id')->select(['id', 'aksi', 'target_email', 'rincian'])->chunkById(500, function ($baris) {
            foreach ($baris as $b) {
                $rincian = is_string($b->rincian) ? json_decode($b->rincian, true) : null;
                $sasaran = LogAksiAdmin::turunkanSasaran((string) $b->aksi, $b->target_email, is_array($rincian) ? $rincian : null);
                if ($sasaran !== null) {
                    DB::table('log_aksi_admin')->where('id', $b->id)->update(['sasaran' => $sasaran]);
                }
            }
        });
    }

    public function down(): void
    {
        foreach (['log_aksi_admin_admin_waktu_idx', 'log_aksi_admin_email_waktu_idx'] as $nama) {
            if (Schema::hasIndex('log_aksi_admin', $nama)) {
                Schema::table('log_aksi_admin', fn (Blueprint $table) => $table->dropIndex($nama));
            }
        }
        if (Schema::hasColumn('log_aksi_admin', 'sasaran')) {
            Schema::table('log_aksi_admin', fn (Blueprint $table) => $table->dropColumn('sasaran'));
        }
    }
};
