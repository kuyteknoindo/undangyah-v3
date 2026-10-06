{{--
    Grafik batang vertikal. <x-statistik.batang :nilai="[..]" :label="[..]" :sub="[..]" :tips="[..]" :lebar="24" satuan="kunjungan" kosong="..." judul="..." />
    Setiap batang bisa difokus/diklik untuk menampilkan keterangannya; tabel setara tersedia lewat tombol Tabel.
--}}
@props(['nilai', 'label', 'sub' => [], 'tips', 'judul', 'kosong', 'rapat' => false])
@php
    $maks = max([0, ...$nilai]);
    $skala = max(4, (int) (ceil($maks / 4) * 4));
    $jumlah = count($nilai);
    $puncak = $maks > 0 ? array_search($maks, $nilai, true) : -1;
@endphp
<div x-data="{ pilih: -1, tips: @js($tips), awal: @js($maks > 0 ? 'Puncak: '.$tips[$puncak].'.' : $kosong) }" class="flex flex-col gap-2">
    <div class="flex gap-2">
        <div aria-hidden="true" class="flex h-40 w-9 shrink-0 flex-col justify-between pb-0 text-right text-[11px] leading-none text-muted tabular-nums">
            <span>{{ $skala }}</span><span>{{ $skala / 2 }}</span><span>0</span>
        </div>
        <div class="min-w-0 flex-1">
            <div role="group" aria-label="{{ $judul }}" class="relative flex h-40 items-end border-b border-line {{ $rapat ? 'gap-px' : 'gap-1' }}" style="background-image: linear-gradient(to bottom, #eef0f3 1px, transparent 1px); background-size: 100% 50%; background-repeat: repeat-y;">
                @foreach ($nilai as $i => $n)
                    <button type="button" aria-label="{{ $tips[$i] }}" title="{{ $tips[$i] }}" x-on:click="pilih = pilih === {{ $i }} ? -1 : {{ $i }}"
                            class="group relative flex h-full min-w-0 flex-1 items-end justify-center focus-visible:outline-2">
                        <span class="block w-full max-w-6 rounded-t-md transition-colors {{ $n > 0 ? 'bg-brand group-hover:bg-brand-dark' : 'bg-transparent' }}"
                              x-bind:class="pilih === {{ $i }} ? '!bg-[#1e3a8a]' : ''"
                              style="height: {{ $n > 0 ? max(2, round($n / $skala * 100, 1)) : 0 }}%"></span>
                    </button>
                @endforeach
                @if ($maks === 0)
                    <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-[13px] text-muted">{{ $kosong }}</div>
                @endif
            </div>
            <div aria-hidden="true" class="mt-1.5 flex {{ $rapat ? 'gap-px' : 'gap-1' }}">
                @foreach ($label as $i => $l)
                    <span class="flex min-w-0 flex-1 flex-col items-center text-[11px] leading-[14px] text-muted"><span class="whitespace-nowrap">{{ $l }}</span>@if (($sub[$i] ?? '') !== '')<span class="whitespace-nowrap text-subtle">{{ $sub[$i] }}</span>@endif</span>
                @endforeach
            </div>
        </div>
    </div>
    <div aria-live="polite" class="text-[13px] leading-5 text-body" x-text="pilih >= 0 ? tips[pilih] : awal">{{ $maks > 0 ? 'Puncak: '.$tips[$puncak].'.' : $kosong }}</div>
</div>
