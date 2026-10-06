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
| 5 Tamu dan pembagian | tamu, QR, pesan, buku tamu, statistik, hadiah | SELESAI (tag fase-5) |
| 6 Paket dan pembayaran | paket, checkout, transfer manual | SELESAI (tag fase-6) |
| 7 Admin penjualan/pengguna | produk, add-on, voucher, pesanan, invoice, metode bayar, users, notifikasi, OTP | SELESAI (tag fase-7) |
| 8 Admin konten/sistem | tema, media, musik, storage, backup | SELESAI (tag fase-8; pemilih foto editor = 8b) |
| 9 Bantuan dan kualitas | bantuan, kontak, syarat, audit | halaman SELESAI; audit berjalan |

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

### Fase 5 (selesai 2026-10-06)
- Jadi: `KodeQr` (bacon/bacon-qr-code, SVG tanpa imagick/gd); RSVP & Tamu `/rsvp/{undangan?}` (CRUD, impor CSV 256 KB/500 baris, ekspor CSV aman rumus, RSVP manual, pengingat wa.me, hapus massal, `KuotaTamu`); Bagikan `/bagikan`, Template Pesan `/template-pesan`, Riwayat Pengiriman `/riwayat-kirim` (wa.me; status "Dibuka di WhatsApp"; antrean "kirim berikutnya"; QR umum + per tamu, unduh SVG/PNG); QR sungguhan di undangan publik (`/{slug}?to=<kode>`, hanya tamu terdaftar) dan dialog Terbitkan; Buku Tamu (moderasi, sematan maks 5, buka/tutup, mode setujui dulu, ekspor), Statistik (agregasi SQL, 7/30/90 hari), Hadiah Digital (rekening di `isi` kd*, hadiah masuk di tabel `hadiah_masuk`).
- Keputusan kecil: kuota tamu dari `Pengaturan paket.kuota_tamu.<paket>` (tanpa pengaturan = tanpa batas); nomor tamu `62...` (9-13 digit); default template pesan per pengguna di `Pengaturan pesan_u<id>`; kolom `tamu.template_pesan`, `tamu.salam`, `tamu.diingatkan_at`; pengaturan buku tamu di `undangan.pengaturan['buku_tamu']={buka,setujui}` (berlaku seketika, tanpa memicu "ada perubahan"); `pengaturan['hadiah']` hanya `ucapan_terima`; editor kini hanya menulis kunci `lingkaran` di `pengaturan` (kunci halaman lain tidak tertimpa); pengingat dari halaman RSVP belum dicatat di `riwayat_kirim`; statistik tanpa donat RSVP (desain tak punya); CSS: breakpoint kustom `lg` terbaca lebih dulu dari `md`, hindari `md:` dan `lg:` pada properti sama.
- Tertunda: form ucapan di publik tetap tampil saat kolom ditutup (kirim ditolak 403 dengan pesan; sembunyikan form butuh ubah renderer/tamu.js); email nyata (kanal email = mailto); editor di tab lain yang menyimpan otomatis bisa menimpa kunci `isi` (mis. rekening) yang diubah halaman Hadiah; pilihan massal RSVP hanya halaman aktif.

### Fase 6 (selesai 2026-10-06)
- Jadi: Paket & Billing `/billing`, Checkout `/checkout`, Konfirmasi Pembayaran `/konfirmasi-pembayaran/{nomor?}`, invoice cetak `/invoice/{nomor}`, bukti pembayaran privat `pesanan.bukti`; layanan `HitungPesanan` (satu-satunya tempat harga dihitung, server-side), `Keranjang` (sesi), `PembuatPesanan`, `KonfirmasiPesanan` (`lunas`/`tolak`/`batalkan`/`kedaluwarsakan`/`sapuKedaluwarsa`, idempoten, transaksi; dipakai admin Fase 7), `SinkronKuotaPaket`; perintah `undangyah:pesanan-kedaluwarsa` tiap 5 menit; `PaketSeeder` idempoten (firstOrCreate: tidak menimpa suntingan admin); kartu paket di sidebar nyata.
- Skema: `users.paket`/`paket_sampai`; tabel `paket`, `addon`, `voucher`, `metode_pembayaran`, `pesanan`, `item_pesanan`, `addon_pengguna` (migrasi 2026_10_05_6100xx). Uang integer rupiah.
- Keputusan kecil: langganan di kolom `users` (bukan tabel `langganan`); paket berbayar kedaluwarsa dihitung gratis (`User::paketAktif()`); Premium Rp 249.000/12 bulan, kuota 3 undangan; Deluxe berstatus draf ("Segera hadir"); kuota tamu kosong = tanpa batas; `Pengaturan paket.aktif` diisi true oleh seeder hanya bila belum diatur, kuota disalin dari tabel `paket` tiap simpan; voucher ditahan saat pesanan dibuat dan dikembalikan bila batal/kedaluwarsa; kedaluwarsa pesanan 24 jam (`pembayaran.kedaluwarsa_jam`), maks 3 pesanan menunggu per pengguna, pesanan identik tidak digandakan; total Rp 0 setelah voucher langsung lunas; bukti jpg/png/webp maks 2 MB di disk privat, hanya pemilik/admin; tombol simulasi bayar TIDAK ada (admin yang konfirmasi); rekening di seeder PLACEHOLDER (halaman menampilkan peringatan "jangan transfer" selama `data.placeholder` benar); invoice tanpa nama PT; `UserFactory` tidak mengisi `paket` (default gratis dari DB).
- Tertunda (Fase 7): CRUD admin paket/add-on/voucher/metode/pesanan; ganti rekening placeholder sebelum dipakai nyata; notifikasi admin saat bukti diunggah; alur refund; `KuotaUndangan::PERINGKAT` hanya gratis/premium/deluxe (paket kustom admin perlu dinamis); enforcement add-on selain kuota (domain, live streaming, blast WA, hapus logo, musik, tema eksklusif) menunggu fitur terkait; batas pakai voucher per pengguna.

### Fase 7 (selesai 2026-10-06)
- Jadi (9 halaman `/admin/*`): Product (`paket`), Addons, Voucher, Metode Pembayaran, Data Pesanan (konfirmasi lunas, tolak bukti, batalkan, kedaluwarsa, refund, pending, catatan internal, CSV), Invoices (ringkasan, antrean tinjau, kirim ulang tagihan via notifikasi), Users, Notifikasi (siaran), OTP Setting. `KuotaUndangan` kini dinamis (peringkat dari tabel `paket`).
- Keamanan/keputusan kecil: setiap aksi admin cek `isAdmin()` di server; admin tidak bisa mengubah/menghapus/menangguhkan diri sendiri atau menyisakan nol admin; hapus akun = ketik email; paket/addon/voucher/metode yang sudah dipakai pesanan hanya bisa diarsipkan/dinonaktifkan; Gratis/Premium tidak bisa diarsipkan; kode paket/addon otomatis dari nama dan tidak berubah; banner merah di admin selama metode pembayaran aktif masih berisi rekening placeholder (tanda `data.placeholder` hilang hanya bila rekening nyata disimpan); `catatan_internal` pesanan tidak pernah tampil ke pembeli; refund hanya penandaan (pilihan turunkan paket ke Gratis; add-on tidak dicabut); admin dapat notifikasi saat bukti baru diunggah; log aksi admin atas akun di tabel `log_aksi_admin`; tangguhkan akun = putus sesi + blokir masuk + undangan 404 (`PengamanAkun`, `users.ditangguhkan_at`); OTP dikonfigurasi lewat `Pengaturan 'otp'` (panjang 4-8, berlaku 1-30 menit, salah 1-10, jeda 10-300 dtk, batas/jam 1-30; nilai di luar rentang jatuh ke bawaan); siaran notifikasi lewat job per 500 pengguna dengan kursor (idempoten), tombol "Proses sekarang" karena pekerja antrean belum aktif.
- **Masuk sebagai (impersonasi)** dibuat karena desain memintanya, terbatas: hanya target berperan user, aktif, terverifikasi; maks 30 menit; tercatat mulai/selesai; banner "Kembali ke Admin"; diblokir untuk komponen akun/auth/checkout/konfirmasi-pembayaran/paket-billing, unggah berkas, dan permintaan non-Livewire selain `keluar`/`impersonasi.akhiri`. Risiko tersisa: komponen lain (mis. hapus undangan, edit tamu) tetap bisa berjalan atas nama pengguna; komponen sensitif baru harus ditambahkan ke `PengamanAkun::KOMPONEN_TERBLOKIR`.
- Tertunda: aktifkan `undangyah-queue.service` (siaran otomatis); email nyata (SMTP); pengembalian dana sungguhan; tambah pengguna manual / reset sandi via tautan oleh admin; saklar OTP aktif, kanal WA/SMS; draf/jadwal siaran; `ModePemeliharaan` memakai `routeIs('livewire.*')` padahal rute update bernama `default-livewire.update` (periksa di Fase 9); batas pakai voucher per pengguna; peringatan ke pembeli bila pesanan menunggu menampilkan rekening contoh.

### Fase 8 (selesai 2026-10-06)
- Jadi: Theme Creator `/admin/theme` (daftar + editor di `?tema=<id|baru>`; `DokumenTema` memvalidasi; kontras teks >= 3:1 wajib untuk publikasi; riwayat 20 versi; arsip/hapus lunak; pratinjau iframe sandbox + CSP; tema hanya menyimpan warna hex, font daftar putih, `global.base` 12-20, jadi renderer tidak pernah menerima CSS/HTML dari admin), Media `/admin/media`, Storage `/admin/storage`, Musik `/admin/musik`, Backup `/admin/backup`.
- Media: disk `media` (`storage/app/public/media`, URL `/storage/media/...`, nama acak 32 hex, tipe dideteksi dari ISI berkas; SVG/HTML/XML/PHP/ZIP ditolak; JPEG/PNG disimpan ulang via GD (EXIF/GPS terbuang), thumbnail webp 480 px); `PenyimpanMedia` (simpan/hapus/kuota/`batasFotoGaleri`), `ReferensiMedia::sinkronPemilik`, `KatalogLagu`; bawaan kuota 100 MB/pengguna, maks 10 MB/file (plafon 12 MB mengikuti batas Livewire), video nonaktif; Storage membersihkan hanya media tak terpakai, tak dirujuk, > 24 jam; Musik tabel `lagu` (kode dipakai `isi['musLagu']`), lagu bawaan l1-l5 tanpa audio (admin perlu mengunggah), pemutar publik menerima path situs.
- Backup: `pg_dump -Fc` (database), arsip file, penuh; checksum SHA-256; unduh terotorisasi; retensi; jadwal harian 02.00 default NONAKTIF (butuh `schedule:run` aktif); sandi DB hanya via `PGPASSWORD`; **restore ke database hidup sengaja TIDAK otomatis** (instruksi `pg_restore` manual di halaman); "Uji pulihkan" ke DB sementara butuh `ALTER ROLE undangyah_dev CREATEDB` (keputusan pemilik; saat ini tombol nonaktif).
- Tertunda: penyunting visual berbasis widget Theme Creator (kanvas/drag/animasi/Cover Gate/Musik Global, format dokumen node); `latar_tetap` hanya penanda; pemilih foto/crop/galeri di editor (8b); pembersihan media terjadwal; backup di server yang sama (belum ada salinan luar/enkripsi); komponen unggah admin belum di `PengamanAkun::KOMPONEN_TERBLOKIR`; hapus lagu bawaan membuat default `l1` di skema tidak valid.

### Fase 8b (selesai 2026-10-06): foto di editor
- Jadi: pemilih/unggah foto (dialog pustaka pengguna, maks 10 sekali unggah, progres, galat kuota), crop (`{ratio: bingkai|asli, zoom 100-300, x/y 0-100}` disimpan sebagai parameter, file asli tak diubah), galeri (batas dari `batasFotoGaleri`, layout grid/masonry/slider, naik/turun, link video http/https), Background Fixed (gambar saja; opasitas/blur/lapisan tervalidasi; hanya bila tema `latar_tetap`). Trait `FotoEditor`.
- Keamanan: klien hanya kirim id media; `src` diturunkan server dari `Media::url()` milik pengguna (aset sistem tidak bisa dipilih untuk foto undangan); struktur `_foto[slot]={src, mid, crop?}`; `ReferensiMedia::sinkronPemilik` dipanggil tiap perubahan; rate limit 120 aksi/menit.
- Kelengkapan: Foto lengkap bila foto latar pembuka ada, Gallery bila >= 1 foto; keduanya saran non-blokir di dialog terbit.
- Tertunda: pilih banyak + seret-urut galeri, tombol Background Fixed di tab Tema, dialog gambar BGF mereset slider belum tersimpan.

### Fase 9 bagian 1 (halaman, 2026-10-06)
- Jadi: Pusat Bantuan `/bantuan` (10 artikel seed, pencarian server, suara Ya/Tidak per pengguna), Hubungi Layanan `/hubungi` (tiket `TKT-<tahun>-<id>`, rate limit 5/jam, notifikasi ke admin, riwayat sendiri), Syarat `/syarat` dan Privasi `/privasi` (publik, `noindex` selama `legal.contoh` benar).
- **PEMILIK HARUS MENGISI** lewat `Pengaturan::simpan`: `kontak` = `{whatsapp, email, jam_layanan, jam_buka, jam_tutup, balasan}` (kosong = bagian kontak tidak tampil) dan `legal` = `{syarat:[{judul,isi}], privasi:[...], diperbarui, contoh:false}` (bawaan: teks contoh dari desain). Footer `layouts/guest.blade.php` masih memuat "PT Undangyah Digital" hardcode.
- FAQ disesuaikan dengan kenyataan aplikasi (transfer bank manual, kode lewat email, WhatsApp belum tersedia).
- Tertunda: CRUD admin artikel bantuan; halaman admin tiket (lihat/balas/status); lampiran tiket; statistik suara; Pusat Bantuan khusus pengguna login.
