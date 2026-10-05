<?php

namespace Tests\Feature;

use App\Livewire\Auth\VerifikasiEmail;
use App\Mail\KodeOtpMail;
use App\Models\KodeOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class VerifikasiEmailTest extends TestCase
{
    use RefreshDatabase;

    private function kodeTerakhir(): string
    {
        return Mail::sent(KodeOtpMail::class)->last()->kode;
    }

    private function salahnya(string $benar): string
    {
        return $benar === '000000' ? '111111' : '000000';
    }

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/verifikasi-email')->assertRedirect(route('masuk'));
    }

    public function test_membuka_halaman_mengirim_kode_otomatis(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();

        $this->actingAs($user);
        Livewire::test(VerifikasiEmail::class)->assertSee('Verifikasi email')->assertSee('Bukan kamu? Keluar');

        Mail::assertSent(KodeOtpMail::class, 1);
    }

    public function test_akun_terverifikasi_dialihkan_ke_beranda(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(VerifikasiEmail::class)->assertRedirect(route('beranda'));
    }

    public function test_kode_benar_memverifikasi_email(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);

        Livewire::test(VerifikasiEmail::class)
            ->set('kode', $this->kodeTerakhir())->call('verifikasi')
            ->assertHasNoErrors()->assertRedirect(route('beranda'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->get('/beranda')->assertOk()->assertSee('Email terverifikasi');
    }

    public function test_kode_tidak_bisa_dipakai_dua_kali(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->unverified()->create());
        $uji = Livewire::test(VerifikasiEmail::class);
        $kode = $this->kodeTerakhir();

        $uji->set('kode', $kode)->call('verifikasi')->assertHasNoErrors();
        $uji->set('kode', $kode)->call('verifikasi')->assertHasErrors('kode');
    }

    public function test_kode_salah_mengurangi_kesempatan_lalu_terkunci(): void
    {
        Mail::fake();
        $this->actingAs($user = User::factory()->unverified()->create());
        $uji = Livewire::test(VerifikasiEmail::class);
        $benar = $this->kodeTerakhir();
        $salah = $this->salahnya($benar);

        $uji->set('kode', $salah)->call('verifikasi');
        $this->assertStringContainsString('Sisa 4 percobaan', $uji->errors()->first('kode'));

        for ($i = 0; $i < 4; $i++) {
            $uji->set('kode', $salah)->call('verifikasi');
        }
        $this->assertStringContainsString('Terlalu banyak kode salah', $uji->errors()->first('kode'));

        // Setelah terkunci, kode yang benar pun ditolak.
        $uji->set('kode', $benar)->call('verifikasi')->assertHasErrors('kode');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_kode_kedaluwarsa_ditolak(): void
    {
        Mail::fake();
        $this->actingAs($user = User::factory()->unverified()->create());
        $uji = Livewire::test(VerifikasiEmail::class);
        $kode = $this->kodeTerakhir();

        $this->travel(11)->minutes();
        $uji->set('kode', $kode)->call('verifikasi')->assertHasErrors('kode');
        $this->assertStringContainsString('kedaluwarsa', $uji->errors()->first('kode'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_format_kode_harus_enam_digit(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->unverified()->create());

        Livewire::test(VerifikasiEmail::class)->set('kode', '12ab')->call('verifikasi')->assertHasErrors('kode');
    }

    public function test_kirim_ulang_dibatasi_jeda_60_detik_lalu_mengganti_kode(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->unverified()->create());
        $uji = Livewire::test(VerifikasiEmail::class);
        $lama = $this->kodeTerakhir();

        $uji->call('kirimUlang');
        $this->assertStringContainsString('Tunggu', $uji->errors()->first('kode'));
        Mail::assertSent(KodeOtpMail::class, 1);

        $this->travel(61)->seconds();
        $uji->call('kirimUlang')->assertHasNoErrors()->assertSee('Kode baru sudah dikirim');
        Mail::assertSent(KodeOtpMail::class, 2);

        $baru = $this->kodeTerakhir();
        if ($baru !== $lama) {
            $uji->set('kode', $lama)->call('verifikasi')->assertHasErrors('kode');
        }
        $uji->set('kode', $baru)->call('verifikasi')->assertRedirect(route('beranda'));
    }

    public function test_maksimal_lima_pengiriman_per_jam(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->unverified()->create());
        $uji = Livewire::test(VerifikasiEmail::class); // pengiriman ke-1

        for ($i = 0; $i < 4; $i++) {
            $this->travel(61)->seconds();
            $uji->call('kirimUlang')->assertHasNoErrors();
        }
        Mail::assertSent(KodeOtpMail::class, 5);

        $this->travel(61)->seconds();
        $uji->call('kirimUlang')->assertHasErrors('kode');
        Mail::assertSent(KodeOtpMail::class, 5);
    }

    public function test_kode_tidak_tersimpan_sebagai_teks_biasa(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->unverified()->create());
        Livewire::test(VerifikasiEmail::class);

        $kode = $this->kodeTerakhir();
        $baris = KodeOtp::first();
        $this->assertNotSame($kode, $baris->code_hash);
        $this->assertSame(64, strlen($baris->code_hash));
    }
}
