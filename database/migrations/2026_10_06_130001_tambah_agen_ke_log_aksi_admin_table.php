<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agen-pengguna ringkas melengkapi IP pada jejak audit (kolom ip sudah ada sejak tabelnya dibuat).
        if (! Schema::hasColumn('log_aksi_admin', 'agen')) {
            Schema::table('log_aksi_admin', function (Blueprint $table) {
                $table->string('agen', 160)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('log_aksi_admin', 'agen')) {
            Schema::table('log_aksi_admin', function (Blueprint $table) {
                $table->dropColumn('agen');
            });
        }
    }
};
