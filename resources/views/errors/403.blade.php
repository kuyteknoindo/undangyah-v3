@php
    $pesan = trim((string) ($exception?->getMessage() ?? ''));
    $bawaan = ['', 'Forbidden', 'This action is unauthorized.'];
@endphp
<x-galat-halaman kode="403" judul="Akses ditolak" :pesan="in_array($pesan, $bawaan, true) ? 'Kamu tidak punya akses ke halaman ini. Pastikan masuk dengan akun yang benar.' : $pesan" />
