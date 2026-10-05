<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\User;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FondasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_tidak_ditemukan_berisi_pencarian_dan_tautan(): void
    {
        $this->get('/halaman-ngawur-123')->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')->assertSee('Cari di Pusat Bantuan')->assertSee('Laporkan masalah ini');
    }

    public function test_halaman_offline_tersedia_untuk_tamu(): void
    {
        $this->get('/offline')->assertOk()->assertSee('Kamu sedang offline')->assertSee('Coba lagi');
    }

    public function test_pengaturan_menyimpan_dan_membaca_nilai_bersarang(): void
    {
        $this->assertNull(Pengaturan::ambil('x.y'));
        $this->assertSame('bawaan', Pengaturan::ambil('x.y', 'bawaan'));

        Pengaturan::simpan('x', ['y' => 5, 'z' => ['a' => 1]]);

        $this->assertSame(5, Pengaturan::ambil('x.y'));
        $this->assertSame(1, Pengaturan::ambil('x.z.a'));
        $this->assertSame(['y' => 5, 'z' => ['a' => 1]], Pengaturan::ambil('x'));
    }

    public function test_mode_pemeliharaan_menahan_pengguna_tapi_tidak_admin(): void
    {
        $this->artisan('undangyah:pemeliharaan', ['status' => 'on', '--pesan' => 'Sedang upgrade', '--eta' => '30 menit'])->assertSuccessful();

        $this->actingAs(User::factory()->create())->get('/beranda')
            ->assertStatus(503)->assertSee('Sedang dalam pemeliharaan')->assertSee('Sedang upgrade')->assertSee('30 menit');
        Auth::logout();
        $this->get('/masuk')->assertOk(); // halaman masuk tetap terbuka agar admin bisa login
        $this->actingAs(User::factory()->admin()->create())->get('/beranda')->assertOk();

        $this->artisan('undangyah:pemeliharaan', ['status' => 'off'])->assertSuccessful();
        $this->actingAs(User::factory()->create())->get('/beranda')->assertOk();
    }

    public function test_seeder_admin_membuat_akun_dan_menulis_sandi_ke_berkas_600_sekali_saja(): void
    {
        $berkas = sys_get_temp_dir().'/admin-login-'.uniqid().'.txt';
        config(['undangyah.admin_login_file' => $berkas]);

        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'phentem@gmail.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());
        $isi = file_get_contents($berkas);
        $this->assertSame('0600', substr(sprintf('%o', fileperms($berkas)), -4));
        preg_match('/Kata sandi: (\S+)/', $isi, $m);
        $this->assertTrue(Hash::check($m[1], $admin->password));

        // Idempoten: menjalankan lagi tidak memutar kata sandi dan tidak menimpa berkas.
        $sebelum = $admin->password;
        $this->seed(AdminSeeder::class);
        $this->assertSame($sebelum, $admin->fresh()->password);
        $this->assertSame($isi, file_get_contents($berkas));
        unlink($berkas);
    }

    public function test_perintah_qa_hanya_menerima_email_uji(): void
    {
        $this->artisan('undangyah:qa-user', ['email' => 'qa-abc@example.test'])->assertSuccessful();
        $this->artisan('undangyah:qa-user', ['email' => 'orang@gmail.com'])->assertFailed();
        $this->assertDatabaseHas('users', ['email' => 'qa-abc@example.test']);

        $this->artisan('undangyah:qa-bersih')->assertSuccessful();
        $this->assertDatabaseMissing('users', ['email' => 'qa-abc@example.test']);
    }

    public function test_placeholder_menu_hilang_otomatis_bila_rute_didaftarkan(): void
    {
        // Semua rute menu sidebar terdaftar (placeholder atau halaman sungguhan).
        foreach (['beranda', 'edit-undangan', 'rsvp', 'bagikan', 'template', 'buku-tamu', 'hadiah', 'statistik', 'billing', 'akun', 'bantuan', 'hubungi', 'notifikasi', 'buat-undangan', 'admin.theme', 'admin.backup'] as $nama) {
            $this->assertTrue(Route::has($nama), "rute {$nama} belum ada");
        }
    }
}
