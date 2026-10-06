<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tamu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('undangan_id')->constrained('undangan')->cascadeOnDelete();
            $table->string('nama');
            $table->string('hp', 20)->nullable();
            $table->string('grup', 60)->nullable();
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->string('kode', 24)->unique(); // token tautan pribadi (?to=)
            $table->timestamp('dikirim_at')->nullable();
            $table->timestamps();

            $table->index('undangan_id');
        });

        Schema::create('rsvp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('undangan_id')->constrained('undangan')->cascadeOnDelete();
            $table->foreignId('tamu_id')->nullable()->constrained('tamu')->nullOnDelete();
            $table->string('nama');
            $table->string('status', 10); // hadir | tidak
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->text('pesan')->nullable();
            $table->string('kode_tamu', 24)->nullable();
            $table->timestamps();

            $table->index(['undangan_id', 'status']);
        });

        Schema::create('ucapan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('undangan_id')->constrained('undangan')->cascadeOnDelete();
            $table->foreignId('tamu_id')->nullable()->constrained('tamu')->nullOnDelete();
            $table->string('nama');
            $table->text('pesan');
            $table->string('kode_tamu', 24)->nullable();
            $table->timestamps();

            $table->index('undangan_id');
        });

        Schema::create('tayangan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('undangan_id')->constrained('undangan')->cascadeOnDelete();
            $table->foreignId('tamu_id')->nullable()->constrained('tamu')->nullOnDelete();
            $table->string('kode_tamu', 24)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['undangan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tayangan');
        Schema::dropIfExists('ucapan');
        Schema::dropIfExists('rsvp');
        Schema::dropIfExists('tamu');
    }
};
