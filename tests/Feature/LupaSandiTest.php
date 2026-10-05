<?php

namespace Tests\Feature;

use App\Livewire\Auth\LupaSandi;
use App\Livewire\Auth\Masuk;
use App\Mail\KodeOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class LupaSandiTest extends TestCase
{
    use RefreshDatabase;

    private function kode(): string
    {
        return Mail::sent(KodeOtpMail::class)->last()->kode;
    }

    public function test_halaman_tampil(): void
    {
        $this->get('/lupa-sandi')->assertOk()->assertSee('Lupa kata sandi?')->assertSee('Kirim Kode');
    }

    public function test_email_tidak_terdaftar_tampak_sama_tapi_tidak_mengirim_email(): void
    {
        Mail::fake();

        $uji = Livewire::test(LupaSandi::class)->set('email', 'tidakada@contoh.com')->call('kirimKode');

        $uji->assertHasNoErrors()->assertSet('langkah', 2)->assertSee('terdaftar');
        Mail::assertNothingSent();
    }

    public function test_email_terdaftar_menerima_kode(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ada@contoh.com']);

        Livewire::test(LupaSandi::class)->set('email', 'ADA@contoh.com')->call('kirimKode')->assertSet('langkah', 2);

        Mail::assertSent(KodeOtpMail::class, fn ($m) => $m->hasTo('ada@contoh.com') && $m->tujuan === 'reset_sandi');
    }

    public function test_email_tidak_valid_ditolak(): void
    {
        Livewire::test(LupaSandi::class)->set('email', 'x')->call('kirimKode')->assertHasErrors('email')->assertSet('langkah', 1);
    }

    public function test_alur_lengkap_mengganti_kata_sandi(): void
    {
        Mail::fake();
        $user = User::factory()->unverified()->create(['email' => 'ada@contoh.com', 'remember_token' => 'lama']);

        $uji = Livewire::test(LupaSandi::class)->set('email', 'ada@contoh.com')->call('kirimKode');
        $uji->set('kode', $this->kode())->call('verifikasi')->assertHasNoErrors()->assertSet('langkah', 3);
        $uji->set('sandi', 'SandiBaru123')->set('sandiKonfirmasi', 'SandiBaru123')->call('simpanSandi')
            ->assertHasNoErrors()->assertSet('langkah', 4)->assertSee('Kata sandi diperbarui');

        $user->refresh();
        $this->assertTrue(Hash::check('SandiBaru123', $user->password));
        $this->assertNotSame('lama', $user->remember_token);
        $this->assertTrue($user->hasVerifiedEmail());

        $this->assertGuest();
        Livewire::test(Masuk::class)->set('email', 'ada@contoh.com')->set('sandi', 'SandiBaru123')->call('masuk')->assertRedirect(route('beranda'));
    }

    public function test_kode_salah_dan_terkunci_setelah_lima_kali(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ada@contoh.com']);
        $uji = Livewire::test(LupaSandi::class)->set('email', 'ada@contoh.com')->call('kirimKode');
        $benar = $this->kode();
        $salah = $benar === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 5; $i++) {
            $uji->set('kode', $salah)->call('verifikasi')->assertHasErrors('kode')->assertSet('langkah', 2);
        }
        $uji->set('kode', $benar)->call('verifikasi')->assertHasErrors('kode')->assertSet('langkah', 2);
    }

    public function test_langkah_tidak_bisa_dilompati_dari_klien(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(LupaSandi::class)->set('langkah', 3);
    }

    public function test_simpan_sandi_tanpa_izin_sesi_ditolak(): void
    {
        $user = User::factory()->create(['email' => 'ada@contoh.com']);

        Livewire::test(LupaSandi::class)
            ->set('sandi', 'SandiBaru123')->set('sandiKonfirmasi', 'SandiBaru123')->call('simpanSandi')
            ->assertHasErrors('sandi')->assertSet('langkah', 1);

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_izin_untuk_email_lain_atau_yang_kedaluwarsa_ditolak(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'ada@contoh.com']);
        $uji = Livewire::test(LupaSandi::class)->set('email', 'ada@contoh.com')->call('kirimKode');
        $uji->set('kode', $this->kode())->call('verifikasi')->assertSet('langkah', 3);

        $this->travel(16)->minutes();
        $uji->set('sandi', 'SandiBaru123')->set('sandiKonfirmasi', 'SandiBaru123')->call('simpanSandi')->assertHasErrors('sandi');
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_aturan_kata_sandi_baru(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ada@contoh.com']);
        $uji = Livewire::test(LupaSandi::class)->set('email', 'ada@contoh.com')->call('kirimKode');
        $uji->set('kode', $this->kode())->call('verifikasi');

        $uji->set('sandi', 'pendek1')->set('sandiKonfirmasi', 'pendek1')->call('simpanSandi')->assertHasErrors('sandi')->assertSet('langkah', 3);
        $uji->set('sandi', 'SandiBaru123')->set('sandiKonfirmasi', 'Berbeda123')->call('simpanSandi')->assertHasErrors('sandiKonfirmasi');
    }

    public function test_ubah_email_kembali_ke_langkah_awal_dan_mencabut_izin(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'ada@contoh.com']);
        $uji = Livewire::test(LupaSandi::class)->set('email', 'ada@contoh.com')->call('kirimKode');
        $uji->set('kode', $this->kode())->call('verifikasi')->assertSet('langkah', 3);

        $uji->call('ubahEmail')->assertSet('langkah', 1);
        $uji->set('sandi', 'SandiBaru123')->set('sandiKonfirmasi', 'SandiBaru123')->call('simpanSandi')->assertHasErrors('sandi');
    }

    public function test_dibatasi_permintaan_kode_per_ip(): void
    {
        Mail::fake();

        for ($i = 0; $i < 8; $i++) {
            Livewire::test(LupaSandi::class)->set('email', "u{$i}@contoh.com")->call('kirimKode')->assertHasNoErrors();
        }
        Livewire::test(LupaSandi::class)->set('email', 'u9@contoh.com')->call('kirimKode')->assertHasErrors('email');
    }
}
