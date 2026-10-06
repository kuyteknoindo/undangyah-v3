<?php

namespace Tests\Feature;

use App\Livewire\Admin\Pembayaran;
use App\Livewire\Checkout;
use App\Models\MetodePembayaran;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Models\User;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

class AdminMetodePembayaranTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
    }

    private function halaman(?User $user = null)
    {
        return Livewire::actingAs($this->sudahKonfirmasiSandi($user ?? User::factory()->admin()->create()))->test(Pembayaran::class);
    }

    private function isiBank($k, array $f = [])
    {
        $k->set('f.label', 'Transfer BCA')->set('f.tipe', 'manual')->set('f.bank', 'BCA')->set('f.nomor', '1234 567 890')->set('f.atas', 'PT Undangan Digital')->set('f.aktif', true);
        foreach ($f as $kunci => $nilai) {
            $k->set('f.'.$kunci, $nilai);
        }

        return $k;
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/pembayaran')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/pembayaran')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/pembayaran')->assertOk()->assertSeeLivewire(Pembayaran::class);
        Livewire::actingAs(User::factory()->create())->test(Pembayaran::class)->assertForbidden();
    }

    public function test_peringatan_jelas_tampil_selama_metode_aktif_masih_placeholder(): void
    {
        Pesanan::factory()->create(['metode_data' => ['placeholder' => true, 'tipe' => 'manual']]);
        Pesanan::factory()->create(['metode_data' => ['placeholder' => false], 'status' => 'lunas']);

        $this->halaman()->assertSee('PERINGATAN: rekening pembayaran masih data contoh')->assertSee('Transfer manual')->assertSee('0000000000')->assertSee('Data contoh')
            ->assertSee('1 pesanan yang sedang menunggu pembayaran');
    }

    public function test_peringatan_hilang_bila_metode_contoh_nonaktif_dan_tidak_ada_metode_aktif_memberi_peringatan_lain(): void
    {
        MetodePembayaran::query()->update(['aktif' => false]);

        $this->halaman()->assertDontSee('PERINGATAN: rekening pembayaran masih data contoh')->assertSee('Semua metode pembayaran nonaktif.');
    }

    public function test_menyimpan_rekening_nyata_menghilangkan_tanda_placeholder_dan_peringatan(): void
    {
        $m = MetodePembayaran::where('kode', 'transfer-bank')->firstOrFail();
        $this->assertTrue($m->data['placeholder']);

        $this->halaman()->call('bukaEdit', $m->id)->assertSet('f.bank', 'BANK CONTOH')
            ->set('f.bank', 'BCA')->set('f.nomor', '0123 4567 89')->set('f.atas', 'CV Undangan Digital')->call('simpan')->assertHasNoErrors()
            ->assertDontSee('PERINGATAN: rekening pembayaran masih data contoh');

        $m->refresh();
        $this->assertFalse($m->data['placeholder']);
        $this->assertSame('0123456789', $m->data['nomor']);
        $this->assertSame('BCA', $m->data['bank']);
        $this->assertSame('transfer-bank', $m->kode);
        $this->assertCount(4, $m->data['langkah']);
    }

    public function test_menyimpan_tanpa_mengganti_rekening_contoh_tidak_menghapus_tanda(): void
    {
        $m = MetodePembayaran::where('kode', 'transfer-bank')->firstOrFail();

        $this->halaman()->call('bukaEdit', $m->id)->set('f.keterangan', 'Teks baru')->call('simpan')->assertHasNoErrors();
        $this->assertTrue($m->fresh()->data['placeholder']);

        $this->halaman()->call('bukaEdit', $m->id)->set('f.nomor', '000000')->set('f.atas', 'Budi')->set('f.bank', 'BCA')->call('simpan')->assertHasNoErrors();
        $this->assertTrue($m->fresh()->data['placeholder']);

        $this->halaman()->call('bukaEdit', $m->id)->set('f.nomor', '123456789')->set('f.bank', 'BCA')->set('f.atas', 'GANTI DI ADMIN: NAMA')->call('simpan')->assertHasNoErrors();
        $this->assertTrue($m->fresh()->data['placeholder']);
    }

    public function test_tambah_metode_rekening_nyata_dipakai_checkout(): void
    {
        $this->isiBank($this->halaman()->call('bukaBaru'), ['biaya_tetap' => '2.500', 'biaya_persen' => '0,7', 'kode_unik' => false, 'langkah' => "Buka m-banking\n\nTransfer sesuai total"])
            ->call('simpan')->assertHasNoErrors()->assertDispatched('toast');

        $m = MetodePembayaran::where('label', 'Transfer BCA')->firstOrFail();
        $this->assertSame('transfer-bca', $m->kode);
        $this->assertSame(2500, $m->biaya_tetap);
        $this->assertEquals(0.7, $m->biaya_persen);
        $this->assertFalse($m->kode_unik);
        $this->assertSame('1234567890', $m->data['nomor']);
        $this->assertFalse($m->data['placeholder']);
        $this->assertSame(['Buka m-banking', 'Transfer sesuai total'], $m->data['langkah']);
        $this->assertSame(3, $m->urutan);
        $this->assertSame(2500 + 1743, $m->biayaUntuk(249000) - 0);

        $this->actingAs(User::factory()->create());
        Livewire::test(Checkout::class)->assertSee('Transfer BCA');
    }

    public function test_metode_instruksi_e_wallet(): void
    {
        $this->halaman()->call('bukaBaru')->set('f.label', 'DANA')->set('f.tipe', 'instruksi')->set('f.tujuan_label', 'Nomor DANA')->set('f.tujuan_nilai', '081234567890')->set('f.atas', 'Siti')->call('simpan')->assertHasNoErrors();

        $m = MetodePembayaran::where('label', 'DANA')->firstOrFail();
        $this->assertSame('instruksi', $m->tipe);
        $this->assertSame('081234567890', $m->data['tujuan_nilai']);
        $this->assertFalse($m->data['placeholder']);
        $this->assertArrayNotHasKey('bank', $m->data);
        $this->assertFalse($m->aktif);
    }

    public function test_validasi_form_metode(): void
    {
        $this->halaman()->call('bukaBaru')->call('simpan')->assertHasErrors(['f.label', 'f.bank', 'f.nomor', 'f.atas']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['label' => 'transfer manual'])->call('simpan')->assertHasErrors(['f.label']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['nomor' => '12ab'])->call('simpan')->assertHasErrors(['f.nomor']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['nomor' => '123'])->call('simpan')->assertHasErrors(['f.nomor']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['biaya_persen' => '150'])->call('simpan')->assertHasErrors(['f.biaya_persen']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['biaya_persen' => 'abc'])->call('simpan')->assertHasErrors(['f.biaya_persen']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['biaya_persen' => '1,234'])->call('simpan')->assertHasErrors(['f.biaya_persen']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['biaya_tetap' => '99999999'])->call('simpan')->assertHasErrors(['f.biaya_tetap']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['instruksi' => str_repeat('a', 301)])->call('simpan')->assertHasErrors(['f.instruksi']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['langkah' => implode("\n", range(1, 9))])->call('simpan')->assertHasErrors(['f.langkah']);
        $this->isiBank($this->halaman()->call('bukaBaru'), ['langkah' => str_repeat('x', 161)])->call('simpan')->assertHasErrors(['f.langkah']);
        $this->halaman()->call('bukaBaru')->set('f.label', 'DANA')->set('f.tipe', 'instruksi')->set('f.atas', 'Siti')->call('simpan')->assertHasErrors(['f.tujuan_nilai']);
        $this->assertSame(2, MetodePembayaran::count());
    }

    public function test_toggle_aktif_urutan_dan_peringatan_saat_menyalakan_contoh(): void
    {
        $e = MetodePembayaran::where('kode', 'dompet-digital')->firstOrFail();
        $b = MetodePembayaran::where('kode', 'transfer-bank')->firstOrFail();
        $this->assertFalse($e->aktif);

        $this->halaman()->call('ubahAktif', $e->id)->assertDispatched('toast', pesan: 'Dompet digital (transfer manual) diaktifkan. Perhatian: rekening masih data contoh, ganti dengan rekening nyata.', tipe: 'info');
        $this->assertTrue($e->fresh()->aktif);

        $this->halaman()->call('geser', $e->id, -1);
        $this->assertSame(1, $e->fresh()->urutan);
        $this->assertSame(2, $b->fresh()->urutan);
    }

    public function test_metode_yang_dipakai_pesanan_tidak_bisa_dihapus_walau_dialog_sudah_terbuka(): void
    {
        $m = MetodePembayaran::where('kode', 'dompet-digital')->firstOrFail();
        Pesanan::factory()->create(['metode_id' => $m->id, 'metode_kode' => 'dompet-digital']);
        $k = $this->halaman()->call('tanyaHapus', $m->id)->assertSet('dialogHapus', false)->assertDispatched('toast');
        $k->call('hapus');
        $this->assertDatabaseHas('metode_pembayaran', ['id' => $m->id]);
    }

    public function test_metode_dengan_kode_pada_pesanan_lama_tetap_dilindungi_meski_relasi_terputus(): void
    {
        $m = MetodePembayaran::where('kode', 'dompet-digital')->firstOrFail();
        Pesanan::factory()->create(['metode_id' => null, 'metode_kode' => 'dompet-digital']);
        $this->halaman()->call('tanyaHapus', $m->id)->assertSet('dialogHapus', false);
        $this->assertDatabaseHas('metode_pembayaran', ['id' => $m->id]);
    }

    public function test_hapus_metode_belum_dipakai(): void
    {
        $m = MetodePembayaran::where('kode', 'dompet-digital')->firstOrFail();
        $this->halaman()->call('tanyaHapus', $m->id)->assertSet('dialogHapus', true)->call('hapus');
        $this->assertDatabaseMissing('metode_pembayaran', ['id' => $m->id]);
    }

    public function test_batas_waktu_pembayaran_disimpan_dan_divalidasi(): void
    {
        $this->halaman()->assertSet('kedaluwarsaJam', '24')->set('kedaluwarsaJam', '48')->call('simpanBatasWaktu')->assertHasNoErrors();
        $this->assertSame(48, Pengaturan::ambil('pembayaran.kedaluwarsa_jam'));

        $this->halaman()->set('kedaluwarsaJam', '0')->call('simpanBatasWaktu')->assertHasErrors(['kedaluwarsaJam']);
        $this->halaman()->set('kedaluwarsaJam', '500')->call('simpanBatasWaktu')->assertHasErrors(['kedaluwarsaJam']);
        $this->assertSame(48, Pengaturan::ambil('pembayaran.kedaluwarsa_jam'));
    }

    public function test_properti_id_terkunci_dan_aksi_dibatasi_lajunya(): void
    {
        try {
            $this->halaman()->set('hapusId', 1);
            $this->fail('Properti terkunci seharusnya menolak perubahan klien.');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }

        $m = MetodePembayaran::where('kode', 'dompet-digital')->firstOrFail();
        $k = $this->halaman();
        for ($i = 0; $i < 61; $i++) {
            $k->call('ubahAktif', $m->id);
        }
        $k->assertDispatched('toast', pesan: 'Terlalu banyak perubahan dalam satu menit. Coba lagi sebentar lagi.', tipe: 'galat');
    }

    public function test_aksi_ditolak_bila_peran_admin_dicabut_setelah_halaman_dibuka(): void
    {
        $admin = User::factory()->admin()->create();
        $k = Livewire::actingAs($admin)->test(Pembayaran::class);
        $admin->forceFill(['role' => User::PERAN_USER])->save();
        $m = MetodePembayaran::where('kode', 'transfer-bank')->firstOrFail();

        $k->call('ubahAktif', $m->id)->assertForbidden();
        $this->assertTrue($m->fresh()->aktif);
    }
}
