<?php

namespace App\Models;

use App\Support\NomorWa;
use App\Support\WarnaAvatar;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

#[Fillable(['name', 'email', 'phone', 'password', 'panggilan', 'bio', 'foto_path', 'avatar_warna', 'kota', 'bahasa', 'phone_verified_at', 'password_changed_at', 'email_baru', 'email_baru_dikirim_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PERAN_USER = 'user';

    public const PERAN_ADMIN = 'admin';

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'email_baru_dikirim_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === self::PERAN_ADMIN;
    }

    public function namaDepan(): string
    {
        return Str::of($this->name)->trim()->before(' ')->toString();
    }

    /** Nama untuk sapaan: panggilan bila diisi, selain itu nama depan. */
    public function sapaan(): string
    {
        return trim((string) $this->panggilan) !== '' ? trim($this->panggilan) : $this->namaDepan();
    }

    public function fotoUrl(): ?string
    {
        return $this->foto_path ? Storage::disk('public')->url($this->foto_path) : null;
    }

    public function warnaAvatar(): string
    {
        return WarnaAvatar::hex($this->avatar_warna);
    }

    /** Nomor WhatsApp tersimpan sebagai 62812...; ditampilkan sebagai +62 812 3456 7890. */
    public function teleponTampil(): ?string
    {
        return NomorWa::tampil($this->phone);
    }

    public function inisial(): string
    {
        $kata = preg_split('/\s+/', trim($this->name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return mb_strtoupper(collect($kata)->take(2)->map(fn ($k) => mb_substr($k, 0, 1))->implode('')) ?: '?';
    }

    public function notifikasi(): HasMany
    {
        return $this->hasMany(Notifikasi::class);
    }

    /** Satu hitungan untuk badge lonceng di header. */
    public function jumlahNotifikasiBelumDibaca(): int
    {
        return $this->notifikasi()->tampil()->belumDibaca()->count();
    }
}
