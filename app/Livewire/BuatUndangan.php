<?php

namespace App\Livewire;

use App\Models\Tema;
use App\Models\Undangan;
use App\Services\KuotaUndangan;
use App\Services\SlugUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Wizard 3 langkah: jenis acara, detail acara, desain. Hasil: Undangan berstatus draf milik pengguna. */
class BuatUndangan extends Component
{
    /** Langkah dan pilihan tema hanya diubah lewat aksi server (lanjut/kembali/pilihTema). */
    #[Locked]
    public int $langkah = 1;

    #[Locked]
    public ?int $temaId = null;

    public string $pria = '';

    public string $wanita = '';

    public string $judul = '';

    public string $tanggal = '';

    public string $waktu = '10:00';

    public string $lokasi = '';

    public string $slug = '';

    public bool $judulDiubah = false;

    public bool $slugDiubah = false;

    public string $galatUmum = '';

    public function mount(): void
    {
        $user = auth()->user();
        $kuota = app(KuotaUndangan::class);
        $this->temaId = $this->temaTersedia()->first(fn (Tema $t) => ! $kuota->temaTerkunci($t, $user))?->id;
    }

    protected function rules(): array
    {
        return [
            'pria' => ['nullable', 'string', 'max:40'],
            'wanita' => ['nullable', 'string', 'max:40'],
            'judul' => ['required', 'string', 'max:120'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'waktu' => ['nullable', 'date_format:H:i'],
            'lokasi' => ['nullable', 'string', 'max:150'],
            'slug' => ['required', 'string', function (string $atribut, mixed $nilai, \Closure $gagal) {
                if ($pesan = app(SlugUndangan::class)->galat((string) $nilai)) {
                    $gagal($pesan);
                }
            }],
        ];
    }

    protected function validationAttributes(): array
    {
        return [
            'pria' => 'Nama mempelai pria',
            'wanita' => 'Nama mempelai wanita',
            'judul' => 'Judul undangan',
            'tanggal' => 'Tanggal acara',
            'waktu' => 'Waktu',
            'lokasi' => 'Lokasi',
            'slug' => 'Link undangan',
        ];
    }

    /** Galat satu isian hilang begitu isiannya diubah (slug diperiksa ulang di updatedSlug). */
    public function updated(string $nama): void
    {
        if ($nama !== 'slug') {
            $this->resetErrorBag($nama);
        }
    }

    public function updatedPria(): void
    {
        $this->isiOtomatis();
    }

    public function updatedWanita(): void
    {
        $this->isiOtomatis();
    }

    public function updatedJudul(): void
    {
        $this->judulDiubah = true;
        if (! $this->slugDiubah) {
            $this->slug = app(SlugUndangan::class)->buat($this->judul);
        }
        $this->resetErrorBag(['judul', 'slug']);
    }

    public function updatedSlug(): void
    {
        $this->slugDiubah = true;
        $this->slug = trim(preg_replace('/[^a-z0-9-]/', '-', mb_strtolower($this->slug)));
        $this->validateOnly('slug');
    }

    private function isiOtomatis(): void
    {
        if ($this->judulDiubah) {
            return;
        }
        $pria = trim($this->pria);
        $wanita = trim($this->wanita);
        $this->judul = $pria !== '' && $wanita !== '' ? "$pria & $wanita" : ($pria !== '' ? $pria : $wanita);
        if (! $this->slugDiubah) {
            $this->slug = trim($this->judul) === '' ? '' : app(SlugUndangan::class)->buat($this->judul);
        }
    }

    public function lanjut(): void
    {
        $this->galatUmum = '';
        if ($this->langkah === 1) {
            if (app(KuotaUndangan::class)->penuh(auth()->user())) {
                return;
            }
            $this->langkah = 2;

            return;
        }
        if ($this->langkah === 2) {
            $this->validate();
            $this->langkah = 3;
        }
    }

    public function kembali(): void
    {
        $this->galatUmum = '';
        $this->langkah = max(1, $this->langkah - 1);
    }

    public function pilihTema(int $id): void
    {
        $tema = $this->temaTersedia()->firstWhere('id', $id);
        if (! $tema || app(KuotaUndangan::class)->temaTerkunci($tema, auth()->user())) {
            $this->galatUmum = 'Pilih tema yang tersedia di paketmu.';

            return;
        }
        $this->galatUmum = '';
        $this->temaId = $tema->id;
    }

    public function buat()
    {
        $user = auth()->user();
        $kuota = app(KuotaUndangan::class);
        $this->galatUmum = '';

        if ($this->langkah !== 3) {
            return;
        }

        $kunci = 'buat-undangan:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->galatUmum = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.';

            return;
        }
        RateLimiter::hit($kunci, 60);

        if ($kuota->penuh($user)) {
            $this->galatUmum = 'Kuota undangan paketmu sudah habis. Upgrade paket untuk membuat undangan baru.';

            return;
        }

        $tema = $this->temaId ? $this->temaTersedia()->firstWhere('id', $this->temaId) : null;
        if (! $tema || $kuota->temaTerkunci($tema, $user)) {
            $this->galatUmum = 'Pilih tema yang tersedia di paketmu.';

            return;
        }

        try {
            $this->validate();
        } catch (ValidationException $e) {
            $this->galatUmum = 'Data belum lengkap. Kembali ke langkah 2 untuk memperbaikinya.';
            $this->langkah = 2;
            throw $e;
        }

        try {
            $undangan = DB::transaction(fn () => Undangan::create([
                'user_id' => $user->id,
                'tema_id' => $tema->id,
                'judul' => trim($this->judul),
                'jenis' => 'Pernikahan',
                'slug' => $this->slug,
                'status' => Undangan::DRAF,
                'isi' => $this->isiAwal(),
                'bagian' => ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []],
                'langkah' => ['tema' => true, 'data' => false, 'tamu' => false, 'terbit' => false],
                'tanggal_acara' => $this->tanggal,
                'lokasi_ringkas' => trim($this->lokasi) ?: null,
                'draf_disimpan_at' => now(),
            ]));
        } catch (UniqueConstraintViolationException) {
            $this->langkah = 2;
            $this->addError('slug', 'Alamat ini sudah dipakai undangan lain.');

            return;
        }

        foreach (['editor', 'undangan.edit'] as $nama) {
            if ($rute = Route::getRoutes()->getByName($nama)) {
                $parameter = $rute->parameterNames()[0] ?? null;

                return $this->redirectRoute($nama, $parameter ? [$parameter => $undangan->id] : [], navigate: true);
            }
        }

        session()->flash('toast', ['pesan' => 'Undangan "'.$undangan->judul.'" dibuat sebagai draf.', 'tipe' => 'sukses']);

        return $this->redirectRoute('beranda', navigate: true);
    }

    /** Isian awal dari skema, ditimpa dengan data yang diisi pengguna. */
    private function isiAwal(): array
    {
        $isi = SkemaUndangan::isiAwal();
        $tanggal = Carbon::createFromFormat('Y-m-d', $this->tanggal);
        $panjang = $tanggal->locale('id')->isoFormat('dddd, D MMMM YYYY');
        $lokasi = trim($this->lokasi);

        foreach (['pria' => 'priaP', 'wanita' => 'wanitaP'] as $prop => $kunci) {
            if (trim($this->{$prop}) !== '') {
                $isi[$kunci] = trim($this->{$prop});
                $isi[$prop.'Nama'] = trim($this->{$prop});
            }
        }
        $isi['tglCover'] = $panjang;
        $isi['hmTgl'] = $this->tanggal;
        $isi['gcTgl'] = $this->tanggal;
        $isi['lvTgl'] = $this->tanggal;
        if ($this->waktu !== '') {
            $isi['hmJam'] = $this->waktu;
        }
        if ($lokasi !== '') {
            $isi['gcLokasi'] = $lokasi;
        }
        $isi['ptNama'] = trim($this->judul);
        foreach ($isi['_daftar']['ev'] ?? [] as $id) {
            $isi["ev{$id}_tgl"] = $panjang;
            if ($lokasi !== '') {
                $isi["ev{$id}_lokasi"] = $lokasi;
            }
        }

        return $isi;
    }

    private function temaTersedia()
    {
        return Tema::terbit()->urut()->get();
    }

    public function render()
    {
        $user = auth()->user();
        $kuota = app(KuotaUndangan::class);
        $tema = $this->temaTersedia();
        $dipilih = $tema->firstWhere('id', $this->temaId);
        $galatSlug = $this->slug !== '' ? app(SlugUndangan::class)->galat($this->slug) : null;

        return view('livewire.buat-undangan', [
            'tema' => $tema,
            'dipilih' => $dipilih,
            'terkunci' => $tema->mapWithKeys(fn (Tema $t) => [$t->id => $kuota->temaTerkunci($t, $user)]),
            'batas' => $kuota->batas($user),
            'terpakai' => $kuota->terpakai($user),
            'penuh' => $kuota->penuh($user),
            'slugTersedia' => $this->slug !== '' && $galatSlug === null,
            'tanggalTitik' => $this->tanggal !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->tanggal) ? implode('.', array_reverse(explode('-', $this->tanggal))) : 'DD.MM.YYYY',
        ])->layout('layouts::app', [
            'judul' => 'Buat Undangan Baru',
            'sub' => 'Undangan baru',
        ]);
    }
}
