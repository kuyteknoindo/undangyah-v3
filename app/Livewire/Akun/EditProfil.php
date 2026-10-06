<?php

namespace App\Livewire\Akun;

use App\Services\FotoProfil;
use App\Support\WarnaAvatar;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class EditProfil extends Component
{
    use WithFileUploads;

    public string $nama = '';

    public string $panggilan = '';

    public string $bio = '';

    public string $avatarWarna = 'biru';

    /** Foto baru (berkas sementara Livewire) yang belum disimpan. */
    public $foto = null;

    /** Pengguna menekan "Hapus foto"; foto lama baru dihapus saat Simpan. */
    public bool $hapusFoto = false;

    /** Nilai tersimpan, pembanding untuk indikator "belum disimpan" di klien. */
    public array $asli = [];

    public function mount(): void
    {
        $this->muatDariAkun();
    }

    private function muatDariAkun(): void
    {
        $user = auth()->user()->fresh();
        $this->nama = $user->name;
        $this->panggilan = (string) $user->panggilan;
        $this->bio = (string) $user->bio;
        $this->avatarWarna = $user->avatar_warna ?: 'biru';
        $this->foto = null;
        $this->hapusFoto = false;
        $this->asli = ['nama' => $this->nama, 'panggilan' => $this->panggilan, 'bio' => $this->bio, 'avatarWarna' => $this->avatarWarna];
    }

    private function aturanFoto(): array
    {
        return ['foto' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120']];
    }

    private const PESAN = [
        'nama.required' => 'Nama wajib diisi.',
        'nama.min' => 'Nama minimal 3 huruf.',
        'foto.image' => 'File harus berupa gambar (JPG atau PNG).',
        'foto.mimes' => 'File harus berupa gambar (JPG atau PNG).',
        'foto.max' => 'Ukuran file maksimal 5 MB.',
        'foto.uploaded' => 'Ukuran file maksimal 5 MB.',
        'foto.file' => 'File tidak bisa diunggah. Coba file lain.',
    ];

    public function updatedFoto(): void
    {
        if ($this->foto === null) {
            return;
        }

        try {
            $this->validateOnly('foto', $this->aturanFoto(), self::PESAN);
        } catch (ValidationException $e) {
            if ($this->foto instanceof TemporaryUploadedFile) {
                $this->foto->delete();
            }
            $this->foto = null;
            throw $e;
        }

        $this->hapusFoto = false;
    }

    public function hapusFotoSekarang(): void
    {
        $this->foto = null;
        $this->hapusFoto = true;
        $this->resetErrorBag('foto');
    }

    public function batalkan(): void
    {
        $this->resetErrorBag();
        $this->muatDariAkun();
    }

    public function simpan(FotoProfil $layananFoto): void
    {
        $this->nama = trim($this->nama);
        $this->panggilan = trim($this->panggilan);
        $this->bio = trim($this->bio);

        $this->validate([
            'nama' => ['required', 'string', 'min:3', 'max:100'],
            'panggilan' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:160'],
            'avatarWarna' => ['required', Rule::in(WarnaAvatar::kunci())],
        ] + $this->aturanFoto(), self::PESAN);

        // Selalu dari pengguna yang sedang masuk: tidak ada id yang bisa dimanipulasi klien.
        $user = auth()->user();
        $fotoLama = $user->foto_path;
        $fotoBaru = $fotoLama;

        if ($this->foto instanceof TemporaryUploadedFile) {
            $fotoBaru = $layananFoto->simpan($this->foto);
            $this->foto->delete();
        } elseif ($this->hapusFoto) {
            $fotoBaru = null;
        }

        $user->fill([
            'name' => $this->nama,
            'panggilan' => $this->panggilan !== '' ? $this->panggilan : null,
            'bio' => $this->bio !== '' ? $this->bio : null,
            'avatar_warna' => $this->avatarWarna,
            'foto_path' => $fotoBaru,
        ])->save();

        if ($fotoLama && $fotoLama !== $fotoBaru) {
            $layananFoto->hapus($fotoLama);
        }

        $this->muatDariAkun();
        $this->dispatch('toast', pesan: 'Profil disimpan.', tipe: 'sukses');
    }

    public function render()
    {
        $user = auth()->user()->fresh();
        $fotoUrl = match (true) {
            $this->foto instanceof TemporaryUploadedFile => $this->foto->temporaryUrl(),
            $this->hapusFoto => null,
            default => $user->fotoUrl(),
        };

        return view('livewire.akun.edit-profil', ['user' => $user, 'fotoUrl' => $fotoUrl])
            ->layout('layouts::app', ['judul' => 'Edit Profil', 'kecil' => 'Akun']);
    }
}
