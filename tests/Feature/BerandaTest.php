<?php

namespace Tests\Feature;

use App\Livewire\Auth\Masuk;
use App\Livewire\Beranda;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class BerandaTest extends TestCase
{
    use RefreshDatabase;

    private function halaman(User $user)
    {
        return Livewire::actingAs($user)->test(Beranda::class)->call('muat');
    }

    public function test_tamu_diarahkan_ke_masuk_dan_akun_belum_terverifikasi_ditolak(): void
    {
        $this->get('/beranda')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->unverified()->create())->get('/beranda')->assertRedirect();
    }

    public function test_halaman_menampilkan_kerangka_sebelum_data_dimuat(): void
    {
        $user = User::factory()->create(['name' => 'Raka Pratama']);

        $this->actingAs($user)->get('/beranda')
            ->assertOk()
            ->assertSeeLivewire(Beranda::class)
            ->assertSee('Memuat beranda')
            ->assertSee('Raka');

        Livewire::actingAs($user)->test(Beranda::class)
            ->assertSee('Memuat beranda')
            ->call('muat')
            ->assertDontSee('Memuat beranda');
    }

    public function test_akun_tanpa_undangan_melihat_dashboard_dengan_keadaan_kosong_per_widget(): void
    {
        $this->halaman(User::factory()->create())
            // dashboard penuh, bukan layar onboarding pengganti
            ->assertSee('Undangan saya')
            ->assertSee('Ringkasan undangan')
            ->assertSee('Kunjungan undangan')
            ->assertSee('Konfirmasi kehadiran')
            ->assertSee('Aksi cepat')
            ->assertSee('Aktivitas tamu')
            ->assertSee('Ucapan &amp; doa', false)
            ->assertDontSee('Buat undangan digital pertamamu')
            ->assertDontSee('Akun baru')
            // keadaan kosong per widget + onboarding kecil di dalam dashboard
            ->assertSee('Belum ada undangan')
            ->assertSee('Belum ada ucapan dari tamu.')
            ->assertSee('Belum ada aktivitas')
            ->assertSee('Belum ada kunjungan')
            ->assertSee('Langkah menuju undangan siap')
            ->assertSee('0 dari 4 langkah selesai')
            ->assertSee('Lihat contoh dashboard');
    }

    public function test_draf_menampilkan_dashboard_dengan_datanya_bukan_onboarding(): void
    {
        $user = User::factory()->create();
        $draf = Undangan::factory()->for($user, 'pengguna')->create([
            'judul' => 'raka & sinta', 'slug' => 'raka-sinta', 'status' => Undangan::DRAF,
            'tanggal_acara' => now()->addDays(30)->toDateString(), 'lokasi_ringkas' => 'Grand Ballroom, Jakarta',
        ]);
        Tamu::factory()->create(['undangan_id' => $draf->id]);

        $this->halaman($user)
            ->assertDontSee('Buat undangan digital pertamamu')
            ->assertDontSee('Akun baru')
            ->assertDontSee('Belum ada undangan')
            ->assertSee('raka &amp; sinta', false)
            ->assertSee('R&amp;S', false)
            ->assertSee('Undangan ini masih draf')
            ->assertSee('Grand Ballroom, Jakarta')
            ->assertSee('Menuju hari H')
            ->assertSee('role="timer"', false)
            ->assertSee('Langkah menuju undangan siap')
            ->assertSee('Undangan saya')
            ->assertSee('data-undangan', false)
            ->assertSee('Total Tamu')
            ->assertSee('Belum diterbitkan');
    }

    public function test_halaman_beranda_utuh_untuk_draf_lewat_http(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Draf Lewat Http', 'status' => Undangan::DRAF]);

        $this->actingAs($user)->get('/beranda')->assertOk()->assertSeeLivewire(Beranda::class)->assertDontSee('Buat undangan digital pertamamu');
        Livewire::actingAs($user)->test(Beranda::class)->call('muat')->assertSee('Draf Lewat Http')->assertDontSee('Buat undangan digital pertamamu');
    }

    public function test_contoh_dashboard_bisa_dinyalakan_tanpa_menyimpan_data(): void
    {
        $user = User::factory()->create();

        $this->halaman($user)
            ->call('tampilkanContoh')
            ->assertSee('Mode contoh')
            ->assertSee('Raka & Sinta')
            ->assertSee('Undangan saya')
            ->call('sembunyikanContoh')
            ->assertSee('Belum ada undangan')
            ->assertDontSee('Mode contoh');

        $this->assertSame(0, Undangan::count());
    }

    public function test_contoh_tidak_muncul_bila_pengguna_sudah_punya_undangan(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Nyata Satu']);

        $this->halaman($user)
            ->set('contoh', true)
            ->assertSee('Nyata Satu')
            ->assertDontSee('Mode contoh')
            ->assertDontSee('Raka & Sinta');
    }

    public function test_ringkasan_daftar_dan_angka_milik_sendiri(): void
    {
        $user = User::factory()->create();
        $terbit = Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Dina & Eko', 'slug' => 'dina-eko']);
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Draf Saya', 'updated_at' => now()->subDay()]);

        $tamu = Tamu::factory()->count(3)->create(['undangan_id' => $terbit->id]);
        Rsvp::create(['undangan_id' => $terbit->id, 'tamu_id' => $tamu[0]->id, 'nama' => 'Budi Santoso', 'status' => 'hadir', 'jumlah' => 2]);
        Rsvp::create(['undangan_id' => $terbit->id, 'tamu_id' => $tamu[1]->id, 'nama' => 'Ayu Lestari', 'status' => 'tidak', 'jumlah' => 1]);
        Ucapan::create(['undangan_id' => $terbit->id, 'nama' => 'Om Hendra', 'pesan' => 'Selamat berbahagia selalu']);
        Tayangan::create(['undangan_id' => $terbit->id, 'tamu_id' => $tamu[2]->id]);
        Tayangan::create(['undangan_id' => $terbit->id]);

        $this->halaman($user)
            ->assertSee('Dina &amp; Eko', false)
            ->assertSee('Draf Saya')
            ->assertSee('Undangan saya')
            ->assertSee('Budi Santoso')
            ->assertSee('Mengonfirmasi hadir')
            ->assertSee('Berhalangan hadir')
            ->assertSee('Om Hendra')
            ->assertSee('Selamat berbahagia selalu')
            ->assertSee('Membuka undangan')
            // ringkasan untuk undangan utama (Dina & Eko): 3 tamu, 1 hadir, 2 kunjungan (1 tamu bernama + 1 tanpa nama)
            ->assertSeeInOrder(['Total Tamu', '3', 'Terkirim', '0', 'Dilihat', '2', 'Konfirmasi Hadir', '1'])
            ->assertSee('dina-eko');
    }

    public function test_data_pengguna_lain_tidak_bocor(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $punyaB = Undangan::factory()->terbit()->for($b, 'pengguna')->create(['judul' => 'Rahasia Milik B', 'slug' => 'rahasia-b']);
        $tamuB = Tamu::factory()->create(['undangan_id' => $punyaB->id, 'nama' => 'Tamu Rahasia B']);
        Rsvp::create(['undangan_id' => $punyaB->id, 'tamu_id' => $tamuB->id, 'nama' => 'Tamu Rahasia B', 'status' => 'hadir', 'jumlah' => 1]);
        Ucapan::create(['undangan_id' => $punyaB->id, 'nama' => 'Pengirim B', 'pesan' => 'Pesan rahasia B']);
        Undangan::factory()->for($a, 'pengguna')->create(['judul' => 'Milik A']);

        $this->halaman($a)
            ->assertSee('Milik A')
            ->assertDontSee('Rahasia Milik B')
            ->assertDontSee('Tamu Rahasia B')
            ->assertDontSee('Pesan rahasia B')
            ->assertDontSee('rahasia-b');

        // Pengguna tanpa undangan tetap melihat keadaan kosong walau pengguna lain punya data.
        $this->halaman(User::factory()->create())->assertSee('Belum ada undangan')->assertDontSee('Rahasia Milik B');
    }

    public function test_id_utama_milik_orang_lain_diabaikan_saat_render(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Undangan::factory()->for($a, 'pengguna')->create(['judul' => 'Milik A']);
        $punyaB = Undangan::factory()->for($b, 'pengguna')->create(['judul' => 'Milik B Utama']);

        Livewire::actingAs($a)->withQueryParams(['u' => $punyaB->id])->test(Beranda::class)
            ->call('muat')
            ->assertSee('Milik A')
            ->assertDontSee('Milik B Utama');
    }

    public function test_jadikan_utama_hanya_untuk_milik_sendiri(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Undangan::factory()->for($a, 'pengguna')->create(['judul' => 'A Pertama', 'updated_at' => now()]);
        $kedua = Undangan::factory()->for($a, 'pengguna')->create(['judul' => 'A Kedua', 'updated_at' => now()->subDay()]);
        $punyaB = Undangan::factory()->for($b, 'pengguna')->create();

        $this->halaman($a)
            ->call('jadikanUtama', $kedua->id)
            ->assertSet('utama', $kedua->id)
            ->call('jadikanUtama', $punyaB->id)
            ->assertNotFound();
    }

    public function test_filter_status(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Yang Terbit']);
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Yang Draf']);

        $this->halaman($user)
            ->assertSee('Yang Terbit')->assertSee('Yang Draf')
            ->call('pilihFilter', 'draf')
            ->assertSet('filter', 'draf')
            ->assertSeeHtml('data-undangan')
            ->assertSee('Yang Draf')
            ->call('pilihFilter', 'terbit')
            ->assertSee('Yang Terbit')
            ->call('pilihFilter', 'ngawur')
            ->assertSet('filter', 'semua');
    }

    public function test_filter_tanpa_hasil_menampilkan_keadaan_kosong_daftar(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Hanya Draf']);

        $this->halaman($user)
            ->call('pilihFilter', 'terbit')
            ->assertSee('Tidak ada undangan di filter ini')
            ->call('pilihFilter', 'semua')
            ->assertDontSee('Tidak ada undangan di filter ini');
    }

    public function test_pemilik_menghapus_undangan_lewat_konfirmasi(): void
    {
        $user = User::factory()->create();
        $undangan = Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Akan Dihapus']);
        $tamu = Tamu::factory()->create(['undangan_id' => $undangan->id]);
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Tetap Ada']);

        $this->halaman($user)
            ->call('konfirmasiHapus', $undangan->id)
            ->assertSet('tampilHapus', true)
            ->assertSet('hapusId', $undangan->id)
            ->assertSee('akan dihapus permanen')
            ->call('hapus')
            ->assertSet('tampilHapus', false)
            ->assertSet('hapusId', null)
            ->assertDispatched('toast')
            ->assertDontSee('Akan Dihapus')
            ->assertSee('Tetap Ada');

        $this->assertDatabaseMissing('undangan', ['id' => $undangan->id]);
        $this->assertDatabaseMissing('tamu', ['id' => $tamu->id]);
        $this->assertSame(1, Undangan::count());
    }

    public function test_hapus_tanpa_konfirmasi_tidak_melakukan_apa_apa(): void
    {
        $user = User::factory()->create();
        $undangan = Undangan::factory()->for($user, 'pengguna')->create();

        $this->halaman($user)->call('hapus');

        $this->assertDatabaseHas('undangan', ['id' => $undangan->id]);
    }

    public function test_pengguna_lain_tidak_bisa_menghapus_atau_membagikan_undangan_orang(): void
    {
        $pemilik = User::factory()->create();
        $penyusup = User::factory()->create();
        $undangan = Undangan::factory()->terbit()->for($pemilik, 'pengguna')->create();

        $this->halaman($penyusup)
            ->call('konfirmasiHapus', $undangan->id)->assertNotFound();
        $this->halaman($penyusup)
            ->call('bagikan', $undangan->id)->assertNotFound();
        $this->halaman($penyusup)
            ->call('hapus')
            ->assertSet('hapusId', null);

        $this->assertDatabaseHas('undangan', ['id' => $undangan->id]);
    }

    public function test_id_hapus_dan_bagikan_terkunci_dari_klien(): void
    {
        $pemilik = User::factory()->create();
        $penyusup = User::factory()->create();
        $undangan = Undangan::factory()->for($pemilik, 'pengguna')->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->halaman($penyusup)->set('hapusId', $undangan->id);
    }

    public function test_dialog_bagikan_memuat_tautan_undangan_terbit(): void
    {
        $user = User::factory()->create();
        $undangan = Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Bagi Ini', 'slug' => 'bagi-ini']);
        Undangan::factory()->for($user, 'pengguna')->create(['slug' => 'masih-draf', 'updated_at' => now()->subDay()]);

        $this->halaman($user)
            ->call('bagikan', $undangan->id)
            ->assertSet('tampilBagikan', true)
            ->assertSee('Bagikan lewat WhatsApp')
            ->assertSee('Lihat seperti tamu')
            ->assertDontSee('belum diterbitkan, jadi tautannya');

        $draf = Undangan::where('slug', 'masih-draf')->first();
        $this->halaman($user)
            ->call('bagikan', $draf->id)
            ->assertSee('belum diterbitkan, jadi tautannya');
    }

    public function test_hapus_utama_membersihkan_pilihan_utama(): void
    {
        $user = User::factory()->create();
        $a = Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Satu']);
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Dua']);

        $this->halaman($user)
            ->call('jadikanUtama', $a->id)
            ->call('konfirmasiHapus', $a->id)
            ->call('hapus')
            ->assertSet('utama', null)
            ->assertSee('Dua');
    }

    public function test_langkah_menuju_undangan_siap_mengikuti_kemajuan(): void
    {
        $user = User::factory()->create();
        $undangan = Undangan::factory()->for($user, 'pengguna')->create();

        $this->halaman($user)->assertSee('0 dari 4 langkah selesai')->assertSee('Langkah menuju undangan siap');

        Tamu::factory()->create(['undangan_id' => $undangan->id]);
        $this->halaman($user)->assertSee('1 dari 4 langkah selesai');

        $undangan->update(['status' => Undangan::TERBIT, 'isi' => ['x' => 1]]);
        $this->halaman($user)->assertSee('3 dari 4 langkah selesai');
    }

    public function test_grafik_kunjungan_menghitung_tayangan_tujuh_hari(): void
    {
        $user = User::factory()->create();
        $undangan = Undangan::factory()->terbit()->for($user, 'pengguna')->create();
        foreach ([now(), now(), now()->subDays(2)] as $waktu) {
            Tayangan::create(['undangan_id' => $undangan->id, 'created_at' => $waktu]);
        }
        Tayangan::create(['undangan_id' => $undangan->id, 'created_at' => now()->subDays(20)]);

        $this->halaman($user)
            ->assertSee('Kunjungan undangan')
            ->assertSeeInOrder(['Kunjungan undangan', '3', 'Baru minggu ini']);
    }

    public function test_filter_utama_hanya_menampilkan_undangan_utama(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Si Utama', 'updated_at' => now()]);
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Si Kedua', 'updated_at' => now()->subDay()]);

        $this->halaman($user)
            ->call('pilihFilter', 'utama')
            ->assertSet('filter', 'utama')
            ->assertSeeHtml('data-undangan')
            ->assertSee('Si Utama')
            ->assertDontSee('Si Kedua');
    }

    public function test_kolom_cari_menyaring_undangan_dan_nama_tamu(): void
    {
        $user = User::factory()->create();
        $a = Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Alpha Wedding', 'slug' => 'alpha-wedding']);
        $b = Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Beta Party', 'slug' => 'beta-party', 'updated_at' => now()->subDay()]);
        Tamu::factory()->create(['undangan_id' => $b->id, 'nama' => 'Zulkifli Rahasia']);
        $orangLain = Undangan::factory()->create(['judul' => 'Milik Orang Lain']);
        Tamu::factory()->create(['undangan_id' => $orangLain->id, 'nama' => 'Zulkifli Orang Lain']);

        $this->halaman($user)
            ->set('cari', 'alpha')
            ->assertSee('Alpha Wedding')->assertDontSee('Beta Party')
            ->set('cari', 'zulkifli')
            ->assertSee('Beta Party')->assertDontSee('Alpha Wedding')->assertDontSee('Milik Orang Lain')
            ->set('cari', 'tidak-ada-yang-cocok')
            ->assertSee('Tidak ada undangan yang cocok')
            ->call('hapusCari')
            ->assertSet('cari', '')
            ->assertSee('Alpha Wedding')->assertSee('Beta Party');
    }

    public function test_hero_menampilkan_banner_perubahan_belum_diterbitkan(): void
    {
        $user = User::factory()->create();
        Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Ada Perubahan', 'ada_perubahan' => true]);

        $this->halaman($user)
            ->assertSee('Ada perubahan yang belum diterbitkan')
            ->assertSee('Buka editor')
            ->assertSee('Belum diterbitkan ulang')
            ->assertDontSee('Undangan ini masih draf');
    }

    public function test_dialog_bagikan_punya_qr_unduh_qr_dan_wa_dari_pesan_undangan(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Dina & Eko', 'slug' => 'dina-eko']);

        $uji = $this->halaman($user)->call('bagikan', $u->id)
            ->assertSee('Kode QR undangan')
            ->assertSee('Unduh QR')
            ->assertSee('Salin')
            ->assertSee('Lihat seperti tamu')
            ->assertSee('Bagikan lewat WhatsApp')
            ->assertSee('Kirim ke daftar tamu')
            ->assertSeeHtml('<svg')
            ->assertSeeHtml('download="qr-dina-eko.svg"')
            ->assertSeeHtml('https://wa.me/?text=');

        $html = $uji->html();
        $this->assertStringContainsString(rawurlencode(route('undangan.publik', 'dina-eko')), $html);
    }

    public function test_draf_di_dialog_bagikan_tanpa_qr(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Masih Draf', 'slug' => 'masih-draf-2']);

        $this->halaman($user)->call('bagikan', $u->id)
            ->assertSee('belum diterbitkan, jadi tautannya')
            ->assertDontSee('Unduh QR')
            ->assertDontSee('Lihat seperti tamu');
    }

    public function test_aksi_cepat_menautkan_ke_rute_nyata_untuk_undangan_utama(): void
    {
        $user = User::factory()->create();
        $u = Undangan::factory()->terbit()->for($user, 'pengguna')->create(['judul' => 'Aksi Cepat']);

        $this->halaman($user)
            ->assertSee('Tambah tamu')->assertSee('Kirim undangan')->assertSee('Ubah desain')->assertSee('Buku tamu')
            ->assertSeeHtml('href="'.route('edit-undangan', ['undangan' => $u->id]).'"')
            ->assertSeeHtml('href="'.route('buku-tamu', ['undangan' => $u->id]).'"')
            ->assertSeeHtml('href="'.route('rsvp', ['undangan' => $u->id]).'"');
    }

    public function test_masuk_lalu_beranda_langsung_dashboard_untuk_akun_dengan_draf(): void
    {
        $user = User::factory()->create(['email' => 'draf@contoh.com']);
        Undangan::factory()->for($user, 'pengguna')->create(['judul' => 'Draf Setelah Masuk', 'status' => Undangan::DRAF]);

        Livewire::test(Masuk::class)->set('email', 'draf@contoh.com')->set('sandi', 'password')->call('masuk')->assertRedirect(route('beranda'));

        $this->get(route('beranda'))->assertOk()->assertSeeLivewire(Beranda::class);
        Livewire::test(Beranda::class)->call('muat')->assertSee('Draf Setelah Masuk')->assertSee('Undangan saya')->assertDontSee('Buat undangan digital pertamamu');
    }

    public function test_header_beranda_memuat_kolom_cari_dan_kartu_profil(): void
    {
        $user = User::factory()->create(['name' => 'Raka Pratama']);

        $this->actingAs($user)->get('/beranda')->assertOk()
            ->assertSee('Cari undangan atau tamu')
            ->assertSeeHtml('role="search"')
            ->assertSee('Raka Pratama')
            ->assertSee('Selamat')
            ->assertSee('Ini ringkasan undanganmu hari ini.');
    }
}
