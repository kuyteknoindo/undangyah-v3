<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat backup. Berkas hasilnya ada di storage/app/backups (privat); kolom `berkas` hanya nama file buatan sistem.
        Schema::create('backup', function (Blueprint $table) {
            $table->id();
            $table->string('tipe', 10)->index(); // database | file | penuh
            $table->string('nama', 80); // label tampilan
            $table->string('berkas', 120)->nullable();
            $table->string('format', 20)->nullable(); // pgdump-custom | sqlite-gz | tar-gz | tar
            $table->unsignedBigInteger('ukuran')->default(0);
            $table->string('checksum', 64)->nullable(); // SHA-256
            $table->string('status', 10)->default('berjalan'); // berjalan | sukses | gagal
            $table->string('sumber', 10)->default('manual'); // manual | jadwal
            $table->text('pesan_galat')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_at')->nullable();
            $table->json('verifikasi')->nullable();
            $table->timestamp('uji_pulih_at')->nullable();
            $table->json('uji_pulih')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup');
    }
};
