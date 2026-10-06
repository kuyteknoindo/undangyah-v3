<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('tipe', 20)->default('sistem');
            $table->string('judul', 160);
            $table->text('isi');
            $table->string('tautan', 500)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('dibaca_at')->nullable();
            $table->timestamp('disembunyikan_at')->nullable();
            $table->timestamps();

            // Hitungan belum dibaca (badge lonceng) dan daftar terbaru per pengguna.
            $table->index(['user_id', 'disembunyikan_at', 'dibaca_at'], 'notifikasi_user_status_idx');
            $table->index(['user_id', 'disembunyikan_at', 'created_at'], 'notifikasi_user_terbaru_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi');
    }
};
