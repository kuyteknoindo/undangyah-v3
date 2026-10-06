<?php

namespace App\Livewire;

use App\Livewire\Concerns\PilihUndangan;
use App\Models\RiwayatKirim as ModelRiwayatKirim;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Services\CatatPengiriman;
use App\Services\PesanUndangan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Riwayat pembukaan WhatsApp per tamu, plus tamu yang belum pernah dikirimi. */
class RiwayatKirim extends Component
{
    use PilihUndangan;

    public const PER_HALAMAN = 10;

    private const MAKS_EKSPOR = 5000;

    public bool $siap = false;

    public string $q = '';

    /** semua | whatsapp | email */
    public string $kanal = 'semua';

    /** semua | hari | 7 | 30 */
    public string $tgl = 'semua';

    /** semua | dibuka | belum */
    public string $status = 'semua';

    public int $hal = 1;

    public function mount(?string $undangan = null): void
    {
        $this->pilihAwal($undangan);
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    public function pilih(int $id): void
    {
        abort_unless(Undangan::where('user_id', auth()->id())->whereKey($id)->exists(), 404);
        $this->undanganId = $id;
        $this->resetFilter();
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'kanal', 'tgl', 'status');
        $this->hal = 1;
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 80);
        $this->hal = 1;
    }

    public function updatedKanal(): void
    {
        $this->kanal = in_array($this->kanal, ['semua', 'whatsapp', 'email'], true) ? $this->kanal : 'semua';
        $this->hal = 1;
    }

    public function updatedTgl(): void
    {
        $this->tgl = in_array($this->tgl, ['semua', 'hari', '7', '30'], true) ? $this->tgl : 'semua';
        $this->hal = 1;
    }

    public function aturStatus(string $status): void
    {
        $this->status = in_array($status, ['semua', 'dibuka', 'belum'], true) ? $status : 'semua';
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

    // ------------------------------------------------------------------ data

    private function escapeLike(string $kata): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($kata)).'%';
    }

    private function queryLog()
    {
        $query = ModelRiwayatKirim::query()->where('undangan_id', $this->undanganId);
        if (trim($this->q) !== '') {
            $query->whereRaw("lower(nama) like ? escape '\\'", [$this->escapeLike(trim($this->q))]);
        }
        if ($this->kanal !== 'semua') {
            $query->where('kanal', $this->kanal);
        }
        $mulai = match ($this->tgl) {
            'hari' => now()->startOfDay(),
            '7' => now()->subDays(6)->startOfDay(),
            '30' => now()->subDays(29)->startOfDay(),
            default => null,
        };
        if ($mulai) {
            $query->where('created_at', '>=', $mulai);
        }

        return $query;
    }

    private function queryBelum()
    {
        $query = Tamu::query()->where('undangan_id', $this->undanganId)->whereNull('dikirim_at');
        if (trim($this->q) !== '') {
            $query->whereRaw("lower(nama) like ? escape '\\'", [$this->escapeLike(trim($this->q))]);
        }

        return $query;
    }

    private function pakaiLog(): bool
    {
        return $this->status !== 'belum';
    }

    private function pakaiBelum(): bool
    {
        // Tamu belum dikirimi tidak punya tanggal dan hanya relevan untuk WhatsApp.
        return $this->status !== 'dibuka' && $this->tgl === 'semua' && in_array($this->kanal, ['semua', 'whatsapp'], true);
    }

    /** @return array{0:Collection,1:int} baris dan total */
    private function ambil(int $offset, int $limit): array
    {
        $nLog = $this->pakaiLog() ? $this->queryLog()->count() : 0;
        $nBelum = $this->pakaiBelum() ? $this->queryBelum()->count() : 0;

        $baris = collect();
        if ($nLog > 0 && $offset < $nLog) {
            $baris = $baris->concat($this->queryLog()->orderByDesc('created_at')->orderByDesc('id')->skip($offset)->take($limit)->get()->map(fn (ModelRiwayatKirim $r) => [
                'id' => 'r-'.$r->id, 'tamu_id' => $r->tamu_id, 'nama' => $r->nama, 'hp' => $r->hp, 'jenis' => $r->jenis, 'kanal' => $r->kanal, 'waktu' => $r->created_at, 'status' => 'dibuka',
            ]));
        }
        $sisa = $limit - $baris->count();
        if ($sisa > 0 && $nBelum > 0) {
            $geser = max(0, $offset - $nLog);
            $baris = $baris->concat($this->queryBelum()->orderBy('nama')->orderBy('id')->skip($geser)->take($sisa)->get()->map(fn (Tamu $t) => [
                'id' => 'p-'.$t->id, 'tamu_id' => $t->id, 'nama' => $t->nama, 'hp' => $t->hp, 'jenis' => 'undangan', 'kanal' => 'whatsapp', 'waktu' => null, 'status' => 'belum',
            ]));
        }

        return [$baris, $nLog + $nBelum];
    }

    // ------------------------------------------------------------------ aksi

    public function kirim(int $tamuId, string $jenis = 'undangan'): void
    {
        $undangan = $this->undanganTerpilih();
        abort_unless($undangan, 404);
        $tamu = Tamu::where('undangan_id', $undangan->id)->find($tamuId);
        abort_unless($tamu, 404);
        if (! $undangan->sudahTerbit()) {
            $this->dispatch('toast', pesan: 'Terbitkan undangan dulu sebelum mengirim.', tipe: 'galat');

            return;
        }
        if (app(CatatPengiriman::class)->catat(auth()->user(), $undangan, $tamu, $jenis)) {
            $this->dispatch('toast', pesan: 'Pengiriman untuk '.$tamu->nama.' dicatat.', tipe: 'sukses');
        } else {
            $this->dispatch('toast', pesan: 'Terlalu banyak pengiriman dalam semenit. Tunggu sebentar.', tipe: 'galat');
        }
    }

    public function ekspor(): ?StreamedResponse
    {
        $undangan = $this->undanganTerpilih();
        abort_unless($undangan, 404);
        $kunci = 'riwayat-ekspor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->dispatch('toast', pesan: 'Terlalu sering mengekspor. Coba lagi sebentar lagi.', tipe: 'galat');

            return null;
        }
        RateLimiter::hit($kunci, 60);

        [$baris] = $this->ambil(0, self::MAKS_EKSPOR);
        if ($baris->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada catatan untuk diekspor.', tipe: 'info');

            return null;
        }
        // Sel yang diawali = + - @ dianggap rumus oleh Excel/Sheets.
        $aman = fn (?string $v): string => ($v !== null && $v !== '' && str_contains("=+-@\t\r", $v[0])) ? "'".$v : (string) $v;
        $nama = 'riwayat-kirim-'.($undangan->slug ?: 'undangan').'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($baris, $aman) {
            $h = fopen('php://output', 'w');
            fwrite($h, "\xEF\xBB\xBF");
            fputcsv($h, ['Penerima', 'Nomor WhatsApp', 'Jenis', 'Kanal', 'Waktu', 'Status'], ',', '"', '');
            foreach ($baris as $b) {
                fputcsv($h, [
                    $aman($b['nama']),
                    $b['hp'] ? '+'.$b['hp'] : '',
                    $b['jenis'] === 'pengingat' ? 'Pengingat RSVP' : 'Undangan',
                    $b['kanal'] === 'email' ? 'Email' : 'WhatsApp',
                    $b['waktu'] ? $b['waktu']->timezone(config('app.timezone'))->format('Y-m-d H:i') : '',
                    $b['status'] === 'dibuka' ? 'Dibuka di WhatsApp' : 'Belum dikirim',
                ], ',', '"', '');
            }
            fclose($h);
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $layout = ['judul' => 'Riwayat Kirim', 'sub' => 'Pengiriman'];
        if (! $this->siap) {
            return view('livewire.riwayat-kirim', ['siap' => false])->layout('layouts::app', $layout);
        }
        $undangan = $this->undanganTerpilih();
        if (! $undangan) {
            return view('livewire.riwayat-kirim', [
                'siap' => true, 'undangan' => null, 'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : null,
            ])->layout('layouts::app', $layout);
        }

        [, $total] = $this->ambil(0, 0);
        $halaman = max(1, (int) ceil($total / self::PER_HALAMAN));
        $this->hal = min(max(1, $this->hal), $halaman);
        [$baris] = $this->ambil(($this->hal - 1) * self::PER_HALAMAN, self::PER_HALAMAN);

        $layanan = app(PesanUndangan::class);
        $daftarTemplate = $layanan->daftar(auth()->user());
        $pengaturan = $layanan->pengaturan(auth()->user(), $daftarTemplate);
        $tamu = Tamu::where('undangan_id', $undangan->id)->whereIn('id', $baris->pluck('tamu_id'))->get()->keyBy('id');
        $terbit = $undangan->sudahTerbit();
        $baris = $baris->map(function (array $b) use ($tamu, $undangan, $layanan, $daftarTemplate, $pengaturan, $terbit) {
            $t = $tamu[$b['tamu_id']] ?? null;
            $b['tamu'] = $t;
            $b['bisaWa'] = $t && $terbit && $b['kanal'] === 'whatsapp';
            $b['urlWa'] = $b['bisaWa'] ? PesanUndangan::urlWa($t->hp, $layanan->untukTamu($undangan, $t, $daftarTemplate, $pengaturan, $b['jenis'])) : '#';

            return $b;
        });

        $semuaTamu = Tamu::where('undangan_id', $undangan->id);
        $tercatat = ModelRiwayatKirim::where('undangan_id', $undangan->id)->count();
        $belum = (clone $semuaTamu)->whereNull('dikirim_at')->count();
        $sudah = (clone $semuaTamu)->whereNotNull('dikirim_at')->count();
        $tanpaNomor = (clone $semuaTamu)->where(fn ($w) => $w->whereNull('hp')->orWhere('hp', ''))->count();

        return view('livewire.riwayat-kirim', [
            'siap' => true,
            'undangan' => $undangan,
            'daftar' => $this->daftarUndangan(),
            'terbit' => $terbit,
            'baris' => $baris,
            'total' => $total,
            'halaman' => $halaman,
            'dari' => $total ? ($this->hal - 1) * self::PER_HALAMAN + 1 : 0,
            'sampai' => min($this->hal * self::PER_HALAMAN, $total),
            'ringkasan' => ['tercatat' => $tercatat, 'sudah' => $sudah, 'belum' => $belum, 'tanpaNomor' => $tanpaNomor],
            'kosongSemua' => $tercatat === 0 && $total === 0 && $this->q === '' && $this->kanal === 'semua' && $this->tgl === 'semua' && $this->status === 'semua',
            'hitungStatus' => ['semua' => $tercatat + $belum, 'dibuka' => $tercatat, 'belum' => $belum],
            'tautanBagikan' => Route::has('bagikan') ? route('bagikan', ['undangan' => $undangan->id]) : null,
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : null,
        ])->layout('layouts::app', $layout);
    }
}
