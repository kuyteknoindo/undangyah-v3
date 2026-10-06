<?php

namespace Tests\Feature;

use App\Livewire\RsvpTamu;
use App\Models\Pengaturan;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Models\User;
use App\Services\CsvTamu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Exceptions\PublicPropertyNotFoundException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class RsvpTamuTest extends TestCase
{
    use RefreshDatabase;

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(RsvpTamu::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function terbit(User $user, string $slug = 'raka-sinta'): Undangan
    {
        return Undangan::factory()->terbit()->create(['user_id' => $user->id, 'slug' => $slug, 'judul' => 'Raka & Sinta']);
    }

    private function tamu(Undangan $u, array $atribut = []): Tamu
    {
        return Tamu::factory()->create(array_merge(['undangan_id' => $u->id], $atribut));
    }

    private function csv(string $isi, string $nama = 'tamu.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nama, $isi);
    }

    // ---------------------------------------------------------------- akses

    public function test_tamu_diarahkan_ke_masuk_dan_rute_asli(): void
    {
        $this->get('/rsvp')->assertRedirect(route('masuk'));
        $this->assertSame('/rsvp', route('rsvp', absolute: false));

        $user = User::factory()->create();
        $this->terbit($user);
        $this->actingAs($user)->get('/rsvp')->assertOk()->assertSeeLivewire(RsvpTamu::class);
    }

    public function test_tanpa_undangan_tampil_keadaan_kosong(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan');
    }

    public function test_undangan_milik_orang_lain_404(): void
    {
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $this->actingAs(User::factory()->create())->get('/rsvp/'.$lain->id)->assertNotFound();
    }

    public function test_tidak_bisa_pilih_undangan_orang_lain_dan_id_terkunci(): void
    {
        $user = User::factory()->create();
        $punya = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');

        $c = $this->komponen($user)->assertSet('undanganId', $punya->id);
        $c->call('pilih', $lain->id)->assertNotFound();
        $this->assertSame($punya->id, $this->komponen($user)->get('undanganId'));
    }

    public function test_id_undangan_terkunci_dari_klien(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('undanganId', $lain->id);
    }

    // ---------------------------------------------------------------- ringkasan dan daftar

    public function test_ringkasan_menghitung_hadir_tidak_belum_dan_jiwa(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $a = $this->tamu($u, ['nama' => 'Ani', 'jumlah' => 3]);
        $b = $this->tamu($u, ['nama' => 'Budi', 'jumlah' => 2]);
        $this->tamu($u, ['nama' => 'Cici', 'jumlah' => 1]);
        Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $a->id, 'nama' => 'Ani', 'status' => 'hadir', 'jumlah' => 2]);
        Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $b->id, 'nama' => 'Budi', 'status' => 'tidak', 'jumlah' => 0]);
        Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => null, 'nama' => 'Anonim', 'status' => 'hadir', 'jumlah' => 4]);

        $c = $this->komponen($user);
        $r = $c->viewData('r');
        $this->assertSame(3, $r['tamu']);
        $this->assertSame(6, $r['jiwaDiundang']);
        $this->assertSame(2, $r['hadir']);
        $this->assertSame(1, $r['tidak']);
        $this->assertSame(1, $r['belum']);
        $this->assertSame(6, $r['orangHadir']);
        $this->assertSame(100, $r['tingkat']);
        $c->assertSee('Anonim')->assertSee('Ani');
    }

    public function test_data_undangan_lain_tidak_ikut_terhitung_atau_tampil(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $this->tamu($lain, ['nama' => 'Rahasia Orang']);
        Rsvp::create(['undangan_id' => $lain->id, 'nama' => 'Jawaban Rahasia', 'status' => 'hadir', 'jumlah' => 1]);

        $c = $this->komponen($user)->call('gantiTab', 'tamu');
        $this->assertSame(0, $c->viewData('r')['tamu']);
        $c->assertDontSee('Rahasia Orang')->assertDontSee('Jawaban Rahasia');
        $this->assertSame(0, $u->tamu()->count());
    }

    public function test_cari_filter_status_grup_dan_paginasi(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        for ($i = 1; $i <= 12; $i++) {
            $this->tamu($u, ['nama' => 'Tamu '.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'grup' => $i % 2 ? 'Keluarga' : 'Teman']);
        }
        $x = $this->tamu($u, ['nama' => 'Zed 100%', 'grup' => 'Kolega']);
        Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $x->id, 'nama' => 'Zed 100%', 'status' => 'hadir', 'jumlah' => 1]);

        $c = $this->komponen($user)->call('gantiTab', 'tamu');
        $this->assertSame(13, $c->viewData('total'));
        $this->assertSame(2, $c->viewData('halamanMaks'));
        $this->assertCount(10, $c->viewData('baris'));
        $c->call('berikutnya');
        $this->assertCount(3, $c->viewData('baris'));

        $c->set('cari', 'tamu 05');
        $this->assertSame(1, $c->viewData('total'));
        $this->assertSame(1, $c->get('halaman'));

        $c->set('cari', '100%');
        $this->assertSame(1, $c->viewData('total'));
        $c->set('cari', '%');
        $this->assertSame(1, $c->viewData('total'));
        $c->set('cari', '_');
        $this->assertSame(0, $c->viewData('total'));

        $c->call('aturUlangFilter')->set('grup', 'Kolega');
        $this->assertSame(1, $c->viewData('total'));
        $c->call('aturUlangFilter')->call('pilihStatus', 'hadir');
        $this->assertSame(1, $c->viewData('total'));
        $c->call('pilihStatus', 'belum');
        $this->assertSame(12, $c->viewData('total'));
        $c->call('pilihStatus', 'sembarang');
        $this->assertSame('semua', $c->get('status'));
    }

    // ---------------------------------------------------------------- tambah / ubah / hapus tamu

    public function test_tambah_tamu_membuat_kode_unik_otomatis_dan_menormalkan_nomor(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $this->komponen($user)->call('bukaTambah')
            ->set('nama', '  Budi   Santoso ')->set('hp', '0812-3456-7890')->set('namaGrup', 'Keluarga')->set('jumlah', 3)
            ->call('simpanTamu')->assertHasNoErrors()->assertSet('tampilForm', false);

        $t = $u->tamu()->first();
        $this->assertSame('Budi Santoso', $t->nama);
        $this->assertSame('6281234567890', $t->hp);
        $this->assertSame(3, $t->jumlah);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{10}$/', $t->kode);
    }

    public function test_kode_tamu_tidak_bisa_diubah_lewat_klien_atau_ubah_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u);
        $kode = $t->kode;

        $c = $this->komponen($user)->call('bukaUbah', $t->id)->set('nama', 'Nama Baru')->call('simpanTamu')->assertHasNoErrors();
        $this->assertSame($kode, $t->fresh()->kode);
        $this->assertSame('Nama Baru', $t->fresh()->nama);

        $this->expectException(PublicPropertyNotFoundException::class);
        $c->set('kode', 'hack');
    }

    public function test_validasi_tambah_tamu(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $c = $this->komponen($user)->call('bukaTambah');

        $c->set('nama', '')->call('simpanTamu')->assertHasErrors(['nama' => 'required']);
        $c->set('nama', str_repeat('a', 101))->call('simpanTamu')->assertHasErrors(['nama' => 'max']);
        $c->set('nama', 'Ani')->set('hp', 'abc')->call('simpanTamu')->assertHasErrors('hp');
        $c->set('hp', '')->set('jumlah', 0)->call('simpanTamu')->assertHasErrors('jumlah');
        $c->set('jumlah', 21)->call('simpanTamu')->assertHasErrors('jumlah');
        $c->set('jumlah', 1)->set('namaGrup', str_repeat('g', 61))->call('simpanTamu')->assertHasErrors('namaGrup');
        $this->assertSame(0, Tamu::count());
    }

    public function test_tamu_duplikat_ditolak_tapi_nama_sama_nomor_beda_boleh(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => 'Ani', 'hp' => '6281111111111']);

        $c = $this->komponen($user)->call('bukaTambah')->set('nama', 'ani')->set('hp', '081111111111')->call('simpanTamu');
        $c->assertHasErrors('nama');
        $this->assertSame(1, Tamu::count());

        $c->set('hp', '082222222222')->call('simpanTamu')->assertHasNoErrors();
        $this->assertSame(2, Tamu::count());
    }

    public function test_ubah_dan_hapus_tamu_orang_lain_ditolak(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $t = $this->tamu($lain, ['nama' => 'Orang Lain']);

        foreach (['bukaUbah', 'mintaHapus', 'bukaUbahRsvp', 'tandaiDiingatkan'] as $aksi) {
            $this->komponen($user)->call($aksi, $t->id)->assertNotFound();
        }
        $this->assertSame('Orang Lain', $t->fresh()->nama);
        $this->assertNull($t->fresh()->diingatkan_at);
    }

    public function test_hapus_tamu_menyimpan_rsvp_sebagai_anonim(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u);
        $r = Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $t->id, 'nama' => 'Ani', 'status' => 'hadir', 'jumlah' => 1]);

        $this->komponen($user)->call('mintaHapus', $t->id)->assertSet('tampilHapus', true)->call('hapusTamu')->assertSet('tampilHapus', false);

        $this->assertNull(Tamu::find($t->id));
        $this->assertNull($r->fresh()->tamu_id);
    }

    public function test_hapus_massal_hanya_menghapus_tamu_sendiri(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $a = $this->tamu($u);
        $b = $this->tamu($u);
        $tetap = $this->tamu($u);
        $asing = $this->tamu($lain);

        $this->komponen($user)->call('gantiTab', 'tamu')
            ->set('terpilih', [(string) $a->id, $b->id, $asing->id, 'x'])
            ->call('mintaHapusMassal')->assertSet('tampilHapusMassal', true)
            ->call('hapusMassal')->assertSet('tampilHapusMassal', false)->assertSet('terpilih', []);

        $this->assertNull(Tamu::find($a->id));
        $this->assertNull(Tamu::find($b->id));
        $this->assertNotNull(Tamu::find($tetap->id));
        $this->assertNotNull(Tamu::find($asing->id));
    }

    public function test_hapus_massal_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $c = $this->komponen($user);
        for ($i = 0; $i < 11; $i++) {
            $c->call('hapusMassal');
        }
        $c->assertDispatched('toast', pesan: 'Terlalu banyak penghapusan massal. Coba lagi sebentar lagi.', tipe: 'galat');
    }

    // ---------------------------------------------------------------- kuota

    public function test_kuota_tamu_membatasi_bila_pengaturan_ada(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota_tamu' => ['gratis' => 2]]);
        $this->tamu($u);
        $this->tamu($u);

        $this->komponen($user)->call('bukaTambah')->set('nama', 'Ketiga')->call('simpanTamu')->assertHasErrors('nama');
        $this->assertSame(2, $u->tamu()->count());
    }

    public function test_tanpa_pengaturan_kuota_tanpa_batas(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 1]]);
        for ($i = 0; $i < 3; $i++) {
            $this->tamu($u);
        }
        $this->komponen($user)->call('bukaTambah')->set('nama', 'Keempat')->call('simpanTamu')->assertHasNoErrors();
        $this->assertSame(4, $u->tamu()->count());
    }

    // ---------------------------------------------------------------- impor CSV

    public function test_impor_csv_pratinjau_lapor_gagal_dan_deduplikasi(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => 'Sudah Ada', 'hp' => null]);
        $isi = "nama,hp,grup,jumlah\nBudi,081234567890,Keluarga,2\nbudi,081234567890,Keluarga,2\nSudah Ada,,,\n,0812,,\nCici,12,,\nDedi,,Teman,abc\nEko,,Kolega,\n";

        $c = $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $this->csv($isi));
        $this->assertCount(2, $c->get('impor'));
        $this->assertSame(2, $c->get('imporDuplikat'));
        $this->assertSame([5, 6, 7], array_column($c->get('imporGagal'), 'baris'));
        $c->assertSee('2 siap diimpor')->assertSee('3 baris gagal');

        $c->call('jalankanImpor')->assertSet('tampilImpor', false);
        $this->assertSame(3, $u->tamu()->count());
        $b = $u->tamu()->where('nama', 'Budi')->first();
        $this->assertSame('6281234567890', $b->hp);
        $this->assertSame(2, $b->jumlah);
        $this->assertSame(1, $u->tamu()->where('nama', 'Eko')->first()->jumlah);
        $this->assertSame(3, $u->tamu()->pluck('kode')->unique()->count());
    }

    public function test_impor_menerima_titik_koma_tanpa_judul_dan_encoding_windows(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $isi = "Siti;0811111111;Teman;1\nJos\xE9 Alvarez;;;3\n";

        $c = $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $this->csv($isi));
        $this->assertSame(['Siti', 'José Alvarez'], array_column($c->get('impor'), 'nama'));
    }

    public function test_impor_menolak_berkas_terlalu_besar_atau_terlalu_banyak_baris_atau_bukan_csv(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);

        $banyak = "nama\n".implode("\n", array_map(fn ($i) => 'Tamu '.$i, range(1, 501)));
        $c = $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $this->csv($banyak));
        $this->assertSame([], $c->get('impor'));
        $c->assertSee('Terlalu banyak baris');

        $besar = UploadedFile::fake()->create('besar.csv', 300, 'text/csv');
        $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $besar)->assertHasErrors('berkasCsv');

        $gambar = UploadedFile::fake()->image('x.png');
        $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $gambar)->assertHasErrors('berkasCsv');

        $kosong = $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $this->csv("\n\n"));
        $kosong->assertSee('Berkas kosong');
        $this->assertSame(0, Tamu::count());
    }

    public function test_impor_menghormati_kuota_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota_tamu' => ['gratis' => 2]]);
        $this->tamu($u);

        $this->komponen($user)->call('bukaImpor')->set('berkasCsv', $this->csv("nama\nA\nB\nC\n"))->call('jalankanImpor');
        $this->assertSame(2, $u->tamu()->count());
    }

    public function test_impor_tanpa_pratinjau_tidak_berbuat_apa_apa_dan_pratinjau_terkunci(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $c = $this->komponen($user)->call('jalankanImpor');
        $this->assertSame(0, $u->tamu()->count());

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('impor', [['nama' => 'Palsu', 'hp' => null, 'grup' => null, 'jumlah' => 1]]);
    }

    // ---------------------------------------------------------------- ekspor CSV

    public function test_ekspor_tamu_aman_dari_csv_injection(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => '=HYPERLINK("http://x")', 'grup' => '+cmd', 'hp' => null]);
        $this->tamu($u, ['nama' => '-1+1', 'grup' => '@SUM(A1)', 'hp' => '6281234567890']);
        $this->tamu($u, ['nama' => 'Aman', 'grup' => 'Biasa']);

        $res = $this->komponen($user)->call('gantiTab', 'tamu')->call('ekspor');
        $res->assertFileDownloaded('tamu-raka-sinta-'.now()->format('Ymd').'.csv');
        $isi = $res->effects['download']['content'] ?? null;
        $this->assertNotNull($isi);
        $teks = base64_decode($isi);
        $this->assertStringContainsString("'=HYPERLINK", $teks);
        $this->assertStringContainsString("'+cmd", $teks);
        $this->assertStringContainsString("'-1+1", $teks);
        $this->assertStringContainsString("'@SUM(A1)", $teks);
        $this->assertStringNotContainsString(',=HYPERLINK', $teks);
        $this->assertStringContainsString('Aman', $teks);
        $this->assertStringContainsString('?to=', $teks);
    }

    public function test_ekspor_hanya_memuat_data_undangan_sendiri_dan_mengikuti_filter(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $this->tamu($u, ['nama' => 'Milik Saya']);
        $this->tamu($lain, ['nama' => 'Milik Orang Lain']);
        Rsvp::create(['undangan_id' => $u->id, 'nama' => 'Jawab Saya', 'status' => 'hadir', 'jumlah' => 2, 'pesan' => 'Sampai jumpa']);
        Rsvp::create(['undangan_id' => $u->id, 'nama' => 'Tidak Datang', 'status' => 'tidak', 'jumlah' => 0]);

        $res = $this->komponen($user)->call('pilihStatus', 'hadir')->call('ekspor');
        $teks = base64_decode($res->effects['download']['content']);
        $this->assertStringContainsString('Jawab Saya', $teks);
        $this->assertStringNotContainsString('Tidak Datang', $teks);
        $this->assertStringNotContainsString('Milik Orang Lain', $teks);
    }

    public function test_ekspor_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        $this->terbit($user);
        $c = $this->komponen($user);
        for ($i = 0; $i < 11; $i++) {
            $c->call('ekspor');
        }
        $c->assertDispatched('toast', tipe: 'galat');
    }

    public function test_helper_csv_sel(): void
    {
        foreach (['=1', '+1', '-1', '@a', "\t1", "\r1"] as $v) {
            $this->assertSame("'".$v, CsvTamu::sel($v));
        }
        $this->assertSame('aman=1', CsvTamu::sel('aman=1'));
        $this->assertSame('5', CsvTamu::sel(5));
        $this->assertSame('', CsvTamu::sel(null));
    }

    // ---------------------------------------------------------------- RSVP: manual, balik, hapus

    public function test_tandai_hadir_manual_membuat_dan_mengubah_rsvp(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, ['nama' => 'Ani', 'jumlah' => 3]);

        $c = $this->komponen($user)->call('bukaUbahRsvp', $t->id)->assertSet('tampilUbahRsvp', true)->assertSet('rsvpJumlah', 3);
        $c->set('rsvpStatus', 'hadir')->set('rsvpJumlah', 2)->call('simpanRsvp')->assertHasNoErrors();
        $r = Rsvp::where('tamu_id', $t->id)->first();
        $this->assertSame(['hadir', 2, $t->kode], [$r->status, $r->jumlah, $r->kode_tamu]);

        $c->call('bukaUbahRsvp', $t->id)->set('rsvpStatus', 'tidak')->call('simpanRsvp');
        $this->assertSame(1, Rsvp::where('tamu_id', $t->id)->count());
        $r->refresh();
        $this->assertSame(['tidak', 0], [$r->status, $r->jumlah]);
    }

    public function test_validasi_ubah_rsvp(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u);
        $c = $this->komponen($user)->call('bukaUbahRsvp', $t->id);
        $c->set('rsvpStatus', 'mungkin')->call('simpanRsvp')->assertHasErrors('rsvpStatus');
        $c->set('rsvpStatus', 'hadir')->set('rsvpJumlah', 99)->call('simpanRsvp')->assertHasErrors('rsvpJumlah');
        $this->assertSame(0, Rsvp::count());
    }

    public function test_balik_status_dan_hapus_jawaban_serta_hak_akses(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');
        $r = Rsvp::create(['undangan_id' => $u->id, 'nama' => 'Ani', 'status' => 'hadir', 'jumlah' => 2]);
        $asing = Rsvp::create(['undangan_id' => $lain->id, 'nama' => 'Asing', 'status' => 'hadir', 'jumlah' => 2]);

        $c = $this->komponen($user)->call('balikStatus', $r->id);
        $this->assertSame(['tidak', 0], [$r->fresh()->status, $r->fresh()->jumlah]);
        $c->call('balikStatus', $r->id);
        $this->assertSame(['hadir', 1], [$r->fresh()->status, $r->fresh()->jumlah]);

        foreach (['balikStatus', 'mintaHapusRsvp'] as $aksi) {
            $this->komponen($user)->call($aksi, $asing->id)->assertNotFound();
        }
        $this->assertSame('hadir', $asing->fresh()->status);

        $c->call('mintaHapusRsvp', $r->id)->assertSet('tampilHapusRsvp', true)->call('hapusRsvp')->assertSet('tampilHapusRsvp', false);
        $this->assertNull(Rsvp::find($r->id));
        $this->assertNotNull(Rsvp::find($asing->id));
    }

    // ---------------------------------------------------------------- tautan dan pengingat

    public function test_tautan_pribadi_memakai_kode_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->tamu($u, ['nama' => 'Ani']);

        $this->komponen($user)->call('gantiTab', 'tamu')->assertSee(str_replace('/', '\/', route('undangan.publik', $u->slug).'?to='.$t->kode), false);
    }

    public function test_pengingat_menandai_tamu_belum_rsvp(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $belum = $this->tamu($u, ['nama' => 'Belum Jawab']);
        $sudah = $this->tamu($u, ['nama' => 'Sudah Jawab']);
        Rsvp::create(['undangan_id' => $u->id, 'tamu_id' => $sudah->id, 'nama' => 'Sudah Jawab', 'status' => 'hadir', 'jumlah' => 1]);

        $c = $this->komponen($user)->set('tampilPengingat', true)->assertSee('Belum Jawab');
        $this->assertCount(1, $c->viewData('pengingat'));
        $c->assertSeeHtml('https://wa.me/'.$belum->hp.'?text=');

        $c->call('tandaiDiingatkan', $belum->id);
        $this->assertNotNull($belum->fresh()->diingatkan_at);
        $this->assertNull($sudah->fresh()->diingatkan_at);
    }

    public function test_nama_tamu_di_tampilan_di_escape(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $this->tamu($u, ['nama' => '<script>alert(1)</script>']);

        $this->komponen($user)->call('gantiTab', 'tamu')->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('&lt;script&gt;', false);
    }
}
