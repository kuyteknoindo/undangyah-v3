<?php

namespace Tests\Feature;

use App\Livewire\Admin\DataPesanan;
use App\Livewire\Admin\Invoices;
use App\Livewire\Admin\LogAudit;
use App\Livewire\Admin\PustakaMedia;
use App\Livewire\Admin\Users;
use App\Livewire\Bagikan;
use App\Livewire\Beranda;
use App\Livewire\BukuTamu;
use App\Livewire\HadiahDigital;
use App\Livewire\RiwayatKirim;
use App\Livewire\RsvpTamu;
use App\Livewire\Statistik;
use App\Models\HadiahMasuk;
use App\Models\ItemPesanan;
use App\Models\LogAksiAdmin;
use App\Models\Media;
use App\Models\Pesanan;
use App\Models\RiwayatKirim as ModelRiwayatKirim;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Pagar regresi N+1: jumlah kueri halaman utama tidak boleh tumbuh bersama jumlah data.
 * Tiap halaman dirender pada data kecil lalu data besar (lazy loading dilarang); selisih jumlah kueri harus nol.
 */
class PerformaKueriTest extends TestCase
{
    use RefreshDatabase;

    private const KECIL = 3;

    private const BESAR = 60;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);
        parent::tearDown();
    }

    private function isi(int $n, User $pemilik): Undangan
    {
        $undangan = Undangan::factory()->terbit()->create(['user_id' => $pemilik->id]);
        Undangan::factory()->count(2)->create(['user_id' => $pemilik->id]);

        $tamuList = Tamu::factory()->count($n)->create(['undangan_id' => $undangan->id, 'dikirim_at' => now()]);
        foreach ($tamuList as $i => $tamu) {
            Rsvp::create(['undangan_id' => $undangan->id, 'tamu_id' => $tamu->id, 'nama' => $tamu->nama, 'status' => $i % 3 ? 'hadir' : 'tidak', 'jumlah' => 1, 'pesan' => 'Selamat']);
            Ucapan::create(['undangan_id' => $undangan->id, 'tamu_id' => $tamu->id, 'nama' => $tamu->nama, 'pesan' => 'Selamat ya']);
            Tayangan::create(['undangan_id' => $undangan->id, 'tamu_id' => $tamu->id]);
            HadiahMasuk::create(['undangan_id' => $undangan->id, 'nama' => $tamu->nama, 'via' => 'Transfer', 'nominal' => 50000]);
            ModelRiwayatKirim::create(['user_id' => $pemilik->id, 'undangan_id' => $undangan->id, 'tamu_id' => $tamu->id, 'nama' => $tamu->nama, 'hp' => $tamu->hp]);
        }

        return $undangan;
    }

    private function isiAdmin(int $n): void
    {
        User::factory()->count($n)->create()->each(function (User $u) {
            $p = Pesanan::factory()->create(['user_id' => $u->id, 'bukti_path' => 'x.jpg', 'bukti_at' => now(), 'status' => Pesanan::MENUNGGU]);
            ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => 'premium', 'nama' => 'Premium', 'harga' => 249000]);
            Media::create(['user_id' => $u->id, 'nama' => 'a.jpg', 'path' => 'a/'.$u->id.'.jpg', 'tipe' => 'gambar', 'mime' => 'image/jpeg', 'ukuran' => 1000, 'folder' => 'Umum']);
        });
    }

    /** @return array{int, list<string>} */
    private function hitung(callable $fn): array
    {
        $sql = [];
        DB::listen(function ($q) use (&$sql) {
            $sql[] = $q->sql;
        });
        $fn();

        return [count($sql), $sql];
    }

    private function ukur(string $nama, callable $kecil, callable $besar, int $toleransi = 0): void
    {
        Model::preventLazyLoading(true);
        [$a] = $this->hitung($kecil);
        [$b, $sql] = $this->hitung($besar);
        $this->assertLessThanOrEqual($a + $toleransi, $b, "{$nama}: kueri tumbuh bersama data.\n".implode("\n", array_slice($sql, 0, 60)));
    }

    public function test_halaman_pengguna_tidak_n_plus_1(): void
    {
        $kecil = User::factory()->create();
        $besar = User::factory()->create();
        $uk = $this->isi(self::KECIL, $kecil);
        $ub = $this->isi(self::BESAR, $besar);

        $this->ukur('beranda', fn () => Livewire::actingAs($kecil)->test(Beranda::class)->call('muat'), fn () => Livewire::actingAs($besar)->test(Beranda::class)->call('muat'));
        $this->ukur('rsvp', fn () => Livewire::actingAs($kecil)->test(RsvpTamu::class, ['undangan' => (string) $uk->id])->call('muat'), fn () => Livewire::actingAs($besar)->test(RsvpTamu::class, ['undangan' => (string) $ub->id])->call('muat'));
        $this->ukur('buku-tamu', fn () => Livewire::actingAs($kecil)->test(BukuTamu::class, ['undangan' => (string) $uk->id])->call('muat'), fn () => Livewire::actingAs($besar)->test(BukuTamu::class, ['undangan' => (string) $ub->id])->call('muat'));
        $this->ukur('hadiah', fn () => Livewire::actingAs($kecil)->test(HadiahDigital::class, ['undangan' => (string) $uk->id])->call('muat'), fn () => Livewire::actingAs($besar)->test(HadiahDigital::class, ['undangan' => (string) $ub->id])->call('muat'));
        $this->ukur('statistik', fn () => Livewire::actingAs($kecil)->test(Statistik::class, ['undangan' => (string) $uk->id])->call('muat'), fn () => Livewire::actingAs($besar)->test(Statistik::class, ['undangan' => (string) $ub->id])->call('muat'));
        $this->ukur('bagikan', fn () => Livewire::actingAs($kecil)->test(Bagikan::class, ['undangan' => (string) $uk->id])->call('muat'), fn () => Livewire::actingAs($besar)->test(Bagikan::class, ['undangan' => (string) $ub->id])->call('muat'));
        $this->ukur('riwayat-kirim', fn () => Livewire::actingAs($kecil)->test(RiwayatKirim::class, ['undangan' => (string) $uk->id])->call('muat'), fn () => Livewire::actingAs($besar)->test(RiwayatKirim::class, ['undangan' => (string) $ub->id])->call('muat'));
    }

    public function test_halaman_admin_tidak_n_plus_1(): void
    {
        $admin = User::factory()->admin()->create();
        $halaman = [
            'a-pesanan' => DataPesanan::class,
            'a-invoices' => Invoices::class,
            'a-users' => Users::class,
            'a-media' => PustakaMedia::class,
        ];

        $this->isiAdmin(self::KECIL);
        Model::preventLazyLoading(true);
        $kecil = [];
        foreach ($halaman as $nama => $kelas) {
            [$kecil[$nama]] = $this->hitung(fn () => Livewire::actingAs($admin)->test($kelas));
        }

        $this->isiAdmin(self::BESAR);
        foreach ($halaman as $nama => $kelas) {
            [$besar, $sql] = $this->hitung(fn () => Livewire::actingAs($admin)->test($kelas));
            $this->assertLessThanOrEqual($kecil[$nama], $besar, "{$nama}: kueri tumbuh bersama data.\n".implode("\n", $sql));
        }
    }

    public function test_halaman_log_audit_tidak_n_plus_1(): void
    {
        $admins = User::factory()->admin()->count(3)->create();
        $isi = function (int $n) use ($admins) {
            foreach (range(1, $n) as $i) {
                LogAksiAdmin::catat($admins[$i % 3], 'pesanan_lunas', null, ['nomor' => 'INV-'.$i]);
            }
        };

        $buka = fn () => Livewire::actingAs($admins[0])->test(LogAudit::class)->call('muat')->call('ke', 1)->set('cari', 'inv');

        $isi(self::KECIL);
        Model::preventLazyLoading(true);
        [$kecil] = $this->hitung($buka);
        $isi(self::BESAR);
        [$besar, $sql] = $this->hitung($buka);
        $this->assertLessThanOrEqual($kecil, $besar, "log-audit: kueri tumbuh bersama data.\n".implode("\n", $sql));
    }
}
