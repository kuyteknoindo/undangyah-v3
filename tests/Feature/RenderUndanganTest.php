<?php

namespace Tests\Feature;

use App\Models\Tema;
use App\Models\Undangan;
use App\Services\RenderUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderUndanganTest extends TestCase
{
    use RefreshDatabase;

    private function bagian(array $sembunyi = [], ?array $urutan = null): array
    {
        return ['urutan' => $urutan ?? SkemaUndangan::urutanAwal(), 'sembunyi' => $sembunyi];
    }

    private function isi(array $timpa = []): array
    {
        return array_replace(SkemaUndangan::isiAwal(), $timpa);
    }

    private function urutanBagian(string $html): array
    {
        preg_match_all('/<section class="uy-bag [^"]*" data-bagian="([a-z]+)"/', $html, $m);

        return $m[1];
    }

    public function test_membuat_fragmen_mandiri_terlingkup(): void
    {
        $html = RenderUndangan::html($this->isi(), $this->bagian(), null);

        $this->assertStringStartsWith('<div class="uy-undangan"', $html);
        $this->assertStringEndsWith('</div>', $html);
        $this->assertSame(1, substr_count($html, '<style>'));
        // Semua aturan CSS berawalan .uy-undangan (tidak bocor ke halaman).
        preg_match('#<style>(.*?)</style>#s', $html, $m);
        foreach (preg_split('/\}\s*/', trim($m[1])) as $aturan) {
            if ($aturan === '') {
                continue;
            }
            $this->assertStringStartsWith('.uy-undangan', trim($aturan), 'aturan CSS tidak terlingkup: '.substr($aturan, 0, 60));
        }
        $this->assertStringNotContainsString('<script', $html);
    }

    public function test_semua_bagian_dirender_dengan_isi_awal(): void
    {
        $foto = ['cover' => 'https://cdn.example.com/c.jpg', 'latar' => 'https://cdn.example.com/l.jpg', 'penutup' => 'https://cdn.example.com/p.jpg', 'g1' => 'https://cdn.example.com/g1.jpg'];
        $html = RenderUndangan::html($this->isi(['_foto' => $foto]), $this->bagian(), null, ['tamu' => ['nama' => 'Budi']]);

        $this->assertSame(SkemaUndangan::urutanAwal(), $this->urutanBagian($html));
        foreach (['THE WEDDING OF', 'Raka', 'Sinta', 'Raka Pratama, S.T.', 'Akad Nikah', 'Resepsi', 'Pertama Bertemu', 'Rundown Acara', 'Bank BCA', '1234 5678 90',
            'Konfirmasi Kehadiran', 'Ucapan &amp; Doa', 'Dresscode', 'Cokelat tanah', 'Raka &amp; Sinta', 'Bapak Hendra Pratama', 'Budi'] as $teks) {
            $this->assertStringContainsString($teks, $html, "hilang: {$teks}");
        }
        $this->assertStringContainsString('data-uy-bagian="rsvp"', $html);
        $this->assertStringContainsString('data-uy-bagian="ucapan"', $html);
        $this->assertStringContainsString('data-uy-bagian="hadiah"', $html);
    }

    public function test_urutan_dan_sembunyi_dihormati(): void
    {
        $urutan = ['sampul', 'acara', 'mempelai', 'rundown', 'quotes', 'penutup'];
        $html = RenderUndangan::html($this->isi(), $this->bagian(['quotes'], $urutan), null);
        $ada = $this->urutanBagian($html);

        $this->assertSame('sampul', $ada[0]);
        $this->assertSame('penutup', end($ada));
        $this->assertNotContains('quotes', $ada);
        $this->assertLessThan(array_search('mempelai', $ada), array_search('acara', $ada));
        $this->assertLessThan(array_search('rundown', $ada), array_search('mempelai', $ada));
        $this->assertStringNotContainsString('Dan di antara tanda-tanda', $html);
    }

    public function test_sembunyi_juga_boleh_berbentuk_peta_dan_sampul_penutup_tetap_di_ujung(): void
    {
        $html = RenderUndangan::html($this->isi(), ['urutan' => ['penutup', 'acara', 'sampul'], 'sembunyi' => ['acara' => true, 'musik' => false]], null);
        $ada = $this->urutanBagian($html);

        $this->assertNotContains('acara', $ada);
        $this->assertContains('musik', $ada);
        $this->assertSame('sampul', $ada[0]);
        $this->assertSame('penutup', end($ada));
    }

    public function test_daftar_berulang_dan_urutannya(): void
    {
        $isi = $this->isi([
            '_daftar' => ['ev' => [7, 3], 'ls' => [], 'rd' => [1], 'kd' => [], 'tp' => [], 'tw' => [], 'ak' => [], 'wr' => [], 'gl' => []],
            'ev7_nama' => 'Ngunduh Mantu', 'ev3_nama' => 'Akad Nikah Utama', 'rd1_t' => '09.15', 'rd1_l' => 'Sesi Doa',
        ]);
        $html = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertLessThan(strpos($html, 'Akad Nikah Utama'), strpos($html, 'Ngunduh Mantu'));
        $this->assertStringNotContainsString('Resepsi', $html, 'item yang tidak ada di _daftar tidak boleh tampil');
        $this->assertStringContainsString('Sesi Doa', $html);
        $this->assertStringNotContainsString('Pertama Bertemu', $html);
        $this->assertStringNotContainsString('Bank BCA', $html);
    }

    public function test_nilai_di_escape_tanpa_html_mentah(): void
    {
        $xss = '<script>alert(1)</script><img src=x onerror=alert(2)>"\'';
        $isi = $this->isi([
            'priaP' => $xss, 'quote' => $xss, 'ev1_nama' => $xss, 'ev1_alamat' => $xss, 'ls1_b' => $xss, 'kd1_atas' => $xss,
            'rsTitle' => $xss, 'ptNama' => $xss, 'wr1_nama' => $xss, 'ak1_username' => $xss, 'lvCat' => $xss,
        ]);
        $html = RenderUndangan::html($isi, $this->bagian(), null, ['tamu' => ['nama' => $xss, 'alamat' => $xss, 'kode' => $xss], 'ucapan' => [['nama' => $xss, 'pesan' => $xss]]]);

        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringNotContainsString('onerror=alert(2)>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_url_javascript_dan_non_http_ditolak(): void
    {
        $isi = $this->isi([
            'ev1_maps' => 'javascript:alert(1)', 'ev2_maps' => 'data:text/html;base64,PHNjcmlwdD4=', 'lvLink' => 'javascript:alert(2)', 'lvBtn' => '1',
            'musUrl' => 'javascript:alert(3)', 'musLink' => 'vbscript:x', 'gcLokasi' => 'x',
            '_foto' => ['cover' => 'javascript:alert(4)', 'pria' => 'data:image/svg+xml;base64,AAAA', 'wanita' => '//evil.example/x.png', 'g1' => 'ftp://evil.example/x.png'],
            '_galeri' => ['layout' => 'grid', 'video' => 'javascript:alert(5)'],
        ]);
        $html = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertStringNotContainsString('javascript:', $html);
        $this->assertStringNotContainsString('vbscript:', $html);
        $this->assertStringNotContainsString('data:text/html', $html);
        $this->assertStringNotContainsString('data:image', $html);
        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_url_https_dan_foto_aman_dipakai(): void
    {
        $isi = $this->isi([
            'ev1_maps' => 'https://maps.google.com/?q=Grand+Ballroom', 'lvLink' => 'https://youtube.com/live/abc', 'lvBtn' => '1',
            '_foto' => ['cover' => 'https://cdn.example.com/c.jpg', 'pria' => '/storage/foto/pria.jpg', 'g1' => ['src' => 'https://cdn.example.com/g1.jpg', 'crop' => ['ratio' => '1:1', 'zoom' => 150, 'x' => 30, 'y' => 70]]],
        ]);
        $html = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertStringContainsString('href="https://maps.google.com/?q=Grand+Ballroom" target="_blank" rel="noopener noreferrer"', $html);
        $this->assertStringContainsString('href="https://youtube.com/live/abc"', $html);
        $this->assertStringContainsString('src="https://cdn.example.com/c.jpg"', $html);
        $this->assertStringContainsString('src="/storage/foto/pria.jpg"', $html);
        $this->assertStringContainsString('transform:scale(1.5)', $html);
    }

    public function test_atribut_data_kunci_hanya_pada_mode_edit(): void
    {
        $isi = $this->isi(['_foto' => ['cover' => 'https://cdn.example.com/c.jpg']]);
        $tamu = RenderUndangan::html($isi, $this->bagian(), null);
        $edit = RenderUndangan::html($isi, $this->bagian(), null, ['edit' => true]);

        $this->assertStringNotContainsString('data-kunci="', $tamu);
        foreach (['priaP', 'wanitaNama', 'ev1_nama', 'ev2_mapsBtn', 'ls1_t', 'rd1_l', 'kd1_no', 'foto:cover', 'foto:pria', 'foto:g1', 'bagian:acara', 'ak1_username', 'wr1_nama', 'ptNama'] as $kunci) {
            $this->assertStringContainsString('data-kunci="'.$kunci.'"', $edit, "tidak ada data-kunci {$kunci}");
        }
        $this->assertStringContainsString('data-item="ev:1"', $edit);
        $this->assertStringContainsString('data-uy-edit="1"', $edit);
        // bidang tanpa elemen kanvas (mis. label RSVP) tidak diberi data-kunci
        $this->assertStringNotContainsString('data-kunci="rsLabelNama"', $edit);
    }

    public function test_mode_edit_menampilkan_petunjuk_untuk_bidang_kosong(): void
    {
        $isi = $this->isi(['priaNama' => '', 'priaOrtu' => '']);
        $edit = RenderUndangan::html($isi, $this->bagian(), null, ['edit' => true]);
        $tamu = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertMatchesRegularExpression('/data-kunci="priaNama"[^>]*data-ph="Nama lengkap pria"/', $edit);
        $this->assertStringNotContainsString('Nama lengkap pria', $tamu);
    }

    public function test_bidang_disembunyikan_dan_gaya_timpaan(): void
    {
        $isi = $this->isi(['_sembunyi' => ['tagline' => true], '_gaya' => ['priaP' => ['fs' => 40, 'b' => false, 'ta' => 'left', 'c' => '#FF0000']]]);
        $html = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertStringNotContainsString('THE WEDDING OF', $html);
        $this->assertMatchesRegularExpression('/font-size:40px;[^"]*font-weight:400;[^"]*text-align:left;color:#FF0000/', $html);
    }

    public function test_gaya_timpaan_tidak_bisa_menyuntik_css(): void
    {
        $isi = $this->isi(['_gaya' => ['priaP' => ['fs' => '40px;background:url(//e.example)', 'ta' => 'left;x:y', 'c' => 'red;background:url(//e.example)']]]);
        $html = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertStringNotContainsString('e.example', $html);
    }

    public function test_warna_dan_font_dari_tema(): void
    {
        $tema = Tema::factory()->create(['warna_utama' => '#5B1530', 'warna_aksen' => '#E9C987', 'warna_permukaan' => '#F9EEF1', 'warna_halaman' => '#FFFFFF', 'warna_teks' => '#2E0B18', 'font_judul' => 'script']);
        $html = RenderUndangan::html($this->isi(), $this->bagian(), $tema);

        $this->assertStringContainsString('--uy-cov:#5B1530', $html);
        $this->assertStringContainsString('--uy-bgb:#F9EEF1', $html);
        $this->assertStringContainsString('--uy-ink:#2E0B18', $html);
        $this->assertStringContainsString('Snell Roundhand', $html);
    }

    public function test_warna_tema_tidak_valid_diganti_bawaan(): void
    {
        $tema = new Tema(['warna_utama' => 'red;} body{display:none', 'warna_aksen' => 'x', 'warna_permukaan' => 'y', 'warna_halaman' => 'z', 'warna_teks' => 'w', 'font_judul' => 'playfair']);
        $html = RenderUndangan::html($this->isi(), $this->bagian(), $tema);

        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringContainsString('--uy-cov:#1D4ED8', $html);
    }

    public function test_lebar_opsi_dibatasi_ke_satuan_aman(): void
    {
        $this->assertStringContainsString('max-width:420px;', RenderUndangan::html([], $this->bagian(), null, ['lebar' => 420]));
        $this->assertStringContainsString('max-width:100%;', RenderUndangan::html([], $this->bagian(), null, ['lebar' => '100%']));
        $this->assertStringContainsString('max-width:480px;', RenderUndangan::html([], $this->bagian(), null, ['lebar' => '1px;}x{']));
    }

    public function test_untuk_undangan_memakai_draf_atau_terbitan(): void
    {
        $tema = Tema::factory()->create();
        $draf = $this->isi(['priaP' => 'Draf']);
        $terbit = $this->isi(['priaP' => 'Terbit']);
        $u = Undangan::factory()->terbit()->dengan($tema)->create([
            'isi' => $draf, 'bagian' => $this->bagian(),
            'terbitan' => ['isi' => $terbit, 'bagian' => $this->bagian(['quotes'])],
        ]);

        $this->assertStringContainsString('>Draf<', RenderUndangan::untukUndangan($u));
        $this->assertStringContainsString('>Draf<', RenderUndangan::untukUndangan($u, false));
        $dariTerbitan = RenderUndangan::untukUndangan($u, true);
        $this->assertStringContainsString('>Terbit<', $dariTerbitan);
        $this->assertStringNotContainsString('>Draf<', $dariTerbitan);
        $this->assertNotContains('quotes', $this->urutanBagian($dariTerbitan));
        $this->assertContains('quotes', $this->urutanBagian(RenderUndangan::untukUndangan($u)));
    }

    public function test_terbitan_tanpa_isi_jatuh_ke_draf(): void
    {
        $u = Undangan::factory()->terbit()->create(['isi' => $this->isi(['priaP' => 'Draf']), 'bagian' => $this->bagian()]);

        $this->assertStringContainsString('>Draf<', RenderUndangan::untukUndangan($u, true));
    }

    public function test_isi_kosong_tidak_menggagalkan_render(): void
    {
        $html = RenderUndangan::html([], [], null);

        $this->assertStringStartsWith('<div class="uy-undangan"', $html);
        $this->assertSame('sampul', $this->urutanBagian($html)[0] ?? null);
    }

    public function test_tamu_hanya_tampil_bila_diberikan_atau_mode_edit(): void
    {
        $isi = $this->isi();

        $this->assertNotContains('tamu', $this->urutanBagian(RenderUndangan::html($isi, $this->bagian(), null)));
        $this->assertContains('tamu', $this->urutanBagian(RenderUndangan::html($isi, $this->bagian(), null, ['tamu' => ['nama' => 'Siti']])));
        $this->assertContains('tamu', $this->urutanBagian(RenderUndangan::html($isi, $this->bagian(), null, ['edit' => true])));
    }

    public function test_tautan_kalender_dan_hitung_mundur(): void
    {
        $isi = $this->isi(['gcAktif' => '1', 'gcTgl' => '2099-02-14', 'gcMulai' => '10:00', 'gcSelesai' => '13:00', 'hmTgl' => '2099-02-14', 'hmJam' => '08:00']);
        $html = RenderUndangan::html($isi, $this->bagian(), null);

        $this->assertStringContainsString('calendar.google.com/calendar/render?action=TEMPLATE', $html);
        $this->assertStringContainsString('dates=20990214T100000/20990214T130000', $html);
        $this->assertStringContainsString('data-uy-bagian="hitung-mundur"', $html);
        $this->assertStringContainsString('data-target="2099-02-14T08:00:00+07:00"', $html);

        $lewat = RenderUndangan::html($this->isi(['hmTgl' => '2020-01-01']), $this->bagian(), null);
        $this->assertStringNotContainsString('class="uy-hitung"', $lewat);
        $this->assertStringContainsString('hari bahagia kami telah tiba', $lewat);
    }

    public function test_latar_tetap_hanya_menerima_url_aman(): void
    {
        $aman = RenderUndangan::html($this->isi(['_latarTetap' => ['on' => true, 'src' => 'https://cdn.example.com/bg.jpg']]), $this->bagian(), null);
        $this->assertStringContainsString('class="uy-bgf"', $aman);
        $buruk = RenderUndangan::html($this->isi(['_latarTetap' => ['on' => true, 'src' => 'javascript:alert(1)']]), $this->bagian(), null);
        $this->assertStringNotContainsString('uy-bgf"', $buruk);
    }
}
