<?php

namespace App\Livewire;

use App\Models\Undangan;
use App\Services\PenahananSlug;
use App\Services\SlugUndangan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Ganti alamat publik (slug) undangan milik pengguna yang sudah terbit. */
class LinkKustom extends Component
{
    private const BATAS_CEK = 60;

    private const BATAS_SIMPAN = 5;

    private const RIWAYAT_MAKS = 20;

    #[Locked]
    public ?int $undanganId = null;

    public string $slug = '';

    public bool $tampilKonfirmasi = false;

    public bool $paham = false;

    #[Locked]
    public string $galatKonfirmasi = '';

    #[Locked]
    public string $catatan = '';

    public bool $siap = false;

    public function mount(?string $undangan = null): void
    {
        $milik = Undangan::where('user_id', auth()->id());
        if ($undangan !== null) {
            $dipilih = (clone $milik)->find((int) $undangan);
            abort_unless($dipilih, 404);
        } else {
            $dipilih = (clone $milik)->where('status', Undangan::TERBIT)->latest('updated_at')->latest('id')->first()
                ?? $milik->latest('updated_at')->latest('id')->first();
        }
        $this->undanganId = $dipilih?->id;
        $this->slug = (string) $dipilih?->slug;
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    private function undangan(): ?Undangan
    {
        return $this->undanganId ? Undangan::where('user_id', auth()->id())->find($this->undanganId) : null;
    }

    public function pilih(int $id): void
    {
        $milik = Undangan::where('user_id', auth()->id())->find($id);
        abort_unless($milik, 404);
        $this->undanganId = $milik->id;
        $this->slug = (string) $milik->slug;
        $this->tampilKonfirmasi = false;
        $this->paham = false;
        $this->galatKonfirmasi = '';
        $this->catatan = '';
    }

    public function updatedSlug(): void
    {
        $this->slug = mb_substr(preg_replace('/[^a-z0-9-]/', '-', mb_strtolower($this->slug)), 0, SlugUndangan::MAKS + 10);
        $this->catatan = '';

        // Setiap perubahan isian dihitung sebagai satu pengecekan ketersediaan.
        if (! $this->cekTerbatas()) {
            RateLimiter::hit($this->kunciCek(), 60);
        }
    }

    private function kunciCek(): string
    {
        return 'link-kustom-cek:'.auth()->id();
    }

    private function cekTerbatas(): bool
    {
        return RateLimiter::tooManyAttempts($this->kunciCek(), self::BATAS_CEK);
    }

    public function kembalikan(): void
    {
        $this->slug = (string) $this->undangan()?->slug;
        $this->catatan = '';
    }

    public function pakaiSaran(string $saran): void
    {
        $this->slug = $saran;
        $this->updatedSlug();
    }

    public function bukaKonfirmasi(): void
    {
        $undangan = $this->undangan();
        $this->galatKonfirmasi = '';
        if (! $undangan || ! $undangan->sudahTerbit() || $this->galatSlug($undangan) !== null || $this->slug === $undangan->slug) {
            return;
        }
        $this->paham = false;
        $this->tampilKonfirmasi = true;
    }

    public function batalKonfirmasi(): void
    {
        $this->tampilKonfirmasi = false;
        $this->paham = false;
        $this->galatKonfirmasi = '';
    }

    public function ganti(): void
    {
        $this->galatKonfirmasi = '';
        $kunci = 'link-kustom-simpan:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_SIMPAN)) {
            $this->galatKonfirmasi = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.';

            return;
        }
        RateLimiter::hit($kunci, 60);

        $awal = $this->undangan();
        abort_unless($awal, 404);
        if (! $awal->sudahTerbit()) {
            $this->galatKonfirmasi = 'Undangan ini belum terbit, link belum bisa diganti.';

            return;
        }
        if (! $this->paham) {
            $this->galatKonfirmasi = 'Centang pernyataan di atas dulu untuk melanjutkan.';

            return;
        }

        $baru = $this->slug;
        if ($baru === $awal->slug) {
            $this->galatKonfirmasi = 'Link baru sama dengan link sekarang.';

            return;
        }
        if ($pesan = app(SlugUndangan::class)->galat($baru, $awal->id)) {
            $this->galatKonfirmasi = $pesan;

            return;
        }

        try {
            DB::transaction(function () use ($awal, $baru) {
                $undangan = Undangan::where('user_id', auth()->id())->lockForUpdate()->findOrFail($awal->id);
                if (! $undangan->sudahTerbit()) {
                    abort(409);
                }
                $lama = $undangan->slug;
                $pengaturan = (array) $undangan->pengaturan;
                $riwayat = (array) ($pengaturan['riwayat_slug'] ?? []);
                array_unshift($riwayat, ['dari' => $lama, 'ke' => $baru, 'pada' => now()->toIso8601String()]);
                $pengaturan['riwayat_slug'] = array_slice($riwayat, 0, self::RIWAYAT_MAKS);

                // RSVP, ucapan, tamu, dan tayangan terikat ke id undangan, jadi ikut pindah tanpa disalin.
                $undangan->slug = $baru;
                $undangan->pengaturan = $pengaturan;
                if (is_array($undangan->terbitan) && array_key_exists('slug', $undangan->terbitan)) {
                    $undangan->terbitan = [...$undangan->terbitan, 'slug' => $baru];
                }
                $undangan->save();
            });
        } catch (UniqueConstraintViolationException) {
            $this->galatKonfirmasi = 'Alamat ini sudah dipakai undangan lain.';

            return;
        } catch (ValidationException $e) {
            $this->galatKonfirmasi = $e->errors()['slug'][0] ?? 'Alamat ini tidak bisa dipakai.';

            return;
        }

        $this->tampilKonfirmasi = false;
        $this->paham = false;
        $this->catatan = 'Link diganti menjadi '.$this->hostPublik().'/'.$baru.'. Link lama dialihkan ke link baru selama '.PenahananSlug::HARI.' hari, lalu tidak aktif.';
    }

    private function hostPublik(): string
    {
        return parse_url(url('/'), PHP_URL_HOST) ?: 'undangyah.com';
    }

    private function galatSlug(Undangan $undangan): ?string
    {
        if ($this->cekTerbatas()) {
            return 'Terlalu banyak pengecekan. Tunggu sebentar lalu coba lagi.';
        }

        return app(SlugUndangan::class)->galat($this->slug, $undangan->id, (int) $undangan->user_id);
    }

    /** Saran alamat dari judul dan tahun acara; hanya yang format-nya valid dan belum dipakai. */
    private function saran(Undangan $undangan): array
    {
        $layanan = app(SlugUndangan::class);
        $dasar = $layanan->bersihkan($undangan->judul);
        $nama = str_contains($undangan->judul, '&')
            ? collect(explode('&', $undangan->judul, 2))->map(fn ($n) => $layanan->bersihkan($n))->filter()->implode('-')
            : '';
        $tahun = $undangan->tanggal_acara?->format('Y');

        $calon = collect([$dasar, $nama]);
        if ($tahun) {
            $calon->push($dasar.'-'.$tahun);
            if ($nama !== '') {
                $calon->push($nama.'-'.$tahun);
            }
        }
        if ($nama !== '') {
            $calon->push($nama.'-menikah', $nama.'-wedding', 'the-wedding-of-'.$nama);
        }
        $calon->push($dasar.'-undangan');
        foreach ([2, 3, 4] as $i) {
            $calon->push($dasar.'-'.$i);
        }

        $calon = $calon->filter(fn ($s) => $s !== '' && $s !== $undangan->slug && $layanan->galatFormat($s) === null)->unique()->values();
        if ($calon->isEmpty()) {
            return [];
        }
        $dipakai = [...Undangan::whereIn('slug', $calon)->pluck('slug')->all(), ...app(PenahananSlug::class)->ditahanLain($calon->all(), (int) $undangan->user_id)];

        return $calon->reject(fn ($s) => in_array($s, $dipakai, true))->take(5)->values()->all();
    }

    public function render()
    {
        $user = auth()->user();
        $daftar = Undangan::where('user_id', $user->id)->latest('updated_at')->latest('id')->get(['id', 'judul', 'slug', 'status']);
        $undangan = $this->undangan();
        $host = $this->hostPublik();

        $galat = null;
        $tersedia = false;
        $sama = false;
        $saran = [];
        $dampak = null;
        $riwayat = [];
        if ($undangan) {
            $sama = $this->slug === $undangan->slug;
            if (! $sama) {
                $galat = $this->galatSlug($undangan);
                $tersedia = $galat === null;
            }
            if ($undangan->sudahTerbit()) {
                $saran = $this->saran($undangan);
                $dampak = [
                    'terkirim' => $undangan->tamu()->whereNotNull('dikirim_at')->count(),
                    'rsvp' => $undangan->rsvp()->count(),
                    'ucapan' => $undangan->ucapan()->count(),
                    'kunjungan' => $undangan->tayangan()->count(),
                ];
            }
            $riwayat = collect((array) ($undangan->pengaturan['riwayat_slug'] ?? []))->map(fn ($r) => [
                'dari' => (string) ($r['dari'] ?? ''),
                'ke' => (string) ($r['ke'] ?? ''),
                'pada' => ! empty($r['pada']) ? Carbon::parse($r['pada'])->locale('id')->translatedFormat('j M Y').' · '.Carbon::parse($r['pada'])->locale('id')->diffForHumans() : '',
            ])->all();
        }

        return view('livewire.link-kustom', [
            'daftar' => $daftar,
            'undangan' => $undangan,
            'host' => $host,
            'galat' => $galat,
            'tersedia' => $tersedia,
            'sama' => $sama,
            'saran' => $saran,
            'dampak' => $dampak,
            'riwayat' => $riwayat,
            'min' => SlugUndangan::MIN,
            'maks' => SlugUndangan::MAKS,
            'tautanBagikan' => Route::has('bagikan') ? route('bagikan') : null,
            'tautanEditor' => $undangan && Route::has('edit-undangan') ? route('edit-undangan', ['undangan' => $undangan->id]) : null,
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : '#',
            'tautanBilling' => Route::has('billing') ? route('billing') : null,
        ])->layout('layouts::app', ['judul' => 'Link Kustom', 'sub' => 'Alamat undangan']);
    }
}
