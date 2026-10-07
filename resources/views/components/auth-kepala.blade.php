@props(['judul', 'sub' => null])
<div class="au-kepala">
    <a href="{{ route('beranda') }}" title="Ke beranda" aria-label="app.undangyah.com — beranda" class="au-logo"><img src="{{ asset('brand/undangyah-logo-ikon.png') }}" alt="Logo undangyah" width="56" height="56" decoding="async"></a>
    <h1 class="au-judul">{{ $judul }}</h1>
    @if ($sub)
        <div class="au-sub">{{ $sub }}</div>
    @endif
</div>
