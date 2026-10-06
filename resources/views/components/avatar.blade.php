{{-- Avatar: foto bila ada, selain itu inisial di atas warna pilihan. <x-avatar :user="$user" kelas="size-20 text-2xl" /> atau :foto/:warna/:ini manual (pratinjau). --}}
@props(['user' => null, 'foto' => null, 'warna' => null, 'ini' => null, 'kelas' => 'size-10 text-sm'])
@php
    $foto ??= $user?->fotoUrl();
    $warna ??= $user?->warnaAvatar() ?? '#1D4ED8';
    $ini ??= $user?->inisial() ?? '?';
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-bold text-white', $kelas]) }} style="background-color: {{ $warna }}">
    @if ($foto)
        <img src="{{ $foto }}" alt="" class="size-full object-cover">
    @else
        {{ $ini }}
    @endif
</span>
