<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Metode pembayaran tanpa gateway. tipe: manual (transfer ke rekening) | instruksi (teks/nomor statis, mis. e-wallet).
        // data (json): bank, nomor, atas, instruksi, langkah[], placeholder (true = data contoh, belum diisi admin).
        Schema::create('metode_pembayaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('label', 80);
            $table->string('keterangan', 160)->nullable();
            $table->string('tipe', 10)->default('manual');
            $table->unsignedBigInteger('biaya_tetap')->default(0);
            $table->decimal('biaya_persen', 5, 2)->default(0);
            $table->boolean('kode_unik')->default(false);
            $table->json('data')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metode_pembayaran');
    }
};
