<?php

namespace Tests\Feature;

use App\Livewire\Admin\Users;
use App\Livewire\Auth\Masuk;
use App\Mail\KodeOtpMail;
use App\Models\LogAksiAdmin;
use App\Models\Notifikasi;
use App\Models\Pesanan;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Models\User;
use App\Services\PengelolaPengguna;
use App\Services\SesiImpersonasi;
use App\Support\AksiAdminDitolak;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminUsersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        $this->admin = User::factory()->admin()->create(['name' => 'Bos Admin', 'email' => 'bos@contoh.com']);
    }

    private function halaman(?User $sebagai = null)
    {
        return Livewire::actingAs($sebagai ?? $this->admin)->test(Users::class);
    }

    // ---- akses ----

    public function test_tamu_diarahkan_ke_masuk(): void
    {
        $this->get('/admin/users')->assertRedirect(route('masuk'));
    }

    public function test_non_admin_ditolak_403(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin/users')->assertForbidden();
        Livewire::actingAs(User::factory()->create())->test(Users::class)->assertForbidden();
    }

    public function test_admin_melihat_halaman_asli_bukan_placeholder(): void
    {
        User::factory()->create(['name' => 'Siti Aminah']);

        $this->actingAs($this->admin)->get('/admin/users')->assertOk()->assertSeeLivewire(Users::class)->assertSee('Daftar pengguna')->assertSee('Siti Aminah');
    }

    public function test_aksi_livewire_menolak_non_admin_walau_komponen_sudah_termuat(): void
    {
        $korban = User::factory()->create();
        $komponen = $this->halaman();
        $biasa = User::factory()->create();

        // Sesi diganti pengguna biasa di tengah jalan: setiap aksi memeriksa ulang.
        $this->actingAs($biasa);
        $komponen->call('mintaAksi', 'hapus', $korban->id)->assertForbidden();
        $this->assertTrue(User::whereKey($korban->id)->exists());
    }

    // ---- daftar ----

    public function test_cari_filter_peran_status_dan_paket(): void
    {
        $this->seed();
        User::factory()->create(['name' => 'Budi Santoso', 'email' => 'budi@contoh.com']);
        User::factory()->create(['name' => 'Siti Aminah', 'email' => 'siti@contoh.com', 'paket' => 'premium', 'paket_sampai' => now()->addMonth()]);
        User::factory()->create(['name' => 'Lama Premium', 'email' => 'lama@contoh.com', 'paket' => 'premium', 'paket_sampai' => now()->subDay()]);
        User::factory()->unverified()->create(['name' => 'Belum Verif', 'email' => 'belum@contoh.com']);
        User::factory()->create(['name' => 'Diblokir', 'email' => 'blok@contoh.com', 'ditangguhkan_at' => now()]);

        $this->halaman()->set('q', 'budi')->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
        $this->halaman()->set('q', 'SITI@')->assertSee('Siti Aminah')->assertDontSee('Budi Santoso');
        $this->halaman()->set('peran', 'admin')->assertSee('Bos Admin')->assertDontSee('Budi Santoso');
        $this->halaman()->set('status', 'ditangguhkan')->assertSee('Diblokir')->assertDontSee('Budi Santoso');
        $this->halaman()->set('status', 'belum')->assertSee('Belum Verif')->assertDontSee('Diblokir');
        $this->halaman()->set('paket', 'premium')->assertSee('Siti Aminah')->assertDontSee('Lama Premium')->assertDontSee('Budi Santoso');
        // Premium yang masa aktifnya habis dihitung gratis (sama seperti User::paketAktif()).
        $this->halaman()->set('paket', 'gratis')->assertSee('Lama Premium')->assertSee('Budi Santoso')->assertDontSee('Siti Aminah');
    }

    public function test_pencarian_menganggap_karakter_like_sebagai_teks_biasa(): void
    {
        User::factory()->create(['name' => 'Budi Santoso']);

        $this->halaman()->set('q', '%')->assertDontSee('Budi Santoso');
        $this->halaman()->set('q', 'b_di')->assertDontSee('Budi Santoso');
    }

    public function test_cari_nomor_hp(): void
    {
        User::factory()->create(['name' => 'Punya HP', 'phone' => '6281234567890']);
        User::factory()->create(['name' => 'Tanpa HP']);

        $this->halaman()->set('q', '0812 3456 7890')->assertSee('Punya HP')->assertDontSee('Tanpa HP')
            ->set('q', '81234567')->assertSee('Punya HP')->assertDontSee('Tanpa HP');
    }

    public function test_paginasi_dan_urutan(): void
    {
        foreach (range(1, 20) as $i) {
            User::factory()->create(['name' => sprintf('Nama %02d', $i)]);
        }

        $h = $this->halaman()->set('urut', 'nama')->assertSee('Menampilkan 1–15 dari 21')->assertSee('Bos Admin')->assertDontSee('Nama 20');
        $h->call('berikutnya')->assertSee('Menampilkan 16–21 dari 21')->assertSee('Nama 20');
        $h->call('berikutnya')->assertSet('halaman', 2);
        $h->set('q', 'Nama 05')->assertSet('halaman', 1)->assertSee('1 pengguna');
    }

    public function test_status_urutan_atau_paket_tak_dikenal_tidak_merusak_halaman(): void
    {
        $this->halaman()->set('paket', "x' or 1=1 --")->set('status', 'ngawur')->set('urut', 'ngawur')->set('peran', 'root')->assertOk()->assertSee('Bos Admin');
    }

    public function test_daftar_tidak_menampilkan_sandi_hash_atau_token(): void
    {
        $u = User::factory()->create(['name' => 'Rahasia Orang']);
        $u->forceFill(['remember_token' => 'TOKENRAHASIA123'])->save();
        $hash = $u->fresh()->password;

        $h = $this->halaman()->call('bukaDetail', $u->id)->assertSee('Rahasia Orang')->assertDontSee($hash)->assertDontSee('TOKENRAHASIA123');
        $this->assertStringNotContainsString($hash, $h->html());
    }

    // ---- detail ----

    public function test_detail_menampilkan_paket_undangan_tamu_dan_pesanan(): void
    {
        $u = User::factory()->create(['name' => 'Detail Orang', 'paket' => 'premium', 'paket_sampai' => now()->addMonths(3), 'kota' => 'Bandung']);
        $undangan = Undangan::factory()->count(2)->create(['user_id' => $u->id]);
        Tamu::factory()->count(3)->create(['undangan_id' => $undangan[0]->id]);
        Pesanan::factory()->create(['user_id' => $u->id, 'nomor' => 'INV-2026-0042']);

        $this->halaman()->call('bukaDetail', $u->id)
            ->assertSee('Detail Orang')->assertSee('Bandung')->assertSee('Premium')->assertSee('INV-2026-0042')
            ->assertSeeInOrder(['Undangan', '2'])->assertSeeInOrder(['Tamu di semua undangan', '3']);
    }

    public function test_id_detail_terkunci_dari_manipulasi_klien(): void
    {
        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman()->set('detailId', 1);
    }

    public function test_detail_pengguna_tak_ada_404(): void
    {
        $this->halaman()->call('bukaDetail', 99999)->assertNotFound();
    }

    // ---- peran ----

    public function test_ubah_peran_pengguna_menjadi_admin_lalu_kembali(): void
    {
        $u = User::factory()->create();

        $this->halaman()->call('mintaAksi', 'peran', $u->id)->assertSet('peranBaru', 'admin')->call('jalankanAksi')->assertHasNoErrors();
        $this->assertTrue($u->fresh()->isAdmin());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'ubah_peran', 'admin_email' => 'bos@contoh.com', 'target_id' => $u->id]);

        $this->halaman()->call('mintaAksi', 'peran', $u->id)->assertSet('peranBaru', 'user')->call('jalankanAksi');
        $this->assertFalse($u->fresh()->isAdmin());
    }

    public function test_tidak_bisa_mengubah_peran_sendiri(): void
    {
        $this->halaman()->call('mintaAksi', 'peran', $this->admin->id)->set('peranBaru', 'user')->call('jalankanAksi')->assertHasErrors('aksi');
        $this->assertTrue($this->admin->fresh()->isAdmin());
    }

    public function test_peran_di_luar_daftar_ditolak(): void
    {
        $u = User::factory()->create();

        $this->halaman()->call('mintaAksi', 'peran', $u->id)->set('peranBaru', 'superadmin')->call('jalankanAksi')->assertHasErrors('peranBaru');
        $this->assertSame('user', $u->fresh()->role);
    }

    public function test_tidak_boleh_menyisakan_nol_admin(): void
    {
        $lain = User::factory()->admin()->create();
        // Pelaku basi: di basis data ia sudah bukan admin lagi, jadi menurunkan satu-satunya admin tersisa harus ditolak.
        User::whereKey($this->admin->id)->update(['role' => 'user']);

        $this->expectException(AksiAdminDitolak::class);
        $this->expectExceptionMessage('setidaknya satu admin aktif');
        app(PengelolaPengguna::class)->ubahPeran($this->admin, $lain, 'user');
    }

    public function test_admin_ditangguhkan_tidak_dihitung_sebagai_admin_aktif(): void
    {
        $target = User::factory()->admin()->create();
        User::factory()->admin()->create(['ditangguhkan_at' => now()]);
        User::whereKey($this->admin->id)->update(['role' => 'user']);

        $this->expectException(AksiAdminDitolak::class);
        app(PengelolaPengguna::class)->tangguhkan($this->admin, $target);
    }

    // ---- paket manual ----

    public function test_atur_paket_manual_dengan_masa_berlaku_tanpa_membuat_pesanan(): void
    {
        $u = User::factory()->create();
        $sampai = now()->addMonths(6)->format('Y-m-d');

        $this->halaman()->call('mintaAksi', 'paket', $u->id)->set('paketBaru', 'premium')->set('paketSampai', $sampai)->set('catatanPaket', 'Kompensasi gangguan')->call('jalankanAksi')->assertHasNoErrors();

        $u->refresh();
        $this->assertSame('premium', $u->paket);
        $this->assertSame($sampai, $u->paket_sampai->format('Y-m-d'));
        $this->assertSame('premium', $u->paketAktif());
        $this->assertSame(0, Pesanan::count());
        $log = LogAksiAdmin::where('aksi', 'atur_paket')->firstOrFail();
        $this->assertSame('bos@contoh.com', $log->admin_email);
        $this->assertSame('Kompensasi gangguan', $log->rincian['catatan']);
        $this->assertNotNull($log->created_at);
        $this->assertSame(1, Notifikasi::where('user_id', $u->id)->where('judul', 'Paket akunmu diperbarui')->count());
    }

    public function test_paket_tanpa_batas_dan_kembali_ke_gratis(): void
    {
        $u = User::factory()->create();

        $this->halaman()->call('mintaAksi', 'paket', $u->id)->set('paketBaru', 'premium')->set('tanpaBatas', true)->call('jalankanAksi')->assertHasNoErrors();
        $this->assertNull($u->fresh()->paket_sampai);
        $this->assertSame('premium', $u->fresh()->paketAktif());

        $this->halaman()->call('mintaAksi', 'paket', $u->id)->set('paketBaru', 'gratis')->call('jalankanAksi')->assertHasNoErrors();
        $this->assertSame('gratis', $u->fresh()->paket);
        $this->assertNull($u->fresh()->paket_sampai);
    }

    public function test_paket_manual_gagal_untuk_kode_tak_dikenal_tanggal_lalu_atau_tanpa_tanggal(): void
    {
        $u = User::factory()->create();

        $h = $this->halaman()->call('mintaAksi', 'paket', $u->id);
        $h->set('paketBaru', 'platinum')->set('paketSampai', now()->addDay()->format('Y-m-d'))->call('jalankanAksi')->assertHasErrors('aksi');
        $h->set('paketBaru', 'premium')->set('paketSampai', now()->subDay()->format('Y-m-d'))->call('jalankanAksi')->assertHasErrors('aksi');
        $h->set('paketBaru', 'premium')->set('paketSampai', now()->addYears(11)->format('Y-m-d'))->call('jalankanAksi')->assertHasErrors('aksi');
        $h->set('paketBaru', 'premium')->set('paketSampai', '')->call('jalankanAksi')->assertHasErrors('paketSampai');
        $h->set('paketBaru', 'premium')->set('paketSampai', 'bukan-tanggal')->call('jalankanAksi')->assertHasErrors('paketSampai');

        $this->assertSame('gratis', $u->fresh()->paket);
        $this->assertSame(0, LogAksiAdmin::where('aksi', 'atur_paket')->count());
    }

    // ---- verifikasi ----

    public function test_tandai_terverifikasi_dan_kirim_ulang_verifikasi(): void
    {
        Mail::fake();
        $u = User::factory()->unverified()->create(['email' => 'baru@contoh.com']);

        $this->halaman()->call('kirimUlangVerifikasi', $u->id);
        Mail::assertSent(KodeOtpMail::class, fn (KodeOtpMail $m) => $m->hasTo('baru@contoh.com'));
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'kirim_ulang_verifikasi', 'target_id' => $u->id]);

        $this->halaman()->call('verifikasi', $u->id);
        $this->assertTrue($u->fresh()->hasVerifiedEmail());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'verifikasi_manual', 'target_id' => $u->id]);

        Mail::fake();
        $this->halaman()->call('kirimUlangVerifikasi', $u->id)->assertDispatched('toast');
        Mail::assertNothingSent();
    }

    public function test_kirim_ulang_verifikasi_menghormati_jeda_otp(): void
    {
        Mail::fake();
        $u = User::factory()->unverified()->create();

        $this->halaman()->call('kirimUlangVerifikasi', $u->id)->call('kirimUlangVerifikasi', $u->id);
        Mail::assertSent(KodeOtpMail::class, 1);
    }

    // ---- tangguhkan ----

    public function test_tangguhkan_memutus_sesi_dan_memblokir_masuk_lalu_aktifkan(): void
    {
        $u = User::factory()->create(['email' => 'korban@contoh.com']);

        $this->halaman()->call('mintaAksi', 'tangguh', $u->id)->set('alasanTangguh', 'Penipuan')->call('jalankanAksi')->assertHasNoErrors();
        $this->assertNotNull($u->fresh()->ditangguhkan_at);
        $this->assertSame('Penipuan', $u->fresh()->ditangguhkan_alasan);

        // Sandi benar pun tidak membuka sesi.
        Livewire::test(Masuk::class)->set('email', 'korban@contoh.com')->set('sandi', 'password')->call('masuk')->assertHasErrors('form');
        $this->assertGuest();

        $this->halaman()->call('aktifkan', $u->id);
        $this->assertNull($u->fresh()->ditangguhkan_at);
        Livewire::test(Masuk::class)->set('email', 'korban@contoh.com')->set('sandi', 'password')->call('masuk')->assertHasNoErrors();
    }

    public function test_sesi_yang_sedang_berjalan_dikeluarkan_saat_ditangguhkan(): void
    {
        $u = User::factory()->create();
        $this->actingAs($u)->get('/beranda')->assertOk();

        User::whereKey($u->id)->update(['ditangguhkan_at' => now()]);

        $this->actingAs($u->fresh())->get('/beranda')->assertRedirect(route('masuk'));
        $this->assertGuest();
    }

    public function test_undangan_milik_akun_ditangguhkan_tidak_tayang(): void
    {
        $u = User::factory()->create();
        $undangan = Undangan::factory()->create(['user_id' => $u->id, 'slug' => 'pasangan-baru', 'status' => 'terbit', 'diterbitkan_at' => now(), 'terbitan' => ['isi' => ['x' => 'y'], 'judul' => 'T', 'slug' => 'pasangan-baru']]);
        $this->assertNotNull($undangan->terbitan);
        User::whereKey($u->id)->update(['ditangguhkan_at' => now()]);

        $this->get('/pasangan-baru')->assertNotFound();
    }

    public function test_tidak_bisa_menangguhkan_diri_sendiri_dan_admin_tanpa_alasan_valid(): void
    {
        $this->halaman()->call('mintaAksi', 'tangguh', $this->admin->id)->call('jalankanAksi')->assertHasErrors('aksi');
        $this->assertNull($this->admin->fresh()->ditangguhkan_at);

        $u = User::factory()->create();
        $this->halaman()->call('mintaAksi', 'tangguh', $u->id)->set('alasanTangguh', str_repeat('a', 300))->call('jalankanAksi')->assertHasErrors('alasanTangguh');
    }

    public function test_aktifkan_akun_yang_tidak_ditangguhkan_ditolak(): void
    {
        $u = User::factory()->create();

        $this->halaman()->call('aktifkan', $u->id)->assertDispatched('toast', tipe: 'galat', pesan: 'Akun ini tidak sedang ditangguhkan.');
    }

    // ---- hapus ----

    public function test_hapus_akun_dengan_konfirmasi_email_menghapus_datanya(): void
    {
        $u = User::factory()->create(['email' => 'hapus@contoh.com']);
        $undangan = Undangan::factory()->create(['user_id' => $u->id]);
        Notifikasi::factory()->create(['user_id' => $u->id]);

        $h = $this->halaman()->call('bukaDetail', $u->id)->call('mintaAksi', 'hapus', $u->id);
        $h->set('emailKonfirmasi', 'salah@contoh.com')->call('jalankanAksi')->assertHasErrors('aksi');
        $this->assertTrue(User::whereKey($u->id)->exists());

        $h->set('emailKonfirmasi', ' HAPUS@contoh.com ')->call('jalankanAksi')->assertHasNoErrors();
        $this->assertFalse(User::whereKey($u->id)->exists());
        $this->assertFalse(Undangan::whereKey($undangan->id)->exists());
        $this->assertSame(0, Notifikasi::where('user_id', $u->id)->count());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'hapus_akun', 'target_email' => 'hapus@contoh.com']);
    }

    public function test_tidak_bisa_menghapus_diri_sendiri_atau_admin(): void
    {
        $this->halaman()->call('mintaAksi', 'hapus', $this->admin->id)->set('emailKonfirmasi', 'bos@contoh.com')->call('jalankanAksi')->assertHasErrors('aksi');
        $this->assertTrue(User::whereKey($this->admin->id)->exists());

        $lain = User::factory()->admin()->create(['email' => 'lain@contoh.com']);
        $this->halaman()->call('mintaAksi', 'hapus', $lain->id)->set('emailKonfirmasi', 'lain@contoh.com')->call('jalankanAksi')->assertHasErrors('aksi');
        $this->assertTrue(User::whereKey($lain->id)->exists());
    }

    public function test_jenis_aksi_dan_id_aksi_terkunci(): void
    {
        $u = User::factory()->create();
        $h = $this->halaman()->call('mintaAksi', 'peran', $u->id);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $h->set('aksiId', $this->admin->id);
    }

    public function test_jenis_aksi_tak_dikenal_404(): void
    {
        $u = User::factory()->create();

        $this->halaman()->call('mintaAksi', 'jadi-presiden', $u->id)->assertNotFound();
    }

    // ---- masuk sebagai ----

    public function test_masuk_sebagai_pengguna_mencatat_log_membatasi_dan_bisa_kembali(): void
    {
        $u = User::factory()->create(['name' => 'Target Orang', 'email' => 'target@contoh.com']);

        $this->halaman()->call('mintaAksi', 'masuk', $u->id)->call('jalankanAksi')->assertRedirect(route('beranda'));
        $this->assertAuthenticatedAs($u);
        $this->assertTrue(app(SesiImpersonasi::class)->aktif());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'impersonasi_mulai', 'admin_email' => 'bos@contoh.com', 'target_id' => $u->id]);

        $this->get('/beranda')->assertOk()->assertSee('Masuk sebagai Target Orang')->assertSee('Kembali ke Admin');
        $this->get('/admin/users')->assertForbidden();

        $this->post('/akhiri-impersonasi')->assertRedirect(route('admin.users'));
        $this->assertAuthenticatedAs($this->admin);
        $this->assertFalse(app(SesiImpersonasi::class)->aktif());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'impersonasi_selesai', 'target_id' => $u->id]);
        $this->get('/beranda')->assertOk()->assertDontSee('Kembali ke Admin');
    }

    public function test_masuk_sebagai_ditolak_untuk_admin_diri_sendiri_ditangguhkan_dan_belum_verifikasi(): void
    {
        $lain = User::factory()->admin()->create();
        $blok = User::factory()->create(['ditangguhkan_at' => now()]);
        $belum = User::factory()->unverified()->create();

        foreach ([$lain, $this->admin, $blok, $belum] as $t) {
            $this->halaman()->call('mintaAksi', 'masuk', $t->id)->call('jalankanAksi')->assertHasErrors('aksi');
        }
        $this->assertSame(0, LogAksiAdmin::where('aksi', 'impersonasi_mulai')->count());
    }

    public function test_sesi_masuk_sebagai_habis_setelah_batas_waktu(): void
    {
        $u = User::factory()->create();
        $this->actingAs($this->admin);
        app(SesiImpersonasi::class)->mulai($this->admin, $u);
        $this->assertAuthenticatedAs($u);

        $this->travel(SesiImpersonasi::MENIT_MAKS + 1)->minutes();

        $this->get('/beranda')->assertRedirect(route('admin.users'));
        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'impersonasi_selesai', 'target_id' => $u->id]);
    }

    public function test_selama_masuk_sebagai_aksi_akun_dan_pembelian_diblokir(): void
    {
        $u = User::factory()->create();
        $this->actingAs($this->admin);
        app(SesiImpersonasi::class)->mulai($this->admin, $u);

        $kirim = fn (string $nama) => $this->postJson(route('default-livewire.update'), ['components' => [['snapshot' => json_encode(['memo' => ['name' => $nama]]), 'updates' => [], 'calls' => []]]]);
        foreach (['akun.ubah-sandi', 'akun.informasi-akun', 'akun.edit-profil', 'checkout', 'konfirmasi-pembayaran', 'paket-billing'] as $nama) {
            $kirim($nama)->assertForbidden();
        }
        // Komponen lain tidak diblokir oleh penjaga ini (boleh gagal di tempat lain karena snapshot palsu).
        $this->assertNotSame(403, $kirim('beranda')->status());
        // Permintaan tulis di luar Livewire (mis. RSVP publik) ditolak selama sesi.
        $this->post('/apa-saja/rsvp')->assertForbidden();
    }

    public function test_pengguna_biasa_tidak_bisa_memulai_masuk_sebagai(): void
    {
        $biasa = User::factory()->create();
        $target = User::factory()->create();

        $this->expectException(HttpException::class);
        app(SesiImpersonasi::class)->mulai($biasa, $target);
    }

    // ---- ekspor ----

    public function test_ekspor_csv_aman_rumus_dan_mengikuti_filter(): void
    {
        User::factory()->create(['name' => '=HYPERLINK("http://jahat.test","klik")', 'email' => 'rumus@contoh.com', 'phone' => '+628123456789']);
        User::factory()->create(['name' => 'Tidak Ikut', 'email' => 'lain@contoh.com']);

        $h = $this->halaman()->set('q', 'rumus')->call('ekspor');
        $h->assertFileDownloaded();
        $csv = base64_decode($h->effects['download']['content']);

        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString("\n=HYPERLINK", $csv);
        $this->assertStringContainsString('rumus@contoh.com', $csv);
        $this->assertStringNotContainsString('Tidak Ikut', $csv);
        $this->assertStringNotContainsString('password', strtolower($csv));
        $this->assertStringNotContainsString('remember', strtolower($csv));
    }

    public function test_ekspor_dibatasi_laju(): void
    {
        $kunci = 'admin-users-ekspor:'.$this->admin->id;
        foreach (range(1, 5) as $_) {
            RateLimiter::hit($kunci, 60);
        }

        $this->halaman()->call('ekspor')->assertDispatched('toast', tipe: 'galat')->assertNoFileDownloaded();
    }
}
