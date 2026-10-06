<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // perlu_ditinjau_at: pesanan sudah lunas tetapi isinya tidak (seluruhnya) diterapkan, admin harus memutuskan (mis. refund).
        // catatan_tinjau: alasan yang ditulis sistem dan terlihat admin; selesai_ditinjau_at: kapan admin menyelesaikannya.
        Schema::table('pesanan', function (Blueprint $table) {
            if (! Schema::hasColumn('pesanan', 'perlu_ditinjau_at')) {
                $table->timestamp('perlu_ditinjau_at')->nullable()->after('ditunda_at');
            }
            if (! Schema::hasColumn('pesanan', 'catatan_tinjau')) {
                $table->text('catatan_tinjau')->nullable()->after('catatan_internal');
            }
            if (! Schema::hasColumn('pesanan', 'selesai_ditinjau_at')) {
                $table->timestamp('selesai_ditinjau_at')->nullable()->after('perlu_ditinjau_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn(['perlu_ditinjau_at', 'catatan_tinjau', 'selesai_ditinjau_at']);
        });
    }
};
