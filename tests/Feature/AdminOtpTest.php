<?php

namespace Tests\Feature;

use App\Livewire\Admin\OtpSetting;
use App\Livewire\Auth\VerifikasiEmail;
use App\Mail\KodeOtpMail;
use App\Models\KodeOtp;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\LayananOtp;
use App\Support\HasilOtp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class AdminOtpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->admin()->create(['email' => 'bos@contoh.com']);
    }

    private function halaman(?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->admin)->test(OtpSetting::class);
    }

    private function atur(array $nilai): void
    {
        Pengaturan::simpan('otp', $nilai);
    }

    /** Kode terkirim terakhir, dibaca dari surel palsu. */
    private function kodeTerkirim(): string
    {
        $kode = null;
        Mail::assertSent(KodeOtpMail::class, function (KodeOtpMail $m) use (&$kode) {
            $kode = $m->kode;

            return true;
        });

        return (string) $kode;
    }

    // ---- akses ----

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/admin/otp')->assertRedirect(route('masuk'));
    }

    public function test_non_admin_ditolak_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/otp')->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(OtpSetting::class)->assertForbidden();
    }

    public function test_admin_melihat_halaman_dengan_nilai_bawaan(): void
    {
        $this->actingAs($this->admin)->get('/admin/otp')->assertOk()->assertSeeLivewire(OtpSetting::class)->assertSee('Verifikasi OTP');

        $this->halaman()->assertSet('panjang', '6')->assertSet('berlakuMenit', '10')->assertSet('maksSalah', '5')->assertSet('jedaDetik', '60')->assertSet('maksPerJam', '5')->assertSee('Bawaan');
    }

    public function test_aksi_simpan_memeriksa_ulang_admin(): void
    {
        $komponen = $this->halaman();
        $this->actingAs(User::factory()->create());

        $komponen->call('simpan')->assertForbidden();
        $this->assertNull(Pengaturan::find('otp'));
    }

    // ---- layanan membaca pengaturan ----

    public function test_tanpa_pengaturan_layanan_memakai_nilai_bawaan_lama(): void
    {
        $a = app(LayananOtp::class)->aturan();

        $this->assertSame(['panjang' => 6, 'berlaku_menit' => 10, 'maks_salah' => 5, 'jeda_detik' => 60, 'maks_per_jam' => 5], $a);
        $this->assertSame([LayananOtp::PANJANG, LayananOtp::BERLAKU_MENIT, LayananOtp::MAKS_SALAH, LayananOtp::JEDA_KIRIM_DETIK, LayananOtp::MAKS_KIRIM_PER_JAM], array_values($a));
    }

    public function test_simpan_pengaturan_langsung_dipakai_layanan(): void
    {
        Mail::fake();

        $this->halaman()->set('panjang', '8')->set('berlakuMenit', '15')->set('maksSalah', '3')->set('jedaDetik', '30')->set('maksPerJam', '4')->call('simpan')->assertHasNoErrors()->assertDispatched('toast', tipe: 'sukses');

        $this->assertSame(['panjang' => 8, 'berlaku_menit' => 15, 'maks_salah' => 3, 'jeda_detik' => 30, 'maks_per_jam' => 4], app(LayananOtp::class)->aturan());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'atur_otp', 'admin_email' => 'bos@contoh.com']);

        app(LayananOtp::class)->kirim('x@contoh.com', LayananOtp::TUJUAN_VERIFIKASI, 'X');
        Mail::assertSent(KodeOtpMail::class, fn (KodeOtpMail $m) => strlen($m->kode) === 8 && ctype_digit($m->kode) && $m->menit === 15);
        $this->assertEqualsWithDelta(15, now()->diffInMinutes(KodeOtp::first()->expires_at, false), 1);
    }

    public function test_panjang_kode_baru_diperiksa_sesuai_panjangnya(): void
    {
        Mail::fake();
        $this->atur(['panjang' => 4]);
        $otp = app(LayananOtp::class);

        $otp->kirim('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI);
        $kode = $this->kodeTerkirim();
        $this->assertSame(4, strlen($kode));

        $this->assertSame(HasilOtp::Salah, $otp->periksa('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI, $kode === '0000' ? '1111' : '0000'));
        $this->assertSame(HasilOtp::Berhasil, $otp->periksa('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI, $kode));
    }

    public function test_batas_percobaan_salah_mengikuti_pengaturan(): void
    {
        Mail::fake();
        $this->atur(['maks_salah' => 2]);
        $otp = app(LayananOtp::class);
        $otp->kirim('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI);

        $this->assertSame(HasilOtp::Salah, $otp->periksa('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI, '000000'));
        $this->assertSame(1, $otp->sisaPercobaan('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI));
        $this->assertSame(HasilOtp::Terkunci, $otp->periksa('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI, '000000'));
        $this->assertFalse($otp->adaKodeAktif('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI));
    }

    public function test_jeda_dan_batas_per_jam_mengikuti_pengaturan(): void
    {
        Mail::fake();
        $this->atur(['jeda_detik' => 10, 'maks_per_jam' => 2]);
        $otp = app(LayananOtp::class);

        $this->assertSame(0, $otp->kirim('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI));
        $tunggu = $otp->kirim('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI);
        $this->assertGreaterThan(0, $tunggu);
        $this->assertLessThanOrEqual(10, $tunggu);

        $this->travel(11)->seconds();
        RateLimiter::clear('otp-jeda:'.LayananOtp::TUJUAN_VERIFIKASI.':a@contoh.com');
        $this->assertSame(0, $otp->kirim('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI));
        RateLimiter::clear('otp-jeda:'.LayananOtp::TUJUAN_VERIFIKASI.':a@contoh.com');
        $this->assertGreaterThan(10, $otp->kirim('a@contoh.com', LayananOtp::TUJUAN_VERIFIKASI), 'kiriman ketiga kena batas per jam');
        Mail::assertSent(KodeOtpMail::class, 2);
    }

    public function test_pengaturan_rusak_di_basis_data_dipotong_ke_rentang_aman(): void
    {
        $this->atur(['panjang' => 99, 'berlaku_menit' => 0, 'maks_salah' => 'banyak', 'jeda_detik' => -5, 'maks_per_jam' => 1000]);

        $this->assertSame(['panjang' => 8, 'berlaku_menit' => 1, 'maks_salah' => 5, 'jeda_detik' => 10, 'maks_per_jam' => 30], app(LayananOtp::class)->aturan());
    }

    // ---- validasi ----

    public function test_validasi_rentang(): void
    {
        $kasus = [
            ['panjang', '3'], ['panjang', '9'], ['panjang', ''], ['panjang', 'abc'],
            ['berlakuMenit', '0'], ['berlakuMenit', '31'],
            ['maksSalah', '0'], ['maksSalah', '11'],
            ['jedaDetik', '9'], ['jedaDetik', '301'],
            ['maksPerJam', '0'], ['maksPerJam', '31'], ['maksPerJam', '2.5'], ['maksPerJam', '-1'],
        ];
        foreach ($kasus as [$bidang, $nilai]) {
            $this->halaman()->set($bidang, $nilai)->call('simpan')->assertHasErrors($bidang);
        }
        $this->assertNull(Pengaturan::find('otp'));
    }

    public function test_jeda_harus_lebih_singkat_dari_masa_berlaku(): void
    {
        $this->halaman()->set('berlakuMenit', '1')->set('jedaDetik', '60')->call('simpan')->assertHasErrors('jedaDetik');
        $this->halaman()->set('berlakuMenit', '1')->set('jedaDetik', '59')->call('simpan')->assertHasNoErrors();
    }

    public function test_batas_rentang_diterima(): void
    {
        $this->halaman()->set('panjang', '4')->set('berlakuMenit', '30')->set('maksSalah', '10')->set('jedaDetik', '300')->set('maksPerJam', '30')->call('simpan')->assertHasNoErrors();
        $this->halaman()->set('panjang', '8')->set('berlakuMenit', '1')->set('maksSalah', '1')->set('jedaDetik', '10')->set('maksPerJam', '1')->call('simpan')->assertHasNoErrors();
    }

    public function test_batalkan_dan_reset_ke_bawaan(): void
    {
        $this->atur(['panjang' => 8, 'berlaku_menit' => 20, 'maks_salah' => 4, 'jeda_detik' => 90, 'maks_per_jam' => 9]);

        $this->halaman()->assertSet('panjang', '8')->set('panjang', '5')->assertSee('Belum disimpan')->call('batalkan')->assertSet('panjang', '8')
            ->call('bukaReset')->assertSet('tampilReset', true)->call('resetBawaan')->assertSet('tampilReset', false)
            ->assertSet('panjang', '6')->assertSet('berlakuMenit', '10');

        $this->assertSame(6, app(LayananOtp::class)->aturan()['panjang']);
    }

    public function test_pilih_panjang_membatasi_rentang(): void
    {
        $this->halaman()->call('pilihPanjang', 99)->assertSet('panjang', '8')->call('pilihPanjang', 1)->assertSet('panjang', '4');
    }

    // ---- uji kirim ----

    public function test_uji_kirim_ke_email_admin_sendiri_dan_verifikasi(): void
    {
        Mail::fake();
        $h = $this->halaman()->call('kirimUji')->assertSee('Kode uji dikirim ke bos@contoh.com');
        Mail::assertSent(KodeOtpMail::class, fn (KodeOtpMail $m) => $m->hasTo('bos@contoh.com') && $m->tujuan === LayananOtp::TUJUAN_UJI);
        $kode = $this->kodeTerkirim();

        // Kode tidak pernah muncul di halaman.
        $this->assertStringNotContainsString($kode, strip_tags($h->html()));

        $h->set('kodeUji', $kode === '000000' ? '111111' : '000000')->call('periksaUji')->assertSee('Kode salah')->assertSet('kodeUji', '');
        $h->set('kodeUji', $kode)->call('periksaUji')->assertSee('Kode benar');
    }

    public function test_uji_kirim_menghormati_jeda(): void
    {
        Mail::fake();

        $this->halaman()->call('kirimUji')->call('kirimUji')->assertSee('Tunggu');
        Mail::assertSent(KodeOtpMail::class, 1);
    }

    public function test_uji_kirim_dibatasi_laju_per_admin(): void
    {
        Mail::fake();
        foreach (range(1, 5) as $_) {
            RateLimiter::hit('otp-uji:'.$this->admin->id, 600);
        }

        $this->halaman()->call('kirimUji')->assertSee('Terlalu banyak uji kirim');
        Mail::assertNothingSent();
    }

    public function test_periksa_uji_tanpa_kode_ditolak(): void
    {
        $this->halaman()->set('kodeUji', 'abc')->call('periksaUji')->assertSee('Masukkan kode dari email dulu');
    }

    public function test_periksa_uji_dibatasi_laju(): void
    {
        foreach (range(1, 15) as $_) {
            RateLimiter::hit('otp-uji-cek:'.$this->admin->id, 600);
        }

        $this->halaman()->set('kodeUji', '123456')->call('periksaUji')->assertSee('Terlalu banyak percobaan');
    }

    // ---- statistik ----

    public function test_statistik_dari_tabel_otp_tanpa_menampilkan_kode(): void
    {
        $this->atur(['maks_salah' => 3]);
        $dasar = ['code_hash' => 'HASHRAHASIA1234567890', 'attempts' => 0, 'consumed_at' => null];
        KodeOtp::create($dasar + ['email' => 'a@x.test', 'tujuan' => 'verifikasi_email', 'expires_at' => now()->addMinutes(5)]);                              // aktif
        KodeOtp::create(['consumed_at' => now()] + $dasar + ['email' => 'b@x.test', 'tujuan' => 'verifikasi_email', 'expires_at' => now()->addMinutes(5)]);       // terpakai
        KodeOtp::create($dasar + ['email' => 'c@x.test', 'tujuan' => 'reset_sandi', 'expires_at' => now()->subMinutes(5)]);                                      // kedaluwarsa
        KodeOtp::create(['attempts' => 3] + $dasar + ['email' => 'd@x.test', 'tujuan' => 'reset_sandi', 'expires_at' => now()->addMinutes(5)]);                  // terkunci
        KodeOtp::create($dasar + ['email' => 'bos@contoh.com', 'tujuan' => LayananOtp::TUJUAN_UJI, 'expires_at' => now()->addMinutes(5)]);                       // uji: tidak dihitung

        $h = $this->halaman();
        $html = preg_replace('/\s+/', ' ', $h->html());

        $this->assertMatchesRegularExpression('/Dibuat 24 jam terakhir<\/dt> <dd[^>]*>\s*4\s*<\/dd>/', $html);
        $this->assertMatchesRegularExpression('/Aktif sekarang<\/dt> <dd[^>]*>\s*1\s*<\/dd>/', $html);
        $this->assertMatchesRegularExpression('/Terpakai \(berhasil\)<\/dt> <dd[^>]*>\s*1\s*<\/dd>/', $html);
        $this->assertMatchesRegularExpression('/Kedaluwarsa<\/dt> <dd[^>]*>\s*1\s*<\/dd>/', $html);
        $this->assertMatchesRegularExpression('/Terkunci<\/dt> <dd[^>]*>\s*1\s*<\/dd>/', $html);
        $this->assertStringContainsString('Verifikasi email: 2', $html);
        $this->assertStringContainsString('Atur ulang sandi: 2', $html);
        $this->assertStringNotContainsString('HASHRAHASIA', $html);
        $this->assertStringNotContainsString('a@x.test', $html);
    }

    public function test_statistik_kosong_tidak_error(): void
    {
        $this->halaman()->assertOk()->assertSee('Statistik OTP');
    }

    // ---- regresi alur pengguna dengan panjang berbeda ----

    public function test_halaman_verifikasi_email_mengikuti_panjang_kode(): void
    {
        Mail::fake();
        $this->atur(['panjang' => 4]);
        $u = User::factory()->unverified()->create();

        $this->actingAs($u)->get('/verifikasi-email')->assertOk()->assertSee('kode 4 digit');
        Livewire::actingAs($u)->test(VerifikasiEmail::class)->set('kode', '123456')->call('verifikasi')->assertHasErrors('kode');
        $kode = $this->kodeTerkirim();
        Livewire::actingAs($u)->test(VerifikasiEmail::class)->set('kode', $kode)->call('verifikasi')->assertHasNoErrors();
        $this->assertTrue($u->fresh()->hasVerifiedEmail());
    }
}
