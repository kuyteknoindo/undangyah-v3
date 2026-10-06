<?php

namespace App\Livewire\Admin;

use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\Paket;
use App\Models\SiaranNotifikasi;
use App\Models\User;
use App\Services\PengirimSiaran;
use App\Services\SegmenPengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Panel Admin > Notifikasi: kirim siaran notifikasi dalam aplikasi ke semua pengguna atau segmen, dan riwayatnya. */
class Notifikasi extends Component
{
    public const PER_HALAMAN = 8;

    public const MAKS_SIARAN_PER_JAM = 10;

    /** Saran tautan internal untuk kolom tautan. */
    public const TAUTAN_SARAN = ['/beranda' => 'Beranda', '/billing' => 'Paket & Billing', '/template' => 'Template Desain', '/buat-undangan' => 'Buat Undangan', '/statistik' => 'Statistik', '/bantuan' => 'Pusat Bantuan'];

    public string $tipe = ModelNotifikasi::TIPE_SISTEM;

    public string $judul = '';

    public string $isi = '';

    public string $tautan = '';

    public string $paket = SegmenPengguna::SEMUA;

    public string $peran = SegmenPengguna::SEMUA;

    public bool $tampilKirim = false;

    public string $q = '';

    public string $filter = 'semua';

    public int $halaman = 1;

    #[Locked]
    public ?int $siaranBaruId = null;

    public function updatedQ(): void
    {
        $this->halaman = 1;
    }

    public function pilihFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['semua', ...array_keys(SiaranNotifikasi::STATUS)], true) ? $filter : 'semua';
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

    public function kosongkan(): void
    {
        $this->admin();
        $this->reset(['judul', 'isi', 'tautan', 'tipe', 'paket', 'peran', 'tampilKirim']);
        $this->resetErrorBag();
    }

    /** Isi formulir dari siaran lama (tanpa mengirim apa pun). */
    public function gunakanLagi(int $id): void
    {
        $this->admin();
        $s = SiaranNotifikasi::findOrFail($id);
        $this->fill(['tipe' => $s->tipe, 'judul' => $s->judul, 'isi' => $s->isi, 'tautan' => (string) $s->tautan, 'paket' => $s->target_paket, 'peran' => $s->target_peran]);
        $this->resetErrorBag();
        $this->dispatch('toast', pesan: 'Siaran disalin ke formulir. Periksa lalu kirim.', tipe: 'info');
    }

    public function mintaKirim(): void
    {
        $this->admin();
        $this->validasi();
        if ($this->penerima() === 0) {
            $this->addError('paket', 'Tidak ada pengguna yang cocok dengan sasaran ini.');

            return;
        }
        $this->tampilKirim = true;
    }

    public function kirim(PengirimSiaran $pengirim): void
    {
        $admin = $this->admin();
        $data = $this->validasi();

        $kunci = 'siaran-notifikasi:'.$admin->id;
        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_SIARAN_PER_JAM)) {
            $this->tampilKirim = false;
            $this->dispatch('toast', pesan: 'Terlalu banyak siaran. Coba lagi dalam '.ceil(RateLimiter::availableIn($kunci) / 60).' menit.', tipe: 'galat');

            return;
        }

        // Cegah kirim ganda (klik dua kali, kirim ulang tab lain) untuk isi yang sama.
        $kembar = SiaranNotifikasi::where('admin_id', $admin->id)->where('judul', $data['judul'])->where('isi', $data['isi'])
            ->where('target_paket', $data['paket'])->where('target_peran', $data['peran'])->where('created_at', '>=', now()->subMinutes(5))->exists();
        if ($kembar) {
            $this->tampilKirim = false;
            $this->dispatch('toast', pesan: 'Siaran yang sama baru saja dikirim. Tunggu beberapa menit bila memang ingin mengulang.', tipe: 'galat');

            return;
        }
        if ($this->penerima() === 0) {
            $this->tampilKirim = false;
            $this->addError('paket', 'Tidak ada pengguna yang cocok dengan sasaran ini.');

            return;
        }

        RateLimiter::hit($kunci, 3600);
        $siaran = $pengirim->buat($admin, $data);
        $this->siaranBaruId = $siaran->id;
        $this->tampilKirim = false;
        $this->reset(['judul', 'isi', 'tautan']);
        $this->halaman = 1;
        $this->dispatch('toast', pesan: 'Siaran dijadwalkan ke '.number_format($siaran->jumlah_target, 0, ',', '.').' pengguna.', tipe: 'sukses');
    }

    /** Untuk lingkungan tanpa pekerja antrean: proses potongan yang masih tersisa sekarang juga. */
    public function prosesSekarang(int $id, PengirimSiaran $pengirim): void
    {
        $this->admin();
        $s = SiaranNotifikasi::findOrFail($id);
        $selesai = $pengirim->prosesSampaiSelesai($s->id);
        $this->dispatch('toast', pesan: $selesai ? 'Siaran selesai dikirim.' : 'Siaran masih berjalan. Tekan lagi bila belum selesai.', tipe: $selesai ? 'sukses' : 'info');
    }

    private function admin(): User
    {
        $admin = auth()->user();
        abort_unless($admin?->isAdmin() && ! $admin->ditangguhkan_at, 403);

        return $admin;
    }

    /** @return list<string> */
    private function kodePaket(): array
    {
        return ['gratis', ...Paket::where('kode', '!=', 'gratis')->orderBy('urutan')->orderBy('id')->pluck('kode')->all()];
    }

    /** @return array{tipe:string,judul:string,isi:string,tautan:?string,paket:string,peran:string} */
    private function validasi(): array
    {
        $this->judul = trim(preg_replace('/\s+/u', ' ', $this->judul) ?? '');
        $this->isi = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', str_replace("\r\n", "\n", $this->isi)) ?? '');
        $this->tautan = trim($this->tautan);

        $this->validate([
            'tipe' => ['required', 'in:'.implode(',', PengirimSiaran::TIPE)],
            'judul' => ['required', 'string', 'min:3', 'max:60'],
            'isi' => ['required', 'string', 'min:10', 'max:300'],
            'tautan' => ['nullable', 'string', 'max:500', fn (string $a, mixed $v, \Closure $gagal) => $v === null || $v === '' || ModelNotifikasi::tautanAman($v) !== null
                ? null : $gagal('Tautan harus diawali "/" (halaman di undangyah) atau "https://".')],
            'paket' => ['required', 'in:'.implode(',', [SegmenPengguna::SEMUA, ...$this->kodePaket()])],
            'peran' => ['required', 'in:semua,user,admin'],
        ], [
            'judul.required' => 'Judul wajib diisi.', 'judul.min' => 'Judul minimal 3 karakter.', 'judul.max' => 'Judul maksimal 60 karakter.',
            'isi.required' => 'Isi pesan wajib diisi.', 'isi.min' => 'Isi pesan minimal 10 karakter.', 'isi.max' => 'Isi pesan maksimal 300 karakter.',
            'paket.in' => 'Pilih paket sasaran yang tersedia.', 'peran.in' => 'Pilih peran sasaran yang tersedia.', 'tipe.in' => 'Jenis notifikasi tidak dikenal.',
        ], ['tautan' => 'Tautan']);

        return ['tipe' => $this->tipe, 'judul' => $this->judul, 'isi' => $this->isi, 'tautan' => $this->tautan !== '' ? $this->tautan : null, 'paket' => $this->paket, 'peran' => $this->peran];
    }

    private function penerima(): int
    {
        if (! in_array($this->paket, [SegmenPengguna::SEMUA, ...$this->kodePaket()], true) || ! in_array($this->peran, ['semua', 'user', 'admin'], true)) {
            return 0;
        }

        return app(PengirimSiaran::class)->hitungPenerima($this->paket, $this->peran);
    }

    private function namaPaket(string $kode): string
    {
        return match ($kode) {
            SegmenPengguna::SEMUA => 'Semua paket',
            'gratis' => 'Gratis',
            default => Paket::where('kode', $kode)->value('nama') ?? ucfirst($kode),
        };
    }

    public function render()
    {
        $this->admin();

        $dasar = SiaranNotifikasi::query();
        $kata = mb_strtolower(trim($this->q));
        if ($kata !== '') {
            $pola = '%'.addcslashes($kata, '%_\\').'%';
            $dasar->where(fn (Builder $w) => $w->whereRaw("LOWER(judul) LIKE ? ESCAPE '\\'", [$pola])->orWhereRaw("LOWER(isi) LIKE ? ESCAPE '\\'", [$pola]));
        }
        $hitung = ['semua' => (clone $dasar)->count()]
            + (clone $dasar)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all();
        $kueri = $this->filter === 'semua' ? clone $dasar : (clone $dasar)->where('status', $this->filter);
        $total = (clone $kueri)->count();
        $maks = max(1, (int) ceil($total / self::PER_HALAMAN));
        $this->halaman = max(1, min($this->halaman, $maks));
        $riwayat = $kueri->with('admin:id,name,email')->orderByDesc('id')->forPage($this->halaman, self::PER_HALAMAN)->get();

        $namaPaket = [];
        foreach ([SegmenPengguna::SEMUA, ...$this->kodePaket()] as $kode) {
            $namaPaket[$kode] = $this->namaPaket($kode);
        }

        return view('livewire.admin.notifikasi.index', [
            'penerima' => $this->penerima(),
            'namaPaket' => $namaPaket,
            'riwayat' => $riwayat,
            'hitung' => $hitung,
            'total' => $total,
            'maks' => $maks,
            'dari' => $total === 0 ? 0 : ($this->halaman - 1) * self::PER_HALAMAN + 1,
            'sampai' => min($total, $this->halaman * self::PER_HALAMAN),
            'adaBerjalan' => SiaranNotifikasi::whereIn('status', [SiaranNotifikasi::ANTRE, SiaranNotifikasi::BERJALAN])->exists(),
            'ringkasan' => [
                'total' => SiaranNotifikasi::count(),
                'terkirim' => (int) SiaranNotifikasi::where('status', SiaranNotifikasi::SELESAI)->count(),
                'penerima' => (int) SiaranNotifikasi::sum('terkirim'),
            ],
            'jenis' => ModelNotifikasi::JENIS,
            'tipeBoleh' => PengirimSiaran::TIPE,
            'saranTautan' => self::TAUTAN_SARAN,
        ])->layout('layouts::app', ['judul' => 'Notifikasi', 'sub' => 'Kirim pengumuman ke pengguna aplikasi.']);
    }
}
