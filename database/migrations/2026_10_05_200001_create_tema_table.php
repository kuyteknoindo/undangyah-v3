<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog tema undangan (dibuat admin lewat Theme Creator, fase 8). Warna dalam hex #RRGGBB.
        Schema::create('tema', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->nullable()->unique(); // id bawaan desain: t1..t8
            $table->string('nama', 100);
            $table->string('tier', 20)->default('Gratis'); // Gratis | Premium | Deluxe
            $table->string('status', 20)->default('draft'); // publish | draft
            $table->string('warna_utama', 7);
            $table->string('warna_aksen', 7);
            $table->string('warna_permukaan', 7);
            $table->string('warna_halaman', 7);
            $table->string('warna_teks', 7);
            $table->string('font_judul', 30)->default('playfair'); // playfair | jakarta | script
            $table->json('latar_tetap')->nullable(); // bgfixed
            $table->json('dokumen')->nullable(); // dokumen Theme Creator (widget, panel, global)
            $table->unsignedInteger('versi')->default(1);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tema');
    }
};
