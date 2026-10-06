<?php

namespace Tests\Feature;

use App\Livewire\Admin\Vouchers;
use App\Models\Pesanan;
use App\Models\User;
use App\Models\Voucher;
use App\Services\HitungPesanan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

class AdminVoucherTest extends TestCase
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
        return Livewire::actingAs($this->sudahKonfirmasiSandi($user ?? User::factory()->admin()->create()))->test(Vouchers::class);
    }

    private function isi($k, array $f = [])
    {
        $k->set('f.kode', 'promo20')->set('f.jenis', 'persen')->set('f.nilai', '20')->set('f.batas_pakai', '10');
        foreach ($f as $kunci => $nilai) {
            $k->set('f.'.$kunci, $nilai);
        }

        return $k;
    }

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/voucher')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/voucher')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/voucher')->assertOk()->assertSeeLivewire(Vouchers::class);
        Livewire::actingAs(User::factory()->create())->test(Vouchers::class)->assertForbidden();
    }

    public function test_daftar_menampilkan_voucher_status_dan_jumlah_terpakai(): void
    {
        $this->halaman()->assertSee('NIKAH20')->assertSee('64 / 100')->assertSee('HEMAT50K')->assertSee('Habis')->assertSee('MERDEKA17')->assertSee('Kedaluwarsa')
            ->assertSee('Total pemakaian');
    }

    public function test_buat_voucher_menormalkan_kode_huruf_besar_dan_terpakai_nol(): void
    {
        $this->isi($this->halaman()->call('bukaBaru'), ['kode' => ' promo 20 ', 'sampai' => '2030-12-31', 'min_belanja' => '100000', 'paket' => ['premium', 'tidak-ada']])
            ->call('simpan')->assertHasNoErrors()->assertDispatched('toast');

        $v = Voucher::where('kode', 'PROMO20')->firstOrFail();
        $this->assertSame('persen', $v->jenis);
        $this->assertSame(20, $v->nilai);
        $this->assertSame(0, $v->terpakai);
        $this->assertSame(10, $v->batas_pakai);
        $this->assertSame(100000, $v->min_belanja);
        $this->assertSame(['premium'], $v->paket_berlaku);
        $this->assertSame('2030-12-31 23:59:59', $v->berlaku_sampai->format('Y-m-d H:i:s'));
        $this->assertSame('aktif', $v->status());
    }

    public function test_voucher_baru_dipakai_checkout(): void
    {
        $this->isi($this->halaman()->call('bukaBaru'))->call('simpan')->assertHasNoErrors();
        $hasil = app(HitungPesanan::class)->hitung(User::factory()->create(), 'premium', [], 'promo20');

        $this->assertSame(49800, $hasil['diskon']);
    }

    public function test_validasi_rentang_dan_jenis(): void
    {
        $this->halaman()->call('bukaBaru')->call('simpan')->assertHasErrors(['f.kode', 'f.nilai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['kode' => 'ab'])->call('simpan')->assertHasErrors(['f.kode']);
        $this->isi($this->halaman()->call('bukaBaru'), ['kode' => 'HEMAT-20'])->call('simpan')->assertHasErrors(['f.kode']);
        $this->isi($this->halaman()->call('bukaBaru'), ['kode' => 'nikah20'])->call('simpan')->assertHasErrors(['f.kode']);
        $this->isi($this->halaman()->call('bukaBaru'), ['nilai' => '0'])->call('simpan')->assertHasErrors(['f.nilai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['nilai' => '101'])->call('simpan')->assertHasErrors(['f.nilai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['batas_pakai' => '0'])->call('simpan')->assertHasErrors(['f.batas_pakai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['dari' => '2030-02-01', 'sampai' => '2030-01-01'])->call('simpan')->assertHasErrors(['f.sampai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['sampai' => 'bukan-tanggal'])->call('simpan')->assertHasErrors(['f.sampai']);
        $this->isi($this->halaman()->call('bukaBaru'), ['jenis' => 'nominal', 'nilai' => '150000000'])->call('simpan')->assertHasErrors(['f.nilai']);
        $this->assertSame(4, Voucher::count());
    }

    public function test_nominal_tidak_dibatasi_100_dan_maks_diskon_hanya_untuk_persen(): void
    {
        $this->isi($this->halaman()->call('bukaBaru'), ['kode' => 'POTONG', 'jenis' => 'nominal', 'nilai' => '75000', 'maks_diskon' => '5000'])->call('simpan')->assertHasNoErrors();
        $v = Voucher::where('kode', 'POTONG')->firstOrFail();
        $this->assertSame('nominal', $v->jenis);
        $this->assertSame(75000, $v->nilai);
        $this->assertNull($v->maks_diskon);

        $this->isi($this->halaman()->call('bukaBaru'), ['kode' => 'PERSEN5', 'nilai' => '50', 'maks_diskon' => '25000'])->call('simpan')->assertHasNoErrors();
        $this->assertSame(25000, Voucher::where('kode', 'PERSEN5')->value('maks_diskon'));
    }

    public function test_edit_tidak_bisa_mengubah_terpakai_dan_kuota_tidak_boleh_di_bawahnya(): void
    {
        $v = Voucher::where('kode', 'NIKAH20')->firstOrFail();
        $k = $this->halaman()->call('bukaEdit', $v->id)->assertSet('f.kode', 'NIKAH20')->assertSet('f.batas_pakai', '100');
        $k->set('f.batas_pakai', '50')->call('simpan')->assertHasErrors(['f.batas_pakai']);
        $this->assertSame(100, $v->fresh()->batas_pakai);

        $k->set('f.batas_pakai', '200')->set('f.terpakai', '0')->call('simpan')->assertHasNoErrors();
        $this->assertSame(200, $v->fresh()->batas_pakai);
        $this->assertSame(64, $v->fresh()->terpakai);
    }

    public function test_kode_boleh_tetap_sama_saat_edit_tetapi_tidak_boleh_sama_dengan_voucher_lain(): void
    {
        $v = Voucher::where('kode', 'NIKAH20')->firstOrFail();
        $this->halaman()->call('bukaEdit', $v->id)->call('simpan')->assertHasNoErrors();
        $this->halaman()->call('bukaEdit', $v->id)->set('f.kode', 'premium10')->call('simpan')->assertHasErrors(['f.kode']);
    }

    public function test_toggle_aktif_mengubah_status_dan_kode_acak_unik(): void
    {
        $v = Voucher::where('kode', 'NIKAH20')->firstOrFail();
        $this->halaman()->call('ubahAktif', $v->id);
        $this->assertSame('nonaktif', $v->fresh()->status());

        $kode = $this->halaman()->call('bukaBaru')->call('acak')->get('f.kode');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $kode);
    }

    public function test_hapus_voucher_belum_dipakai_pesanan(): void
    {
        $v = Voucher::where('kode', 'PREMIUM10')->firstOrFail();
        $this->halaman()->call('tanyaHapus', $v->id)->assertSet('dialogHapus', true)->call('hapus');
        $this->assertDatabaseMissing('voucher', ['id' => $v->id]);
    }

    public function test_voucher_yang_dipakai_pesanan_tidak_bisa_dihapus(): void
    {
        $v = Voucher::where('kode', 'PREMIUM10')->firstOrFail();
        Pesanan::factory()->create(['voucher_id' => $v->id, 'voucher_kode' => 'PREMIUM10']);
        $k = $this->halaman()->call('tanyaHapus', $v->id)->assertSet('dialogHapus', false)->assertDispatched('toast');
        $k->call('hapus');
        $this->assertDatabaseHas('voucher', ['id' => $v->id]);
    }

    public function test_filter_status_dan_pencarian(): void
    {
        $this->halaman()->call('atur', 'habis')->assertSee('HEMAT50K')->assertDontSee('NIKAH20')
            ->call('atur', 'kedaluwarsa')->assertSee('MERDEKA17')->assertDontSee('HEMAT50K')
            ->call('atur', 'semua')->set('q', 'premium')->assertSee('PREMIUM10')->assertDontSee('NIKAH20')
            ->call('atur', 'sembarang')->assertSet('fs', 'semua');
    }

    public function test_ekspor_csv_aman_dari_rumus_dan_dibatasi(): void
    {
        Voucher::create(['kode' => 'EXPORT1', 'jenis' => 'persen', 'nilai' => 5, 'aktif' => true]);
        $k = $this->halaman()->set('q', 'export')->call('ekspor');
        $k->assertFileDownloaded('voucher-'.now()->format('Ymd').'.csv');

        $admin = User::factory()->admin()->create();
        $l = Livewire::actingAs($admin)->test(Vouchers::class);
        for ($i = 0; $i < 11; $i++) {
            $l->call('ekspor');
        }
        $l->assertDispatched('toast', pesan: 'Terlalu sering mengekspor. Coba lagi sebentar lagi.', tipe: 'galat');
    }

    public function test_aksi_ditolak_bila_peran_admin_dicabut_setelah_halaman_dibuka(): void
    {
        $admin = User::factory()->admin()->create();
        $k = Livewire::actingAs($admin)->test(Vouchers::class);
        $admin->forceFill(['role' => User::PERAN_USER])->save();

        $k->call('ekspor')->assertForbidden();
    }

    public function test_id_terkunci(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman()->set('hapusId', 1);
    }
}
