{{-- Lockup logo asli (409x95). Lebar tampil 164 px (md) atau 204 px (lg, tepat setengah ukuran asli untuk layar retina); atribut width/height mencegah layout shift. --}}
@props(['ukuran' => 'md'])
@php
    [$lebar, $tinggi] = $ukuran === 'lg' ? [204, 47] : [164, 38];
@endphp
<img src="{{ asset('brand/undangyah-logo-full.png') }}" alt="undangyah.com, undangan digital" width="{{ $lebar }}" height="{{ $tinggi }}" decoding="async" {{ $attributes->merge(['class' => 'block h-auto max-w-full']) }}>
