@php
    use App\Models\Lagu;
    $labelSumber = fn (Lagu $l) => match (true) {
        $l->sumber === 'youtube' => ['YouTube', 'merah'],
        $l->urlAudio() !== null => [$l->sumber === 'media' ? 'Dari Media' : 'Link audio', 'biru'],
        $l->bawaan => ['Bawaan', 'abu'],
        default => ['Tanpa audio', 'kuning'],
    };
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-2xl">
            <p class="sub m-0">Katalog musik latar yang bisa dipilih pengguna di Edit Undangan. Hanya lagu berstatus Publik yang tampil untuk pengguna.</p>
            <p class="hint m-0 mt-1">Lagu bawaan memakai kode yang sama dengan pilihan lama di editor (l1-l5). Pratinjau hanya bisa diputar bila lagu punya file audio (dari Media atau link langsung). Lagu YouTube dibuka di tab baru.</p>
        </div>
        <button type="button" class="btn btn-primary" wire:click="bukaBaru"><x-ikon nama="tambah" :ukuran="16" /> Tambah lagu</button>
    </div>

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Total lagu</div><div class="kpi-v">{{ $semua->count() }}</div><div class="hint">{{ $semua->where('bawaan', true)->count() }} lagu bawaan editor</div></div>
        <div class="kartu kpi"><div class="kpi-l">Publik</div><div class="kpi-v">{{ $semua->where('status', 'publik')->count() }}</div><div class="hint">Tampil di Edit Undangan</div></div>
        <div class="kartu kpi"><div class="kpi-l">Draf</div><div class="kpi-v">{{ $semua->where('status', 'draf')->count() }}</div><div class="hint">Belum tampil untuk pengguna</div></div>
        <div class="kartu kpi"><div class="kpi-l">Bisa diputar</div><div class="kpi-v">{{ $bisaDiputar }}</div><div class="hint">Punya audio atau link YouTube</div></div>
    </section>

    <section class="kartu overflow-hidden" aria-label="Daftar lagu">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <label for="mu-q" class="sr-only">Cari judul, artis, atau kategori</label>
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                    <input id="mu-q" type="search" class="inp pl-9" maxlength="60" placeholder="Cari judul, artis, atau kategori" wire:model.live.debounce.300ms="q" autocomplete="off">
                </div>
                <label for="mu-kat" class="sr-only">Filter kategori</label>
                <select id="mu-kat" class="inp w-auto" wire:model.live="kat">
                    <option value="semua">Semua kategori</option>
                    @foreach ($kategori as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach
                </select>
            </div>
            <div class="seg flex-wrap self-start" role="group" aria-label="Filter status">
                <button type="button" wire:click="atur('semua')" aria-pressed="{{ $fs === 'semua' ? 'true' : 'false' }}">Semua <span class="text-muted">{{ $hitung['semua'] }}</span></button>
                <button type="button" wire:click="atur('publik')" aria-pressed="{{ $fs === 'publik' ? 'true' : 'false' }}">Publik <span class="text-muted">{{ $hitung['publik'] }}</span></button>
                <button type="button" wire:click="atur('draf')" aria-pressed="{{ $fs === 'draf' ? 'true' : 'false' }}">Draf <span class="text-muted">{{ $hitung['draf'] }}</span></button>
            </div>
        </div>

        @if ($putar)
            <div role="region" aria-label="Pratinjau lagu" class="flex flex-wrap items-center gap-3 border-b border-brand-200 bg-brand-50 px-4 py-3 sm:px-5">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-white text-brand"><x-ikon nama="am-audio" :ukuran="16" /></span>
                <div class="min-w-0 flex-1 basis-40"><div class="truncate text-[13px] font-semibold text-ink">{{ $putar->judul }}</div><div class="truncate text-xs text-muted">{{ $putar->artis }} · {{ $putar->durasiTeks() }}</div></div>
                @if ($putar->urlAudio())
                    <audio controls autoplay preload="none" src="{{ $putar->urlAudio() }}" class="h-9 max-w-full flex-[2_1_240px]" aria-label="Pemutar {{ $putar->judul }}"></audio>
                @elseif ($putar->urlYoutube())
                    <span class="text-[13px] text-muted">Lagu dari YouTube tidak diputar langsung di sini.</span>
                    <a class="btn btn-secondary btn-sm" href="{{ $putar->urlYoutube() }}" target="_blank" rel="noopener noreferrer"><x-ikon nama="am-luar" :ukuran="14" /> Buka di YouTube</a>
                @else
                    <div role="status" class="text-[13px] text-[#93370d]">{{ $putar->bawaan ? 'Lagu bawaan ini belum punya file audio. Tambahkan lewat Edit agar bisa diputar.' : 'Lagu ini belum punya sumber audio. Tambahkan lewat Edit.' }}</div>
                @endif
                <button type="button" class="btn-icon" wire:click="tutupPutar" aria-label="Tutup pratinjau"><x-ikon nama="tutup" :ukuran="14" /></button>
            </div>
        @endif

        @if ($terkunci)
            <div class="border-b border-line bg-surface px-4 py-2 text-[13px] text-muted sm:px-5">Hapus pencarian dan filter untuk mengatur urutan lagu.</div>
        @endif

        @if ($semua->isEmpty())
            <x-kosong judul="Belum ada lagu" teks="Tambahkan lagu dari file audio atau link YouTube. Lagu berstatus Publik muncul di bagian Musik pada Edit Undangan." ikon="admin-music">
                <button type="button" class="btn btn-primary btn-sm" wire:click="bukaBaru"><x-ikon nama="tambah" :ukuran="14" /> Tambah lagu</button>
            </x-kosong>
        @elseif ($tampil->isEmpty())
            <x-kosong judul="Tidak ada lagu yang cocok" teks="Ubah kata kunci, kategori, atau status." ikon="bagikan-cari">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilter">Reset filter</button>
            </x-kosong>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar lagu</caption>
                    <thead><tr><th>Urutan</th><th>Lagu</th><th>Kategori</th><th>Durasi</th><th>Sumber</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($tampil as $l)
                            @php
                                $i = $semua->search(fn ($x) => $x->id === $l->id);
                                [$sl, $sw] = $labelSumber($l);
                                $pub = $l->status === 'publik';
                            @endphp
                            <tr wire:key="l-{{ $l->id }}">
                                <td>
                                    <div class="flex items-center gap-1">
                                        <span class="w-5 text-center text-muted">{{ $i + 1 }}</span>
                                        <button type="button" class="btn-icon !size-7 disabled:opacity-40" wire:click="naik({{ $l->id }})" @disabled($sedangFilter || $i <= 0) aria-label="Naikkan {{ $l->judul }}"><x-ikon nama="am-naik" :ukuran="13" /></button>
                                        <button type="button" class="btn-icon !size-7 disabled:opacity-40" wire:click="turun({{ $l->id }})" @disabled($sedangFilter || $i >= $semua->count() - 1) aria-label="Turunkan {{ $l->judul }}"><x-ikon nama="am-turun" :ukuran="13" /></button>
                                    </div>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span @class(['flex size-9 shrink-0 items-center justify-center rounded-lg', 'bg-brand-50 text-brand' => $pub, 'bg-[#f2f4f7] text-muted' => ! $pub])><x-ikon nama="am-audio" :ukuran="16" /></span>
                                        <div class="min-w-0"><div class="max-w-[260px] truncate font-medium text-ink">{{ $l->judul }}</div><div class="max-w-[260px] truncate text-xs text-muted">{{ collect([$l->artis, $l->keterangan])->filter()->implode(' · ') }}</div></div>
                                    </div>
                                </td>
                                <td>{{ $l->kategori }}</td>
                                <td>{{ $l->durasiTeks() }}</td>
                                <td><x-pill :warna="$sw">{{ $sl }}</x-pill></td>
                                <td><button type="button" wire:click="ubahStatus({{ $l->id }})" class="inline-flex min-h-6 items-center" title="Klik untuk mengubah status" aria-label="Status {{ $l->judul }}: {{ $pub ? 'Publik' : 'Draf' }}. Klik untuk mengubah ke {{ $pub ? 'Draf' : 'Publik' }}"><x-pill :warna="$pub ? 'hijau' : 'kuning'">{{ $pub ? 'Publik' : 'Draf' }}</x-pill></button></td>
                                <td>
                                    <div class="flex items-center gap-1">
                                        <button type="button" @class(['btn-icon', '!border-brand !bg-brand !text-white' => $putarId === $l->id, 'text-brand' => $putarId !== $l->id]) wire:click="putar({{ $l->id }})" aria-label="{{ $putarId === $l->id ? 'Hentikan pratinjau' : 'Putar pratinjau' }} {{ $l->judul }}"><x-ikon :nama="$putarId === $l->id ? 'am-jeda' : 'am-putar'" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon" wire:click="bukaEdit({{ $l->id }})" aria-label="Edit {{ $l->judul }}" title="Edit"><x-ikon nama="bagikan-edit" :ukuran="14" /></button>
                                        <button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus({{ $l->id }})" aria-label="Hapus {{ $l->judul }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-[13px] text-muted sm:px-5">
                <span>Menampilkan {{ $tampil->count() }} dari {{ $semua->count() }} lagu.</span>
                <span>Klik status untuk mengubah Publik/Draf. Urutan dipakai saat menampilkan katalog di editor.</span>
            </div>
        @endif
    </section>

    {{-- Formulir --}}
    <x-dialog wire:model="dialogForm" :judul="$editId ? 'Edit lagu' : 'Tambah lagu'" :sub="$editId ? ($formBawaan ? 'Lagu bawaan editor' : null) : 'Lagu baru masuk ke urutan paling bawah.'" lebar="max-w-[680px]">
        <div>
            <span class="lbl" id="mu-src-l">Sumber lagu</span>
            <div class="seg" role="group" aria-labelledby="mu-src-l">
                @foreach (Lagu::SUMBER as $k => $label)
                    <button type="button" wire:click="setSumber('{{ $k }}')" aria-pressed="{{ ($f['sumber'] ?? 'media') === $k ? 'true' : 'false' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="grid gap-3.5 sm:grid-cols-2">
            <div>
                <label for="mu-judul" class="lbl">Judul lagu <span class="text-[#b42318]">*</span></label>
                <input id="mu-judul" type="text" class="inp" maxlength="120" placeholder="Contoh: Perayaan Cinta" wire:model="f.judul" @error('f.judul') aria-invalid="true" @enderror>
                @error('f.judul')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="mu-artis" class="lbl">Artis <span class="text-[#b42318]">*</span></label>
                <input id="mu-artis" type="text" class="inp" maxlength="120" placeholder="Contoh: Studio Nada" wire:model="f.artis" @error('f.artis') aria-invalid="true" @enderror>
                @error('f.artis')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="mu-kat-f" class="lbl">Kategori <span class="text-[#b42318]">*</span></label>
                <select id="mu-kat-f" class="inp" wire:model="f.kategori">@foreach ($kategori as $k)<option value="{{ $k }}">{{ $k }}</option>@endforeach</select>
                @error('f.kategori')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
            <div>
                <label for="mu-dur" class="lbl">Durasi <span class="text-[#b42318]">*</span></label>
                <input id="mu-dur" type="text" class="inp" maxlength="7" placeholder="3:12" wire:model="f.durasi" aria-describedby="mu-dur-h" @error('f.durasi') aria-invalid="true" @enderror>
                <div id="mu-dur-h" class="hint mt-1">Format m:ss, misalnya 3:12.</div>
                @error('f.durasi')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        </div>
        <div>
            <label for="mu-ket" class="lbl">Keterangan singkat</label>
            <input id="mu-ket" type="text" class="inp" maxlength="120" placeholder="Contoh: Instrumental piano" wire:model="f.keterangan">
            <div class="hint mt-1">Tampil di bawah judul pada pilihan musik editor dan di kartu musik undangan.</div>
            @error('f.keterangan')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>

        @if (($f['sumber'] ?? 'media') === 'media')
            <div>
                <label for="mu-media" class="lbl">File audio dari Media{{ $formBawaan ? ' (opsional)' : ' *' }}</label>
                <select id="mu-media" class="inp" wire:model="f.media_id" @error('f.media_id') aria-invalid="true" @enderror>
                    <option value="">{{ $formBawaan ? 'Tanpa file audio' : 'Pilih file audio…' }}</option>
                    @foreach ($audioSistem as $a)<option value="{{ $a->id }}">{{ $a->nama }} ({{ \App\Services\PenyimpanMedia::teksUkuran($a->ukuran) }})</option>@endforeach
                </select>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[13px]">
                    <label for="mu-unggah" class="btn btn-secondary btn-sm cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand"><x-ikon nama="am-unggah" :ukuran="14" /> Unggah audio baru<input id="mu-unggah" type="file" class="sr-only" wire:model="audio" accept="audio/mpeg,audio/mp4,audio/ogg,audio/wav,audio/x-wav,.mp3,.m4a,.ogg,.wav"></label>
                    <span wire:loading wire:target="audio" class="text-muted" role="status">Mengunggah…</span>
                    <span class="hint">MP3, M4A, OGG, atau WAV. Tersimpan sebagai aset sistem di folder Musik.</span>
                </div>
                @error('f.media_id')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        @elseif (($f['sumber'] ?? '') === 'tautan')
            <div>
                <label for="mu-link" class="lbl">Link audio{{ $formBawaan ? ' (opsional)' : ' *' }}</label>
                <input id="mu-link" type="url" class="inp" maxlength="500" placeholder="https://contoh.com/lagu.mp3" wire:model="f.url" @error('f.url') aria-invalid="true" @enderror>
                <div class="hint mt-1">Link langsung ke file audio (.mp3, .m4a, .ogg). Pastikan Anda berhak memakai lagunya.</div>
                @error('f.url')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        @else
            <div>
                <label for="mu-yt" class="lbl">Link YouTube <span class="text-[#b42318]">*</span></label>
                <input id="mu-yt" type="url" class="inp" maxlength="500" placeholder="https://www.youtube.com/watch?v=..." wire:model="f.youtube" @error('f.youtube') aria-invalid="true" @enderror>
                <div class="hint mt-1">Di undangan, lagu YouTube dibuka sebagai tautan (tidak diputar otomatis).</div>
                @error('f.youtube')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
            </div>
        @endif

        <div>
            <span class="lbl" id="mu-st-l">Status</span>
            <div class="seg" role="group" aria-labelledby="mu-st-l">
                <button type="button" wire:click="setStatusForm('publik')" aria-pressed="{{ ($f['status'] ?? 'publik') === 'publik' ? 'true' : 'false' }}">Publik</button>
                <button type="button" wire:click="setStatusForm('draf')" aria-pressed="{{ ($f['status'] ?? '') === 'draf' ? 'true' : 'false' }}">Draf</button>
            </div>
            <div class="hint mt-1">Draf tidak tampil di Edit Undangan.</div>
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="buka = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">{{ $editId ? 'Simpan perubahan' : 'Tambah lagu' }}</button>
        </x-slot:footer>
    </x-dialog>

    {{-- Hapus --}}
    <x-dialog wire:model="dialogHapus" :judul="'Hapus lagu '.($hapus?->judul ?? '').'?'" sub="Lagu dihapus dari katalog dan tidak bisa dipilih lagi di Edit Undangan." lebar="max-w-[480px]">
        @if ($hapus && ($hapus->bawaan || $pemakaiHapus > 0))
            <div role="note" class="rounded-xl border border-[#fedf89] bg-[#fffaeb] p-3 text-[13px] text-[#93370d]">
                @if ($pemakaiHapus > 0){{ $pemakaiHapus }} undangan sedang memilih lagu ini dan akan kehilangan musik latar. @endif
                @if ($hapus->bawaan)Ini lagu bawaan editor (kode {{ $hapus->kode }}).@endif
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="buka = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus">Hapus lagu</button>
        </x-slot:footer>
    </x-dialog>
</div>
