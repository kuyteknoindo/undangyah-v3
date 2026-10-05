@php
    use App\Support\Navigasi;

    $pengguna = auth()->user();
    $judul ??= 'Beranda';
    $sub ??= null;
    $admin ??= false;
    $adminAktif = request()->routeIs('admin.*');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judul }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div x-data="{ buka: false }" x-on:keydown.escape.window="buka = false" x-on:livewire:navigated.window="buka = false" class="flex min-h-screen">

    <a href="#konten" class="sr-only z-[80] rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Lewati ke konten</a>

    {{-- Scrim menu geser (di bawah 1024 px) --}}
    <div x-show="buka" x-cloak x-transition.opacity class="fixed inset-0 z-[55] bg-gray-900/40 lg:hidden" x-on:click="buka = false" aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside id="menu-utama" aria-label="Menu utama"
           x-bind:class="buka ? 'translate-x-0 shadow-[0_24px_60px_rgba(16,24,40,0.28)]' : 'invisible -translate-x-full lg:visible lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-[60] flex w-[276px] flex-col gap-4 overflow-y-auto overscroll-contain border-r border-line bg-white px-3 py-4 transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:shadow-none">

        <div class="flex items-center justify-between px-1">
            <a href="{{ route('beranda') }}" wire:navigate aria-label="undangyah, beranda"><x-logo /></a>
            <button type="button" class="flex size-9 items-center justify-center rounded-lg text-muted hover:bg-gray-100 lg:hidden" x-on:click="buka = false" aria-label="Tutup menu">
                <x-ikon nama="tutup" :ukuran="20" />
            </button>
        </div>

        <a href="{{ route('buat-undangan') }}" wire:navigate class="btn btn-primary min-h-10">
            <x-ikon nama="tambah" :ukuran="16" /> Buat Undangan
        </a>

        <nav aria-label="Navigasi utama" class="flex flex-1 flex-col gap-4">
            <div class="flex flex-col gap-0.5">
                <div class="px-2.5 pb-1 text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Menu</div>
                @foreach (Navigasi::menu() as $item)
                    <x-nav-item :href="route($item['route'])" :ikon="$item['ikon']" :badge="$item['badge'] ?? null" :aktif="request()->routeIs($item['route'])">{{ $item['label'] }}</x-nav-item>
                @endforeach
            </div>

            @if ($pengguna?->isAdmin())
                <div class="flex flex-col gap-0.5" x-data="{ adm: {{ $adminAktif ? 'true' : 'false' }} }">
                    <div class="px-2.5 pb-1 text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Admin</div>
                    <button type="button" class="nav-item" x-on:click="adm = !adm" x-bind:aria-expanded="adm" aria-controls="submenu-admin">
                        <x-ikon nama="admin" class="text-subtle" />
                        <span class="flex-1 text-left">Panel Admin</span>
                        <x-ikon nama="panah-bawah" :ukuran="16" class="text-subtle transition-transform" x-bind:class="adm ? 'rotate-180' : ''" />
                    </button>
                    <div id="submenu-admin" x-show="adm" x-cloak class="ml-4 flex flex-col gap-0.5 border-l border-line pl-2">
                        @foreach (Navigasi::admin() as $grup)
                            <div class="px-2.5 pt-2 pb-1 text-[10px] leading-[14px] font-semibold tracking-[0.08em] text-subtle uppercase">{{ $grup['judul'] }}</div>
                            @foreach ($grup['item'] as $item)
                                <x-nav-item :href="route('admin.'.$item['slug'])" :ikon="$item['ikon']" :aktif="request()->routeIs('admin.'.$item['slug'])">{{ $item['label'] }}</x-nav-item>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="flex flex-col gap-0.5">
                <div class="px-2.5 pb-1 text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Lainnya</div>
                @foreach (Navigasi::lainnya() as $item)
                    <x-nav-item :href="route($item['route'])" :ikon="$item['ikon']" :aktif="request()->routeIs($item['route'])">{{ $item['label'] }}</x-nav-item>
                @endforeach
            </div>
        </nav>

        <a href="{{ route('billing') }}" wire:navigate class="flex items-center gap-2.5 rounded-xl border border-line bg-surface px-3 py-2.5 text-ink">
            <span class="flex size-8 items-center justify-center rounded-lg bg-brand-50 text-brand"><x-ikon nama="bintang" :ukuran="16" /></span>
            <span class="flex min-w-0 flex-col">
                <span class="text-[13px] leading-[18px] font-semibold">Paket Gratis</span>
                <span class="truncate text-xs leading-4 text-muted">Lihat paket &amp; billing</span>
            </span>
        </a>

        <form method="POST" action="{{ route('keluar') }}">
            @csrf
            <button type="submit" class="nav-item"><x-ikon nama="keluar" class="text-subtle" /> <span>Keluar</span></button>
        </form>
    </aside>

    {{-- Konten --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex min-h-[68px] flex-wrap items-center gap-3.5 border-b border-line bg-white px-3.5 py-3 sm:px-5 lg:px-8">
            <button type="button" class="flex size-10 items-center justify-center rounded-[10px] border border-line-strong bg-white text-nav lg:hidden"
                    x-on:click="buka = true" aria-label="Buka menu navigasi" aria-controls="menu-utama" x-bind:aria-expanded="buka">
                <x-ikon nama="menu" :ukuran="20" />
            </button>

            <div class="min-w-0 flex-1">
                @if ($adminAktif || $admin)
                    <div class="text-xs leading-4 font-semibold text-brand">Panel Admin</div>
                @endif
                <h1 class="truncate text-lg leading-6 font-semibold tracking-tight text-ink sm:text-xl sm:leading-7">{{ $judul }}</h1>
                @if ($sub)
                    <div class="truncate text-[13px] leading-[18px] text-muted">{{ $sub }}</div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('notifikasi') }}" wire:navigate aria-label="Notifikasi" class="flex size-10 items-center justify-center rounded-[10px] border border-line-strong bg-white text-nav hover:bg-gray-50">
                    <x-ikon nama="lonceng" :ukuran="18" />
                </a>
                <a href="{{ route('akun') }}" wire:navigate aria-label="Buka pengaturan akun" class="flex items-center gap-2.5 rounded-[10px] border border-line-strong bg-white py-1 pr-3 pl-1 hover:bg-gray-50">
                    <span class="flex size-8 items-center justify-center rounded-lg bg-brand text-xs font-bold text-white">{{ $pengguna?->inisial() }}</span>
                    <span class="hidden min-w-0 flex-col text-left sm:flex">
                        <span class="max-w-[160px] truncate text-[13px] leading-4 font-semibold text-ink">{{ $pengguna?->name }}</span>
                        <span class="max-w-[160px] truncate text-xs leading-4 text-muted">{{ $pengguna?->email }}</span>
                    </span>
                </a>
            </div>
        </header>

        <main id="konten" class="flex-1 px-3.5 py-5 sm:px-5 lg:px-8 lg:py-7">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
