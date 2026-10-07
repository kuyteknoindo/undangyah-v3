@php
    use App\Support\Navigasi;

    $pengguna = auth()->user();
    $judul ??= 'Beranda';
    $sub ??= null;
    $admin ??= false;
    $cari ??= false;
    $adminAktif = request()->routeIs('admin.*');
    $eyebrow ??= ($adminAktif || $admin) ? 'Panel Admin' : null;
    // Satu kueri untuk lonceng (semua belum dibaca) dan lencana RSVP di sidebar.
    $hitungNotif = $pengguna?->notifikasi()->tampil()->belumDibaca()
        ->selectRaw('count(*) as total, coalesce(sum(case when tipe = ? then 1 else 0 end), 0) as rsvp', [\App\Models\Notifikasi::TIPE_RSVP])->first();
    $belumDibaca = (int) ($hitungNotif->total ?? 0);
    $rsvpBaru = (int) ($hitungNotif->rsvp ?? 0);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <x-meta-brand />
    <title>{{ $judul }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<div x-data="{ buka: false }" x-on:keydown.escape.window="buka = false" x-on:livewire:navigated.window="buka = false" class="flex min-h-screen">

    <a href="#konten" class="sr-only z-[80] rounded-lg bg-white px-3 py-2 text-sm font-semibold text-brand focus:not-sr-only focus:fixed focus:top-3 focus:left-3">Lewati ke konten</a>

    {{-- Scrim menu geser (di bawah 1024 px) --}}
    <div x-show="buka" x-cloak x-transition.opacity class="fixed inset-0 z-[55] bg-gray-900/40 lg:hidden" x-on:click="buka = false" aria-hidden="true"></div>

    {{-- Sidebar --}}
    <aside id="menu-utama" aria-label="Menu utama" x-trap.noscroll="buka"
           x-bind:class="buka ? 'translate-x-0 shadow-[0_24px_60px_rgba(16,24,40,0.28)]' : 'invisible -translate-x-full lg:visible lg:translate-x-0'"
           class="fixed inset-y-0 left-0 z-[60] flex w-[248px] flex-col gap-4 overflow-y-auto overscroll-contain border-r border-line bg-white px-3 py-4 transition-transform duration-200 ease-out lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:shadow-none">

        <div class="flex items-center justify-between">
            <a href="{{ route('beranda') }}" wire:navigate title="Ke beranda" aria-label="undangyah.com, beranda" class="flex min-h-12 items-center px-2"><x-logo /></a>
            <button type="button" class="flex size-9 items-center justify-center rounded-lg text-muted hover:bg-gray-100 lg:hidden" x-on:click="buka = false" aria-label="Tutup menu">
                <x-ikon nama="tutup" :ukuran="20" />
            </button>
        </div>

        <a href="{{ route('buat-undangan') }}" wire:navigate class="btn btn-primary min-h-10">
            <x-ikon nama="tambah" :ukuran="16" :tebal="2.5" /> Buat Undangan
        </a>

        <nav aria-label="Navigasi utama" class="flex flex-1 flex-col gap-[18px]">
            <div class="flex flex-col gap-0.5">
                <div class="px-2.5 pb-1 text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Menu</div>
                @foreach (Navigasi::menu() as $item)
                    <x-nav-item :href="route($item['route'])" :ikon="$item['ikon']" :badge="$item['badge'] ?? null" :lencana="$item['route'] === 'rsvp' ? $rsvpBaru : 0" :aktif="request()->routeIs($item['route'])">{{ $item['label'] }}</x-nav-item>
                @endforeach
            </div>

            @if ($pengguna?->isAdmin())
                <div class="flex flex-col gap-0.5" x-data="{ adm: {{ $adminAktif ? 'true' : 'false' }} || (() => { try { return localStorage.getItem('undangyah-admin-open') === '1'; } catch (e) { return false; } })() }">
                    <div class="px-2.5 pb-1 text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Admin</div>
                    <button type="button" class="nav-item" x-on:click="adm = !adm; try { localStorage.setItem('undangyah-admin-open', adm ? '1' : '0'); } catch (e) {}" x-bind:aria-expanded="adm" aria-controls="submenu-admin">
                        <x-ikon nama="admin" />
                        <span class="flex-1 text-left">Panel Admin</span>
                        <x-ikon nama="panah-bawah" :ukuran="16" class="text-subtle transition-transform duration-150" x-bind:class="adm ? 'rotate-180' : ''" />
                    </button>
                    <div id="submenu-admin" x-show="adm" x-cloak class="mt-0.5 mr-0 mb-0.5 ml-[19px] flex flex-col gap-px border-l border-line pl-2.5">
                        @foreach (Navigasi::admin() as $grup)
                            <div class="px-2 pt-2 pb-[3px] text-[10px] leading-[13px] font-semibold tracking-[0.06em] text-subtle uppercase">{{ $grup['judul'] }}</div>
                            @foreach ($grup['item'] as $item)
                                <x-nav-item :kecil="true" :href="route('admin.'.$item['slug'])" :ikon="$item['ikon']" :aktif="request()->routeIs('admin.'.$item['slug'])">{{ $item['label'] }}</x-nav-item>
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

        @php($paketSidebar = auth()->check() ? app(\App\Services\RingkasanAkun::class)->paket(auth()->user()) : null)
        <a href="{{ route('billing') }}" wire:navigate class="box-border flex items-center gap-2.5 rounded-xl border border-line bg-surface px-3 py-2.5 text-ink">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#FFF4D6] text-[#B7791F]"><x-ikon nama="bintang" :ukuran="16" /></span>
            <span class="min-w-0 flex-1">
                <span class="block text-[13px] leading-[18px] font-semibold">Paket {{ $paketSidebar['nama'] ?? 'Gratis' }}</span>
                <span class="block text-xs leading-4 text-muted">{{ ($paketSidebar['aktif'] ?? false) && $paketSidebar['sampai'] ? 'Aktif sampai '.$paketSidebar['sampai']->translatedFormat('j M Y') : 'Upgrade untuk fitur penuh' }}</span>
            </span>
            <x-ikon nama="panah-kanan" :ukuran="16" class="text-subtle" />
        </a>

        <form method="POST" action="{{ route('keluar') }}">
            @csrf
            <button type="submit" class="nav-item text-[#dc2626]"><x-ikon nama="keluar" /> <span>Keluar</span></button>
        </form>
    </aside>

    {{-- Konten --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="box-border flex min-h-[68px] items-center gap-3.5 border-b border-line bg-white px-3.5 py-3 sm:px-5 lg:px-8">
            <button type="button" class="flex size-10 shrink-0 items-center justify-center rounded-[10px] border border-line bg-white text-[#4b5563] lg:hidden"
                    x-on:click="buka = true" aria-label="Buka menu navigasi" aria-controls="menu-utama" x-bind:aria-expanded="buka">
                <x-ikon nama="menu" :ukuran="20" />
            </button>

            <div class="min-w-0 flex-1">
                @if ($eyebrow)
                    <div class="text-xs leading-4 font-medium text-muted">{{ $eyebrow }}</div>
                @endif
                <h1 class="m-0 truncate text-lg leading-6 font-semibold tracking-[-0.015em] text-ink sm:text-xl sm:leading-7">{{ $judul }}</h1>
                @if ($sub)
                    <div class="truncate text-[13px] leading-[18px] text-muted">{{ $sub }}</div>
                @endif
            </div>

            @if ($cari)
                <form method="GET" action="{{ route('beranda') }}" role="search" class="relative hidden min-w-[140px] shrink basis-[320px] sm:block">
                    <label for="cari-global" class="sr-only">Cari undangan atau tamu</label>
                    <span class="pointer-events-none absolute top-[11px] left-3 flex text-subtle" aria-hidden="true"><x-ikon nama="cari" :ukuran="18" /></span>
                    <input id="cari-global" type="search" name="cari" value="{{ request('cari') }}" maxlength="80" autocomplete="off" placeholder="Cari undangan atau tamu"
                           class="box-border h-10 w-full rounded-[10px] border border-line bg-[#F7F8FA] pr-3 pl-[38px] text-sm leading-5 text-ink outline-none placeholder:text-muted focus:border-brand focus:bg-white focus:ring-[3px] focus:ring-brand/15">
                </form>
            @endif

            <div class="flex shrink-0 items-center gap-2.5">
                <a href="{{ route('notifikasi') }}" wire:navigate
                   x-data="{ n: {{ $belumDibaca }} }" x-on:notifikasi-dihitung.window="n = $event.detail.jumlah"
                   x-bind:aria-label="'Notifikasi, ' + n + ' belum dibaca'" aria-label="Notifikasi, {{ $belumDibaca }} belum dibaca"
                   class="relative box-border flex size-10 shrink-0 items-center justify-center rounded-[10px] border border-line bg-white text-[#4b5563] hover:bg-gray-50">
                    <x-ikon nama="lonceng" :ukuran="18" />
                    <span x-show="n > 0" @if ($belumDibaca < 1) x-cloak @endif aria-hidden="true" data-badge-notifikasi
                          class="absolute -top-[5px] -right-[5px] box-border h-[18px] min-w-[18px] rounded-full border-2 border-white bg-[#EF4444] px-[5px] text-center text-[10px] leading-3.5 font-semibold text-white"
                          x-text="n > 99 ? '99+' : n">{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
                </a>
                <a href="{{ route('akun') }}" wire:navigate aria-label="Buka pengaturan akun" class="box-border flex min-h-10 items-center gap-2.5 rounded-[10px] border border-line bg-white py-[3px] pr-3 pl-[3px] hover:bg-gray-50">
                    <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-brand text-xs font-semibold text-white">{{ $pengguna?->inisial() }}</span>
                    <span class="hidden min-w-0 flex-col text-left sm:flex">
                        <span class="max-w-[160px] truncate text-[13px] leading-[17px] font-semibold text-ink">{{ $pengguna?->name }}</span>
                        <span class="max-w-[160px] truncate text-[11px] leading-[14px] text-muted">{{ $pengguna?->email }}</span>
                    </span>
                </a>
            </div>
        </header>

        <main id="konten" class="flex-1 px-3.5 py-5 sm:px-5 lg:px-8 lg:pt-7 lg:pb-12">
            @if (session('sukses'))
                <div role="status" class="mx-auto mb-5 flex max-w-5xl items-center gap-2 rounded-xl bg-success-50 px-4 py-3 text-sm font-medium text-success">
                    <x-ikon nama="centang" :ukuran="18" /> {{ session('sukses') }}
                </div>
            @endif
            {{ $slot }}
        </main>
    </div>
</div>
<x-toast />
</body>
</html>
