<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Artikel Pusat Bantuan. `kode` membuat seeder idempoten (tidak menimpa suntingan admin nanti).
        Schema::create('bantuan_artikel', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->nullable()->unique();
            $table->string('kategori', 20);
            $table->string('judul', 200);
            $table->text('isi');
            $table->string('rute', 60)->nullable();
            $table->string('rute_label', 80)->nullable();
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('publik')->default(true);
            $table->timestamps();

            $table->index(['publik', 'urutan']);
        });

        // Satu suara per pengguna per artikel ("Apakah ini membantu?").
        Schema::create('bantuan_suara', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bantuan_artikel_id')->constrained('bantuan_artikel')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('membantu');
            $table->timestamps();

            $table->unique(['bantuan_artikel_id', 'user_id']);
        });

        Schema::create('tiket_bantuan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('undangan_id')->nullable()->constrained('undangan')->nullOnDelete();
            $table->string('kategori', 20);
            $table->string('subjek', 120);
            $table->text('pesan');
            $table->string('status', 12)->default('diproses'); // diproses | selesai
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_bantuan');
        Schema::dropIfExists('bantuan_suara');
        Schema::dropIfExists('bantuan_artikel');
    }
};
