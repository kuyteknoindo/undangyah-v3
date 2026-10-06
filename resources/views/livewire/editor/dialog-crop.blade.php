{{-- Dialog crop: kontrol rasio/zoom/geser (Alpine). Hanya parameter yang dikirim ke server; berkas asli tidak diubah. Gaya gambar sama dengan RenderUndangan::gayaGambar. --}}
<x-dialog wire:model="dialogCrop" judul="Crop foto" sub="Geser foto di bingkai atau pakai kontrol di bawah. Berkas aslinya tidak diubah." lebar="max-w-[520px]">
    @if ($dialogCrop && $fotoCrop && $fotoCrop['ada'])
        <div wire:key="crop-{{ $fotoCrop['slot'] }}" class="flex flex-col gap-4"
             x-data="{
                ratio: @js($fotoCrop['crop']['ratio'] ?? 'bingkai'), zoom: @js((int) ($fotoCrop['crop']['zoom'] ?? 100)), x: @js((int) ($fotoCrop['crop']['x'] ?? 50)), y: @js((int) ($fotoCrop['crop']['y'] ?? 50)),
                asal: null, el: null, awal: null,
                gaya() { return 'position:absolute;top:0;left:0;width:100%;height:100%;display:block;object-fit:' + (this.ratio === 'asli' ? 'contain' : 'cover') + ';object-position:' + this.x + '% ' + this.y + '%;transform:scale(' + (this.zoom / 100) + ');transform-origin:' + this.x + '% ' + this.y + '%;'; },
                batas(n) { return Math.max(0, Math.min(100, Math.round(n))); },
                mulai(e) { e.currentTarget.setPointerCapture(e.pointerId); const r = e.currentTarget.getBoundingClientRect(); this.asal = { px: e.clientX, py: e.clientY, x: this.x, y: this.y, w: r.width, h: r.height }; },
                geser(e) { if (!this.asal) return; const k = 100 / (this.zoom / 100); this.x = this.batas(this.asal.x - (e.clientX - this.asal.px) / this.asal.w * k); this.y = this.batas(this.asal.y - (e.clientY - this.asal.py) / this.asal.h * k); },
                akhir() { this.asal = null; },
                ulang() { this.ratio = 'bingkai'; this.zoom = 100; this.x = 50; this.y = 50; },
                terapkan() { this.awal = null; $wire.simpanCrop(this.ratio, this.zoom, this.x, this.y); },
                pasang() { if (this.el) this.el.style.cssText = this.gaya(); },
                init() {
                    this.el = document.querySelector('#ed-kanvas [data-kunci=&quot;foto:{{ $fotoCrop['slot'] }}&quot;] img');
                    this.awal = this.el ? this.el.style.cssText : null;
                    ['ratio', 'zoom', 'x', 'y'].forEach((k) => this.$watch(k, () => this.pasang()));
                },
                destroy() { if (this.el && this.awal !== null) this.el.style.cssText = this.awal; },
             }">
            <div>
                <div class="mb-1.5 flex items-center justify-between gap-2">
                    <div class="ed-label">{{ $fotoCrop['label'] }}</div>
                    <div class="text-xs text-[#666c76]">Seret foto untuk menggeser</div>
                </div>
                <div class="flex min-h-[200px] items-center justify-center rounded-2xl bg-[#e9ecf1] p-4">
                    <div class="relative max-w-full touch-none overflow-hidden bg-[#cbd0d8] select-none" style="{{ $fotoCrop['bentuk'] }}; cursor: grab"
                         x-on:pointerdown="mulai($event)" x-on:pointermove="geser($event)" x-on:pointerup="akhir()" x-on:pointercancel="akhir()">
                        <img src="{{ $fotoCrop['src'] }}" alt="Pratinjau {{ mb_strtolower($fotoCrop['label']) }}" draggable="false" x-bind:style="gaya()">
                    </div>
                </div>
            </div>

            <div>
                <div class="ed-label mb-1.5">Rasio</div>
                <div role="radiogroup" aria-label="Rasio foto" class="grid grid-cols-2 gap-1.5">
                    <button type="button" role="radio" class="ed-radio text-center" x-bind:aria-checked="ratio === 'bingkai'" x-on:click="ratio = 'bingkai'">Isi bingkai</button>
                    <button type="button" role="radio" class="ed-radio text-center" x-bind:aria-checked="ratio === 'asli'" x-on:click="ratio = 'asli'">Foto utuh (asli)</button>
                </div>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label for="cr-zoom" class="mb-1 flex justify-between text-[13px] font-bold"><span>Perbesar</span><span class="font-medium text-[#666c76]" x-text="zoom + '%'"></span></label>
                    <input id="cr-zoom" type="range" min="100" max="300" step="5" x-model.number="zoom" class="w-full accent-[#1d4ed8]">
                </div>
                <div>
                    <label for="cr-x" class="mb-1 flex justify-between text-[13px] font-bold"><span>Geser kiri-kanan</span><span class="font-medium text-[#666c76]" x-text="x + '%'"></span></label>
                    <input id="cr-x" type="range" min="0" max="100" step="1" x-model.number="x" class="w-full accent-[#1d4ed8]">
                </div>
                <div>
                    <label for="cr-y" class="mb-1 flex justify-between text-[13px] font-bold"><span>Geser atas-bawah</span><span class="font-medium text-[#666c76]" x-text="y + '%'"></span></label>
                    <input id="cr-y" type="range" min="0" max="100" step="1" x-model.number="y" class="w-full accent-[#1d4ed8]">
                </div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-[#eef0f3] pt-4">
                <button type="button" class="btn btn-ghost mr-auto" x-on:click="ulang()">Atur ulang</button>
                <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogCrop = false">Batal</button>
                <button type="button" class="btn btn-primary" x-on:click="terapkan()">Terapkan crop</button>
            </div>
        </div>
    @endif
</x-dialog>
