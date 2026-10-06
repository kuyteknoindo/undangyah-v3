<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->unique();
            $table->string('jenis', 10)->default('persen'); // persen | nominal
            $table->unsignedBigInteger('nilai');
            $table->unsignedBigInteger('maks_diskon')->nullable();
            $table->unsignedBigInteger('min_belanja')->default(0);
            $table->unsignedInteger('batas_pakai')->nullable(); // null = tanpa batas
            $table->unsignedInteger('terpakai')->default(0);
            $table->timestamp('berlaku_dari')->nullable();
            $table->timestamp('berlaku_sampai')->nullable();
            $table->json('paket_berlaku')->nullable(); // daftar kode paket; null = semua paket
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voucher');
    }
};
