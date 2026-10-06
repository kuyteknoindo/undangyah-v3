{{-- Kartu Background Fixed di panel kiri (tab Bagian); hanya bila tema mendukung atau sudah aktif. --}}
@if ($fotoBgf)
    <div class="ed-kartu flex flex-col gap-2.5 p-3" wire:key="kartu-bgf">
        <div class="flex items-center gap-2">
            <div class="min-w-0 flex-1">
                <div class="text-sm leading-5 font-bold">Background Fixed</div>
                <div class="text-xs leading-4 text-[#666c76]">{{ $fotoBgf['on'] ? 'Aktif · gambar' : ($fotoBgf['ada'] ? 'Mati · gambar sudah dipilih' : 'Belum diatur') }}</div>
            </div>
            <button type="button" class="ed-kecil" wire:click="bukaBgf">Atur</button>
        </div>
        @if ($fotoBgf['thumb'])
            <button type="button" wire:click="bukaBgf" class="relative block h-16 w-full overflow-hidden rounded-xl bg-[#f4f5f7]" aria-label="Buka pengaturan Background Fixed">
                <img src="{{ $fotoBgf['thumb'] }}" alt="" class="size-full object-cover" loading="lazy">
            </button>
        @endif
        <div class="text-xs leading-[17px] text-[#666c76]">Gambar yang tetap diam di belakang seluruh bagian saat tamu menggulir undangan.</div>
    </div>
@endif
