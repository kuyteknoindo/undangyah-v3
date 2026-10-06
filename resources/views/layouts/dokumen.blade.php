@php
    $judul ??= 'Dokumen';
    $indeks ??= false;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="{{ $indeks ? 'index, follow' : 'noindex, nofollow' }}">
    <title>{{ $judul }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="flex min-h-screen flex-col">
    <a href="#konten" class="sr-only z-[80] rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Lewati ke konten</a>
    <header class="flex min-h-[68px] items-center justify-between gap-3 border-b border-line bg-white px-4 sm:px-6 lg:px-8">
        <a href="{{ url('/') }}" aria-label="undangyah, beranda"><x-logo /></a>
        <nav aria-label="Akun" class="flex items-center gap-2">
            @auth
                <a href="{{ route('beranda') }}" class="btn btn-secondary btn-sm">Ke aplikasi</a>
            @else
                <a href="{{ route('masuk') }}" class="btn btn-secondary btn-sm">Masuk</a>
                <a href="{{ route('daftar') }}" class="btn btn-primary btn-sm">Daftar</a>
            @endauth
        </nav>
    </header>
    <main id="konten" class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
        <h1 class="sr-only">{{ $judul }}</h1>
        {{ $slot }}
    </main>
    <footer class="pb-6 text-center text-xs leading-5 text-subtle">&copy; {{ date('Y') }} {{ config('app.name') }}</footer>
</div>
<x-toast />
</body>
</html>
