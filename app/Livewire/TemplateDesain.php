<?php

namespace App\Livewire;

use App\Models\Tema;
use App\Models\Undangan;
use App\Services\KuotaUndangan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Katalog tema: cari, saring menurut paket, pratinjau, dan terapkan tema ke draf undangan milik pengguna. */
class TemplateDesain extends Component
{
    private const NAMA_HURUF = ['playfair' => 'Serif klasik', 'jakarta' => 'Sans modern', 'script' => 'Script'];

    /** Undangan target; hanya diubah lewat pilihUndangan() yang memeriksa kepemilikan. */
    #[Locked]
    public ?int $undanganId = null;

    #[Locked]
    public ?int $pratinjauId = null;

    public bool $tampilPratinjau = false;

    /** sampul | penuh */
    public string $modePratinjau = 'sampul';

    public string $q = '';

    public string $tier = 'Semua';

    #[Locked]
    public string $catatan = '';

    #[Locked]
    public string $catatanTipe = 'sukses';

    /** Data berat dimuat setelah halaman tampil, supaya kerangka pemuat terlihat. */
    public bool $siap = false;

    public function mount(): void
    {
        $milik = Undangan::where('user_id', auth()->id())->latest('updated_at')->latest('id');
        $diminta = (int) request()->query('undangan');
        $this->undanganId = ($diminta ? (clone $milik)->whereKey($diminta)->value('id') : null) ?? $milik->value('id');
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    private function undangan(): ?Undangan
    {
        return $this->undanganId ? Undangan::where('user_id', auth()->id())->find($this->undanganId) : null;
    }

    private function temaTersedia(int $id): Tema
    {
        $tema = Tema::terbit()->find($id);
        abort_unless($tema, 404);

        return $tema;
    }

    public function pilihUndangan(int $id): void
    {
        $milik = Undangan::where('user_id', auth()->id())->find($id);
        abort_unless($milik, 404);
        $this->undanganId = $milik->id;
        $this->catatan = '';
    }

    public function pilihTier(string $tier): void
    {
        $this->tier = in_array($tier, ['Semua', ...Tema::TIER], true) ? $tier : 'Semua';
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 60);
    }

    public function tampilkanSemua(): void
    {
        $this->q = '';
        $this->tier = 'Semua';
    }

    public function pratinjau(int $id): void
    {
        $this->pratinjauId = $this->temaTersedia($id)->id;
        $this->modePratinjau = 'sampul';
        $this->tampilPratinjau = true;
    }

    public function tutupPratinjau(): void
    {
        $this->tampilPratinjau = false;
    }

    public function pakai(int $id): void
    {
        $user = auth()->user();
        $this->catatan = '';

        $kunci = 'template-pakai:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, 20)) {
            $this->beriCatatan('Terlalu banyak percobaan. Coba lagi sebentar lagi.', 'galat');

            return;
        }
        RateLimiter::hit($kunci, 60);

        $tema = $this->temaTersedia($id);
        $undangan = $this->undangan();
        if (! $undangan) {
            $this->beriCatatan('Buat undangan dulu, lalu pilih temanya di sini.', 'galat');

            return;
        }
        if (app(KuotaUndangan::class)->temaTerkunci($tema, $user)) {
            $this->beriCatatan('Tema '.$tema->nama.' perlu paket '.$tema->tier.'. Upgrade paketmu untuk memakainya.', 'galat');

            return;
        }
        if ($undangan->tema_id === $tema->id) {
            $this->beriCatatan('Tema '.$tema->nama.' sudah dipakai undangan ini.', 'info');
            $this->tampilPratinjau = false;

            return;
        }

        // Hanya kolom tema yang disentuh: isi, bagian, dan slug milik pengguna tidak berubah.
        $undangan->tema_id = $tema->id;
        $undangan->langkah = array_merge((array) $undangan->langkah, ['tema' => true]);
        $undangan->draf_disimpan_at = now();
        if ($undangan->sudahTerbit()) {
            $undangan->ada_perubahan = true;
        }
        $undangan->save();

        $this->tampilPratinjau = false;
        $this->beriCatatan('Tema '.$tema->nama.' dipakai untuk "'.$undangan->judul.'".'.($undangan->sudahTerbit() ? ' Perubahan ini baru tampil ke tamu setelah kamu menerbitkan ulang.' : ''), 'sukses');
    }

    private function beriCatatan(string $teks, string $tipe): void
    {
        $this->catatan = $teks;
        $this->catatanTipe = $tipe;
    }

    /** Pratinjau penuh bergantung pada renderer yang dibuat terpisah; kembali null bila belum ada atau gagal. */
    private function htmlPenuh(Undangan $undangan, Tema $tema): ?string
    {
        $kelas = '\\App\\Services\\RenderUndangan';
        if (! class_exists($kelas) || ! method_exists($kelas, 'html')) {
            return null;
        }
        try {
            $statis = (new \ReflectionMethod($kelas, 'html'))->isStatic();
            $html = $statis
                ? $kelas::html((array) $undangan->isi, (array) $undangan->bagian, $tema)
                : app($kelas)->html((array) $undangan->isi, (array) $undangan->bagian, $tema);

            return is_string($html) && $html !== '' ? $html : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function tautanEditor(?Undangan $undangan): ?string
    {
        if (! $undangan) {
            return null;
        }
        foreach (['editor', 'undangan.edit'] as $nama) {
            if ($rute = Route::getRoutes()->getByName($nama)) {
                $parameter = $rute->parameterNames()[0] ?? null;

                return route($nama, $parameter ? [$parameter => $undangan->id] : []);
            }
        }

        return Route::has('edit-undangan') ? route('edit-undangan', ['undangan' => $undangan->id]) : null;
    }

    public function render()
    {
        $user = auth()->user();
        $kuota = app(KuotaUndangan::class);
        $semua = Tema::terbit()->urut()->get();
        $daftarUndangan = Undangan::where('user_id', $user->id)->latest('updated_at')->latest('id')->get(['id', 'judul', 'status', 'tema_id', 'tanggal_acara', 'slug']);
        $undangan = $daftarUndangan->firstWhere('id', $this->undanganId);
        $aktif = $undangan?->tema_id ? $semua->firstWhere('id', $undangan->tema_id) : null;

        $kata = mb_strtolower(trim($this->q));
        $tampil = $semua->filter(function (Tema $t) use ($kata) {
            if ($this->tier !== 'Semua' && $t->tier !== $this->tier) {
                return false;
            }

            return $kata === '' || str_contains(mb_strtolower($t->nama.' '.$t->tier.' '.(self::NAMA_HURUF[$t->font_judul] ?? '')), $kata);
        })->values();

        $chip = collect(['Semua', ...Tema::TIER])->map(fn ($k) => [
            'nama' => $k,
            'jumlah' => $k === 'Semua' ? $semua->count() : $semua->where('tier', $k)->count(),
        ]);

        $pv = $this->tampilPratinjau && $this->pratinjauId ? $semua->firstWhere('id', $this->pratinjauId) : null;
        $judulPv = $undangan && trim($undangan->judul) !== '' ? trim($undangan->judul) : 'Raka & Sinta';
        $tanggalPv = $undangan?->tanggal_acara?->format('d.m.Y') ?? '14.02.2027';

        $penuh = null;
        $penuhTersedia = $pv && $undangan && class_exists('\\App\\Services\\RenderUndangan');
        if ($pv && $undangan && $this->modePratinjau === 'penuh') {
            $penuh = $this->htmlPenuh(Undangan::where('user_id', $user->id)->find($undangan->id) ?? $undangan, $pv);
        }

        return view('livewire.template.desain', [
            'semua' => $semua,
            'tema' => $tampil,
            'chip' => $chip,
            'daftarUndangan' => $daftarUndangan,
            'undangan' => $undangan,
            'aktif' => $aktif,
            'terkunci' => $semua->mapWithKeys(fn (Tema $t) => [$t->id => $kuota->temaTerkunci($t, $user)]),
            'pv' => $pv,
            'judulPv' => $judulPv,
            'tanggalPv' => $tanggalPv,
            'htmlPenuh' => $penuh,
            'penuhTersedia' => (bool) $penuhTersedia,
            'namaHuruf' => self::NAMA_HURUF,
            'tautanEditor' => $this->catatanTipe === 'sukses' && $this->catatan !== '' ? $this->tautanEditor($undangan) : null,
            'tautanBilling' => Route::has('billing') ? route('billing') : null,
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : '#',
        ])->layout('layouts::app', ['judul' => 'Template Desain', 'sub' => 'Pilih tampilan undanganmu']);
    }
}
