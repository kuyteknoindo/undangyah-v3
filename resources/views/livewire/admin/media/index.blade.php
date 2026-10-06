@php
    use App\Models\Media;
    use App\Services\PenyimpanMedia;

    $ukur = fn ($b) => PenyimpanMedia::teksUkuran($b);
    $zona = config('app.timezone');
    $tgl = fn ($d) => $d?->timezone($zona)->locale('id')->translatedFormat('j M Y');
    $tglJam = fn ($d) => $d?->timezone($zona)->locale('id')->translatedFormat('j M Y, H:i');
    $ikonTipe = ['gambar' => 'am-gambar', 'audio' => 'am-audio', 'video' => 'am-video', 'dokumen' => 'am-dokumen'];
    $warnaTipe = ['gambar' => 'bg-brand-50 text-brand', 'audio' => 'bg-[#f4f3ff] text-[#5925dc]', 'video' => 'bg-[#fff4ed] text-[#c4320a]', 'dokumen' => 'bg-[#f2f4f7] text-[#4b5563]'];
    $semuaTerpilihTampil = $baris->isNotEmpty() && $baris->every(fn ($m) => in_array($m->id, $dipilih, true));
    $namaPemilik = fn ($m) => $m->user_id ? ($m->pemilik?->name ?? 'Pengguna #'.$m->user_id) : 'Aset sistem';
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5"
     x-data="{ unggah: false, progres: 0 }"
     x-on:livewire-upload-start="unggah = true; progres = 0"
     x-on:livewire-upload-progress="progres = $event.detail.progress"
     x-on:livewire-upload-finish="unggah = false"
     x-on:livewire-upload-error="unggah = false; $dispatch('toast', { pesan: 'Unggahan gagal. Periksa ukuran file dan koneksi, lalu coba lagi.', tipe: 'galat' })">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-2xl">
            <p class="sub m-0">Pustaka file untuk undangan: foto, ornamen, musik, video, dan dokumen. Unggahan dari halaman ini menjadi aset sistem (tanpa kuota); file milik pengguna ikut tampil dan bisa disaring.</p>
            <p class="hint m-0 mt-1">Maks {{ rtrim(rtrim(number_format($konfig['maks_mb'], 1, ',', '.'), '0'), ',') }} MB per file · sekali unggah maks 20 file · batas server PHP {{ $batasPhp }}. SVG, HTML, dan skrip ditolak; jenis file diperiksa dari isinya.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.storage') }}" class="btn btn-secondary"><x-ikon nama="am-disk" :ukuran="16" /> Atur storage</a>
            <label for="med-up" class="btn btn-primary cursor-pointer focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-brand">
                <x-ikon nama="am-unggah" :ukuran="16" /> Unggah file
                <input id="med-up" type="file" class="sr-only" multiple wire:model="berkas" accept="image/jpeg,image/png,image/webp,image/gif,audio/mpeg,audio/mp4,audio/ogg,audio/wav,audio/x-wav,video/mp4,video/webm,application/pdf,.docx,.xlsx,.pptx,.txt,.csv,.mp3,.m4a,.ogg,.wav">
            </label>
        </div>
    </div>

    <div x-show="unggah" x-cloak role="status" aria-live="polite" class="kartu flex items-center gap-3 px-4 py-3">
        <x-ikon nama="am-unggah" :ukuran="16" />
        <div class="min-w-0 flex-1">
            <div class="text-[13px] font-medium text-ink">Mengunggah file… <span x-text="progres + '%'"></span></div>
            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-label="Progres unggahan" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progres"><div class="h-full rounded-full bg-brand transition-[width]" x-bind:style="'width:' + progres + '%'"></div></div>
        </div>
    </div>
    <div wire:loading wire:target="berkas" class="sr-only" role="status">Memproses unggahan…</div>

    @if ($galatUnggah)
        <div role="alert" class="flex items-start gap-3 rounded-xl border border-[#fecdca] bg-[#fef3f2] px-4 py-3">
            <span class="mt-0.5 text-[#b42318]"><x-ikon nama="peringatan" :ukuran="16" /></span>
            <div class="min-w-0 flex-1 text-[13px] text-[#912018]">
                <div class="font-semibold">{{ count($galatUnggah) === 1 ? '1 file tidak diunggah' : count($galatUnggah).' file tidak diunggah' }}</div>
                <ul class="m-0 mt-1 list-disc pl-4">@foreach ($galatUnggah as $g)<li>{{ $g }}</li>@endforeach</ul>
            </div>
            <button type="button" class="btn-icon" wire:click="tutupGalat" aria-label="Tutup pesan"><x-ikon nama="tutup" :ukuran="14" /></button>
        </div>
    @endif

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        <div class="kartu kpi"><div class="kpi-l">Total file</div><div class="kpi-v">{{ number_format($semuaJumlah, 0, ',', '.') }}</div><div class="hint">di semua pemilik</div></div>
        <div class="kartu kpi"><div class="kpi-l">Ukuran file</div><div class="kpi-v">{{ $ukur($semuaUkuran) }}</div><div class="hint">total berkas tersimpan</div></div>
        <div class="kartu kpi"><div class="kpi-l">Gambar</div><div class="kpi-v">{{ number_format($jumlah('gambar'), 0, ',', '.') }}</div><div class="hint">{{ $ukur($ukuranTipe('gambar')) }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Audio &amp; video</div><div class="kpi-v">{{ number_format($jumlah('audio') + $jumlah('video'), 0, ',', '.') }}</div><div class="hint">{{ $jumlah('audio') }} audio · {{ $jumlah('video') }} video</div></div>
        <div class="kartu kpi col-span-2 lg:col-span-1"><div class="kpi-l">Dokumen</div><div class="kpi-v">{{ number_format($jumlah('dokumen'), 0, ',', '.') }}</div><div class="hint">{{ $ukur($ukuranTipe('dokumen')) }}</div></div>
    </section>

    <section class="kartu overflow-hidden" aria-label="Pustaka media">
        <div class="flex flex-col gap-3 border-b border-line p-4 sm:p-5">
            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full sm:max-w-xs">
                    <label for="med-q" class="sr-only">Cari nama file atau teks alt</label>
                    <span class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted"><x-ikon nama="bagikan-cari" :ukuran="16" /></span>
                    <input id="med-q" type="search" class="inp pl-9" maxlength="60" placeholder="Cari nama file atau teks alt" wire:model.live.debounce.300ms="q" autocomplete="off">
                </div>
                <label for="med-folder" class="sr-only">Filter folder</label>
                <select id="med-folder" class="inp w-auto" wire:model.live="folder">
                    <option value="semua">Semua folder</option>
                    @foreach (Media::FOLDER as $f)<option value="{{ $f }}">{{ $f }}</option>@endforeach
                </select>
                <label for="med-pemilik" class="sr-only">Filter pemilik</label>
                <select id="med-pemilik" class="inp w-auto" wire:model.live="pemilik">
                    <option value="semua">Semua pemilik</option>
                    <option value="sistem">Aset sistem</option>
                    <option value="pengguna">Milik pengguna</option>
                </select>
                <label for="med-urut" class="sr-only">Urutkan</label>
                <select id="med-urut" class="inp w-auto" wire:model.live="urut">
                    <option value="baru">Urut: Terbaru</option>
                    <option value="nama">Urut: Nama A-Z</option>
                    <option value="ukuran">Urut: Ukuran terbesar</option>
                </select>
                <div class="seg sm:ml-auto" role="group" aria-label="Tampilan">
                    <button type="button" wire:click="aturTampilan('grid')" aria-pressed="{{ $tampilan === 'grid' ? 'true' : 'false' }}" aria-label="Tampilan grid" title="Grid"><x-ikon nama="am-grid" :ukuran="16" /></button>
                    <button type="button" wire:click="aturTampilan('daftar')" aria-pressed="{{ $tampilan === 'daftar' ? 'true' : 'false' }}" aria-label="Tampilan daftar" title="Daftar"><x-ikon nama="am-daftar" :ukuran="16" /></button>
                </div>
            </div>
            <div class="seg flex-wrap self-start" role="group" aria-label="Filter tipe">
                <button type="button" wire:click="atur('semua')" aria-pressed="{{ $tipe === 'semua' ? 'true' : 'false' }}">Semua <span class="text-muted">{{ $hitungTipe->sum() }}</span></button>
                @foreach (Media::TIPE as $k => $label)
                    <button type="button" wire:click="atur('{{ $k }}')" aria-pressed="{{ $tipe === $k ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ (int) ($hitungTipe[$k] ?? 0) }}</span></button>
                @endforeach
            </div>
        </div>

        @if ($dipilihJumlah > 0)
            <div role="region" aria-label="Aksi file terpilih" class="flex flex-wrap items-center gap-3 border-b border-brand-200 bg-brand-50 px-4 py-2.5 text-[13px] sm:px-5">
                <span class="font-semibold text-brand">{{ $dipilihJumlah }} file dipilih</span>
                <span class="text-muted">{{ $ukur($dipilihUkuran) }}</span>
                <span class="grow"></span>
                <button type="button" class="btn btn-ghost btn-sm" wire:click="pilihSemuaTampil">{{ $semuaTerpilihTampil ? 'Batalkan pilih yang tampil' : 'Pilih semua yang tampil ('.$baris->count().')' }}</button>
                <button type="button" class="btn btn-ghost btn-sm" wire:click="batalPilih">Batal pilih</button>
                <button type="button" class="btn btn-danger btn-sm" wire:click="tanyaHapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /> Hapus terpilih</button>
            </div>
        @endif

        @if ($semuaJumlah === 0)
            <x-kosong judul="Belum ada media" teks="Unggah foto, ornamen, musik, atau dokumen. File yang diunggah bisa dipakai di Edit Undangan dan Theme Creator." ikon="am-gambar">
                <label for="med-up" class="btn btn-primary btn-sm cursor-pointer"><x-ikon nama="am-unggah" :ukuran="14" /> Unggah file</label>
            </x-kosong>
        @elseif ($total === 0)
            <x-kosong judul="Tidak ada file yang cocok" teks="Ubah kata kunci, tipe, folder, atau pemilik." ikon="bagikan-cari">
                <button type="button" class="btn btn-secondary btn-sm" wire:click="resetFilter">Reset filter</button>
            </x-kosong>
        @elseif ($tampilan === 'grid')
            <div class="grid grid-cols-2 gap-3 p-4 sm:grid-cols-3 sm:p-5 lg:grid-cols-4 xl:grid-cols-6">
                @foreach ($baris as $m)
                    @php $on = in_array($m->id, $dipilih, true); $thumb = $m->tipe === 'gambar' ? $m->urlThumb() : null; @endphp
                    <div wire:key="m-{{ $m->id }}" @class(['relative overflow-hidden rounded-xl bg-white', 'border border-line' => ! $on, 'border border-brand shadow-[0_0_0_3px_rgba(29,78,216,.18)]' => $on])>
                        <button type="button" class="block w-full text-left" wire:click="bukaDetail({{ $m->id }})" aria-label="Buka detail {{ $m->nama }}">
                            <span class="flex aspect-[4/3] items-center justify-center overflow-hidden bg-surface">
                                @if ($thumb)
                                    <img src="{{ $thumb }}" alt="{{ $m->alt ?: $m->nama }}" loading="lazy" decoding="async" class="size-full object-cover">
                                @else
                                    <span class="flex size-full flex-col items-center justify-center gap-1 {{ $warnaTipe[$m->tipe] ?? $warnaTipe['dokumen'] }}"><x-ikon :nama="$ikonTipe[$m->tipe] ?? 'am-dokumen'" :ukuran="28" /><span class="text-[11px] font-semibold uppercase">{{ $m->ekstensi() }}</span></span>
                                @endif
                            </span>
                            <span class="flex flex-col gap-0.5 px-3 py-2.5">
                                <span class="truncate text-[13px] font-medium text-ink" title="{{ $m->nama }}">{{ $m->nama }}</span>
                                <span class="flex items-center gap-1.5 text-[11px] text-muted"><span>{{ $ukur($m->ukuran) }} · {{ $m->folder }}</span>@if ($m->dipakai)<span class="tag !bg-[#ecfdf3] !text-[#067647]">Dipakai</span>@endif</span>
                            </span>
                        </button>
                        <button type="button" role="checkbox" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="Pilih {{ $m->nama }}" wire:click="pilih({{ $m->id }})"
                                @class(['absolute top-2 left-2 flex size-5 items-center justify-center rounded-md border-[1.5px]', 'border-brand bg-brand text-white' => $on, 'border-[#aeb3bb] bg-white/90 text-transparent' => ! $on])><x-ikon nama="centang" :ukuran="12" /></button>
                    </div>
                @endforeach
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar media</caption>
                    <thead><tr>
                        <th class="w-10"><button type="button" role="checkbox" aria-checked="{{ $semuaTerpilihTampil ? 'true' : 'false' }}" aria-label="Pilih semua yang tampil" wire:click="pilihSemuaTampil" @class(['flex size-5 items-center justify-center rounded-md border-[1.5px]', 'border-brand bg-brand text-white' => $semuaTerpilihTampil, 'border-[#aeb3bb] bg-white text-transparent' => ! $semuaTerpilihTampil])><x-ikon nama="centang" :ukuran="12" /></button></th>
                        <th>File</th><th>Folder</th><th>Pemilik</th><th>Ukuran</th><th>Diunggah</th><th>Status</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($baris as $m)
                            @php $on = in_array($m->id, $dipilih, true); $thumb = $m->tipe === 'gambar' ? $m->urlThumb() : null; @endphp
                            <tr wire:key="r-{{ $m->id }}">
                                <td><button type="button" role="checkbox" aria-checked="{{ $on ? 'true' : 'false' }}" aria-label="Pilih {{ $m->nama }}" wire:click="pilih({{ $m->id }})" @class(['flex size-5 items-center justify-center rounded-md border-[1.5px]', 'border-brand bg-brand text-white' => $on, 'border-[#aeb3bb] bg-white text-transparent' => ! $on])><x-ikon nama="centang" :ukuran="12" /></button></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg {{ $warnaTipe[$m->tipe] ?? $warnaTipe['dokumen'] }}">
                                            @if ($thumb)<img src="{{ $thumb }}" alt="" loading="lazy" class="size-full object-cover">@else<x-ikon :nama="$ikonTipe[$m->tipe] ?? 'am-dokumen'" :ukuran="18" />@endif
                                        </span>
                                        <div class="min-w-0">
                                            <button type="button" class="block max-w-[260px] truncate text-left font-medium text-ink hover:text-brand" wire:click="bukaDetail({{ $m->id }})" title="{{ $m->nama }}">{{ $m->nama }}</button>
                                            <div class="text-xs text-muted">{{ $m->labelTipe() }}{{ $m->lebar ? ' · '.$m->lebar.'×'.$m->tinggi : '' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $m->folder }}</td>
                                <td class="max-w-[160px] truncate">{{ $namaPemilik($m) }}</td>
                                <td>{{ $ukur($m->ukuran) }}</td>
                                <td>{{ $tgl($m->created_at) }}</td>
                                <td><x-pill :warna="$m->dipakai ? 'hijau' : 'abu'">{{ $m->dipakai ? 'Dipakai' : 'Tak terpakai' }}</x-pill></td>
                                <td><button type="button" class="btn-icon text-[#b42318]" wire:click="tanyaHapus([{{ $m->id }}])" aria-label="Hapus {{ $m->nama }}" title="Hapus"><x-ikon nama="bagikan-hapus" :ukuran="14" /></button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($total > 0)
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3 text-[13px] text-muted sm:px-5">
                <span>Menampilkan {{ $baris->count() }} dari {{ $total }} file{{ $adaFilter ? ' (difilter)' : '' }}. Klik file untuk detail, alt, folder, atau menghapus.</span>
                @if ($halaman > 1)
                    <div class="flex items-center gap-2">
                        <button type="button" wire:click="sebelumnya" @disabled($halAktif <= 1) class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya">Sebelumnya</button>
                        <span>{{ $halAktif }} / {{ $halaman }}</span>
                        <button type="button" wire:click="berikutnya" @disabled($halAktif >= $halaman) class="btn btn-secondary btn-sm" aria-label="Halaman berikutnya">Berikutnya</button>
                    </div>
                @endif
            </div>
        @endif
    </section>

    {{-- Detail --}}
    <x-dialog wire:model="dialogDetail" judul="Detail file" :sub="$detail?->nama" lebar="max-w-[860px]">
        @if ($detail)
            <div class="grid gap-5 md:grid-cols-2">
                <div class="flex flex-col gap-3">
                    <div class="flex min-h-48 items-center justify-center overflow-hidden rounded-xl border border-line bg-surface">
                        @if ($detail->tipe === 'gambar')
                            <img src="{{ $detail->url() }}" alt="{{ $detail->alt ?: $detail->nama }}" class="max-h-72 w-full object-contain">
                        @elseif ($detail->tipe === 'audio')
                            <div class="flex w-full flex-col items-center gap-3 p-5"><span class="flex size-12 items-center justify-center rounded-full {{ $warnaTipe['audio'] }}"><x-ikon nama="am-audio" :ukuran="22" /></span><audio controls preload="none" src="{{ $detail->url() }}" class="w-full" aria-label="Pemutar {{ $detail->nama }}"></audio></div>
                        @elseif ($detail->tipe === 'video')
                            <video controls preload="metadata" src="{{ $detail->url() }}" class="max-h-72 w-full"></video>
                        @else
                            <div class="flex flex-col items-center gap-2 p-6 text-center"><span class="flex size-12 items-center justify-center rounded-full {{ $warnaTipe['dokumen'] }}"><x-ikon nama="am-dokumen" :ukuran="22" /></span><div class="sub">Pratinjau dokumen tidak tersedia di sini.</div><a class="btn btn-secondary btn-sm" href="{{ $detail->url() }}" target="_blank" rel="noopener noreferrer"><x-ikon nama="bagikan-unduh" :ukuran="14" /> Unduh file</a></div>
                        @endif
                    </div>
                    <dl class="m-0 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-[13px]">
                        <dt class="text-muted">Tipe</dt><dd class="m-0 text-right text-ink">{{ $detail->labelTipe() }} (.{{ $detail->ekstensi() }})</dd>
                        <dt class="text-muted">Format</dt><dd class="m-0 text-right text-ink">{{ $detail->mime }}</dd>
                        <dt class="text-muted">Ukuran</dt><dd class="m-0 text-right text-ink">{{ $ukur($detail->ukuran) }}</dd>
                        <dt class="text-muted">Dimensi</dt><dd class="m-0 text-right text-ink">{{ $detail->lebar ? $detail->lebar.' × '.$detail->tinggi.' px' : '–' }}</dd>
                        <dt class="text-muted">Pemilik</dt><dd class="m-0 text-right text-ink">{{ $namaPemilik($detail) }}</dd>
                        <dt class="text-muted">Diunggah</dt><dd class="m-0 text-right text-ink">{{ $tglJam($detail->created_at) }}</dd>
                        <dt class="text-muted">Dirujuk</dt><dd class="m-0 text-right text-ink">{{ $rujukanDetail ? $ringkasan->ringkas($rujukanDetail) : 'Belum ada' }}</dd>
                    </dl>
                </div>
                <div class="flex flex-col gap-3.5">
                    <div>
                        <label for="med-nama" class="lbl">Nama file</label>
                        <input id="med-nama" type="text" class="inp" maxlength="120" wire:model="dNama" @error('dNama') aria-invalid="true" @enderror>
                        @error('dNama')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="med-alt" class="lbl">Teks alternatif (alt)</label>
                        <textarea id="med-alt" class="inp" rows="3" maxlength="300" placeholder="Contoh: Foto prewedding Raka dan Sinta di taman" wire:model="dAlt" @error('dAlt') aria-invalid="true" @enderror></textarea>
                        <div class="hint mt-1">Dibacakan pembaca layar dan tampil bila gambar gagal dimuat.</div>
                        @error('dAlt')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="med-dfolder" class="lbl">Folder</label>
                        <select id="med-dfolder" class="inp" wire:model="dFolder">@foreach (Media::FOLDER as $f)<option value="{{ $f }}">{{ $f }}</option>@endforeach</select>
                        @error('dFolder')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                    </div>
                    <div class="flex items-start justify-between gap-3 rounded-xl border border-line px-3.5 py-3">
                        <div>
                            <div class="text-[13px] font-medium text-ink" id="med-pakai-l">Dipakai di undangan</div>
                            <div class="hint">Penanda pemakaian. Diperbarui otomatis saat editor menyimpan; bisa diubah manual. File yang ditandai dipakai tidak ikut dibersihkan di Storage.</div>
                        </div>
                        <button type="button" role="switch" class="sakelar mt-0.5" aria-checked="{{ $dDipakai ? 'true' : 'false' }}" aria-labelledby="med-pakai-l" wire:click="$toggle('dDipakai')"></button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="navigator.clipboard?.writeText(@js($detail->nama)); $dispatch('toast', { pesan: 'Nama file disalin.', tipe: 'sukses' })"><x-ikon nama="bagikan-salin" :ukuran="14" /> Salin nama</button>
                        <button type="button" class="btn btn-secondary btn-sm" x-data x-on:click="navigator.clipboard?.writeText(location.origin + @js($detail->url())); $dispatch('toast', { pesan: 'URL file disalin.', tipe: 'sukses' })"><x-ikon nama="am-tautan" :ukuran="14" /> Salin URL</button>
                        <a class="btn btn-secondary btn-sm" href="{{ $detail->url() }}" target="_blank" rel="noopener noreferrer"><x-ikon nama="am-luar" :ukuran="14" /> Buka</a>
                    </div>
                </div>
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-danger sm:mr-auto" wire:click="tanyaHapus([{{ (int) $detailId }}])"><x-ikon nama="bagikan-hapus" :ukuran="14" /> Hapus file</button>
            <button type="button" class="btn btn-secondary" x-on:click="buka = false">Batal</button>
            <button type="button" class="btn btn-primary" wire:click="simpanDetail" wire:loading.attr="disabled" wire:target="simpanDetail">Simpan perubahan</button>
        </x-slot:footer>
    </x-dialog>

    {{-- Konfirmasi hapus --}}
    @php
        $nHapusDipakai = $hapusDaftar->filter(fn ($m) => $m->dipakai || isset($rujukanHapus[$m->id]))->count();
    @endphp
    <x-dialog wire:model="dialogHapus" :judul="$hapusDaftar->count() === 1 ? 'Hapus file ini?' : 'Hapus '.$hapusDaftar->count().' file?'" sub="File yang dihapus hilang dari pustaka dan tidak bisa dikembalikan." lebar="max-w-[520px]">
        <div class="max-h-56 overflow-auto rounded-xl border border-line">
            @foreach ($hapusDaftar->take(8) as $m)
                <div class="flex items-center justify-between gap-3 border-b border-[#eef0f3] px-3 py-2 text-[13px] last:border-b-0">
                    <span class="min-w-0 truncate text-ink">{{ $m->nama }}</span>
                    <span class="flex shrink-0 items-center gap-2 text-muted">@if ($m->dipakai || isset($rujukanHapus[$m->id]))<x-pill warna="kuning">Dipakai</x-pill>@endif {{ $ukur($m->ukuran) }}</span>
                </div>
            @endforeach
            @if ($hapusDaftar->count() > 8)<div class="px-3 py-2 text-[13px] text-muted">dan {{ $hapusDaftar->count() - 8 }} file lainnya · total {{ $ukur($hapusDaftar->sum('ukuran')) }}</div>@endif
        </div>
        @if ($nHapusDipakai > 0)
            <div role="alert" class="rounded-xl border border-[#fedf89] bg-[#fffaeb] p-3 text-[13px] text-[#93370d]">
                {{ $nHapusDipakai }} file ditandai dipakai atau masih dirujuk (undangan, tema, atau lagu). Undangan yang memakainya akan menampilkan ruang kosong.
                <label class="mt-2 flex cursor-pointer items-start gap-2 font-medium"><input type="checkbox" class="mt-0.5" wire:model.live="hapusPaksa"> Hapus juga file yang sedang dipakai</label>
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="buka = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="hapus" wire:loading.attr="disabled" wire:target="hapus" @disabled($nHapusDipakai > 0 && $nHapusDipakai === $hapusDaftar->count() && ! $hapusPaksa)>{{ $hapusDaftar->count() === 1 ? 'Hapus file' : 'Hapus '.$hapusDaftar->count().' file' }}</button>
        </x-slot:footer>
    </x-dialog>

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
