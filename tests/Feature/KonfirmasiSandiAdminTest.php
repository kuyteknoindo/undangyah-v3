<?php

namespace Tests\Feature;

use App\Livewire\Admin\AddOns;
use App\Livewire\Admin\BackupRestore;
use App\Livewire\Admin\DataPesanan;
use App\Livewire\Admin\Pembayaran;
use App\Livewire\Admin\PustakaMedia;
use App\Livewire\Admin\Theme;
use App\Livewire\Admin\Users;
use App\Livewire\Admin\Vouchers;
use App\Models\AddOn;
use App\Models\Backup;
use App\Models\ItemPesanan;
use App\Models\LogAksiAdmin;
use App\Models\Media;
use App\Models\MetodePembayaran;
use App\Models\Pesanan;
use App\Models\Tema;
use App\Models\User;
use App\Models\Voucher;
use App\Services\KonfirmasiSandiAdmin;
use App\Services\PembuatBackup;
use App\Services\SesiImpersonasi;
use App\Support\KonfirmasiSandiGagal;
use Database\Seeders\PaketSeeder;
use Illuminate\Auth\Events\Logout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Concerns\SiapkanBackupUji;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

/** Konfirmasi kata sandi untuk aksi admin sensitif: penegakan di server, masa berlaku 5 menit, pembatasan percobaan, audit. */
class KonfirmasiSandiAdminTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;
    use SiapkanBackupUji;

    private const SANDI = 'RahasiaUnik!987';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanBackup();
        $this->seed(PaketSeeder::class);
        $this->admin = User::factory()->admin()->create(['email' => 'bos@contoh.com', 'password' => Hash::make(self::SANDI)]);
        RateLimiter::clear('konfirmasi-sandi:'.$this->admin->id.'|127.0.0.1');
    }

    protected function tearDown(): void
    {
        $this->bersihkanBackup();
        parent::tearDown();
    }

    private function users()
    {
        return Livewire::actingAs($this->admin)->test(Users::class);
    }

    /** Buka dialog "Hapus pengguna" untuk korban, siap dijalankan. */
    private function siapHapus(User $korban)
    {
        return $this->users()->call('mintaAksi', 'hapus', $korban->id)->set('emailKonfirmasi', $korban->email);
    }

    private function isiRekening($k)
    {
        return $k->set('f.label', 'Transfer BCA')->set('f.tipe', 'manual')->set('f.bank', 'BCA')->set('f.nomor', '1234567890')->set('f.atas', 'PT Undangan')->set('f.aktif', false);
    }

    // ------------------------------------------------------------ penegakan di server

    public function test_aksi_dipanggil_langsung_tanpa_konfirmasi_ditolak_dan_dialog_dibuka(): void
    {
        $korban = User::factory()->create();

        $this->siapHapus($korban)->call('jalankanAksi')
            ->assertSet('tampilKonfirmasiSandi', true)->assertSet('tertundaMetode', 'jalankanAksi')->assertSet('tertundaLabel', 'Hapus pengguna')
            ->assertSee('Konfirmasi kata sandi');

        $this->assertTrue(User::whereKey($korban->id)->exists());
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'hapus_akun']);
    }

    public function test_properti_tertunda_terkunci_dari_manipulasi_klien(): void
    {
        $korban = User::factory()->create();
        $k = $this->siapHapus($korban)->call('jalankanAksi');

        foreach (['tertundaMetode' => 'ekspor', 'tertundaArg' => ['x' => 1], 'tertundaLabel' => 'lain', 'tertundaPesan' => 'lain'] as $prop => $nilai) {
            try {
                $k->set($prop, $nilai);
                $this->fail("Properti $prop seharusnya terkunci.");
            } catch (CannotUpdateLockedPropertyException) {
                $this->addToAssertionCount(1);
            }
        }
        // Tanpa aksi tertunda, tombol Konfirmasi tidak menjalankan apa pun.
        $this->users()->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi');
        $this->assertTrue(User::whereKey($korban->id)->exists());
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'konfirmasi_sandi']);
    }

    public function test_sandi_benar_menjalankan_aksi_tertunda_dan_mengosongkan_properti(): void
    {
        $korban = User::factory()->create(['email' => 'korban@contoh.com']);

        $this->siapHapus($korban)->call('jalankanAksi')
            ->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')
            ->assertHasNoErrors()->assertSet('sandiAdmin', '')->assertSet('tampilKonfirmasiSandi', false)->assertSet('tertundaMetode', '');

        $this->assertFalse(User::whereKey($korban->id)->exists());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'konfirmasi_sandi', 'admin_id' => $this->admin->id]);
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'hapus_akun', 'target_email' => 'korban@contoh.com', 'admin_email' => 'bos@contoh.com']);
    }

    public function test_batal_melupakan_aksi_tertunda(): void
    {
        $korban = User::factory()->create();
        $this->siapHapus($korban)->call('jalankanAksi')->call('batalKonfirmasiSandi')
            ->assertSet('tampilKonfirmasiSandi', false)->assertSet('tertundaMetode', '')->assertSet('sandiAdmin', '');
        $this->assertTrue(User::whereKey($korban->id)->exists());
    }

    // ------------------------------------------------------------ sandi salah dan pembatasan

    public function test_sandi_salah_ditolak_dengan_sisa_percobaan_dan_dicatat_tanpa_sandi(): void
    {
        $korban = User::factory()->create();
        $k = $this->siapHapus($korban)->call('jalankanAksi');

        $k->set('sandiAdmin', 'SalahUnik!654')->call('konfirmasiSandi')
            ->assertHasErrors('sandiAdmin')->assertSet('sandiAdmin', '')->assertSet('tampilKonfirmasiSandi', true)
            ->assertSee('Kata sandi salah. Sisa 4 percobaan.');
        $k->set('sandiAdmin', 'SalahUnik!654')->call('konfirmasiSandi')->assertSee('Sisa 3 percobaan.');

        $this->assertTrue(User::whereKey($korban->id)->exists());
        $this->assertSame(2, LogAksiAdmin::where('aksi', 'konfirmasi_sandi_gagal')->count());
        $this->assertStringNotContainsString('SalahUnik', LogAksiAdmin::all()->toJson());
    }

    public function test_sandi_kosong_ditolak_tanpa_menghitung_percobaan(): void
    {
        $korban = User::factory()->create();
        $this->siapHapus($korban)->call('jalankanAksi')->set('sandiAdmin', '')->call('konfirmasiSandi')->assertSee('Masukkan kata sandi akunmu.');
        $this->assertSame(0, RateLimiter::attempts('konfirmasi-sandi:'.$this->admin->id.'|127.0.0.1'));
    }

    public function test_lima_salah_mengunci_sementara_walau_sandi_benar_lalu_terbuka_lagi(): void
    {
        $korban = User::factory()->create();
        $k = $this->siapHapus($korban)->call('jalankanAksi');

        foreach (range(1, 5) as $_) {
            $k->set('sandiAdmin', 'SalahUnik!654')->call('konfirmasiSandi');
        }
        $k->assertSee('dikunci sementara');

        // Sandi benar pun ditolak selama terkunci; aksi tidak jalan, kunci dicatat sekali.
        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasErrors('sandiAdmin')->assertSee('coba lagi dalam');
        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi');
        $this->assertTrue(User::whereKey($korban->id)->exists());
        $this->assertSame(5, LogAksiAdmin::where('aksi', 'konfirmasi_sandi_gagal')->count());
        $this->assertSame(1, LogAksiAdmin::where('aksi', 'konfirmasi_sandi_terkunci')->count());
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'konfirmasi_sandi']);

        $this->travel(16)->minutes();
        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertFalse(User::whereKey($korban->id)->exists());
    }

    public function test_batas_percobaan_terpisah_per_admin(): void
    {
        $lain = User::factory()->admin()->create(['password' => Hash::make('SandiLain!321')]);
        foreach (range(1, 5) as $_) {
            RateLimiter::hit('konfirmasi-sandi:'.$this->admin->id.'|127.0.0.1', 900);
        }

        $korban = User::factory()->create();
        Livewire::actingAs($lain)->test(Users::class)->call('mintaAksi', 'hapus', $korban->id)->set('emailKonfirmasi', $korban->email)
            ->call('jalankanAksi')->set('sandiAdmin', 'SandiLain!321')->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertFalse(User::whereKey($korban->id)->exists());
    }

    // ------------------------------------------------------------ masa berlaku

    public function test_konfirmasi_berlaku_lima_menit_lalu_kedaluwarsa_dan_diminta_lagi(): void
    {
        $korban = User::factory()->create(['email' => 'a@contoh.com']);
        $korban2 = User::factory()->create(['email' => 'b@contoh.com']);
        $layanan = app(KonfirmasiSandiAdmin::class);

        $this->siapHapus($korban)->call('jalankanAksi')->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi');
        $this->assertFalse(User::whereKey($korban->id)->exists());
        $this->assertTrue($layanan->berlaku($this->admin));

        // Dalam masa berlaku, aksi sensitif berikutnya langsung jalan (satu stempel untuk semua aksi).
        $this->travel(4)->minutes();
        $this->siapHapus($korban2)->call('jalankanAksi')->assertSet('tampilKonfirmasiSandi', false);
        $this->assertFalse(User::whereKey($korban2->id)->exists());

        $korban3 = User::factory()->create(['email' => 'c@contoh.com']);
        $this->travel(2)->minutes();
        $k = $this->siapHapus($korban3)->call('jalankanAksi')->assertSet('tampilKonfirmasiSandi', true)->assertSee('sudah kedaluwarsa');
        $this->assertTrue(User::whereKey($korban3->id)->exists());
        $this->assertFalse($layanan->berlaku($this->admin));

        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertFalse(User::whereKey($korban3->id)->exists());
    }

    public function test_masa_berlaku_tepat_lima_menit(): void
    {
        $layanan = app(KonfirmasiSandiAdmin::class);
        $this->sudahKonfirmasiSandi($this->admin);
        $this->travel(299)->seconds();
        $this->assertTrue($layanan->berlaku($this->admin));
        $this->travel(2)->seconds();
        $this->assertSame('kedaluwarsa', $layanan->status($this->admin));
    }

    public function test_stempel_tidak_berlaku_untuk_admin_lain_atau_setelah_sandi_diganti(): void
    {
        $layanan = app(KonfirmasiSandiAdmin::class);
        $lain = User::factory()->admin()->create();
        $this->sudahKonfirmasiSandi($lain);
        $this->assertFalse($layanan->berlaku($this->admin));

        $this->sudahKonfirmasiSandi($this->admin);
        $this->assertTrue($layanan->berlaku($this->admin));
        $this->admin->forceFill(['password' => Hash::make('SandiBaru!111')])->save();
        $this->assertFalse($layanan->berlaku($this->admin->fresh()));
    }

    public function test_stempel_dicabut_saat_keluar(): void
    {
        $layanan = app(KonfirmasiSandiAdmin::class);
        $this->sudahKonfirmasiSandi($this->admin);
        $this->assertTrue($layanan->berlaku($this->admin));

        event(new Logout('web', $this->admin));
        $this->assertFalse($layanan->berlaku($this->admin));
        $this->assertNull(session(KonfirmasiSandiAdmin::KUNCI));

        $this->sudahKonfirmasiSandi($this->admin);
        $this->actingAs($this->admin)->withSession($this->sesiKonfirmasi($this->admin))->post(route('keluar'))->assertRedirect(route('masuk'));
        $this->assertNull(session(KonfirmasiSandiAdmin::KUNCI));
    }

    public function test_sesi_masuk_sebagai_tidak_bisa_mengonfirmasi(): void
    {
        $layanan = app(KonfirmasiSandiAdmin::class);
        $this->assertTrue($layanan->bisaKonfirmasi($this->admin));
        session(['impersonasi' => ['admin_id' => $this->admin->id, 'mulai' => now()->timestamp, 'sampai' => now()->addMinutes(30)->timestamp]]);
        $this->assertFalse($layanan->bisaKonfirmasi($this->admin));
        $this->expectException(KonfirmasiSandiGagal::class);
        $layanan->konfirmasi($this->admin, self::SANDI, 'uji');
    }

    public function test_non_admin_tidak_bisa_mengonfirmasi_dan_halaman_403(): void
    {
        $biasa = User::factory()->create();
        $this->assertFalse(app(KonfirmasiSandiAdmin::class)->bisaKonfirmasi($biasa));
        Livewire::actingAs($biasa)->test(Users::class)->assertForbidden();
        Livewire::actingAs($biasa)->test(Pembayaran::class)->assertForbidden();
        Livewire::actingAs($biasa)->test(BackupRestore::class)->assertForbidden();
    }

    // ------------------------------------------------------------ Users

    public function test_semua_jenis_aksi_pengguna_sensitif_diminta_sandi(): void
    {
        $t = User::factory()->create(['email' => 'target@contoh.com']);

        $this->users()->call('mintaAksi', 'peran', $t->id)->set('peranBaru', 'admin')->call('jalankanAksi')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertSame('user', $t->fresh()->role);

        $this->users()->call('mintaAksi', 'tangguh', $t->id)->call('jalankanAksi')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertNull($t->fresh()->ditangguhkan_at);

        $this->users()->call('mintaAksi', 'masuk', $t->id)->call('jalankanAksi')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertFalse(app(SesiImpersonasi::class)->aktif());

        $this->users()->call('mintaAksi', 'paket', $t->id)->set('paketBaru', 'premium')->set('tanpaBatas', true)->call('jalankanAksi')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertNotSame('premium', $t->fresh()->paket);
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'ubah_peran']);
    }

    public function test_ubah_peran_menjadi_admin_setelah_konfirmasi_dicatat_dengan_ip_dan_agen(): void
    {
        $t = User::factory()->create();
        $this->withHeaders(['User-Agent' => 'UjiBrowser/1.0'])->actingAs($this->admin);
        $this->users()->call('mintaAksi', 'peran', $t->id)->set('peranBaru', 'admin')->call('jalankanAksi')
            ->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();

        $this->assertSame('admin', $t->fresh()->role);
        $log = LogAksiAdmin::where('aksi', 'ubah_peran')->firstOrFail();
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame('bos@contoh.com', $log->admin_email);
        $this->assertSame('127.0.0.1', $log->ip);
        $this->assertNotNull($log->created_at);
    }

    public function test_ekspor_pengguna_butuh_sandi_dan_dicatat(): void
    {
        User::factory()->create();
        $this->users()->call('ekspor')->assertSet('tampilKonfirmasiSandi', true)->assertNoRedirect()->assertNotDispatched('x');
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'ekspor_pengguna']);

        $this->users()->call('ekspor')->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertFileDownloaded();
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'ekspor_pengguna']);
    }

    // ------------------------------------------------------------ Metode Pembayaran

    public function test_ubah_rekening_butuh_sandi_dan_log_menyamarkan_nomor(): void
    {
        $m = MetodePembayaran::create(['kode' => 'bca', 'label' => 'Transfer BCA', 'tipe' => 'manual', 'urutan' => 9, 'aktif' => false, 'biaya_tetap' => 0, 'biaya_persen' => 0, 'kode_unik' => false, 'data' => ['bank' => 'BCA', 'nomor' => '1111222233', 'atas' => 'PT Lama', 'placeholder' => false]]);
        $k = Livewire::actingAs($this->admin)->test(Pembayaran::class)->call('bukaEdit', $m->id)->set('f.nomor', '9876543210')->call('simpan');

        $k->assertSet('tampilKonfirmasiSandi', true);
        $this->assertSame('1111222233', $m->fresh()->data['nomor']);

        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertSame('9876543210', $m->fresh()->data['nomor']);

        $log = LogAksiAdmin::where('aksi', 'ubah_rekening')->firstOrFail();
        $this->assertSame('******3210', $log->rincian['sesudah']['nomor']);
        $this->assertSame('******2233', $log->rincian['sebelum']['nomor']);
        $semua = LogAksiAdmin::all()->toJson();
        $this->assertStringNotContainsString('9876543210', $semua);
        $this->assertStringNotContainsString('1111222233', $semua);
        $this->assertStringNotContainsString(self::SANDI, $semua);
    }

    public function test_tambah_metode_butuh_sandi_tetapi_toggle_aktif_dan_biaya_tidak(): void
    {
        $k = $this->isiRekening(Livewire::actingAs($this->admin)->test(Pembayaran::class)->call('bukaBaru'))->call('simpan');
        $k->assertSet('tampilKonfirmasiSandi', true);
        $this->assertDatabaseMissing('metode_pembayaran', ['label' => 'Transfer BCA']);
        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi');
        $m = MetodePembayaran::where('label', 'Transfer BCA')->firstOrFail();
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'metode_bayar_buat']);

        // Toggle aktif dan ubah biaya (tanpa menyentuh rekening) tidak butuh sandi baru, walau stempel sudah habis.
        $this->travel(10)->minutes();
        $l = Livewire::actingAs($this->admin)->test(Pembayaran::class);
        $l->call('ubahAktif', $m->id)->assertSet('tampilKonfirmasiSandi', false);
        $this->assertTrue((bool) $m->fresh()->aktif);
        $l->call('bukaEdit', $m->id)->set('f.biaya_tetap', '1.500')->call('simpan')->assertSet('tampilKonfirmasiSandi', false)->assertHasNoErrors();
        $this->assertSame(1500, $m->fresh()->biaya_tetap);
    }

    public function test_hapus_metode_pembayaran_butuh_sandi_dan_tercatat(): void
    {
        $m = MetodePembayaran::create(['kode' => 'bca', 'label' => 'Transfer BCA', 'tipe' => 'manual', 'urutan' => 9, 'aktif' => false, 'biaya_tetap' => 0, 'biaya_persen' => 0, 'kode_unik' => false, 'data' => ['bank' => 'BCA', 'nomor' => '1111222233', 'atas' => 'PT Lama', 'placeholder' => false]]);
        $k = Livewire::actingAs($this->admin)->test(Pembayaran::class)->call('tanyaHapus', $m->id)->call('hapus');

        $k->assertSet('tampilKonfirmasiSandi', true)->assertSet('tertundaLabel', 'Hapus metode pembayaran');
        $this->assertDatabaseHas('metode_pembayaran', ['id' => $m->id]);
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'metode_bayar_hapus']);

        $k->set('sandiAdmin', 'salah-salah')->call('konfirmasiSandi')->assertHasErrors('sandiAdmin');
        $this->assertDatabaseHas('metode_pembayaran', ['id' => $m->id]);

        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors()->assertSet('sandiAdmin', '')->assertSet('tampilKonfirmasiSandi', false);
        $this->assertDatabaseMissing('metode_pembayaran', ['id' => $m->id]);
        $log = LogAksiAdmin::where('aksi', 'metode_bayar_hapus')->firstOrFail();
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame('bca', $log->rincian['kode']);
        $this->assertStringNotContainsString('1111222233', LogAksiAdmin::all()->toJson());
    }

    public function test_metode_pembayaran_yang_dipakai_pesanan_tetap_tak_bisa_dihapus_walau_sandi_benar(): void
    {
        $m = MetodePembayaran::create(['kode' => 'bca', 'label' => 'Transfer BCA', 'tipe' => 'manual', 'urutan' => 9, 'aktif' => false, 'biaya_tetap' => 0, 'biaya_persen' => 0, 'kode_unik' => false, 'data' => ['bank' => 'BCA', 'nomor' => '1111222233', 'atas' => 'PT Lama', 'placeholder' => false]]);
        $k = Livewire::actingAs($this->admin)->test(Pembayaran::class)->call('tanyaHapus', $m->id)->call('hapus')->assertSet('tampilKonfirmasiSandi', true);
        // Pesanan muncul setelah dialog terbuka: pemeriksaan dipakai tetap dijalankan setelah konfirmasi.
        Pesanan::factory()->create(['metode_id' => $m->id, 'metode_kode' => 'bca']);
        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi');
        $this->assertDatabaseHas('metode_pembayaran', ['id' => $m->id]);
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'metode_bayar_hapus']);
    }

    // ------------------------------------------------------------ Backup

    public function test_hapus_backup_uji_pulih_dan_retensi_butuh_sandi(): void
    {
        $b = app(PembuatBackup::class)->buat('database', $this->admin);
        $k = Livewire::actingAs($this->admin)->test(BackupRestore::class);

        $k->call('tanyaHapus', $b->id)->call('hapus')->assertSet('tampilKonfirmasiSandi', true)->assertSet('tertundaLabel', 'Hapus backup');
        $this->assertTrue(Backup::whereKey($b->id)->exists());

        $k->call('batalKonfirmasiSandi')->call('tanyaRetensi')->call('terapkanRetensi')->assertSet('tampilKonfirmasiSandi', true)->assertSet('tertundaMetode', 'terapkanRetensi');
        $k->call('batalKonfirmasiSandi')->call('unduh', $b->id)->assertSet('tertundaMetode', 'unduh')->assertNoRedirect();

        $k->call('batalKonfirmasiSandi')->call('tanyaHapus', $b->id)->call('hapus')
            ->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertFalse(Backup::whereKey($b->id)->exists());
    }

    public function test_unduh_backup_lewat_tautan_langsung_tanpa_konfirmasi_dialihkan(): void
    {
        $b = app(PembuatBackup::class)->buat('database', $this->admin);
        $url = route('admin.backup.unduh', $b);

        $this->actingAs($this->admin)->get($url)->assertRedirect(route('admin.backup'));
        $this->assertDatabaseMissing('log_aksi_admin', ['aksi' => 'unduh_backup']);

        $this->actingAs($this->admin)->withSession($this->sesiKonfirmasi($this->admin))->get($url)->assertOk();
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'unduh_backup']);

        $this->actingAs($this->admin)->withSession($this->sesiKonfirmasi($this->admin, now()->subMinutes(6)->timestamp))->get($url)->assertRedirect(route('admin.backup'));
        $this->actingAs(User::factory()->create())->withSession($this->sesiKonfirmasi($this->admin))->get($url)->assertForbidden();
    }

    // ------------------------------------------------------------ aksi sebanding lain

    public function test_hapus_massal_media_butuh_sandi_tetapi_satu_file_tidak(): void
    {
        Storage::fake(Media::DISK);
        $buat = function (string $nama): Media {
            $path = 'sistem/2026/10/'.bin2hex(random_bytes(8)).'.jpg';
            Storage::disk(Media::DISK)->put($path, 'x');

            return Media::create(['user_id' => null, 'nama' => $nama, 'path' => $path, 'tipe' => 'gambar', 'mime' => 'image/jpeg', 'ukuran' => 1000, 'lebar' => 10, 'tinggi' => 10, 'folder' => 'Umum', 'dipakai' => false]);
        };
        $a = $buat('a.jpg');
        $b = $buat('b.jpg');
        $c = $buat('c.jpg');

        $k = Livewire::actingAs($this->admin)->test(PustakaMedia::class)->call('tanyaHapus', [$a->id, $b->id])->call('hapus');
        $k->assertSet('tampilKonfirmasiSandi', true);
        $this->assertSame(3, Media::count());

        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertSame(1, Media::count());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'media_hapus']);

        // Satu file biasa: tanpa sandi.
        $this->travel(6)->minutes();
        Livewire::actingAs($this->admin)->test(PustakaMedia::class)->call('tanyaHapus', [$c->id])->call('hapus')->assertSet('tampilKonfirmasiSandi', false);
        $this->assertSame(0, Media::count());
    }

    public function test_hapus_voucher_addon_tema_butuh_sandi(): void
    {
        $voucher = Voucher::create(['kode' => 'HAPUSAJA', 'jenis' => Voucher::PERSEN, 'nilai' => 10, 'min_belanja' => 0, 'aktif' => true, 'terpakai' => 0]);
        Livewire::actingAs($this->admin)->test(Vouchers::class)->call('tanyaHapus', $voucher->id)->call('hapus')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertTrue(Voucher::whereKey($voucher->id)->exists());

        $addon = AddOn::create(['kode' => 'uji-addon', 'nama' => 'Add-on Uji', 'deskripsi' => 'uji', 'harga' => 5000, 'urutan' => 99, 'aktif' => true]);
        Livewire::actingAs($this->admin)->test(AddOns::class)->call('tanyaHapus', $addon->id)->call('hapus')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertTrue(AddOn::whereKey($addon->id)->exists());

        $tema = Tema::factory()->create(['kode' => null]);
        $tema->delete();
        Livewire::actingAs($this->admin)->test(Theme::class)->call('tanyaHapus', $tema->id)->call('hapusPermanen')->assertSet('tampilKonfirmasiSandi', true);
        $this->assertTrue(Tema::onlyTrashed()->whereKey($tema->id)->exists());

        // Setelah konfirmasi, ketiganya bisa dihapus dan tercatat.
        $this->sudahKonfirmasiSandi($this->admin);
        Livewire::actingAs($this->admin)->test(Vouchers::class)->call('tanyaHapus', $voucher->id)->call('hapus')->assertSet('tampilKonfirmasiSandi', false);
        $this->assertFalse(Voucher::whereKey($voucher->id)->exists());
        $this->assertDatabaseHas('log_aksi_admin', ['aksi' => 'voucher_hapus']);
    }

    public function test_refund_dan_ekspor_pesanan_butuh_sandi(): void
    {
        $pembeli = User::factory()->create();
        $p = Pesanan::factory()->create(['user_id' => $pembeli->id, 'status' => Pesanan::LUNAS, 'dibayar_at' => now()]);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => 'premium', 'nama' => 'Paket Premium', 'harga' => 249000, 'efek' => ['paket' => 'premium', 'durasi_bulan' => 12]]);

        $k = Livewire::actingAs($this->admin)->test(DataPesanan::class)->call('tanya', $p->id, 'refund')->set('alasan', 'Pembeli minta refund')->call('jalankan');
        $k->assertSet('tampilKonfirmasiSandi', true);
        $this->assertSame(Pesanan::LUNAS, $p->fresh()->status);

        $k->set('sandiAdmin', self::SANDI)->call('konfirmasiSandi')->assertHasNoErrors();
        $this->assertSame(Pesanan::REFUND, $p->fresh()->status);

        $this->travel(6)->minutes();
        Livewire::actingAs($this->admin)->test(DataPesanan::class)->call('ekspor')->assertSet('tampilKonfirmasiSandi', true);
    }
}
