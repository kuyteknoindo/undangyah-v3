{{-- Panel foto bagian aktif: kartu slot foto (Pilih/Ganti, Crop, Hapus) dan, untuk bagian Gallery, pengelola galeri. Data dari trait FotoEditor. --}}
@foreach ($fotoSlot as $f)
    <div class="ed-kartu p-3" wire:key="foto-{{ $f['slot'] }}" data-f="foto:{{ $f['slot'] }}" data-aktif="{{ $terpilih === 'foto:'.$f['slot'] ? '1' : '0' }}">
        <div class="flex items-center gap-3">
            <span class="flex size-16 flex-none items-center justify-center overflow-hidden rounded-xl bg-[#f4f5f7] text-[#9aa0a9]">
                @if ($f['thumb'])<img src="{{ $f['thumb'] }}" alt="" class="size-full object-cover" loading="lazy">@else<x-ikon nama="ef-foto-kosong" :ukuran="26" />@endif
            </span>
            <div class="min-w-0 flex-1">
                <div class="text-[13px] leading-[18px] font-bold">{{ $f['label'] }}</div>
                <div class="truncate text-xs leading-4 text-[#666c76]">{{ $f['ada'] ? $f['nama'] : 'Belum ada foto' }}</div>
                @if ($f['crop'])<div class="mt-0.5 text-[11px] leading-4 font-bold text-brand">Crop aktif</div>@endif
            </div>
        </div>
        <div class="mt-2.5 flex flex-wrap gap-1.5">
            <button type="button" id="f-foto:{{ $f['slot'] }}" class="ed-kecil" wire:click="bukaFoto('{{ $f['slot'] }}')">
                <x-ikon :nama="$f['ada'] ? 'ef-ganti' : 'ef-unggah'" :ukuran="14" />{{ $f['ada'] ? 'Ganti foto' : 'Pilih foto' }}
            </button>
            @if ($f['ada'] && $f['bisaCrop'])
                <button type="button" class="ed-kecil" wire:click="bukaCrop('{{ $f['slot'] }}')"><x-ikon nama="ef-crop" :ukuran="14" />Crop</button>
            @endif
            @if ($f['ada'])
                <button type="button" class="ed-kecil ed-ikon text-[#d83a3a]" wire:click="hapusFoto('{{ $f['slot'] }}')" wire:confirm="Lepas foto dari slot ini? Berkasnya tetap ada di pustakamu." aria-label="Lepas {{ mb_strtolower($f['label']) }}" title="Lepas foto"><x-ikon nama="editor-hapus" :ukuran="14" /></button>
            @endif
        </div>
        @if ($f['slot'] === 'share')
            <div class="hint mt-2">Muncul di pratinjau saat link dibagikan (WhatsApp, dll). Bila kosong, foto sampul dipakai.</div>
        @endif
    </div>
@endforeach

@if ($fotoGaleri)
    @php
        $gl = $fotoGaleri;
    @endphp
    <div class="ed-kartu flex flex-col gap-3 p-3" wire:key="galeri-atur">
        <div class="flex items-center gap-2">
            <div class="flex-1 text-[13px] leading-[18px] font-bold">Foto galeri</div>
            <span class="rounded-lg bg-[#eef3fe] px-2 py-0.5 text-[11px] leading-4 font-bold text-brand" role="status">{{ $gl['terisi'] }} / {{ $gl['batas'] }} foto</span>
        </div>
        <div>
            <div class="ed-label mb-1.5">Tata letak</div>
            <div role="radiogroup" aria-label="Tata letak galeri" class="grid grid-cols-3 gap-1.5">
                @foreach (\App\Livewire\EditorUndangan::LAYOUT_GALERI as $kode => $nama)
                    <button type="button" role="radio" aria-checked="{{ $gl['layout'] === $kode ? 'true' : 'false' }}" class="ed-radio text-center" wire:click="aturLayoutGaleri('{{ $kode }}')">{{ $nama }}</button>
                @endforeach
            </div>
        </div>
        <button type="button" class="ed-kecil w-full justify-center" wire:click="bukaGaleri" @disabled($gl['terisi'] >= $gl['batas'])><x-ikon nama="tambah" :ukuran="14" />Tambah foto dari pustaka</button>
        @if ($gl['terisi'] >= $gl['batas'])
            <div class="hint">Batas foto galeri paketmu sudah tercapai. Lepas foto lain atau tingkatkan paket untuk menambah.</div>
        @endif
        <div>
            <label for="gl-video" class="mb-1.5 block text-[13px] leading-[18px] font-bold">Link video <span class="ml-1 rounded-md bg-[#f2f4f7] px-1.5 text-[10px] leading-4 font-bold text-[#666c76]">Opsional</span></label>
            <input id="gl-video" type="text" inputmode="url" autocomplete="off" maxlength="500" class="ed-fld" placeholder="https://youtube.com/…" wire:model.live.debounce.700ms="videoGaleri" aria-invalid="{{ $galatVideo !== '' ? 'true' : 'false' }}">
            @if ($galatVideo !== '')<div class="galat mt-1" role="alert">{{ $galatVideo }}</div>@endif
        </div>
    </div>

    @if ($gl['item'] === [])
        <div class="rounded-2xl bg-[#f4f5f7] p-4 text-center text-[13px] text-[#666c76]">Galeri kosong. Tambahkan foto dari pustaka.</div>
    @endif
    @foreach ($gl['item'] as $f)
        <div class="ed-kartu p-2.5" wire:key="galeri-{{ $f['id'] }}" data-f="foto:{{ $f['slot'] }}" data-aktif="{{ $terpilih === 'foto:'.$f['slot'] ? '1' : '0' }}">
            <div class="flex items-center gap-2.5">
                <span class="flex size-14 flex-none items-center justify-center overflow-hidden rounded-lg bg-[#f4f5f7] text-[#9aa0a9]">
                    @if ($f['thumb'])<img src="{{ $f['thumb'] }}" alt="" class="size-full object-cover" loading="lazy">@else<x-ikon nama="ef-foto-kosong" :ukuran="22" />@endif
                </span>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] leading-[18px] font-bold">Foto {{ $f['no'] }}</div>
                    <div class="truncate text-xs leading-4 text-[#666c76]">{{ $f['ada'] ? $f['nama'] : 'Slot kosong' }}@if ($f['crop']) · crop aktif @endif</div>
                </div>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-1">
                <button type="button" id="f-foto:{{ $f['slot'] }}" class="ed-kecil !px-2.5" wire:click="bukaFoto('{{ $f['slot'] }}')"><x-ikon :nama="$f['ada'] ? 'ef-ganti' : 'ef-unggah'" :ukuran="14" />{{ $f['ada'] ? 'Ganti' : 'Isi foto' }}</button>
                @if ($f['ada'])<button type="button" class="ed-kecil !px-2.5" wire:click="bukaCrop('{{ $f['slot'] }}')"><x-ikon nama="ef-crop" :ukuran="14" />Crop</button>@endif
                <span class="flex-1"></span>
                <button type="button" class="ed-kecil ed-ikon" wire:click="geserFotoGaleri({{ $f['id'] }}, -1)" @disabled($f['pertama']) aria-label="Naikkan foto {{ $f['no'] }}"><x-ikon nama="editor-naik" :ukuran="15" /></button>
                <button type="button" class="ed-kecil ed-ikon" wire:click="geserFotoGaleri({{ $f['id'] }}, 1)" @disabled($f['terakhir']) aria-label="Turunkan foto {{ $f['no'] }}"><x-ikon nama="editor-turun" :ukuran="15" /></button>
                <button type="button" class="ed-kecil ed-ikon text-[#d83a3a]" wire:click="hapusSlotGaleri({{ $f['id'] }})" wire:confirm="Hapus foto {{ $f['no'] }} dari galeri? Berkasnya tetap ada di pustakamu." aria-label="Hapus foto {{ $f['no'] }} dari galeri"><x-ikon nama="editor-hapus" :ukuran="15" /></button>
            </div>
        </div>
    @endforeach
@endif
