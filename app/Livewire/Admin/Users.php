<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\MintaKonfirmasiSandi;
use App\Models\LogAksiAdmin;
use App\Models\Paket;
use App\Models\User;
use App\Services\CsvTamu;
use App\Services\KuotaUndangan;
use App\Services\PengelolaPengguna;
use App\Services\SegmenPengguna;
use App\Services\SesiImpersonasi;
use App\Support\AksiAdminDitolak;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Panel Admin > Users: daftar, detail, dan aksi atas akun pengguna. Semua aksi lewat PengelolaPengguna. */
class Users extends Component
{
    use MintaKonfirmasiSandi;

    public const PER_HALAMAN = 15;

    public const MAKS_EKSPOR = 5000;

    private const URUTAN = ['baru' => 'Terbaru bergabung', 'lama' => 'Terlama bergabung', 'nama' => 'Nama A-Z', 'login' => 'Login terakhir'];

    private const JENIS_AKSI = ['peran', 'paket', 'tangguh', 'hapus', 'masuk'];

    /** Semua jenis aksi dialog ini sensitif dan wajib konfirmasi sandi (lihat KonfirmasiSandiAdmin). */
    private const LABEL_SANDI = [
        'peran' => 'Ubah peran pengguna',
        'paket' => 'Ubah paket pengguna secara manual',
        'tangguh' => 'Tangguhkan akun pengguna',
        'hapus' => 'Hapus pengguna',
        'masuk' => 'Masuk sebagai pengguna',
    ];

    #[Url(as: 'q', except: '')]
    public string $q = '';

    #[Url(as: 'paket', except: 'semua')]
    public string $paket = 'semua';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'peran', except: '')]
    public string $peran = '';

    #[Url(as: 'urut', except: 'baru')]
    public string $urut = 'baru';

    #[Url(as: 'hal', except: 1)]
    public int $halaman = 1;

    #[Locked]
    public ?int $detailId = null;

    public bool $tampilDetail = false;

    #[Locked]
    public string $jenisAksi = '';

    #[Locked]
    public ?int $aksiId = null;

    public bool $tampilAksi = false;

    public string $peranBaru = User::PERAN_USER;

    public string $paketBaru = 'gratis';

    public string $paketSampai = '';

    public bool $tanpaBatas = false;

    public string $catatanPaket = '';

    public string $alasanTangguh = '';

    public string $emailKonfirmasi = '';

    // ---- filter ----

    public function updated(string $nama): void
    {
        if (in_array($nama, ['q', 'paket', 'status', 'peran', 'urut'], true)) {
            $this->halaman = 1;
        }
    }

    public function aturUlangFilter(): void
    {
        $this->reset(['q', 'paket', 'status', 'peran']);
        $this->halaman = 1;
    }

    public function sebelumnya(): void
    {
        $this->halaman = max(1, $this->halaman - 1);
    }

    public function berikutnya(): void
    {
        $this->halaman++;
    }

    // ---- detail ----

    public function bukaDetail(int $id): void
    {
        $this->admin();
        User::findOrFail($id);
        $this->detailId = $id;
        $this->tampilDetail = true;
    }

    // ---- aksi langsung (tanpa dialog) ----

    public function verifikasi(int $id, PengelolaPengguna $pengelola): void
    {
        $this->jalankan(fn (User $admin, User $t) => $pengelola->tandaiTerverifikasi($admin, $t), $id, 'Email pengguna ditandai terverifikasi.');
    }

    public function kirimUlangVerifikasi(int $id, PengelolaPengguna $pengelola): void
    {
        $admin = $this->admin();
        if ($this->batasi('admin-users-kirim', 10, 600)) {
            return;
        }
        $this->jalankan(fn (User $a, User $t) => $pengelola->kirimUlangVerifikasi($a, $t), $id, 'Kode verifikasi baru dikirim ke email pengguna.', $admin);
    }

    public function aktifkan(int $id, PengelolaPengguna $pengelola): void
    {
        $this->jalankan(fn (User $admin, User $t) => $pengelola->aktifkan($admin, $t), $id, 'Akun diaktifkan kembali.');
    }

    // ---- aksi dengan dialog ----

    public function mintaAksi(string $jenis, int $id): void
    {
        $this->admin();
        abort_unless(in_array($jenis, self::JENIS_AKSI, true), 404);
        $t = User::findOrFail($id);

        $this->resetErrorBag();
        $this->reset(['catatanPaket', 'alasanTangguh', 'emailKonfirmasi', 'tanpaBatas']);
        $this->jenisAksi = $jenis;
        $this->aksiId = $t->id;
        $this->peranBaru = $t->isAdmin() ? User::PERAN_USER : User::PERAN_ADMIN;
        $this->paketBaru = $t->paketAktif() === 'gratis' ? (Paket::where('kode', '!=', 'gratis')->orderBy('urutan')->value('kode') ?? 'gratis') : $t->paketAktif();
        $this->paketSampai = ($t->paket_sampai && $t->paket_sampai->isFuture() ? $t->paket_sampai : now()->addYear())->format('Y-m-d');
        $this->tampilAksi = true;
    }

    public function tutupAksi(): void
    {
        $this->tampilAksi = false;
    }

    public function jalankanAksi(PengelolaPengguna $pengelola, SesiImpersonasi $impersonasi)
    {
        $admin = $this->admin();
        abort_unless(in_array($this->jenisAksi, self::JENIS_AKSI, true) && $this->aksiId, 404);
        $t = User::findOrFail($this->aksiId);

        try {
            switch ($this->jenisAksi) {
                case 'peran':
                    $this->validate(['peranBaru' => ['required', 'in:user,admin']], [], ['peranBaru' => 'Peran']);
                    if (! $this->butuhSandi(self::LABEL_SANDI['peran'], 'jalankanAksi')) {
                        return null;
                    }
                    $pengelola->ubahPeran($admin, $t, $this->peranBaru);
                    $pesan = 'Peran '.$t->name.' kini '.$pengelola->labelPeran($this->peranBaru).'.';
                    break;

                case 'paket':
                    $kode = $this->validate([
                        'paketBaru' => ['required', 'string', 'max:30'],
                        'paketSampai' => [$this->tanpaBatas || $this->paketBaru === 'gratis' ? 'nullable' : 'required', 'date_format:Y-m-d'],
                        'catatanPaket' => ['nullable', 'string', 'max:200'],
                    ], [
                        'paketSampai.required' => 'Pilih tanggal akhir masa berlaku, atau centang tanpa batas waktu.',
                        'paketSampai.date_format' => 'Format tanggal tidak valid.',
                    ], ['paketBaru' => 'Paket', 'catatanPaket' => 'Catatan'])['paketBaru'];
                    $sampai = $this->tanpaBatas || $kode === 'gratis' ? null : Carbon::createFromFormat('Y-m-d', $this->paketSampai)->endOfDay();
                    if (! $this->butuhSandi(self::LABEL_SANDI['paket'], 'jalankanAksi')) {
                        return null;
                    }
                    $pengelola->aturPaket($admin, $t, $kode, $sampai, $this->catatanPaket);
                    $pesan = 'Paket '.$t->name.' diperbarui.';
                    break;

                case 'tangguh':
                    $this->validate(['alasanTangguh' => ['nullable', 'string', 'max:200']], [], ['alasanTangguh' => 'Alasan']);
                    if (! $this->butuhSandi(self::LABEL_SANDI['tangguh'], 'jalankanAksi')) {
                        return null;
                    }
                    $pengelola->tangguhkan($admin, $t, $this->alasanTangguh);
                    $pesan = $t->name.' ditangguhkan. Sesinya diputus.';
                    break;

                case 'hapus':
                    if (mb_strtolower(trim($this->emailKonfirmasi)) !== mb_strtolower($t->email)) {
                        $this->addError('aksi', 'Email konfirmasi tidak cocok.');

                        return null;
                    }
                    if (! $this->butuhSandi(self::LABEL_SANDI['hapus'], 'jalankanAksi')) {
                        return null;
                    }
                    if ($this->batasi('admin-users-hapus', 10, 600)) {
                        return null;
                    }
                    $pengelola->hapus($admin, $t, $this->emailKonfirmasi);
                    $this->detailId = null;
                    $this->tampilDetail = false;
                    $pesan = 'Akun '.$t->name.' dihapus.';
                    break;

                case 'masuk':
                    if (! $this->butuhSandi(self::LABEL_SANDI['masuk'], 'jalankanAksi')) {
                        return null;
                    }
                    if ($this->batasi('admin-users-masuk', 10, 600)) {
                        return null;
                    }
                    $impersonasi->mulai($admin, $t);

                    return $this->redirect(route('beranda'));
            }
        } catch (AksiAdminDitolak $e) {
            $this->addError('aksi', $e->getMessage());

            return null;
        }

        $this->tampilAksi = false;
        $this->dispatch('toast', pesan: $pesan, tipe: 'sukses');

        return null;
    }

    // ---- ekspor ----

    public function ekspor(): ?StreamedResponse
    {
        $admin = $this->admin();
        if (! $this->butuhSandi('Ekspor data pengguna (CSV)', 'ekspor')) {
            return null;
        }
        if ($this->batasi('admin-users-ekspor', 5, 60)) {
            return null;
        }

        $baris = $this->kueri()->limit(self::MAKS_EKSPOR)->get()->map(fn (User $u) => [
            $u->name, $u->email, $u->phone, $u->isAdmin() ? 'Admin' : 'Pengguna', $this->namaPaket($u->paketAktif()),
            $u->paket_sampai?->format('Y-m-d'), (int) $u->undangan_count, $this->labelStatus($u)[0],
            $u->created_at?->format('Y-m-d H:i'), $u->login_terakhir_at?->format('Y-m-d H:i'),
        ]);
        LogAksiAdmin::catat($admin, 'ekspor_pengguna', null, ['jumlah' => $baris->count(), 'filter' => array_filter(['q' => trim($this->q), 'paket' => $this->paket !== 'semua' ? $this->paket : null, 'status' => $this->status, 'peran' => $this->peran])]);
        $isi = app(CsvTamu::class)->tulis(['Nama', 'Email', 'HP', 'Peran', 'Paket', 'Paket sampai', 'Undangan', 'Status', 'Bergabung', 'Login terakhir'], $baris);

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, 'pengguna-undangyah-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---- bantu ----

    private function admin(): User
    {
        $admin = auth()->user();
        abort_unless($admin?->isAdmin() && ! $admin->ditangguhkan_at, 403);

        return $admin;
    }

    /** @param  callable(User,User):void  $aksi */
    private function jalankan(callable $aksi, int $id, string $sukses, ?User $admin = null): void
    {
        $admin ??= $this->admin();
        $t = User::findOrFail($id);
        try {
            $aksi($admin, $t);
        } catch (AksiAdminDitolak $e) {
            $this->dispatch('toast', pesan: $e->getMessage(), tipe: 'galat');

            return;
        }
        $this->dispatch('toast', pesan: $sukses, tipe: 'sukses');
    }

    private function batasi(string $kunci, int $maks, int $detik): bool
    {
        $kunci = $kunci.':'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, $maks)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak permintaan. Coba lagi sebentar lagi.', tipe: 'galat');

            return true;
        }
        RateLimiter::hit($kunci, $detik);

        return false;
    }

    /** @return Builder<User> */
    private function kueriDasar(): Builder
    {
        $q = User::query();
        $kata = mb_strtolower(trim($this->q));
        if ($kata !== '') {
            $pola = '%'.addcslashes($kata, '%_\\').'%';
            $angka = preg_replace('/\D/', '', $kata);
            $q->where(function (Builder $w) use ($pola, $angka) {
                $w->whereRaw("LOWER(name) LIKE ? ESCAPE '\\'", [$pola])->orWhereRaw("LOWER(email) LIKE ? ESCAPE '\\'", [$pola]);
                if (strlen((string) $angka) >= 4) {
                    $w->orWhere('phone', 'like', '%'.ltrim($angka, '0').'%');
                }
            });
        }
        if (in_array($this->peran, [User::PERAN_USER, User::PERAN_ADMIN], true)) {
            $q->where('role', $this->peran);
        }
        match ($this->status) {
            'aktif' => $q->whereNotNull('email_verified_at')->whereNull('ditangguhkan_at'),
            'ditangguhkan' => $q->whereNotNull('ditangguhkan_at'),
            'belum' => $q->whereNull('email_verified_at')->whereNull('ditangguhkan_at'),
            default => null,
        };

        return $q;
    }

    /** @return Builder<User> */
    private function kueriTersaring(): Builder
    {
        return app(SegmenPengguna::class)->paket($this->kueriDasar(), $this->paketValid());
    }

    /** @return Builder<User> */
    private function kueri(): Builder
    {
        $q = $this->kueriTersaring();
        $q->addSelect(['users.*', 'undangan_count' => DB::table('undangan')->selectRaw('count(*)')->whereColumn('undangan.user_id', 'users.id')]);

        return match ($this->urut) {
            'lama' => $q->orderBy('id'),
            'nama' => $q->orderByRaw('LOWER(name)')->orderBy('id'),
            'login' => $q->orderByRaw('login_terakhir_at IS NULL')->orderByDesc('login_terakhir_at')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        };
    }

    /** @return list<string> */
    private function kodePaket(): array
    {
        return ['gratis', ...Paket::where('kode', '!=', 'gratis')->orderBy('urutan')->orderBy('id')->pluck('kode')->all()];
    }

    private function paketValid(): string
    {
        return in_array($this->paket, $this->kodePaket(), true) ? $this->paket : 'semua';
    }

    private function namaPaket(string $kode): string
    {
        return $kode === 'gratis' ? 'Gratis' : (Paket::where('kode', $kode)->value('nama') ?? ucfirst($kode));
    }

    /** @return array{0:string,1:string} [label, warna pill] */
    private function labelStatus(User $u): array
    {
        return match (true) {
            (bool) $u->ditangguhkan_at => ['Ditangguhkan', 'merah'],
            ! $u->hasVerifiedEmail() => ['Belum verifikasi', 'kuning'],
            default => ['Aktif', 'hijau'],
        };
    }

    public function render(PengelolaPengguna $pengelola, KuotaUndangan $kuota, SegmenPengguna $segmen)
    {
        $admin = $this->admin();

        $total = $this->kueriTersaring()->count();
        $maks = max(1, (int) ceil($total / self::PER_HALAMAN));
        $this->halaman = max(1, min($this->halaman, $maks));
        $baris = $this->kueri()->forPage($this->halaman, self::PER_HALAMAN)->get();

        $kuotaBaris = [];
        $paketBaris = [];
        foreach ($baris as $u) {
            $batas = $kuota->batas($u);
            $kuotaBaris[$u->id] = $batas === null ? '∞' : (string) $batas;
            $paketBaris[$u->id] = $this->namaPaket($u->paketAktif());
        }

        $segmenDasar = $this->kueriDasar();
        $segmenPaket = collect(['semua', ...$this->kodePaket()])->map(fn (string $kode) => [
            'kode' => $kode,
            'label' => $kode === 'semua' ? 'Semua' : $this->namaPaket($kode),
            'n' => $segmen->paket(clone $segmenDasar, $kode)->count(),
        ])->all();

        $detail = null;
        $detailUser = null;
        if ($this->detailId && ($detailUser = User::find($this->detailId))) {
            $detail = $pengelola->detail($detailUser);
        }
        $aksiUser = $this->aksiId ? User::find($this->aksiId) : null;

        return view('livewire.admin.users.index', [
            'admin' => $admin,
            'baris' => $baris,
            'total' => $total,
            'dari' => $total === 0 ? 0 : ($this->halaman - 1) * self::PER_HALAMAN + 1,
            'sampai' => min($total, $this->halaman * self::PER_HALAMAN),
            'maks' => $maks,
            'kuotaBaris' => $kuotaBaris,
            'paketBaris' => $paketBaris,
            'segmenPaket' => $segmenPaket,
            'kpi' => $this->kpi($segmen),
            'detailUser' => $detailUser,
            'detail' => $detail,
            'aksiUser' => $aksiUser,
            'daftarPaket' => Paket::orderBy('urutan')->orderBy('id')->get(['kode', 'nama', 'status']),
            'urutan' => self::URUTAN,
            'adaFilter' => trim($this->q) !== '' || $this->paket !== 'semua' || $this->status !== '' || $this->peran !== '',
            'impersonasiMaks' => SesiImpersonasi::MENIT_MAKS,
        ])->layout('layouts::app', ['judul' => 'Users', 'sub' => 'Kelola akun pengguna, paket, dan akses.']);
    }

    /** @return array{total:int,berbayar:int,aktif30:int,ditangguhkan:int,belum:int} */
    private function kpi(SegmenPengguna $segmen): array
    {
        $semua = User::query();

        return [
            'total' => (clone $semua)->count(),
            'berbayar' => (clone $semua)->where(fn (Builder $w) => $w->whereNotIn('id', $segmen->paket(User::query(), 'gratis')->select('id')))->count(),
            'aktif30' => (clone $semua)->where('login_terakhir_at', '>=', now()->subDays(30))->count(),
            'ditangguhkan' => (clone $semua)->whereNotNull('ditangguhkan_at')->count(),
            'belum' => (clone $semua)->whereNull('email_verified_at')->count(),
        ];
    }
}
