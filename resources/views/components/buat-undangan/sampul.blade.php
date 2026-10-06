@props(['tema', 'judul', 'tanggal', 'besar' => false])
@php
    $g = \App\Support\PratinjauTema::gaya($tema);
@endphp
<div class="overflow-hidden rounded-xl" style="{{ $g['bingkai'] }}">
    <div class="relative flex flex-col items-center justify-center gap-1 text-center {{ $besar ? 'aspect-[4/5] px-6' : 'aspect-[4/5] px-2' }}" style="{{ $g['sampul'] }}">
        <span class="absolute rounded-full {{ $besar ? 'size-40' : 'size-24' }}" style="{{ $g['cincin'] }}"></span>
        <span class="absolute rounded-full {{ $besar ? 'size-52' : 'size-32' }}" style="{{ $g['cincin'] }}"></span>
        <span class="relative font-bold tracking-[0.2em] {{ $besar ? 'text-[10px]' : 'text-[6px]' }}" style="{{ $g['kicker'] }}">THE WEDDING OF</span>
        <span class="relative line-clamp-2 max-w-full font-semibold break-words {{ $besar ? 'text-3xl leading-9' : 'text-[13px] leading-4' }}" style="{{ $g['judul'] }}">{{ $judul }}</span>
        <span class="relative font-semibold {{ $besar ? 'text-xs' : 'text-[7px]' }}" style="{{ $g['tanggal'] }}">{{ $tanggal }}</span>
        <span class="relative mt-1 rounded-full {{ $besar ? 'h-6 w-24' : 'h-3 w-10' }}" style="{{ $g['tombol'] }}"></span>
    </div>
    <div class="flex flex-col items-center gap-1 {{ $besar ? 'p-3' : 'p-1.5' }}" style="{{ $g['badan'] }}">
        <span class="block h-1 w-3/4 rounded-full" style="{{ $g['garis'] }}"></span>
        <span class="block h-1 w-1/2 rounded-full" style="{{ $g['garis'] }}"></span>
        <span class="block size-1.5 rounded-full" style="{{ $g['titik'] }}"></span>
    </div>
</div>
