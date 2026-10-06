<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('undangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tema_id')->nullable()->constrained('tema')->nullOnDelete();
            $table->string('judul');
            $table->string('jenis', 40)->default('Pernikahan'); // Pernikahan | Ulang Tahun | ...
            $table->string('slug', 80)->nullable()->unique(); // alamat publik /{slug}
            $table->string('status', 20)->default('draf'); // draf | terbit
            $table->json('isi')->nullable(); // seluruh isian editor (peta kunci -> nilai, termasuk daftar berulang)
            $table->json('bagian')->nullable(); // urutan dan tampil/sembunyi tiap bagian
            $table->json('pengaturan')->nullable(); // pengaturan undangan (musik, RSVP, dst.)
            $table->json('terbitan')->nullable(); // salinan beku yang dilihat tamu saat terbit
            $table->timestamp('diterbitkan_at')->nullable();
            $table->boolean('ada_perubahan')->default(false); // draf berbeda dari terbitan
            $table->json('langkah')->nullable(); // progres: tema, data, tamu, terbit
            $table->date('tanggal_acara')->nullable();
            $table->string('lokasi_ringkas')->nullable();
            $table->timestamp('draf_disimpan_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('undangan');
    }
};
