<?php

namespace Tests\Feature;

use App\Livewire\TemplatePesan as KomponenTemplatePesan;
use App\Models\Tamu;
use App\Models\TemplatePesan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\PesanUndangan;
use App\Services\RingkasanAkun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class TemplatePesanTest extends TestCase
{
    use RefreshDatabase;

    private function komponen(User $user, ?int $undangan = null)
    {
        return Livewire::actingAs($user)->test(KomponenTemplatePesan::class, ['undangan' => $undangan === null ? null : (string) $undangan])->call('muat');
    }

    private function terbit(User $user, string $slug = 'raka-sinta'): Undangan
    {
        return Undangan::factory()->terbit()->create(['user_id' => $user->id, 'slug' => $slug, 'judul' => 'Raka & Sinta', 'lokasi_ringkas' => 'Gedung Sasana']);
    }

    private function buat(User $user, array $atribut = []): TemplatePesan
    {
        return TemplatePesan::create(array_merge(['user_id' => $user->id, 'nama' => 'Milikku', 'kanal' => 'whatsapp', 'isi' => 'Halo {nama}, buka {link}'], $atribut));
    }

    public function test_tamu_diarahkan_ke_masuk_dan_halaman_asli(): void
    {
        $this->get('/template-pesan')->assertRedirect(route('masuk'));
        $this->actingAs(User::factory()->create())->get('/template-pesan')->assertOk()->assertSeeLivewire(KomponenTemplatePesan::class);
    }

    public function test_menampilkan_template_bawaan_dan_milik_sendiri_saja(): void
    {
        $user = User::factory()->create();
        $this->buat($user, ['nama' => 'Keluarga Besar']);
        $this->buat(User::factory()->create(), ['nama' => 'Rahasia Orang Lain']);

        $this->komponen($user)->assertSee('Keluarga Besar')->assertSee('Undangan Formal')->assertSee('Pengingat RSVP')->assertDontSee('Rahasia Orang Lain');
    }

    public function test_tanpa_undangan_memakai_data_contoh(): void
    {
        $this->komponen(User::factory()->create())->assertSee('Data contoh')->assertSee('Raka & Sinta');
    }

    public function test_undangan_orang_lain_404(): void
    {
        $user = User::factory()->create();
        $lain = $this->terbit(User::factory()->create(), 'milik-lain');

        $this->actingAs($user)->get('/template-pesan?undangan='.$lain->id)->assertNotFound();
        $this->komponen($user)->call('pilih', $lain->id)->assertNotFound();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('undanganId', $lain->id);
    }

    public function test_pratinjau_memakai_data_undangan_dan_nama_tamu_contoh(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);

        $c = $this->komponen($user, $u->id)->set('pvNama', 'Bu Rina')->call('pratinjau', 'b-formal');
        $c->assertSee('Kepada Yth. Bu Rina,')->assertSee('Gedung Sasana')->assertSee(route('undangan.publik', 'raka-sinta'));
    }

    public function test_buat_template_valid(): void
    {
        $user = User::factory()->create();

        $this->komponen($user)->call('bukaBaru')->assertSet('dialogForm', true)
            ->set('mNama', '  Undangan   Keluarga ')->set('mIsi', 'Halo {nama}, ini {link}')
            ->call('simpan')->assertHasNoErrors()->assertSet('dialogForm', false);

        $this->assertDatabaseHas('template_pesan', ['user_id' => $user->id, 'nama' => 'Undangan Keluarga', 'kanal' => 'whatsapp', 'subjek' => null, 'isi' => 'Halo {nama}, ini {link}']);
    }

    public function test_buat_template_email_butuh_subjek(): void
    {
        $user = User::factory()->create();

        $c = $this->komponen($user)->call('bukaBaru')->call('setKanal', 'email')->assertSet('mSubjek', 'Undangan: {judul}');
        $c->set('mNama', 'Surel')->set('mIsi', 'Isi {link}')->set('mSubjek', '')->call('simpan')->assertHasErrors('mSubjek');
        $c->set('mSubjek', 'Halo {nama}')->call('simpan')->assertHasNoErrors();
        $this->assertDatabaseHas('template_pesan', ['nama' => 'Surel', 'kanal' => 'email', 'subjek' => 'Halo {nama}']);
    }

    public function test_validasi_gagal(): void
    {
        $user = User::factory()->create();
        $this->buat($user, ['nama' => 'Sudah Ada']);

        $c = $this->komponen($user)->call('bukaBaru');
        $c->call('simpan')->assertHasErrors(['mNama' => 'required', 'mIsi' => 'required']);
        $c->set('mNama', str_repeat('n', PesanUndangan::MAKS_NAMA + 1))->set('mIsi', 'x')->call('simpan')->assertHasErrors(['mNama' => 'max']);
        $c->set('mNama', 'Ok')->set('mIsi', str_repeat('i', PesanUndangan::MAKS_ISI + 1))->call('simpan')->assertHasErrors(['mIsi' => 'max']);
        $c->set('mIsi', 'x')->set('mNama', 'sudah ada')->call('simpan')->assertHasErrors('mNama');
        $c->set('mNama', 'Ok')->set('mKanal', 'telegram')->call('simpan')->assertHasErrors('mKanal');
        $this->assertSame(1, TemplatePesan::count());
    }

    public function test_batas_jumlah_template_milik_pengguna(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < PesanUndangan::MAKS_TEMPLATE; $i++) {
            $this->buat($user, ['nama' => 'T'.$i]);
        }

        $this->komponen($user)->call('bukaBaru')->set('mNama', 'Berlebih')->set('mIsi', 'x')->call('simpan')->assertHasErrors('mNama');
        $this->assertSame(PesanUndangan::MAKS_TEMPLATE, TemplatePesan::where('user_id', $user->id)->count());

        // Pengguna lain tidak terpengaruh batas.
        $this->komponen(User::factory()->create())->call('bukaBaru')->set('mNama', 'Berlebih')->set('mIsi', 'x')->call('simpan')->assertHasNoErrors();
    }

    public function test_simpan_dibatasi_laju(): void
    {
        $user = User::factory()->create();
        RateLimiter::clear('template-pesan-simpan:'.$user->id);

        $c = $this->komponen($user)->call('bukaBaru');
        for ($i = 0; $i < 31; $i++) {
            $c->set('mNama', '')->call('simpan');
        }
        $c->assertHasErrors('mNama');
        $this->assertStringContainsString('Terlalu banyak', $c->errors()->first('mNama'));
    }

    public function test_edit_template_sendiri(): void
    {
        $user = User::factory()->create();
        $t = $this->buat($user);

        $this->komponen($user)->call('bukaEdit', 'u-'.$t->id)->assertSet('mNama', 'Milikku')->assertSet('mIsi', 'Halo {nama}, buka {link}')
            ->set('mNama', 'Diubah')->call('simpan')->assertHasNoErrors();

        $this->assertSame('Diubah', $t->fresh()->nama);
        $this->assertSame(1, TemplatePesan::count());
    }

    public function test_edit_hapus_template_orang_lain_dan_id_ngawur_404(): void
    {
        $user = User::factory()->create();
        $lain = $this->buat(User::factory()->create(), ['nama' => 'Orang Lain']);

        $this->komponen($user)->call('bukaEdit', 'u-'.$lain->id)->assertNotFound();
        $this->komponen($user)->call('mintaHapus', 'u-'.$lain->id)->assertNotFound();
        $this->komponen($user)->call('bukaEdit', 'b-formal')->assertNotFound();
        $this->komponen($user)->call('bukaEdit', 'u-abc')->assertNotFound();
        $this->komponen($user)->call('hapus')->assertNotFound();
        $this->assertSame('Orang Lain', $lain->fresh()->nama);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->set('mId', $lain->id);
    }

    public function test_simpan_tidak_menimpa_template_orang_lain_lewat_id_form(): void
    {
        $user = User::factory()->create();
        $lain = $this->buat(User::factory()->create(), ['nama' => 'Orang Lain']);

        $this->expectException(CannotUpdateLockedPropertyException::class);
        $this->komponen($user)->call('bukaBaru')->set('mId', $lain->id);
    }

    public function test_duplikat_template_bawaan(): void
    {
        $user = User::factory()->create();

        $c = $this->komponen($user)->call('duplikat', 'b-santai')->assertSet('dialogForm', true)->assertSet('mId', null)->assertSet('mNama', 'Undangan Santai (salinan)');
        $c->call('simpan')->assertHasNoErrors();
        $this->assertDatabaseHas('template_pesan', ['user_id' => $user->id, 'nama' => 'Undangan Santai (salinan)']);
        $this->komponen($user)->call('duplikat', 'b-tidak-ada')->assertNotFound();
    }

    public function test_hapus_template_mengembalikan_default_dan_tamu(): void
    {
        $user = User::factory()->create();
        $u = $this->terbit($user);
        $t = $this->buat($user);
        $id = 'u-'.$t->id;
        $tamu = Tamu::factory()->create(['undangan_id' => $u->id, 'template_pesan' => $id]);
        $tamuLain = Tamu::factory()->create(['undangan_id' => $this->terbit(User::factory()->create(), 'milik-lain')->id, 'template_pesan' => $id]);
        app(PesanUndangan::class)->simpanPengaturan($user, $id, 'islam');

        $this->komponen($user)->call('mintaHapus', $id)->assertSet('hapusId', $id)->call('hapus')->assertSet('hapusId', null);

        $this->assertDatabaseMissing('template_pesan', ['id' => $t->id]);
        $this->assertSame(['template' => 'b-formal', 'salam' => 'islam'], app(PesanUndangan::class)->pengaturan($user));
        $this->assertNull($tamu->fresh()->template_pesan);
        $this->assertSame($id, $tamuLain->fresh()->template_pesan);
    }

    public function test_batal_hapus(): void
    {
        $user = User::factory()->create();
        $t = $this->buat($user);

        $this->komponen($user)->call('mintaHapus', 'u-'.$t->id)->call('batalHapus')->assertSet('hapusId', null);
        $this->assertDatabaseHas('template_pesan', ['id' => $t->id]);
    }

    public function test_jadikan_default_hanya_template_whatsapp_sendiri_atau_bawaan(): void
    {
        $user = User::factory()->create();
        $wa = $this->buat($user);
        $email = $this->buat($user, ['nama' => 'Surel', 'kanal' => 'email', 'subjek' => 's']);
        $lain = $this->buat(User::factory()->create(), ['nama' => 'Orang Lain']);

        $this->komponen($user)->call('jadikanDefault', 'u-'.$wa->id);
        $this->assertSame('u-'.$wa->id, app(PesanUndangan::class)->pengaturan($user)['template']);

        $this->komponen($user)->call('jadikanDefault', 'b-santai');
        $this->assertSame('b-santai', app(PesanUndangan::class)->pengaturan($user)['template']);

        $this->komponen($user)->call('jadikanDefault', 'u-'.$email->id);
        $this->assertSame('b-santai', app(PesanUndangan::class)->pengaturan($user)['template']);

        $this->komponen($user)->call('jadikanDefault', 'u-'.$lain->id)->assertNotFound();
        $this->assertSame('b-santai', app(PesanUndangan::class)->pengaturan($user)['template']);
    }

    public function test_default_pengguna_terpisah(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        app(PesanUndangan::class)->simpanPengaturan($a, 'b-santai', 'hindu');

        $this->assertSame('b-santai', app(PesanUndangan::class)->pengaturan($a)['template']);
        $this->assertSame(['template' => 'b-formal', 'salam' => 'umum'], app(PesanUndangan::class)->pengaturan($b));
    }

    public function test_ubah_template_default_ke_email_melepas_default(): void
    {
        $user = User::factory()->create();
        $t = $this->buat($user);
        app(PesanUndangan::class)->simpanPengaturan($user, 'u-'.$t->id);

        $this->komponen($user)->call('bukaEdit', 'u-'.$t->id)->call('setKanal', 'email')->call('simpan')->assertHasNoErrors();
        $this->assertSame('b-formal', app(PesanUndangan::class)->pengaturan($user)['template']);
    }

    public function test_cari_dan_filter_kanal(): void
    {
        $user = User::factory()->create();
        $this->buat($user, ['nama' => 'Teman Kantor', 'isi' => 'Hai rekan {link}']);
        $this->buat($user, ['nama' => 'Surel Resmi', 'kanal' => 'email', 'subjek' => 's', 'isi' => 'Yth {nama} {link}']);

        $c = $this->komponen($user);
        $c->set('q', 'rekan')->assertSee('Teman Kantor')->assertDontSee('Surel Resmi')->assertDontSee('Undangan Santai');
        $c->set('q', '')->set('kanal', 'email')->assertSee('Surel Resmi')->assertDontSee('Teman Kantor');
        $c->set('q', 'tidak-ada-apapun')->assertSee('Tidak ada template yang cocok');
        $c->call('resetFilter')->assertSet('kanal', 'semua')->assertSee('Teman Kantor');
    }

    public function test_sisip_salam_menambahkan_pembuka_tanpa_menumpuk(): void
    {
        $user = User::factory()->create();

        $c = $this->komponen($user)->call('bukaBaru')->set('mIsi', 'Halo {nama}');
        $c->call('sisipSalam', 'islam')->assertSet('mIsi', "Assalamu'alaikum Warahmatullahi Wabarakatuh,\n\nHalo {nama}");
        $c->call('sisipSalam', 'hindu')->assertSet('mIsi', "Om Swastyastu,\n\nHalo {nama}");
        $c->call('sisipSalam', 'umum')->call('sisipSalam', 'tidak-ada')->assertSet('mIsi', "Om Swastyastu,\n\nHalo {nama}");
    }

    public function test_peringatan_variabel_tidak_dikenal_dan_tanpa_link(): void
    {
        $user = User::factory()->create();

        $this->komponen($user)->call('bukaBaru')->set('mIsi', 'Halo {namma}')->assertSee('Variabel tidak dikenal')->assertSee('{namma}')->assertSee('Tambahkan {link}');
    }

    public function test_isi_template_di_escape_di_halaman(): void
    {
        $user = User::factory()->create();
        $this->buat($user, ['nama' => '<b>tebal</b>', 'isi' => '<script>alert(1)</script> {link}']);

        $this->komponen($user)->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('<b>tebal</b>', false)->assertSee('&lt;b&gt;tebal&lt;/b&gt;', false);
    }

    public function test_variabel_diisi_sekali_jalan_tanpa_menyuntik_variabel_lain(): void
    {
        $u = new Undangan(['judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'lokasi_ringkas' => 'Jakarta']);

        $hasil = PesanUndangan::isi('{nama} | {judul} | {tempat} | {link}', $u, 'Budi {link}', 'umum', 'abc1234567');

        $this->assertStringStartsWith('Budi {link} | Raka & Sinta | Jakarta | '.route('undangan.publik', 'raka-sinta').'?to=abc1234567', $hasil);
    }

    public function test_ringkasan_profil_menghitung_template_milik_sendiri(): void
    {
        $user = User::factory()->create();
        $this->buat($user);
        $this->buat($user, ['nama' => 'Kedua']);
        $this->buat(User::factory()->create());

        $this->assertSame(2, app(RingkasanAkun::class)->jumlahTemplatePesan($user));
    }
}
