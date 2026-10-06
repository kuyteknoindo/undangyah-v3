<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\Tema;
use App\Services\DokumenTema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Admin > Theme Creator: daftar tema. Dengan ?tema=<id|baru> halaman yang sama menampilkan editor (ThemeEditor),
 * supaya menu sidebar tetap menyala dan rutenya tetap satu (`admin.theme`).
 *
 * Arsip = hapus lunak (`deleted_at`): tema hilang dari katalog pengguna, undangan yang memakainya tetap tampil
 * (relasi `Undangan::tema` memakai withTrashed). Hapus permanen hanya untuk tema arsip yang tidak dipakai undangan.
 */
class Theme extends Component
{
    use HakAdminKatalog;

    public const URUTAN = ['urutan' => 'Urutan katalog', 'nama' => 'Nama A-Z', 'terbaru' => 'Terbaru diubah', 'dipakai' => 'Paling banyak dipakai'];

    public string $q = '';

    /** semua | publik | draf | arsip */
    public string $fs = 'semua';

    /** Semua | Gratis | Premium | Deluxe */
    public string $tier = 'Semua';

    public string $urut = 'urutan';

    /** Kosong = daftar; angka = id tema; "baru" = tema baru. Dibersihkan di editorId(). */
    #[Url(as: 'tema')]
    public string $tema = '';

    public bool $dialogArsip = false;

    #[Locked]
    public ?int $arsipId = null;

    public bool $dialogHapus = false;

    #[Locked]
    public ?int $hapusId = null;

    public function mount(): void
    {
        $this->pastikanAdmin();
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
    }

    public function atur(string $status): void
    {
        $this->fs = in_array($status, ['semua', 'publik', 'draf', 'arsip'], true) ? $status : 'semua';
    }

    public function aturTier(string $tier): void
    {
        $this->tier = in_array($tier, ['Semua', ...Tema::TIER], true) ? $tier : 'Semua';
    }

    public function updatedUrut(): void
    {
        if (! array_key_exists($this->urut, self::URUTAN)) {
            $this->urut = 'urutan';
        }
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'fs', 'tier');
    }

    /** @return int|'baru'|null */
    private function editorId(): int|string|null
    {
        if ($this->tema === 'baru') {
            return 'baru';
        }

        return ctype_digit($this->tema) && (int) $this->tema > 0 ? (int) $this->tema : null;
    }

    // ------------------------------------------------------------------ aksi baris

    public function ubahStatus(int $id, string $status): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $t = Tema::findOrFail($id);
        $baru = $status === 'publik' ? 'publish' : 'draft';
        if ($t->status === $baru) {
            return;
        }
        if ($baru === 'publish') {
            $periksa = DokumenTema::periksa($t->only(array_keys(DokumenTema::WARNA)));
            if (DokumenTema::adaGalat($periksa)) {
                $this->dispatch('toast', pesan: 'Tema belum bisa dipublikasikan: '.$periksa[0]['teks'], tipe: 'galat');

                return;
            }
        }
        $t->update(['status' => $baru]);
        $this->dispatch('toast', pesan: $baru === 'publish' ? '"'.$t->nama.'" dipublikasikan. Tampil di tab Tema pengguna.' : '"'.$t->nama.'" dijadikan draf dan disembunyikan dari pengguna.', tipe: 'sukses');
    }

    public function duplikat(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $t = Tema::findOrFail($id);
        $nama = $this->namaSalinan($t->nama);
        $salin = DB::transaction(function () use ($t, $nama) {
            $salin = Tema::create([
                'nama' => $nama, 'tier' => $t->tier, 'status' => 'draft',
                'warna_utama' => $t->warna_utama, 'warna_aksen' => $t->warna_aksen, 'warna_permukaan' => $t->warna_permukaan,
                'warna_halaman' => $t->warna_halaman, 'warna_teks' => $t->warna_teks, 'font_judul' => $t->font_judul,
                'latar_tetap' => $t->latar_tetap, 'dokumen' => DokumenTema::bersihkan($t->dokumen),
                'versi' => 1, 'urutan' => (int) Tema::withTrashed()->max('urutan') + 1,
            ]);
            $salin->riwayat()->create(['versi' => 1, 'label' => 'Salinan dari '.Str::limit($t->nama, 40, ''), 'data' => ThemeEditor::potret($salin), 'user_id' => auth()->id()]);

            return $salin;
        });
        $this->dispatch('toast', pesan: 'Tema diduplikat sebagai "'.$salin->nama.'" (draf).', tipe: 'sukses');
    }

    private function namaSalinan(string $nama): string
    {
        $dasar = Str::limit($nama, 80, '');
        $kandidat = $dasar.' (salinan)';
        for ($n = 2; Tema::whereRaw('lower(nama) = ?', [mb_strtolower($kandidat)])->exists(); $n++) {
            $kandidat = $dasar.' (salinan '.$n.')';
        }

        return $kandidat;
    }

    public function geser(int $id, int $arah): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $daftar = Tema::orderBy('urutan')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $daftar, true);
        $j = $i === false ? false : $i + ($arah < 0 ? -1 : 1);
        if ($i === false || ! isset($daftar[$j])) {
            return;
        }
        [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];
        DB::transaction(function () use ($daftar) {
            foreach ($daftar as $urut => $temaId) {
                Tema::whereKey($temaId)->update(['urutan' => $urut + 1]);
            }
        });
    }

    // ------------------------------------------------------------------ arsip & hapus

    public function tanyaArsip(int $id): void
    {
        $this->pastikanAdmin();
        $this->arsipId = Tema::findOrFail($id)->id;
        $this->dialogArsip = true;
    }

    public function arsipkan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $t = $this->arsipId ? Tema::find($this->arsipId) : null;
        $this->dialogArsip = false;
        if (! $t) {
            return;
        }
        // Jadi draf lebih dulu: bila dipulihkan kelak, tema tidak langsung muncul lagi di katalog pengguna.
        $t->update(['status' => 'draft']);
        $t->delete();
        $this->arsipId = null;
        $this->dispatch('toast', pesan: '"'.$t->nama.'" diarsipkan. Undangan yang memakainya tetap tampil seperti biasa.', tipe: 'sukses');
    }

    public function pulihkan(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $t = Tema::onlyTrashed()->findOrFail($id);
        $t->restore();
        $this->dispatch('toast', pesan: '"'.$t->nama.'" dipulihkan sebagai draf.', tipe: 'sukses');
    }

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $t = Tema::onlyTrashed()->findOrFail($id);
        if ($t->kode !== null) {
            $this->dispatch('toast', pesan: 'Tema bawaan sistem tidak dihapus permanen. Biarkan diarsipkan.', tipe: 'galat');

            return;
        }
        if ($t->undangan()->exists()) {
            $this->dispatch('toast', pesan: 'Tema ini masih dipakai undangan. Biarkan diarsipkan agar undangan tidak kehilangan tampilannya.', tipe: 'galat');

            return;
        }
        $this->hapusId = $t->id;
        $this->dialogHapus = true;
    }

    public function hapusPermanen(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $t = $this->hapusId ? Tema::onlyTrashed()->find($this->hapusId) : null;
        $this->dialogHapus = false;
        $this->hapusId = null;
        if (! $t || $t->kode !== null || $t->undangan()->exists()) {
            $this->dispatch('toast', pesan: 'Tema ini tidak bisa dihapus permanen.', tipe: 'galat');

            return;
        }
        $nama = $t->nama;
        $t->forceDelete();
        $this->dispatch('toast', pesan: '"'.$nama.'" dihapus permanen.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $this->pastikanAdmin();
        $editor = $this->editorId();
        if ($editor !== null) {
            return view('livewire.admin.theme.editor-induk', ['editor' => $editor])
                ->layout('layouts::app', ['judul' => 'Theme Creator', 'sub' => 'Konten', 'admin' => true]);
        }

        $semua = Tema::withTrashed()->withCount('undangan')->get();
        $aktif = $semua->whereNull('deleted_at');
        $hitung = [
            'semua' => $aktif->count(),
            'publik' => $aktif->where('status', 'publish')->count(),
            'draf' => $aktif->where('status', '!=', 'publish')->count(),
            'arsip' => $semua->whereNotNull('deleted_at')->count(),
        ];
        $q = mb_strtolower(trim($this->q));
        $baris = $semua->filter(function (Tema $t) use ($q) {
            $arsip = $t->deleted_at !== null;
            $statusCocok = match ($this->fs) {
                'arsip' => $arsip,
                'publik' => ! $arsip && $t->status === 'publish',
                'draf' => ! $arsip && $t->status !== 'publish',
                default => ! $arsip,
            };

            return $statusCocok
                && ($this->tier === 'Semua' || $t->tier === $this->tier)
                && ($q === '' || str_contains(mb_strtolower($t->nama), $q));
        });
        $baris = match ($this->urut) {
            'nama' => $baris->sortBy(fn (Tema $t) => mb_strtolower($t->nama)),
            'terbaru' => $baris->sortByDesc(fn (Tema $t) => $t->updated_at?->getTimestamp() ?? 0),
            'dipakai' => $baris->sortByDesc(fn (Tema $t) => $t->undangan_count),
            default => $baris->sortBy(fn (Tema $t) => sprintf('%09d-%09d', $t->urutan, $t->id)),
        };

        return view('livewire.admin.theme.index', [
            'baris' => $baris->values(),
            'hitung' => $hitung,
            'urutanDrag' => $this->urut === 'urutan' && $this->fs !== 'arsip' && $this->tier === 'Semua' && $q === '',
            'arsipTema' => $this->arsipId ? $semua->firstWhere('id', $this->arsipId) : null,
            'hapusTema' => $this->hapusId ? $semua->firstWhere('id', $this->hapusId) : null,
        ])->layout('layouts::app', ['judul' => 'Theme Creator', 'sub' => 'Konten', 'admin' => true]);
    }
}
