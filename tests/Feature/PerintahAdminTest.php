<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PerintahAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_membuat_admin_baru_terverifikasi(): void
    {
        $this->artisan('undangyah:admin', ['email' => 'Admin@Contoh.com', '--nama' => 'Bos', '--sandi' => 'SandiAdmin123'])->assertSuccessful();

        $admin = User::where('email', 'admin@contoh.com')->firstOrFail();
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue(Hash::check('SandiAdmin123', $admin->password));
    }

    public function test_menaikkan_akun_yang_ada_menjadi_admin(): void
    {
        $user = User::factory()->unverified()->create(['email' => 'ada@contoh.com']);

        $this->artisan('undangyah:admin', ['email' => 'ada@contoh.com'])->assertSuccessful();

        $this->assertTrue($user->fresh()->isAdmin());
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_email_tidak_valid_gagal(): void
    {
        $this->artisan('undangyah:admin', ['email' => 'bukan-email'])->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
