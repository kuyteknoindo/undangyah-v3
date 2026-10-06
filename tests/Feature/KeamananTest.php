<?php

namespace Tests\Feature;

use App\Livewire\Auth\Daftar;
use App\Livewire\Auth\LupaSandi;
use App\Livewire\Bagikan;
use App\Mail\KodeOtpMail;
use App\Models\Notifikasi;
use App\Models\Tayangan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\CatatTayangan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/** Regresi hasil audit keamanan (Fase 9). */
class KeamananTest extends TestCase
{
    use RefreshDatabase;

    private function sesiDb(int $userId, string $id): void
    {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'ip_address' => '203.0.113.9', 'user_agent' => 'x', 'payload' => '', 'last_activity' => time()]);
    }

    public function test_pendaftar_sah_yang_mengambil_alih_akun_belum_terverifikasi_mencabut_sesi_penyerang(): void
    {
        Mail::fake();
        $penyerang = User::factory()->unverified()->create(['email' => 'korban@contoh.com']);
        $this->sesiDb($penyerang->id, 'sesi-penyerang');

        Livewire::test(Daftar::class)
            ->set('nama', 'Korban Asli')->set('email', 'korban@contoh.com')->set('sandi', 'Rahasia123')->set('sandiKonfirmasi', 'Rahasia123')->set('setuju', true)
            ->call('daftar')->assertHasNoErrors();

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-penyerang']);
    }

    public function test_atur_ulang_kata_sandi_mencabut_semua_sesi_lama(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ada@contoh.com']);
        $this->sesiDb($user->id, 'sesi-lama');
        $this->sesiDb(User::factory()->create()->id, 'sesi-orang-lain');

        $uji = Livewire::test(LupaSandi::class)->set('email', 'ada@contoh.com')->call('kirimKode');
        $uji->set('kode', Mail::sent(KodeOtpMail::class)->last()->kode)->call('verifikasi')->assertSet('langkah', 3);
        $uji->set('sandi', 'SandiBaru123')->set('sandiKonfirmasi', 'SandiBaru123')->call('simpanSandi')->assertSet('langkah', 4);

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-lama']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-orang-lain']);
    }

    public function test_tautan_notifikasi_tidak_bisa_jadi_alihan_terbuka(): void
    {
        foreach (['/\\evil.example', "/\t/evil.example", '//evil.example', 'javascript:alert(1)', '/ok path', "/a\nb", 'data:text/html,x', '/x\\y'] as $buruk) {
            $this->assertNull(Notifikasi::tautanAman($buruk), $buruk);
        }
        $this->assertSame('/admin/pesanan?cari=INV-2026-0001', Notifikasi::tautanAman('/admin/pesanan?cari=INV-2026-0001'));
        $this->assertSame('https://contoh.com/a?b=1', Notifikasi::tautanAman('https://contoh.com/a?b=1'));
    }

    public function test_banjir_tayangan_dengan_user_agent_acak_dibatasi_per_ip(): void
    {
        $u = Undangan::factory()->create();
        $layanan = app(CatatTayangan::class);

        for ($i = 0; $i < CatatTayangan::BATAS_PER_JAM + 25; $i++) {
            $r = Request::create('/raka', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.7', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Peramban'.$i]);
            $layanan->catat($r, $u);
        }

        $this->assertSame(CatatTayangan::BATAS_PER_JAM, Tayangan::where('undangan_id', $u->id)->count());

        // IP lain tidak ikut terkunci.
        $lain = Request::create('/raka', 'GET', [], [], [], ['REMOTE_ADDR' => '203.0.113.8', 'HTTP_USER_AGENT' => 'Mozilla/5.0 Lain']);
        $this->assertTrue($layanan->catat($lain, $u));
    }

    public function test_batas_tampil_bagikan_tidak_bisa_dibesarkan_klien(): void
    {
        $u = Undangan::factory()->create();
        Livewire::actingAs($u->pengguna)->test(Bagikan::class)
            ->set('batas', 1000000)->assertSet('batas', Bagikan::MAKS_TAMU)
            ->set('batas', -5)->assertSet('batas', 1);
    }

    public function test_bingkai_pratinjau_tidak_error_untuk_parameter_array(): void
    {
        $u = Undangan::factory()->create();
        $respons = $this->actingAs($u->pengguna)->get('/pratinjau/bingkai/'.$u->id.'?v[]=x');
        $this->assertLessThan(500, $respons->status());
    }
}
