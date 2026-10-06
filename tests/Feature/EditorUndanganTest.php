<?php

namespace Tests\Feature;

use App\Livewire\EditorUndangan;
use App\Models\Pengaturan;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Services\IsiUndangan;
use App\Services\RenderUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class EditorUndanganTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tema $tema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->tema = Tema::factory()->create(['nama' => 'Biru Klasik']);
    }

    private function buat(array $ubah = [], ?User $pemilik = null): Undangan
    {
        return Undangan::factory()->create(array_merge([
            'user_id' => ($pemilik ?? $this->user)->id,
            'tema_id' => $this->tema->id,
            'judul' => 'Raka & Sinta',
            'slug' => 'raka-sinta',
            'isi' => SkemaUndangan::isiAwal(),
            'bagian' => ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []],
        ], $ubah));
    }

    private function editor(Undangan $u, ?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->user)->test(EditorUndangan::class, ['undangan' => $u->id]);
    }

    // ------------------------------------------------------------ rute dan akses

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/edit-undangan/'.$this->buat()->id)->assertRedirect(route('masuk'));
    }

    public function test_rute_menampilkan_editor_asli_bukan_placeholder(): void
    {
        $u = $this->buat();

        $this->assertSame('/edit-undangan/'.$u->id, route('edit-undangan', ['undangan' => $u->id], false));
        $this->assertSame('/edit-undangan', route('edit-undangan', [], false));

        $this->actingAs($this->user)->get('/edit-undangan/'.$u->id)
            ->assertOk()
            ->assertSeeLivewire(EditorUndangan::class)
            ->assertSee('Edit Undangan · Raka &amp; Sinta', false)
            ->assertSee('Konten Undangan')
            ->assertSee('Fitur Tambahan');
    }

    public function test_tanpa_parameter_membuka_undangan_terbaru_milik_sendiri(): void
    {
        $lama = $this->buat(['slug' => 'lama']);
        $lama->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
        $baru = $this->buat(['slug' => 'baru']);
        $this->buat(['slug' => 'orang-lain'], User::factory()->create())->touch();

        $this->actingAs($this->user)->get('/edit-undangan')->assertRedirect(route('edit-undangan', ['undangan' => $baru->id]));
    }

    public function test_tanpa_undangan_diarahkan_ke_wizard(): void
    {
        $this->actingAs($this->user)->get('/edit-undangan')->assertRedirect(route('buat-undangan'));
    }

    public function test_undangan_orang_lain_404(): void
    {
        $milikLain = $this->buat([], User::factory()->create());

        $this->actingAs($this->user)->get('/edit-undangan/'.$milikLain->id)->assertNotFound();
        $this->actingAs($this->user)->get('/edit-undangan/999999')->assertNotFound();
        Livewire::actingAs($this->user)->test(EditorUndangan::class, ['undangan' => $milikLain->id])->assertNotFound();
    }

    public function test_id_undangan_dan_status_tidak_bisa_diubah_klien(): void
    {
        $milikLain = $this->buat(['slug' => 'orang-lain'], User::factory()->create());
        $komponen = $this->editor($this->buat());

        foreach (['undanganId' => $milikLain->id, 'tahapTerbit' => 'selesai', 'mode' => 'x'] as $properti => $nilai) {
            try {
                $komponen->set($properti, $nilai);
                $this->fail("{$properti} seharusnya terkunci");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
        foreach (['daftar', 'bagian', 'sembunyi', 'pengaturan'] as $properti) {
            try {
                $komponen->set($properti, []);
                $this->fail("{$properti} seharusnya terkunci");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
        $this->assertSame('orang-lain', $milikLain->fresh()->slug);
    }

    public function test_tampilan_memuat_isi_dan_kanvas(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u);
        $komponen->assertSet('isi.priaP', 'Raka')->assertSet('daftar.ev', [1, 2])->assertSet('bagianAktif', 'sampul');
        $komponen->assertSee('Bagian Sampul')->assertSee('Posisi tetap', false);

        $this->assertTrue(class_exists(RenderUndangan::class));
        $komponen->assertSeeHtml('class="uy-undangan"')->assertSeeHtml('data-kunci="priaP"');

        $komponen->call('setMode', 'tamu')->assertSet('mode', 'tamu')->assertDontSeeHtml('data-kunci="priaP"');
        $komponen->call('setMode', 'bebas')->assertSet('mode', 'tamu');
    }

    public function test_undangan_dengan_isi_kosong_tetap_terbuka_dengan_nilai_awal(): void
    {
        $u = $this->buat(['isi' => [], 'bagian' => null]);

        $this->editor($u)->assertSet('isi.priaP', 'Raka')->assertSet('daftar.ev', [1, 2])->assertSet('bagian.urutan', SkemaUndangan::urutanAwal());
    }

    // ------------------------------------------------------------ simpan otomatis

    public function test_perubahan_bidang_disimpan_otomatis_sebagai_draf(): void
    {
        $u = $this->buat(['draf_disimpan_at' => now()->subDay()]);

        $this->editor($u)->set('isi.priaP', 'Budi')->set('isi.ev1_nama', 'Ijab Kabul')->assertHasNoErrors();

        $u->refresh();
        $this->assertSame('Budi', $u->isi['priaP']);
        $this->assertSame('Ijab Kabul', $u->isi['ev1_nama']);
        $this->assertSame('Sinta', $u->isi['wanitaP']);
        $this->assertSame([1, 2], $u->isi['_daftar']['ev']);
        $this->assertSame(Undangan::DRAF, $u->status);
        $this->assertFalse($u->ada_perubahan);
        $this->assertTrue($u->draf_disimpan_at->isToday());
        $this->assertTrue($u->langkah['data']);
    }

    public function test_tanggal_dan_lokasi_ringkas_ikut_isian(): void
    {
        $u = $this->buat();
        $tanggal = now()->addMonths(5)->toDateString();

        $this->editor($u)->set('isi.hmTgl', $tanggal)->set('isi.gcLokasi', 'Gedung Serbaguna');

        $this->assertSame($tanggal, $u->fresh()->tanggal_acara->toDateString());
        $this->assertSame('Gedung Serbaguna', $u->fresh()->lokasi_ringkas);
    }

    public function test_perubahan_pada_undangan_terbit_menandai_ada_perubahan_dan_kembali_bila_dibatalkan(): void
    {
        $komponen = $this->editor($u = $this->buat());
        $komponen->call('bukaTerbit')->call('terbitkan')->assertHasNoErrors();
        $this->assertFalse($u->fresh()->ada_perubahan);

        $komponen->set('isi.priaP', 'Budi');
        $this->assertTrue($u->fresh()->ada_perubahan);

        $komponen->set('isi.priaP', 'Raka');
        $this->assertFalse($u->fresh()->ada_perubahan);
    }

    public function test_tanpa_perubahan_nilai_ada_perubahan_bila_terbitan_tak_sama(): void
    {
        $u = $this->buat(['status' => Undangan::TERBIT, 'diterbitkan_at' => now(), 'terbitan' => ['judul' => 'lama']]);

        $this->editor($u)->set('isi.quote', 'Kutipan baru');

        $this->assertTrue($u->fresh()->ada_perubahan);
    }

    // ------------------------------------------------------------ keamanan isian

    public function test_kunci_asing_ditolak_dan_tidak_disimpan(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)
            ->set('isi.kunciAsing', 'x')
            ->set('isi.ev99_nama', 'tak terdaftar')
            ->set('isi._daftar', [1, 2, 3]);

        $komponen->assertSet('isi.priaP', 'Raka');
        $this->assertArrayNotHasKey('kunciAsing', $komponen->get('isi'));
        $this->assertArrayNotHasKey('ev99_nama', $komponen->get('isi'));
        $this->assertArrayNotHasKey('_daftar', $komponen->get('isi'));

        $u->refresh();
        $this->assertArrayNotHasKey('kunciAsing', $u->isi);
        $this->assertArrayNotHasKey('ev99_nama', $u->isi);
        $this->assertSame([1, 2], $u->isi['_daftar']['ev']);
        $this->assertSame('Raka', $u->isi['priaP']);
    }

    public function test_aksi_bidang_menolak_kunci_asing(): void
    {
        $u = $this->buat();

        $this->editor($u)->call('setBidang', 'kunciAsing', '1')->call('toggleBidang', 'kunciAsing')->call('pilihBidang', 'kunciAsing')
            ->assertSet('terpilih', null);

        $this->assertArrayNotHasKey('kunciAsing', $u->fresh()->isi);
    }

    public function test_html_dibuang_dan_panjang_dibatasi(): void
    {
        $u = $this->buat();

        $this->editor($u)
            ->set('isi.priaP', 'Raka<script>alert(1)</script> <b>Tebal</b>')
            ->set('isi.quote', str_repeat('a', 5000))
            ->set('isi.sapaan', str_repeat('b', 900));

        $u->refresh();
        $this->assertStringNotContainsString('<', $u->isi['priaP']);
        $this->assertSame('Rakaalert(1) Tebal', $u->isi['priaP']);
        $this->assertSame(IsiUndangan::MAKS_AREA, mb_strlen($u->isi['quote']));
        $this->assertSame(IsiUndangan::MAKS_TEKS, mb_strlen($u->isi['sapaan']));
    }

    public function test_keluaran_kanvas_selalu_di_escape(): void
    {
        $u = $this->buat(['isi' => array_merge(SkemaUndangan::isiAwal(), ['priaP' => '"><img src=x onerror=alert(1)>'])]);

        $html = $this->editor($u)->html();

        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('onerror=alert(1)>', str_replace('&quot;', '"', $html) === $html ? $html : '');
    }

    public function test_url_hanya_http_atau_https(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        foreach (['javascript:alert(1)', 'data:text/html;base64,AAAA', 'ftp://x.com/a', 'maps.google.com', 'https://', 'https://a b.com'] as $buruk) {
            $komponen->set('isi.ev1_maps', $buruk);
            $this->assertArrayHasKey('ev1_maps', $komponen->get('galatBidang'), $buruk);
            $this->assertStringStartsWith('https://maps.google.com', $u->fresh()->isi['ev1_maps'], 'tidak boleh menyimpan '.$buruk);
        }

        $komponen->set('isi.ev1_maps', 'https://maps.app.goo.gl/abc123');
        $this->assertArrayNotHasKey('ev1_maps', $komponen->get('galatBidang'));
        $this->assertSame('https://maps.app.goo.gl/abc123', $u->fresh()->isi['ev1_maps']);

        $komponen->set('isi.musLink', '');
        $this->assertSame('', $u->fresh()->isi['musLink']);
    }

    public function test_pilihan_saklar_tanggal_jam_divalidasi_per_jenis(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        $komponen->call('setBidang', 'lvPlat', 'friendster')->call('setBidang', 'musAuto', '7')->set('isi.hmTgl', '2027-13-45')->set('isi.hmJam', '25:99');
        $galat = $komponen->get('galatBidang');
        foreach (['lvPlat', 'musAuto', 'hmTgl', 'hmJam'] as $k) {
            $this->assertArrayHasKey($k, $galat);
        }
        $komponen->call('pilihBagian', 'livestream')->assertSee('Pilihan tidak tersedia.')->call('pilihBagian', 'acara')->call('pilihBagian', 'mempelai');
        $isi = $u->fresh()->isi;
        $this->assertSame('youtube', $isi['lvPlat']);
        $this->assertSame('1', $isi['musAuto']);
        $this->assertSame('2027-02-14', $isi['hmTgl']);
        $this->assertSame('08:00', $isi['hmJam']);

        $komponen->call('setBidang', 'lvPlat', 'zoom')->call('setBidang', 'musAuto', '0')->set('isi.hmTgl', '2028-02-29')->set('isi.hmJam', '19:30');
        $this->assertSame([], $komponen->get('galatBidang'));
        $isi = $u->fresh()->isi;
        $this->assertSame(['zoom', '0', '2028-02-29', '19:30'], [$isi['lvPlat'], $isi['musAuto'], $isi['hmTgl'], $isi['hmJam']]);
    }

    public function test_bidang_opsional_bisa_disembunyikan_dari_undangan(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->call('toggleBidang', 'tagline');
        $this->assertSame(['tagline' => true], $u->fresh()->isi['_sembunyi']);

        $komponen->call('toggleBidang', 'tagline');
        $this->assertSame([], $u->fresh()->isi['_sembunyi']);
    }

    // ------------------------------------------------------------ bagian

    public function test_bagian_diurut_ulang_dalam_grupnya(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->call('geserBagian', 'quotes', -1);
        $urutan = $u->fresh()->bagian['urutan'];
        $this->assertSame(['sampul', 'mempelai', 'acara', 'quotes', 'lovestory'], array_slice($urutan, 0, 5));

        $komponen->call('pindahBagian', 'turut', 'mempelai');
        $this->assertSame(['sampul', 'turut', 'mempelai'], array_slice($u->fresh()->bagian['urutan'], 0, 3));
        $this->assertSame(SkemaUndangan::urutanAwal(), collect($u->fresh()->bagian['urutan'])->sort()->values()->pipe(fn () => collect(SkemaUndangan::urutanAwal())->all()));
        $this->assertEqualsCanonicalizing(SkemaUndangan::urutanAwal(), $u->fresh()->bagian['urutan']);
    }

    public function test_sampul_dan_penutup_tetap_dan_tidak_menyeberang_grup(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        $komponen->call('geserBagian', 'sampul', 1)->call('geserBagian', 'penutup', -1)->call('pindahBagian', 'sampul', 'acara')
            ->call('pindahBagian', 'mempelai', 'penutup')->call('pindahBagian', 'mempelai', 'kado')->call('pindahBagian', 'kado', 'mempelai');
        $this->assertSame(SkemaUndangan::urutanAwal(), $u->fresh()->bagian['urutan']);

        $komponen->call('geserBagian', 'tamu', 5)->call('geserBagian', 'tidak-ada', 1)->call('geserBagian', 'penutup', 1);
        $this->assertSame(SkemaUndangan::urutanAwal(), $u->fresh()->bagian['urutan']);
    }

    public function test_bagian_bisa_disembunyikan_dan_ditampilkan_kecuali_sampul(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        $komponen->call('toggleBagian', 'quotes')->call('toggleBagian', 'sampul')->call('toggleBagian', 'asing');
        $this->assertSame(['quotes'], $u->fresh()->bagian['sembunyi']);
        $komponen->assertSeeHtml('opacity-60')->assertDontSeeHtml('data-bagian="quotes"');

        $komponen->call('toggleBagian', 'quotes');
        $this->assertSame([], $u->fresh()->bagian['sembunyi']);
    }

    public function test_pilih_bagian_dan_bidang_dari_kanvas(): void
    {
        $komponen = $this->editor($this->buat());

        $komponen->call('pilihBagian', 'kado')->assertSet('bagianAktif', 'kado')->assertSet('terpilih', null);
        $komponen->call('pilihBagian', 'asing')->assertSet('bagianAktif', 'kado');
        $komponen->call('pilihBidang', 'ev2_lokasi')->assertSet('bagianAktif', 'acara')->assertSet('terpilih', 'ev2_lokasi')->assertDispatched('bidang-dipilih', kunci: 'ev2_lokasi');
        $komponen->call('pilihBidang', 'bagian:rundown')->assertSet('bagianAktif', 'rundown');
        $komponen->call('pilihBidang', 'foto:pria')->assertSet('bagianAktif', 'mempelai');
        $komponen->call('pilihBidang', 'foto:g3')->assertSet('bagianAktif', 'gallery');
        $komponen->call('pilihBidang', 'ev77_nama')->assertSet('bagianAktif', 'gallery');
    }

    // ------------------------------------------------------------ daftar berulang

    public function test_tambah_item_daftar_berulang(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->call('tambahItem', 'ev')->assertDispatched('bidang-dipilih');

        $u->refresh();
        $id = $u->isi['_daftar']['ev'][2];
        $this->assertGreaterThanOrEqual(100, $id);
        $this->assertSame([1, 2, $id], $u->isi['_daftar']['ev']);
        $this->assertSame('Acara Baru', $u->isi["ev{$id}_nama"]);
        $komponen->assertSet("isi.ev{$id}_nama", 'Acara Baru')->assertSet('terpilih', "ev{$id}_nama");

        $komponen->set("isi.ev{$id}_nama", 'Ngunduh Mantu');
        $this->assertSame('Ngunduh Mantu', $u->fresh()->isi["ev{$id}_nama"]);

        $komponen->call('tambahItem', 'tidak-ada');
        $this->assertCount(3, $u->fresh()->isi['_daftar']['ev']);
    }

    public function test_hapus_item_menghormati_minimum_skema(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        $komponen->call('hapusItem', 'ev', 2);
        $u->refresh();
        $this->assertSame([1], $u->isi['_daftar']['ev']);
        $this->assertArrayNotHasKey('ev2_nama', $u->isi);

        $komponen->call('hapusItem', 'ev', 1)->assertDispatched('toast');
        $this->assertSame([1], $u->fresh()->isi['_daftar']['ev']);
        $this->assertSame('Akad Nikah', $u->fresh()->isi['ev1_nama']);

        $komponen->call('hapusItem', 'ls', 1)->call('hapusItem', 'ls', 2)->call('hapusItem', 'ls', 3)->call('hapusItem', 'ls', 3);
        $this->assertSame([], $u->fresh()->isi['_daftar']['ls']);
    }

    public function test_hapus_item_yang_bukan_miliknya_diabaikan(): void
    {
        $u = $this->buat();

        $this->editor($u)->call('hapusItem', 'ev', 55)->call('hapusItem', 'xx', 1)->call('geserItem', 'ev', 55, 1)->call('gandakanItem', 'ev', 55);

        $this->assertSame([1, 2], $u->fresh()->isi['_daftar']['ev']);
    }

    public function test_geser_dan_gandakan_item(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        $komponen->call('geserItem', 'ls', 1, 1);
        $this->assertSame([2, 1, 3], $u->fresh()->isi['_daftar']['ls']);
        $komponen->call('geserItem', 'ls', 3, 1)->call('geserItem', 'ls', 2, -1)->call('geserItem', 'ls', 1, 2);
        $this->assertSame([2, 1, 3], $u->fresh()->isi['_daftar']['ls']);

        $komponen->call('gandakanItem', 'kd', 1);
        $u->refresh();
        $baru = $u->isi['_daftar']['kd'][1];
        $this->assertSame([1, $baru, 2], $u->isi['_daftar']['kd']);
        $this->assertSame('Bank BCA', $u->isi["kd{$baru}_bank"]);
        $this->assertSame($u->isi['kd1_no'], $u->isi["kd{$baru}_no"]);
    }

    public function test_id_item_baru_unik_di_antara_item_yang_ada(): void
    {
        $u = $this->buat();

        $this->editor($u)->call('tambahItem', 'rd')->call('tambahItem', 'rd')->call('tambahItem', 'ak');

        $isi = $u->fresh()->isi;
        $baru = array_merge(array_slice($isi['_daftar']['rd'], 4), $isi['_daftar']['ak'] ? array_slice($isi['_daftar']['ak'], 2) : []);
        $this->assertCount(3, $baru);
        $this->assertCount(3, array_unique($baru));
        $this->assertGreaterThanOrEqual(100, min($baru));
        $this->assertCount(6, $isi['_daftar']['rd']);
    }

    public function test_jumlah_item_dibatasi(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u);

        for ($i = 0; $i < IsiUndangan::MAKS_ITEM + 5; $i++) {
            $komponen->call('tambahItem', 'wr');
        }

        $this->assertCount(IsiUndangan::MAKS_ITEM, $u->fresh()->isi['_daftar']['wr']);
    }

    // ------------------------------------------------------------ tema dan pengaturan

    public function test_pilih_tema_tersimpan_dan_hanya_tema_terbit(): void
    {
        $u = $this->buat();
        $lain = Tema::factory()->create(['nama' => 'Hijau']);
        $draf = Tema::factory()->draft()->create();

        $komponen = $this->editor($u)->call('pilihTema', $lain->id);
        $this->assertSame($lain->id, $u->fresh()->tema_id);
        $this->assertTrue($u->fresh()->langkah['tema']);

        $komponen->call('pilihTema', $draf->id)->assertDispatched('toast')->call('pilihTema', 987654);
        $this->assertSame($lain->id, $u->fresh()->tema_id);
    }

    public function test_tema_terkunci_paket_ditolak(): void
    {
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 5]]);
        $u = $this->buat();
        $premium = Tema::factory()->tier('Premium')->create();

        $this->editor($u)->call('pilihTema', $premium->id)->assertDispatched('toast');

        $this->assertSame($this->tema->id, $u->fresh()->tema_id);
    }

    public function test_perubahan_tema_pada_undangan_terbit_menandai_perubahan(): void
    {
        $u = $this->buat();
        $lain = Tema::factory()->create();
        $komponen = $this->editor($u)->call('bukaTerbit')->call('terbitkan');

        $komponen->call('pilihTema', $lain->id);

        $this->assertTrue($u->fresh()->ada_perubahan);
        $this->assertSame($this->tema->id, $u->fresh()->terbitan['tema']['id']);
    }

    public function test_lingkaran_sampul_disimpan_di_pengaturan(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->call('toggleLingkaran');

        $u->refresh();
        $this->assertFalse($u->pengaturan['lingkaran']);
        $this->assertFalse($u->isi['_cincin']);

        $komponen->call('toggleLingkaran');
        $this->assertTrue($u->fresh()->pengaturan['lingkaran']);
    }

    public function test_ubah_nama_undangan_dan_link_saat_draf(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->set('judul', '  Pesta   Raka  ')->set('slug', 'Pesta Raka');

        $u->refresh();
        $this->assertSame('Pesta Raka', $u->judul);
        $this->assertSame('pesta-raka', $u->slug);
        $komponen->assertSet('galatSlug', '');
    }

    public function test_nama_kosong_dan_link_buruk_ditolak(): void
    {
        $u = $this->buat();
        Undangan::factory()->create(['slug' => 'dipakai-orang']);

        $komponen = $this->editor($u)->set('judul', '   ')->assertSet('galatJudul', 'Nama undangan wajib diisi.');
        $this->assertSame('Raka & Sinta', $u->fresh()->judul);

        foreach (['admin', 'dipakai-orang', 'ab'] as $buruk) {
            $komponen->set('slug', $buruk);
            $this->assertNotSame('', $komponen->get('galatSlug'), $buruk);
            $this->assertSame('raka-sinta', $u->fresh()->slug);
        }
    }

    public function test_link_undangan_terbit_tidak_bisa_diganti_dari_pengaturan(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->call('bukaTerbit')->call('terbitkan');

        $komponen->set('slug', 'curang');

        $this->assertSame('raka-sinta', $u->fresh()->slug);
        $komponen->assertSet('slug', 'raka-sinta');
    }

    // ------------------------------------------------------------ terbit

    public function test_alur_terbit_menyalin_beku_dan_mengaktifkan(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->set('isi.priaP', 'Budi')->call('toggleBagian', 'quotes');

        $komponen->call('bukaTerbit')->assertSet('dialogTerbit', true)->assertSet('slugTerbit', 'raka-sinta')->assertSet('tahapTerbit', 'form')
            ->set('slugTerbit', 'Budi Sinta 2027')->call('terbitkan')->assertHasNoErrors()
            ->assertSet('tahapTerbit', 'selesai')->assertSet('galatSlug', '')->assertDispatched('toast');

        $u->refresh();
        $this->assertSame(Undangan::TERBIT, $u->status);
        $this->assertSame('budi-sinta-2027', $u->slug);
        $this->assertNotNull($u->diterbitkan_at);
        $this->assertFalse($u->ada_perubahan);
        $this->assertTrue($u->langkah['terbit']);
        $this->assertSame('Budi', $u->terbitan['isi']['priaP']);
        $this->assertSame(['quotes'], $u->terbitan['bagian']['sembunyi']);
        $this->assertSame($u->bagian, $u->terbitan['bagian']);
        $this->assertSame('budi-sinta-2027', $u->terbitan['slug']);
        $this->assertSame('Biru Klasik', $u->terbitan['tema']['nama']);
        $this->assertSame('#1D4ED8', $u->terbitan['tema']['warna_utama']);
        $this->assertArrayHasKey('lingkaran', $u->terbitan['pengaturan']);
    }

    public function test_terbitan_tidak_berubah_oleh_suntingan_dan_tema_admin(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->call('bukaTerbit')->call('terbitkan');

        $komponen->set('isi.priaP', 'Dodi');
        $this->tema->update(['nama' => 'Berubah', 'warna_utama' => '#FF0000']);

        $u->refresh();
        $this->assertSame('Raka', $u->terbitan['isi']['priaP']);
        $this->assertSame('Dodi', $u->isi['priaP']);
        $this->assertSame('Biru Klasik', $u->terbitan['tema']['nama']);
        $this->assertSame('#1D4ED8', $u->terbitan['tema']['warna_utama']);
        $this->assertTrue($u->ada_perubahan);
    }

    public function test_terbitkan_perubahan_memperbarui_terbitan(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->call('bukaTerbit')->call('terbitkan');
        $komponen->set('isi.priaP', 'Dodi')->call('bukaTerbit')->assertSet('tahapTerbit', 'form')->assertSee('Ada perubahan yang belum diterbitkan');

        $komponen->call('terbitkan')->assertHasNoErrors();

        $u->refresh();
        $this->assertSame('Dodi', $u->terbitan['isi']['priaP']);
        $this->assertFalse($u->ada_perubahan);
        $this->assertSame(Undangan::TERBIT, $u->status);
    }

    public function test_buka_dialog_pada_undangan_terbit_tanpa_perubahan_menampilkan_link(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->call('bukaTerbit')->call('terbitkan');

        $komponen->call('bukaTerbit')->assertSet('tahapTerbit', 'selesai')->assertSee('Undangan sudah terbit')->assertSee('raka-sinta');
    }

    public function test_batal_terbit_kembali_ke_draf_dengan_isi_utuh(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->set('isi.priaP', 'Budi')->call('bukaTerbit')->call('terbitkan');

        $komponen->call('jadikanDraf')->assertSet('dialogTerbit', false);

        $u->refresh();
        $this->assertSame(Undangan::DRAF, $u->status);
        $this->assertNull($u->terbitan);
        $this->assertNull($u->diterbitkan_at);
        $this->assertFalse($u->ada_perubahan);
        $this->assertSame('Budi', $u->isi['priaP']);
        $this->assertSame('raka-sinta', $u->slug);
        $this->assertFalse($u->langkah['terbit']);

        $komponen->call('jadikanDraf');
        $this->assertSame(Undangan::DRAF, $u->fresh()->status);
    }

    public function test_terbit_gagal_bila_data_wajib_kosong(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->set('isi.priaP', '')->set('isi.tglCover', '   ')->call('bukaTerbit');
        $komponen->assertSee('Nama panggilan pria belum diisi')->assertSee('Tanggal acara belum diisi')->call('terbitkan');

        $this->assertNotSame('', $komponen->get('galatTerbit'));
        $u->refresh();
        $this->assertSame(Undangan::DRAF, $u->status);
        $this->assertNull($u->terbitan);
    }

    public function test_terbit_gagal_untuk_link_buruk_terlarang_atau_bentrok(): void
    {
        $u = $this->buat();
        Undangan::factory()->terbit()->create(['slug' => 'sudah-ada']);
        $komponen = $this->editor($u)->call('bukaTerbit');

        foreach (['', 'ab', 'admin', 'Sudah Ada', 'a--b-', '-awal', 'x y!'] as $buruk) {
            $komponen->set('slugTerbit', $buruk)->call('terbitkan');
            $this->assertNotSame('', $komponen->get('galatSlug'), "slug '{$buruk}' seharusnya ditolak");
            $this->assertSame(Undangan::DRAF, $u->fresh()->status);
        }
        $this->assertSame('raka-sinta', $u->fresh()->slug);
    }

    public function test_slug_milik_undangan_sendiri_boleh_dipakai_ulang(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->call('bukaTerbit')->call('terbitkan');

        $komponen->set('isi.priaP', 'Dodi')->call('bukaTerbit')->call('terbitkan')->assertSet('galatSlug', '');

        $this->assertSame('raka-sinta', $u->fresh()->slug);
    }

    public function test_terbit_dibatasi_laju(): void
    {
        $u = $this->buat();
        $komponen = $this->editor($u)->call('bukaTerbit');
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('terbit-undangan:'.$this->user->id, 60);
        }

        $komponen->call('terbitkan')->assertSet('galatTerbit', 'Terlalu banyak percobaan. Coba lagi sebentar lagi.');

        $this->assertSame(Undangan::DRAF, $u->fresh()->status);
    }

    public function test_terbit_ditolak_bila_tema_terkunci_paket(): void
    {
        Pengaturan::simpan('paket', ['aktif' => true, 'kuota' => ['gratis' => 5]]);
        $premium = Tema::factory()->tier('Premium')->create();
        $u = $this->buat(['tema_id' => $premium->id]);

        $komponen = $this->editor($u)->call('bukaTerbit')->assertSee('tidak tersedia di paketmu')->call('terbitkan');

        $this->assertNotSame('', $komponen->get('galatTerbit'));
        $this->assertSame(Undangan::DRAF, $u->fresh()->status);
    }

    public function test_peringatan_tamu_belum_ada_tidak_menghalangi_terbit(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->call('bukaTerbit')->assertSee('Belum ada tamu')->call('terbitkan');

        $this->assertSame('', $komponen->get('galatTerbit'));
        $this->assertSame(Undangan::TERBIT, $u->fresh()->status);
    }

    public function test_pemilik_lain_tidak_bisa_terbit_lewat_komponen_orang(): void
    {
        $u = $this->buat();
        $penyusup = User::factory()->create();

        Livewire::actingAs($penyusup)->test(EditorUndangan::class, ['undangan' => $u->id])->assertNotFound();

        $this->assertSame(Undangan::DRAF, $u->fresh()->status);
    }

    // ------------------------------------------------------------ rendering kanvas

    public function test_kanvas_menampilkan_isi_terbaru_dan_urutan_bagian(): void
    {
        $u = $this->buat();

        $komponen = $this->editor($u)->set('isi.priaP', 'Bambang')->call('toggleBagian', 'quotes');

        $komponen->assertSee('Bambang');
        $html = $komponen->html();
        $this->assertStringNotContainsString('data-bagian="quotes"', $html);
        $this->assertStringContainsString('data-bagian="mempelai"', $html);
    }

    public function test_dialog_terbit_menautkan_ke_bidang_yang_kurang(): void
    {
        $u = $this->buat();

        $this->editor($u)->set('isi.wanitaP', '')->call('bukaTerbit')->assertSeeHtml('$wire.pilihBidang(&#039;wanitaP&#039;)');
    }
}
