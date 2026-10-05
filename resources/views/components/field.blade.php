@props(['id', 'label', 'ikon' => null, 'type' => 'text', 'sandi' => false, 'galat' => null, 'awalan' => null])
<div class="flex flex-col gap-1.5" @if ($sandi) x-data="{ lihat: false }" @endif>
    <label for="{{ $id }}" class="label">{{ $label }}</label>
    <div class="field" @if ($galat) data-invalid @endif>
        @if ($ikon)
            <span class="text-muted"><x-ikon :nama="$ikon" :ukuran="18" /></span>
        @endif
        @if ($awalan)
            <span class="text-sm font-medium text-muted">{{ $awalan }}</span>
        @endif
        <input id="{{ $id }}" type="{{ $sandi ? 'password' : $type }}"
               @if ($sandi) x-bind:type="lihat ? 'text' : 'password'" @endif
               @if ($galat) aria-invalid="true" aria-describedby="{{ $id }}-galat" @endif
               {{ $attributes->class(['field-input']) }}>
        @if ($sandi)
            <button type="button" x-on:click="lihat = !lihat" x-bind:aria-label="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" x-bind:aria-pressed="lihat"
                    class="flex size-10 shrink-0 items-center justify-center rounded-lg text-muted hover:text-ink">
                <span x-show="!lihat"><x-ikon nama="mata" :ukuran="20" /></span>
                <span x-show="lihat" x-cloak><x-ikon nama="mata-tutup" :ukuran="20" /></span>
            </button>
        @endif
    </div>
    @if ($galat)
        <div id="{{ $id }}-galat" role="alert" class="flex items-center gap-1.5 text-xs leading-4 font-medium text-danger">
            <x-ikon nama="peringatan" :ukuran="14" /> {{ $galat }}
        </div>
    @endif
</div>
