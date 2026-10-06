<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pustaka media: berkas di disk `media`, metadata di sini. user_id kosong = aset admin/sistem.
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('nama', 150); // nama asli (bisa diganti admin)
            $table->string('path', 200)->unique(); // relatif terhadap disk `media`, berisi nama acak
            $table->string('thumb_path', 200)->nullable();
            $table->string('tipe', 12); // gambar | audio | video | dokumen
            $table->string('mime', 100);
            $table->unsignedBigInteger('ukuran'); // byte berkas yang tersimpan
            $table->unsignedInteger('lebar')->nullable();
            $table->unsignedInteger('tinggi')->nullable();
            $table->string('folder', 30)->default('Umum');
            $table->string('alt', 300)->nullable();
            $table->boolean('dipakai')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'tipe']);
            $table->index(['tipe', 'created_at']);
            $table->index('dipakai');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
