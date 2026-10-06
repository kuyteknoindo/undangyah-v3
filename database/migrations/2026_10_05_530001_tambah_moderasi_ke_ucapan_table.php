<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ucapan', function (Blueprint $table) {
            $table->boolean('disembunyikan')->default(false)->after('kode_tamu');
            $table->boolean('disematkan')->default(false)->after('disembunyikan');
            $table->index(['undangan_id', 'disembunyikan', 'disematkan'], 'ucapan_moderasi_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ucapan', function (Blueprint $table) {
            $table->dropIndex('ucapan_moderasi_idx');
            $table->dropColumn(['disembunyikan', 'disematkan']);
        });
    }
};
