<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul ?? config('app.name') }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<main class="mx-auto flex min-h-screen w-full max-w-[560px] flex-col items-center justify-center gap-4 px-6 py-10 text-center">
    <a href="{{ url('/') }}" class="mb-1 inline-flex items-center gap-2.5" aria-label="undangyah, beranda"><x-logo /><span class="sr-only">Beranda</span></a>
    {{ $slot }}
</main>
</body>
</html>
