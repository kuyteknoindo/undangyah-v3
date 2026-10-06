{{-- Satu bidang isian. Variabel: $k (kunci), $meta. Nilai ada di $isi[$k]; perubahan disaring di server. --}}
@php
    $nilai = (string) ($isi[$k] ?? '');
    $galatK = $galatBidang[$k] ?? null;
    $tersembunyi = in_array($k, $sembunyi, true);
    $area = $meta['kind'] === 'text' && $meta['rows'] >= 2;
    $urlSah = $meta['kind'] === 'url' && $nilai !== '' && $galatK === null;
    $id = 'f-'.$k;
@endphp
<div wire:key="bidang-{{ $k }}" data-f="{{ $k }}" class="{{ $tersembunyi ? 'opacity-60' : '' }}">
    <div class="mb-1.5 flex items-center gap-1.5">
        <label for="{{ $id }}" class="flex-1 text-[13px] leading-[18px] font-bold">{{ $meta['label'] }}@if ($meta['opt'] && $meta['kind'] !== 'switch') <span class="ml-1 rounded-md bg-[#f2f4f7] px-1.5 text-[10px] leading-4 font-bold text-[#666c76]">Opsional</span>@endif</label>
        @if ($meta['canvas'])
            <button type="button" class="ed-kecil ed-ikon !size-7 !min-h-7 !rounded-lg" wire:click="toggleBidang('{{ $k }}')" aria-label="{{ $tersembunyi ? 'Tampilkan' : 'Sembunyikan' }} {{ $meta['label'] }} di undangan" title="{{ $tersembunyi ? 'Tampilkan di undangan' : 'Sembunyikan dari undangan' }}">
                <x-ikon :nama="$tersembunyi ? 'mata-tutup' : 'mata'" :ukuran="14" />
            </button>
        @endif
    </div>

    @switch($meta['kind'])
        @case('select')
            <div id="{{ $id }}" role="radiogroup" aria-label="{{ $meta['label'] }}" class="flex flex-col gap-1.5">
                @foreach ($meta['opts'] ?? [] as [$nilaiOpsi, $labelOpsi])
                    <button type="button" role="radio" aria-checked="{{ (string) $nilaiOpsi === $nilai ? 'true' : 'false' }}" class="ed-radio" wire:click="setBidang('{{ $k }}', '{{ $nilaiOpsi }}')">{{ app(\App\Services\IsiUndangan::class)->isiLabel($labelOpsi, $isi) }}</button>
                @endforeach
            </div>
            @break

        @case('switch')
            <button type="button" id="{{ $id }}" role="switch" aria-checked="{{ $nilai === '1' ? 'true' : 'false' }}" wire:click="setBidang('{{ $k }}', '{{ $nilai === '1' ? '0' : '1' }}')"
                    class="flex min-h-11 w-full items-center gap-3 rounded-[14px] border-2 border-[#e2e5e9] px-3 text-left">
                <span class="flex-1 text-sm font-semibold">{{ $nilai === '1' ? 'Aktif' : 'Mati' }}</span><span class="ed-sw"></span>
            </button>
            @break

        @case('date')
            <input id="{{ $id }}" type="date" class="ed-fld" wire:model.live="isi.{{ $k }}" aria-invalid="{{ $galatK ? 'true' : 'false' }}">
            @break

        @case('time')
            <input id="{{ $id }}" type="time" class="ed-fld" wire:model.live="isi.{{ $k }}" aria-invalid="{{ $galatK ? 'true' : 'false' }}">
            @break

        @case('color')
            <div class="flex items-center gap-2.5">
                <input id="{{ $id }}" type="color" class="size-11 flex-none cursor-pointer rounded-xl border-2 border-[#e2e5e9] bg-white p-1" wire:model.live.debounce.400ms="isi.{{ $k }}" aria-label="Pilih warna">
                <span class="text-[13px] font-semibold text-[#454b55]">{{ $nilai }}</span>
            </div>
            @break

        @case('url')
            <input id="{{ $id }}" type="text" inputmode="url" autocomplete="off" maxlength="500" class="ed-fld" placeholder="https://…" wire:model.live.debounce.700ms="isi.{{ $k }}" aria-invalid="{{ $galatK ? 'true' : 'false' }}">
            @break

        @default
            @if ($area)
                <textarea id="{{ $id }}" rows="{{ max(2, $meta['rows']) }}" maxlength="2000" class="ed-fld" placeholder="{{ $meta['label'] }}" wire:model.live.debounce.700ms="isi.{{ $k }}"></textarea>
            @else
                <input id="{{ $id }}" type="text" maxlength="255" class="ed-fld" placeholder="{{ $meta['label'] }}" wire:model.live.debounce.700ms="isi.{{ $k }}">
            @endif
    @endswitch

    @if ($galatK)
        <div class="galat mt-1" role="alert">{{ $galatK }}</div>
    @elseif ($meta['kind'] === 'url' && $nilai === '' && $meta['hint'] !== '')
        <div class="hint mt-1" style="{{ $meta['opt'] ? '' : 'color:#b25e00' }}">{{ $meta['hint'] }}</div>
    @elseif ($urlSah)
        <a href="{{ $nilai }}" target="_blank" rel="noopener noreferrer nofollow" class="mt-1.5 inline-flex items-center gap-1 text-xs font-bold text-brand"><x-ikon nama="editor-luar" :ukuran="13" />Uji link di tab baru</a>
    @elseif ($meta['hint'] !== '' && $meta['kind'] !== 'url')
        <div class="hint mt-1">{{ $meta['hint'] }}</div>
    @endif
</div>
