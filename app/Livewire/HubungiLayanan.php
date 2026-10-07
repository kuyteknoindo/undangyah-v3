<?php

namespace App\Livewire;

use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\TiketBantuan;
use App\Models\TiketLampiran;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KontakLayanan;
use App\Services\Notifikasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Formulir ke layanan pelanggan. Tiket milik pengguna (dibaca/diubah hanya olehnya), admin diberi notifikasi. */
class HubungiLayanan extends Component
{
    use WithFileUploads;

    private const FORMAT = ['png', 'jpg', 'jpeg', 'webp', 'pdf'];

    public const BATAS_PER_JAM = 5;

    private const RINGKAS = 4;

    public string $kategori = '';

    public string $undanganId = '';

    public string $subjek = '';

    public string $pesan = '';

    /** Tiket yang baru dikirim (untuk layar "Pesan terkirim"); hanya diisi server. */
    #[Locked]
    public ?int $tiketBaru = null;

    /** Pilihan berkas terbaru dari input; dipindah ke $lampiran setelah divalidasi. */
    public array $berkasBaru = [];

    /** Berkas yang sudah lolos pemeriksaan. Hanya server yang mengisi (dikunci), divalidasi ulang saat kirim. */
    #[Locked]
    public array $lampiran = [];

    public string $galatBerkas = '';

    public bool $semua = false;

    public ?int $bukaTiket = null;

    public function pilihKategori(string $kunci): void
    {
        $this->kategori = array_key_exists($kunci, TiketBantuan::KATEGORI) ? $kunci : '';
        $this->resetErrorBag('kategori');
    }

    public function updatedBerkasBaru(): void
    {
        $this->galatBerkas = '';
        foreach ($this->berkasBaru as $f) {
            $nama = $this->namaAman($f->getClientOriginalName());
            if (count($this->lampiran) >= TiketLampiran::MAKS_BERKAS) {
                $this->galatBerkas = 'Maksimal '.TiketLampiran::MAKS_BERKAS.' berkas.';
                break;
            }
            $cek = Validator::make(['f' => $f], ['f' => $this->aturanBerkas()]);
            if ($cek->fails()) {
                $this->galatBerkas = '“'.$nama.'” ditolak: '.(isset($cek->failed()['f']['Max']) ? 'ukuran melebihi 2 MB.' : 'format harus PNG, JPG, WebP, atau PDF.');

                continue;
            }
            foreach ($this->lampiran as $ada) {
                if ($ada->getClientOriginalName() === $f->getClientOriginalName() && $ada->getSize() === $f->getSize()) {
                    continue 2;
                }
            }
            $this->lampiran[] = $f;
        }
        $this->berkasBaru = [];
    }

    public function hapusLampiran(int $indeks): void
    {
        unset($this->lampiran[$indeks]);
        $this->lampiran = array_values($this->lampiran);
        $this->galatBerkas = '';
    }

    /** @return list<string> */
    private function aturanBerkas(): array
    {
        return ['file', 'mimes:'.implode(',', self::FORMAT), 'max:'.TiketLampiran::MAKS_KB];
    }

    private function namaAman(string $nama): string
    {
        $nama = trim(preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', ' ', $nama) ?? '');

        return Str::limit($nama !== '' ? $nama : 'berkas', 100, '');
    }

    public function ubahSemua(): void
    {
        $this->semua = ! $this->semua;
    }

    public function ubahTiket(int $id): void
    {
        $this->bukaTiket = $this->bukaTiket === $id ? null : $id;
    }

    public function pesanLain(): void
    {
        $this->tiketBaru = null;
        $this->reset('kategori', 'undanganId', 'subjek', 'pesan', 'lampiran', 'berkasBaru', 'galatBerkas');
        $this->resetErrorBag();
    }

    public function kirim(Notifikasi $notifikasi): void
    {
        $user = auth()->user();
        abort_unless($user, 403);

        $this->subjek = trim($this->subjek);
        $this->pesan = trim($this->pesan);

        $this->validate([
            'kategori' => ['required', Rule::in(array_keys(TiketBantuan::KATEGORI))],
            'undanganId' => ['nullable', 'integer', Rule::exists('undangan', 'id')->where('user_id', $user->id)],
            'subjek' => ['required', 'string', 'max:120'],
            'pesan' => ['required', 'string', 'min:10', 'max:1000'],
            'lampiran' => ['array', 'max:'.TiketLampiran::MAKS_BERKAS],
            'lampiran.*' => $this->aturanBerkas(),
        ], [
            'kategori.required' => 'Pilih kategori dulu.',
            'kategori.in' => 'Kategori tidak dikenal.',
            'undanganId.integer' => 'Undangan tidak valid.',
            'undanganId.exists' => 'Undangan tidak ditemukan.',
            'subjek.required' => 'Subjek wajib diisi.',
            'subjek.max' => 'Subjek maksimal 120 karakter.',
            'pesan.required' => 'Pesan minimal 10 karakter.',
            'pesan.min' => 'Pesan minimal 10 karakter.',
            'pesan.max' => 'Pesan maksimal 1000 karakter.',
            'lampiran.max' => 'Maksimal '.TiketLampiran::MAKS_BERKAS.' berkas.',
            'lampiran.*.mimes' => 'Lampiran harus berformat PNG, JPG, WebP, atau PDF.',
            'lampiran.*.max' => 'Setiap lampiran maksimal 2 MB.',
            'lampiran.*.file' => 'Lampiran tidak valid.',
        ]);

        $kunci = 'tiket-bantuan:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_PER_JAM)) {
            $this->addError('form', 'Kamu sudah mengirim '.self::BATAS_PER_JAM.' pesan dalam satu jam terakhir. Coba lagi nanti, atau hubungi kami lewat kontak langsung.');

            return;
        }

        // Klik ganda / kirim ulang isi yang sama dalam 10 menit tidak membuat tiket kedua.
        $kembar = TiketBantuan::where('user_id', $user->id)
            ->where('subjek', $this->subjek)->where('pesan', $this->pesan)
            ->where('created_at', '>=', now()->subMinutes(10))->first();
        if ($kembar) {
            $this->tiketBaru = $kembar->id;
            $this->reset('lampiran', 'berkasBaru', 'galatBerkas');

            return;
        }

        RateLimiter::hit($kunci, 3600);

        $tiket = DB::transaction(function () use ($user) {
            $tiket = TiketBantuan::create([
                'user_id' => $user->id,
                'undangan_id' => $this->undanganId !== '' ? (int) $this->undanganId : null,
                'kategori' => $this->kategori,
                'subjek' => $this->subjek,
                'pesan' => $this->pesan,
                'status' => 'diproses',
            ]);
            $tiket->update(['nomor' => 'TKT-'.now()->format('Y').'-'.str_pad((string) $tiket->id, 4, '0', STR_PAD_LEFT)]);
            $this->simpanLampiran($tiket);

            return $tiket;
        });

        $this->beritahuAdmin($notifikasi, $tiket, $user);
        $this->reset('kategori', 'undanganId', 'subjek', 'pesan', 'lampiran', 'berkasBaru', 'galatBerkas');
        $this->tiketBaru = $tiket->id;
    }

    private function simpanLampiran(TiketBantuan $tiket): void
    {
        foreach ($this->lampiran as $f) {
            $ekstensi = in_array($f->guessExtension(), self::FORMAT, true) ? $f->guessExtension() : 'bin';
            $path = $f->storeAs('tiket-lampiran/'.$tiket->id, Str::random(40).'.'.$ekstensi, TiketLampiran::DISK);
            $tiket->lampiran()->create([
                'nama' => $this->namaAman($f->getClientOriginalName()),
                'path' => $path,
                'mime' => (string) $f->getMimeType(),
                'ukuran' => (int) $f->getSize(),
            ]);
        }
    }

    private function beritahuAdmin(Notifikasi $notifikasi, TiketBantuan $tiket, User $user): void
    {
        $isi = 'Tiket '.$tiket->nomor.' dari '.$user->name.' ('.$user->email.'), '.$tiket->labelKategori().': '
            .$tiket->subjek.'. '.mb_substr($tiket->pesan, 0, 300).($tiket->lampiran()->exists() ? ' ('.$tiket->lampiran()->count().' lampiran)' : '');
        foreach (User::where('role', User::PERAN_ADMIN)->pluck('id') as $adminId) {
            $notifikasi->kirim($adminId, ModelNotifikasi::TIPE_SISTEM, 'Tiket bantuan baru', $isi, null, ['tiket' => $tiket->nomor]);
        }
    }

    public function render(KontakLayanan $kontak)
    {
        $user = auth()->user();
        $milik = TiketBantuan::where('user_id', $user->id);
        $jumlah = (clone $milik)->count();
        $tiket = (clone $milik)->with('undangan:id,judul', 'lampiran:id,tiket_bantuan_id,nama')->latest('id')->limit($this->semua ? 50 : self::RINGKAS)->get();
        $baru = $this->tiketBaru ? (clone $milik)->find($this->tiketBaru) : null;

        return view('livewire.hubungi.index', [
            'kategoriDaftar' => TiketBantuan::KATEGORI,
            'undanganDaftar' => Undangan::where('user_id', $user->id)->latest('id')->limit(100)->get(['id', 'judul', 'status']),
            'tiket' => $tiket,
            'jumlah' => $jumlah,
            'ringkas' => self::RINGKAS,
            'baru' => $baru,
            'kontak' => $kontak->ambil(),
            'tautanBantuan' => Route::has('bantuan') ? route('bantuan') : null,
        ])->layout('layouts::app', ['judul' => 'Hubungi Layanan', 'sub' => 'Bantuan']);
    }
}
