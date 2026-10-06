<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\Lagu;
use App\Models\Media;
use App\Models\Undangan;
use App\Services\MediaDitolak;
use App\Services\PenyimpanMedia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Admin > Musik: katalog musik latar yang bisa dipilih pengguna di editor (hanya lagu berstatus publik). */
class KatalogMusik extends Component
{
    use HakAdminKatalog, WithFileUploads;

    public string $q = '';

    public string $kat = 'semua';

    /** semua | publik | draf */
    public string $fs = 'semua';

    public bool $dialogForm = false;

    #[Locked]
    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $f = [];

    /** Berkas audio yang baru dipilih di dalam formulir (dijadikan aset sistem lalu dipilih sebagai sumber). */
    public $audio = null;

    #[Locked]
    public ?int $putarId = null;

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
        return ['judul' => '', 'artis' => '', 'keterangan' => '', 'kategori' => Lagu::KATEGORI[0], 'durasi' => '', 'sumber' => 'media', 'media_id' => '', 'url' => '', 'youtube' => '', 'status' => Lagu::PUBLIK, 'bawaan' => false];
    }

    /** @return list<string> */
    private function daftarKategori(): array
    {
        return array_values(array_unique([...Lagu::KATEGORI, ...Lagu::query()->distinct()->pluck('kategori')->all()]));
    }

    // ------------------------------------------------------------------ filter

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
    }

    public function updatedKat(): void
    {
        if ($this->kat !== 'semua' && ! in_array($this->kat, $this->daftarKategori(), true)) {
            $this->kat = 'semua';
        }
    }

    public function atur(string $status): void
    {
        $this->fs = in_array($status, [Lagu::PUBLIK, Lagu::DRAF], true) ? $status : 'semua';
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'kat', 'fs');
    }

    // ------------------------------------------------------------------ pratinjau

    public function putar(int $id): void
    {
        $this->pastikanAdmin();
        $this->putarId = $this->putarId === $id || ! Lagu::whereKey($id)->exists() ? null : $id;
    }

    public function tutupPutar(): void
    {
        $this->putarId = null;
    }

    // ------------------------------------------------------------------ urutan dan status

    public function ubahStatus(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $l = Lagu::findOrFail($id);
        $l->update(['status' => $l->status === Lagu::PUBLIK ? Lagu::DRAF : Lagu::PUBLIK]);
        $this->dispatch('toast', pesan: '"'.$l->judul.'" sekarang '.($l->status === Lagu::PUBLIK ? 'Publik' : 'Draf').'.', tipe: 'sukses');
    }

    public function naik(int $id): void
    {
        $this->pindah($id, -1);
    }

    public function turun(int $id): void
    {
        $this->pindah($id, 1);
    }

    private function pindah(int $id, int $arah): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        if ($this->sedangDifilter()) {
            $this->dispatch('toast', pesan: 'Hapus pencarian dan filter untuk mengatur urutan lagu.', tipe: 'info');

            return;
        }
        DB::transaction(function () use ($id, $arah) {
            $urut = Lagu::query()->urut()->lockForUpdate()->get()->values();
            $i = $urut->search(fn (Lagu $l) => $l->id === $id);
            $j = $i === false ? false : $i + $arah;
            if ($i === false || $j < 0 || $j >= $urut->count()) {
                return;
            }
            $a = $urut[$i];
            $urut[$i] = $urut[$j];
            $urut[$j] = $a;
            foreach ($urut as $k => $l) {
                if ($l->urutan !== $k + 1) {
                    $l->update(['urutan' => $k + 1]);
                }
            }
            $this->dispatch('toast', pesan: '"'.$a->judul.'" dipindah ke urutan '.($j + 1).'.', tipe: 'sukses');
        });
    }

    private function sedangDifilter(): bool
    {
        return trim($this->q) !== '' || $this->kat !== 'semua' || $this->fs !== 'semua';
    }

    // ------------------------------------------------------------------ formulir

    public function bukaBaru(): void
    {
        $this->pastikanAdmin();
        $this->resetErrorBag();
        $this->editId = null;
        $this->audio = null;
        $this->f = $this->formKosong();
        $this->dialogForm = true;
    }

    public function bukaEdit(int $id): void
    {
        $this->pastikanAdmin();
        $l = Lagu::findOrFail($id);
        $this->resetErrorBag();
        $this->editId = $l->id;
        $this->audio = null;
        $this->f = [
            'judul' => $l->judul, 'artis' => $l->artis, 'keterangan' => (string) $l->keterangan, 'kategori' => $l->kategori, 'durasi' => $l->durasiTeks(),
            'sumber' => $l->sumber, 'media_id' => $l->media_id ? (string) $l->media_id : '', 'url' => (string) $l->url, 'youtube' => (string) $l->youtube,
            'status' => $l->status, 'bawaan' => (bool) $l->bawaan,
        ];
        $this->dialogForm = true;
    }

    public function setSumber(string $sumber): void
    {
        $this->pastikanAdmin();
        $this->f['sumber'] = array_key_exists($sumber, Lagu::SUMBER) ? $sumber : 'media';
    }

    public function setStatusForm(string $status): void
    {
        $this->pastikanAdmin();
        $this->f['status'] = $status === Lagu::DRAF ? Lagu::DRAF : Lagu::PUBLIK;
    }

    /** Unggah audio langsung dari formulir: menjadi aset sistem di folder Musik dan langsung dipilih sebagai sumber. */
    public function updatedAudio(): void
    {
        $this->pastikanAdmin();
        $berkas = $this->audio;
        $this->audio = null;
        if (! $berkas) {
            return;
        }
        try {
            $m = app(PenyimpanMedia::class)->simpan($berkas, null, ['tipe' => [Media::AUDIO], 'folder' => 'Musik', 'pelaku' => auth()->user()]);
            $this->f['sumber'] = 'media';
            $this->f['media_id'] = (string) $m->id;
            $this->resetErrorBag('f.media_id');
            $this->dispatch('toast', pesan: 'Audio diunggah dan dipilih sebagai sumber.', tipe: 'sukses');
        } catch (MediaDitolak $e) {
            $this->addError('f.media_id', $e->getMessage());
        } finally {
            if (method_exists($berkas, 'delete')) {
                $berkas->delete();
            }
        }
    }

    public function simpan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $lagu = $this->editId ? Lagu::findOrFail($this->editId) : null;
        $f = array_map(fn ($v) => is_string($v) ? trim(strip_tags($v)) : $v, $this->f);
        $sumber = array_key_exists($f['sumber'] ?? '', Lagu::SUMBER) ? $f['sumber'] : 'media';
        $bawaan = (bool) ($lagu?->bawaan);

        $this->validate([
            'f.judul' => ['required', 'string', 'max:120'],
            'f.artis' => ['required', 'string', 'max:120'],
            'f.keterangan' => ['nullable', 'string', 'max:120'],
            'f.kategori' => ['required', 'string', 'in:'.implode(',', $this->daftarKategori())],
            'f.durasi' => ['required', 'string', 'max:7'],
            'f.status' => ['required', 'in:publik,draf'],
        ], [
            'f.judul.required' => 'Judul lagu wajib diisi.',
            'f.artis.required' => 'Artis wajib diisi.',
            'f.kategori.required' => 'Pilih kategori.',
            'f.kategori.in' => 'Kategori tidak tersedia.',
            'f.durasi.required' => 'Durasi wajib diisi.',
            'f.judul.max' => 'Judul maksimal 120 karakter.',
            'f.artis.max' => 'Artis maksimal 120 karakter.',
            'f.keterangan.max' => 'Keterangan maksimal 120 karakter.',
        ], ['f.status' => 'Status']);

        $galat = [];
        $detik = Lagu::durasiDariTeks((string) $f['durasi']);
        if ($detik === null) {
            $galat['f.durasi'] = 'Gunakan format m:ss, misalnya 3:12.';
        }

        $mediaId = null;
        $url = null;
        $youtube = null;
        if ($sumber === 'media') {
            $id = (string) ($f['media_id'] ?? '');
            if ($id === '') {
                if (! $bawaan) {
                    $galat['f.media_id'] = 'Pilih file audio dari Media atau unggah file baru.';
                }
            } elseif (! ctype_digit($id) || ! Media::whereKey((int) $id)->where('tipe', Media::AUDIO)->whereNull('user_id')->exists()) {
                $galat['f.media_id'] = 'File audio tidak ditemukan di aset sistem.';
            } else {
                $mediaId = (int) $id;
            }
        } elseif ($sumber === 'tautan') {
            $u = (string) ($f['url'] ?? '');
            if ($u === '') {
                if (! $bawaan) {
                    $galat['f.url'] = 'Link audio wajib diisi untuk lagu baru.';
                }
            } elseif (! Lagu::urlSah($u) || str_starts_with($u, '/')) {
                $galat['f.url'] = 'URL tidak valid. Gunakan alamat lengkap diawali https://';
            } else {
                $url = $u;
            }
        } else {
            $y = (string) ($f['youtube'] ?? '');
            if ($y === '') {
                $galat['f.youtube'] = 'Link YouTube wajib diisi.';
            } elseif (! Lagu::urlYoutubeSah($y)) {
                $galat['f.youtube'] = 'Link YouTube tidak valid. Contoh: https://youtu.be/xxxxxxxxxxx';
            } else {
                $youtube = $y;
            }
        }

        if (! isset($galat['f.judul']) && Lagu::query()->when($lagu, fn ($q) => $q->whereKeyNot($lagu->id))
            ->whereRaw('lower(judul) = ?', [mb_strtolower($f['judul'])])->whereRaw('lower(artis) = ?', [mb_strtolower($f['artis'])])->exists()) {
            $galat['f.judul'] = 'Lagu dengan judul dan artis ini sudah ada.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $data = [
            'judul' => $f['judul'], 'artis' => $f['artis'], 'keterangan' => $f['keterangan'] ?: null, 'kategori' => $f['kategori'], 'durasi' => $detik,
            'sumber' => $sumber, 'media_id' => $mediaId, 'url' => $url, 'youtube' => $youtube, 'status' => $f['status'],
        ];
        if ($lagu) {
            $lagu->update($data);
        } else {
            do {
                $kode = 'lg'.Str::lower(Str::random(8));
            } while (Lagu::where('kode', $kode)->exists());
            Lagu::create($data + ['kode' => $kode, 'bawaan' => false, 'urutan' => (int) Lagu::max('urutan') + 1]);
        }

        $this->dialogForm = false;
        $this->dispatch('toast', pesan: $lagu ? 'Perubahan lagu disimpan.' : 'Lagu "'.$data['judul'].'" ditambahkan.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ hapus

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $this->hapusId = Lagu::findOrFail($id)->id;
        $this->dialogHapus = true;
    }

    public function hapus(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $this->dialogHapus = false;
        $l = $this->hapusId ? Lagu::find($this->hapusId) : null;
        if (! $l) {
            return;
        }
        DB::transaction(function () use ($l) {
            $l->delete();
            Lagu::query()->urut()->get()->each(fn (Lagu $x, int $k) => $x->urutan === $k + 1 ?: $x->update(['urutan' => $k + 1]));
        });
        if ($this->putarId === $l->id) {
            $this->putarId = null;
        }
        $this->hapusId = null;
        $this->dispatch('toast', pesan: 'Lagu "'.$l->judul.'" dihapus.', tipe: 'sukses');
    }

    /** Jumlah undangan yang sedang memilih lagu ini (isi `musLagu`). */
    private function jumlahPemakai(string $kode): int
    {
        $n = 0;
        Undangan::query()->select(['id', 'isi'])->chunkById(300, function ($kelompok) use ($kode, &$n) {
            $n += $kelompok->filter(fn ($u) => ($u->isi['musLagu'] ?? null) === $kode)->count();
        });

        return $n;
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $this->pastikanAdmin();
        $semua = Lagu::query()->with('media')->urut()->get();
        $kategori = $this->daftarKategori();

        $cari = mb_strtolower(trim($this->q));
        $dasar = $semua->filter(fn (Lagu $l) => ($this->kat === 'semua' || $l->kategori === $this->kat)
            && ($cari === '' || str_contains(mb_strtolower($l->judul.' '.$l->artis.' '.$l->kategori.' '.$l->keterangan), $cari)));
        $tampil = $dasar->filter(fn (Lagu $l) => $this->fs === 'semua' || $l->status === $this->fs)->values();
        $hitung = ['semua' => $dasar->count(), Lagu::PUBLIK => $dasar->where('status', Lagu::PUBLIK)->count(), Lagu::DRAF => $dasar->where('status', Lagu::DRAF)->count()];

        $putar = $this->putarId ? $semua->firstWhere('id', $this->putarId) : null;
        $hapus = $this->hapusId ? $semua->firstWhere('id', $this->hapusId) : null;
        $audioSistem = $this->dialogForm ? Media::where('tipe', Media::AUDIO)->whereNull('user_id')->orderByDesc('id')->limit(200)->get(['id', 'nama', 'ukuran']) : collect();

        return view('livewire.admin.musik.index', [
            'semua' => $semua,
            'tampil' => $tampil,
            'hitung' => $hitung,
            'kategori' => $kategori,
            'terkunci' => $this->sedangDifilter() && $tampil->count() > 1,
            'sedangFilter' => $this->sedangDifilter(),
            'putar' => $putar,
            'hapus' => $hapus,
            'pemakaiHapus' => $hapus ? $this->jumlahPemakai($hapus->kode) : 0,
            'bisaDiputar' => $semua->filter(fn (Lagu $l) => $l->dapatDiputar())->count(),
            'audioSistem' => $audioSistem,
            'formBawaan' => (bool) ($this->f['bawaan'] ?? false),
        ])->layout('layouts::app', ['judul' => 'Musik', 'sub' => 'Konten', 'admin' => true]);
    }
}
