<?php

namespace App\Livewire;

use App\Livewire\Concerns\PilihUndanganMilik;
use App\Models\HadiahMasuk;
use App\Models\Undangan;
use App\Services\CsvTamu;
use App\Services\HadiahUndangan;
use App\Services\PengaturanUndangan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Hadiah digital: rekening/e-wallet dan alamat kado (tampil di undangan), ucapan terima kasih, dan catatan hadiah masuk. */
class HadiahDigital extends Component
{
    use PilihUndanganMilik;

    public const PER_HALAMAN = 10;

    public const MAKS_UCAPAN = 200;

    public const MAKS_NOMINAL = 1000000000;

    private const MAKS_CATATAN = 500;

    private const MAKS_EKSPOR = 5000;

    private const KUNCI_PENGATURAN = 'hadiah';

    public bool $siap = false;

    public string $filter = 'semua';

    public int $halaman = 1;

    // Dialog rekening
    public bool $tampilRek = false;

    #[Locked]
    public ?int $rekId = null;

    public string $rJenis = 'bank';

    public string $rBank = '';

    public string $rNo = '';

    public string $rAn = '';

    #[Locked]
    public ?int $hapusRekId = null;

    // Dialog alamat
    public bool $tampilAlamat = false;

    public string $aNama = '';

    public string $aHp = '';

    public string $aAlamat = '';

    public bool $hapusAlamatTanya = false;

    // Ucapan terima kasih
    public string $ucapanTerima = '';

    // Dialog catat hadiah
    public bool $tampilHadiah = false;

    public string $tNama = '';

    public string $tVia = '';

    public string $tNominal = '';

    public string $tPesan = '';

    public bool $tDone = true;

    #[Locked]
    public ?int $hapusHadiahId = null;

    public function mount(?string $undangan = null): void
    {
        $this->tentukanUndanganAwal($undangan);
        $this->muatUcapanTerima();
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    protected function setelahPilihUndangan(): void
    {
        $this->filter = 'semua';
        $this->halaman = 1;
        $this->tampilRek = $this->tampilAlamat = $this->tampilHadiah = $this->hapusAlamatTanya = false;
        $this->hapusRekId = $this->hapusHadiahId = null;
        $this->muatUcapanTerima();
    }

    private function muatUcapanTerima(): void
    {
        $u = $this->undanganTerpilih();
        $this->ucapanTerima = $u ? (string) app(PengaturanUndangan::class)->ambil($u, self::KUNCI_PENGATURAN, ['ucapan_terima' => ''])['ucapan_terima'] : '';
    }

    // ------------------------------------------------------------------ bantu

    private function bersihkan(string $teks, bool $banyakBaris = false): string
    {
        $teks = strip_tags(str_replace(["\r\n", "\r"], "\n", $teks));
        $teks = preg_replace($banyakBaris ? '/[\x00-\x09\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u' : '/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', ' ', $teks) ?? '';
        $teks = preg_replace($banyakBaris ? '/[ \t]+/u' : '/\s+/u', ' ', $teks) ?? '';

        return trim($teks);
    }

    private function batasi(string $kunci, int $maks, int $detik): bool
    {
        $kunci .= ':'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, $maks)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak perubahan dalam waktu singkat. Coba lagi sebentar lagi.', tipe: 'galat');

            return true;
        }
        RateLimiter::hit($kunci, $detik);

        return false;
    }

    private function hadiahMilik(int $id): HadiahMasuk
    {
        return HadiahMasuk::query()->where('undangan_id', $this->undanganWajib()->id)->findOrFail($id);
    }

    private function ubahSemua(string $kunci, string $pesan, callable $aksi): void
    {
        if ($this->batasi($kunci, 60, 60)) {
            return;
        }
        $aksi();
        $this->dispatch('toast', pesan: $pesan, tipe: 'sukses');
    }

    // ------------------------------------------------------------------ rekening

    public function bukaRek(?int $id = null): void
    {
        $u = $this->undanganWajib();
        $layanan = app(HadiahUndangan::class);
        $this->resetErrorBag();

        if ($id === null) {
            if (count($layanan->rekening($u)) >= HadiahUndangan::MAKS_REKENING) {
                $this->dispatch('toast', pesan: 'Maksimal '.HadiahUndangan::MAKS_REKENING.' rekening atau e-wallet.', tipe: 'galat');

                return;
            }
            $this->rekId = null;
            $this->rJenis = 'bank';
            $this->rBank = HadiahUndangan::BANK[0];
            $this->rNo = '';
            $this->rAn = (string) auth()->user()->name;
        } else {
            abort_unless($layanan->adaRekening($u, $id), 404);
            $r = collect($layanan->rekening($u))->firstWhere('id', $id);
            $this->rekId = $id;
            $this->rJenis = $r['jenis'];
            $this->rBank = $r['bank'];
            $this->rNo = $r['no'];
            $this->rAn = $r['an'];
        }
        $this->tampilRek = true;
    }

    public function pilihJenis(string $jenis): void
    {
        $this->rJenis = $jenis === 'ewallet' ? 'ewallet' : 'bank';
        $this->rBank = $this->rJenis === 'ewallet' ? HadiahUndangan::EWALLET[0] : HadiahUndangan::BANK[0];
        $this->resetErrorBag(['rBank', 'rNo']);
    }

    public function simpanRek(): void
    {
        $u = $this->undanganWajib();
        $layanan = app(HadiahUndangan::class);
        $ewallet = $this->rJenis === 'ewallet';

        $this->rBank = $this->bersihkan($this->rBank);
        $this->rAn = $this->bersihkan($this->rAn);
        $angka = preg_replace('/[\s\-]/', '', $this->rNo) ?? '';

        $lama = $this->rekId ? collect($layanan->rekening($u))->firstWhere('id', $this->rekId) : null;
        if ($this->rekId !== null) {
            abort_unless($lama, 404);
        }
        // Nama bank yang diketik bebas di editor boleh dipertahankan; selain itu harus dari daftar.
        $daftar = $ewallet ? HadiahUndangan::EWALLET : HadiahUndangan::BANK;
        if ($lama && $lama['bank'] !== '' && $lama['bank'] === $this->rBank && (HadiahUndangan::ewallet($lama['bank']) === $ewallet)) {
            $daftar[] = $lama['bank'];
        }

        $galat = [];
        if (! in_array($this->rBank, $daftar, true)) {
            $galat['rBank'] = 'Pilih '.($ewallet ? 'e-wallet' : 'bank').' dari daftar.';
        }
        if ($angka === '') {
            $galat['rNo'] = 'Nomor wajib diisi.';
        } elseif (! ctype_digit($angka)) {
            $galat['rNo'] = 'Nomor hanya boleh berisi angka.';
        } elseif ($ewallet ? (strlen($angka) < 8 || strlen($angka) > 15) : (strlen($angka) < 6 || strlen($angka) > 20)) {
            $galat['rNo'] = $ewallet ? 'Nomor e-wallet harus 8 sampai 15 angka.' : 'Nomor rekening harus 6 sampai 20 angka.';
        } elseif (collect($layanan->rekening($u))->contains(fn ($r) => $r['id'] !== $this->rekId && $r['bank'] === $this->rBank && preg_replace('/\D/', '', $r['no']) === $angka)) {
            $galat['rNo'] = 'Nomor ini sudah tersimpan untuk '.$this->rBank.'.';
        }
        if (mb_strlen($this->rAn) < 2) {
            $galat['rAn'] = 'Nama pemilik wajib diisi (minimal 2 huruf).';
        } elseif (mb_strlen($this->rAn) > 100) {
            $galat['rAn'] = 'Nama pemilik maksimal 100 karakter.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }
        if ($this->rekId === null && count($layanan->rekening($u)) >= HadiahUndangan::MAKS_REKENING) {
            $this->dispatch('toast', pesan: 'Maksimal '.HadiahUndangan::MAKS_REKENING.' rekening atau e-wallet.', tipe: 'galat');

            return;
        }

        $baru = $this->rekId === null;
        $this->ubahSemua('hadiah-ubah', 'Rekening '.$this->rBank.($baru ? ' ditambahkan' : ' diperbarui').'.', function () use ($layanan, $u, $angka) {
            $layanan->simpanRekening($u, $this->rekId, $this->rBank, HadiahUndangan::formatNomor($angka), $this->rAn);
            $this->tampilRek = false;
        });
    }

    public function tanyaHapusRek(int $id): void
    {
        abort_unless(app(HadiahUndangan::class)->adaRekening($this->undanganWajib(), $id), 404);
        $this->hapusRekId = $id;
    }

    public function batalHapusRek(): void
    {
        $this->hapusRekId = null;
    }

    public function hapusRek(): void
    {
        $u = $this->undanganWajib();
        $id = $this->hapusRekId;
        $this->hapusRekId = null;
        if ($id === null) {
            return;
        }
        abort_unless(app(HadiahUndangan::class)->adaRekening($u, $id), 404);
        $this->ubahSemua('hadiah-ubah', 'Rekening dihapus.', fn () => app(HadiahUndangan::class)->hapusRekening($u, $id));
    }

    // ------------------------------------------------------------------ alamat kado

    public function bukaAlamat(): void
    {
        $a = app(HadiahUndangan::class)->alamat($this->undanganWajib());
        $this->resetErrorBag();
        $this->aNama = $a['penerima'] ?? '';
        $this->aHp = $a['hp'] ?? '';
        $this->aAlamat = $a['alamat'] ?? '';
        $this->tampilAlamat = true;
    }

    public function simpanAlamat(): void
    {
        $u = $this->undanganWajib();
        $this->aNama = $this->bersihkan($this->aNama);
        $this->aHp = $this->bersihkan($this->aHp);
        $this->aAlamat = $this->bersihkan($this->aAlamat, true);
        $hp = preg_replace('/[\s\-]/', '', $this->aHp) ?? '';

        $galat = [];
        if (mb_strlen($this->aNama) < 2) {
            $galat['aNama'] = 'Nama penerima wajib diisi.';
        } elseif (mb_strlen($this->aNama) > 100) {
            $galat['aNama'] = 'Nama penerima maksimal 100 karakter.';
        }
        if ($hp !== '' && ! preg_match('/^(\+62|62|0)[0-9]{8,13}$/', $hp)) {
            $galat['aHp'] = 'Nomor telepon tidak valid. Contoh: 08123456789.';
        }
        if (mb_strlen($this->aAlamat) < 10) {
            $galat['aAlamat'] = 'Alamat lengkap wajib diisi (minimal 10 karakter).';
        } elseif (mb_strlen($this->aAlamat) > 300) {
            $galat['aAlamat'] = 'Alamat maksimal 300 karakter.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $this->ubahSemua('hadiah-ubah', 'Alamat kado disimpan.', function () use ($u, $hp) {
            app(HadiahUndangan::class)->simpanAlamat($u, $this->aNama, $hp, $this->aAlamat);
            $this->tampilAlamat = false;
        });
    }

    public function tanyaHapusAlamat(): void
    {
        $this->hapusAlamatTanya = true;
    }

    public function batalHapusAlamat(): void
    {
        $this->hapusAlamatTanya = false;
    }

    public function hapusAlamat(): void
    {
        $u = $this->undanganWajib();
        $this->hapusAlamatTanya = false;
        $this->ubahSemua('hadiah-ubah', 'Alamat kado dihapus.', fn () => app(HadiahUndangan::class)->hapusAlamat($u));
    }

    // ------------------------------------------------------------------ ucapan terima kasih

    public function simpanUcapanTerima(): void
    {
        $u = $this->undanganWajib();
        $teks = $this->bersihkan($this->ucapanTerima, true);
        if (mb_strlen($teks) > self::MAKS_UCAPAN) {
            throw ValidationException::withMessages(['ucapanTerima' => 'Ucapan maksimal '.self::MAKS_UCAPAN.' karakter.']);
        }
        $this->ucapanTerima = $teks;
        $this->ubahSemua('hadiah-ubah', $teks === '' ? 'Ucapan terima kasih dikosongkan.' : 'Ucapan terima kasih disimpan.', fn () => app(PengaturanUndangan::class)->simpan($u, self::KUNCI_PENGATURAN, ['ucapan_terima' => $teks]));
    }

    // ------------------------------------------------------------------ hadiah masuk

    /** Pilihan "Diterima lewat": rekening yang ada di undangan + Tunai + Lainnya. @return list<string> */
    private function pilihanVia(Undangan $u): array
    {
        $nama = collect(app(HadiahUndangan::class)->rekening($u))->pluck('bank')->filter()->unique()->values()->all();

        return [...$nama, 'Tunai', 'Lainnya'];
    }

    public function bukaHadiah(): void
    {
        $this->resetErrorBag();
        $this->tNama = '';
        $this->tVia = $this->pilihanVia($this->undanganWajib())[0];
        $this->tNominal = '';
        $this->tPesan = '';
        $this->tDone = true;
        $this->tampilHadiah = true;
    }

    public function simpanHadiah(): void
    {
        $u = $this->undanganWajib();
        $this->tNama = $this->bersihkan($this->tNama);
        $this->tPesan = $this->bersihkan($this->tPesan);
        $angka = preg_replace('/\D/', '', $this->tNominal) ?? '';

        $galat = [];
        if (mb_strlen($this->tNama) < 2) {
            $galat['tNama'] = 'Nama pemberi wajib diisi.';
        } elseif (mb_strlen($this->tNama) > 80) {
            $galat['tNama'] = 'Nama pemberi maksimal 80 karakter.';
        }
        if (! in_array($this->tVia, $this->pilihanVia($u), true)) {
            $galat['tVia'] = 'Pilih salah satu dari daftar.';
        }
        if ($angka === '' || (int) $angka === 0) {
            $galat['tNominal'] = 'Nominal wajib diisi.';
        } elseif (strlen($angka) > 10 || (int) $angka > self::MAKS_NOMINAL) {
            $galat['tNominal'] = 'Nominal terlalu besar.';
        }
        if (mb_strlen($this->tPesan) > 200) {
            $galat['tPesan'] = 'Pesan maksimal 200 karakter.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }
        if (HadiahMasuk::query()->where('undangan_id', $u->id)->count() >= self::MAKS_CATATAN) {
            $this->dispatch('toast', pesan: 'Catatan hadiah sudah mencapai '.self::MAKS_CATATAN.'. Hapus yang tidak perlu dulu.', tipe: 'galat');

            return;
        }

        $this->ubahSemua('hadiah-catat', 'Hadiah dari '.$this->tNama.' dicatat.', function () use ($u, $angka) {
            HadiahMasuk::query()->create([
                'undangan_id' => $u->id, 'nama' => $this->tNama, 'via' => $this->tVia, 'nominal' => (int) $angka,
                'terkonfirmasi' => $this->tDone, 'pesan' => $this->tPesan !== '' ? $this->tPesan : null,
            ]);
            $this->tampilHadiah = false;
            $this->halaman = 1;
        });
    }

    public function konfirmasi(int $id): void
    {
        $h = $this->hadiahMilik($id);
        $h->forceFill(['terkonfirmasi' => true])->save();
        $this->dispatch('toast', pesan: 'Hadiah dari '.$h->nama.' dikonfirmasi.', tipe: 'sukses');
    }

    public function tanyaHapusHadiah(int $id): void
    {
        $this->hapusHadiahId = $this->hadiahMilik($id)->id;
    }

    public function batalHapusHadiah(): void
    {
        $this->hapusHadiahId = null;
    }

    public function hapusHadiah(): void
    {
        if (! $this->hapusHadiahId) {
            return;
        }
        $this->hadiahMilik($this->hapusHadiahId)->delete();
        $this->hapusHadiahId = null;
        $this->dispatch('toast', pesan: 'Catatan hadiah dihapus.', tipe: 'sukses');
    }

    public function pilihFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['semua', 'done', 'pending'], true) ? $filter : 'semua';
        $this->halaman = 1;
    }

    public function ke(int $halaman): void
    {
        $this->halaman = max(1, $halaman);
    }

    private function kueriHadiah(Undangan $u): Builder
    {
        $q = HadiahMasuk::query()->where('undangan_id', $u->id);
        match ($this->filter) {
            'done' => $q->where('terkonfirmasi', true),
            'pending' => $q->where('terkonfirmasi', false),
            default => null,
        };

        return $q->orderByDesc('id');
    }

    public function ekspor(): ?StreamedResponse
    {
        $u = $this->undanganWajib();
        $kunci = 'hadiah-ekspor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 10)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak ekspor. Coba lagi sebentar lagi.', tipe: 'galat');

            return null;
        }
        RateLimiter::hit($kunci, 60);

        $baris = $this->kueriHadiah($u)->limit(self::MAKS_EKSPOR)->get();
        if ($baris->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada hadiah untuk diekspor.', tipe: 'info');

            return null;
        }
        $isi = app(CsvTamu::class)->tulis(['Pemberi', 'Lewat', 'Nominal (Rp)', 'Status', 'Pesan', 'Waktu'], $baris->map(fn (HadiahMasuk $h) => [
            $h->nama, $h->via, $h->nominal, $h->terkonfirmasi ? 'Terkonfirmasi' : 'Menunggu', $h->pesan, $h->created_at?->format('Y-m-d H:i'),
        ]));

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'hadiah-'.($u->slug ?: 'undangan').'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $daftar = $this->daftarUndangan();
        $u = $this->undanganTerpilih();
        $data = null;

        if ($this->siap && $u) {
            $layanan = app(HadiahUndangan::class);
            $r = HadiahMasuk::query()->where('undangan_id', $u->id)->toBase()
                ->selectRaw('count(*) as total, sum(case when terkonfirmasi then 1 else 0 end) as selesai, coalesce(sum(case when terkonfirmasi then nominal else 0 end), 0) as uang')->first();
            $item = $this->kueriHadiah($u)->paginate(self::PER_HALAMAN, ['*'], 'halaman', max(1, $this->halaman));
            if ($item->isEmpty() && $item->total() > 0) {
                $this->halaman = $item->lastPage();
                $item = $this->kueriHadiah($u)->paginate(self::PER_HALAMAN, ['*'], 'halaman', $this->halaman);
            }
            $total = (int) ($r->total ?? 0);
            $selesai = (int) ($r->selesai ?? 0);
            $data = [
                'rekening' => $layanan->rekening($u),
                'alamat' => $layanan->alamat($u),
                'item' => $item,
                'ringkas' => ['total' => $total, 'selesai' => $selesai, 'menunggu' => $total - $selesai, 'uang' => (int) ($r->uang ?? 0)],
                'via' => $this->pilihanVia($u),
                'bagianDisembunyikan' => in_array('kado', (array) ($u->bagian['sembunyi'] ?? []), true),
                'adaPerubahan' => (bool) $u->ada_perubahan,
            ];
        }

        return view('livewire.hadiah.index', [
            'daftar' => $daftar,
            'undangan' => $u,
            'data' => $data,
            'bank' => HadiahUndangan::BANK,
            'ewallet' => HadiahUndangan::EWALLET,
            'tautanBuat' => Route::has('buat-undangan') ? route('buat-undangan') : '#',
            'tautanEditor' => $u && Route::has('edit-undangan') ? route('edit-undangan', ['undangan' => $u->id]) : '#',
        ])->layout('layouts::app', ['judul' => 'Hadiah Digital', 'sub' => 'Kelola undangan']);
    }
}
