<?php

namespace Tests\Feature;

use App\Livewire\Admin\Theme;
use App\Livewire\Admin\ThemeEditor;
use App\Models\RiwayatTema;
use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use App\Services\DokumenTema;
use App\Services\RenderUndangan;
use App\Support\SkemaUndangan;
use Database\Seeders\TemaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

class AdminThemeTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;

    private function daftar(?User $user = null)
    {
        return Livewire::actingAs($this->sudahKonfirmasiSandi($user ?? User::factory()->admin()->create()))->test(Theme::class);
    }

    private function editor(int|string|null $sasaran = 'baru', ?User $user = null)
    {
        return Livewire::actingAs($this->sudahKonfirmasiSandi($user ?? User::factory()->admin()->create()))->test(ThemeEditor::class, ['sasaran' => $sasaran]);
    }

    private function isi($k, array $f = [])
    {
        $k->set('f.nama', 'Tema Uji')->set('f.warna_utama', '#112233');
        foreach ($f as $kunci => $nilai) {
            $k->set('f.'.$kunci, $nilai);
        }

        return $k;
    }

    private function bagian(): array
    {
        return ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []];
    }

    // ------------------------------------------------------------------ akses

    public function test_tamu_diarahkan_non_admin_ditolak_admin_melihat_halaman(): void
    {
        $this->get('/admin/theme')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/theme')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/theme')->assertOk()->assertSeeLivewire(Theme::class);
        $this->actingAs(User::factory()->admin()->create())->get('/admin/theme?tema=baru')->assertOk()->assertSeeLivewire(ThemeEditor::class);
        $this->actingAs(User::factory()->create())->get('/admin/theme?tema=baru')->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(Theme::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(ThemeEditor::class, ['sasaran' => 'baru'])->assertForbidden();
    }

    public function test_aksi_ditolak_bagi_non_admin_yang_sudah_memuat_komponen(): void
    {
        $t = Tema::factory()->create();
        $k = $this->daftar();
        auth()->logout();
        $this->actingAs(User::factory()->create());
        $k->call('ubahStatus', $t->id, 'draf')->assertForbidden();
        $this->assertSame('publish', $t->fresh()->status);
    }

    // ------------------------------------------------------------------ daftar

    public function test_daftar_menampilkan_tema_dan_hitungan(): void
    {
        $this->seed(TemaSeeder::class);
        $this->daftar()->assertSee('Biru Klasik')->assertSee('Senja Tropis')->assertSee('Royal Burgundy')
            ->assertSee('7 tema publik')->assertSee('1 draf');
    }

    public function test_cari_dan_filter(): void
    {
        $this->seed(TemaSeeder::class);
        $this->daftar()->set('q', 'midnight')->assertSee('Midnight')->assertDontSee('Biru Klasik');
        $this->daftar()->call('atur', 'draf')->assertSee('Senja Tropis')->assertDontSee('Biru Klasik');
        $this->daftar()->call('atur', 'publik')->assertDontSee('Senja Tropis')->assertSee('Biru Klasik');
        $this->daftar()->call('aturTier', 'Deluxe')->assertSee('Royal Burgundy')->assertDontSee('Biru Klasik');
        $this->daftar()->set('q', 'zzzz')->assertSee('Tidak ada tema yang cocok');
        $this->daftar()->call('atur', 'tidak-ada')->assertSet('fs', 'semua');
        $this->daftar()->call('aturTier', 'Platinum')->assertSet('tier', 'Semua');
        $this->daftar()->set('urut', 'asal')->assertSet('urut', 'urutan');
    }

    public function test_urutan_nama_dan_paling_dipakai(): void
    {
        $a = Tema::factory()->create(['nama' => 'Zebra', 'urutan' => 1]);
        $b = Tema::factory()->create(['nama' => 'Apel', 'urutan' => 2]);
        Undangan::factory()->count(2)->dengan($b)->create();
        $this->daftar()->set('urut', 'nama')->assertSeeInOrder(['Apel', 'Zebra']);
        $this->daftar()->set('urut', 'dipakai')->assertSeeInOrder(['Apel', 'Zebra'])->assertSee('Dipakai 2 undangan');
        $this->daftar()->assertSeeInOrder(['Zebra', 'Apel']);
    }

    public function test_publikasikan_dan_tarik(): void
    {
        $t = Tema::factory()->draft()->create();
        $this->assertCount(0, Tema::terbit()->get());
        $this->daftar()->call('ubahStatus', $t->id, 'publik')->assertDispatched('toast');
        $this->assertSame('publish', $t->fresh()->status);
        $this->assertCount(1, Tema::terbit()->get());
        $this->daftar()->call('ubahStatus', $t->id, 'draf');
        $this->assertSame('draft', $t->fresh()->status);
        $this->assertCount(0, Tema::terbit()->get());
    }

    public function test_tema_kontras_buruk_tidak_bisa_dipublikasikan(): void
    {
        $t = Tema::factory()->draft()->create(['warna_teks' => '#FEFEFE', 'warna_halaman' => '#FFFFFF']);
        $this->daftar()->call('ubahStatus', $t->id, 'publik')->assertDispatched('toast', pesan: 'Tema belum bisa dipublikasikan: Kontras teks terhadap latar halaman hanya 1,0:1 (minimum 3:1, ideal 4,5:1). Teks sulit dibaca.', tipe: 'galat');
        $this->assertSame('draft', $t->fresh()->status);
    }

    public function test_duplikat_membuat_draf_dengan_nama_unik(): void
    {
        $t = Tema::factory()->create(['nama' => 'Asli', 'kode' => 'x1', 'dokumen' => ['global' => ['base' => 17]], 'latar_tetap' => ['global' => true]]);
        $this->daftar()->call('duplikat', $t->id)->call('duplikat', $t->id);

        $this->assertSame(3, Tema::count());
        $s = Tema::where('nama', 'Asli (salinan)')->firstOrFail();
        $this->assertSame('draft', $s->status);
        $this->assertNull($s->kode);
        $this->assertSame(1, $s->versi);
        $this->assertSame(['global' => ['base' => 17]], $s->dokumen);
        $this->assertSame($t->warna_utama, $s->warna_utama);
        $this->assertTrue(Tema::where('nama', 'Asli (salinan 2)')->exists());
        $this->assertSame(1, $s->riwayat()->count());
        $this->assertGreaterThan($t->urutan, $s->urutan);
    }

    public function test_geser_urutan(): void
    {
        $a = Tema::factory()->create(['urutan' => 1]);
        $b = Tema::factory()->create(['urutan' => 2]);
        $this->daftar()->call('geser', $b->id, -1);
        $this->assertSame(1, $b->fresh()->urutan);
        $this->assertSame(2, $a->fresh()->urutan);
        $this->daftar()->call('geser', $b->id, -1);
        $this->assertSame(1, $b->fresh()->urutan);
    }

    public function test_arsipkan_tema_yang_dipakai_undangan_tetap_dirender_dan_bisa_dipulihkan(): void
    {
        $t = Tema::factory()->create(['warna_utama' => '#AA1122']);
        $u = Undangan::factory()->dengan($t)->create(['isi' => SkemaUndangan::isiAwal()]);

        $this->daftar()->call('tanyaArsip', $t->id)->assertSet('dialogArsip', true)->assertSee('1 undangan memakai tema ini')->call('arsipkan');

        $this->assertSoftDeleted('tema', ['id' => $t->id]);
        $this->assertSame('draft', Tema::withTrashed()->find($t->id)->status);
        $this->assertCount(0, Tema::terbit()->get());
        $this->assertStringContainsString('#AA1122', RenderUndangan::untukUndangan($u->fresh()));

        $this->daftar()->call('atur', 'arsip')->assertSee($t->nama)->call('pulihkan', $t->id);
        $this->assertNull($t->fresh()->deleted_at);
        $this->assertSame('draft', $t->fresh()->status);
    }

    public function test_hapus_permanen_hanya_tema_arsip_tak_dipakai_dan_bukan_bawaan(): void
    {
        $bebas = Tema::factory()->create();
        $dipakai = Tema::factory()->create();
        Undangan::factory()->dengan($dipakai)->create();
        $bawaan = Tema::factory()->create(['kode' => 't9']);
        $bebas->riwayat()->create(['versi' => 1, 'data' => ['nama' => 'x']]);
        foreach ([$bebas, $dipakai, $bawaan] as $t) {
            $t->delete();
        }

        $this->daftar()->call('tanyaHapus', $dipakai->id)->assertSet('dialogHapus', false)->assertDispatched('toast', pesan: 'Tema ini masih dipakai undangan. Biarkan diarsipkan agar undangan tidak kehilangan tampilannya.', tipe: 'galat');
        $this->daftar()->call('tanyaHapus', $bawaan->id)->assertSet('dialogHapus', false)->assertDispatched('toast');
        $this->daftar()->call('tanyaHapus', $bebas->id)->assertSet('dialogHapus', true)->call('hapusPermanen');

        $this->assertDatabaseMissing('tema', ['id' => $bebas->id]);
        $this->assertDatabaseCount('riwayat_tema', 0);
        $this->assertDatabaseHas('tema', ['id' => $dipakai->id]);
        $this->assertDatabaseHas('tema', ['id' => $bawaan->id]);
    }

    public function test_hapus_permanen_tema_aktif_ditolak(): void
    {
        $t = Tema::factory()->create();
        $this->daftar()->call('tanyaHapus', $t->id)->assertNotFound();
        $this->assertDatabaseHas('tema', ['id' => $t->id]);
    }

    public function test_id_hapus_dan_arsip_terkunci(): void
    {
        $t = Tema::factory()->create();
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->daftar()->set('hapusId', $t->id);
    }

    // ------------------------------------------------------------------ editor

    public function test_buat_tema_baru_sebagai_draf_dengan_riwayat(): void
    {
        $this->isi($this->editor('baru'), ['warna_aksen' => '#e8c46e', 'font_judul' => 'jakarta', 'tier' => 'Premium'])
            ->call('simpan', 'draft')->assertHasNoErrors()->assertRedirect(route('admin.theme', ['tema' => 1]));

        $t = Tema::firstOrFail();
        $this->assertSame('Tema Uji', $t->nama);
        $this->assertSame('draft', $t->status);
        $this->assertSame('Premium', $t->tier);
        $this->assertSame('#112233', $t->warna_utama);
        $this->assertSame('#E8C46E', $t->warna_aksen);
        $this->assertSame('jakarta', $t->font_judul);
        $this->assertNull($t->dokumen);
        $this->assertNull($t->latar_tetap);
        $this->assertSame(1, $t->versi);
        $this->assertSame(1, RiwayatTema::where('tema_id', $t->id)->count());
        $this->assertCount(0, Tema::terbit()->get());
    }

    public function test_buat_tema_langsung_dipublikasikan_dengan_base_dan_bgfixed(): void
    {
        $k = $this->isi($this->editor('baru'), ['base' => 17])->call('ubahBgfixed');
        $k->call('simpan', 'publish')->assertHasNoErrors();

        $t = Tema::firstOrFail();
        $this->assertSame('publish', $t->status);
        $this->assertSame(['global' => ['base' => 17]], $t->dokumen);
        $this->assertSame(['global' => true, 'jenis' => 'image', 'section' => []], $t->latar_tetap);
        $this->assertCount(1, Tema::terbit()->get());
    }

    public function test_validasi_ketat_form_tema(): void
    {
        $this->editor('baru')->call('simpan')->assertHasErrors(['f.nama']);
        $this->isi($this->editor('baru'), ['nama' => 'ab'])->call('simpan')->assertHasErrors(['f.nama']);
        $this->editor('baru')->set('f.nama', str_repeat('a', 101))->assertSet('f.nama', str_repeat('a', 100));
        foreach (['112233', '#12345', '#GGGGGG', 'red', '#112233;background:url(x)', 'url(javascript:1)', '#11223344'] as $buruk) {
            $this->isi($this->editor('baru'), ['warna_teks' => $buruk])->call('simpan')->assertHasErrors(['f.warna_teks']);
        }
        $this->isi($this->editor('baru'), ['font_judul' => 'comic'])->call('simpan')->assertHasErrors(['f.font_judul']);
        $this->isi($this->editor('baru'), ['font_judul' => 'x;} body{display:none'])->call('simpan')->assertHasErrors(['f.font_judul']);
        $this->isi($this->editor('baru'), ['tier' => 'Platinum'])->call('simpan')->assertHasErrors(['f.tier']);
        $this->isi($this->editor('baru'), ['base' => 11])->call('simpan')->assertHasErrors(['f.base']);
        $this->isi($this->editor('baru'), ['base' => 21])->call('simpan')->assertHasErrors(['f.base']);
        $this->isi($this->editor('baru'), ['base' => 'besar'])->call('simpan')->assertHasErrors(['f.base']);
        $this->assertSame(0, Tema::count());
    }

    public function test_nama_tema_harus_unik_tanpa_membedakan_huruf(): void
    {
        Tema::factory()->create(['nama' => 'Emas Mewah']);
        $this->isi($this->editor('baru'), ['nama' => 'emas mewah'])->call('simpan')->assertHasErrors(['f.nama']);
        $t = Tema::factory()->create(['nama' => 'Lain']);
        $this->editor($t->id)->set('f.nama', 'EMAS MEWAH')->call('simpan')->assertHasErrors(['f.nama']);
        $this->editor($t->id)->set('f.nama', 'Lain')->call('simpan')->assertHasNoErrors();
    }

    public function test_edit_tema_menaikkan_versi_hanya_bila_isi_berubah(): void
    {
        $t = Tema::factory()->create(['warna_utama' => '#112233']);
        $k = $this->editor($t->id)->assertSet('versiDasar', 1);

        $k->call('simpan', 'publish')->assertHasNoErrors();
        $this->assertSame(1, $t->fresh()->versi);
        $this->assertSame(0, RiwayatTema::count());

        $k->set('f.warna_utama', '#445566')->assertSet('kotor', true)->call('simpan', 'draft')->assertSet('kotor', false)->assertSet('versiDasar', 2);
        $t->refresh();
        $this->assertSame('#445566', $t->warna_utama);
        $this->assertSame(2, $t->versi);
        $this->assertSame('draft', $t->status);
        $this->assertSame(1, RiwayatTema::where('tema_id', $t->id)->count());
    }

    public function test_suntingan_bersamaan_ditolak(): void
    {
        $t = Tema::factory()->create();
        $k = $this->editor($t->id);
        $t->update(['versi' => 5]);
        $k->set('f.warna_utama', '#445566')->call('simpan')->assertHasErrors(['f.nama']);
        $this->assertNotSame('#445566', $t->fresh()->warna_utama);
    }

    public function test_tema_kontras_buruk_tersimpan_sebagai_draf_tetapi_tidak_terbit(): void
    {
        $t = Tema::factory()->draft()->create();
        $k = $this->editor($t->id)->set('f.warna_teks', '#FAFAFA')->set('f.warna_halaman', '#FFFFFF')->assertSee('Teks sulit dibaca');
        $k->call('simpan', 'publish')->assertDispatched('toast', tipe: 'galat');
        $this->assertSame('draft', $t->fresh()->status);
        $this->assertSame('#10203A', $t->fresh()->warna_teks);
        $k->call('simpan', 'draft')->assertHasNoErrors();
        $this->assertSame('#FAFAFA', $t->fresh()->warna_teks);
    }

    public function test_tema_arsip_tidak_bisa_disimpan(): void
    {
        $t = Tema::factory()->create();
        $t->delete();
        $this->editor($t->id)->assertSee('diarsipkan')->set('f.nama', 'Diubah Paksa')->call('simpan')->assertDispatched('toast', tipe: 'galat');
        $this->assertNotSame('Diubah Paksa', Tema::withTrashed()->find($t->id)->nama);
    }

    public function test_palet_font_dan_tier_hanya_dari_daftar_putih(): void
    {
        $k = $this->editor('baru')->call('terapkanPalet', 1)->assertSet('f.warna_utama', '#2F5D4B')->assertSet('kotor', true);
        $k->call('terapkanPalet', 99)->assertSet('f.warna_utama', '#2F5D4B');
        $k->call('pilihFont', 'script')->assertSet('f.font_judul', 'script')->call('pilihFont', 'papyrus')->assertSet('f.font_judul', 'script');
        $k->call('pilihTier', 'Deluxe')->assertSet('f.tier', 'Deluxe')->call('pilihTier', 'VIP')->assertSet('f.tier', 'Deluxe');
    }

    public function test_properti_sensitif_terkunci(): void
    {
        $t = Tema::factory()->create();
        $k = $this->editor($t->id);
        foreach (['temaId' => 999, 'versiDasar' => 0, 'status' => 'publish', 'arsip' => true] as $p => $v) {
            try {
                $k->set($p, $v);
                $this->fail("$p tidak terkunci");
            } catch (CannotUpdateLockedPropertyException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_pratinjau_memakai_nilai_form_dan_sandbox_tanpa_skrip(): void
    {
        $k = $this->editor('baru')->set('f.warna_utama', '#AB12CD')->set('f.base', 20);
        $k->assertSeeHtml('sandbox=""')->assertSeeHtml('#AB12CD')->assertSeeHtml('uy-undangan');
        $html = $k->html();
        $this->assertStringNotContainsString('<script', explode('srcdoc="', $html)[1] ?? '');
        $k->assertSeeHtml('Content-Security-Policy');
    }

    public function test_pratinjau_tidak_pecah_saat_hex_belum_valid(): void
    {
        $this->editor('baru')->set('f.warna_utama', '#12')->assertSee('bukan format #RRGGBB')->assertSeeHtml('uy-undangan');
    }

    public function test_riwayat_versi_dan_pemulihan(): void
    {
        $t = Tema::factory()->create(['warna_utama' => '#111111']);
        $k = $this->editor($t->id);
        $k->set('f.warna_utama', '#222222')->call('simpan')->set('f.warna_utama', '#333333')->call('simpan');
        $this->assertSame(3, $t->fresh()->versi);

        $v2 = RiwayatTema::where('tema_id', $t->id)->where('versi', 2)->firstOrFail();
        $k->call('bukaRiwayat')->assertSet('dialogRiwayat', true)->assertSee('Versi 2')->assertSee('Versi 3');
        $k->call('pulihkanVersi', $v2->id)->assertSet('f.warna_utama', '#222222')->assertSet('kotor', true)->assertSet('dialogRiwayat', false);
        $this->assertSame('#333333', $t->fresh()->warna_utama);
        $k->call('simpan');
        $this->assertSame('#222222', $t->fresh()->warna_utama);
        $this->assertSame(4, $t->fresh()->versi);
    }

    public function test_pemulihan_tidak_menerima_riwayat_tema_lain_dan_data_rusak(): void
    {
        $a = Tema::factory()->create(['warna_utama' => '#111111']);
        $b = Tema::factory()->create();
        $rb = $b->riwayat()->create(['versi' => 1, 'data' => ['warna_utama' => '#999999']]);
        $this->editor($a->id)->call('pulihkanVersi', $rb->id)->assertNotFound();

        $ra = $a->riwayat()->create(['versi' => 1, 'data' => ['nama' => 'Lama', 'warna_utama' => 'url(javascript:alert(1))', 'font_judul' => 'zzz', 'tier' => 'X', 'dokumen' => ['global' => ['base' => 999]]]]);
        $this->editor($a->id)->call('pulihkanVersi', $ra->id)->assertSet('f.nama', 'Lama')->assertSet('f.warna_utama', '#111111')->assertSet('f.font_judul', 'playfair')->assertSet('f.tier', 'Gratis')->assertSet('f.base', 14);
    }

    public function test_riwayat_dibatasi_20_versi(): void
    {
        $t = Tema::factory()->create();
        $k = $this->editor($t->id);
        for ($i = 0; $i < 24; $i++) {
            $k->set('f.warna_utama', sprintf('#%06X', 0x100000 + $i))->call('simpan');
        }
        $this->assertSame(20, RiwayatTema::where('tema_id', $t->id)->count());
        $this->assertSame(25, $t->fresh()->versi);
        $this->assertSame(0, RiwayatTema::where('tema_id', $t->id)->where('versi', '<', 6)->count());
    }

    // ------------------------------------------------------------------ dokumen + renderer

    public function test_dokumen_dibersihkan_dari_kunci_dan_nilai_liar(): void
    {
        $this->assertNull(DokumenTema::bersihkan(null));
        $this->assertNull(DokumenTema::bersihkan('<script>alert(1)</script>'));
        $this->assertNull(DokumenTema::bersihkan(['global' => ['base' => 14]]));
        $this->assertNull(DokumenTema::bersihkan(['global' => ['base' => '16;background:url(x)'], 'css' => 'body{display:none}', 'html' => '<b>']));
        $this->assertNull(DokumenTema::bersihkan(['global' => ['base' => 99]]));
        $this->assertSame(['global' => ['base' => 18]], DokumenTema::bersihkan(['global' => ['base' => '18'], 'css' => 'x', 'nodes' => [1]]));
    }

    private function ukuranTeks(string $html): array
    {
        preg_match_all('/font-size:(\d+)px/', $html, $m);

        return array_map('intval', $m[1]);
    }

    public function test_renderer_menskalakan_ukuran_teks_dari_dokumen(): void
    {
        $isi = SkemaUndangan::isiAwal();
        $dasar = RenderUndangan::html($isi, $this->bagian(), new Tema(['warna_utama' => '#1D4ED8']));
        $besar = RenderUndangan::html($isi, $this->bagian(), new Tema(['warna_utama' => '#1D4ED8', 'dokumen' => ['global' => ['base' => 20]]]));
        $kecil = RenderUndangan::html($isi, $this->bagian(), new Tema(['warna_utama' => '#1D4ED8', 'dokumen' => ['global' => ['base' => 12]]]));

        $this->assertGreaterThan(array_sum($this->ukuranTeks($dasar)), array_sum($this->ukuranTeks($besar)));
        $this->assertLessThan(array_sum($this->ukuranTeks($dasar)), array_sum($this->ukuranTeks($kecil)));
    }

    public function test_renderer_mengabaikan_dokumen_tidak_valid_dan_tidak_membocorkan_isinya(): void
    {
        $isi = SkemaUndangan::isiAwal();
        $dasar = RenderUndangan::html($isi, $this->bagian(), new Tema(['warna_utama' => '#1D4ED8']));
        foreach ([
            ['global' => ['base' => 99]],
            ['global' => ['base' => 'x;color:red']],
            ['global' => ['base' => 14]],
            ['css' => '</style><script>alert(1)</script>', 'html' => '<img src=x onerror=alert(1)>'],
            'bukan array',
        ] as $liar) {
            $html = RenderUndangan::html($isi, $this->bagian(), new Tema(['warna_utama' => '#1D4ED8', 'dokumen' => $liar]));
            $this->assertSame($dasar, $html);
            $this->assertStringNotContainsString('alert(1)', $html);
        }
    }

    public function test_renderer_memvalidasi_warna_dan_font_tema_tetap_aman(): void
    {
        $t = new Tema(['warna_utama' => 'red;background:url(//x)', 'warna_aksen' => '</style><script>', 'warna_permukaan' => 'bad', 'warna_halaman' => '#FFF', 'warna_teks' => 'expression(1)', 'font_judul' => 'x;}body{display:none']);
        $html = RenderUndangan::html(SkemaUndangan::isiAwal(), $this->bagian(), $t);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringNotContainsString('url(//x)', $html);
        $this->assertStringContainsString('--uy-cov:#1D4ED8', $html);
    }

    public function test_delapan_tema_seeder_tetap_dirender(): void
    {
        $this->seed(TemaSeeder::class);
        $this->assertSame(8, Tema::withTrashed()->count());
        foreach (Tema::all() as $t) {
            $this->assertNull($t->dokumen === null ? null : DokumenTema::bersihkan($t->dokumen));
            $html = RenderUndangan::html(SkemaUndangan::isiAwal(), $this->bagian(), $t);
            $this->assertStringContainsString('--uy-cov:'.strtoupper($t->warna_utama), $html, $t->nama);
            $this->assertStringContainsString('THE WEDDING OF', $html);
            $this->assertFalse(DokumenTema::adaGalat(DokumenTema::periksa($t->only(array_keys(DokumenTema::WARNA)))), $t->nama.' tidak lolos pemeriksaan');
        }
    }

    public function test_terbitan_membawa_dokumen_tema_ke_renderer(): void
    {
        $isi = SkemaUndangan::isiAwal();
        $tema = Tema::factory()->create();
        $salinan = $tema->only(['id', 'kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul', 'latar_tetap']);
        $u = Undangan::factory()->terbit()->dengan($tema)->create(['isi' => $isi, 'terbitan' => ['isi' => $isi, 'bagian' => $this->bagian(), 'tema' => $salinan + ['dokumen' => ['global' => ['base' => 20]]]]]);
        $u2 = Undangan::factory()->terbit()->dengan($tema)->create(['isi' => $isi, 'terbitan' => ['isi' => $isi, 'bagian' => $this->bagian(), 'tema' => $salinan]]);

        $this->assertGreaterThan(
            array_sum($this->ukuranTeks(RenderUndangan::untukUndangan($u2, true))),
            array_sum($this->ukuranTeks(RenderUndangan::untukUndangan($u, true))),
        );
    }

    public function test_periksa_kontras(): void
    {
        $ok = DokumenTema::periksa(['warna_utama' => '#1D4ED8', 'warna_aksen' => '#C99A3B', 'warna_permukaan' => '#F3F6FB', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#10203A']);
        $this->assertSame('ok', $ok[0]['lv']);
        $this->assertSame('err', DokumenTema::periksa(['warna_utama' => 'x'] + DokumenTema::BAWAAN)[0]['lv']);
        $warn = DokumenTema::periksa(['warna_teks' => '#8A8A8A', 'warna_halaman' => '#FFFFFF'] + DokumenTema::BAWAAN);
        $this->assertSame('warn', $warn[0]['lv']);
        $this->assertFalse(DokumenTema::adaGalat($warn));
        $this->assertEqualsWithDelta(21.0, DokumenTema::rasioKontras('#000000', '#FFFFFF'), 0.01);
    }
}
