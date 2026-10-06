<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Langganan aktif pengguna. `paket` = kode paket (paket.kode); `paket_sampai` null berarti tanpa batas waktu.
        Schema::table('users', function (Blueprint $table) {
            $table->string('paket', 30)->default('gratis')->after('role');
            $table->timestamp('paket_sampai')->nullable()->after('paket');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['paket', 'paket_sampai']);
        });
    }
};
