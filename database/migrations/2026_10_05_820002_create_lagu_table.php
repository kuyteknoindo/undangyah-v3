<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Katalog musik latar. `kode` adalah nilai yang disimpan editor di isi undangan (`musLagu`), mis. l1..l5 untuk lagu bawaan.
        Schema::create('lagu', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 20)->unique();
            $table->string('judul', 120);
            $table->string('artis', 120);
            $table->string('keterangan', 120)->nullable();
            $table->string('kategori', 40);
            $table->unsignedSmallInteger('durasi')->default(0); // detik
            $table->string('sumber', 10)->default('tautan'); // media | tautan | youtube
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->string('url', 500)->nullable(); // tautan audio langsung (sumber = tautan)
            $table->string('youtube', 500)->nullable();
            $table->string('status', 10)->default('publik'); // publik | draf
            $table->unsignedInteger('urutan')->default(0);
            $table->boolean('bawaan')->default(false);
            $table->timestamps();

            $table->index(['status', 'urutan']);
        });

        // Lagu bawaan editor (id sama dengan pilihan lama l1..l5) agar undangan yang sudah memilihnya tetap sah.
        $baris = [
            ['l1', 'Perayaan Cinta', 'Studio Nada', 'Instrumental piano', 'Instrumental', 192],
            ['l2', 'Hari Bahagia', 'Studio Nada', 'Akustik gitar', 'Akustik', 168],
            ['l3', 'Janji Setia', 'Studio Nada', 'Orkestra lembut', 'Orkestra', 220],
            ['l4', 'Senja di Pelaminan', 'Studio Nada', 'Instrumental', 'Instrumental', 245],
            ['l5', 'Langkah Baru', 'Studio Nada', 'Pop akustik', 'Pop', 202],
        ];
        foreach ($baris as $i => [$kode, $judul, $artis, $ket, $kategori, $durasi]) {
            DB::table('lagu')->insert([
                'kode' => $kode, 'judul' => $judul, 'artis' => $artis, 'keterangan' => $ket, 'kategori' => $kategori, 'durasi' => $durasi,
                'sumber' => 'tautan', 'status' => 'publik', 'urutan' => $i + 1, 'bawaan' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lagu');
    }
};
