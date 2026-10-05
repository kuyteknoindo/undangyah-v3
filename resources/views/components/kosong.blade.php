{{-- Empty state. <x-kosong judul="Belum ada undangan" teks="..." ikon="edit"> <a ...>Aksi</a> </x-kosong> --}}
@props(['judul', 'teks' => null, 'ikon' => 'edit'])
<div {{ $attributes->merge(['class' => 'kosong']) }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon :nama="$ikon" :ukuran="22" /></span>
    <div class="mt-1 text-sm leading-5 font-semibold text-ink">{{ $judul }}</div>
    @if ($teks)<div class="sub max-w-sm">{{ $teks }}</div>@endif
    @if (trim((string) $slot) !== '')<div class="mt-2 flex flex-wrap justify-center gap-2">{{ $slot }}</div>@endif
</div>
