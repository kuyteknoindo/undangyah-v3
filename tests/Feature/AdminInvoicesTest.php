<?php

namespace Tests\Feature;

use App\Livewire\Admin\Invoices;
use App\Models\ItemPesanan;
use App\Models\Notifikasi;
use App\Models\Pesanan;
use App\Models\User;
use Database\Seeders\PaketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminInvoicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PaketSeeder::class);
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(10, 0));
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function pesanan(?User $user = null, array $atribut = []): Pesanan
    {
        $user ??= User::factory()->create();
        $p = Pesanan::factory()->create($atribut + ['user_id' => $user->id, 'pembeli_nama' => 'Sari Wulandari', 'pembeli_email' => 'sari@example.test', 'metode_label' => 'Transfer manual',
            'metode_data' => ['tipe' => 'manual', 'bank' => 'BANK CONTOH', 'nomor' => '123', 'atas' => 'A.N. Contoh']]);
        ItemPesanan::create(['pesanan_id' => $p->id, 'tipe' => 'paket', 'kode' => 'premium', 'nama' => 'Paket Premium', 'harga' => 249000, 'efek' => ['paket' => 'premium', 'durasi_bulan' => 12]]);

        return $p;
    }

    private function bukti(Pesanan $p): Pesanan
    {
        Storage::disk('local')->put('bukti-pembayaran/'.$p->id.'.png', 'isi');
        $p->forceFill(['bukti_path' => 'bukti-pembayaran/'.$p->id.'.png', 'bukti_nama' => 'transfer-'.$p->id.'.png', 'bukti_at' => now()])->save();

        return $p;
    }

    private function halaman(?User $admin = null)
    {
        return Livewire::actingAs($admin ?? $this->admin())->test(Invoices::class);
    }

    public function test_tamu_diarahkan_non_admin_403_admin_ok(): void
    {
        $this->get('/admin/invoices')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/admin/invoices')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/invoices')->assertOk()->assertSeeLivewire(Invoices::class);
        Livewire::actingAs(User::factory()->create())->test(Invoices::class)->assertForbidden();
    }

    public function test_aksi_ditolak_bila_peran_dicabut(): void
    {
        $p = $this->bukti($this->pesanan());
        foreach ([['tinjau', [$p->id]], ['tanya', [$p->id, 'lunas']], ['tunda', [$p->id]], ['kirimUlang', [$p->id]], ['ekspor', []]] as [$aksi, $arg]) {
            $k = Livewire::actingAs($this->admin())->test(Invoices::class);
            $this->actingAs(User::factory()->create());
            $k->call($aksi, ...$arg)->assertForbidden();
        }
        $this->assertSame('menunggu', $p->fresh()->status);
    }

    public function test_daftar_invoice_ringkasan_dan_pendapatan_bulanan(): void
    {
        $this->pesanan(null, ['status' => 'lunas', 'total' => 200000, 'dibayar_at' => now()]);
        $this->pesanan(null, ['status' => 'lunas', 'total' => 100000, 'dibayar_at' => now()->subMonth(), 'created_at' => now()->subMonth()]);
        $this->pesanan(null, ['status' => 'lunas', 'total' => 40000, 'dibayar_at' => now()->subMonths(8), 'created_at' => now()->subMonths(8)]);
        $menunggu = $this->pesanan(null, ['total' => 70000, 'kedaluwarsa_at' => now()->addHours(5)]);
        $this->pesanan(null, ['status' => 'batal', 'total' => 999000]);

        $k = $this->halaman();
        $k->assertSee($menunggu->nomor)
            ->assertSee('Rp 340.000')           // lunas total (3 invoice)
            ->assertSee('3 invoice lunas')
            ->assertSee('Rp 70.000')            // belum dibayar
            ->assertSee('1 invoice · 1 jatuh tempo')
            ->assertSee('Bulan ini Rp 200.000')
            ->assertSee('Rp 100.000 <span', false)
            ->assertSee('Rp 410.000 ditagihkan, di luar batal');

        $html = $k->html();
        $this->assertStringNotContainsString('Rp 40.000 <span', $html, 'pembayaran 8 bulan lalu di luar 6 bulan');
    }

    public function test_filter_status_cari_urut_dan_paginasi(): void
    {
        $lunas = $this->pesanan(null, ['status' => 'lunas', 'pembeli_nama' => 'Tono Lunas', 'total' => 111000]);
        $menunggu = $this->pesanan(null, ['pembeli_nama' => 'Rini Menunggu', 'total' => 222000]);
        $lewat = $this->pesanan(null, ['kedaluwarsa_at' => now()->subHour(), 'pembeli_nama' => 'Eko Lewat']);

        $this->halaman()->call('pilihStatus', 'lunas')->assertSee($lunas->nomor)->assertDontSee($menunggu->nomor)
            ->call('pilihStatus', 'kedaluwarsa')->assertSee($lewat->nomor)->assertDontSee($menunggu->nomor)
            ->call('resetFilter')->set('cari', 'rini')->assertSee($menunggu->nomor)->assertDontSee($lunas->nomor)
            ->set('cari', 'zzz')->assertSee('Tidak ada invoice yang cocok')
            ->call('resetFilter')->set('urut', 'rendah')->assertSet('urut', 'rendah')
            ->set('urut', 'asal')->assertSet('urut', 'baru');

        foreach (range(1, 12) as $i) {
            $this->pesanan(null, ['total' => 5000 + $i]);
        }
        $this->halaman()->assertSee('Menampilkan 1–10 dari 15')->call('ke', 2)->assertSee('Menampilkan 11–15 dari 15');
    }

    public function test_antrean_perlu_ditinjau_hanya_menunggu_dengan_bukti_pending_di_bawah(): void
    {
        $a = $this->bukti($this->pesanan(null, ['pembeli_nama' => 'Antrean A']));
        $b = $this->bukti($this->pesanan(null, ['pembeli_nama' => 'Antrean B', 'ditunda_at' => now()]));
        $tanpa = $this->pesanan(null, ['pembeli_nama' => 'Tanpa Bukti']);
        $lunas = $this->bukti($this->pesanan(null, ['pembeli_nama' => 'Sudah Lunas', 'status' => 'lunas']));

        $html = $this->halaman()->assertSee('Perlu ditinjau')->assertSee('Antrean A')->assertSee('Antrean B')->html();
        $this->assertStringContainsString('aria-label="Lihat bukti transfer '.$a->nomor.'"', $html);
        $this->assertStringNotContainsString('aria-label="Lihat bukti transfer '.$tanpa->nomor.'"', $html);
        $this->assertStringNotContainsString('aria-label="Lihat bukti transfer '.$lunas->nomor.'"', $html);
        $this->assertLessThan(strpos($html, 'Antrean B'), strpos($html, 'Antrean A'), 'yang Pending ditaruh di bawah');
    }

    public function test_tinjau_menampilkan_bukti_lewat_rute_terotorisasi(): void
    {
        $p = $this->bukti($this->pesanan());
        $this->halaman()->call('tinjau', $p->id)->assertSet('dialogTinjau', true)
            ->assertSee('Bukti transfer '.$p->nomor)->assertSee(route('pesanan.bukti', $p), false)->assertSee('BANK CONTOH 123')->assertSee('Cocokkan nominal');
        $this->halaman()->call('tinjau', 99999)->assertNotFound();
    }

    public function test_setujui_dari_antrean_melunasi_dan_menutup_dialog(): void
    {
        $user = User::factory()->create();
        $p = $this->bukti($this->pesanan($user));

        $this->halaman()->call('tinjau', $p->id)->call('tanya', $p->id, 'lunas')->call('jalankan')
            ->assertSet('dialogTinjau', false)->assertSet('tinjauId', null);
        $this->assertSame('lunas', $p->fresh()->status);
        $this->assertSame('premium', $user->fresh()->paket);
    }

    public function test_tolak_dari_antrean_wajib_alasan(): void
    {
        $user = User::factory()->create();
        $p = $this->bukti($this->pesanan($user));
        $k = $this->halaman()->call('tanya', $p->id, 'tolak')->call('jalankan')->assertHasErrors('alasan');
        $k->set('alasan', 'Nama pengirim tidak cocok')->call('jalankan')->assertHasNoErrors();
        $p->refresh();
        $this->assertNull($p->bukti_path);
        $this->assertNotNull($p->bukti_ditolak_at);
        $this->assertStringContainsString('Nama pengirim tidak cocok', Notifikasi::where('user_id', $user->id)->value('isi'));
        $k->assertSee('Tidak ada bukti transfer yang menunggu');
    }

    public function test_pending_menggeser_ke_bawah_dan_bisa_dilepas(): void
    {
        $p = $this->bukti($this->pesanan());
        $k = $this->halaman()->call('tunda', $p->id)->assertSee('Pending');
        $this->assertNotNull($p->fresh()->ditunda_at);
        $k->call('tunda', $p->id);
        $this->assertNull($p->fresh()->ditunda_at);
    }

    public function test_tinjau_pesanan_lunas_tidak_menyediakan_aksi_status(): void
    {
        $p = $this->bukti($this->pesanan(null, ['status' => 'lunas']));
        $k = $this->halaman()->call('tinjau', $p->id);
        $k->call('tanya', $p->id, 'tolak')->assertSet('dialogAksi', false);
        $k->call('tanya', $p->id, 'lunas')->assertSet('dialogAksi', false);
        $this->assertSame('lunas', $p->fresh()->status);
    }

    public function test_kirim_ulang_invoice_lunas_ke_pembeli_dengan_tautan_invoice(): void
    {
        $user = User::factory()->create();
        $p = $this->pesanan($user, ['status' => 'lunas', 'dibayar_at' => now()]);
        $this->halaman()->call('kirimUlang', $p->id)->assertDispatched('toast', tipe: 'sukses');
        $n = Notifikasi::where('user_id', $user->id)->first();
        $this->assertSame('/invoice/'.$p->nomor, $n->tautan);
        $this->assertSame('pembayaran', $n->tipe);
    }

    public function test_kirim_ulang_kedaluwarsa_mengarah_ke_billing_dan_batal_ditolak(): void
    {
        $user = User::factory()->create();
        $lewat = $this->pesanan($user, ['kedaluwarsa_at' => now()->subHour()]);
        $batal = $this->pesanan($user, ['status' => 'batal']);
        $k = $this->halaman()->call('kirimUlang', $lewat->id);
        $this->assertSame('/billing', Notifikasi::where('user_id', $user->id)->value('tautan'));
        $k->call('kirimUlang', $batal->id)->assertDispatched('toast', tipe: 'galat');
        $this->assertSame(1, Notifikasi::where('user_id', $user->id)->count());
    }

    public function test_ekspor_csv_mengikuti_filter_dan_aman_rumus(): void
    {
        $a = $this->pesanan(null, ['status' => 'lunas', 'pembeli_nama' => '@cmd|calc', 'dibayar_at' => now()]);
        $b = $this->pesanan(null, ['pembeli_nama' => 'Biasa']);
        $k = $this->halaman()->call('pilihStatus', 'lunas')->call('ekspor');
        $k->assertFileDownloaded();
        $csv = base64_decode($k->effects['download']['content']);
        $this->assertStringContainsString($a->nomor, $csv);
        $this->assertStringNotContainsString($b->nomor, $csv);
        $this->assertStringContainsString("'@cmd|calc", $csv);
        $this->assertStringContainsString('No. invoice', $csv);
    }

    public function test_admin_membuka_invoice_pengguna_lain_dan_tombol_kembali_ke_invoices(): void
    {
        $p = $this->pesanan(null, ['status' => 'lunas', 'dibayar_at' => now()]);
        $this->actingAs($this->admin())->get(route('invoice', $p))->assertOk()->assertSee($p->nomor)->assertSee('Sari Wulandari')
            ->assertSee(route('admin.invoices'), false);
        $this->actingAs($p->pengguna)->get(route('invoice', $p))->assertOk()->assertSee(route('billing'), false)->assertDontSee(route('admin.invoices'), false);
        $this->actingAs(User::factory()->create())->get(route('invoice', $p))->assertNotFound();
    }

    public function test_agregasi_sql_tidak_memuat_data_orang_lain_ke_pembeli(): void
    {
        // Pembeli biasa tidak pernah melihat ringkasan pendapatan.
        $this->pesanan(null, ['status' => 'lunas', 'total' => 777000, 'dibayar_at' => now()]);
        $this->actingAs(User::factory()->create())->get('/admin/invoices')->assertForbidden()->assertDontSee('777.000');
    }
}
