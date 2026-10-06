<?php

namespace Tests\Feature;

use App\Livewire\Admin\DataPesanan;
use App\Models\ItemPesanan;
use App\Models\Notifikasi;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\KonfirmasiPesanan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPesananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(10, 0));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function pesanan(?User $user = null, array $atribut = [], string $paket = 'premium'): Pesanan
    {
        $user ??= User::factory()->create();
        $p = Pesanan::factory()->create($atribut + ['user_id' => $user->id, 'pembeli_nama' => 'Budi Santoso', 'pembeli_email' => 'budi@example.test', 'metode_label' => 'Transfer manual',
            'metode_data' => ['tipe' => 'manual', 'bank' => 'BANK CONTOH', 'nomor' => '123', 'atas' => 'A.N. Contoh']]);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => $paket, 'nama' => 'Paket '.ucfirst($paket), 'harga' => 249000, 'efek' => ['paket' => $paket, 'durasi_bulan' => 12]]);

        return $p;
    }

    private function bukti(Pesanan $p): Pesanan
    {
        Storage::disk('local')->put('bukti-pembayaran/x.png', 'isi');
        $p->forceFill(['bukti_path' => 'bukti-pembayaran/x.png', 'bukti_nama' => 'transfer.png', 'bukti_at' => now()])->save();

        return $p;
    }

    private function halaman(?User $admin = null)
    {
        return Livewire::actingAs($admin ?? $this->admin())->test(DataPesanan::class);
    }

    // ---- akses

    public function test_tamu_diarahkan_dan_non_admin_403(): void
    {
        $this->get('/admin/pesanan')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/pesanan')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/pesanan')->assertOk()->assertSeeLivewire(DataPesanan::class);
    }

    public function test_komponen_menolak_non_admin_di_semua_titik(): void
    {
        $p = $this->pesanan();
        Livewire::actingAs(User::factory()->create())->test(DataPesanan::class)->assertForbidden();

        // Sesi admin dicabut di tengah jalan: aksi langsung ke komponen tetap ditolak (komponen baru per aksi,
        // karena permintaan yang gagal 403 membuat komponen uji tak bisa dipakai lagi).
        $admin = $this->admin();
        foreach ([['tanya', [$p->id, 'lunas']], ['buka', [$p->id]], ['tunda', [$p->id]], ['kirimUlang', [$p->id]], ['ekspor', []]] as [$aksi, $arg]) {
            $k = Livewire::actingAs($admin)->test(DataPesanan::class);
            $this->actingAs(User::factory()->create());
            $k->call($aksi, ...$arg)->assertForbidden();
        }
        $this->assertSame('menunggu', $p->fresh()->status);
    }

    public function test_id_dan_jenis_aksi_terkunci_dari_klien(): void
    {
        $p = $this->pesanan();
        $k = $this->halaman();
        foreach (['detailId', 'aksiId', 'aksiJenis'] as $prop) {
            try {
                $k->set($prop, $prop === 'aksiJenis' ? 'lunas' : $p->id);
                $this->fail("$prop seharusnya terkunci");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
        $k->call('jalankan');
        $this->assertSame('menunggu', $p->fresh()->status);
    }

    // ---- daftar, filter, ringkasan

    public function test_daftar_menampilkan_pesanan_semua_pengguna_dan_ringkasan(): void
    {
        $a = $this->pesanan(null, ['status' => 'lunas', 'dibayar_at' => now(), 'total' => 300000]);
        $b = $this->pesanan(null, ['total' => 100000]);
        $this->pesanan(null, ['status' => 'batal']);

        $this->halaman()->assertSee($a->nomor)->assertSee($b->nomor)->assertSee('Rp 300.000')
            ->assertSee('Pesanan hari ini')->assertSee('Pendapatan lunas')->assertSee('Konversi bayar');
    }

    public function test_kpi_dan_hitungan_status_dari_sql(): void
    {
        $this->pesanan(null, ['status' => 'lunas', 'dibayar_at' => now(), 'total' => 200000]);
        $this->pesanan(null, ['status' => 'lunas', 'dibayar_at' => now()->subMonths(2), 'total' => 50000, 'created_at' => now()->subMonths(2)]);
        $this->pesanan(null, ['total' => 70000]);
        $this->pesanan(null, ['status' => 'refund', 'total' => 10000]);

        $k = $this->halaman();
        $k->assertSee('Rp 250.000')            // pendapatan lunas total
            ->assertSee('Bulan ini Rp 200.000')
            ->assertSee('Rp 70.000 belum dibayar')
            ->assertSee('3 dari 4 pesanan pernah dibayar')
            ->assertSee('75%');
    }

    public function test_filter_status_termasuk_menunggu_yang_lewat_batas_sebagai_kedaluwarsa(): void
    {
        $menunggu = $this->pesanan();
        $lewat = $this->pesanan(null, ['kedaluwarsa_at' => now()->subHour()]);
        $lunas = $this->pesanan(null, ['status' => 'lunas']);

        $this->halaman()->call('pilihStatus', 'kedaluwarsa')->assertSee($lewat->nomor)->assertDontSee($menunggu->nomor)->assertDontSee($lunas->nomor)
            ->call('pilihStatus', 'menunggu')->assertSee($menunggu->nomor)->assertDontSee($lewat->nomor)
            ->call('pilihStatus', 'lunas')->assertSee($lunas->nomor)->assertDontSee($menunggu->nomor)
            ->call('pilihStatus', 'ngawur')->assertSee($lunas->nomor)->assertSee($menunggu->nomor);
    }

    public function test_cari_nomor_nama_email_dan_item_serta_like_di_escape(): void
    {
        $a = $this->pesanan(null, ['pembeli_nama' => 'Citra Lestari', 'pembeli_email' => 'citra@example.test']);
        $b = $this->pesanan(null, ['pembeli_nama' => 'Dewi 100%', 'pembeli_email' => 'dewi@example.test'], 'deluxe');

        $k = $this->halaman();
        $k->set('cari', 'citra lest')->assertSee($a->nomor)->assertDontSee($b->nomor)
            ->set('cari', 'dewi@example')->assertSee($b->nomor)->assertDontSee($a->nomor)
            ->set('cari', strtolower($a->nomor))->assertSee($a->nomor)->assertDontSee($b->nomor)
            ->set('cari', 'Paket Deluxe')->assertSee($b->nomor)->assertDontSee($a->nomor)
            ->set('cari', '%')->assertSee($b->nomor)->assertDontSee($a->nomor)
            ->set('cari', 'tidak-ada-ini')->assertSee('Tidak ada pesanan yang cocok')
            ->call('resetFilter')->assertSee($a->nomor)->assertSee($b->nomor);
    }

    public function test_filter_metode_periode_dan_rentang(): void
    {
        $lama = $this->pesanan(null, ['created_at' => now()->subDays(40), 'metode_label' => 'QRIS']);
        $baru = $this->pesanan();

        $k = $this->halaman();
        $k->set('metode', 'QRIS')->assertSee($lama->nomor)->assertDontSee($baru->nomor)
            ->set('metode', '')->set('periode', '30')->assertSee($baru->nomor)->assertDontSee($lama->nomor)
            ->set('periode', 'today')->assertSee($baru->nomor)->assertDontSee($lama->nomor)
            ->set('periode', 'custom')->set('dari', now()->subDays(45)->format('Y-m-d'))->set('sampai', now()->subDays(35)->format('Y-m-d'))
            ->assertSee($lama->nomor)->assertDontSee($baru->nomor)
            ->set('dari', '2026-10-09')->set('sampai', '2026-10-01')->assertSee('Tanggal awal tidak boleh setelah tanggal akhir.')
            ->set('periode', 'ngawur')->assertSet('periode', '')
            ->set('dari', 'bukan-tanggal')->assertSet('dari', '');
    }

    public function test_urutan_dan_paginasi(): void
    {
        foreach (range(1, 12) as $i) {
            $this->pesanan(null, ['total' => $i * 1000, 'pembeli_nama' => 'Nama '.$i, 'created_at' => now()->subMinutes(100 - $i)]);
        }
        $k = $this->halaman();
        $k->set('urut', 'tinggi')->assertSee('Rp 12.000')->assertDontSee('Rp 1.000')->assertSee('Menampilkan 1–10 dari 12')
            ->call('ke', 2)->assertSee('Rp 1.000')->assertSee('Rp 2.000')->assertDontSee('Rp 12.000')
            ->call('ke', 99)->assertSee('Rp 1.000')
            ->set('urut', 'ngawur')->assertSet('urut', 'baru');
    }

    public function test_halaman_dibuka_dari_tautan_notifikasi_dengan_cari_terisi(): void
    {
        $a = $this->pesanan();
        $b = $this->pesanan();
        $this->actingAs($this->admin())->get('/admin/pesanan?cari='.$a->nomor)->assertOk()->assertSee($a->nomor)->assertDontSee($b->nomor);
    }

    // ---- detail

    public function test_detail_menampilkan_item_pembeli_voucher_bukti_dan_riwayat(): void
    {
        $v = Voucher::create(['kode' => 'HEMAT10', 'jenis' => 'persen', 'nilai' => 10]);
        $p = $this->bukti($this->pesanan(null, ['voucher_id' => $v->id, 'voucher_kode' => 'HEMAT10', 'diskon' => 24900, 'subtotal' => 249000, 'total' => 224100, 'pembeli_hp' => '08123']));

        $this->halaman()->call('buka', $p->id)->assertSet('dialogDetail', true)
            ->assertSee('Paket Premium')->assertSee('Budi Santoso')->assertSee('budi@example.test')->assertSee('08123')
            ->assertSee('HEMAT10')->assertSee('Rp 224.100')->assertSee('BANK CONTOH 123')->assertSee('Riwayat status')
            ->assertSee('Bukti pembayaran diunggah')->assertSee(route('pesanan.bukti', $p), false);
    }

    public function test_admin_boleh_membuka_bukti_dan_pengguna_lain_tidak(): void
    {
        $p = $this->bukti($this->pesanan());
        $this->actingAs($this->admin())->get(route('pesanan.bukti', $p))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('pesanan.bukti', $p))->assertNotFound();
    }

    public function test_catatan_admin_tersimpan_internal_dan_divalidasi(): void
    {
        $p = $this->pesanan();
        $k = $this->halaman()->call('buka', $p->id)->set('catatan', "Sudah dihubungi via WA\n<b>x</b>")->call('simpanCatatan')->assertHasNoErrors();
        $this->assertStringContainsString('Sudah dihubungi', $p->fresh()->catatan_internal);
        $k->assertDontSee('<b>x</b>', false);

        $k->set('catatan', str_repeat('a', 1001))->call('simpanCatatan')->assertHasErrors('catatan');
        $k->set('catatan', '')->call('simpanCatatan');
        $this->assertNull($p->fresh()->catatan_internal);
    }

    public function test_catatan_internal_tidak_terlihat_pembeli(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, ['catatan_internal' => 'RAHASIA-INTERNAL']);
        $this->actingAs($user)->get(route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]))->assertOk()->assertDontSee('RAHASIA-INTERNAL');
        $this->actingAs($user)->get(route('invoice', $p))->assertOk()->assertDontSee('RAHASIA-INTERNAL');
    }

    // ---- aksi status

    public function test_tandai_lunas_mengaktifkan_paket_dan_memberi_notifikasi(): void
    {
        $user = User::factory()->create();
        $p = $this->bukti($this->pesanan($user));

        $this->halaman()->call('tanya', $p->id, 'lunas')->assertSet('dialogAksi', true)->assertSee('diaktifkan selama 12 bulan')
            ->set('alasan', 'Mutasi cocok')->call('jalankan')->assertSet('dialogAksi', false)->assertDispatched('toast');

        $p->refresh();
        $user->refresh();
        $this->assertSame('lunas', $p->status);
        $this->assertSame('premium', $user->paket);
        $this->assertStringContainsString('Mutasi cocok', $p->catatan_admin);
        $this->assertTrue(Notifikasi::where('user_id', $user->id)->where('judul', 'Pembayaran diterima')->exists());
    }

    public function test_lunas_dari_daftar_pesanan_kedaluwarsa_diberi_peringatan(): void
    {
        $p = $this->pesanan(null, ['status' => 'kedaluwarsa', 'kedaluwarsa_at' => now()->subDay()]);
        $this->halaman()->call('tanya', $p->id, 'lunas')->assertSee('sudah kedaluwarsa')->call('jalankan');
        $this->assertSame('lunas', $p->fresh()->status);
    }

    public function test_lunas_dua_kali_tidak_menggandakan(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman();
        $k->call('tanya', $p->id, 'lunas');
        // Admin lain melunasi lebih dulu.
        app(KonfirmasiPesanan::class)->lunas($p);
        $sampai = $user->fresh()->paket_sampai;
        $k->call('jalankan')->assertDispatched('toast', pesan: 'Status pesanan sudah berubah. Tidak ada yang diubah.', tipe: 'galat');
        $this->assertTrue($sampai->equalTo($user->fresh()->paket_sampai));
        $this->assertSame(1, Notifikasi::where('user_id', $user->id)->where('judul', 'Pembayaran diterima')->count());
    }

    public function test_tolak_bukti_wajib_alasan_menghapus_bukti_dan_memberi_notifikasi(): void
    {
        $user = User::factory()->create();
        $p = $this->bukti($this->pesanan($user));

        $k = $this->halaman()->call('tanya', $p->id, 'tolak');
        $k->set('alasan', '')->call('jalankan')->assertHasErrors('alasan');
        $k->set('alasan', 'ab')->call('jalankan')->assertHasErrors('alasan');
        $this->assertNotNull($p->fresh()->bukti_path);

        $k->set('alasan', 'Nominal tidak sesuai')->call('jalankan')->assertHasNoErrors();
        $p->refresh();
        $this->assertNull($p->bukti_path);
        $this->assertNotNull($p->bukti_ditolak_at);
        $this->assertSame('menunggu', $p->status);
        Storage::disk('local')->assertMissing('bukti-pembayaran/x.png');
        $this->assertStringContainsString('Nominal tidak sesuai', Notifikasi::where('user_id', $user->id)->latest('id')->value('isi'));
    }

    public function test_tolak_tanpa_bukti_tidak_tersedia(): void
    {
        $p = $this->pesanan();
        $this->halaman()->call('tanya', $p->id, 'tolak')->assertSet('dialogAksi', false)->assertDispatched('toast', pesan: 'Aksi ini tidak tersedia untuk status pesanan sekarang.', tipe: 'galat');
    }

    public function test_batalkan_butuh_alasan_dan_mengembalikan_voucher(): void
    {
        $v = Voucher::create(['kode' => 'PROMO', 'jenis' => 'nominal', 'nilai' => 1000, 'terpakai' => 1]);
        $p = $this->pesanan(null, ['voucher_id' => $v->id, 'voucher_kode' => 'PROMO', 'voucher_ditahan' => true]);

        $k = $this->halaman()->call('tanya', $p->id, 'batal');
        $k->call('jalankan')->assertHasErrors('alasan');
        $k->set('alasan', 'Permintaan pembeli')->call('jalankan');
        $p->refresh();
        $this->assertSame('batal', $p->status);
        $this->assertSame('Permintaan pembeli', $p->catatan_admin);
        $this->assertSame(0, $v->fresh()->terpakai);
    }

    public function test_tandai_kedaluwarsa_manual_sebelum_batas(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $this->halaman()->call('tanya', $p->id, 'kedaluwarsa')->call('jalankan');
        $this->assertSame('kedaluwarsa', $p->fresh()->status);
        $this->assertTrue(Notifikasi::where('user_id', $user->id)->where('judul', 'Waktu pembayaran habis')->exists());
    }

    public function test_refund_hanya_dari_lunas_dan_bisa_menurunkan_paket(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman();
        $k->call('tanya', $p->id, 'refund')->assertDispatched('toast', pesan: 'Aksi ini tidak tersedia untuk status pesanan sekarang.', tipe: 'galat');

        app(KonfirmasiPesanan::class)->lunas($p);
        $this->assertSame('premium', $user->fresh()->paket);

        $k->call('tanya', $p->id, 'refund')->assertSet('turunkan', true)->assertSee('Turunkan paket ke Gratis')
            ->call('jalankan')->assertHasErrors('alasan')
            ->set('alasan', 'Pembeli minta dikembalikan')->call('jalankan')->assertHasNoErrors();

        $p->refresh();
        $user->refresh();
        $this->assertSame('refund', $p->status);
        $this->assertNotNull($p->refund_at);
        $this->assertSame('gratis', $user->paket);
        $this->assertNull($user->paket_sampai);
        $this->assertSame('gratis', $user->paketAktif());
        $this->assertTrue(Notifikasi::where('user_id', $user->id)->where('judul', 'Pesanan di-refund')->exists());
    }

    public function test_refund_tanpa_menurunkan_paket_membiarkan_paket(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        app(KonfirmasiPesanan::class)->lunas($p);

        $this->halaman()->call('tanya', $p->id, 'refund')->set('turunkan', false)->set('alasan', 'Kompensasi manual')->call('jalankan');
        $this->assertSame('refund', $p->fresh()->status);
        $this->assertSame('premium', $user->fresh()->paket);
    }

    public function test_status_final_tidak_punya_aksi(): void
    {
        $batal = $this->pesanan(null, ['status' => 'batal']);
        $refund = $this->pesanan(null, ['status' => 'refund']);
        $k = $this->halaman();
        foreach ([$batal, $refund] as $p) {
            foreach (['lunas', 'tolak', 'batal', 'kedaluwarsa', 'refund'] as $jenis) {
                $k->call('tanya', $p->id, $jenis)->assertSet('dialogAksi', false);
            }
        }
        $k->call('tanya', $batal->id, 'ngawur')->assertSet('dialogAksi', false);
        $this->assertSame('batal', $batal->fresh()->status);
        $this->assertSame('refund', $refund->fresh()->status);
    }

    public function test_pending_ditandai_dan_dilepas_serta_reset_saat_bukti_baru(): void
    {
        $p = $this->bukti($this->pesanan());
        $k = $this->halaman();
        $k->call('tunda', $p->id);
        $this->assertNotNull($p->fresh()->ditunda_at);
        $k->call('tunda', $p->id);
        $this->assertNull($p->fresh()->ditunda_at);

        $tanpaBukti = $this->pesanan();
        $k->call('tunda', $tanpaBukti->id)->assertDispatched('toast', pesan: 'Hanya pesanan menunggu yang sudah punya bukti yang bisa di-Pending.', tipe: 'galat');
        $this->assertNull($tanpaBukti->fresh()->ditunda_at);
    }

    public function test_aksi_pada_pesanan_tidak_ada_404(): void
    {
        $this->halaman()->call('tanya', 99999, 'lunas')->assertNotFound();
        $this->halaman()->call('buka', 99999)->assertNotFound();
    }

    // ---- ekspor

    public function test_ekspor_csv_aman_rumus_dan_mengikuti_filter(): void
    {
        $a = $this->pesanan(null, ['pembeli_nama' => '=HYPERLINK("http://x")', 'status' => 'lunas', 'dibayar_at' => now()]);
        $b = $this->pesanan(null, ['pembeli_nama' => 'Orang Biasa']);

        $k = $this->halaman()->call('pilihStatus', 'lunas')->call('ekspor');
        $k->assertFileDownloaded();
        $unduh = $this->halaman()->call('pilihStatus', 'lunas')->call('ekspor');
        $isi = $unduh->effects['download']['content'] ?? '';
        $csv = base64_decode($isi);
        $this->assertStringContainsString($a->nomor, $csv);
        $this->assertStringNotContainsString($b->nomor, $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    public function test_ekspor_kosong_dan_dibatasi_laju(): void
    {
        $k = $this->halaman()->call('ekspor')->assertDispatched('toast', pesan: 'Tidak ada pesanan untuk diekspor.', tipe: 'info');
        $this->pesanan();
        RateLimiter::clear('pesanan-ekspor:'.auth()->id());
        for ($i = 0; $i < 10; $i++) {
            $k->call('ekspor');
        }
        $k->call('ekspor')->assertDispatched('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');
    }

    // ---- kirim ulang

    public function test_kirim_ulang_tagihan_memberi_notifikasi_dan_dibatasi(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user);
        $k = $this->halaman()->call('kirimUlang', $p->id)->assertDispatched('toast', tipe: 'sukses');
        $n = Notifikasi::where('user_id', $user->id)->latest('id')->first();
        $this->assertSame('/konfirmasi-pembayaran/'.$p->nomor, $n->tautan);

        $k->call('kirimUlang', $p->id)->assertDispatched('toast', pesan: 'Tagihan baru saja dikirim. Coba lagi sebentar lagi.', tipe: 'info');
        $this->assertSame(1, Notifikasi::where('user_id', $user->id)->count());

        $batal = $this->pesanan($user, ['status' => 'batal']);
        $k->call('kirimUlang', $batal->id)->assertDispatched('toast', pesan: 'Pesanan batal atau refund tidak dikirim ulang.', tipe: 'galat');
    }
}
