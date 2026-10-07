<?php

namespace Tests\Feature;

use App\Livewire\Admin\DataPesanan;
use App\Livewire\Admin\Invoices;
use App\Models\ItemPesanan;
use App\Models\LogAksiAdmin;
use App\Models\Pesanan;
use App\Models\User;
use App\Services\KonfirmasiPesanan;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Concerns\KonfirmasiSandiTes;
use Tests\TestCase;

/** Setiap aksi admin pada pesanan (uang dan akses pembeli) wajib meninggalkan baris log audit yang lengkap dan tanpa data sensitif. */
class AuditAksiPesananAdminTest extends TestCase
{
    use KonfirmasiSandiTes;
    use RefreshDatabase;

    private User $admin;

    private User $pembeli;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        Storage::fake('local');
        $this->admin = User::factory()->admin()->create(['name' => 'Admin Uji', 'email' => 'admin-uji@contoh.com']);
        $this->pembeli = User::factory()->create(['email' => 'pembeli-uji@contoh.com']);
    }

    private function pesanan(string $keadaan): Pesanan
    {
        $p = Pesanan::factory()->create([
            'user_id' => $this->pembeli->id, 'pembeli_nama' => 'Budi', 'pembeli_email' => 'pembeli-uji@contoh.com',
            'metode_data' => ['tipe' => 'manual', 'bank' => 'BANK RAHASIA', 'nomor' => '5550001239876', 'atas' => 'A.N. Contoh'],
        ]);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => 'premium', 'nama' => 'Paket Premium', 'harga' => 249000, 'efek' => ['paket' => 'premium', 'durasi_bulan' => 12]]);
        if (in_array($keadaan, ['lunas', 'tolak'], true)) {
            Storage::disk('local')->put('bukti-pembayaran/x.png', 'isi');
            $p->forceFill(['bukti_path' => 'bukti-pembayaran/x.png', 'bukti_nama' => 'transfer.png', 'bukti_at' => now()])->save();
        }
        if (in_array($keadaan, ['refund', 'tinjau'], true)) {
            app(KonfirmasiPesanan::class)->lunas($p);
            $p->refresh();
        }
        if ($keadaan === 'tinjau') {
            $p->forceFill(['perlu_ditinjau_at' => now(), 'catatan_tinjau' => 'Perlu dicek'])->save();
        }

        return $p;
    }

    /** @return array<string,array{0:string,1:class-string,2:string,3:string}> */
    public static function jalur(): array
    {
        $aksi = [
            ['lunas', 'lunas', 'pesanan_lunas'],
            ['tolak', 'tolak', 'pesanan_tolak'],
            ['batal', 'batal', 'pesanan_batal'],
            ['kedaluwarsa', 'kedaluwarsa', 'pesanan_kedaluwarsa'],
            ['tinjau', 'tinjau', 'pesanan_tinjau'],
            ['refund', 'refund', 'pesanan_refund'],
        ];
        $hasil = [];
        foreach ([DataPesanan::class, Invoices::class] as $kelas) {
            foreach ($aksi as [$keadaan, $jenis, $kode]) {
                $hasil[class_basename($kelas).' '.$jenis] = [$jenis, $kelas, $kode, $keadaan === 'batal' || $keadaan === 'kedaluwarsa' ? 'menunggu' : $keadaan];
            }
        }

        return $hasil;
    }

    #[DataProvider('jalur')]
    public function test_tiap_aksi_admin_menghasilkan_baris_log_lengkap_tanpa_data_sensitif(string $jenis, string $kelas, string $kode, string $keadaan): void
    {
        $this->travelTo(now()->setDate(2026, 10, 6)->setTime(10, 0));
        $p = $this->pesanan($keadaan);
        $alasan = in_array($jenis, ['tolak', 'batal', 'refund'], true) ? 'Alasan uji audit' : '';

        Livewire::actingAs($this->sudahKonfirmasiSandi($this->admin))->test($kelas)
            ->call('tanya', $p->id, $jenis)->set('alasan', $alasan)->call('jalankan')->assertHasNoErrors();

        $log = LogAksiAdmin::where('aksi', $kode)->sole();
        $this->assertSame($this->admin->id, $log->admin_id);
        $this->assertSame('admin-uji@contoh.com', $log->admin_email);
        $this->assertSame($this->pembeli->id, $log->target_id);
        $this->assertSame($p->nomor, $log->rincian['nomor']);
        $this->assertSame($p->total, $log->rincian['total']);
        $this->assertNotNull($log->ip);
        $this->assertSame(now()->toDateTimeString(), $log->created_at->toDateTimeString());
        if ($alasan !== '') {
            $this->assertSame($alasan, $log->rincian['alasan']);
        }

        $mentah = $log->toJson();
        foreach (['5550001239876', 'BANK RAHASIA', 'password', 'remember_token'] as $terlarang) {
            $this->assertStringNotContainsString($terlarang, $mentah);
        }
    }

    public function test_aksi_yang_gagal_atau_ditolak_tidak_meninggalkan_log_palsu(): void
    {
        $p = $this->pesanan('lunas');
        $k = Livewire::actingAs($this->sudahKonfirmasiSandi($this->admin))->test(DataPesanan::class);
        $k->call('tanya', $p->id, 'lunas')->set('alasan', '')->call('jalankan');
        // Status sudah lunas: aksi kedua (stale) tidak mengubah apa pun dan tidak dicatat dua kali.
        $k->call('jalankan');
        $this->assertSame(1, LogAksiAdmin::where('aksi', 'pesanan_lunas')->count());

        // Validasi gagal (alasan wajib) = tidak ada perubahan dan tidak ada log.
        $q = $this->pesanan('tolak');
        $k->call('tanya', $q->id, 'tolak')->set('alasan', '')->call('jalankan')->assertHasErrors('alasan');
        $this->assertSame('menunggu', $q->fresh()->status);
        $this->assertSame(0, LogAksiAdmin::where('aksi', 'pesanan_tolak')->count());
    }

    public function test_sapuan_terjadwal_bukan_aksi_admin_dan_tidak_masuk_log(): void
    {
        $p = Pesanan::factory()->kedaluwarsa()->create(['user_id' => $this->pembeli->id]);
        app(KonfirmasiPesanan::class)->sapuKedaluwarsa();
        $this->assertSame('kedaluwarsa', $p->fresh()->status);
        $this->assertSame(0, LogAksiAdmin::count());
    }
}
