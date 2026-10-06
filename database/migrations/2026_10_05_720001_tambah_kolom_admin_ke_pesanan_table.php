<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            // ditunda_at: penanda "Pending" admin pada bukti yang belum bisa diputuskan. refund_at: waktu penandaan refund.
            // catatan_internal: catatan bebas admin, tidak pernah ditampilkan ke pembeli (catatan_admin bisa tampil saat bukti ditolak).
            $table->timestamp('refund_at')->nullable()->after('dibatalkan_at');
            $table->timestamp('ditunda_at')->nullable()->after('refund_at');
            $table->text('catatan_internal')->nullable()->after('catatan_admin');
        });
    }

    public function down(): void
    {
        Schema::table('pesanan', function (Blueprint $table) {
            $table->dropColumn(['refund_at', 'ditunda_at', 'catatan_internal']);
        });
    }
};
