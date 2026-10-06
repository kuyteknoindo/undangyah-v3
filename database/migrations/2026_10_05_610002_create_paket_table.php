<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog paket (Gratis, Premium, ...). status: aktif (dijual) | draf (tampil "Segera hadir", belum bisa dibeli) | arsip (disembunyikan).
        Schema::create('paket', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('sku', 20)->nullable();
            $table->string('nama', 60);
            $table->string('tagline', 120)->nullable();
            $table->unsignedBigInteger('harga')->default(0);
            $table->unsignedBigInteger('harga_coret')->default(0);
            $table->unsignedSmallInteger('durasi_bulan')->default(0);
            $table->unsignedSmallInteger('kuota_undangan')->default(1);
            $table->unsignedInteger('kuota_tamu')->nullable();
            $table->unsignedSmallInteger('kuota_foto')->default(3);
            $table->json('fitur')->nullable();
            $table->boolean('populer')->default(false);
            $table->string('status', 10)->default('aktif');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paket');
    }
};
