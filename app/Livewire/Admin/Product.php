<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\ItemPesanan;
use App\Models\Paket;
use App\Models\User;
use App\Services\SinkronKuotaPaket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Admin > Product: katalog paket langganan (harga, masa aktif, kuota, fitur, status, urutan). */
class Product extends Component
{
    use HakAdminKatalog;

    /** Dipakai sistem (kuota, masa aktif, tagihan): tidak boleh diarsipkan atau dihapus. */
    public const SISTEM = ['gratis', 'premium'];

    private const MAKS_FITUR = 20;

    public string $q = '';

    /** semua | aktif | draf | arsip */
    public string $fs = 'semua';

    public bool $dialogForm = false;

    #[Locked]
    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $f = [];

    /** @var list<string> */
    public array $fitur = [];

    public string $fiturBaru = '';

    public bool $dialogKonfirmasi = false;

    #[Locked]
    public ?int $aksiId = null;

    /** hapus | arsip */
    #[Locked]
    public string $aksiJenis = 'hapus';

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->f = $this->formKosong();
    }

    private function formKosong(): array
    {
        return ['nama' => '', 'sku' => '', 'harga' => '', 'harga_coret' => '', 'durasi_bulan' => '12', 'kuota_undangan' => '1', 'kuota_tamu' => '', 'kuota_foto' => '3', 'tagline' => '', 'status' => Paket::DRAF, 'populer' => false];
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
    }

    public function atur(string $status): void
    {
        $this->fs = in_array($status, ['semua', Paket::AKTIF, Paket::DRAF, Paket::ARSIP], true) ? $status : 'semua';
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'fs');
    }

    // ------------------------------------------------------------------ form

    public function bukaBaru(): void
    {
        $this->pastikanAdmin();
        $this->resetErrorBag();
        $this->editId = null;
        $this->f = $this->formKosong();
        $this->fitur = [];
        $this->fiturBaru = '';
        $this->dialogForm = true;
    }

    public function bukaEdit(int $id): void
    {
        $this->pastikanAdmin();
        $p = Paket::findOrFail($id);
        $this->resetErrorBag();
        $this->editId = $p->id;
        $this->f = [
            'nama' => $p->nama, 'sku' => (string) $p->sku, 'harga' => (string) $p->harga, 'harga_coret' => $p->harga_coret ? (string) $p->harga_coret : '',
            'durasi_bulan' => (string) $p->durasi_bulan, 'kuota_undangan' => (string) $p->kuota_undangan, 'kuota_tamu' => $p->kuota_tamu === null ? '' : (string) $p->kuota_tamu,
            'kuota_foto' => (string) $p->kuota_foto, 'tagline' => (string) $p->tagline, 'status' => $p->status, 'populer' => (bool) $p->populer,
        ];
        $this->fitur = array_values(array_map('strval', $p->fitur ?? []));
        $this->fiturBaru = '';
        $this->dialogForm = true;
    }

    public function tambahFitur(): void
    {
        $this->pastikanAdmin();
        $t = trim(mb_substr($this->fiturBaru, 0, 80));
        if ($t === '' || count($this->fitur) >= self::MAKS_FITUR) {
            return;
        }
        $this->fitur[] = $t;
        $this->fiturBaru = '';
    }

    public function hapusFitur(int $i): void
    {
        $this->pastikanAdmin();
        unset($this->fitur[$i]);
        $this->fitur = array_values($this->fitur);
    }

    public function geserFitur(int $i, int $arah): void
    {
        $this->pastikanAdmin();
        $j = $i + ($arah < 0 ? -1 : 1);
        if (! isset($this->fitur[$i], $this->fitur[$j])) {
            return;
        }
        [$this->fitur[$i], $this->fitur[$j]] = [$this->fitur[$j], $this->fitur[$i]];
    }

    private function sistem(?Paket $p): bool
    {
        return $p !== null && in_array($p->kode, self::SISTEM, true);
    }

    public function simpan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $paket = $this->editId ? Paket::findOrFail($this->editId) : null;
        $sistem = $this->sistem($paket);

        foreach (['harga', 'harga_coret', 'durasi_bulan', 'kuota_undangan', 'kuota_tamu', 'kuota_foto'] as $k) {
            $this->f[$k] = $this->angka($this->f[$k] ?? '');
        }
        $this->f['nama'] = trim((string) ($this->f['nama'] ?? ''));
        $this->f['sku'] = strtoupper(trim((string) ($this->f['sku'] ?? '')));
        $this->f['tagline'] = trim((string) ($this->f['tagline'] ?? ''));
        $this->fitur = array_values(array_filter(array_map(fn ($t) => trim((string) $t), $this->fitur), fn ($t) => $t !== ''));
        if ($sistem) {
            $this->f['status'] = Paket::AKTIF;
        }

        $this->validate([
            'f.nama' => ['required', 'string', 'min:2', 'max:60'],
            'f.sku' => ['nullable', 'regex:/^[A-Z0-9-]{2,20}$/'],
            'f.harga' => ['required', 'integer', 'min:0', 'max:100000000'],
            'f.harga_coret' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'f.durasi_bulan' => ['required', 'integer', 'min:0', 'max:120'],
            'f.kuota_undangan' => ['required', 'integer', 'min:1', 'max:100'],
            'f.kuota_tamu' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'f.kuota_foto' => ['required', 'integer', 'min:0', 'max:200'],
            'f.tagline' => ['nullable', 'string', 'max:80'],
            'f.status' => ['required', 'in:aktif,draf,arsip'],
            'fitur' => ['required', 'array', 'min:1', 'max:'.self::MAKS_FITUR],
            'fitur.*' => ['string', 'max:80'],
        ], [
            'f.sku.regex' => 'Kode SKU 2-20 karakter: huruf besar, angka, atau strip.',
            'fitur.required' => 'Tambahkan minimal 1 fitur.',
            'fitur.min' => 'Tambahkan minimal 1 fitur.',
        ], [
            'f.nama' => 'Nama paket', 'f.sku' => 'Kode SKU', 'f.harga' => 'Harga', 'f.harga_coret' => 'Harga coret', 'f.durasi_bulan' => 'Masa aktif',
            'f.kuota_undangan' => 'Kuota undangan', 'f.kuota_tamu' => 'Kuota tamu', 'f.kuota_foto' => 'Maks foto', 'f.tagline' => 'Tagline', 'f.status' => 'Status', 'fitur.*' => 'Fitur',
        ]);

        $galat = [];
        $ada = Paket::query()->when($paket, fn ($q) => $q->whereKeyNot($paket->id));
        if ((clone $ada)->whereRaw('lower(nama) = ?', [mb_strtolower($this->f['nama'])])->exists()) {
            $galat['f.nama'] = 'Nama ini sudah dipakai paket lain.';
        }
        if ($this->f['sku'] !== '' && (clone $ada)->where('sku', $this->f['sku'])->exists()) {
            $galat['f.sku'] = 'Kode SKU ini sudah dipakai paket lain.';
        }
        if ($paket?->kode === 'gratis' && (int) $this->f['harga'] !== 0) {
            $galat['f.harga'] = 'Paket Gratis harus Rp 0.';
        }
        if ((int) $this->f['harga_coret'] > 0 && (int) $this->f['harga_coret'] <= (int) $this->f['harga']) {
            $galat['f.harga_coret'] = 'Harga coret harus lebih besar dari harga.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $status = $this->f['status'];
        $data = [
            'nama' => $this->f['nama'],
            'sku' => $this->f['sku'] === '' ? null : $this->f['sku'],
            'tagline' => $this->f['tagline'] === '' ? null : $this->f['tagline'],
            'harga' => (int) $this->f['harga'],
            'harga_coret' => (int) $this->f['harga_coret'],
            'durasi_bulan' => (int) $this->f['durasi_bulan'],
            'kuota_undangan' => (int) $this->f['kuota_undangan'],
            'kuota_tamu' => $this->f['kuota_tamu'] === '' ? null : (int) $this->f['kuota_tamu'],
            'kuota_foto' => (int) $this->f['kuota_foto'],
            'fitur' => $this->fitur,
            'status' => $status,
            'populer' => $status !== Paket::ARSIP && (bool) $this->f['populer'],
        ];

        DB::transaction(function () use (&$paket, $data) {
            if ($paket) {
                $paket->update($data);
            } else {
                $paket = Paket::create($data + ['kode' => $this->kodeBaru($data['nama']), 'urutan' => (int) Paket::max('urutan') + 1]);
            }
            if ($data['populer']) {
                Paket::whereKeyNot($paket->id)->where('populer', true)->update(['populer' => false]);
            }
        });
        app(SinkronKuotaPaket::class)->sinkronkan();

        $this->dialogForm = false;
        $this->dispatch('toast', pesan: 'Paket '.$data['nama'].' disimpan.', tipe: 'sukses');
    }

    /** Kode sistem (slug, dipakai users.paket dan voucher): dibuat dari nama, unik, dan tidak berubah lagi. */
    private function kodeBaru(string $nama): string
    {
        $dasar = Str::slug(Str::limit($nama, 24, ''), '-') ?: 'paket';
        $kode = $dasar;
        for ($n = 2; Paket::where('kode', $kode)->exists(); $n++) {
            $kode = $dasar.'-'.$n;
        }

        return $kode;
    }

    // ------------------------------------------------------------------ aksi baris

    public function duplikat(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $p = Paket::findOrFail($id);
        $nama = $p->nama.' (salinan)';
        for ($n = 2; Paket::whereRaw('lower(nama) = ?', [mb_strtolower($nama)])->exists(); $n++) {
            $nama = $p->nama.' (salinan '.$n.')';
        }
        $salinan = Paket::create([
            'kode' => $this->kodeBaru($nama), 'nama' => $nama, 'sku' => null, 'tagline' => $p->tagline, 'harga' => $p->harga, 'harga_coret' => $p->harga_coret,
            'durasi_bulan' => $p->durasi_bulan, 'kuota_undangan' => $p->kuota_undangan, 'kuota_tamu' => $p->kuota_tamu, 'kuota_foto' => $p->kuota_foto,
            'fitur' => $p->fitur, 'populer' => false, 'status' => Paket::DRAF, 'urutan' => (int) Paket::max('urutan') + 1,
        ]);
        app(SinkronKuotaPaket::class)->sinkronkan();
        $this->dispatch('toast', pesan: 'Paket diduplikat sebagai draf: '.$salinan->nama.'.', tipe: 'sukses');
    }

    public function geser(int $id, int $arah): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $daftar = Paket::orderBy('urutan')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $daftar, true);
        $j = $i === false ? false : $i + ($arah < 0 ? -1 : 1);
        if ($i === false || ! isset($daftar[$j])) {
            return;
        }
        [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];
        DB::transaction(function () use ($daftar) {
            foreach ($daftar as $urut => $paketId) {
                Paket::whereKey($paketId)->update(['urutan' => $urut + 1]);
            }
        });
        app(SinkronKuotaPaket::class)->sinkronkan();
    }

    public function populer(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $p = Paket::findOrFail($id);
        if ($p->status === Paket::ARSIP) {
            $this->dispatch('toast', pesan: 'Pulihkan paket ini dulu sebelum menandainya populer.', tipe: 'galat');

            return;
        }
        $nyala = ! $p->populer;
        DB::transaction(function () use ($p, $nyala) {
            if ($nyala) {
                Paket::whereKeyNot($p->id)->where('populer', true)->update(['populer' => false]);
            }
            $p->update(['populer' => $nyala]);
        });
        $this->dispatch('toast', pesan: $nyala ? $p->nama.' ditandai sebagai paket populer.' : 'Tanda populer dilepas dari '.$p->nama.'.', tipe: 'sukses');
    }

    public function pulihkan(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $p = Paket::findOrFail($id);
        if ($p->status === Paket::ARSIP) {
            $p->update(['status' => Paket::DRAF]);
            app(SinkronKuotaPaket::class)->sinkronkan();
            $this->dispatch('toast', pesan: 'Paket '.$p->nama.' dipulihkan sebagai draf.', tipe: 'sukses');
        }
    }

    public function tanyaArsip(int $id): void
    {
        $this->pastikanAdmin();
        $p = Paket::findOrFail($id);
        if ($this->sistem($p)) {
            $this->dispatch('toast', pesan: 'Paket sistem tidak bisa diarsipkan.', tipe: 'galat');

            return;
        }
        $this->aksiId = $p->id;
        $this->aksiJenis = 'arsip';
        $this->dialogKonfirmasi = true;
    }

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $p = Paket::findOrFail($id);
        if ($this->sistem($p)) {
            $this->dispatch('toast', pesan: 'Paket sistem tidak bisa dihapus.', tipe: 'galat');

            return;
        }
        if ($this->dipakai($p)) {
            $this->dispatch('toast', pesan: 'Paket ini sudah dipakai pengguna atau pesanan. Arsipkan saja agar riwayat tetap utuh.', tipe: 'galat');

            return;
        }
        $this->aksiId = $p->id;
        $this->aksiJenis = 'hapus';
        $this->dialogKonfirmasi = true;
    }

    public function jalankanAksi(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $p = $this->aksiId ? Paket::find($this->aksiId) : null;
        $this->dialogKonfirmasi = false;
        if (! $p || $this->sistem($p)) {
            return;
        }
        if ($this->aksiJenis === 'arsip') {
            $p->update(['status' => Paket::ARSIP, 'populer' => false]);
            $pesan = 'Paket '.$p->nama.' diarsipkan. Tidak lagi tampil di halaman harga.';
        } else {
            if ($this->dipakai($p)) {
                $this->dispatch('toast', pesan: 'Paket ini sudah dipakai pengguna atau pesanan. Arsipkan saja.', tipe: 'galat');

                return;
            }
            $p->delete();
            $pesan = 'Paket '.$p->nama.' dihapus.';
        }
        app(SinkronKuotaPaket::class)->sinkronkan();
        $this->dispatch('toast', pesan: $pesan, tipe: 'sukses');
    }

    private function dipakai(Paket $p): bool
    {
        return User::where('paket', $p->kode)->exists()
            || ItemPesanan::where('tipe', ItemPesanan::PAKET)->where('kode', $p->kode)->exists();
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $this->pastikanAdmin();
        $semua = Paket::orderBy('urutan')->orderBy('id')->get();
        $hitung = ['semua' => $semua->count(), 'aktif' => 0, 'draf' => 0, 'arsip' => 0];
        foreach ($semua as $p) {
            $hitung[$p->status] = ($hitung[$p->status] ?? 0) + 1;
        }
        $pengguna = User::whereIn('paket', $semua->pluck('kode'))->groupBy('paket')->selectRaw('paket, count(*) as n')->pluck('n', 'paket');
        $q = mb_strtolower(trim($this->q));
        $baris = $semua->filter(fn (Paket $p) => ($this->fs === 'semua' || $p->status === $this->fs)
            && ($q === '' || str_contains(mb_strtolower($p->nama.' '.$p->kode.' '.$p->sku.' '.$p->tagline), $q)))->values();
        $tertinggi = $semua->where('status', Paket::AKTIF)->sortByDesc('harga')->first();

        return view('livewire.admin.product.index', [
            'semua' => $semua,
            'baris' => $baris,
            'kartu' => $semua->where('status', '!=', Paket::ARSIP)->values(),
            'hitung' => $hitung,
            'tertinggi' => $tertinggi,
            'populerPaket' => $semua->firstWhere('populer', true),
            'pengguna' => $pengguna,
            'editPaket' => $this->editId ? $semua->firstWhere('id', $this->editId) : null,
            'aksiPaket' => $this->aksiId ? $semua->firstWhere('id', $this->aksiId) : null,
        ])->layout('layouts::app', ['judul' => 'Product', 'sub' => 'Penjualan', 'admin' => true]);
    }
}
