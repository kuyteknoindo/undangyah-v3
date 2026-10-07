{{--
    Kolom isian halaman auth (papan Login/Register/LupaSandi). Dengan `ikon` atau `sandi` = kotak .au-fld;
    tanpa keduanya = input polos setinggi 44 px (nama/email di papan Daftar).
--}}
@props(['id', 'label', 'ikon' => null, 'type' => 'text', 'sandi' => false, 'galat' => null])
<div class="au-baris" @if ($sandi) x-data="{ lihat: false }" @endif>
    <label for="{{ $id }}" class="au-lbl">{{ $label }}</label>
    @if ($ikon || $sandi)
        <div class="au-fld" @if ($galat) data-invalid @endif>
            @if ($ikon)
                <span class="au-ikon" aria-hidden="true"><x-ikon :nama="$ikon" :ukuran="17" /></span>
            @endif
            <input id="{{ $id }}" type="{{ $sandi ? 'password' : $type }}"
                   @if ($sandi) x-bind:type="lihat ? 'text' : 'password'" @endif
                   aria-invalid="{{ $galat ? 'true' : 'false' }}" @if ($galat) aria-describedby="{{ $id }}-galat" @endif
                   {{ $attributes->class(['au-input']) }}>
            @if ($sandi)
                <button type="button" x-on:click="lihat = !lihat" x-bind:aria-label="lihat ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'" x-bind:aria-pressed="lihat ? 'true' : 'false'" aria-label="Tampilkan kata sandi" aria-pressed="false" class="au-mata">
                    <span x-show="!lihat" class="flex"><x-ikon nama="mata" :ukuran="18" /></span>
                    <span x-show="lihat" x-cloak class="flex"><x-ikon nama="mata-tutup" :ukuran="18" /></span>
                </button>
            @endif
        </div>
    @else
        <input id="{{ $id }}" type="{{ $type }}" aria-invalid="{{ $galat ? 'true' : 'false' }}" @if ($galat) aria-describedby="{{ $id }}-galat" @endif {{ $attributes->class(['au-polos']) }}>
    @endif
    @if ($galat)
        <x-au.galat :id="$id.'-galat'">{{ $galat }}</x-au.galat>
    @endif
</div>
