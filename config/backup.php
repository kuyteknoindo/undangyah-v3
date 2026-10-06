<?php

return [
    // Folder hasil backup (privat, di luar public/). Izin folder 700, berkas 600.
    'direktori' => storage_path('app/backups'),

    // Nama koneksi database yang dicadangkan; kosong = koneksi bawaan aplikasi.
    'koneksi' => env('BACKUP_KONEKSI'),

    'pg_dump' => env('BACKUP_PG_DUMP', 'pg_dump'),
    'pg_restore' => env('BACKUP_PG_RESTORE', 'pg_restore'),
    'psql' => env('BACKUP_PSQL', 'psql'),

    // Disk yang ikut arsip file unggahan: disk => null (seluruh isi) atau daftar subfolder. Disk yang belum ada di
    // config/filesystems.php (mis. `media`, dibuat fitur Media) dilewati; disk non-lokal juga dilewati.
    'disk_file' => [
        'media' => null,
        'public' => null,
        'local' => ['bukti-pembayaran'],
    ],

    // Backup ditolak bila ruang bebas < faktor x perkiraan ukuran.
    'faktor_ruang' => 2,

    // Batas backup manual lewat UI per admin per jam.
    'maks_per_jam' => 3,

    'batas_waktu_detik' => 1800,

    // true = UI hanya memasukkan job ke antrean (butuh pekerja antrean aktif); false = jalan langsung di permintaan.
    'antrean' => (bool) env('BACKUP_ANTREAN', false),

    // Awalan nama database sementara untuk "Uji pulihkan". Nama lengkap: awalan_<8 huruf/angka acak>.
    'awalan_db_uji' => 'undangyah_uji_pulih',
];
