<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('panggilan', 20)->nullable();
            $table->string('bio', 160)->nullable();
            $table->string('foto_path')->nullable();
            $table->string('avatar_warna', 20)->default('biru');
            $table->string('kota', 100)->nullable();
            $table->string('bahasa', 5)->default('id');
            $table->timestamp('phone_verified_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            // Perubahan email menunggu konfirmasi lewat tautan bertanda tangan yang dikirim ke alamat baru.
            $table->string('email_baru')->nullable();
            $table->timestamp('email_baru_dikirim_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['panggilan', 'bio', 'foto_path', 'avatar_warna', 'kota', 'bahasa', 'phone_verified_at', 'password_changed_at', 'email_baru', 'email_baru_dikirim_at']);
        });
    }
};
