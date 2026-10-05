<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan sistem berbentuk kunci -> nilai JSON (pemeliharaan, OTP, penyimpanan, backup, dst.).
        Schema::create('pengaturan', function (Blueprint $table) {
            $table->string('kunci', 100)->primary();
            $table->json('nilai')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan');
    }
};
