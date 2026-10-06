@php
    $namaFont = \App\Services\DokumenTema::FONT;
    $tierGaya = \App\Support\PratinjauTema::TIER;
    $arsipMode = $fs === 'arsip';
@endphp
<div class="mx-auto flex max-w-[1240px] flex-col gap-4">
    <div class="flex flex-wrap items-center gap-3">
        <p class="sub m-0 min-w-0 flex-1 basis-80">{{ $hitung['publik'] }} tema publik tampil di tab Tema pengguna · {{ $hitung['draf'] }} draf tersembunyi{{ $hitung['arsip'] ? ' · '.$hitung['arsip'].' diarsipkan' : '' }}. Tema berstatus Publik otomatis muncul di halaman Edit Undangan dan Template Desain.</p>
        <a href="{{ route('admin.theme', ['tema' => 'baru']) }}" wire:navigate class="btn btn-primary"><x-ikon nama="tambah" :ukuran="16" /> Tambah tema</a>
    </div>

    <div class="flex flex-wrap items-center gap-x-3 gap-y-2.5">
        <div class="relative w-full sm:max-w-xs sm:flex-1">
            <label for="tq" class="sr-only">Cari tema</label>
            <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
            <input id="tq" type="search" class="inp pl-9" maxlength="60" placeholder="Cari tema..." wire:model.live.debounce.300ms="q" autocomplete="off">
        </div>
        <div class="seg flex-wrap" role="group" aria-label="Filter status">
            @foreach (['semua' => 'Semua', 'publik' => 'Publik', 'draf' => 'Draf', 'arsip' => 'Arsip'] as $k => $label)
                <button type="button" wire:click="atur('{{ $k }}')" aria-pressed="{{ $fs === $k ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ $hitung[$k] }}</span></button>
            @endforeach
        </div>
        <div class="seg flex-wrap" role="group" aria-label="Filter paket">
            @foreach (['Semua', ...\App\Models\Tema::TIER] as $t)
                <button type="button" wire:click="aturTier('{{ $t }}')" aria-pressed="{{ $tier === $t ? 'true' : 'false' }}">{{ $t }}</button>
            @endforeach
        </div>
        <div>
            <label for="tu" class="sr-only">Urutkan</label>
            <select id="tu" class="inp" wire:model.live="urut">
                @foreach (\App\Livewire\Admin\Theme::URUTAN as $k => $label)
                    <option value="{{ $k }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($hitung['semua'] + $hitung['arsip'] === 0)
        <x-kosong judul="Belum ada tema" teks="Buat tema pertama, lalu publikasikan agar tampil di halaman Edit Undangan." ikon="admin-theme">
            <a href="{{ route('admin.theme', ['tema' => 'baru']) }}" wire:navigate class="btn btn-primary btn-sm">Tambah tema</a>
        </x-kosong>
    @elseif ($baris->isEmpty())
        <x-kosong judul="{{ $arsipMode ? 'Belum ada tema yang diarsipkan' : 'Tidak ada tema yang cocok' }}" teks="{{ $arsipMode ? 'Tema yang diarsipkan muncul di sini dan bisa dipulihkan.' : 'Ubah kata kunci atau filter.' }}" ikon="bagikan-cari">
            <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilter">Reset filter</button>
        </x-kosong>
    @else
        <div class="grid gap-4" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
            @foreach ($baris as $t)
                @php
                    $publik = $t->status === 'publish';
                    $diarsip = $t->deleted_at !== null;
                @endphp
                <article wire:key="tema-{{ $t->id }}" @class(['kartu flex flex-col gap-3 rounded-3xl p-3', 'opacity-80' => $diarsip, '!border-[#BFE9A0]' => $publik && ! $diarsip])>
                    <a href="{{ route('admin.theme', ['tema' => $t->id]) }}" wire:navigate class="block w-full rounded-xl" aria-label="Buka tema {{ $t->nama }} di editor" title="Buka di editor">
                        <x-buat-undangan.sampul :tema="$t" judul="Raka & Sinta" tanggal="12 Desember 2026" />
                    </a>
                    <div class="flex min-w-0 flex-col gap-1.5 px-1">
                        <div class="flex items-center gap-2">
                            <div class="min-w-0 flex-1 truncate text-[15px] leading-5 font-bold text-ink" title="{{ $t->nama }}">{{ $t->nama }}</div>
                            @if ($diarsip)
                                <x-pill warna="abu">Arsip</x-pill>
                            @else
                                <x-pill :warna="$publik ? 'hijau' : 'kuning'">{{ $publik ? 'Publik' : 'Draf' }}</x-pill>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 text-xs leading-4 text-muted">
                            <span class="rounded-md px-1.5 text-[11px] leading-4 font-semibold" style="{{ $tierGaya[$t->tier] ?? $tierGaya['Gratis'] }}">{{ $t->tier }}</span>
                            <span>{{ $namaFont[$t->font_judul] ?? 'Serif klasik' }}</span>
                            @if (! empty($t->latar_tetap))<span class="tag">Background Fixed</span>@endif
                            @if ($t->kode)<span class="tag" title="Tema bawaan sistem">Bawaan</span>@endif
                        </div>
                        <div class="text-xs leading-4 text-muted">Dipakai {{ number_format($t->undangan_count, 0, ',', '.') }} undangan · versi {{ $t->versi }}</div>
                    </div>

                    @if (! $diarsip)
                        <div class="seg w-full" role="group" aria-label="Status tema {{ $t->nama }}">
                            <button type="button" class="flex-1" wire:click="ubahStatus({{ $t->id }}, 'publik')" aria-pressed="{{ $publik ? 'true' : 'false' }}"><x-ikon nama="template-globe" :ukuran="14" class="inline" /> Publish</button>
                            <button type="button" class="flex-1" wire:click="ubahStatus({{ $t->id }}, 'draf')" aria-pressed="{{ $publik ? 'false' : 'true' }}"><x-ikon nama="mata-tutup" :ukuran="14" class="inline" /> Draft</button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.theme', ['tema' => $t->id]) }}" wire:navigate class="btn btn-secondary btn-sm min-w-0 flex-1"><x-ikon nama="bagikan-edit" :ukuran="14" /> Edit</a>
                            <button type="button" class="btn-icon" wire:click="duplikat({{ $t->id }})" aria-label="Duplikat {{ $t->nama }}" title="Duplikat"><x-ikon nama="bagikan-duplikat" :ukuran="15" /></button>
                            @if ($urutanDrag)
                                <button type="button" class="btn-icon" wire:click="geser({{ $t->id }}, -1)" aria-label="Naikkan urutan {{ $t->nama }}" title="Naikkan urutan" @disabled($loop->first)><x-ikon nama="editor-naik" :ukuran="14" /></button>
                                <button type="button" class="btn-icon" wire:click="geser({{ $t->id }}, 1)" aria-label="Turunkan urutan {{ $t->nama }}" title="Turunkan urutan" @disabled($loop->last)><x-ikon nama="editor-turun" :ukuran="14" /></button>
                            @endif
                            <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaArsip({{ $t->id }})" aria-label="Arsipkan {{ $t->nama }}" title="Arsipkan"><x-ikon nama="katalog-arsip" :ukuran="15" /></button>
                        </div>
                    @else
                        <div class="flex flex-wrap gap-2">
                            <button type="button" class="btn btn-secondary btn-sm min-w-0 flex-1" wire:click="pulihkan({{ $t->id }})"><x-ikon nama="katalog-pulihkan" :ukuran="14" /> Pulihkan</button>
                            @if (! $t->kode && $t->undangan_count === 0)
                                <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $t->id }})" aria-label="Hapus permanen {{ $t->nama }}" title="Hapus permanen"><x-ikon nama="bagikan-hapus" :ukuran="15" /></button>
                            @else
                                <span class="inline-flex size-[34px] items-center justify-center text-muted" role="img" aria-label="Tidak bisa dihapus permanen: {{ $t->kode ? 'tema bawaan' : 'masih dipakai undangan' }}" title="Tidak bisa dihapus permanen: {{ $t->kode ? 'tema bawaan' : 'masih dipakai undangan' }}"><x-ikon nama="editor-kunci" :ukuran="14" /></span>
                            @endif
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    <x-dialog wire:model="dialogArsip" judul="Arsipkan tema?" :sub="$arsipTema ? $arsipTema->nama : null" lebar="max-w-[460px]">
        @if ($arsipTema)
            <p class="m-0 text-sm leading-5 text-gray-700">Tema <b>{{ $arsipTema->nama }}</b> disembunyikan dari katalog pengguna dan dijadikan draf. Kamu bisa memulihkannya kapan saja dari filter Arsip.</p>
            @if ($arsipTema->undangan_count > 0)
                <div class="rounded-xl bg-[#FFF4CC] px-3 py-2.5 text-[13px] leading-5 text-[#7A4A00]">{{ $arsipTema->undangan_count }} undangan memakai tema ini. Tampilannya tidak berubah, tetapi pemiliknya tidak bisa memilih tema ini lagi sampai kamu memulihkannya.</div>
            @endif
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogArsip = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="arsipkan" wire:loading.attr="disabled" wire:target="arsipkan">Arsipkan</button>
        </x-slot:footer>
    </x-dialog>

    <x-dialog wire:model="dialogHapus" judul="Hapus tema permanen?" :sub="$hapusTema ? $hapusTema->nama : null" lebar="max-w-[460px]">
        <p class="m-0 text-sm leading-5 text-gray-700">Tema dan riwayat versinya dihapus dan tidak bisa dikembalikan. Tidak ada undangan yang memakainya.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="$wire.dialogHapus = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapusPermanen" wire:loading.attr="disabled" wire:target="hapusPermanen">Hapus permanen</button>
        </x-slot:footer>
    </x-dialog>
</div>
