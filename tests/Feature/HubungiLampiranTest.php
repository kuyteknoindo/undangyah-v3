<?php

namespace Tests\Feature;

use App\Livewire\HubungiLayanan;
use App\Models\TiketBantuan;
use App\Models\TiketLampiran;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class HubungiLampiranTest extends TestCase
{
    use RefreshDatabase;

    private function isi($c)
    {
        return $c->call('pilihKategori', 'akun')->set('subjek', 'Tidak bisa masuk')->set('pesan', 'Saya tidak bisa masuk sejak kemarin sore.');
    }

    public function test_lampiran_valid_tersimpan_di_disk_privat_dan_tampil_namanya(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $c = Livewire::actingAs($user)->test(HubungiLayanan::class)
            ->set('berkasBaru', [UploadedFile::fake()->image('bukti transfer.png'), UploadedFile::fake()->create('rincian.pdf', 100, 'application/pdf')])
            ->assertSet('galatBerkas', '')->assertSee('bukti transfer.png')->assertSee('rincian.pdf');
        $this->isi($c)->call('kirim')->assertHasNoErrors()->assertSee('Pesan terkirim');

        $tiket = TiketBantuan::with('lampiran')->first();
        $this->assertCount(2, $tiket->lampiran);
        foreach ($tiket->lampiran as $l) {
            Storage::disk('local')->assertExists($l->path);
            $this->assertStringStartsWith('tiket-lampiran/'.$tiket->id.'/', $l->path);
            $this->assertStringNotContainsString('bukti', $l->path);
        }
        $this->assertSame(['bukti transfer.png', 'rincian.pdf'], $tiket->lampiran->pluck('nama')->sort()->values()->all());

        Livewire::actingAs($user)->test(HubungiLayanan::class)->call('ubahTiket', $tiket->id)->assertSee('Lampiran: ');
    }

    public function test_format_ukuran_dan_jumlah_dibatasi(): void
    {
        Storage::fake('local');
        $c = Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)
            ->set('berkasBaru', [UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')])
            ->assertSet('galatBerkas', '“virus.exe” ditolak: format harus PNG, JPG, WebP, atau PDF.')
            ->set('berkasBaru', [UploadedFile::fake()->create('besar.pdf', 3000, 'application/pdf')])
            ->assertSet('galatBerkas', '“besar.pdf” ditolak: ukuran melebihi 2 MB.');
        $c->set('berkasBaru', [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 11, 'application/pdf'), UploadedFile::fake()->create('c.pdf', 12, 'application/pdf'), UploadedFile::fake()->create('d.pdf', 13, 'application/pdf')])
            ->assertSet('galatBerkas', 'Maksimal 3 berkas.')
            ->assertSet('lampiran', fn ($l) => count($l) === 3);
        $this->assertSame(0, TiketLampiran::count());
    }

    public function test_hapus_lampiran_dan_lampiran_terkunci_dari_klien(): void
    {
        Storage::fake('local');
        $c = Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)
            ->set('berkasBaru', [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), UploadedFile::fake()->create('b.pdf', 11, 'application/pdf')])
            ->call('hapusLampiran', 0)->assertSet('lampiran', fn ($l) => count($l) === 1)
            ->call('hapusLampiran', 99)->assertSet('lampiran', fn ($l) => count($l) === 1);
        $this->isi($c)->call('kirim');
        $this->assertSame(['b.pdf'], TiketLampiran::pluck('nama')->all());

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)->set('lampiran', ['x']);
    }

    public function test_lampiran_ikut_terhapus_bersama_tiket_dan_berkasnya(): void
    {
        Storage::fake('local');
        $c = Livewire::actingAs(User::factory()->create())->test(HubungiLayanan::class)
            ->set('berkasBaru', [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]);
        $this->isi($c)->call('kirim');
        $l = TiketLampiran::first();
        Storage::disk('local')->assertExists($l->path);

        $l->delete();
        Storage::disk('local')->assertMissing($l->path);
    }
}
