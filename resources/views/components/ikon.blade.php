@props(['nama', 'ukuran' => 18])
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="{{ $ukuran }}" height="{{ $ukuran }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'shrink-0']) }}>{!! \App\Support\Ikon::ambil($nama) !!}</svg>
