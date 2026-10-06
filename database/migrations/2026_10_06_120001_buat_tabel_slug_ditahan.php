<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Slug lama (setelah diganti atau undangan dihapus) ditahan 90 hari. Satu baris per slug (unik): menahan ulang
     * slug yang sama memperbarui baris. `user_id` dan `undangan_id` sengaja TANPA foreign key: penahanan harus
     * bertahan walau akun pemilik atau undangannya sudah terhapus.
     */
    public function up(): void
    {
        if (Schema::hasTable('slug_ditahan')) {
            return;
        }

        Schema::create('slug_ditahan', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 60)->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('undangan_id')->nullable()->index();
            $table->timestamp('ditahan_sampai')->index();
            $table->string('alasan', 10); // ganti | hapus
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slug_ditahan');
    }
};
