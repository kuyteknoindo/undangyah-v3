<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul ?? 'Masuk' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div class="flex min-h-screen flex-col px-4">
    <main class="flex flex-1 items-center justify-center py-7">
        <div class="flex w-full max-w-[440px] flex-col gap-6 rounded-2xl border border-line bg-white p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04),0_12px_32px_-12px_rgba(16,24,40,0.12)] sm:p-8 sm:pb-7">
            {{ $slot }}
        </div>
    </main>
    <footer class="pb-6 text-center text-xs leading-5 text-subtle">&copy; {{ date('Y') }} PT Undangyah Digital</footer>
</div>
</body>
</html>
