<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan manual hadiah yang diterima pemilik (uang tidak lewat undangyah, hanya pencatatan).
        Schema::create('hadiah_masuk', function (Blueprint $table) {
            $table->id();
            $table->foreignId('undangan_id')->constrained('undangan')->cascadeOnDelete();
            $table->string('nama', 80);
            $table->string('via', 40);
            $table->unsignedBigInteger('nominal');
            $table->boolean('terkonfirmasi')->default(false);
            $table->string('pesan', 200)->nullable();
            $table->timestamps();

            $table->index(['undangan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hadiah_masuk');
    }
};
