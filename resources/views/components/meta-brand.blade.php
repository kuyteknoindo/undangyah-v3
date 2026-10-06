{{-- Ikon situs, theme-color, dan (opsional) meta OG dasar dengan gambar bermerek. Dipakai di <head> layout. --}}
@props(['og' => false, 'judul' => null, 'deskripsi' => 'undangyah.com, undangan digital: buat, kelola, dan bagikan undangan pernikahan dan acara dari satu tempat.'])
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('brand/favicon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('brand/apple-touch-icon.png') }}">
<meta name="theme-color" content="#1D4ED8">
@if ($og)
    <meta name="description" content="{{ $deskripsi }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $judul ?? config('app.name') }}">
    <meta property="og:description" content="{{ $deskripsi }}">
    <meta property="og:image" content="{{ asset(\App\Services\MetaUndangan::GAMBAR_BAWAAN) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="{{ asset(\App\Services\MetaUndangan::GAMBAR_BAWAAN) }}">
@endif
