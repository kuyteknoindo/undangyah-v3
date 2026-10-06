<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\Media;
use App\Services\KonfigStorage;
use App\Services\MediaDitolak;
use App\Services\PenyimpanMedia;
use App\Services\ReferensiMedia;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Admin > Media: pustaka file (foto, ornamen, audio, video, dokumen). Unggahan admin menjadi aset sistem (tanpa pemilik, tanpa kuota). */
class PustakaMedia extends Component
{
    use HakAdminKatalog, WithFileUploads;

    private const PER_HALAMAN = 24;

    private const MAKS_PILIH = 200;

    private const MAKS_BERKAS = 20;

    /** @var array<int,mixed> */
    public array $berkas = [];

    public string $q = '';

    /** semua | gambar | audio | video | dokumen */
    public string $tipe = 'semua';

    /** semua | nama folder */
    public string $folder = 'semua';

    /** semua | sistem | pengguna */
    public string $pemilik = 'semua';

    /** baru | nama | ukuran */
    public string $urut = 'baru';

    /** grid | daftar */
    public string $tampilan = 'grid';

    public int $hal = 1;

    /** @var list<int> */
    public array $dipilih = [];

    /** @var list<string> */
    public array $galatUnggah = [];

    // detail
    #[Locked]
    public ?int $detailId = null;

    public bool $dialogDetail = false;

    public string $dNama = '';

    public string $dAlt = '';

    public string $dFolder = 'Umum';

    public bool $dDipakai = false;

    // hapus
    public bool $dialogHapus = false;

    /** @var list<int> */
    #[Locked]
    public array $hapusIds = [];

    public bool $hapusPaksa = false;

    public function mount(): void
    {
        $this->pastikanAdmin();
    }

    // ------------------------------------------------------------------ unggah

    public function updatedBerkas(): void
    {
        $this->pastikanAdmin();
        $berkas = array_slice(array_values((array) $this->berkas), 0, self::MAKS_BERKAS);
        $lebih = count((array) $this->berkas) - count($berkas);
        $this->berkas = [];
        $galat = [];
        $sukses = 0;
        $penyimpan = app(PenyimpanMedia::class);
        $folder = $this->folder !== 'semua' ? $this->folder : null;

        foreach ($berkas as $b) {
            try {
                $penyimpan->simpan($b, null, ['folder' => $folder, 'pelaku' => auth()->user()]);
                $sukses++;
            } catch (MediaDitolak $e) {
                $galat[] = $e->getMessage();
            } catch (\Throwable $e) {
                report($e);
                $galat[] = 'File tidak bisa diproses.';
            } finally {
                if (method_exists($b, 'delete')) {
                    $b->delete();
                }
            }
        }
        if ($lebih > 0) {
            $galat[] = 'Maksimal '.self::MAKS_BERKAS." file sekali unggah; {$lebih} file diabaikan.";
        }

        $this->galatUnggah = $galat;
        $this->hal = 1;
        if ($sukses > 0) {
            $this->dispatch('toast', pesan: $sukses.' file berhasil diunggah.', tipe: 'sukses');
        }
    }

    public function tutupGalat(): void
    {
        $this->galatUnggah = [];
    }

    // ------------------------------------------------------------------ filter

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
        $this->hal = 1;
    }

    public function atur(string $tipe): void
    {
        $this->tipe = $tipe === 'semua' || isset(Media::TIPE[$tipe]) ? $tipe : 'semua';
        $this->hal = 1;
    }

    public function updatedFolder(): void
    {
        if ($this->folder !== 'semua' && ! in_array($this->folder, Media::FOLDER, true)) {
            $this->folder = 'semua';
        }
        $this->hal = 1;
    }

    public function updatedPemilik(): void
    {
        if (! in_array($this->pemilik, ['semua', 'sistem', 'pengguna'], true)) {
            $this->pemilik = 'semua';
        }
        $this->hal = 1;
    }

    public function updatedUrut(): void
    {
        if (! in_array($this->urut, ['baru', 'nama', 'ukuran'], true)) {
            $this->urut = 'baru';
        }
        $this->hal = 1;
    }

    public function aturTampilan(string $nilai): void
    {
        $this->tampilan = $nilai === 'daftar' ? 'daftar' : 'grid';
        $this->hal = 1;
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'tipe', 'folder', 'pemilik');
        $this->hal = 1;
    }

    public function sebelumnya(): void
    {
        $this->hal = max(1, $this->hal - 1);
    }

    public function berikutnya(): void
    {
        $this->hal++;
    }

    // ------------------------------------------------------------------ pilih

    public function pilih(int $id): void
    {
        $this->pastikanAdmin();
        if (in_array($id, $this->dipilih, true)) {
            $this->dipilih = array_values(array_diff($this->dipilih, [$id]));
        } elseif (count($this->dipilih) < self::MAKS_PILIH && Media::whereKey($id)->exists()) {
            $this->dipilih[] = $id;
        }
    }

    public function pilihSemuaTampil(): void
    {
        $this->pastikanAdmin();
        $id = $this->kueri()->forPage($this->halAktif(), $this->perHalaman())->pluck('media.id')->all();
        $semua = $id !== [] && count(array_diff($id, $this->dipilih)) === 0;
        $this->dipilih = $semua
            ? array_values(array_diff($this->dipilih, $id))
            : array_slice(array_values(array_unique([...$this->dipilih, ...$id])), 0, self::MAKS_PILIH);
    }

    public function batalPilih(): void
    {
        $this->dipilih = [];
    }

    // ------------------------------------------------------------------ detail

    public function bukaDetail(int $id): void
    {
        $this->pastikanAdmin();
        $m = Media::findOrFail($id);
        $this->resetErrorBag();
        $this->detailId = $m->id;
        $this->dNama = $m->nama;
        $this->dAlt = (string) $m->alt;
        $this->dFolder = in_array($m->folder, Media::FOLDER, true) ? $m->folder : 'Umum';
        $this->dDipakai = (bool) $m->dipakai;
        $this->dialogDetail = true;
    }

    public function simpanDetail(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $m = $this->detailId ? Media::findOrFail($this->detailId) : null;
        abort_if($m === null, 404);

        $this->dNama = trim($this->dNama);
        $this->validate([
            'dNama' => ['required', 'string', 'max:120', 'regex:/^[^\\\\\/:*?"<>|\x00-\x1F]+$/u'],
            'dAlt' => ['nullable', 'string', 'max:300'],
            'dFolder' => ['required', 'in:'.implode(',', Media::FOLDER)],
        ], [
            'dNama.required' => 'Nama file wajib diisi.',
            'dNama.regex' => 'Nama tidak boleh memuat karakter \\ / : * ? " < > |.',
            'dNama.max' => 'Nama file maksimal 120 karakter.',
            'dAlt.max' => 'Teks alt maksimal 300 karakter.',
            'dFolder.in' => 'Folder tidak tersedia.',
        ]);

        $m->update([
            'nama' => $this->dNama,
            'alt' => trim(strip_tags($this->dAlt)) ?: null,
            'folder' => $this->dFolder,
            'dipakai' => $this->dDipakai,
        ]);
        $this->dialogDetail = false;
        $this->dispatch('toast', pesan: 'Perubahan file disimpan.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ hapus

    /** @param  list<int>  $ids */
    public function tanyaHapus(array $ids = []): void
    {
        $this->pastikanAdmin();
        $ids = array_values(array_unique(array_map('intval', $ids ?: $this->dipilih)));
        $ids = Media::whereIn('id', array_slice($ids, 0, self::MAKS_PILIH))->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        $this->hapusIds = $ids;
        $this->hapusPaksa = false;
        $this->dialogDetail = false;
        $this->dialogHapus = true;
    }

    public function hapus(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $this->dialogHapus = false;
        $daftar = Media::whereIn('id', $this->hapusIds)->get();
        $terhapus = 0;
        $dilewati = 0;
        $penyimpan = app(PenyimpanMedia::class);
        foreach ($daftar as $m) {
            try {
                $penyimpan->hapus($m, $this->hapusPaksa);
                $terhapus++;
            } catch (MediaDitolak) {
                $dilewati++;
            }
        }
        $this->dipilih = array_values(array_diff($this->dipilih, $this->hapusIds));
        $this->hapusIds = [];
        $this->hapusPaksa = false;
        if ($this->detailId && ! Media::whereKey($this->detailId)->exists()) {
            $this->detailId = null;
        }

        if ($terhapus > 0) {
            $this->dispatch('toast', pesan: $terhapus === 1 ? 'File dihapus.' : $terhapus.' file dihapus.', tipe: 'sukses');
        }
        if ($dilewati > 0) {
            $this->dispatch('toast', pesan: $dilewati.' file tidak dihapus karena sedang dipakai atau dirujuk. Centang konfirmasi hapus paksa bila yakin.', tipe: 'galat');
        }
    }

    // ------------------------------------------------------------------ tampilan

    private function kueri(): Builder
    {
        $q = Media::query();
        if ($this->tipe !== 'semua') {
            $q->where('tipe', $this->tipe);
        }
        $this->saringDasar($q);
        match ($this->urut) {
            'nama' => $q->orderByRaw('lower(nama)')->orderBy('id'),
            'ukuran' => $q->orderByDesc('ukuran')->orderByDesc('id'),
            default => $q->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $q;
    }

    /** Saringan selain tipe (dipakai juga untuk menghitung angka per tipe). */
    private function saringDasar(Builder $q): Builder
    {
        $cari = trim($this->q);
        if ($cari !== '') {
            $pola = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($cari)).'%';
            $q->where(fn (Builder $w) => $w->whereRaw("lower(nama) like ? escape '\\'", [$pola])->orWhereRaw("lower(coalesce(alt, '')) like ? escape '\\'", [$pola]));
        }
        if ($this->folder !== 'semua') {
            $q->where('folder', $this->folder);
        }
        if ($this->pemilik === 'sistem') {
            $q->whereNull('user_id');
        } elseif ($this->pemilik === 'pengguna') {
            $q->whereNotNull('user_id');
        }

        return $q;
    }

    private function perHalaman(): int
    {
        return $this->tampilan === 'daftar' ? 20 : self::PER_HALAMAN;
    }

    private function halAktif(): int
    {
        $total = (clone $this->kueri())->count();
        $maks = max(1, (int) ceil($total / $this->perHalaman()));

        return min(max(1, $this->hal), $maks);
    }

    public function render()
    {
        $this->pastikanAdmin();
        $total = (clone $this->kueri())->count();
        $halaman = max(1, (int) ceil($total / $this->perHalaman()));
        $hal = min(max(1, $this->hal), $halaman);
        $baris = $this->kueri()->with('pemilik:id,name')->forPage($hal, $this->perHalaman())->get();

        $perTipe = Media::query()->selectRaw('tipe, count(*) as n, coalesce(sum(ukuran), 0) as ukuran')->groupBy('tipe')->get()->keyBy('tipe');
        $hitungTipe = $this->saringDasar(Media::query())->selectRaw('tipe, count(*) as n')->groupBy('tipe')->pluck('n', 'tipe');
        $jumlah = fn (string $t) => (int) ($perTipe[$t]->n ?? 0);
        $ukuran = fn (string $t) => (int) ($perTipe[$t]->ukuran ?? 0);

        $detail = $this->detailId ? Media::with('pemilik:id,name')->find($this->detailId) : null;
        $ringkas = app(ReferensiMedia::class);
        $hapusDaftar = $this->hapusIds ? Media::whereIn('id', $this->hapusIds)->get() : collect();
        $rujukanHapus = $hapusDaftar->isNotEmpty() ? $ringkas->cari($hapusDaftar) : [];
        $rujukanDetail = $detail ? ($ringkas->cari([$detail])[$detail->id] ?? []) : [];
        $dipilihAda = $this->dipilih ? Media::whereIn('id', $this->dipilih)->get(['id', 'ukuran']) : collect();

        return view('livewire.admin.media.index', [
            'baris' => $baris,
            'total' => $total,
            'halAktif' => $hal,
            'halaman' => $halaman,
            'semuaJumlah' => (int) $perTipe->sum('n'),
            'semuaUkuran' => (int) $perTipe->sum('ukuran'),
            'jumlah' => $jumlah,
            'ukuranTipe' => $ukuran,
            'hitungTipe' => $hitungTipe,
            'konfig' => KonfigStorage::ambil(),
            'batasPhp' => ini_get('upload_max_filesize'),
            'detail' => $detail,
            'rujukanDetail' => $rujukanDetail,
            'hapusDaftar' => $hapusDaftar,
            'rujukanHapus' => $rujukanHapus,
            'ringkasan' => $ringkas,
            'dipilihJumlah' => $dipilihAda->count(),
            'dipilihUkuran' => (int) $dipilihAda->sum('ukuran'),
            'adaFilter' => $this->q !== '' || $this->tipe !== 'semua' || $this->folder !== 'semua' || $this->pemilik !== 'semua',
        ])->layout('layouts::app', ['judul' => 'Media', 'sub' => 'Konten', 'admin' => true]);
    }
}
