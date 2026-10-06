<?php

namespace Tests\Feature;

use App\Livewire\Admin\Product;
use App\Models\ItemPesanan;
use App\Models\Paket;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\Tema;
use App\Models\User;
use App\Services\HitungPesanan;
use App\Services\KuotaUndangan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

class AdminProductTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function halaman(?User $user = null)
    {
        return Livewire::actingAs($this->sudahKonfirmasiSandi($user ?? $this->admin()))->test(Product::class);
    }

    private function isi($komponen, array $f = [], array $fitur = ['Fitur satu'])
    {
        return $komponen->set('f.nama', 'Platinum')->set('f.harga', '999000')->set('f.durasi_bulan', '12')->set('f.kuota_undangan', '20')->set('f.kuota_foto', '50')
            ->set('f.status', 'aktif')->set('fitur', $fitur)
            ->tap(function ($k) use ($f) {
                foreach ($f as $kunci => $nilai) {
                    $k->set('f.'.$kunci, $nilai);
                }
            });
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/product')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/product')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/product')->assertOk()->assertSeeLivewire(Product::class);
    }

    public function test_aksi_livewire_tetap_ditolak_untuk_non_admin(): void
    {
        Livewire::actingAs(User::factory()->create())->test(Product::class)->assertForbidden();
    }

    public function test_daftar_paket_dari_database_tampil(): void
    {
        $this->halaman()->assertSee('Gratis')->assertSee('Premium')->assertSee('Deluxe')->assertSee('Rp 249.000')->assertSee('Paket sistem')->assertSee('Pratinjau halaman harga');
    }

    public function test_tambah_paket_tersimpan_sebagai_draf_dengan_kode_unik_dan_kuota_tersinkron(): void
    {
        $k = $this->isi($this->halaman()->call('bukaBaru'), ['status' => 'draf', 'sku' => 'p-plat', 'kuota_tamu' => '750'], ['Fitur satu', 'Fitur dua']);
        $k->call('simpan')->assertHasNoErrors()->assertDispatched('toast');

        $p = Paket::where('nama', 'Platinum')->firstOrFail();
        $this->assertSame('platinum', $p->kode);
        $this->assertSame('P-PLAT', $p->sku);
        $this->assertSame('draf', $p->status);
        $this->assertSame(999000, $p->harga);
        $this->assertSame(['Fitur satu', 'Fitur dua'], $p->fitur);
        $this->assertSame(4, $p->urutan);
        $this->assertSame(20, Pengaturan::ambil('paket.kuota.platinum'));
        $this->assertSame(750, Pengaturan::ambil('paket.kuota_tamu.platinum'));
        $this->assertSame(3, Pengaturan::ambil('paket.kuota.premium'));
    }

    public function test_kode_paket_tidak_bentrok_untuk_nama_serupa(): void
    {
        $this->isi($this->halaman()->call('bukaBaru'), ['nama' => 'Premium!'])->call('simpan')->assertHasNoErrors();

        $this->assertSame('premium-2', Paket::where('nama', 'Premium!')->value('kode'));
    }

    public function test_validasi_form_paket(): void
    {
        $k = $this->halaman()->call('bukaBaru')->set('f.harga', '')->call('simpan');
        $k->assertHasErrors(['f.nama', 'f.harga', 'fitur'])->assertSee('Nama paket wajib diisi.')->assertSee('Harga wajib diisi.')->assertSee('Tambahkan minimal 1 fitur.');

        $this->isi($this->halaman()->call('bukaBaru'), ['nama' => 'premium'])->call('simpan')->assertHasErrors(['f.nama']);
        $this->isi($this->halaman()->call('bukaBaru'), ['harga_coret' => '999000'])->call('simpan')->assertHasErrors(['f.harga_coret']);
        $this->isi($this->halaman()->call('bukaBaru'), ['sku' => 'x'])->call('simpan')->assertHasErrors(['f.sku']);
        $this->isi($this->halaman()->call('bukaBaru'), ['sku' => 'P-PREM'])->call('simpan')->assertHasErrors(['f.sku']);
        $this->isi($this->halaman()->call('bukaBaru'), ['kuota_undangan' => '0'])->call('simpan')->assertHasErrors(['f.kuota_undangan']);
        $this->isi($this->halaman()->call('bukaBaru'), ['durasi_bulan' => '500'])->call('simpan')->assertHasErrors(['f.durasi_bulan']);
        $this->isi($this->halaman()->call('bukaBaru'), ['tagline' => str_repeat('a', 81)])->call('simpan')->assertHasErrors(['f.tagline']);
        $this->isi($this->halaman()->call('bukaBaru'), ['status' => 'rahasia'])->call('simpan')->assertHasErrors(['f.status']);
        $this->assertSame(3, Paket::count());
    }

    public function test_edit_paket_menyinkronkan_kuota_dan_tidak_mengubah_kode(): void
    {
        $premium = Paket::where('kode', 'premium')->firstOrFail();
        $this->halaman()->call('bukaEdit', $premium->id)->assertSet('f.nama', 'Premium')
            ->set('f.kuota_undangan', '7')->set('f.kuota_tamu', '300')->set('f.nama', 'Premium Plus')->call('simpan')->assertHasNoErrors();

        $premium->refresh();
        $this->assertSame('premium', $premium->kode);
        $this->assertSame('Premium Plus', $premium->nama);
        $this->assertSame(7, Pengaturan::ambil('paket.kuota.premium'));
        $this->assertSame(300, Pengaturan::ambil('paket.kuota_tamu.premium'));
    }

    public function test_kuota_tamu_dikosongkan_berarti_tanpa_batas(): void
    {
        $premium = Paket::where('kode', 'premium')->firstOrFail();
        $premium->update(['kuota_tamu' => 100]);
        $this->halaman()->call('bukaEdit', $premium->id)->set('f.kuota_tamu', '')->call('simpan')->assertHasNoErrors();

        $this->assertNull($premium->fresh()->kuota_tamu);
        $this->assertNull(Pengaturan::ambil('paket.kuota_tamu.premium'));
    }

    public function test_paket_gratis_harus_rp_0_dan_paket_sistem_selalu_aktif(): void
    {
        $gratis = Paket::where('kode', 'gratis')->firstOrFail();
        $this->halaman()->call('bukaEdit', $gratis->id)->set('f.harga', '1000')->call('simpan')->assertHasErrors(['f.harga']);
        $this->assertSame(0, $gratis->fresh()->harga);

        $this->halaman()->call('bukaEdit', $gratis->id)->set('f.status', 'arsip')->call('simpan')->assertHasNoErrors();
        $this->assertSame('aktif', $gratis->fresh()->status);
    }

    public function test_hanya_satu_paket_populer(): void
    {
        $premium = Paket::where('kode', 'premium')->firstOrFail();
        $deluxe = Paket::where('kode', 'deluxe')->firstOrFail();
        $this->assertTrue($premium->populer);

        $this->halaman()->call('populer', $deluxe->id);
        $this->assertTrue($deluxe->fresh()->populer);
        $this->assertFalse($premium->fresh()->populer);

        $this->halaman()->call('populer', $deluxe->id);
        $this->assertFalse($deluxe->fresh()->populer);
    }

    public function test_paket_arsip_tidak_bisa_ditandai_populer_dan_kehilangan_tanda_saat_diarsipkan(): void
    {
        $deluxe = Paket::where('kode', 'deluxe')->firstOrFail();
        $deluxe->update(['populer' => true]);
        $this->halaman()->call('tanyaArsip', $deluxe->id)->assertSet('dialogKonfirmasi', true)->call('jalankanAksi');
        $this->assertSame('arsip', $deluxe->fresh()->status);
        $this->assertFalse($deluxe->fresh()->populer);

        $this->halaman()->call('populer', $deluxe->id)->assertDispatched('toast');
        $this->assertFalse($deluxe->fresh()->populer);

        $this->halaman()->call('pulihkan', $deluxe->id);
        $this->assertSame('draf', $deluxe->fresh()->status);
    }

    public function test_paket_sistem_tidak_bisa_diarsipkan_atau_dihapus(): void
    {
        $premium = Paket::where('kode', 'premium')->firstOrFail();
        $k = $this->halaman()->call('tanyaArsip', $premium->id)->assertSet('dialogKonfirmasi', false);
        $k->call('tanyaHapus', $premium->id)->assertSet('dialogKonfirmasi', false);
        $this->assertDatabaseHas('paket', ['id' => $premium->id, 'status' => 'aktif']);
    }

    public function test_hapus_paket_belum_dipakai_berhasil_dan_menyinkronkan_kuota(): void
    {
        $deluxe = Paket::where('kode', 'deluxe')->firstOrFail();
        $this->assertSame(10, Pengaturan::ambil('paket.kuota.deluxe'));

        $this->halaman()->call('tanyaHapus', $deluxe->id)->assertSet('dialogKonfirmasi', true)->call('jalankanAksi');

        $this->assertDatabaseMissing('paket', ['id' => $deluxe->id]);
        $this->assertNull(Pengaturan::ambil('paket.kuota.deluxe'));
    }

    public function test_paket_yang_dipakai_pengguna_atau_pesanan_tidak_bisa_dihapus(): void
    {
        $deluxe = Paket::where('kode', 'deluxe')->firstOrFail();
        User::factory()->create(['paket' => 'deluxe', 'paket_sampai' => now()->addMonth()]);
        $this->halaman()->call('tanyaHapus', $deluxe->id)->assertSet('dialogKonfirmasi', false)->assertDispatched('toast');
        $this->assertDatabaseHas('paket', ['id' => $deluxe->id]);

        User::where('paket', 'deluxe')->update(['paket' => 'gratis']);
        $pesanan = Pesanan::factory()->create();
        ItemPesanan::create(['pesanan_id' => $pesanan->id, 'tipe' => 'paket', 'kode' => 'deluxe', 'nama' => 'Paket Deluxe', 'harga' => 499000]);
        $this->halaman()->call('tanyaHapus', $deluxe->id)->assertSet('dialogKonfirmasi', false);
        $this->assertDatabaseHas('paket', ['id' => $deluxe->id]);

        // Arsip tetap boleh untuk paket yang dipakai.
        $this->halaman()->call('tanyaArsip', $deluxe->id)->assertSet('dialogKonfirmasi', true)->call('jalankanAksi');
        $this->assertSame('arsip', $deluxe->fresh()->status);
    }

    public function test_hapus_diblokir_walau_dialog_dibuka_lalu_paket_dipakai(): void
    {
        $deluxe = Paket::where('kode', 'deluxe')->firstOrFail();
        $k = $this->halaman()->call('tanyaHapus', $deluxe->id)->assertSet('dialogKonfirmasi', true);
        User::factory()->create(['paket' => 'deluxe', 'paket_sampai' => now()->addMonth()]);
        $k->call('jalankanAksi');

        $this->assertDatabaseHas('paket', ['id' => $deluxe->id]);
    }

    public function test_properti_id_terkunci_dari_manipulasi_klien(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman()->set('editId', 1);
    }

    public function test_geser_urutan_dan_duplikat(): void
    {
        $gratis = Paket::where('kode', 'gratis')->firstOrFail();
        $premium = Paket::where('kode', 'premium')->firstOrFail();
        $this->halaman()->call('geser', $premium->id, -1);
        $this->assertSame(1, $premium->fresh()->urutan);
        $this->assertSame(2, $gratis->fresh()->urutan);

        $this->halaman()->call('duplikat', $premium->id);
        $salinan = Paket::where('nama', 'Premium (salinan)')->firstOrFail();
        $this->assertSame('draf', $salinan->status);
        $this->assertFalse($salinan->populer);
        $this->assertNull($salinan->sku);
        $this->assertSame($premium->fitur, $salinan->fitur);
        $this->assertNotSame($premium->kode, $salinan->kode);
    }

    public function test_pencarian_dan_filter_status(): void
    {
        $this->halaman()->set('q', 'delux')->assertSeeInOrder(['Semua paket', 'Deluxe'])->set('q', 'tidak-ada-paket')->assertSee('Tidak ada paket yang cocok');
        $this->halaman()->call('atur', 'draf')->assertSet('fs', 'draf')->call('atur', 'asal')->assertSet('fs', 'semua');
    }

    public function test_paket_buatan_admin_ikut_menghitung_kuota_dan_peringkat_tema(): void
    {
        $this->isi($this->halaman()->call('bukaBaru'), ['kuota_undangan' => '20'])->call('simpan')->assertHasNoErrors();
        $kuota = app(KuotaUndangan::class);
        $pemilik = User::factory()->create(['paket' => 'platinum', 'paket_sampai' => now()->addMonth()]);
        $gratis = User::factory()->create();
        $premium = User::factory()->create(['paket' => 'premium', 'paket_sampai' => now()->addMonth()]);
        $platinum = Tema::factory()->tier('Platinum')->create();
        $deluxe = Tema::factory()->tier('Deluxe')->create();
        $premiumTema = Tema::factory()->tier('Premium')->create();

        $this->assertSame(20, $kuota->batas($pemilik));
        $this->assertSame('platinum', $kuota->paket($pemilik));
        $this->assertTrue($kuota->temaTerkunci($platinum, $gratis));
        $this->assertTrue($kuota->temaTerkunci($platinum, $premium));
        $this->assertFalse($kuota->temaTerkunci($platinum, $pemilik));
        $this->assertFalse($kuota->temaTerkunci($deluxe, $pemilik));
        $this->assertFalse($kuota->temaTerkunci($premiumTema, $pemilik));
        $this->assertTrue($kuota->temaTerkunci($premiumTema, $gratis));
        $this->assertFalse($kuota->temaTerkunci($premiumTema, $premium));
    }

    public function test_paket_aktif_baru_bisa_dibeli_dan_arsip_menyembunyikan_dari_checkout(): void
    {
        $this->isi($this->halaman()->call('bukaBaru'))->call('simpan')->assertHasNoErrors();
        $user = User::factory()->create();
        $hitung = app(HitungPesanan::class);
        $this->assertSame(999000, $hitung->hitung($user, 'platinum', [])['subtotal']);

        $p = Paket::where('kode', 'platinum')->firstOrFail();
        $this->halaman()->call('tanyaArsip', $p->id)->call('jalankanAksi');
        $this->assertNull($hitung->hitung($user, 'platinum', [])['paket']);
    }

    public function test_aksi_tulis_dibatasi_lajunya(): void
    {
        $admin = $this->admin();
        $premium = Paket::where('kode', 'premium')->firstOrFail();
        $k = Livewire::actingAs($admin)->test(Product::class);
        for ($i = 0; $i < 61; $i++) {
            $k->call('populer', $premium->id);
        }
        $k->assertDispatched('toast', pesan: 'Terlalu banyak perubahan dalam satu menit. Coba lagi sebentar lagi.', tipe: 'galat');
    }
}
