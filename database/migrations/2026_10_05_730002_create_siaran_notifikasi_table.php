<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Riwayat siaran notifikasi admin. Pengiriman berjalan per potongan; `kursor_user_id` = id pengguna terakhir yang diproses.
        Schema::create('siaran_notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipe', 20)->default('sistem');
            $table->string('judul', 60);
            $table->string('isi', 300);
            $table->string('tautan', 500)->nullable();
            $table->string('target_paket', 30)->default('semua');
            $table->string('target_peran', 20)->default('semua');
            $table->unsignedInteger('jumlah_target')->default(0);
            $table->unsignedInteger('terkirim')->default(0);
            $table->unsignedBigInteger('kursor_user_id')->default(0);
            $table->string('status', 15)->default('antre')->index();
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siaran_notifikasi');
    }
};
