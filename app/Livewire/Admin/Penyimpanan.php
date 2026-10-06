<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\LogAksiAdmin;
use App\Models\Media;
use App\Services\KonfigStorage;
use App\Services\PenyimpanMedia;
use App\Services\ReferensiMedia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Admin > Storage: pengaturan unggahan (kuota per pengguna, batas per file, tipe) dan ringkasan pemakaian nyata
 * (agregasi SQL + ukuran folder di disk), serta pembersihan file yang tidak dipakai dan tidak dirujuk.
 */
class Penyimpanan extends Component
{
    use HakAdminKatalog;

    /** File yang baru diunggah belum tentu sempat ditempatkan pengguna di undangan; jangan dibersihkan terlalu cepat. */
    public const UMUR_MIN_JAM = 24;

    public const MAKS_BERSIH = 1000;

    private const CACHE_DISK = 'storage:ukuran-disk';

    public string $kuota = '';

    public string $maks = '';

    /** @var array<string,bool> */
    public array $tipe = [];

    public bool $dialogBersih = false;

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->muatForm();
    }

    private function muatForm(): void
    {
        $k = KonfigStorage::ambil();
        $this->kuota = (string) $k['kuota_mb'];
        $this->maks = self::teksAngka($k['maks_mb']);
        $this->tipe = $k['tipe'];
        $this->resetErrorBag();
    }

    private static function teksAngka(float|int $n): string
    {
        return rtrim(rtrim(number_format((float) $n, 2, ',', ''), '0'), ',');
    }

    // ------------------------------------------------------------------ pengaturan

    public function ubahTipe(string $kunci): void
    {
        $this->pastikanAdmin();
        if (array_key_exists($kunci, KonfigStorage::BAWAAN['tipe'])) {
            $this->tipe[$kunci] = ! ($this->tipe[$kunci] ?? false);
        }
    }

    public function kembalikanBawaan(): void
    {
        $this->pastikanAdmin();
        $this->kuota = (string) KonfigStorage::BAWAAN['kuota_mb'];
        $this->maks = self::teksAngka(KonfigStorage::BAWAAN['maks_mb']);
        $this->tipe = KonfigStorage::BAWAAN['tipe'];
        $this->resetErrorBag();
    }

    public function batalkanUbahan(): void
    {
        $this->pastikanAdmin();
        $this->muatForm();
    }

    public function simpan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $kuota = trim($this->kuota);
        $maks = str_replace(',', '.', trim($this->maks));
        $tipe = [];
        foreach (array_keys(KonfigStorage::BAWAAN['tipe']) as $k) {
            $tipe[$k] = (bool) ($this->tipe[$k] ?? false);
        }

        $galat = [];
        if (! preg_match('/^\d{1,6}$/', $kuota) || (int) $kuota < KonfigStorage::KUOTA_MIN || (int) $kuota > KonfigStorage::KUOTA_MAKS) {
            $galat['kuota'] = 'Isi kuota antara '.KonfigStorage::KUOTA_MIN.' dan '.number_format(KonfigStorage::KUOTA_MAKS, 0, ',', '.').' MB, dalam angka bulat.';
        }
        if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $maks) || (float) $maks < KonfigStorage::MAKS_MIN || (float) $maks > KonfigStorage::MAKS_MAKS) {
            $galat['maks'] = 'Batas per file antara '.str_replace('.', ',', (string) KonfigStorage::MAKS_MIN).' dan '.KonfigStorage::MAKS_MAKS.' MB (maks 2 angka desimal).';
        } elseif (! isset($galat['kuota']) && (float) $maks > (float) $kuota) {
            $galat['maks'] = 'Batas per file tidak boleh melebihi kuota pengguna.';
        }
        if (! in_array(true, $tipe, true)) {
            $galat['tipe'] = 'Izinkan minimal satu tipe file.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        KonfigStorage::simpan(['kuota_mb' => (int) $kuota, 'maks_mb' => (float) $maks, 'tipe' => $tipe]);
        LogAksiAdmin::catat(auth()->user(), 'storage_atur', null, ['kuota_mb' => (int) $kuota, 'maks_mb' => (float) $maks, 'tipe' => $tipe]);
        $this->muatForm();
        $this->dispatch('toast', pesan: 'Pengaturan storage disimpan.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ pembersihan

    /**
     * Media yang aman dibersihkan: tidak ditandai dipakai, tidak dirujuk undangan/tema/lagu, dan sudah lewat umur minimum.
     * Dihitung ulang di server tiap kali (tidak mempercayai daftar dari klien).
     *
     * @return Collection<int,Media>
     */
    private function kandidat(int $batas = self::MAKS_BERSIH): Collection
    {
        $hasil = collect();
        $ref = app(ReferensiMedia::class);
        Media::where('dipakai', false)->where('created_at', '<=', now()->subHours(self::UMUR_MIN_JAM))->orderBy('id')
            ->chunkById(300, function ($kelompok) use ($ref, $batas, &$hasil) {
                $rujukan = $ref->cari($kelompok);
                $hasil = $hasil->concat($kelompok->reject(fn (Media $m) => isset($rujukan[$m->id])));

                return $hasil->count() < $batas;
            });

        return $hasil->take($batas)->values();
    }

    public function tanyaBersih(): void
    {
        $this->pastikanAdmin();
        if ($this->kandidat(1)->isEmpty()) {
            $this->dispatch('toast', pesan: 'Tidak ada file yang aman dibersihkan.', tipe: 'info');

            return;
        }
        $this->dialogBersih = true;
    }

    public function bersihkan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $this->dialogBersih = false;
        $kunci = 'storage-bersih:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, 5)) {
            $this->dispatch('toast', pesan: 'Terlalu sering membersihkan. Coba lagi sebentar lagi.', tipe: 'galat');

            return;
        }
        RateLimiter::hit($kunci, 60);

        $n = 0;
        $byte = 0;
        foreach ($this->kandidat() as $m) {
            $ukuran = $m->ukuran;
            $m->delete();
            $n++;
            $byte += $ukuran;
        }
        Cache::forget(self::CACHE_DISK);
        if ($n === 0) {
            $this->dispatch('toast', pesan: 'Tidak ada file yang aman dibersihkan.', tipe: 'info');

            return;
        }
        LogAksiAdmin::catat(auth()->user(), 'storage_bersih', null, ['jumlah' => $n, 'byte' => $byte]);
        $this->dispatch('toast', pesan: $n.' file tidak terpakai dihapus, '.PenyimpanMedia::teksUkuran($byte).' dibebaskan.', tipe: 'sukses');
    }

    public function hitungUlang(): void
    {
        $this->pastikanAdmin();
        Cache::forget(self::CACHE_DISK);
        $this->dispatch('toast', pesan: 'Penyimpanan dihitung ulang.', tipe: 'info');
    }

    // ------------------------------------------------------------------ tampilan

    /** @return array{byte:int,file:int,dihitung:string} ukuran nyata folder media di disk (termasuk thumbnail), di-cache 2 menit */
    private function ukuranDisk(): array
    {
        return Cache::remember(self::CACHE_DISK, 120, function () {
            $byte = 0;
            $file = 0;
            $akar = Storage::disk(Media::DISK)->path('');
            if (is_dir($akar)) {
                $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($akar, \FilesystemIterator::SKIP_DOTS));
                foreach ($it as $f) {
                    if ($f->isFile()) {
                        $byte += (int) $f->getSize();
                        $file++;
                    }
                }
            }

            return ['byte' => $byte, 'file' => $file, 'dihitung' => now()->format('H:i:s')];
        });
    }

    public function render()
    {
        $this->pastikanAdmin();
        $konfig = KonfigStorage::ambil();

        $perTipe = Media::query()->selectRaw('tipe, count(*) as n, coalesce(sum(ukuran), 0) as ukuran')->groupBy('tipe')->get()->keyBy('tipe');
        $jumlah = (int) $perTipe->sum('n');
        $total = (int) $perTipe->sum('ukuran');
        $takTerpakai = Media::where('dipakai', false)->selectRaw('count(*) as n, coalesce(sum(ukuran), 0) as ukuran')->first();

        $perPengguna = Media::query()
            ->leftJoin('users', 'users.id', '=', 'media.user_id')
            ->selectRaw('media.user_id, users.name as nama, count(*) as n, coalesce(sum(media.ukuran), 0) as ukuran')
            ->groupBy('media.user_id', 'users.name')
            ->orderByDesc('ukuran')->limit(8)->get();
        $penggunaMelebihi = Media::query()->whereNotNull('user_id')->selectRaw('user_id, sum(ukuran) as ukuran')->groupBy('user_id')
            ->havingRaw('sum(ukuran) > ?', [$konfig['kuota_byte']])->get()->count();

        $terbesar = Media::query()->orderByDesc('ukuran')->limit(6)->get(['id', 'nama', 'tipe', 'ukuran', 'dipakai', 'user_id']);

        $kandidat = $this->dialogBersih ? $this->kandidat() : collect();
        $path = storage_path();
        $disk = ['total' => (int) @disk_total_space($path), 'bebas' => (int) @disk_free_space($path)];

        $form = ['kuota' => (string) $konfig['kuota_mb'], 'maks' => self::teksAngka($konfig['maks_mb']), 'tipe' => $konfig['tipe']];
        $kotor = $form['kuota'] !== trim($this->kuota) || $form['maks'] !== trim($this->maks) || $form['tipe'] != $this->tipe;

        return view('livewire.admin.storage.index', [
            'konfig' => $konfig,
            'perTipe' => $perTipe,
            'jumlah' => $jumlah,
            'total' => $total,
            'takTerpakai' => $takTerpakai,
            'perPengguna' => $perPengguna,
            'penggunaMelebihi' => $penggunaMelebihi,
            'terbesar' => $terbesar,
            'kandidat' => $kandidat,
            'ukuranDisk' => $this->ukuranDisk(),
            'disk' => $disk,
            'kotor' => $kotor,
            'bawaan' => KonfigStorage::adaBawaan(),
            'batasPhp' => ini_get('upload_max_filesize'),
            'batasPost' => ini_get('post_max_size'),
            'umurMin' => self::UMUR_MIN_JAM,
            'maksBersih' => self::MAKS_BERSIH,
        ])->layout('layouts::app', ['judul' => 'Storage', 'sub' => 'Sistem', 'admin' => true]);
    }
}
