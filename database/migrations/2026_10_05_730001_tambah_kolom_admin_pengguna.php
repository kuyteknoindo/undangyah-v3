<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penangguhan akun oleh admin dan waktu masuk terakhir (diisi listener Login).
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('ditangguhkan_at')->nullable();
            $table->string('ditangguhkan_alasan', 200)->nullable();
            $table->timestamp('login_terakhir_at')->nullable();
        });

        // Catatan aksi admin atas akun pengguna. Email disalin (snapshot) karena akun target bisa dihapus.
        Schema::create('log_aksi_admin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_email', 190);
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_email', 190)->nullable();
            $table->string('aksi', 40)->index();
            $table->json('rincian')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aksi_admin');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ditangguhkan_at', 'ditangguhkan_alasan', 'login_terakhir_at']);
        });
    }
};
