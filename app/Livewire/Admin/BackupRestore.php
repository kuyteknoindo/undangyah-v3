<?php

namespace App\Livewire\Admin;

use App\Jobs\JalankanBackup;
use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Livewire\Concerns\MintaKonfirmasiSandi;
use App\Models\Backup;
use App\Models\LogAksiAdmin;
use App\Models\Pengaturan;
use App\Models\User;
use App\Services\BackupGagal;
use App\Services\PembuatBackup;
use App\Services\PengaturanBackup;
use App\Services\UjiPulihBackup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Admin > Backup dan Restore. Membuat, memverifikasi, mengunduh, dan menghapus backup, serta "Uji pulihkan" ke database
 * sementara. Restore ke database hidup SENGAJA tidak ada di sini: itu keputusan dan tindakan pemilik (instruksi manual).
 * Bila kelak ada aksi restore, taruh `butuhSandi('Restore backup', ...)` di awal metodenya seperti hapus/ujiPulih/unduh.
 * Aksi sensitif di halaman ini (wajib konfirmasi sandi): hapus backup, terapkan retensi (hapus massal), uji pulih, unduh berkas.
 */
class BackupRestore extends Component
{
    use HakAdminKatalog;
    use MintaKonfirmasiSandi;

    private const PER_HALAMAN = 30;

    private const BATAS_UJI_PER_JAM = 2;

    private const BATAS_VERIFIKASI_PER_JAM = 30;

    public string $label = '';

    /** database | file | penuh */
    public string $tipe = 'database';

    public bool $otomatis = false;

    public string $frekuensi = 'harian';

    public string $retensiHari = '30';

    public string $simpanMinimal = '5';

    public string $tipeOtomatis = 'database';

    public bool $dialogHapus = false;

    #[Locked]
    public ?int $hapusId = null;

    public bool $dialogUji = false;

    #[Locked]
    public ?int $ujiId = null;

    public bool $dialogRetensi = false;

    #[Locked]
    public array $hasilUji = [];

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->muatPengaturan();
    }

    private function muatPengaturan(): void
    {
        $p = PengaturanBackup::ambil();
        $this->otomatis = $p['otomatis'];
        $this->frekuensi = $p['frekuensi'];
        $this->retensiHari = (string) $p['retensi_hari'];
        $this->simpanMinimal = (string) $p['simpan_minimal'];
        $this->tipeOtomatis = $p['tipe'];
    }

    public function pilihTipe(string $tipe): void
    {
        $this->tipe = isset(Backup::TIPE[$tipe]) ? $tipe : 'database';
    }

    // ------------------------------------------------------------------ pengaturan otomatis

    public function simpanPengaturan(): void
    {
        $admin = $this->pastikanAdminDapat();
        if (! $this->izinTulis()) {
            return;
        }
        $nilai = $this->validate([
            'otomatis' => ['boolean'],
            'frekuensi' => ['required', 'in:'.implode(',', array_keys(PengaturanBackup::FREKUENSI))],
            'retensiHari' => ['required', 'in:'.implode(',', PengaturanBackup::RETENSI_HARI)],
            'simpanMinimal' => ['required', 'in:'.implode(',', PengaturanBackup::SIMPAN_MINIMAL)],
            'tipeOtomatis' => ['required', 'in:'.implode(',', array_keys(PengaturanBackup::TIPE_OTOMATIS))],
        ], [], ['frekuensi' => 'Frekuensi', 'retensiHari' => 'Lama simpan', 'simpanMinimal' => 'Jumlah minimum', 'tipeOtomatis' => 'Isi backup otomatis']);

        $sebelum = PengaturanBackup::ambil();
        $baru = [
            'otomatis' => (bool) $nilai['otomatis'],
            'frekuensi' => $nilai['frekuensi'],
            'retensi_hari' => (int) $nilai['retensiHari'],
            'simpan_minimal' => (int) $nilai['simpanMinimal'],
            'tipe' => $nilai['tipeOtomatis'],
        ];
        Pengaturan::simpan('backup', $baru + ['diubah_oleh' => $admin->id, 'diubah_at' => now()->toIso8601String()]);
        LogAksiAdmin::catat($admin, 'atur_backup', null, ['sebelum' => $sebelum, 'sesudah' => $baru]);
        $this->dispatch('toast', pesan: 'Pengaturan backup otomatis disimpan.', tipe: 'sukses');
    }

    public function batalkanPengaturan(): void
    {
        $this->pastikanAdmin();
        $this->resetValidation();
        $this->muatPengaturan();
    }

    // ------------------------------------------------------------------ buat backup

    public function buatCepat(PembuatBackup $pembuat): void
    {
        $this->tipe = 'database';
        $this->label = '';
        $this->buat($pembuat);
    }

    public function buat(PembuatBackup $pembuat): void
    {
        $admin = $this->pastikanAdminDapat();
        $this->validate([
            'tipe' => ['required', 'in:'.implode(',', array_keys(Backup::TIPE))],
            'label' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z0-9._\- ]+$/'],
        ], ['label.regex' => 'Nama backup hanya boleh huruf, angka, spasi, strip, titik, dan garis bawah.'], ['label' => 'Nama backup', 'tipe' => 'Tipe backup']);

        $kunci = 'backup-buat:'.$admin->id;
        $maks = (int) config('backup.maks_per_jam', 3);
        if (RateLimiter::tooManyAttempts($kunci, $maks)) {
            $this->dispatch('toast', pesan: "Batas {$maks} backup per jam tercapai. Coba lagi dalam ".ceil(RateLimiter::availableIn($kunci) / 60).' menit.', tipe: 'galat');

            return;
        }

        try {
            $backup = $pembuat->mulai($this->tipe, $admin, $this->label);
        } catch (BackupGagal $e) {
            $this->dispatch('toast', pesan: $e->getMessage(), tipe: 'galat');

            return;
        }
        RateLimiter::hit($kunci, 3600);
        LogAksiAdmin::catat($admin, 'buat_backup', null, ['backup_id' => $backup->id, 'tipe' => $backup->tipe]);
        $this->label = '';

        if (config('backup.antrean')) {
            JalankanBackup::dispatch($backup->id);
            $this->dispatch('toast', pesan: 'Backup dimasukkan ke antrean. Status diperbarui otomatis.', tipe: 'info');

            return;
        }

        $backup = $pembuat->jalankan($backup);
        $backup->sukses()
            ? $this->dispatch('toast', pesan: 'Backup selesai ('.PembuatBackup::ukuran($backup->ukuran).').', tipe: 'sukses')
            : $this->dispatch('toast', pesan: 'Backup gagal: '.$backup->pesan_galat, tipe: 'galat');
    }

    // ------------------------------------------------------------------ verifikasi

    public function verifikasi(int $id, PembuatBackup $pembuat): void
    {
        $admin = $this->pastikanAdminDapat();
        $backup = Backup::findOrFail($id);
        $kunci = 'backup-verifikasi:'.$admin->id;
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_VERIFIKASI_PER_JAM)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak verifikasi dalam satu jam. Coba lagi nanti.', tipe: 'galat');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        $hasil = $pembuat->verifikasi($backup);
        LogAksiAdmin::catat($admin, 'verifikasi_backup', null, ['backup_id' => $backup->id, 'ok' => $hasil['ok']]);
        $this->dispatch('toast', pesan: $hasil['ok'] ? 'Verifikasi lolos: checksum cocok dan struktur valid.' : 'Verifikasi GAGAL: '.$hasil['pesan'], tipe: $hasil['ok'] ? 'sukses' : 'galat');
    }

    // ------------------------------------------------------------------ hapus

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $backup = Backup::findOrFail($id);
        if ($backup->status === Backup::BERJALAN) {
            $this->dispatch('toast', pesan: 'Backup yang sedang berjalan tidak bisa dihapus.', tipe: 'galat');

            return;
        }
        $this->hapusId = $backup->id;
        $this->dialogHapus = true;
    }

    public function hapus(PembuatBackup $pembuat): void
    {
        $admin = $this->pastikanAdminDapat();
        if (! $this->izinTulis()) {
            return;
        }
        if (! $this->butuhSandi('Hapus backup', 'hapus')) {
            return;
        }
        $backup = $this->hapusId ? Backup::find($this->hapusId) : null;
        $this->dialogHapus = false;
        $this->hapusId = null;
        if (! $backup) {
            return;
        }

        try {
            $pembuat->hapus($backup);
        } catch (BackupGagal $e) {
            $this->dispatch('toast', pesan: $e->getMessage(), tipe: 'galat');

            return;
        }
        LogAksiAdmin::catat($admin, 'hapus_backup', null, ['backup_id' => $backup->id, 'berkas' => $backup->berkas]);
        $this->dispatch('toast', pesan: 'Backup dihapus.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ retensi

    public function tanyaRetensi(): void
    {
        $this->pastikanAdmin();
        $this->dialogRetensi = true;
    }

    public function terapkanRetensi(PembuatBackup $pembuat): void
    {
        $admin = $this->pastikanAdminDapat();
        if (! $this->izinTulis()) {
            return;
        }
        if (! $this->butuhSandi('Hapus backup lama (retensi)', 'terapkanRetensi')) {
            return;
        }
        $p = PengaturanBackup::ambil();
        $hapus = $pembuat->terapkanRetensi($p['retensi_hari'], $p['simpan_minimal']);
        $this->dialogRetensi = false;
        LogAksiAdmin::catat($admin, 'retensi_backup', null, ['dihapus' => $hapus->count(), 'hari' => $p['retensi_hari'], 'minimal' => $p['simpan_minimal']]);
        $this->dispatch('toast', pesan: $hapus->count().' backup lama dihapus.', tipe: $hapus->isEmpty() ? 'info' : 'sukses');
    }

    // ------------------------------------------------------------------ uji pulihkan

    public function tanyaUji(int $id, UjiPulihBackup $uji): void
    {
        $this->pastikanAdmin();
        $backup = Backup::findOrFail($id);
        $kem = $uji->kemampuan();
        if (! $kem['bisa'] || ! $backup->bisaDiujiPulih()) {
            $this->dispatch('toast', pesan: $kem['bisa'] ? 'Backup ini tidak bisa diuji pulih.' : (string) $kem['alasan'], tipe: 'galat');

            return;
        }
        $this->hasilUji = [];
        $this->ujiId = $backup->id;
        $this->dialogUji = true;
    }

    public function ujiPulih(UjiPulihBackup $uji): void
    {
        $admin = $this->pastikanAdminDapat();
        $backup = $this->ujiId ? Backup::find($this->ujiId) : null;
        if (! $backup) {
            return;
        }
        if (! $this->butuhSandi('Uji pulihkan backup', 'ujiPulih')) {
            return;
        }
        $kunci = 'backup-uji:'.$admin->id;
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_UJI_PER_JAM)) {
            $this->dispatch('toast', pesan: 'Batas '.self::BATAS_UJI_PER_JAM.' uji pulih per jam tercapai.', tipe: 'galat');

            return;
        }
        RateLimiter::hit($kunci, 3600);

        try {
            $this->hasilUji = $uji->jalankan($backup);
        } catch (BackupGagal $e) {
            $this->hasilUji = ['ok' => false, 'pesan' => $e->getMessage()];
        }
        LogAksiAdmin::catat($admin, 'uji_pulih_backup', null, ['backup_id' => $backup->id, 'ok' => (bool) ($this->hasilUji['ok'] ?? false), 'tabel' => $this->hasilUji['tabel'] ?? null, 'baris' => $this->hasilUji['baris'] ?? null]);
    }

    /** Berkas backup memuat seluruh database (termasuk data pribadi): unduhan wajib konfirmasi sandi, dicek ulang di controller. */
    public function unduh(int $id)
    {
        $this->pastikanAdminDapat();
        $backup = Backup::findOrFail($id);
        if (! $backup->sukses()) {
            $this->dispatch('toast', pesan: 'Hanya backup yang berhasil yang bisa diunduh.', tipe: 'galat');

            return null;
        }
        if (! $this->butuhSandi('Unduh backup', 'unduh', ['id' => $id])) {
            return null;
        }

        return $this->redirect(route('admin.backup.unduh', $backup));
    }

    public function tutupUji(): void
    {
        $this->dialogUji = false;
        $this->ujiId = null;
        $this->hasilUji = [];
    }

    // ------------------------------------------------------------------ tampilan

    private function pastikanAdminDapat(): User
    {
        $this->pastikanAdmin();

        return auth()->user();
    }

    private function jadwalBerikutnya(): ?Carbon
    {
        if (! $this->otomatis) {
            return null;
        }
        $t = now()->setTime(2, 0);
        if ($this->frekuensi === 'mingguan') {
            $t = $t->isSunday() && $t->isFuture() ? $t : $t->next('sunday')->setTime(2, 0);
        } elseif ($this->frekuensi === 'bulanan') {
            $t = $t->day === 1 && $t->isFuture() ? $t : $t->addMonthNoOverflow()->startOfMonth()->setTime(2, 0);
        } elseif ($t->isPast()) {
            $t = $t->addDay();
        }

        return $t;
    }

    public function render(PembuatBackup $pembuat, UjiPulihBackup $uji)
    {
        $this->pastikanAdmin();
        $sukses = Backup::where('status', Backup::SUKSES);
        $disk = $pembuat->ringkasanDisk();
        $tersimpan = PengaturanBackup::ambil();
        $sekarang = [
            'otomatis' => $this->otomatis, 'frekuensi' => $this->frekuensi, 'retensi_hari' => (int) $this->retensiHari,
            'simpan_minimal' => (int) $this->simpanMinimal, 'tipe' => $this->tipeOtomatis,
        ];
        $c = $pembuat->konfigDb();

        return view('livewire.admin.backup.index', [
            'baris' => Backup::with('pembuat:id,name')->orderByDesc('id')->limit(self::PER_HALAMAN)->get(),
            'total' => Backup::count(),
            'jumlahSukses' => (clone $sukses)->count(),
            'ukuranSukses' => (int) (clone $sukses)->sum('ukuran'),
            'terakhir' => Backup::orderByDesc('id')->first(),
            'adaBerjalan' => Backup::where('status', Backup::BERJALAN)->exists(),
            'disk' => $disk,
            'ukuranDb' => $pembuat->ukuranDatabase(),
            'mesinDb' => $pembuat->driver() === 'pgsql' ? 'PostgreSQL' : ($pembuat->driver() === 'sqlite' ? 'SQLite' : strtoupper($pembuat->driver())),
            'dbDump' => ['host' => $c['host'] ?? '127.0.0.1', 'port' => $c['port'] ?? 5432],
            'kemampuan' => $uji->kemampuan(),
            'kotor' => $tersimpan !== $sekarang,
            'sudahDisimpan' => Pengaturan::ambil('backup') !== null,
            'jadwal' => $this->jadwalBerikutnya(),
            'ujiBackup' => $this->ujiId ? Backup::find($this->ujiId) : null,
            'hapusBackup' => $this->hapusId ? Backup::find($this->hapusId) : null,
            'akanDihapus' => $this->dialogRetensi ? $pembuat->terapkanRetensi($tersimpan['retensi_hari'], $tersimpan['simpan_minimal'], true) : collect(),
            'sisaKuota' => max(0, (int) config('backup.maks_per_jam', 3) - RateLimiter::attempts('backup-buat:'.auth()->id())),
            'sumberFile' => $pembuat->sumberFile(),
        ])->layout('layouts::app', ['judul' => 'Backup dan Restore', 'sub' => 'Salinan database dan file, verifikasi, dan uji pulih.', 'admin' => true]);
    }
}
