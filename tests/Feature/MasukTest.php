<?php

namespace Tests\Feature;

use App\Livewire\Auth\Masuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasukTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_masuk_tampil(): void
    {
        $this->get('/masuk')->assertOk()->assertSee('Selamat datang kembali')->assertSee('Lupa kata sandi?')->assertSee('Daftar sekarang');
    }

    public function test_masuk_berhasil_mengarah_ke_beranda(): void
    {
        $user = User::factory()->create(['email' => 'deni@contoh.com']);

        Livewire::test(Masuk::class)
            ->set('email', ' Deni@Contoh.com ')->set('sandi', 'password')
            ->call('masuk')
            ->assertHasNoErrors()->assertRedirect(route('beranda'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_sandi_salah_dan_email_tak_dikenal_memberi_pesan_yang_sama(): void
    {
        User::factory()->create(['email' => 'ada@contoh.com']);

        $a = Livewire::test(Masuk::class)->set('email', 'ada@contoh.com')->set('sandi', 'salah-salah')->call('masuk');
        $b = Livewire::test(Masuk::class)->set('email', 'tidakada@contoh.com')->set('sandi', 'salah-salah')->call('masuk');

        $a->assertHasErrors('form');
        $b->assertHasErrors('form');
        $this->assertSame($a->errors()->first('form'), $b->errors()->first('form'));
        $this->assertSame('Email atau kata sandi salah.', $a->errors()->first('form'));
        $this->assertGuest();
    }

    public function test_isian_wajib_divalidasi(): void
    {
        Livewire::test(Masuk::class)->call('masuk')->assertHasErrors(['email' => 'required', 'sandi' => 'required']);
        Livewire::test(Masuk::class)->set('email', 'bukan-email')->set('sandi', 'x')->call('masuk')->assertHasErrors(['email' => 'email']);
    }

    public function test_dibatasi_setelah_lima_kali_gagal_walau_sandi_benar(): void
    {
        User::factory()->create(['email' => 'batas@contoh.com']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Masuk::class)->set('email', 'batas@contoh.com')->set('sandi', 'salah')->call('masuk')->assertHasErrors('form');
        }

        $uji = Livewire::test(Masuk::class)->set('email', 'batas@contoh.com')->set('sandi', 'password')->call('masuk');
        $uji->assertHasErrors('form');
        $this->assertStringContainsString('Terlalu banyak percobaan', $uji->errors()->first('form'));
        $this->assertGuest();
    }

    public function test_akun_belum_terverifikasi_diarahkan_ke_halaman_verifikasi(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get('/beranda')->assertRedirect(route('verification.notice'));
    }

    public function test_pengguna_yang_sudah_masuk_tidak_melihat_halaman_masuk(): void
    {
        $this->actingAs(User::factory()->create())->get('/masuk')->assertRedirect(route('beranda'));
    }

    public function test_tombol_google_dan_whatsapp_menampilkan_info_segera_hadir(): void
    {
        Livewire::test(Masuk::class)->call('segera', 'google')->assertSee('Masuk dengan Google segera hadir');
        Livewire::test(Masuk::class)->call('segera', 'wa')->assertSee('Masuk dengan WhatsApp segera hadir');
    }
}
