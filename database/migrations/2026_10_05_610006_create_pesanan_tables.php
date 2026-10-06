<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Uang selalu bilangan bulat rupiah. Voucher/metode disalin (snapshot) agar pesanan lama tetap utuh bila katalog berubah.
        Schema::create('pesanan', function (Blueprint $table) {
            $table->id();
            $table->string('nomor', 20)->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('diskon')->default(0);
            $table->unsignedBigInteger('biaya')->default(0);
            $table->unsignedSmallInteger('kode_unik')->default(0);
            $table->unsignedBigInteger('total');
            $table->foreignId('voucher_id')->nullable()->constrained('voucher')->nullOnDelete();
            $table->string('voucher_kode', 30)->nullable();
            $table->boolean('voucher_ditahan')->default(false);
            $table->foreignId('metode_id')->nullable()->constrained('metode_pembayaran')->nullOnDelete();
            $table->string('metode_kode', 30)->nullable();
            $table->string('metode_label', 80)->nullable();
            $table->json('metode_data')->nullable();
            $table->string('status', 15)->default('menunggu');
            $table->string('tanda_item', 64)->nullable();
            $table->timestamp('kedaluwarsa_at')->nullable();
            $table->timestamp('dibayar_at')->nullable();
            $table->timestamp('dibatalkan_at')->nullable();
            $table->string('pembeli_nama', 80);
            $table->string('pembeli_email', 120);
            $table->string('pembeli_hp', 30)->nullable();
            $table->string('pembeli_alamat', 200)->nullable();
            $table->string('bukti_path', 160)->nullable();
            $table->string('bukti_nama', 120)->nullable();
            $table->timestamp('bukti_at')->nullable();
            $table->timestamp('bukti_ditolak_at')->nullable();
            $table->text('catatan_admin')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'kedaluwarsa_at']);
        });

        Schema::create('item_pesanan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pesanan_id')->constrained('pesanan')->cascadeOnDelete();
            $table->string('tipe', 10); // paket | addon
            $table->string('kode', 40);
            $table->string('nama', 120);
            $table->unsignedBigInteger('harga');
            $table->json('efek')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
        });

        // Kepemilikan add-on per pengguna (jumlah dibeli). Add-on tanpa multi hanya boleh dimiliki satu kali.
        Schema::create('addon_pengguna', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('addon_kode', 40);
            $table->unsignedSmallInteger('jumlah')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'addon_kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_pengguna');
        Schema::dropIfExists('item_pesanan');
        Schema::dropIfExists('pesanan');
    }
};
