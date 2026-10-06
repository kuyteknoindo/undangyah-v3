<?php

namespace App\Livewire;

use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\TiketBantuan;
use App\Models\Undangan;
use App\Models\User;
use App\Services\KontakLayanan;
use App\Services\Notifikasi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Formulir ke layanan pelanggan. Tiket milik pengguna (dibaca/diubah hanya olehnya), admin diberi notifikasi. */
class HubungiLayanan extends Component
{
    public const BATAS_PER_JAM = 5;

    private const RINGKAS = 4;

    public string $kategori = '';

    public string $undanganId = '';

    public string $subjek = '';

    public string $pesan = '';

    /** Tiket yang baru dikirim (untuk layar "Pesan terkirim"); hanya diisi server. */
    #[Locked]
    public ?int $tiketBaru = null;

    public bool $semua = false;

    public ?int $bukaTiket = null;

    public function pilihKategori(string $kunci): void
    {
        $this->kategori = array_key_exists($kunci, TiketBantuan::KATEGORI) ? $kunci : '';
        $this->resetErrorBag('kategori');
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
        $this->reset('kategori', 'undanganId', 'subjek', 'pesan');
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

            return $tiket;
        });

        $this->beritahuAdmin($notifikasi, $tiket, $user);
        $this->reset('kategori', 'undanganId', 'subjek', 'pesan');
        $this->tiketBaru = $tiket->id;
    }

    private function beritahuAdmin(Notifikasi $notifikasi, TiketBantuan $tiket, User $user): void
    {
        $isi = 'Tiket '.$tiket->nomor.' dari '.$user->name.' ('.$user->email.'), '.$tiket->labelKategori().': '
            .$tiket->subjek.'. '.mb_substr($tiket->pesan, 0, 300);
        foreach (User::where('role', User::PERAN_ADMIN)->pluck('id') as $adminId) {
            $notifikasi->kirim($adminId, ModelNotifikasi::TIPE_SISTEM, 'Tiket bantuan baru', $isi, null, ['tiket' => $tiket->nomor]);
        }
    }

    public function render(KontakLayanan $kontak)
    {
        $user = auth()->user();
        $milik = TiketBantuan::where('user_id', $user->id);
        $jumlah = (clone $milik)->count();
        $tiket = (clone $milik)->with('undangan:id,judul')->latest('id')->limit($this->semua ? 50 : self::RINGKAS)->get();
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
