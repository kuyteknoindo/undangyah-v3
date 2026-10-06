{{--
    Kode QR (SVG dari server) + unduh SVG / PNG. PNG digambar lewat canvas di klien.
    <x-bagikan.qr :svg="$svg" berkas="qr-undangan" />
--}}
@props(['svg', 'berkas' => 'qr-undangan', 'ukuran' => 'size-[200px]'])
<div x-data="{
        galat: '',
        uriSvg() { return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(this.$refs.kotak.innerHTML.trim()); },
        unduhPng() {
            this.galat = '';
            const gagal = () => { this.galat = 'Unduhan PNG gagal di perangkat ini. Coba unduh SVG.'; };
            try {
                const img = new Image();
                img.onload = () => {
                    try {
                        const cv = document.createElement('canvas'); cv.width = 1024; cv.height = 1024;
                        const cx = cv.getContext('2d'); cx.fillStyle = '#FFFFFF'; cx.fillRect(0, 0, 1024, 1024); cx.imageSmoothingEnabled = false; cx.drawImage(img, 0, 0, 1024, 1024);
                        cv.toBlob((b) => {
                            if (!b) { gagal(); return; }
                            const a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = @js($berkas) + '.png';
                            document.body.appendChild(a); a.click(); a.remove(); setTimeout(() => URL.revokeObjectURL(a.href), 4000);
                        }, 'image/png');
                    } catch (e) { gagal(); }
                };
                img.onerror = gagal; img.src = this.uriSvg();
            } catch (e) { gagal(); }
        },
     }" {{ $attributes->class(['flex flex-col items-center gap-3']) }}>
    <div x-ref="kotak" role="img" aria-label="Kode QR" class="{{ $ukuran }} overflow-hidden rounded-xl border border-line bg-white p-2 [&>svg]:size-full">{!! $svg !!}</div>
    <div class="flex flex-wrap justify-center gap-2">
        <a x-bind:href="uriSvg()" download="{{ $berkas }}.svg" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-unduh" :ukuran="14" /> SVG</a>
        <button type="button" x-on:click="unduhPng()" class="btn btn-secondary btn-sm"><x-ikon nama="bagikan-unduh" :ukuran="14" /> PNG</button>
    </div>
    <div x-show="galat" x-cloak role="alert" class="galat" x-text="galat"></div>
</div>
