# PROGRESS: undangyah v3

Catatan konteks lintas sesi. Perbarui di akhir tiap fase. Rencana asli: dokumen "Rencana Implementasi undangyah v3 (Laravel), Desktop Dulu" (10 fase; mobile sesudah fase 9).

## Status fase
| Fase | Isi | Status |
|---|---|---|
| 0 Fondasi | kerangka, layout, komponen dasar, peran, seeder admin, 404/offline/pemeliharaan | SELESAI (tag fase-0) |
| 1 Akun | daftar/masuk/lupa sandi/OTP, profil, edit profil, informasi akun, ubah sandi, notifikasi | SELESAI (tag fase-1) |
| 2 Data inti + Beranda | tabel inti, Beranda, wizard buat undangan | SELESAI (tag fase-2) |
| 3 Editor undangan | editor, terbit, slug, pratinjau, template | SELESAI (tag fase-3) |
| 4 Undangan publik | `/{slug}`, RSVP, ucapan, OG | SELESAI (tag fase-4) |
| 5 Tamu dan pembagian | tamu, QR, pesan, buku tamu, statistik, hadiah | belum |
| 6 Paket dan pembayaran | paket, checkout, transfer manual | belum |
| 7 Admin penjualan/pengguna | produk, add-on, voucher, pesanan, invoice, metode bayar, users, notifikasi, OTP | belum |
| 8 Admin konten/sistem | tema, media, musik, storage, backup | belum |
| 9 Bantuan dan kualitas | bantuan, kontak, syarat, audit | belum |

## Keputusan (dari pemilik)
- Email: `MAIL_MAILER=log` dulu; SMTP menyusul. OTP lewat email (bukan WhatsApp); nomor WA opsional saat daftar. Tombol Google/WhatsApp login = "segera hadir".
- Basic Auth nginx melindungi dev.undangyah.com; Cloudflare Access ditunda. `APP_ENV=local`, `APP_DEBUG=false`.
- Token GitHub: urusan pemilik; deploy key cukup. Token repo lama tidak disentuh.
- Admin awal: phentem@gmail.com (seeder; sandi acak di `/home/deploy/undangyah/ADMIN-LOGIN.txt`, izin 600, tidak di-commit).
- WhatsApp: tautan `wa.me` (tanpa gateway). Pembayaran: transfer manual (tanpa Duitku).
- Menu admin di desain ada 14 halaman (bukan 13).
- Migrasi data lama dan peralihan `app.undangyah.com` diputuskan SETELAH fase 9 (sekarang dipakai undangyah-v2).

## Catatan teknis
- Ingress tunnel dikelola remote (Cloudflare API); skrip & rollback di `/root/undangyah-1b/`. Port 8082 jangan dipakai (cdn2).
- Unit `undangyah-queue.service` ada tapi belum diaktifkan (email dikirim sinkron). Cron `deploy` menjalankan `schedule:run`.

## Hal tertunda / menunggu pemilik
- Kredensial SMTP. Cloudflare Access (butuh izin token). Logo asli (sementara teks). Gateway WA gowa: belum diputuskan.
- Cadangan: snapshot Proxmox sebelum fase 3, 6, 9 dan jadwal `pg_dump` (tindakan pemilik / fase 8).

## Log fase
### Fase 0 (selesai 2026-10-05)
- Jadi: kerangka Laravel 13/Livewire 4/Tailwind 4, layout sidebar+header (menu geser <1024 px), komponen dasar (kartu, tombol, input, pill, tag, tabel, seg, sakelar, kpi, dialog, toast, empty state, skeleton), peran user/admin, AdminSeeder (admin awal, sandi di ADMIN-LOGIN.txt 600), halaman 404, offline, pemeliharaan (`undangyah:pemeliharaan on|off`, tabel `pengaturan`), CLAUDE.md, alat QA browser (`/home/deploy/undangyah/qa`), perintah `undangyah:qa-user`/`qa-bersih`.
- Keputusan kecil: rute per fitur di `routes/halaman`, `routes/admin`, `routes/publik.php` (placeholder otomatis hilang); halaman pemeliharaan memakai middleware global web (admin lolos); tema/komponen memakai nama kelas Indonesia.
- Tes: 57 lulus. QA browser lolos (admin awal masuk, 404, offline).
- Tertunda: logo asli, SMTP, Cloudflare Access.

### Fase 1 (selesai 2026-10-06)
- Jadi: profil (`/akun`), edit profil + foto, informasi akun (konfirmasi email baru lewat tautan bertanda tangan), ubah sandi, daftar notifikasi + badge header, layanan notifikasi.
- Perbaikan saat penutupan: `@php(...)` inline bentrok dengan `@php ... @endphp` di view notifikasi (pakai blok); regex tes badge dibuat toleran spasi.
- Tes: 96 lulus. QA browser 1440 dan 390 px: 5 halaman 200, tanpa galat JS. Catatan: skrip QA jalan sebagai root (lib memakai `runuser`); QA lambat (~20 dtk/halaman).
- Skema inti Fase 2 (tema, undangan, tamu, rsvp, ucapan, tayangan, slug, skema editor) sudah dimigrasi bersama Fase 1; dilanjutkan di Fase 2.

### Fase 2 (selesai 2026-10-06)
- Jadi: tabel inti (tema, undangan, tamu, rsvp, ucapan, tayangan), model + factory, `TemaSeeder` (8 tema), `SlugUndangan`, `SkemaUndangan` (125 bidang editor), Beranda (keadaan kosong + contoh, KPI, undangan utama, grafik 7 hari, donat RSVP, aktivitas, daftar undangan dengan filter/hapus/bagikan), wizard `/buat-undangan` (jenis, detail, tema; rate limit 10/menit; `KuotaUndangan`).
- Keputusan kecil: hitung mundur Beranda memakai 09:00 (belum ada kolom jam); label status "Terbit" mengikuti DB; tanggal acara wajib >= hari ini; slug maks 60 (mengikuti `SlugUndangan`); layar "berhasil dibuat" tidak dibuat (langsung redirect); `bagian` = `{urutan, sembunyi}` (editor Fase 3 menyesuaikan di `BuatUndangan::buat()`); kuota/kunci tema aktif hanya bila `Pengaturan paket.aktif` benar, kolom paket pengguna menunggu Fase 6.
- Catatan lintas fase: Fase 3 membaca param query `undangan` di rute `edit-undangan` dan menamai rute editor `editor`/`undangan.edit` (wizard redirect ke sana bila ada). Fase 4 butuh rute publik `/{slug}` untuk "Lihat seperti tamu". QR dialog Bagikan menunggu Fase 5. Beranda belum memakai `KuotaUndangan` (Fase 6).
- QA: server dev sering sangat sibuk (proses lain di mesin); skrip QA jalan sebagai root, `waitUntil:'load'`.

### Fase 3 (selesai 2026-10-06)
- Jadi: editor `/edit-undangan/{undangan?}` (layout penuh `layouts/editor`, 18 bagian + urut/sembunyi, bidang per jenis, daftar berulang, tema, pengaturan, simpan otomatis, kanvas klik-untuk-pilih, terbit/terbitkan ulang/jadikan draf via `PenerbitUndangan`); `RenderUndangan` (kontrak `html()`/`untukUndangan()`, mode edit `data-kunci`, titik sambung Fase 4 `data-uy-bagian`/`data-uy-aksi`); Pratinjau `/pratinjau/{undangan?}` (bingkai iframe sandbox + CSP); Template Desain `/template`; Link Kustom `/link-kustom/{undangan?}`; `RingkasanAkun::tautanUndangan()` terisi.
- Keputusan kecil: `terbitan` = `{judul, slug, isi, bagian, pengaturan, tema, tanda, diterbitkan_at}`; `ada_perubahan` dari tanda tangan isi+bagian+tema+pengaturan+judul; `bagian.sembunyi` = daftar id; struktur isi ekstra `_foto/_galeri/_gaya/_latarTetap/_cincin/_sembunyi` dibaca renderer; riwayat slug di `pengaturan['riwayat_slug']` (maks 20); draf tidak bisa ganti slug lewat Link Kustom; slug 3-60 (bukan 3-40); link terbit diganti lewat dialog Terbitkan ulang atau Link Kustom; tombol "Buka di tab baru" pratinjau membuka bingkai (arahkan ke `/{slug}` setelah Fase 4).
- Tertunda: foto/pemilih gambar/crop/latar tetap (menunggu Media Fase 8), gaya teks per bidang + urungkan/ulangi, QR (Fase 5), rute publik `/{slug}` (Fase 4), pemutar musik (Fase 4/8), dokumen tema Theme Creator di renderer, mode demo Link Kustom.
- QA ganti-link 390 px belum diuji (slug sudah dipakai di putaran 1440); langkah lain tanpa galat JS.

### Fase 4 (selesai 2026-10-06)
- Jadi: `routes/publik.php` (`/{slug}` tangkap-semua bernama `undangan.publik`, `/{slug}/ucapan` GET/POST, `/{slug}/rsvp` POST), `UndanganPublikController`, `MetaUndangan` (SEO/OG), `CatatTayangan`, view `publik/undangan` (dokumen sendiri, kolom 480 px), aset statis `public/undangan/tamu.{js,css}` + `og-bawaan.png` (tanpa Vite). Gerbang buka, musik, hitung mundur, salin, lembar RSVP/ucapan, "muat ucapan lainnya".
- Keputusan kecil: isi hanya dari `terbitan` (terbit tanpa `terbitan.isi` = 404); pemilik login di slug draf diarahkan ke pratinjau; `robots` `noindex, nofollow` (desain tak menyatakan boleh diindeks); `?to=` kode tamu (10 karakter) dicocokkan, teks lain jadi nama sapaan (maks 60, di-escape); RSVP anonim maks 20 jiwa, tamu terdaftar maks `tamu.jumlah`, "tidak hadir" disimpan jumlah 0; satu baris RSVP per `tamu_id`; pesan RSVP ikut tampil sebagai ucapan; rate limit per IP(HMAC)+undangan 10 menit: 5 RSVP, 3 ucapan, 30 tulis umum, baca ucapan 60/menit; tayangan dedupe 30 menit via cache HMAC(IP|UA), IP tidak disimpan, bot dan pemilik login diabaikan; honeypot `situs_web`; header `no-cache, private`, `nosniff`, Referrer-Policy, Permissions-Policy, CSP ketat (skrip 'self' + beacon Cloudflare); bagian "tamu/QR" disembunyikan di publik (`tanpaQr`) sampai QR sungguhan Fase 5.
- Tertunda: moderasi/penutupan/sematan ucapan (tabel `ucapan` belum punya kolom; Fase 5 Buku Tamu), musik YouTube (`musLink`, hanya tautan), latar tetap video/slideshow, `rsLabelWa` tidak dikirim, tes CSRF (dinonaktifkan di lingkungan tes; ditangani 419 ramah di JS).
