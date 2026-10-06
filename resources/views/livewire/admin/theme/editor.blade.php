@php
    $fontNama = \App\Services\DokumenTema::FONT;
    $labelWarna = \App\Services\DokumenTema::WARNA;
    $palet = \App\Services\DokumenTema::PALET;
    $publik = $status === 'publish';
    $galatAda = \App\Services\DokumenTema::adaGalat($periksa);
@endphp
<div class="flex flex-col gap-4"
     x-data
     x-on:beforeunload.window="if ($wire.kotor) { $event.preventDefault(); $event.returnValue = ''; }">
    <div class="kartu flex flex-wrap items-center gap-x-3 gap-y-2 p-3">
        <button type="button" class="btn btn-secondary btn-sm" x-on:click="if (!$wire.kotor || confirm('Perubahan belum disimpan. Tinggalkan editor?')) Livewire.navigate('{{ route('admin.theme') }}')">
            <x-ikon nama="editor-kembali" :ukuran="14" /> Daftar
        </button>
        <div class="min-w-0 flex-1 basis-56">
            <label for="tn" class="sr-only">Nama tema</label>
            <input id="tn" type="text" class="inp !font-semibold" maxlength="100" placeholder="Nama tema" wire:model.live.debounce.400ms="f.nama" @disabled($arsip) @error('f.nama') aria-invalid="true" @enderror>
        </div>
        <span class="rounded-md px-1.5 text-[11px] leading-4 font-semibold" style="{{ \App\Support\PratinjauTema::TIER[$f['tier']] ?? '' }}">{{ $f['tier'] }}</span>
        @if ($arsip)
            <x-pill warna="abu">Arsip</x-pill>
        @elseif ($temaId)
            <x-pill :warna="$publik ? 'hijau' : 'kuning'">{{ $publik ? 'Publik' : 'Draf' }}</x-pill>
        @else
            <x-pill warna="kuning">Belum disimpan</x-pill>
        @endif
        <span role="status" class="text-xs text-muted" x-text="$wire.kotor ? 'Perubahan belum disimpan' : '{{ $temaId ? 'Tersimpan · versi '.$versiDasar : '' }}'"></span>
        <div class="ml-auto flex flex-wrap items-center gap-2">
            @if ($temaId)
                <button type="button" class="btn btn-secondary btn-sm" wire:click="bukaRiwayat"><x-ikon nama="bagikan-riwayat" :ukuran="14" /> Riwayat</button>
            @endif
            <button type="button" class="btn btn-secondary btn-sm" wire:click="simpan('draft')" wire:loading.attr="disabled" wire:target="simpan" @disabled($arsip)><x-ikon nama="bagikan-simpan" :ukuran="14" /> Simpan draf</button>
            <button type="button" class="btn btn-primary btn-sm" wire:click="simpan('publish')" wire:loading.attr="disabled" wire:target="simpan" @disabled($arsip || $galatAda)><x-ikon nama="template-globe" :ukuran="14" /> Publikasikan</button>
        </div>
    </div>

    @if ($arsip)
        <div class="rounded-xl bg-[#F2F4F7] px-4 py-3 text-[13px] leading-5 text-gray-700">Tema ini diarsipkan dan hanya bisa dilihat. Pulihkan dari daftar tema (filter Arsip) untuk mengubahnya.</div>
    @endif
    @if ($tema && $tema->undangan_count > 0 && ! $arsip)
        <div class="rounded-xl border border-[#d9e4fb] bg-brand-50 px-4 py-3 text-[13px] leading-5 text-gray-700">{{ $tema->undangan_count }} undangan memakai tema ini. Undangan yang sudah terbit memakai salinan tema saat diterbitkan; perubahan di sini terlihat pada draf dan terbitan berikutnya.</div>
    @endif
    @error('f.nama')<div class="galat" role="alert">{{ $message }}</div>@enderror

    <div class="grid items-start gap-4 grid-cols-[minmax(0,1fr)] lg:grid-cols-[380px_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <section class="kartu flex flex-col gap-3.5 p-4" aria-labelledby="th-info">
                <h2 id="th-info" class="h2 m-0">Info tema</h2>
                <div>
                    <span class="lbl">Paket</span>
                    <div class="seg" role="group" aria-label="Paket tema">
                        @foreach (\App\Models\Tema::TIER as $t)
                            <button type="button" wire:click="pilihTier('{{ $t }}')" aria-pressed="{{ $f['tier'] === $t ? 'true' : 'false' }}" @disabled($arsip)>{{ $t }}</button>
                        @endforeach
                    </div>
                    @error('f.tier')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            </section>

            <section class="kartu flex flex-col gap-3.5 p-4" aria-labelledby="th-warna">
                <h2 id="th-warna" class="h2 m-0">Warna global</h2>
                <div>
                    <span class="lbl">Palet cepat</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($palet as $i => [$namaPalet, $nilaiPalet])
                            <button type="button" class="btn btn-secondary btn-sm !gap-2" wire:click="terapkanPalet({{ $i }})" @disabled($arsip) title="Terapkan palet {{ $namaPalet }}">
                                <span class="flex gap-0.5" aria-hidden="true">@foreach (['warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_teks'] as $k)<span class="size-3.5 rounded-full border border-black/10" style="background:{{ $nilaiPalet[$k] }}"></span>@endforeach</span>
                                {{ $namaPalet }}
                            </button>
                        @endforeach
                    </div>
                </div>
                @foreach ($labelWarna as $k => $label)
                    <div wire:key="warna-{{ $k }}">
                        <label for="w-{{ $k }}" class="lbl">{{ $label }}</label>
                        <div class="flex items-center gap-2">
                            <input type="color" class="h-10 w-12 shrink-0 cursor-pointer rounded-[10px] border border-line-strong bg-white p-1" aria-label="Pilih {{ $label }}" value="{{ \App\Services\DokumenTema::hex($f[$k] ?? null) ?? '#000000' }}" wire:model.live.debounce.300ms="f.{{ $k }}" @disabled($arsip)>
                            <input id="w-{{ $k }}" type="text" class="inp font-mono uppercase" maxlength="7" spellcheck="false" autocomplete="off" placeholder="#RRGGBB" wire:model.live.debounce.400ms="f.{{ $k }}" @disabled($arsip) @error('f.'.$k) aria-invalid="true" @enderror>
                        </div>
                        @error('f.'.$k)<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                    </div>
                @endforeach
            </section>

            <section class="kartu flex flex-col gap-3.5 p-4" aria-labelledby="th-tipo">
                <h2 id="th-tipo" class="h2 m-0">Tipografi global</h2>
                <div>
                    <span class="lbl">Font judul</span>
                    <div class="seg" role="group" aria-label="Font judul">
                        @foreach ($fontNama as $k => $label)
                            <button type="button" wire:click="pilihFont('{{ $k }}')" aria-pressed="{{ $f['font_judul'] === $k ? 'true' : 'false' }}" @disabled($arsip)>{{ $label }}</button>
                        @endforeach
                    </div>
                    @error('f.font_judul')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="th-base" class="lbl">Ukuran teks dasar: <b>{{ (int) $f['base'] }} px</b></label>
                    <input id="th-base" type="range" class="h-6 w-full" min="{{ \App\Services\DokumenTema::BASE_MIN }}" max="{{ \App\Services\DokumenTema::BASE_MAX }}" step="1" wire:model.live.debounce.300ms="f.base" @disabled($arsip)>
                    <div class="hint">{{ \App\Services\DokumenTema::BASE_MIN }} sampai {{ \App\Services\DokumenTema::BASE_MAX }} px. Bawaan {{ \App\Services\DokumenTema::BASE_BAWAAN }} px. Ukuran teks yang diatur sendiri oleh pengguna tidak ikut berubah.</div>
                    @error('f.base')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                </div>
            </section>

            <section class="kartu flex flex-col gap-3 p-4" aria-labelledby="th-bgf">
                <h2 id="th-bgf" class="h2 m-0">Background Fixed</h2>
                <div class="flex items-center justify-between gap-3">
                    <label for="th-bgf-sw" class="text-sm leading-5 font-medium text-ink">Aktifkan untuk pengguna tema ini</label>
                    <button id="th-bgf-sw" type="button" role="switch" class="sakelar" aria-checked="{{ ! empty($f['bgfixed']) ? 'true' : 'false' }}" wire:click="ubahBgfixed" @disabled($arsip)></button>
                </div>
                <div class="hint">Menyala: tema ini ditandai mendukung latar tetap (gambar yang diatur pengguna di Edit Undangan). Mati: fitur itu tidak ditawarkan untuk tema ini.</div>
            </section>

            <section class="kartu flex flex-col gap-3 p-4" aria-labelledby="th-qa">
                <h2 id="th-qa" class="h2 m-0 flex items-center gap-2"><x-ikon nama="theme-periksa" :ukuran="16" class="text-brand" /> Periksa tema</h2>
                <ul class="m-0 flex list-none flex-col gap-2 p-0">
                    @foreach ($periksa as $p)
                        <li @class(['flex items-start gap-2 rounded-xl px-3 py-2 text-[13px] leading-5', 'bg-[#FEF3F2] text-[#B42318]' => $p['lv'] === 'err', 'bg-[#FFFAEB] text-[#B54708]' => $p['lv'] === 'warn', 'bg-[#ECFDF3] text-[#067647]' => $p['lv'] === 'ok'])>
                            <x-ikon :nama="$p['lv'] === 'ok' ? 'centang' : 'peringatan'" :ukuran="15" class="mt-0.5 shrink-0" />
                            <span>{{ $p['teks'] }}</span>
                        </li>
                    @endforeach
                </ul>
                @if ($galatAda)<div class="hint">Masalah penting menghalangi penerbitan. Draf tetap bisa disimpan.</div>@endif
            </section>
        </div>

        <section class="kartu flex flex-col items-center gap-3 p-4 lg:sticky lg:top-4" aria-labelledby="th-pv">
            <div class="flex w-full items-center justify-between gap-2">
                <h2 id="th-pv" class="h2 m-0">Pratinjau langsung</h2>
                <span class="sub" wire:loading.delay wire:target="f, pilihFont, pilihTier, terapkanPalet">Memperbarui...</span>
            </div>
            <div class="w-full max-w-[420px] overflow-hidden rounded-[28px] border-[6px] border-gray-900 bg-white shadow-lg">
                <iframe title="Pratinjau tema {{ $f['nama'] }}" class="block h-[720px] w-full border-0 bg-white" sandbox="" referrerpolicy="no-referrer" srcdoc="{{ $dokumen }}"></iframe>
            </div>
            <p class="sub m-0 max-w-[420px] text-center">Memakai isi contoh undangan. Gulir di dalam ponsel untuk melihat seluruh bagian. Pratinjau memakai nilai yang sedang kamu ketik, belum tentu sudah tersimpan.</p>
        </section>
    </div>

    <x-dialog wire:model="dialogRiwayat" judul="Riwayat versi" sub="Maksimal 20 versi terakhir. Memulihkan hanya memuat ke formulir; simpan untuk menjadikannya versi baru." lebar="max-w-[560px]">
        @forelse ($riwayat as $r)
            <div wire:key="rw-{{ $r->id }}" class="flex items-center gap-3 rounded-xl border border-line p-3">
                <div class="min-w-0 flex-1">
                    <div class="text-sm leading-5 font-semibold text-ink">Versi {{ $r->versi }} · {{ $r->label ?? 'Disimpan' }}</div>
                    <div class="text-xs leading-4 text-muted">{{ $r->created_at?->translatedFormat('j M Y H:i') }}{{ $r->pembuat ? ' · '.$r->pembuat->name : '' }}</div>
                    <div class="mt-1.5 flex gap-1" aria-hidden="true">@foreach (['warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks'] as $k)<span class="size-3.5 rounded-full border border-black/10" style="background:{{ \App\Services\DokumenTema::hex($r->data[$k] ?? null) ?? '#FFFFFF' }}"></span>@endforeach</div>
                </div>
                @if (! $arsip)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="pulihkanVersi({{ $r->id }})">Pulihkan</button>
                @endif
            </div>
        @empty
            <p class="m-0 text-sm text-muted">Belum ada riwayat.</p>
        @endforelse
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogRiwayat = false">Tutup</button>
        </x-slot:footer>
    </x-dialog>
</div>
