<?php

namespace Tests\Feature;

use App\Support\SkemaUndangan;
use Tests\TestCase;

class SkemaUndanganTest extends TestCase
{
    public function test_skema_dimuat_lengkap_dari_desain(): void
    {
        $this->assertCount(125, SkemaUndangan::bidang());
        $this->assertCount(18, SkemaUndangan::bagian());
        $this->assertSame(['sampul', 'mempelai', 'acara', 'lovestory', 'quotes', 'foto', 'gallery', 'musik', 'rundown', 'turut', 'kado', 'livestream', 'gcal', 'social', 'dresscode', 'rsvp', 'tamu', 'penutup'], array_values(array_unique([...['sampul'], ...SkemaUndangan::konten(), ...SkemaUndangan::fitur(), 'penutup'])));
        $this->assertSame('Raka', SkemaUndangan::nilaiAwal()['priaP']);
        $this->assertSame(['ev', 'ls', 'rd', 'kd', 'tp', 'tw', 'ak', 'wr'], array_keys(SkemaUndangan::templat()));
    }

    public function test_isi_awal_memuat_item_daftar_dan_urutannya(): void
    {
        $isi = SkemaUndangan::isiAwal();

        $this->assertSame('Akad Nikah', $isi['ev1_nama']);
        $this->assertSame([1, 2], $isi['_daftar']['ev']);
        $this->assertSame('2019', $isi['ls1_y']);
        $this->assertSame([1, 2, 3, 4, 5, 6], $isi['_daftar']['gl']);
    }

    public function test_setiap_bidang_punya_jenis_dikenal(): void
    {
        foreach (SkemaUndangan::bidang() as $kunci => $b) {
            $this->assertContains($b['kind'], ['text', 'time', 'date', 'url', 'select', 'switch'], "jenis tak dikenal pada {$kunci}");
            $this->assertArrayHasKey($b['g'], SkemaUndangan::bagian(), "grup {$kunci} bukan bagian");
        }
    }
}
