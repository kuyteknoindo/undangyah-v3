<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Salinan tiap versi tema (Theme Creator > Riwayat versi). Hanya kolom tervalidasi, bukan HTML/CSS bebas.
        Schema::create('riwayat_tema', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tema_id')->constrained('tema')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('label', 60)->nullable();
            $table->json('data');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tema_id', 'versi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_tema');
    }
};
