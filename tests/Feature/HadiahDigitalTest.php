<?php

namespace Tests\Feature;

use App\Livewire\HadiahDigital;
use App\Models\HadiahMasuk;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Services\HadiahUndangan;
use App\Services\PenerbitUndangan;
use App\Services\PengaturanUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class HadiahDigitalTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(HadiahDigital::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    /** Undangan terbit dengan isi awal lengkap (rekening contoh bawaan skema ikut ada). */
    private function undangan(User $user, array $lain = []): Undangan
    {
        $tema = Tema::factory()->create();
        $isi = SkemaUndangan::isiAwal();
        $bagian = ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];

        $u = Undangan::factory()->for($user, 'pengguna')->dengan($tema)->create(array_merge([
            'judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'status' => Undangan::TERBIT, 'diterbitkan_at' => now(),
            'isi' => $isi, 'bagian' => $bagian,
            'terbitan' => ['judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'isi' => $isi, 'bagian' => $bagian, 'pengaturan' => [],
                'tema' => $tema->only(['kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul']),
                'diterbitkan_at' => now()->toIso8601String()],
        ], $lain));
        $u->forceFill(['terbitan' => [...$u->terbitan, 'tanda' => app(PenerbitUndangan::class)->tanda($u)]])->save();

        return $u;
    }

    /** Undangan tanpa rekening sama sekali. */
    private function kosong(User $user, array $lain = []): Undangan
    {
        $u = $this->undangan($user, $lain);
        $isi = $u->isi;
        foreach ($isi['_daftar']['kd'] as $id) {
            foreach (['bank', 'no', 'atas'] as $b) {
                unset($isi["kd{$id}_{$b}"]);
            }
        }
        $isi['_daftar']['kd'] = [];
        $isi['kdAlamat'] = $isi['kdPenerima'] = $isi['kdTelp'] = '';
        $u->forceFill(['isi' => $isi])->save();

        return $u->fresh();
    }

    private function hadiah(Undangan $u, string $nama, int $nominal = 100000, bool $selesai = true, string $via = 'Tunai'): HadiahMasuk
    {
        return HadiahMasuk::create(['undangan_id' => $u->id, 'nama' => $nama, 'via' => $via, 'nominal' => $nominal, 'terkonfirmasi' => $selesai]);
    }

    // ------------------------------------------------------------------ akses

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/hadiah')->assertRedirect(route('masuk'));
    }

    public function test_rute_asli_bukan_placeholder(): void
    {
        $this->assertSame('/hadiah', route('hadiah', absolute: false));
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->actingAs($user)->get('/hadiah')->assertOk()->assertSeeLivewire(HadiahDigital::class);
        $this->actingAs($user)->get('/hadiah/'.$u->id)->assertOk();
    }

    public function test_keadaan_kosong_tanpa_undangan(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan');
    }

    public function test_undangan_orang_lain_404_dan_id_terkunci(): void
    {
        $user = User::factory()->create();
        $this->undangan($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);

        $this->actingAs($user)->get('/hadiah/'.$lain->id)->assertNotFound();
        $this->komponen($user)->call('pilihUndangan', $lain->id)->assertStatus(404);

        $c = $this->komponen($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('undanganId', $lain->id);
    }

    // ------------------------------------------------------------------ rekening

    public function test_menampilkan_rekening_dari_isi_undangan(): void
    {
        $user = User::factory()->create();
        $this->undangan($user);

        $this->komponen($user)->assertSee('Bank BCA')->assertSee('1234 5678 90')->assertSee('a.n. Raka Pratama')->assertDontSee('a.n. a.n.', false)->assertSee('Bank Mandiri');
    }

    public function test_tambah_rekening_masuk_ke_isi_dan_undangan_ditandai_berubah(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);
        $this->assertFalse((bool) $u->ada_perubahan);

        $this->komponen($user)->assertSee('Belum ada rekening atau e-wallet')
            ->call('bukaRek')->assertSet('tampilRek', true)
            ->set('rBank', 'BCA')->set('rNo', '123-456 7890')->set('rAn', 'Raka Pratama')
            ->call('simpanRek')->assertHasNoErrors()->assertSet('tampilRek', false)
            ->assertSee('1234 5678 90');

        $u->refresh();
        $this->assertSame([1], $u->isi['_daftar']['kd']);
        $this->assertSame('BCA', $u->isi['kd1_bank']);
        $this->assertSame('1234 5678 90', $u->isi['kd1_no']);
        $this->assertSame('a.n. Raka Pratama', $u->isi['kd1_atas']);
        $this->assertTrue((bool) $u->ada_perubahan, 'perubahan baru tampil di tamu setelah terbit ulang');
    }

    public function test_tambah_ewallet(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);

        $this->komponen($user)->call('bukaRek')->call('pilihJenis', 'ewallet')->assertSet('rBank', 'GoPay')
            ->set('rNo', '081234567890')->set('rAn', 'Sinta')->call('simpanRek')->assertHasNoErrors()->assertSee('E-wallet');

        $r = app(HadiahUndangan::class)->rekening($u->fresh());
        $this->assertSame([['id' => 1, 'bank' => 'GoPay', 'no' => '0812 3456 7890', 'an' => 'Sinta', 'jenis' => 'ewallet']], $r);
    }

    public function test_validasi_rekening(): void
    {
        $user = User::factory()->create();
        $this->kosong($user);

        $this->komponen($user)->call('bukaRek')
            ->set('rNo', '')->set('rAn', '')->call('simpanRek')->assertHasErrors(['rNo', 'rAn'])
            ->set('rNo', 'abc123')->set('rAn', 'Ani')->call('simpanRek')->assertHasErrors(['rNo'])
            ->set('rNo', '123')->call('simpanRek')->assertHasErrors(['rNo'])
            ->set('rBank', 'Bank Palsu')->set('rNo', '1234567890')->call('simpanRek')->assertHasErrors(['rBank'])
            ->set('rBank', 'BCA')->set('rAn', str_repeat('a', 101))->call('simpanRek')->assertHasErrors(['rAn'])
            ->call('pilihJenis', 'ewallet')->set('rNo', '1234567')->set('rAn', 'Ani')->call('simpanRek')->assertHasErrors(['rNo'])
            ->set('rBank', 'BCA')->set('rNo', '081234567890')->call('simpanRek')->assertHasErrors(['rBank']);

        $this->assertSame([], app(HadiahUndangan::class)->rekening(Undangan::firstOrFail()));
    }

    public function test_nomor_ganda_untuk_bank_yang_sama_ditolak(): void
    {
        $user = User::factory()->create();
        $this->kosong($user);

        $this->komponen($user)->call('bukaRek')->set('rNo', '1234567890')->set('rAn', 'Ani')->call('simpanRek')->assertHasNoErrors()
            ->call('bukaRek')->set('rNo', '1234 5678 90')->set('rAn', 'Ani')->call('simpanRek')->assertHasErrors(['rNo']);
    }

    public function test_html_dibersihkan_dari_nama(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);

        $this->komponen($user)->call('bukaRek')->set('rNo', '1234567890')->set('rAn', '<b>Ani</b><script>x</script>')->call('simpanRek')->assertHasNoErrors();

        $this->assertSame('a.n. Anix', $u->fresh()->isi['kd1_atas']);
    }

    public function test_edit_rekening_mempertahankan_nama_bank_bebas_dari_editor(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->komponen($user)->call('bukaRek', 1)->assertSet('rBank', 'Bank BCA')->assertSet('rJenis', 'bank')
            ->set('rAn', 'Raka P.')->call('simpanRek')->assertHasNoErrors();

        $isi = $u->fresh()->isi;
        $this->assertSame('Bank BCA', $isi['kd1_bank']);
        $this->assertSame('a.n. Raka P.', $isi['kd1_atas']);
        $this->assertSame('Bank Mandiri', $isi['kd2_bank'], 'rekening lain tidak tersentuh');
    }

    public function test_hapus_rekening_dengan_konfirmasi(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $c = $this->komponen($user)->call('tanyaHapusRek', 1)->assertSet('hapusRekId', 1)->assertSee('Hapus akun ini?');
        $this->assertSame([1, 2], $u->fresh()->isi['_daftar']['kd']);

        $c->call('batalHapusRek')->assertSet('hapusRekId', null);
        $c->call('tanyaHapusRek', 1)->call('hapusRek');

        $isi = $u->fresh()->isi;
        $this->assertSame([2], $isi['_daftar']['kd']);
        $this->assertArrayNotHasKey('kd1_no', $isi);
        $this->assertSame('Bank Mandiri', $isi['kd2_bank']);
    }

    public function test_rekening_yang_bukan_milik_undangan_ditolak(): void
    {
        $user = User::factory()->create();
        $this->undangan($user);

        $c = $this->komponen($user);
        $c->call('bukaRek', 99)->assertStatus(404);
        $this->komponen($user)->call('tanyaHapusRek', 99)->assertStatus(404);
    }

    public function test_aksi_tidak_menyentuh_undangan_orang_lain(): void
    {
        $user = User::factory()->create();
        $punya = $this->kosong($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);
        $sebelum = $lain->isi;

        $this->komponen($user)->call('bukaRek')->set('rNo', '1234567890')->set('rAn', 'Ani')->call('simpanRek');

        $this->assertSame($sebelum, $lain->fresh()->isi);
        $this->assertSame(1, count(app(HadiahUndangan::class)->rekening($punya->fresh())));
    }

    public function test_batas_jumlah_rekening(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);
        $layanan = app(HadiahUndangan::class);
        foreach (range(1, HadiahUndangan::MAKS_REKENING) as $i) {
            $layanan->simpanRekening($u, null, 'BCA', '1234 5678 9'.$i, 'Ani');
        }

        $this->komponen($user, $u->id)->call('bukaRek')->assertSet('tampilRek', false)
            ->assertDispatched('toast', pesan: 'Maksimal '.HadiahUndangan::MAKS_REKENING.' rekening atau e-wallet.', tipe: 'galat');
    }

    // ------------------------------------------------------------------ alamat

    public function test_simpan_alamat_ke_isi_undangan(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);

        $this->komponen($user)->assertSee('Belum ada alamat')->call('bukaAlamat')->assertSet('tampilAlamat', true)
            ->set('aNama', 'Raka Pratama')->set('aHp', '0812-3456-7890')->set('aAlamat', "Jl. Melati No. 8\nMenteng, Jakarta Pusat")
            ->call('simpanAlamat')->assertHasNoErrors()->assertSet('tampilAlamat', false)->assertSee('Raka Pratama')->assertSee('Menteng, Jakarta Pusat');

        $isi = $u->fresh()->isi;
        $this->assertSame('Raka Pratama', $isi['kdPenerima']);
        $this->assertSame('081234567890', $isi['kdTelp']);
        $this->assertSame("Jl. Melati No. 8\nMenteng, Jakarta Pusat", $isi['kdAlamat']);
    }

    public function test_validasi_alamat(): void
    {
        $user = User::factory()->create();
        $this->kosong($user);

        $this->komponen($user)->call('bukaAlamat')
            ->set('aNama', '')->set('aAlamat', 'pendek')->call('simpanAlamat')->assertHasErrors(['aNama', 'aAlamat'])
            ->set('aNama', 'Raka')->set('aAlamat', 'Jl. Melati No. 8 Jakarta')->set('aHp', '12345')->call('simpanAlamat')->assertHasErrors(['aHp'])
            ->set('aHp', '')->set('aAlamat', str_repeat('a', 301))->call('simpanAlamat')->assertHasErrors(['aAlamat']);
    }

    public function test_hapus_alamat_dengan_konfirmasi(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $c = $this->komponen($user)->assertSee('Jl. Melati No. 8')->call('tanyaHapusAlamat')->assertSee('Hapus alamat ini?');
        $this->assertNotSame('', $u->fresh()->isi['kdAlamat']);
        $c->call('hapusAlamat');

        $this->assertSame('', $u->fresh()->isi['kdAlamat']);
        $this->assertNull(app(HadiahUndangan::class)->alamat($u->fresh()));
    }

    // ------------------------------------------------------------------ ucapan terima kasih

    public function test_ucapan_terima_kasih_tersimpan_tanpa_menandai_perubahan(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->komponen($user)->set('ucapanTerima', 'Terima kasih atas doa dan hadiahnya.')->call('simpanUcapanTerima')->assertHasNoErrors();

        $u->refresh();
        $this->assertSame('Terima kasih atas doa dan hadiahnya.', app(PengaturanUndangan::class)->ambil($u, 'hadiah')['ucapan_terima']);
        $this->assertSame(app(PenerbitUndangan::class)->tanda($u), $u->terbitan['tanda']);

        $this->komponen($user)->assertSet('ucapanTerima', 'Terima kasih atas doa dan hadiahnya.');
    }

    public function test_ucapan_terima_kasih_dibatasi_200_karakter(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->komponen($user)->set('ucapanTerima', str_repeat('a', 201))->call('simpanUcapanTerima')->assertHasErrors(['ucapanTerima']);
        $this->assertSame('', app(PengaturanUndangan::class)->ambil($u->fresh(), 'hadiah', ['ucapan_terima' => ''])['ucapan_terima']);
    }

    // ------------------------------------------------------------------ hadiah masuk

    public function test_catat_hadiah_dan_ringkasan(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);

        $this->komponen($user)->assertSee('Belum ada hadiah tercatat')
            ->call('bukaHadiah')->assertSet('tampilHadiah', true)->assertSet('tVia', 'Tunai')
            ->set('tNama', 'Budi')->set('tNominal', 'Rp 500.000')->set('tPesan', 'Selamat')->call('simpanHadiah')->assertHasNoErrors()->assertSet('tampilHadiah', false)
            ->call('bukaHadiah')->set('tNama', 'Citra')->set('tNominal', '250000')->set('tDone', false)->call('simpanHadiah')->assertHasNoErrors()
            ->assertSee('Budi')->assertSee('Citra')->assertSee('Rp 500.000')->assertSee('Menunggu');

        $b = HadiahMasuk::where('nama', 'Budi')->firstOrFail();
        $this->assertSame($u->id, $b->undangan_id);
        $this->assertSame(500000, $b->nominal);
        $this->assertTrue($b->terkonfirmasi);
        $this->assertFalse(HadiahMasuk::where('nama', 'Citra')->firstOrFail()->terkonfirmasi);
    }

    public function test_validasi_hadiah(): void
    {
        $user = User::factory()->create();
        $this->kosong($user);

        $this->komponen($user)->call('bukaHadiah')
            ->set('tNama', '')->set('tNominal', '')->call('simpanHadiah')->assertHasErrors(['tNama', 'tNominal'])
            ->set('tNama', 'Budi')->set('tNominal', 'abc')->call('simpanHadiah')->assertHasErrors(['tNominal'])
            ->set('tNominal', '2000000000')->call('simpanHadiah')->assertHasErrors(['tNominal'])
            ->set('tNominal', '1000')->set('tVia', 'Kartu Kredit')->call('simpanHadiah')->assertHasErrors(['tVia'])
            ->set('tVia', 'Tunai')->set('tPesan', str_repeat('a', 201))->call('simpanHadiah')->assertHasErrors(['tPesan']);

        $this->assertSame(0, HadiahMasuk::count());
    }

    public function test_via_mengikuti_rekening_di_undangan(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->komponen($user)->call('bukaHadiah')->assertSet('tVia', 'Bank BCA')->set('tNama', 'Budi')->set('tNominal', '1000')->set('tVia', 'Bank Mandiri')->call('simpanHadiah')->assertHasNoErrors();
        $this->assertSame('Bank Mandiri', HadiahMasuk::firstOrFail()->via);
    }

    public function test_konfirmasi_filter_dan_hapus(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);
        $a = $this->hadiah($u, 'Ani', 100000, true);
        $b = $this->hadiah($u, 'Budi', 50000, false);

        $c = $this->komponen($user)->assertSee('Rp 100.000')
            ->call('pilihFilter', 'pending')->assertSee('Budi')->assertDontSee('Ani')
            ->call('pilihFilter', 'done')->assertSee('Ani')->assertDontSee('Budi')
            ->call('pilihFilter', 'ngawur')->assertSet('filter', 'semua')
            ->call('konfirmasi', $b->id);
        $this->assertTrue($b->fresh()->terkonfirmasi);

        $c->call('tanyaHapusHadiah', $a->id)->assertSet('hapusHadiahId', $a->id)->assertSee('Hapus');
        $this->assertSame(2, HadiahMasuk::count());
        $c->call('hapusHadiah');
        $this->assertSame(1, HadiahMasuk::count());
    }

    public function test_hadiah_orang_lain_tidak_bisa_dibaca_atau_diubah(): void
    {
        $user = User::factory()->create();
        $this->kosong($user);
        $lain = $this->kosong(User::factory()->create(), ['slug' => 'orang-lain']);
        $asing = $this->hadiah($lain, 'Rahasia', 999000, false);

        $c = $this->komponen($user)->assertDontSee('Rahasia');
        $c->call('konfirmasi', $asing->id)->assertStatus(404);
        $this->komponen($user)->call('tanyaHapusHadiah', $asing->id)->assertStatus(404);
        $this->assertFalse($asing->fresh()->terkonfirmasi);
        $this->assertSame(1, HadiahMasuk::count());

        $c2 = $this->komponen($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c2->set('hapusHadiahId', $asing->id);
    }

    public function test_paginasi_hadiah(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);
        foreach (range(1, 13) as $i) {
            $this->hadiah($u, 'Pemberi '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $c = $this->komponen($user)->assertSee('Pemberi 13')->assertDontSee('Pemberi 01')->assertSee('1 / 2');
        $c->call('ke', 2)->assertSee('Pemberi 01')->assertDontSee('Pemberi 13');
        $c->call('ke', 50)->assertSee('2 / 2');
    }

    public function test_ekspor_csv_aman_dan_dibatasi(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);

        $this->komponen($user)->call('ekspor')->assertNotDispatched('download')->assertDispatched('toast', pesan: 'Tidak ada hadiah untuk diekspor.', tipe: 'info');

        $this->hadiah($u, '=cmd|calc', 100000);
        $this->hadiah($u, 'Ani', 50000, false);
        $res = $this->komponen($user)->call('pilihFilter', 'done')->call('ekspor')->assertFileDownloaded();
        $csv = base64_decode($res->effects['download']['content'] ?? '');
        $this->assertStringContainsString('100000', $csv);
        $this->assertStringNotContainsString('Ani', $csv);
        $this->assertStringNotContainsString('"=cmd', $csv);
        $this->assertStringNotContainsString(',=cmd', $csv);

        RateLimiter::clear('hadiah-ekspor:'.$user->id);
        $c = $this->komponen($user);
        foreach (range(1, 10) as $i) {
            $c->call('ekspor');
        }
        $c->call('ekspor')->assertDispatched('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');
    }

    public function test_peringatan_bagian_kado_disembunyikan(): void
    {
        $user = User::factory()->create();
        $this->undangan($user, ['bagian' => ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => ['kado']]]);

        $this->komponen($user)->assertSee('Bagian Kado Digital disembunyikan');
    }

    // ------------------------------------------------------------------ tersambung ke undangan publik

    public function test_rekening_dari_halaman_ini_tampil_di_undangan_setelah_terbit_ulang(): void
    {
        $user = User::factory()->create();
        $u = $this->kosong($user);
        $this->komponen($user)->call('bukaRek')->set('rBank', 'BCA')->set('rNo', '5555666677')->set('rAn', 'Raka Pratama')->call('simpanRek')->assertHasNoErrors();

        $this->withHeaders(['User-Agent' => self::UA])->get('/raka-sinta')->assertOk()->assertDontSee('5555 6666 77');

        app(PenerbitUndangan::class)->terbitkan($u->fresh(), 'raka-sinta');

        $this->withHeaders(['User-Agent' => self::UA])->get('/raka-sinta')->assertOk()->assertSee('5555 6666 77')->assertSee('Raka Pratama');
    }
}
