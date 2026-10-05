<?php

namespace Tests\Feature;

use App\Livewire\Auth\Daftar;
use App\Mail\KodeOtpMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class DaftarTest extends TestCase
{
    use RefreshDatabase;

    private function isi($uji, array $ubah = [])
    {
        $data = ['nama' => 'Deni Saputra', 'email' => 'baru@contoh.com', 'whatsapp' => '', 'sandi' => 'Rahasia123', 'sandiKonfirmasi' => 'Rahasia123', 'setuju' => true] + [];
        foreach (array_merge($data, $ubah) as $k => $v) {
            $uji->set($k, $v);
        }

        return $uji;
    }

    public function test_halaman_daftar_tampil(): void
    {
        $this->get('/daftar')->assertOk()->assertSee('Buat akun baru')->assertSee('Nomor WhatsApp')->assertSee('Daftar Sekarang');
    }

    public function test_isian_kosong_ditolak(): void
    {
        Livewire::test(Daftar::class)->call('daftar')
            ->assertHasErrors(['nama' => 'required', 'email' => 'required', 'sandi' => 'required', 'setuju' => 'accepted']);
    }

    public function test_aturan_kata_sandi(): void
    {
        $this->isi(Livewire::test(Daftar::class), ['sandi' => 'pendek1', 'sandiKonfirmasi' => 'pendek1'])->call('daftar')->assertHasErrors('sandi');
        $this->isi(Livewire::test(Daftar::class), ['sandi' => 'hurufsajaaa', 'sandiKonfirmasi' => 'hurufsajaaa'])->call('daftar')->assertHasErrors('sandi');
        $this->isi(Livewire::test(Daftar::class), ['sandi' => '1234567890', 'sandiKonfirmasi' => '1234567890'])->call('daftar')->assertHasErrors('sandi');
        $this->isi(Livewire::test(Daftar::class), ['sandiKonfirmasi' => 'Beda12345'])->call('daftar')->assertHasErrors('sandiKonfirmasi');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_daftar_berhasil_membuat_akun_user_masuk_dan_kirim_otp(): void
    {
        Mail::fake();

        $this->isi(Livewire::test(Daftar::class), ['whatsapp' => '0812-3456-7890'])
            ->call('daftar')->assertHasNoErrors()->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'baru@contoh.com')->firstOrFail();
        $this->assertSame('user', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('6281234567890', $user->phone);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
        $this->assertAuthenticatedAs($user);
        Mail::assertSent(KodeOtpMail::class, fn ($m) => $m->hasTo('baru@contoh.com') && preg_match('/^\d{6}$/', $m->kode));
    }

    public function test_nomor_whatsapp_tidak_valid_ditolak(): void
    {
        $this->isi(Livewire::test(Daftar::class), ['whatsapp' => '12345'])->call('daftar')->assertHasErrors('whatsapp');
    }

    public function test_email_yang_sudah_terverifikasi_tidak_bisa_didaftarkan_lagi(): void
    {
        Mail::fake();
        User::factory()->create(['email' => 'baru@contoh.com']);

        $this->isi(Livewire::test(Daftar::class))->call('daftar')->assertHasErrors('email');
        Mail::assertNothingSent();
        $this->assertGuest();
    }

    public function test_akun_belum_terverifikasi_dengan_email_sama_diperbarui_bukan_diduplikasi(): void
    {
        Mail::fake();
        User::factory()->unverified()->create(['email' => 'baru@contoh.com', 'name' => 'Lama']);

        $this->isi(Livewire::test(Daftar::class))->call('daftar')->assertHasNoErrors();

        $this->assertDatabaseCount('users', 1);
        $user = User::first();
        $this->assertSame('Deni Saputra', $user->name);
        $this->assertTrue(Hash::check('Rahasia123', $user->password));
    }

    public function test_dibatasi_sepuluh_pendaftaran_per_jam_per_ip(): void
    {
        Mail::fake();

        for ($i = 1; $i <= 10; $i++) {
            $this->isi(Livewire::test(Daftar::class), ['email' => "u{$i}@contoh.com"])->call('daftar')->assertHasNoErrors();
        }

        $uji = $this->isi(Livewire::test(Daftar::class), ['email' => 'u11@contoh.com'])->call('daftar');
        $uji->assertHasErrors('form');
        $this->assertDatabaseMissing('users', ['email' => 'u11@contoh.com']);
    }
}
