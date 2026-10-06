@props(['judul', 'sub' => null])
<div class="flex flex-col items-center gap-1.5 text-center">
    <a href="{{ route('beranda') }}" title="Ke beranda" class="mb-2.5"><x-logo ukuran="lg" /></a>
    <h1 class="m-0 text-2xl leading-8 font-semibold tracking-[-0.02em] text-ink">{{ $judul }}</h1>
    @if ($sub)
        <p class="m-0 text-sm leading-5 text-muted">{{ $sub }}</p>
    @endif
</div>
