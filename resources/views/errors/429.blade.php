@php
    $pesan = trim((string) ($exception?->getMessage() ?? ''));
    $bawaan = ['', 'Too Many Requests', 'Too Many Attempts.'];
@endphp
<x-galat-halaman kode="429" judul="Terlalu banyak permintaan" :pesan="in_array($pesan, $bawaan, true) ? 'Kamu terlalu sering mencoba. Tunggu beberapa saat, lalu coba lagi.' : $pesan" :ulang="true" />
