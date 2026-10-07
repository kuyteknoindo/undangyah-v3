<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lampiran tiket bantuan (maks. 3 berkas per tiket). Berkas di disk privat `local`; hanya nama yang ditampilkan ke pemilik. */
    public function up(): void
    {
        if (Schema::hasTable('tiket_lampiran')) {
            return;
        }

        Schema::create('tiket_lampiran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tiket_bantuan_id')->constrained('tiket_bantuan')->cascadeOnDelete();
            $table->string('nama', 120);
            $table->string('path', 200);
            $table->string('mime', 60);
            $table->unsignedInteger('ukuran');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiket_lampiran');
    }
};
