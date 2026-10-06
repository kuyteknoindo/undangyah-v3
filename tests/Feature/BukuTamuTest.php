<?php

namespace Tests\Feature;

use App\Livewire\BukuTamu;
use App\Models\Rsvp;
use App\Models\Tema;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\BukuTamuPengaturan;
use App\Services\PenerbitUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class BukuTamuTest extends TestCase
{
    use RefreshDatabase;

    private const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(BukuTamu::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function undangan(User $user, array $lain = []): Undangan
    {
        return Undangan::factory()->terbit()->create(array_merge(['user_id' => $user->id, 'slug' => 'raka-sinta', 'judul' => 'Raka & Sinta'], $lain));
    }

    private function ucapan(Undangan $u, string $nama, string $pesan = 'Selamat ya', array $lain = []): Ucapan
    {
        return Ucapan::create(array_merge(['undangan_id' => $u->id, 'nama' => $nama, 'pesan' => $pesan], $lain));
    }

    /** Undangan terbit lengkap supaya halaman publik bisa dirender. */
    private function terbitPublik(User $pemilik, array $pengaturan = []): Undangan
    {
        $tema = Tema::factory()->create();
        $isi = SkemaUndangan::isiAwal();
        $bagian = ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];

        $u = Undangan::factory()->for($pemilik, 'pengguna')->dengan($tema)->create([
            'judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'status' => Undangan::TERBIT, 'diterbitkan_at' => now(),
            'isi' => $isi, 'bagian' => $bagian, 'pengaturan' => $pengaturan,
            'terbitan' => ['judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'isi' => $isi, 'bagian' => $bagian, 'pengaturan' => $pengaturan,
                'tema' => $tema->only(['kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul']),
                'diterbitkan_at' => now()->toIso8601String()],
        ]);
        $u->forceFill(['terbitan' => [...$u->terbitan, 'tanda' => app(PenerbitUndangan::class)->tanda($u)]])->save();

        return $u;
    }

    // ------------------------------------------------------------------ akses

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/buku-tamu')->assertRedirect(route('masuk'));
    }

    public function test_rute_asli_bukan_placeholder(): void
    {
        $this->assertSame('/buku-tamu', route('buku-tamu', absolute: false));
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->actingAs($user)->get('/buku-tamu')->assertOk()->assertSeeLivewire(BukuTamu::class);
        $this->actingAs($user)->get('/buku-tamu/'.$u->id)->assertOk();
    }

    public function test_keadaan_kosong_tanpa_undangan(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Belum ada undangan')->assertSet('undanganId', null);
    }

    public function test_undangan_orang_lain_404_di_rute(): void
    {
        $lain = $this->undangan(User::factory()->create());

        $this->actingAs(User::factory()->create())->get('/buku-tamu/'.$lain->id)->assertNotFound();
    }

    public function test_pilih_undangan_orang_lain_404_dan_id_terkunci(): void
    {
        $user = User::factory()->create();
        $punya = $this->undangan($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);

        $this->komponen($user)->assertSet('undanganId', $punya->id)->call('pilihUndangan', $lain->id)->assertStatus(404);

        $c = $this->komponen($user);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('undanganId', $lain->id);
    }

    public function test_hanya_ucapan_undangan_sendiri_yang_tampil(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);
        $this->ucapan($u, 'Sari', 'Barakallah untuk kalian');
        $this->ucapan($lain, 'Rahasia', 'Pesan undangan orang lain');

        $this->komponen($user)->assertSee('Sari')->assertSee('Barakallah untuk kalian')->assertDontSee('Pesan undangan orang lain');
    }

    public function test_pesan_dibersihkan_dari_html(): void
    {
        $user = User::factory()->create();
        $this->ucapan($this->undangan($user), 'Budi', '<script>alert(1)</script>');

        $this->komponen($user)->assertDontSeeHtml('<script>alert(1)</script>')->assertSee('&lt;script&gt;', false);
    }

    // ------------------------------------------------------------------ moderasi

    public function test_sembunyikan_dan_tampilkan(): void
    {
        $user = User::factory()->create();
        $x = $this->ucapan($this->undangan($user), 'Sari');

        $c = $this->komponen($user)->call('sembunyikan', $x->id);
        $this->assertTrue($x->fresh()->disembunyikan);
        $c->call('sembunyikan', $x->id);
        $this->assertFalse($x->fresh()->disembunyikan);
    }

    public function test_sematkan_lepas_dan_batas_maksimal(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $semua = collect(range(1, Ucapan::MAKS_SEMATAN + 1))->map(fn ($i) => $this->ucapan($u, 'Tamu '.$i));

        $c = $this->komponen($user);
        foreach ($semua->take(Ucapan::MAKS_SEMATAN) as $x) {
            $c->call('sematkan', $x->id);
        }
        $this->assertSame(Ucapan::MAKS_SEMATAN, Ucapan::where('disematkan', true)->count());

        $c->call('sematkan', $semua->last()->id)->assertDispatched('toast', pesan: 'Maksimal '.Ucapan::MAKS_SEMATAN.' ucapan yang disematkan. Lepas satu dulu.', tipe: 'galat');
        $this->assertFalse($semua->last()->fresh()->disematkan);

        $c->call('sematkan', $semua->first()->id);
        $this->assertFalse($semua->first()->fresh()->disematkan);
    }

    public function test_sematan_dan_sembunyi_saling_meniadakan(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $a = $this->ucapan($u, 'A', 'x', ['disematkan' => true]);
        $b = $this->ucapan($u, 'B', 'x', ['disembunyikan' => true]);

        $c = $this->komponen($user)->call('sembunyikan', $a->id)->call('sematkan', $b->id);

        $this->assertTrue($a->fresh()->disembunyikan);
        $this->assertFalse($a->fresh()->disematkan);
        $this->assertTrue($b->fresh()->disematkan);
        $this->assertFalse($b->fresh()->disembunyikan);
    }

    public function test_hapus_dengan_konfirmasi_dan_urungkan(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $x = $this->ucapan($u, 'Sari', 'Doa terbaik', ['disematkan' => true]);

        $c = $this->komponen($user)->call('tanyaHapus', $x->id)->assertSet('hapusId', $x->id)->assertSee('Hapus ucapan ini?');
        $this->assertSame(1, Ucapan::count(), 'belum dihapus sebelum dikonfirmasi');

        $c->call('batalHapus')->assertSet('hapusId', null);
        $this->assertSame(1, Ucapan::count());

        $c->call('tanyaHapus', $x->id)->call('hapus')->assertSee('Urungkan');
        $this->assertSame(0, Ucapan::count());

        $c->call('urungkan')->assertSet('terhapus', null);
        $pulih = Ucapan::firstOrFail();
        $this->assertSame($x->id, $pulih->id);
        $this->assertSame('Doa terbaik', $pulih->pesan);
        $this->assertTrue($pulih->disematkan);
    }

    public function test_aksi_pada_ucapan_orang_lain_404_dan_tidak_berubah(): void
    {
        $user = User::factory()->create();
        $this->undangan($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);
        $asing = $this->ucapan($lain, 'Asing');

        $c = $this->komponen($user);
        foreach (['sembunyikan', 'sematkan', 'tanyaHapus'] as $aksi) {
            $c->call($aksi, $asing->id)->assertStatus(404);
        }
        $this->assertFalse($asing->fresh()->disembunyikan);
        $this->assertFalse($asing->fresh()->disematkan);
        $this->assertSame(1, Ucapan::count());
    }

    public function test_id_hapus_dan_salinan_terhapus_tidak_bisa_dimanipulasi_klien(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $this->ucapan($u, 'Sari');
        $c = $this->komponen($user);

        try {
            $c->set('hapusId', 1);
            $this->fail('hapusId harus terkunci');
        } catch (CannotUpdateLockedPropertyException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $c->set('terhapus', ['id' => 99]);
    }

    public function test_urungkan_tidak_membuat_ucapan_di_undangan_lain(): void
    {
        $user = User::factory()->create();
        $a = $this->undangan($user);
        $b = $this->undangan($user, ['slug' => 'kedua']);
        $x = $this->ucapan($a, 'Sari');

        $c = $this->komponen($user, $a->id)->call('tanyaHapus', $x->id)->call('hapus')->assertSee('Urungkan');
        $c->call('pilihUndangan', $b->id)->assertSet('terhapus', null)->call('urungkan');
        $this->assertSame(0, Ucapan::count());
        $this->assertSame(0, Ucapan::where('undangan_id', $b->id)->count());
    }

    // ------------------------------------------------------------------ cari, filter, urut, halaman

    public function test_cari_filter_dan_urut(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $this->ucapan($u, 'Zainal', 'Selamat menempuh hidup baru');
        $this->ucapan($u, 'Anita', 'Semoga sakinah');
        $this->ucapan($u, 'Budi', 'Rahasia 100% asli', ['disembunyikan' => true]);
        $this->ucapan($u, 'Citra', 'Doa tulus', ['disematkan' => true]);

        $this->komponen($user)
            ->set('cari', 'sakinah')->assertSee('Anita')->assertDontSee('Zainal')
            ->set('cari', 'ZAINAL')->assertSee('Zainal')->assertDontSee('Anita')
            ->set('cari', '100%')->assertSee('Budi')->assertDontSee('Citra')
            ->set('cari', '%')->assertSee('Budi')->assertDontSee('Zainal')
            ->set('cari', 'tidak-ada')->assertSee('Ucapan tidak ditemukan')
            ->call('resetFilter')->assertSet('cari', '')->assertSee('Zainal')
            ->call('pilihFilter', 'sembunyi')->assertSee('Budi')->assertDontSee('Zainal')
            ->call('pilihFilter', 'sematan')->assertSee('Citra')->assertDontSee('Budi')
            ->call('pilihFilter', 'tampil')->assertSee('Zainal')->assertDontSee('Budi')
            ->call('pilihFilter', 'ngawur')->assertSet('filter', 'semua')
            ->set('urut', 'nama')->assertSeeInOrder(['Citra', 'Anita', 'Budi', 'Zainal'])
            ->set('urut', 'lama')->assertSeeInOrder(['Citra', 'Zainal', 'Anita', 'Budi'])
            ->set('urut', 'baru')->assertSeeInOrder(['Citra', 'Budi', 'Anita', 'Zainal'])
            ->set('urut', 'aneh')->assertSet('urut', 'baru');
    }

    public function test_paginasi(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        foreach (range(1, 23) as $i) {
            $this->ucapan($u, 'Tamu '.str_pad((string) $i, 2, '0', STR_PAD_LEFT));
        }

        $c = $this->komponen($user)->assertSee('Tamu 23')->assertDontSee('Tamu 13')->assertSee('1 / 3');
        $c->call('ke', 3)->assertSee('Tamu 01')->assertDontSee('Tamu 23');
        $c->call('ke', 99)->assertSee('3 / 3')->assertSee('Tamu 01');
        $c->call('ke', -5)->assertSee('1 / 3');
    }

    public function test_ringkasan_menghitung_di_basis_data(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $this->ucapan($u, 'A');
        $this->ucapan($u, 'B', 'x', ['disembunyikan' => true]);
        $this->ucapan($u, 'C', 'x', ['disematkan' => true]);

        $this->komponen($user)->assertSeeHtmlInOrder(['Total ucapan', '3', 'Tampil', '2', 'Disematkan', '1', 'Disembunyikan', '1']);
    }

    // ------------------------------------------------------------------ pengaturan

    public function test_buka_tutup_dan_setujui_tersimpan_tanpa_menandai_perubahan(): void
    {
        $user = User::factory()->create();
        $u = $this->terbitPublik($user);
        $tandaAwal = $u->terbitan['tanda'];
        $this->assertSame(['buka' => true, 'setujui' => false], app(BukuTamuPengaturan::class)->ambil($u));

        $this->komponen($user)->call('ubahBuka')->call('ubahSetujui');

        $u->refresh();
        $this->assertSame(['buka' => false, 'setujui' => true], app(BukuTamuPengaturan::class)->ambil($u));
        $this->assertSame(['buka' => false, 'setujui' => true], $u->terbitan['pengaturan']['buku_tamu']);
        $this->assertSame(app(PenerbitUndangan::class)->tanda($u), $u->terbitan['tanda']);
        $this->assertNotSame($tandaAwal, $u->terbitan['tanda']);

        $this->komponen($user)->call('ubahBuka');
        $this->assertTrue(app(BukuTamuPengaturan::class)->ambil($u->fresh())['buka']);
    }

    public function test_pengaturan_tidak_menyembunyikan_perubahan_draf_yang_belum_terbit(): void
    {
        $user = User::factory()->create();
        $u = $this->terbitPublik($user);
        $u->forceFill(['judul' => 'Judul draf baru'])->save();
        $tandaTerbitan = $u->fresh()->terbitan['tanda'];

        $this->komponen($user)->call('ubahBuka');

        $this->assertSame($tandaTerbitan, $u->fresh()->terbitan['tanda'], 'tanda lama dipertahankan: perubahan draf tetap terdeteksi');
        $this->assertNotSame(app(PenerbitUndangan::class)->tanda($u->fresh()), $u->fresh()->terbitan['tanda']);
    }

    public function test_pengaturan_hanya_untuk_undangan_sendiri(): void
    {
        $user = User::factory()->create();
        $punya = $this->undangan($user);
        $lain = $this->undangan(User::factory()->create(), ['slug' => 'orang-lain']);

        $this->komponen($user)->call('ubahBuka')->call('ubahSetujui');

        $this->assertSame(['buka' => false, 'setujui' => true], app(BukuTamuPengaturan::class)->ambil($punya->fresh()));
        $this->assertSame(['buka' => true, 'setujui' => false], app(BukuTamuPengaturan::class)->ambil($lain->fresh()));
    }

    // ------------------------------------------------------------------ ekspor

    public function test_ekspor_csv_mengikuti_filter_dan_melindungi_rumus(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);
        $this->ucapan($u, '=HYPERLINK("http://x")', 'Pesan satu');
        $this->ucapan($u, 'Sari', 'Pesan dua');

        $res = $this->komponen($user)->set('cari', 'Pesan satu')->call('ekspor')->assertFileDownloaded();
        $csv = $res->effects['download']['content'] ?? null;
        $this->assertNotNull($csv);
        $isi = base64_decode($csv);
        $this->assertStringContainsString('Pesan satu', $isi);
        $this->assertStringNotContainsString('Pesan dua', $isi);
        $this->assertStringNotContainsString(',=HYPERLINK', $isi);
        $this->assertStringNotContainsString('"=HYPERLINK', $isi);
    }

    public function test_ekspor_kosong_dan_dibatasi(): void
    {
        $user = User::factory()->create();
        $u = $this->undangan($user);

        $this->komponen($user)->call('ekspor')->assertNotDispatched('download')->assertDispatched('toast', pesan: 'Tidak ada ucapan untuk diekspor.', tipe: 'info');

        $this->ucapan($u, 'Sari');
        RateLimiter::clear('buku-tamu-ekspor:'.$user->id);
        $c = $this->komponen($user);
        foreach (range(1, 10) as $i) {
            $c->call('ekspor');
        }
        $c->call('ekspor')->assertDispatched('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');
    }

    // ------------------------------------------------------------------ tersambung ke undangan publik

    private function kirimUcapan(Undangan $u, array $data = [])
    {
        return $this->withHeaders(['User-Agent' => self::UA])->postJson('/'.$u->slug.'/ucapan', array_merge(['nama' => 'Budi', 'pesan' => 'Selamat menempuh hidup baru'], $data));
    }

    public function test_ucapan_tersembunyi_tidak_tampil_di_halaman_dan_daftar_publik(): void
    {
        $u = $this->terbitPublik(User::factory()->create());
        $this->ucapan($u, 'Terlihat', 'Pesan terlihat');
        $this->ucapan($u, 'Disembunyi', 'Pesan disembunyikan pemilik', ['disembunyikan' => true]);

        $this->withHeaders(['User-Agent' => self::UA])->get('/raka-sinta')->assertOk()->assertSee('Pesan terlihat')->assertDontSee('Pesan disembunyikan pemilik');
        $this->getJson('/raka-sinta/ucapan')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.pesan', 'Pesan terlihat');
    }

    public function test_sematan_di_atas_di_halaman_pertama_saja(): void
    {
        $u = $this->terbitPublik(User::factory()->create());
        $this->ucapan($u, 'Lama', 'Ucapan sematan lama', ['disematkan' => true]);
        foreach (range(1, 12) as $i) {
            $this->ucapan($u, 'Tamu', 'Ucapan biasa '.$i);
        }

        $res = $this->withHeaders(['User-Agent' => self::UA])->get('/raka-sinta')->assertOk();
        $res->assertSeeInOrder(['Ucapan sematan lama', 'Ucapan biasa 12']);
        $res->assertDontSee('Ucapan biasa 2<', false);

        $dua = $this->getJson('/raka-sinta/ucapan?sebelum='.Ucapan::where('pesan', 'Ucapan biasa 3')->value('id'))->assertOk();
        $pesan = collect($dua->json('data'))->pluck('pesan')->all();
        $this->assertSame(['Ucapan biasa 2', 'Ucapan biasa 1'], $pesan, 'sematan tidak diulang di halaman berikutnya');
    }

    public function test_ucapan_baru_ditolak_bila_kolom_ditutup(): void
    {
        $u = $this->terbitPublik(User::factory()->create(), ['buku_tamu' => ['buka' => false, 'setujui' => false]]);

        $this->kirimUcapan($u)->assertForbidden()->assertJsonPath('pesan', 'Kolom ucapan sedang ditutup oleh pemilik undangan.');
        $this->assertSame(0, Ucapan::count());
    }

    public function test_pesan_rsvp_tidak_jadi_ucapan_bila_ditutup_tetapi_rsvp_tetap_tercatat(): void
    {
        $u = $this->terbitPublik(User::factory()->create(), ['buku_tamu' => ['buka' => false, 'setujui' => false]]);

        $this->withHeaders(['User-Agent' => self::UA])->postJson('/raka-sinta/rsvp', ['nama' => 'Budi', 'status' => 'hadir', 'jumlah' => 2, 'pesan' => 'Insya Allah hadir'])->assertOk();

        $this->assertSame(1, Rsvp::count());
        $this->assertSame(0, Ucapan::count());
    }

    public function test_mode_setujui_membuat_ucapan_baru_tersembunyi(): void
    {
        $u = $this->terbitPublik(User::factory()->create(), ['buku_tamu' => ['buka' => true, 'setujui' => true]]);

        $this->kirimUcapan($u)->assertOk()->assertJsonPath('ucapan', null);
        $x = Ucapan::firstOrFail();
        $this->assertTrue($x->disembunyikan);
        $this->getJson('/raka-sinta/ucapan')->assertJsonCount(0, 'data');

        $this->withHeaders(['User-Agent' => self::UA.' v2'])->postJson('/raka-sinta/rsvp', ['nama' => 'Sari', 'status' => 'hadir', 'jumlah' => 1, 'pesan' => 'Sampai jumpa'])->assertOk()->assertJsonPath('ucapan', null);
        $this->assertSame(2, Ucapan::where('disembunyikan', true)->count());
    }

    public function test_ucapan_baru_langsung_tampil_secara_bawaan(): void
    {
        $u = $this->terbitPublik(User::factory()->create());

        $this->kirimUcapan($u)->assertOk()->assertJsonPath('ucapan.nama', 'Budi');
        $this->assertFalse(Ucapan::firstOrFail()->disembunyikan);
    }

    public function test_pemilik_menyetujui_lewat_halaman_lalu_tampil_di_publik(): void
    {
        $user = User::factory()->create();
        $u = $this->terbitPublik($user, ['buku_tamu' => ['buka' => true, 'setujui' => true]]);
        $this->kirimUcapan($u)->assertOk();

        $this->komponen($user)->assertSee('Disembunyikan')->call('sembunyikan', Ucapan::firstOrFail()->id);

        $this->getJson('/raka-sinta/ucapan')->assertJsonCount(1, 'data');
    }
}
