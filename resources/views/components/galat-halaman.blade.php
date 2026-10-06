@props(['kode', 'judul', 'pesan', 'ulang' => false])
{{-- Halaman galat HTTP (403/419/429/500/503). Tanpa route()/DB agar tetap tampil saat sistem bermasalah. --}}
<x-layouts.sederhana :judul="$judul">
    <span class="flex size-16 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon nama="bantuan" :ukuran="32" /></span>
    <div class="text-5xl leading-none font-bold tracking-tight text-brand">{{ $kode }}</div>
    <h1 class="m-0 text-2xl leading-8 font-semibold tracking-[-0.02em] text-ink">{{ $judul }}</h1>
    <p class="m-0 max-w-md text-sm leading-6 text-muted">{{ $pesan }}</p>
    <div class="mt-2 flex flex-wrap justify-center gap-2">
        @if ($ulang)<a href="{{ url()->current() }}" class="btn btn-primary">Coba lagi</a>@endif
        <a href="{{ url('/beranda') }}" class="btn btn-secondary">Ke Beranda</a>
    </div>
</x-layouts.sederhana>
