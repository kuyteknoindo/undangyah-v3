<?php

namespace Tests\Feature;

use App\Livewire\Admin\LogAudit;
use App\Models\LogAksiAdmin;
use App\Models\User;
use App\Support\LabelAksiAdmin;
use App\Support\Navigasi;
use App\Support\PenyamarLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

class AdminLogAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 7)->setTime(12, 0));
        $this->admin = User::factory()->admin()->create(['name' => 'Bos Besar', 'email' => 'bos@contoh.com']);
    }

    private function halaman(?User $admin = null)
    {
        return Livewire::actingAs($admin ?? $this->admin)->test(LogAudit::class)->call('muat');
    }

    /** @param  array<string,mixed>  $atribut */
    private function log(string $aksi, array $atribut = []): LogAksiAdmin
    {
        $admin = $atribut['admin'] ?? $this->admin;
        unset($atribut['admin']);
        $baris = LogAksiAdmin::catat($admin, $aksi, null, $atribut['rincian'] ?? []);
        $baris->forceFill(array_filter([
            'created_at' => $atribut['waktu'] ?? null,
            'ip' => $atribut['ip'] ?? null,
            'sasaran' => $atribut['sasaran'] ?? null,
            'target_email' => $atribut['target_email'] ?? null,
        ], fn ($v) => $v !== null))->save();

        return $baris;
    }

    // ---- akses

    public function test_tamu_diarahkan_non_admin_403_admin_boleh(): void
    {
        $this->get('/admin/audit')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/audit')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/audit')->assertOk()->assertSeeLivewire(LogAudit::class)->assertSee('Log Audit');
        Livewire::actingAs(User::factory()->create())->test(LogAudit::class)->assertForbidden();
    }

    public function test_peran_admin_dicabut_di_tengah_jalan_ditolak_di_semua_titik(): void
    {
        $baris = $this->log('pesanan_lunas');
        foreach ([['muat', []], ['bukaDetail', [$baris->id]], ['ke', [2]], ['aturUlangFilter', []], ['tutupDetail', []]] as [$aksi, $arg]) {
            $k = Livewire::actingAs($this->admin)->test(LogAudit::class);
            $this->admin->forceFill(['role' => 'user'])->save();
            $this->actingAs($this->admin->fresh());
            if ($aksi !== 'tutupDetail') {
                $k->call($aksi, ...$arg)->assertForbidden();
            }
            $this->admin->forceFill(['role' => 'admin'])->save();
        }
    }

    public function test_menu_dan_rute_terdaftar(): void
    {
        $slug = collect(Navigasi::admin())->flatMap(fn ($g) => $g['item'])->pluck('slug');
        $this->assertTrue($slug->contains('audit'));
        $this->assertCount(15, $slug);
        $this->assertSame('Log Audit', Navigasi::judul('admin.audit'));
        $this->assertSame('/admin/audit', route('admin.audit', [], false));
    }

    // ---- daftar

    public function test_skeleton_dulu_lalu_data_setelah_muat(): void
    {
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-1']]);
        Livewire::actingAs($this->admin)->test(LogAudit::class)->assertSee('Memuat log audit')->assertDontSee('INV-1')
            ->call('muat')->assertDontSee('Memuat log audit')->assertSee('INV-1');
    }

    public function test_terbaru_dulu_label_manusiawi_dan_paginasi_dua_puluh(): void
    {
        foreach (range(1, 25) as $i) {
            $this->log('pesanan_lunas', ['rincian' => ['nomor' => sprintf('INV-%03d', $i)], 'waktu' => now()->subMinutes(100 - $i)]);
        }
        $k = $this->halaman()->assertSee('Menampilkan 1–20 dari 25')->assertSee('INV-025')->assertSee('INV-006')->assertDontSee('INV-005')
            ->assertSee('Pesanan ditandai lunas')->assertSee('Bos Besar')->assertSee('bos@contoh.com');
        $html = $k->html();
        $this->assertLessThan(strpos($html, 'INV-024'), strpos($html, 'INV-025'));
        $this->assertLessThan(strpos($html, 'INV-006'), strpos($html, 'INV-024'));

        $k->call('ke', 2)->assertSee('Menampilkan 21–25 dari 25')->assertSee('INV-005')->assertSee('INV-001')->assertDontSee('INV-006');
        // Halaman di luar jangkauan dirapikan, bukan kosong.
        $k->call('ke', 99)->assertSee('INV-001');
    }

    public function test_kode_mentah_ada_di_tooltip_dan_kode_tak_dikenal_tetap_terbaca(): void
    {
        $this->log('aksi_baru_entah');
        $this->halaman()->assertSee('title="aksi_baru_entah"', false)->assertSee('Aksi baru entah');
        $this->assertSame('Pesanan ditandai refund', LabelAksiAdmin::label('pesanan_refund'));
    }

    public function test_kosong_menampilkan_empty_state_dan_filter_tanpa_hasil(): void
    {
        $this->halaman()->assertSee('Belum ada catatan audit');
        $this->log('pesanan_lunas');
        $this->halaman()->set('cari', 'tidak-ada-yang-begini')->assertSee('Tidak ada catatan yang cocok')->assertSee('Reset filter')
            ->call('aturUlangFilter')->assertDontSee('Tidak ada catatan yang cocok')->assertSee('Pesanan ditandai lunas');
    }

    // ---- filter

    public function test_filter_tanggal_admin_dan_aksi_bisa_digabung(): void
    {
        $lain = User::factory()->admin()->create(['email' => 'lain@contoh.com']);
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-A'], 'waktu' => '2026-10-01 00:00:00']);
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-B'], 'waktu' => '2026-10-03 23:59:59']);
        $this->log('pesanan_batal', ['rincian' => ['nomor' => 'INV-C'], 'waktu' => '2026-10-03 10:00:00']);
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-D'], 'waktu' => '2026-10-04 00:00:00', 'admin' => $lain]);
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-E'], 'waktu' => '2026-10-03 12:00:00', 'admin' => $lain]);

        // Batas tanggal inklusif: 1 Okt 00:00 dan 3 Okt 23:59:59 ikut, 4 Okt 00:00 tidak.
        $this->halaman()->set('dari', '2026-10-01')->set('sampai', '2026-10-03')
            ->assertSee('INV-A')->assertSee('INV-B')->assertSee('INV-C')->assertSee('INV-E')->assertDontSee('INV-D');
        $this->halaman()->set('dari', '2026-10-02')->assertDontSee('INV-A')->assertSee('INV-B')->assertSee('INV-D');
        $this->halaman()->set('sampai', '2026-10-01')->assertSee('INV-A')->assertDontSee('INV-B');

        $this->halaman()->set('admin', 'lain@contoh.com')->assertSee('INV-D')->assertSee('INV-E')->assertDontSee('INV-A');
        $this->halaman()->set('aksi', 'pesanan_batal')->assertSee('INV-C')->assertDontSee('INV-A')->assertDontSee('INV-E');

        // Gabungan: admin lain + lunas + 3 Okt saja.
        $this->halaman()->set('admin', 'lain@contoh.com')->set('aksi', 'pesanan_lunas')->set('dari', '2026-10-03')->set('sampai', '2026-10-03')
            ->assertSee('INV-E')->assertDontSee('INV-D')->assertDontSee('INV-B')->assertDontSee('INV-C');

        // Daftar pilihan berasal dari isi log.
        $this->halaman()->assertSee('lain@contoh.com')->assertSee('bos@contoh.com')->assertSee('Pesanan dibatalkan');
    }

    public function test_tanggal_tidak_sah_diabaikan_dan_rentang_terbalik_ditukar(): void
    {
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-X'], 'waktu' => '2026-10-02 10:00:00']);
        $this->halaman()->set('dari', 'bukan-tanggal')->set('sampai', '2026-02-31')->assertSet('dari', '')->assertSet('sampai', '')->assertSee('INV-X');
        $this->halaman()->set('dari', '2026-10-05')->set('sampai', '2026-10-01')->assertSet('dari', '2026-10-01')->assertSet('sampai', '2026-10-05')->assertSee('INV-X');
    }

    public function test_pencarian_sasaran_aksi_label_email_dan_ip(): void
    {
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-2026-00077'], 'ip' => '10.1.2.3']);
        $this->log('hapus_akun', ['target_email' => 'korban@contoh.com', 'sasaran' => 'korban@contoh.com', 'ip' => '10.9.9.9']);
        $this->log('buat_backup', ['rincian' => ['backup_id' => 5]]);

        $this->halaman()->set('cari', 'inv-2026-00077')->assertSee('INV-2026-00077')->assertDontSee('korban@contoh.com');
        $this->halaman()->set('cari', 'korban@')->assertSee('korban@contoh.com')->assertDontSee('INV-2026');
        $this->halaman()->set('cari', '10.1.2')->assertSee('INV-2026-00077')->assertDontSee('korban@contoh.com');
        $this->halaman()->set('cari', 'hapus_akun')->assertSee('korban@contoh.com')->assertDontSee('INV-2026');
        $this->halaman()->set('cari', 'dibuat')->assertSee('Backup dibuat')->assertDontSee('korban@contoh.com');
        $this->halaman()->set('cari', 'bos@contoh')->assertSee('INV-2026-00077')->assertSee('korban@contoh.com');
        $this->halaman()->set('cari', 'Backup #5')->assertSee('Backup #5')->assertDontSee('INV-2026');
    }

    public function test_wildcard_persen_dan_garis_bawah_diperlakukan_literal(): void
    {
        $this->log('pesanan_lunas', ['rincian' => ['nomor' => 'INV-AB']]);
        $this->log('pesanan_batal', ['rincian' => ['nomor' => 'INV-100%_X']]);

        $this->halaman()->set('cari', '%')->assertSee('INV-100%_X')->assertDontSee('INV-AB');
        $this->halaman()->set('cari', 'INV-A_')->assertDontSee('INV-AB')->assertSee('Tidak ada catatan yang cocok');
        $this->halaman()->set('cari', '100%_x')->assertSee('INV-100%_X');
        $this->halaman()->set('cari', '\\')->assertSee('Tidak ada catatan yang cocok');
    }

    // ---- detail dan penyamaran

    public function test_detail_menyamarkan_data_sensitif(): void
    {
        $baris = $this->log('ubah_rekening', ['rincian' => [
            'metode' => 'Transfer BCA',
            'sebelum' => ['bank' => 'BCA', 'nomor' => '1234567890123'],
            'sesudah' => ['bank' => 'BCA', 'nomor' => '******3210', 'tujuan_nilai' => '081234567890'],
            'password' => 'RahasiaSekali!1',
            'sandi_admin' => 'SandiPanjang#9',
            'token' => 'tok_live_abcdef123456',
            'api_secret' => 'sk_live_zzzzzzzz',
            'kode_otp' => '482913',
            'kode' => '735201',
            'nomor_rekening' => '9876543210987',
            'catatan' => '5550001112223',
            'isi' => 'Halo ini isi pesan pribadi',
            'bearer' => str_repeat('a1B2', 12),
            'alasan' => 'Pindah bank',
            'email_pihak_ketiga' => 'pembeli@contoh.com',
            'nomor_pesanan' => 'INV-2026-00001',
            'kode_voucher' => 'HEMAT10',
        ]]);

        $html = $this->halaman()->call('bukaDetail', $baris->id)->assertSet('dialogDetail', true)->html();

        foreach (['1234567890123', '9876543210987', '081234567890', 'RahasiaSekali', 'SandiPanjang', 'tok_live', 'sk_live', '482913', '735201', '5550001112223', 'isi pesan pribadi', str_repeat('a1B2', 12)] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $html, "bocor: {$terlarang}");
        }
        // Yang boleh tampil.
        foreach (['******3210', '*********0123', 'Pindah bank', 'pembeli@contoh.com', 'INV-2026-00001', 'HEMAT10', PenyamarLog::TERSEMBUNYI, 'Transfer BCA'] as $boleh) {
            $this->assertStringContainsString(e($boleh), $html, "hilang: {$boleh}");
        }
    }

    public function test_penyamar_unit(): void
    {
        $r = PenyamarLog::saring([
            'nomor' => 'INV-2026-00001', 'rekening' => ['nomor' => '1111222233334444'], 'hp' => '081200001111',
            'tanggal' => '2026-10-07', 'iso' => '2026-10-07T10:00:00+07:00', 'total' => 1234567890, 'ok' => true,
            'dalam' => ['password' => 'x', 'daftar' => [['secret' => 'y', 'aman' => 'z']]],
        ]);
        $this->assertSame('INV-2026-00001', $r['nomor']);
        $this->assertSame('************4444', $r['rekening']['nomor']);
        $this->assertSame('********1111', $r['hp']);
        $this->assertSame('2026-10-07', $r['tanggal']);
        $this->assertSame('2026-10-07T10:00:00+07:00', $r['iso']);
        $this->assertSame(1234567890, $r['total']);
        $this->assertTrue($r['ok']);
        $this->assertSame(PenyamarLog::TERSEMBUNYI, $r['dalam']['password']);
        $this->assertSame(PenyamarLog::TERSEMBUNYI, $r['dalam']['daftar'][0]['secret']);
        $this->assertSame('z', $r['dalam']['daftar'][0]['aman']);
        $this->assertNull(PenyamarLog::saring(null));
    }

    public function test_detail_id_terkunci_dan_id_tak_ada_404(): void
    {
        $k = $this->halaman();
        try {
            $k->set('detailId', 1);
            $this->fail('detailId seharusnya terkunci');
        } catch (CannotUpdateLockedPropertyException) {
            $this->assertTrue(true);
        }
        $k->call('bukaDetail', 99999)->assertNotFound();
    }

    // ---- baca saja

    public function test_tidak_ada_metode_publik_yang_menulis_dan_tidak_ada_tulis_ke_database(): void
    {
        $boleh = ['mount', 'muat', 'updated', 'aturUlangFilter', 'ke', 'bukaDetail', 'tutupDetail', 'render'];
        $publik = collect((new ReflectionClass(LogAudit::class))->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $m) => $m->getDeclaringClass()->getName() === LogAudit::class)->map->getName()->all();
        $this->assertEqualsCanonicalizing($boleh, $publik);

        $sumber = file_get_contents(app_path('Livewire/Admin/LogAudit.php')).file_get_contents(resource_path('views/livewire/admin/audit/index.blade.php'));
        foreach (['->delete(', '->update(', '->save(', '->create(', 'forceDelete', '::catat(', 'wire:click="hapus', 'streamDownload', 'function ekspor', 'wire:click="ekspor'] as $tulis) {
            $this->assertStringNotContainsString($tulis, $sumber);
        }

        $baris = $this->log('pesanan_lunas');
        $sebelum = LogAksiAdmin::count();
        $sql = [];
        \DB::listen(function ($q) use (&$sql) {
            $sql[] = strtolower(ltrim($q->sql));
        });
        $this->halaman()->set('cari', 'x')->call('aturUlangFilter')->call('bukaDetail', $baris->id)->call('tutupDetail');
        $this->assertSame($sebelum, LogAksiAdmin::count());
        $menulis = array_filter($sql, fn ($s) => preg_match('/^(insert|update|delete|drop|alter|truncate)\b/', $s) && ! str_contains($s, 'sessions') && ! str_contains($s, 'cache'));
        $this->assertSame([], array_values($menulis));
    }

    // ---- sasaran

    public function test_catat_mengisi_sasaran_tanpa_data_sensitif(): void
    {
        $sasaran = fn (string $aksi, ?string $email, array $r) => LogAksiAdmin::turunkanSasaran($aksi, $email, $r ?: null);
        $this->assertSame('Pesanan INV-9', $sasaran('pesanan_lunas', 'b@x.id', ['nomor' => 'INV-9']));
        $this->assertSame('b@x.id', $sasaran('tangguhkan', 'b@x.id', []));
        $this->assertSame('Metode Transfer BCA', $sasaran('ubah_rekening', null, ['metode' => 'Transfer BCA', 'sesudah' => ['nomor' => '123']]));
        $this->assertSame('Voucher HEMAT', $sasaran('voucher_hapus', null, ['kode' => 'HEMAT']));
        $this->assertSame('Backup #3', $sasaran('hapus_backup', null, ['backup_id' => 3]));
        $this->assertNull($sasaran('ekspor_pengguna', null, ['jumlah' => 3]));
    }
}
