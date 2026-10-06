@php
    $terbit = $u->sudahTerbit();
    $berubah = $terbit && $u->ada_perubahan;
    $tombolTerbit = $terbit ? ($berubah ? 'Terbitkan ulang' : 'Link & QR') : 'Terbitkan';
    $nomor = ['konten' => count($konten), 'fitur' => count($fitur)];
@endphp
<div class="ed-root"
     x-data="{
        kiri: window.matchMedia('(min-width: 1024px)').matches, kanan: window.matchMedia('(min-width: 1024px)').matches,
        tab: 'bagian', zoom: 100, sel: @js($terpilih), tier: 'Semua', lipat: {}, salin: false, seret: null, atas: null,
        fokus() { const mode = this.kiri || this.kanan; this.kiri = !mode; this.kanan = !mode; },
        zoomKe(n) { this.zoom = Math.max(50, Math.min(150, n)); },
        bukaKanan() { if (window.innerWidth < 1024) { this.kanan = true; this.kiri = false; } },
        salinTaut() { navigator.clipboard?.writeText(@js($url)).catch(() => {}); this.salin = true; setTimeout(() => this.salin = false, 1800); },
     }"
     x-on:bidang-dipilih.window="sel = $event.detail.kunci; bukaKanan(); setTimeout(() => document.getElementById('f-' + sel)?.focus(), 120)"
     x-on:bagian-dipilih.window="bukaKanan()"
     x-on:keydown.escape.window="if (window.innerWidth < 1024) { kiri = false; kanan = false }">

    <style>
        .ed-root { display: flex; flex-direction: column; height: 100vh; height: 100dvh; background: #f4f5f7; color: #262a31; }
        .ed-root button, .ed-root a { transition: transform 120ms, box-shadow 120ms, background-color 120ms, opacity 120ms; }
        .ed-root button:disabled { opacity: .4; cursor: not-allowed; }
        .ed-root :focus-visible { outline: 3px solid #bfd3ff; outline-offset: 2px; }
        .ed-head { flex: none; display: flex; flex-wrap: wrap; align-items: center; gap: 10px 14px; min-height: 64px; padding: 10px 20px; background: #fff; border-bottom: 2px solid #e2e5e9; }
        .ed-ikbtn { flex: none; display: inline-flex; width: 44px; height: 44px; align-items: center; justify-content: center; border-radius: 22px; border: 2px solid #e2e5e9; background: #fff; color: #262a31; }
        .ed-ikbtn[aria-pressed='true'] { background: #e3ecfd; border-color: #1d4ed8; color: #1d4ed8; }
        .ed-pil { display: inline-flex; min-height: 44px; align-items: center; gap: 8px; padding: 0 16px; border-radius: 16px; border: 2px solid #e2e5e9; background: #fff; font-size: 13px; line-height: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #262a31; white-space: nowrap; }
        .ed-pil[aria-pressed='true'] { background: #1d4ed8; border-color: #1d4ed8; color: #fff; box-shadow: 0 4px 0 #1a3fa8; }
        .ed-primer { display: inline-flex; min-height: 44px; align-items: center; justify-content: center; gap: 8px; padding: 0 20px; border-radius: 16px; background: #1d4ed8; color: #fff; box-shadow: 0 4px 0 #1a3fa8; font-size: 13px; line-height: 18px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; white-space: nowrap; }
        .ed-primer:active:not(:disabled) { transform: translateY(4px); box-shadow: none; }
        .ed-lencana { flex: none; border-radius: 10px; padding: 4px 10px; font-size: 12px; line-height: 16px; font-weight: 700; }
        .ed-body { flex: 1; min-height: 0; display: flex; position: relative; }
        .ed-pane { display: flex; flex-direction: column; min-height: 0; background: #fff; box-sizing: border-box; flex: none; }
        .ed-kiri { width: 300px; border-right: 2px solid #e2e5e9; }
        .ed-kanan { width: 340px; border-left: 2px solid #e2e5e9; }
        @media (max-width: 1279px) { .ed-kiri { width: 264px; } .ed-kanan { width: 300px; } }
        .ed-off { display: none; }
        @media (max-width: 1023px) {
            .ed-pane { display: none; }
            .ed-pane.ed-on { display: flex; position: absolute; top: 0; bottom: 0; z-index: 30; width: min(340px, 92vw); box-shadow: 0 24px 60px rgba(19, 31, 36, .35); }
            .ed-kiri.ed-on { left: 0; } .ed-kanan.ed-on { right: 0; }
            .ed-head { padding: 8px 12px; }
        }
        .ed-scroll { flex: 1; min-height: 0; overflow-y: auto; padding: 14px 14px 24px; scrollbar-width: thin; }
        .ed-tab { flex: 1; min-height: 44px; border-radius: 14px; font-size: 13px; line-height: 18px; font-weight: 700; color: #666c76; }
        .ed-tab[aria-selected='true'] { background: #e3ecfd; color: #1d4ed8; }
        .ed-label { font-size: 11px; line-height: 14px; font-weight: 700; letter-spacing: .08em; color: #666c76; text-transform: uppercase; }
        .ed-baris { display: flex; align-items: stretch; border-radius: 14px; }
        .ed-baris:hover { background: #f4f5f7; }
        .ed-baris[data-aktif='1'] { background: #eef3fe; }
        .ed-baris.ed-atas { box-shadow: inset 0 3px 0 #1d4ed8; }
        .ed-grip { flex: none; display: flex; width: 28px; align-items: center; justify-content: center; color: #9aa0a9; cursor: grab; border-radius: 10px; }
        .ed-fld { width: 100%; box-sizing: border-box; min-height: 44px; border-radius: 14px; border: 2px solid #e2e5e9; background: #f4f5f7; padding: 0 12px; font: inherit; font-size: 14px; line-height: 19px; font-weight: 500; color: #262a31; transition: border-color 120ms, background-color 120ms; }
        .ed-fld:focus { border-color: #1d4ed8; background: #fff; outline: none; }
        textarea.ed-fld { padding: 10px 12px; resize: vertical; min-height: 84px; }
        .ed-fld[aria-invalid='true'] { border-color: #ea2b2b; }
        .ed-kartu { border: 2px solid #e2e5e9; border-radius: 16px; background: #fff; }
        .ed-kartu[data-aktif='1'] { border-color: #1d4ed8; }
        .ed-kecil { display: inline-flex; min-height: 34px; align-items: center; gap: 6px; padding: 0 12px; border-radius: 12px; border: 2px solid #e2e5e9; background: #fff; font-size: 12px; line-height: 16px; font-weight: 700; color: #262a31; }
        .ed-kecil.ed-ikon { width: 34px; padding: 0; justify-content: center; }
        .ed-kecil:hover:not(:disabled) { background: #f4f5f7; }
        .ed-radio { min-height: 38px; padding: 0 12px; border-radius: 12px; border: 2px solid #e2e5e9; background: #fff; font-size: 13px; line-height: 17px; font-weight: 600; text-align: left; }
        .ed-radio[aria-checked='true'] { background: #e3ecfd; border-color: #1d4ed8; color: #1a3fa8; }
        .ed-sw { position: relative; flex: none; width: 44px; height: 26px; border-radius: 13px; background: #aeb3bb; transition: background-color 150ms; }
        .ed-sw::after { content: ''; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 10px; background: #fff; transition: transform 150ms; }
        [aria-checked='true'] > .ed-sw { background: #1d4ed8; } [aria-checked='true'] > .ed-sw::after { transform: translateX(18px); }
        .ed-canvas { flex: 1 1 auto; min-width: 0; overflow: auto; display: flex; flex-direction: column; align-items: center; padding: 20px 16px 96px; background-color: #e9ecf1; background-image: radial-gradient(#cbd0d8 1.2px, transparent 1.2px); background-size: 22px 22px; }
        .ed-bingkai { width: 100%; max-width: 500px; flex: none; border-radius: 4px; box-shadow: 0 10px 40px rgba(16, 32, 58, .18); background: #fff; overflow: hidden; }
        .ed-mode { flex: none; display: flex; gap: 4px; margin-bottom: 12px; padding: 4px; border-radius: 999px; border: 2px solid #e2e5e9; background: #fff; }
        .ed-mode button { min-height: 36px; padding: 0 16px; border-radius: 999px; font-size: 12px; line-height: 16px; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; }
        .ed-mode button[aria-pressed='true'] { background: #1d4ed8; color: #fff; }
        .ed-zoom { position: absolute; left: 50%; bottom: 16px; z-index: 5; display: flex; align-items: center; gap: 2px; padding: 4px; border-radius: 999px; border: 2px solid #e2e5e9; background: #fff; transform: translateX(-50%); }
        .ed-zoom button { display: inline-flex; min-width: 36px; height: 36px; align-items: center; justify-content: center; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .ed-zoom button:hover { background: #f4f5f7; }
        .ed-scrim { position: absolute; inset: 0; z-index: 20; background: rgba(19, 31, 36, .4); }
        @media (min-width: 1024px) { .ed-scrim { display: none; } }
    </style>

    {{-- Header --}}
    <header class="ed-head">
        <a href="{{ route('beranda') }}" wire:navigate aria-label="Kembali ke Beranda" class="ed-ikbtn"><x-ikon nama="editor-kembali" :ukuran="20" /></a>
        <div class="flex min-w-0 items-center gap-2.5">
            <div class="min-w-0">
                <h1 class="m-0 truncate text-base leading-[21px] font-bold tracking-tight">Edit Undangan · {{ $u->judul }}</h1>
                <div class="flex items-center gap-1.5 text-xs leading-4 font-medium text-[#666c76]">
                    <x-ikon nama="centang" :ukuran="14" />
                    <span role="status">
                        <span wire:loading.delay.shortest>Menyimpan…</span>
                        <span wire:loading.delay.shortest.remove>Tersimpan otomatis{{ $tersimpanJam ? ' · '.$tersimpanJam : '' }}</span>
                    </span>
                </div>
            </div>
            @if ($terbit)
                @if ($berubah)
                    <span class="ed-lencana" style="background:#fff4cc;color:#b25e00" title="Tamu masih melihat versi yang terakhir diterbitkan">Aktif · ada perubahan belum diterbitkan</span>
                @else
                    <span class="ed-lencana" style="background:#d7ffb8;color:#3c7a00">Aktif</span>
                @endif
            @else
                <span class="ed-lencana" style="background:#fff4cc;color:#b25e00">Draf</span>
            @endif
        </div>
        <div class="flex-1 basis-5"></div>
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="ed-ikbtn" x-on:click="kiri = !kiri; if (kiri && innerWidth < 1024) kanan = false" x-bind:aria-pressed="kiri" x-bind:aria-label="kiri ? 'Sembunyikan panel kiri' : 'Tampilkan panel kiri'"><x-ikon nama="editor-panel-kiri" :ukuran="20" /></button>
            <button type="button" class="ed-ikbtn" x-on:click="kanan = !kanan; if (kanan && innerWidth < 1024) kiri = false" x-bind:aria-pressed="kanan" x-bind:aria-label="kanan ? 'Sembunyikan panel kanan' : 'Tampilkan panel kanan'"><x-ikon nama="editor-panel-kanan" :ukuran="20" /></button>
            <button type="button" class="ed-pil max-lg:hidden" x-on:click="fokus()" x-bind:aria-pressed="!kiri && !kanan"><x-ikon nama="editor-fokus" :ukuran="16" /><span x-text="(!kiri && !kanan) ? 'Keluar fokus' : 'Fokus kanvas'"></span></button>
            @if ($rutaPratinjau)
                <a href="{{ $rutaPratinjau }}" target="_blank" rel="noopener" class="ed-pil" title="Lihat seperti tamu (pratinjau penuh di tab baru)"><x-ikon nama="mata" :ukuran="16" /><span class="max-sm:hidden">Lihat</span></a>
            @else
                <button type="button" class="ed-pil" wire:click="setMode('tamu')" title="Lihat seperti tamu (pratinjau)"><x-ikon nama="mata" :ukuran="16" /><span class="max-sm:hidden">Lihat</span></button>
            @endif
            <button type="button" class="ed-pil" x-on:click="salinTaut()"><x-ikon nama="editor-salin" :ukuran="16" /><span class="max-md:hidden" x-text="salin ? 'Tersalin' : 'Salin link'"></span></button>
            @if ($rutaBagikan)
                <a href="{{ $rutaBagikan }}" wire:navigate class="ed-pil max-md:hidden" title="Bagikan undangan ke tamu"><x-ikon nama="bagikan" :ukuran="16" />Bagikan</a>
            @endif
            <button type="button" class="ed-primer" wire:click="bukaTerbit">{{ $tombolTerbit }}</button>
        </div>
    </header>

    <div class="ed-body">
        <div class="ed-scrim" x-show="kiri || kanan" x-cloak x-on:click="if (innerWidth < 1024) { kiri = false; kanan = false }"></div>

        {{-- Panel kiri: Bagian / Tema / Pengaturan --}}
        <aside class="ed-pane ed-kiri" x-bind:class="kiri ? 'ed-on' : 'ed-off'" aria-label="Panel konten">
            <div role="tablist" aria-label="Panel konten" class="flex flex-none items-center gap-1 border-b-2 border-[#e2e5e9] p-2">
                @foreach (['bagian' => 'Bagian', 'tema' => 'Tema', 'setting' => 'Pengaturan'] as $kode => $nama)
                    <button type="button" role="tab" id="tab-{{ $kode }}" class="ed-tab" x-bind:aria-selected="tab === '{{ $kode }}'" aria-controls="panel-{{ $kode }}" x-on:click="tab = '{{ $kode }}'">{{ $nama }}</button>
                @endforeach
                <button type="button" class="ed-ikbtn !size-9 border-0 max-lg:hidden" x-on:click="kiri = false" aria-label="Sembunyikan panel kiri" title="Sembunyikan panel kiri"><x-ikon nama="editor-kembali" :ukuran="18" /></button>
            </div>
            <div class="ed-scroll">
                <div id="panel-bagian" role="tabpanel" aria-labelledby="tab-bagian" x-show="tab === 'bagian'" class="flex flex-col gap-4">
                    @include('livewire.editor.kiri-bagian')
                </div>
                <div id="panel-tema" role="tabpanel" aria-labelledby="tab-tema" x-show="tab === 'tema'" x-cloak class="flex flex-col gap-4">
                    @include('livewire.editor.kiri-tema')
                </div>
                <div id="panel-setting" role="tabpanel" aria-labelledby="tab-setting" x-show="tab === 'setting'" x-cloak class="flex flex-col gap-4">
                    @include('livewire.editor.kiri-pengaturan')
                </div>
            </div>
        </aside>

        {{-- Kanvas --}}
        <main class="ed-canvas" aria-label="Kanvas undangan">
            <div role="group" aria-label="Mode tampilan" class="ed-mode">
                <button type="button" wire:click="setMode('edit')" aria-pressed="{{ $mode === 'edit' ? 'true' : 'false' }}">Kanvas edit</button>
                <button type="button" wire:click="setMode('tamu')" aria-pressed="{{ $mode === 'tamu' ? 'true' : 'false' }}">Tampilan tamu</button>
            </div>
            <div class="mb-3 flex max-w-[500px] items-center gap-2 text-center text-xs leading-4 font-medium text-[#666c76]">
                @if ($mode === 'edit')
                    <x-ikon nama="edit" :ukuran="14" /><span>Klik teks di kanvas untuk mengedit isiannya di panel kanan</span>
                @else
                    <x-ikon nama="mata" :ukuran="14" /><span>Ini tampilan untuk tamu, memakai tampilan yang sama dengan link terbitan.</span>
                @endif
            </div>

            <style x-text="sel ? '#ed-kanvas [data-kunci=&quot;' + sel + '&quot;]{outline:2px solid #1d4ed8;outline-offset:1px;background-color:rgba(29,78,216,.06)}' : ''"></style>

            <div id="ed-kanvas" class="ed-bingkai" x-bind:style="'zoom:' + (zoom / 100)"
                 x-on:click="
                    if ($event.target.closest('a')) $event.preventDefault();
                    const el = $event.target.closest('[data-kunci]');
                    if (el && $wire.mode === 'edit') $wire.pilihBidang(el.dataset.kunci);
                    else { const b = $event.target.closest('[data-bagian]'); if (b && $wire.mode === 'edit') $wire.pilihBagian(b.dataset.bagian); }">
                @if ($kanvas === null)
                    @include('livewire.editor.kerangka')
                @else
                    {!! $kanvas !!}
                @endif
            </div>

            <div class="ed-zoom" role="group" aria-label="Zoom kanvas">
                <button type="button" x-on:click="zoomKe(zoom - 10)" aria-label="Perkecil"><x-ikon nama="editor-zoom-keluar" :ukuran="18" /></button>
                <button type="button" x-on:click="zoomKe(100)" aria-label="Kembalikan zoom ke 100 persen" x-text="zoom + '%'"></button>
                <button type="button" x-on:click="zoomKe(zoom + 10)" aria-label="Perbesar"><x-ikon nama="editor-zoom-masuk" :ukuran="18" /></button>
            </div>
        </main>

        {{-- Panel kanan: Isi --}}
        <aside class="ed-pane ed-kanan" x-bind:class="kanan ? 'ed-on' : 'ed-off'" aria-label="Isi bagian" x-on:focusin="const f = $event.target.closest('[data-f]'); if (f) sel = f.dataset.f">
            @include('livewire.editor.kanan-isi')
        </aside>
    </div>

    @include('livewire.editor.dialog-terbit')
    @include('livewire.editor.dialog-bgf')
    @include('livewire.editor.dialog-crop')
    @include('livewire.editor.dialog-foto')
</div>
