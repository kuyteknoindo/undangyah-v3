<?php

namespace App\Livewire;

use App\Livewire\Concerns\PilihUndangan;
use App\Models\Tamu;
use App\Models\TemplatePesan as ModelTemplatePesan;
use App\Models\Undangan;
use App\Services\PesanUndangan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Template pesan bawaan (baca saja, bisa diduplikat) dan milik pengguna (tambah, ubah, hapus, jadikan default). */
class TemplatePesan extends Component
{
    use PilihUndangan;

    private const BATAS_SIMPAN = 30;

    public bool $siap = false;

    public string $q = '';

    /** semua | whatsapp | email */
    public string $kanal = 'semua';

    public string $sel = '';

    public string $pvNama = 'Budi Santoso';

    public bool $dialogForm = false;

    #[Locked]
    public ?int $mId = null;

    public string $mNama = '';

    public string $mKanal = 'whatsapp';

    public string $mSubjek = '';

    public string $mIsi = '';

    #[Locked]
    public ?string $hapusId = null;

    public function mount(?string $undangan = null): void
    {
        $this->pilihAwal($undangan);
        $this->sel = app(PesanUndangan::class)->pengaturan(auth()->user())['template'];
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    public function pilih(int $id): void
    {
        abort_unless(Undangan::where('user_id', auth()->id())->whereKey($id)->exists(), 404);
        $this->undanganId = $id;
    }

    public function updatedPvNama(): void
    {
        $this->pvNama = mb_substr($this->pvNama, 0, 60);
    }

    public function updatedMIsi(): void
    {
        $this->mIsi = mb_substr($this->mIsi, 0, PesanUndangan::MAKS_ISI + 200);
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'kanal');
    }

    public function pratinjau(string $id): void
    {
        if (app(PesanUndangan::class)->daftar(auth()->user())->has($id)) {
            $this->sel = $id;
        }
    }

    // ------------------------------------------------------------------ form

    private function buka(?ModelTemplatePesan $t, ?array $salinan = null): void
    {
        $this->resetErrorBag();
        $this->mId = $t?->id;
        $this->mNama = $t?->nama ?? ($salinan ? $salinan['nama'].' (salinan)' : '');
        $this->mKanal = $t?->kanal ?? ($salinan['kanal'] ?? 'whatsapp');
        $this->mSubjek = $t?->subjek ?? ($salinan['subjek'] ?? '');
        $this->mIsi = $t?->isi ?? ($salinan['isi'] ?? '');
        $this->dialogForm = true;
    }

    public function bukaBaru(): void
    {
        $this->buka(null);
    }

    public function bukaEdit(string $id): void
    {
        $this->buka($this->milik($id));
    }

    public function duplikat(string $id): void
    {
        $sumber = app(PesanUndangan::class)->daftar(auth()->user())->get($id);
        abort_unless($sumber, 404);
        $this->buka(null, $sumber);
    }

    /** Hanya template "u-<id>" milik pengguna login; selain itu 404. */
    private function milik(?string $id): ModelTemplatePesan
    {
        abort_unless($id && preg_match('/^u-(\d+)$/', $id, $m), 404);
        $t = ModelTemplatePesan::where('user_id', auth()->id())->find((int) $m[1]);
        abort_unless($t, 404);

        return $t;
    }

    public function setKanal(string $kanal): void
    {
        $this->mKanal = $kanal === 'email' ? 'email' : 'whatsapp';
        if ($this->mKanal === 'email' && trim($this->mSubjek) === '') {
            $this->mSubjek = 'Undangan: {judul}';
        }
    }

    public function sisipSalam(string $kunci): void
    {
        $salam = PesanUndangan::salam()[$kunci] ?? null;
        if (! $salam || $salam['buka'] === '') {
            return;
        }
        $semuaBuka = collect(PesanUndangan::salam())->pluck('buka')->filter()->all();
        $isi = ltrim($this->mIsi);
        foreach ($semuaBuka as $buka) {
            if (str_starts_with($isi, $buka)) {
                $isi = ltrim(substr($isi, strlen($buka)));
                break;
            }
        }
        $this->mIsi = $salam['buka']."\n\n".$isi;
    }

    public function simpan(): void
    {
        $this->resetErrorBag();
        $kunci = 'template-pesan-simpan:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_SIMPAN)) {
            $this->addError('mNama', 'Terlalu banyak penyimpanan. Coba lagi sebentar lagi.');

            return;
        }
        RateLimiter::hit($kunci, 60);

        $this->mNama = trim(preg_replace('/\s+/u', ' ', $this->mNama) ?? '');
        $this->mSubjek = trim($this->mSubjek);
        $email = $this->mKanal === 'email';
        $this->validate([
            'mNama' => ['required', 'string', 'max:'.PesanUndangan::MAKS_NAMA],
            'mKanal' => ['required', 'in:whatsapp,email'],
            'mSubjek' => [$email ? 'required' : 'nullable', 'string', 'max:'.PesanUndangan::MAKS_SUBJEK],
            'mIsi' => ['required', 'string', 'max:'.PesanUndangan::MAKS_ISI],
        ], [
            'mNama.required' => 'Nama template wajib diisi.',
            'mNama.max' => 'Nama template maksimal '.PesanUndangan::MAKS_NAMA.' karakter.',
            'mSubjek.required' => 'Subjek email wajib diisi.',
            'mSubjek.max' => 'Subjek email maksimal '.PesanUndangan::MAKS_SUBJEK.' karakter.',
            'mIsi.required' => 'Isi pesan wajib diisi.',
            'mIsi.max' => 'Isi pesan maksimal '.PesanUndangan::MAKS_ISI.' karakter.',
        ]);

        $sudah = ModelTemplatePesan::where('user_id', auth()->id());
        if ($this->mId) {
            $t = $this->milik('u-'.$this->mId);
            $dobel = (clone $sudah)->where('id', '!=', $t->id);
        } else {
            if ((clone $sudah)->count() >= PesanUndangan::MAKS_TEMPLATE) {
                $this->addError('mNama', 'Template buatanmu sudah mencapai '.PesanUndangan::MAKS_TEMPLATE.'. Hapus yang tidak dipakai dulu.');

                return;
            }
            $t = new ModelTemplatePesan(['user_id' => auth()->id()]);
            $dobel = clone $sudah;
        }
        if ($dobel->whereRaw('lower(nama) = ?', [mb_strtolower($this->mNama)])->exists()) {
            $this->addError('mNama', 'Sudah ada template dengan nama itu. Pakai nama lain.');

            return;
        }

        $t->fill(['nama' => $this->mNama, 'kanal' => $this->mKanal, 'subjek' => $email ? $this->mSubjek : null, 'isi' => $this->mIsi])->save();
        // Template yang kanalnya berubah ke email tidak boleh tetap menjadi default WhatsApp.
        if ($email) {
            $this->lepasDefault('u-'.$t->id);
        }

        $baru = $this->mId === null;
        $this->dialogForm = false;
        $this->sel = 'u-'.$t->id;
        $this->mId = null;
        $this->dispatch('toast', pesan: 'Template "'.$t->nama.'" '.($baru ? 'ditambahkan.' : 'disimpan.'), tipe: 'sukses');
    }

    // ------------------------------------------------------------------ hapus & default

    public function mintaHapus(string $id): void
    {
        $this->milik($id);
        $this->hapusId = $id;
    }

    public function batalHapus(): void
    {
        $this->hapusId = null;
    }

    public function hapus(): void
    {
        $t = $this->milik($this->hapusId);
        $id = 'u-'.$t->id;
        $nama = $t->nama;
        $t->delete();
        $this->lepasDefault($id);
        // Tamu yang memakai template ini kembali mengikuti default.
        Tamu::whereIn('undangan_id', Undangan::where('user_id', auth()->id())->select('id'))->where('template_pesan', $id)->update(['template_pesan' => null]);
        $this->hapusId = null;
        if ($this->sel === $id) {
            $this->sel = app(PesanUndangan::class)->pengaturan(auth()->user())['template'];
        }
        $this->dispatch('toast', pesan: 'Template "'.$nama.'" dihapus.', tipe: 'sukses');
    }

    private function lepasDefault(string $id): void
    {
        $layanan = app(PesanUndangan::class);
        $sekarang = $layanan->pengaturan(auth()->user());
        if ($sekarang['template'] === $id) {
            $layanan->simpanPengaturan(auth()->user(), PesanUndangan::TEMPLATE_BAWAAN_UTAMA);
        }
    }

    public function jadikanDefault(string $id): void
    {
        $daftar = app(PesanUndangan::class)->daftar(auth()->user());
        $t = $daftar->get($id);
        abort_unless($t, 404);
        if ($t['kanal'] !== 'whatsapp') {
            $this->dispatch('toast', pesan: 'Hanya template WhatsApp yang bisa jadi default.', tipe: 'galat');

            return;
        }
        app(PesanUndangan::class)->simpanPengaturan(auth()->user(), $id);
        $this->sel = $id;
        $this->dispatch('toast', pesan: '"'.$t['nama'].'" sekarang jadi template default.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $layout = ['judul' => 'Template Pesan', 'sub' => 'Pengiriman'];
        if (! $this->siap) {
            return view('livewire.template-pesan', ['siap' => false])->layout('layouts::app', $layout);
        }

        $layanan = app(PesanUndangan::class);
        $undangan = $this->undanganTerpilih();
        $contoh = ! $undangan;
        $dataUndangan = $undangan ?? new Undangan(['judul' => 'Raka & Sinta', 'slug' => 'raka-sinta', 'lokasi_ringkas' => 'Gedung Serbaguna, Jakarta', 'tanggal_acara' => now()->addMonths(2)]);

        $semua = $layanan->daftar(auth()->user());
        $pengaturan = $layanan->pengaturan(auth()->user(), $semua);
        $kata = mb_strtolower(trim($this->q));
        $cocok = fn (array $t) => ($this->kanal === 'semua' || $t['kanal'] === $this->kanal)
            && ($kata === '' || str_contains(mb_strtolower($t['nama'].' '.$t['isi']), $kata));
        $kartu = fn (array $t) => $t + [
            'default' => $pengaturan['template'] === $t['id'],
            'ringkas' => trim(preg_replace('/\s+/u', ' ', $t['isi']) ?? ''),
            'panjang' => mb_strlen($t['isi']),
        ];

        $milik = $semua->filter(fn ($t) => ! $t['bawaan']);
        $bawaan = $semua->filter(fn ($t) => $t['bawaan']);
        $terpilih = $semua->get($this->sel) ?? $semua->get($pengaturan['template']);
        $nama = trim($this->pvNama) !== '' ? trim($this->pvNama) : 'Nama Tamu';
        $teks = PesanUndangan::isi($terpilih['isi'], $dataUndangan, $nama, $pengaturan['salam']);
        $subjek = $terpilih['subjek'] ? PesanUndangan::isi($terpilih['subjek'], $dataUndangan, $nama, $pengaturan['salam']) : '';

        return view('livewire.template-pesan', [
            'siap' => true,
            'undangan' => $undangan,
            'contoh' => $contoh,
            'daftarUndangan' => $this->daftarUndangan(),
            'milik' => $milik->filter($cocok)->map($kartu)->values(),
            'bawaan' => $bawaan->filter($cocok)->map($kartu)->values(),
            'jumlahMilik' => $milik->count(),
            'jumlahBawaan' => $bawaan->count(),
            'hitung' => ['semua' => $semua->count(), 'whatsapp' => $semua->where('kanal', 'whatsapp')->count(), 'email' => $semua->where('kanal', 'email')->count()],
            'terpilih' => $terpilih,
            'teks' => $teks,
            'subjek' => $subjek,
            'email' => $terpilih['kanal'] === 'email',
            'urlWa' => PesanUndangan::urlWa(null, $teks),
            'urlSurel' => 'mailto:?subject='.rawurlencode($subjek).'&body='.rawurlencode($teks),
            'cuplikanForm' => $this->dialogForm ? PesanUndangan::isi($this->mIsi, $dataUndangan, $nama, $pengaturan['salam']) : '',
            'asingForm' => $this->dialogForm ? PesanUndangan::variabelAsing($this->mIsi.' '.$this->mSubjek) : [],
            'salam' => PesanUndangan::salam(),
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : null,
            'tautanBagikan' => Route::has('bagikan') ? route('bagikan', $undangan ? ['undangan' => $undangan->id] : []) : null,
        ])->layout('layouts::app', $layout);
    }
}
