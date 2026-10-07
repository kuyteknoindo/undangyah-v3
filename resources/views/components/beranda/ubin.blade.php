{{-- Ubin "Aksi cepat" Beranda: tautan bila `href` ada, selain itu tombol (atribut wire:click dari pemanggil). --}}
@props(['href' => null, 'ikon', 'nama', 'ket'])
@php
    $kelas = 'box-border flex w-full flex-col items-start gap-2.5 rounded-xl border border-[#EEF0F3] bg-[#FCFCFD] p-3.5 text-left transition hover:border-brand-200 hover:bg-white disabled:cursor-not-allowed disabled:opacity-60';
@endphp
@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->class([$kelas]) }}>
@else
    <button type="button" {{ $attributes->class([$kelas]) }}>
@endif
    <span aria-hidden="true" class="flex size-[34px] shrink-0 items-center justify-center rounded-[9px] bg-brand-50 text-brand"><x-ikon :nama="$ikon" :ukuran="17" /></span>
    <span class="min-w-0">
        <span class="block text-sm leading-5 font-semibold text-ink">{{ $nama }}</span>
        <span class="block text-xs leading-4 text-muted">{{ $ket }}</span>
    </span>
@if ($href)
    </a>
@else
    </button>
@endif
