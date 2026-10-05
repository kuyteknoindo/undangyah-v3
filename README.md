# undangyah (v3)

Aplikasi undangan digital. Laravel 13 + Livewire 4 (Alpine) + Tailwind 4, PostgreSQL, Redis (DB 5, prefix `uyl:`).

Desain acuan (bukan kode untuk dijalankan) ada di `../desain-acuan/{desktop,mobile}`.

## Lingkungan dev (server ini)
- Situs: https://dev.undangyah.com (Cloudflare Tunnel -> nginx 127.0.0.1:8083 -> PHP-FPM pool `undangyah`).
- Pengguna sistem `deploy`; proyek di `/home/deploy/undangyah/app`.
- Konfigurasi rahasia hanya di `.env` (tidak di-commit). Contoh: `.env.example`.

## Perintah umum
```sh
composer install
npm install && npm run build      # build aset (public/build)
php artisan migrate               # database undangyah_dev
php artisan test                  # tes (SQLite in-memory)
vendor/bin/pint                   # format kode
php artisan undangyah:admin email@contoh.com   # buat/naikkan akun admin (tahap 2)
```

## Tahap pengembangan
1. Kerangka + tata letak sidebar/header
2. Akun: daftar, masuk, lupa kata sandi, OTP email, peran admin
3. Database domain (undangan, tamu, RSVP, paket, pesanan, media)
4+. Halaman pengguna, undangan publik, panel admin, pembayaran manual, penyimpanan/email/backup, tes & deploy
