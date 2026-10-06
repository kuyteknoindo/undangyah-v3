<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Livewire\Concerns\MintaKonfirmasiSandi;
use App\Models\AddOn;
use App\Models\AddonPengguna;
use App\Models\ItemPesanan;
use App\Models\LogAksiAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Admin > Addons Product: fitur tambahan yang dibeli tanpa ganti paket. */
class AddOns extends Component
{
    use HakAdminKatalog;
    use MintaKonfirmasiSandi;

    /** Dibaca KonfirmasiPesanan/KuotaUndangan lewat efeknya: tidak boleh dihapus, jenis efeknya terkunci. */
    public const SISTEM = ['kuota-undangan', 'perpanjang-masa-aktif'];

    public const SATUAN_TAHUN = ' / tahun';

    /** Kunci efek yang dikelola form; kunci lain pada baris lama dibiarkan. */
    private const KUNCI_EFEK = ['kuota_undangan', 'perpanjang_bulan', 'fitur'];

    public string $q = '';

    /** semua | aktif | nonaktif */
    public string $fs = 'semua';

    public bool $dialogForm = false;

    #[Locked]
    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $f = [];

    public bool $dialogHapus = false;

    #[Locked]
    public ?int $hapusId = null;

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->f = $this->formKosong();
    }

    private function formKosong(): array
    {
        return ['nama' => '', 'deskripsi' => '', 'harga' => '', 'tahunan' => false, 'multi' => false, 'butuh_premium' => false, 'termasuk_premium' => false, 'aktif' => true, 'efek_jenis' => 'tidak', 'efek_nilai' => ''];
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
    }

    public function atur(string $status): void
    {
        $this->fs = in_array($status, ['semua', 'aktif', 'nonaktif'], true) ? $status : 'semua';
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'fs');
    }

    private function sistem(?AddOn $a): bool
    {
        return $a !== null && in_array($a->kode, self::SISTEM, true);
    }

    // ------------------------------------------------------------------ form

    public function bukaBaru(): void
    {
        $this->pastikanAdmin();
        $this->resetErrorBag();
        $this->editId = null;
        $this->f = $this->formKosong();
        $this->dialogForm = true;
    }

    public function bukaEdit(int $id): void
    {
        $this->pastikanAdmin();
        $a = AddOn::findOrFail($id);
        $this->resetErrorBag();
        $efek = (array) ($a->efek ?? []);
        $jenis = 'tidak';
        $nilai = '';
        foreach (self::KUNCI_EFEK as $k) {
            if (isset($efek[$k]) && $efek[$k] !== '') {
                $jenis = $k;
                $nilai = (string) $efek[$k];
                break;
            }
        }
        $this->editId = $a->id;
        $this->f = [
            'nama' => $a->nama, 'deskripsi' => (string) $a->deskripsi, 'harga' => (string) $a->harga, 'tahunan' => $a->satuan === self::SATUAN_TAHUN,
            'multi' => (bool) $a->multi, 'butuh_premium' => (bool) $a->butuh_premium, 'termasuk_premium' => (bool) $a->termasuk_premium, 'aktif' => (bool) $a->aktif,
            'efek_jenis' => $jenis, 'efek_nilai' => $nilai,
        ];
        $this->dialogForm = true;
    }

    public function simpan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $addon = $this->editId ? AddOn::findOrFail($this->editId) : null;
        $sistem = $this->sistem($addon);

        $this->f['nama'] = trim((string) ($this->f['nama'] ?? ''));
        $this->f['deskripsi'] = trim((string) ($this->f['deskripsi'] ?? ''));
        $this->f['harga'] = $this->angka($this->f['harga'] ?? '');
        $jenis = in_array($this->f['efek_jenis'] ?? '', ['tidak', ...self::KUNCI_EFEK], true) ? $this->f['efek_jenis'] : 'tidak';
        if ($sistem) {
            // Jenis efek add-on sistem tidak boleh berubah: sistem bergantung padanya.
            $jenis = $addon->kode === 'kuota-undangan' ? 'kuota_undangan' : 'perpanjang_bulan';
        }
        $this->f['efek_jenis'] = $jenis;
        $this->f['efek_nilai'] = $jenis === 'fitur' ? strtolower(trim((string) $this->f['efek_nilai'])) : $this->angka($this->f['efek_nilai'] ?? '');

        $aturan = [
            'f.nama' => ['required', 'string', 'min:3', 'max:80'],
            'f.deskripsi' => ['required', 'string', 'max:100'],
            'f.harga' => ['required', 'integer', 'min:1', 'max:100000000'],
        ];
        if ($jenis === 'kuota_undangan') {
            $aturan['f.efek_nilai'] = ['required', 'integer', 'min:1', 'max:100'];
        } elseif ($jenis === 'perpanjang_bulan') {
            $aturan['f.efek_nilai'] = ['required', 'integer', 'min:1', 'max:60'];
        } elseif ($jenis === 'fitur') {
            $aturan['f.efek_nilai'] = ['required', 'regex:/^[a-z0-9-]{2,40}$/'];
        }
        $this->validate($aturan, [
            'f.harga.min' => 'Harga harus lebih dari Rp 0.',
            'f.efek_nilai.regex' => 'Kode fitur 2-40 karakter: huruf kecil, angka, atau strip.',
        ], ['f.nama' => 'Nama add-on', 'f.deskripsi' => 'Deskripsi', 'f.harga' => 'Harga', 'f.efek_nilai' => 'Nilai efek']);

        $galat = [];
        if (AddOn::query()->when($addon, fn ($q) => $q->whereKeyNot($addon->id))->whereRaw('lower(nama) = ?', [mb_strtolower($this->f['nama'])])->exists()) {
            $galat['f.nama'] = 'Nama ini sudah dipakai add-on lain.';
        }
        if (! empty($this->f['butuh_premium']) && ! empty($this->f['termasuk_premium'])) {
            $galat['f.aturan'] = 'Tidak bisa sekaligus "Butuh paket Premium" dan "Sudah termasuk Premium": add-on itu tidak akan pernah bisa dibeli.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $efek = $addon ? array_diff_key((array) ($addon->efek ?? []), array_flip(self::KUNCI_EFEK)) : [];
        if ($jenis !== 'tidak') {
            $efek[$jenis] = $jenis === 'fitur' ? $this->f['efek_nilai'] : (int) $this->f['efek_nilai'];
        }
        $data = [
            'nama' => $this->f['nama'],
            'deskripsi' => $this->f['deskripsi'],
            'harga' => (int) $this->f['harga'],
            'satuan' => ! empty($this->f['tahunan']) ? self::SATUAN_TAHUN : null,
            'multi' => (bool) $this->f['multi'],
            'butuh_premium' => (bool) $this->f['butuh_premium'],
            'termasuk_premium' => (bool) $this->f['termasuk_premium'],
            'aktif' => (bool) $this->f['aktif'],
            'efek' => $efek === [] ? null : $efek,
        ];
        $hargaLama = $addon?->harga;
        if ($addon) {
            $addon->update($data);
        } else {
            $addon = AddOn::create($data + ['kode' => $this->kodeBaru($data['nama']), 'urutan' => (int) AddOn::max('urutan') + 1]);
        }
        LogAksiAdmin::catat(auth()->user(), $hargaLama === null ? 'addon_buat' : 'addon_ubah', null, [
            'addon' => $data['nama'], 'kode' => $addon->kode, 'aktif' => $data['aktif'],
            'harga' => ['sebelum' => $hargaLama, 'sesudah' => $data['harga']],
        ]);

        $this->dialogForm = false;
        $this->dispatch('toast', pesan: 'Add-on '.$data['nama'].' disimpan.', tipe: 'sukses');
    }

    /** Kode sistem (slug): dipakai keranjang, item pesanan, dan kepemilikan pengguna; tidak berubah setelah dibuat. */
    private function kodeBaru(string $nama): string
    {
        $dasar = Str::slug(Str::limit($nama, 34, ''), '-') ?: 'addon';
        $kode = $dasar;
        for ($n = 2; AddOn::where('kode', $kode)->exists(); $n++) {
            $kode = $dasar.'-'.$n;
        }

        return $kode;
    }

    // ------------------------------------------------------------------ aksi baris

    public function ubahAktif(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $a = AddOn::findOrFail($id);
        $a->update(['aktif' => ! $a->aktif]);
        $this->dispatch('toast', pesan: $a->nama.($a->aktif ? ' diaktifkan.' : ' dinonaktifkan. Tidak tampil di Paket & Billing.'), tipe: 'sukses');
    }

    public function geser(int $id, int $arah): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $daftar = AddOn::orderBy('urutan')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $daftar, true);
        $j = $i === false ? false : $i + ($arah < 0 ? -1 : 1);
        if ($i === false || ! isset($daftar[$j])) {
            return;
        }
        [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];
        DB::transaction(function () use ($daftar) {
            foreach ($daftar as $urut => $addonId) {
                AddOn::whereKey($addonId)->update(['urutan' => $urut + 1]);
            }
        });
    }

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $a = AddOn::findOrFail($id);
        if ($this->sistem($a)) {
            $this->dispatch('toast', pesan: 'Add-on sistem tidak bisa dihapus. Nonaktifkan saja bila perlu.', tipe: 'galat');

            return;
        }
        if ($this->dipakai($a)) {
            $this->dispatch('toast', pesan: 'Add-on ini sudah dipakai pesanan atau pengguna. Nonaktifkan saja agar riwayat tetap utuh.', tipe: 'galat');

            return;
        }
        $this->hapusId = $a->id;
        $this->dialogHapus = true;
    }

    public function hapus(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        if (! $this->butuhSandi('Hapus add-on', 'hapus')) {
            return;
        }
        $a = $this->hapusId ? AddOn::find($this->hapusId) : null;
        $this->dialogHapus = false;
        if (! $a || $this->sistem($a)) {
            return;
        }
        if ($this->dipakai($a)) {
            $this->dispatch('toast', pesan: 'Add-on ini sudah dipakai pesanan atau pengguna. Nonaktifkan saja.', tipe: 'galat');

            return;
        }
        $a->delete();
        LogAksiAdmin::catat(auth()->user(), 'addon_hapus', null, ['addon' => $a->nama, 'kode' => $a->kode]);
        $this->dispatch('toast', pesan: 'Add-on '.$a->nama.' dihapus.', tipe: 'sukses');
    }

    private function dipakai(AddOn $a): bool
    {
        return ItemPesanan::where('tipe', ItemPesanan::ADDON)->where('kode', $a->kode)->exists()
            || AddonPengguna::where('addon_kode', $a->kode)->exists();
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $this->pastikanAdmin();
        $semua = AddOn::orderBy('urutan')->orderBy('id')->get();
        $hitung = ['semua' => $semua->count(), 'aktif' => $semua->where('aktif', true)->count()];
        $hitung['nonaktif'] = $hitung['semua'] - $hitung['aktif'];
        $q = mb_strtolower(trim($this->q));
        $baris = $semua->filter(fn (AddOn $a) => ($this->fs === 'semua' || ($this->fs === 'aktif') === (bool) $a->aktif)
            && ($q === '' || str_contains(mb_strtolower($a->nama.' '.$a->deskripsi.' '.$a->kode), $q)))->values();
        $termahal = $semua->sortByDesc('harga')->first();
        $pemilik = AddonPengguna::whereIn('addon_kode', $semua->pluck('kode'))->groupBy('addon_kode')->selectRaw('addon_kode, count(*) as n')->pluck('n', 'addon_kode');

        return view('livewire.admin.addons.index', [
            'semua' => $semua,
            'baris' => $baris,
            'hitung' => $hitung,
            'termahal' => $termahal,
            'nButuh' => $semua->where('butuh_premium', true)->count(),
            'nTermasuk' => $semua->where('termasuk_premium', true)->count(),
            'pemilik' => $pemilik,
            'editAddon' => $this->editId ? $semua->firstWhere('id', $this->editId) : null,
            'hapusAddon' => $this->hapusId ? $semua->firstWhere('id', $this->hapusId) : null,
        ])->layout('layouts::app', ['judul' => 'Addons Product', 'sub' => 'Penjualan', 'admin' => true]);
    }
}
