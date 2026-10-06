<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_pesan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nama', 60);
            $table->string('kanal', 10)->default('whatsapp'); // whatsapp | email
            $table->string('subjek', 120)->nullable(); // hanya kanal email
            $table->text('isi');
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('riwayat_kirim', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('undangan_id')->constrained('undangan')->cascadeOnDelete();
            $table->foreignId('tamu_id')->constrained('tamu')->cascadeOnDelete();
            $table->string('nama'); // salinan nama tamu saat dicatat
            $table->string('hp', 20)->nullable();
            $table->string('jenis', 12)->default('undangan'); // undangan | pengingat
            $table->string('kanal', 10)->default('whatsapp');
            $table->string('status', 12)->default('dibuka'); // dibuka = tautan WhatsApp dibuka pengguna (bukan konfirmasi terkirim)
            $table->timestamp('created_at')->useCurrent();

            $table->index(['undangan_id', 'created_at']);
            $table->index('user_id');
        });

        Schema::table('tamu', function (Blueprint $table) {
            $table->string('template_pesan', 40)->nullable(); // id template khusus tamu ini (b-... bawaan atau u-<id>); null = ikuti default
            $table->string('salam', 12)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tamu', function (Blueprint $table) {
            $table->dropColumn(['template_pesan', 'salam']);
        });
        Schema::dropIfExists('riwayat_kirim');
        Schema::dropIfExists('template_pesan');
    }
};
