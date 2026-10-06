{{-- Dialog Background Fixed (gambar saja): aktif, gambar, warna + opasitas lapisan, opasitas gambar, blur. Nilai divalidasi ulang di server. --}}
<x-dialog wire:model="dialogBgf" judul="Background Fixed" sub="Gambar yang tetap diam di belakang seluruh bagian saat tamu menggulir undangan." lebar="max-w-[520px]">
    @if ($dialogBgf && $fotoBgf)
        <div wire:key="bgf-{{ $fotoBgf['src'] }}" class="flex flex-col gap-4"
             x-data="{ on: @js($fotoBgf['on']), oc: @js($fotoBgf['oc']), oo: @js($fotoBgf['oo']), op: @js($fotoBgf['op']), blur: @js($fotoBgf['blur']) }">
            <button type="button" role="switch" x-bind:aria-checked="on" x-on:click="on = !on" class="flex min-h-11 w-full items-center gap-3 rounded-[14px] border-2 border-[#e2e5e9] px-3 text-left">
                <span class="flex-1 text-sm font-semibold" x-text="on ? 'Aktif' : 'Mati'"></span><span class="ed-sw"></span>
            </button>

            <div>
                <div class="relative h-40 w-full overflow-hidden rounded-2xl bg-[#e9ecf1]">
                    @if ($fotoBgf['src'])
                        <img src="{{ $fotoBgf['src'] }}" alt="Pratinjau Background Fixed" class="absolute inset-0 size-full object-cover" x-bind:style="'opacity:' + (op / 100) + ';filter:blur(' + blur + 'px)'">
                        <div class="absolute inset-0" x-bind:style="'background:' + oc + ';opacity:' + (oo / 100)"></div>
                    @else
                        <div class="flex size-full flex-col items-center justify-center gap-1 text-[13px] text-[#666c76]"><x-ikon nama="ef-latar" :ukuran="26" />Belum ada gambar</div>
                    @endif
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <button type="button" class="ed-kecil" wire:click="bukaFotoBgf"><x-ikon :nama="$fotoBgf['ada'] ? 'ef-ganti' : 'ef-unggah'" :ukuran="14" />{{ $fotoBgf['ada'] ? 'Ganti gambar' : 'Pilih gambar' }}</button>
                    @if ($fotoBgf['nama'])<span class="min-w-0 flex-1 truncate text-xs text-[#666c76]">{{ $fotoBgf['nama'] }}</span>@endif
                </div>
                @unless ($fotoBgf['tersedia'])<div class="hint mt-2" style="color:#b25e00">Tema yang dipakai sekarang tidak memakai Background Fixed. Kamu hanya bisa mematikannya.</div>@endunless
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label for="bg-op" class="mb-1 flex justify-between text-[13px] font-bold"><span>Opasitas gambar</span><span class="font-medium text-[#666c76]" x-text="op + '%'"></span></label>
                    <input id="bg-op" type="range" min="20" max="100" step="5" x-model.number="op" class="w-full accent-[#1d4ed8]">
                </div>
                <div>
                    <label for="bg-blur" class="mb-1 flex justify-between text-[13px] font-bold"><span>Blur</span><span class="font-medium text-[#666c76]" x-text="blur + ' px'"></span></label>
                    <input id="bg-blur" type="range" min="0" max="20" step="1" x-model.number="blur" class="w-full accent-[#1d4ed8]">
                </div>
                <div>
                    <label for="bg-oc" class="mb-1 block text-[13px] font-bold">Warna lapisan</label>
                    <div class="flex items-center gap-2.5">
                        <input id="bg-oc" type="color" x-model="oc" class="size-11 flex-none cursor-pointer rounded-xl border-2 border-[#e2e5e9] bg-white p-1">
                        <span class="text-[13px] font-semibold text-[#454b55]" x-text="oc.toUpperCase()"></span>
                    </div>
                </div>
                <div>
                    <label for="bg-oo" class="mb-1 flex justify-between text-[13px] font-bold"><span>Opasitas lapisan</span><span class="font-medium text-[#666c76]" x-text="oo + '%'"></span></label>
                    <input id="bg-oo" type="range" min="0" max="90" step="5" x-model.number="oo" class="w-full accent-[#1d4ed8]">
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-[#eef0f3] pt-4">
                @if ($fotoBgf['ada'])<button type="button" class="btn btn-danger mr-auto" wire:click="hapusBgf" wire:confirm="Hapus pengaturan Background Fixed?">Hapus</button>@endif
                <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogBgf = false">Batal</button>
                <button type="button" class="btn btn-primary" x-on:click="$wire.simpanBgf(on, oc, oo, op, blur)">Simpan</button>
            </div>
        </div>
    @endif
</x-dialog>
