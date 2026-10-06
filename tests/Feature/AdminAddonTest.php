<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddOns;
use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\ItemPesanan;
use App\Models\Pesanan;
use App\Models\User;
use App\Services\HitungPesanan;
use App\Services\KuotaUndangan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class AdminAddonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
    }

    private function halaman(?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->admin()->create())->test(AddOns::class);
    }

    private function isi($k, array $f = [])
    {
        $k->set('f.nama', 'Template video')->set('f.deskripsi', 'Video pembuka undangan')->set('f.harga', '75000');
        foreach ($f as $kunci => $nilai) {
            $k->set('f.'.$kunci, $nilai);
        }

        return $k;
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/addons')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/addons')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/addons')->assertOk()->assertSeeLivewire(AddOns::class);
        Livewire::actingAs(User::factory()->create())->test(AddOns::class)->assertForbidden();
    }

    public function test_daftar_addon_dari_database_tampil(): void
    {
        $this->halaman()->assertSee('Tambah kuota undangan')->assertSee('Domain kustom')->assertSee('Rp 150.000')->assertSee('Sistem');
    }

    public function test_tambah_addon_dengan_efek_kuota(): void
    {
        $k = $this->isi($this->halaman()->call('bukaBaru'), ['efek_jenis' => 'kuota_undangan', 'efek_nilai' => '2', 'multi' => true, 'tahunan' => true]);
        $k->call('simpan')->assertHasNoErrors()->assertDispatched('toast');

        $a = AddOn::where('nama', 'Template video')->firstOrFail();
        $this->assertSame('template-video', $a->kode);
        $this->assertSame(75000, $a->harga);
        $this->assertSame(' / tahun', $a->satuan);
        $this->assertTrue($a->multi);
        $this->assertTrue($a->aktif);
        $this->assertSame(['kuota_undangan' => 2], $a->efek);
        $this->assertSame(9, $a->urutan);

        // Efek yang tersimpan benar-benar dipakai perhitungan kuota.
        $user = User::factory()->create();
        AddonPengguna::create(['user_id' => $user->id, 'addon_kode' => 'template-video', 'jumlah' => 2]);
        $this->assertSame(4, app(KuotaUndangan::class)->tambahan($user));
    }

    public function test_validasi_form_addon(): void
    {
        $this->halaman()->call('bukaBaru')->call('simpan')->assertHasErrors(['f.nama', 'f.deskripsi', 'f.harga']);
        $this->isi($this->halaman()->call('bukaBaru'), ['nama' => 'domain kustom'])->call('simpan')->assertHasErrors(['f.nama']);
        $this->isi($this->halaman()->call('bukaBaru'), ['harga' => '0'])->call('simpan')->assertHasErrors(['f.harga']);
        $this->isi($this->halaman()->call('bukaBaru'), ['nama' => 'ab'])->call('simpan')->assertHasErrors(['f.nama']);
        $this->isi($this->halaman()->call('bukaBaru'), ['butuh_premium' => true, 'termasuk_premium' => true])->call('simpan')->assertHasErrors(['f.aturan']);
        $this->isi($this->halaman()->call('bukaBaru'), ['efek_jenis' => 'kuota_undangan', 'efek_nilai' => ''])->call('simpan')->assertHasErrors(['f.efek_nilai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['efek_jenis' => 'fitur', 'efek_nilai' => 'Pakai Spasi!'])->call('simpan')->assertHasErrors(['f.efek_nilai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['efek_jenis' => 'perpanjang_bulan', 'efek_nilai' => '999'])->call('simpan')->assertHasErrors(['f.efek_nilai']);
        $this->assertSame(8, AddOn::count());
    }

    public function test_edit_addon_mempertahankan_kode_dan_jenis_efek_sistem_terkunci(): void
    {
        $a = AddOn::where('kode', 'kuota-undangan')->firstOrFail();
        $this->halaman()->call('bukaEdit', $a->id)->assertSet('f.efek_jenis', 'kuota_undangan')->assertSet('f.efek_nilai', '1')
            ->set('f.efek_jenis', 'fitur')->set('f.efek_nilai', '2')->set('f.harga', '30000')->call('simpan')->assertHasNoErrors();

        $a->refresh();
        $this->assertSame('kuota-undangan', $a->kode);
        $this->assertSame(30000, $a->harga);
        $this->assertSame(['kuota_undangan' => 2], $a->efek);
    }

    public function test_efek_lain_pada_baris_lama_tidak_hilang(): void
    {
        $a = AddOn::where('kode', 'domain-kustom')->firstOrFail();
        $a->update(['efek' => ['fitur' => 'domain-kustom', 'catatan' => 'jangan hilang']]);
        $this->halaman()->call('bukaEdit', $a->id)->set('f.harga', '160000')->call('simpan')->assertHasNoErrors();

        $this->assertSame(['catatan' => 'jangan hilang', 'fitur' => 'domain-kustom'], $a->fresh()->efek);
    }

    public function test_ubah_aktif_menyembunyikan_addon_dari_checkout(): void
    {
        $a = AddOn::where('kode', 'live-streaming')->firstOrFail();
        $user = User::factory()->create();
        $this->assertCount(1, app(HitungPesanan::class)->hitung($user, null, ['live-streaming'])['item']);

        $this->halaman()->call('ubahAktif', $a->id);

        $this->assertFalse($a->fresh()->aktif);
        $this->assertCount(0, app(HitungPesanan::class)->hitung($user, null, ['live-streaming'])['item']);
    }

    public function test_addon_sistem_tidak_bisa_dihapus(): void
    {
        $a = AddOn::where('kode', 'perpanjang-masa-aktif')->firstOrFail();
        $this->halaman()->call('tanyaHapus', $a->id)->assertSet('dialogHapus', false)->assertDispatched('toast');
        $this->assertDatabaseHas('addon', ['id' => $a->id]);
    }

    public function test_addon_belum_dipakai_bisa_dihapus(): void
    {
        $a = AddOn::where('kode', 'musik-premium')->firstOrFail();
        $this->halaman()->call('tanyaHapus', $a->id)->assertSet('dialogHapus', true)->call('hapus');
        $this->assertDatabaseMissing('addon', ['id' => $a->id]);
    }

    public function test_addon_yang_dipakai_pesanan_atau_pengguna_tidak_bisa_dihapus(): void
    {
        $a = AddOn::where('kode', 'musik-premium')->firstOrFail();
        $pesanan = Pesanan::factory()->create();
        ItemPesanan::create(['pesanan_id' => $pesanan->id, 'tipe' => 'addon', 'kode' => 'musik-premium', 'nama' => 'Musik premium', 'harga' => 29000]);
        $this->halaman()->call('tanyaHapus', $a->id)->assertSet('dialogHapus', false)->assertDispatched('toast');
        $this->assertDatabaseHas('addon', ['id' => $a->id]);

        $b = AddOn::where('kode', 'hapus-logo')->firstOrFail();
        AddonPengguna::create(['user_id' => User::factory()->create()->id, 'addon_kode' => 'hapus-logo', 'jumlah' => 1]);
        $k = $this->halaman()->call('tanyaHapus', $b->id)->assertSet('dialogHapus', false);
        $k->call('hapus');
        $this->assertDatabaseHas('addon', ['id' => $b->id]);
    }

    public function test_hapus_diblokir_bila_dipakai_setelah_dialog_dibuka(): void
    {
        $a = AddOn::where('kode', 'blast-whatsapp')->firstOrFail();
        $k = $this->halaman()->call('tanyaHapus', $a->id)->assertSet('dialogHapus', true);
        AddonPengguna::create(['user_id' => User::factory()->create()->id, 'addon_kode' => 'blast-whatsapp', 'jumlah' => 1]);
        $k->call('hapus');
        $this->assertDatabaseHas('addon', ['id' => $a->id]);
    }

    public function test_id_terkunci_dan_urutan_bisa_digeser(): void
    {
        $a = AddOn::where('kode', 'kuota-undangan')->firstOrFail();
        $b = AddOn::where('kode', 'perpanjang-masa-aktif')->firstOrFail();
        $this->halaman()->call('geser', $b->id, -1);
        $this->assertSame(1, $b->fresh()->urutan);
        $this->assertSame(2, $a->fresh()->urutan);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman()->set('hapusId', 1);
    }

    public function test_pencarian_dan_filter(): void
    {
        $this->halaman()->set('q', 'domain')->assertSee('Rp 150.000')->assertDontSee('Live streaming')
            ->set('q', 'zzz')->assertSee('Tidak ada add-on yang cocok')->call('resetFilter')->assertSee('Live streaming');
        $a = AddOn::where('kode', 'live-streaming')->firstOrFail();
        $a->update(['aktif' => false]);
        $this->halaman()->call('atur', 'nonaktif')->assertSee('Live streaming')->assertDontSeeHtml('wire:key="a-'.AddOn::where('kode', 'domain-kustom')->value('id').'"');
    }
}
