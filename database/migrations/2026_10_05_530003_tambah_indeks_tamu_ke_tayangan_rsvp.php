<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Statistik mencocokkan tamu dengan tayangan/RSVP lewat tamu_id; PostgreSQL tidak mengindeks foreign key otomatis.
    public function up(): void
    {
        Schema::table('tayangan', fn (Blueprint $t) => $t->index('tamu_id', 'tayangan_tamu_id_idx'));
        Schema::table('rsvp', fn (Blueprint $t) => $t->index('tamu_id', 'rsvp_tamu_id_idx'));
    }

    public function down(): void
    {
        Schema::table('tayangan', fn (Blueprint $t) => $t->dropIndex('tayangan_tamu_id_idx'));
        Schema::table('rsvp', fn (Blueprint $t) => $t->dropIndex('rsvp_tamu_id_idx'));
    }
};
