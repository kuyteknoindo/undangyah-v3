<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add-on. `efek` (json) dibaca saat pesanan lunas: {"kuota_undangan":1} atau {"perpanjang_bulan":3}; kosong = hanya dicatat sebagai kepemilikan.
        Schema::create('addon', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('nama', 80);
            $table->string('deskripsi', 200)->nullable();
            $table->unsignedBigInteger('harga')->default(0);
            $table->string('satuan', 30)->nullable();
            $table->boolean('multi')->default(false);
            $table->boolean('butuh_premium')->default(false);
            $table->boolean('termasuk_premium')->default(false);
            $table->json('efek')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon');
    }
};
