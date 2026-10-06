@php
    use App\Models\Media;
    use App\Services\PenyimpanMedia;

    $ukur = fn ($b) => PenyimpanMedia::teksUkuran($b);
    $warna = ['gambar' => '#1d4ed8', 'audio' => '#7a5af8', 'video' => '#f79009', 'dokumen' => '#98a2b3'];
    $pembagi = max($total, 1);
    $pctDisk = $disk['total'] > 0 ? round(($disk['total'] - $disk['bebas']) / $disk['total'] * 100, 1) : 0;
    $deskTipe = ['gambar' => 'JPG, PNG, WEBP, GIF (SVG ditolak)', 'audio' => 'MP3, M4A, OGG, WAV', 'video' => 'MP4, WEBM. Biasanya besar, aktifkan hanya bila perlu', 'dokumen' => 'PDF, DOCX, XLSX, PPTX, TXT, CSV (tanpa makro)'];
    $labelTipe = ['gambar' => 'Gambar', 'audio' => 'Audio', 'video' => 'Video', 'dokumen' => 'Dokumen'];
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="max-w-2xl">
            <p class="sub m-0">Penggunaan penyimpanan media di server: jumlah dan ukuran berkas per tipe dan per pengguna, ruang disk nyata, serta pengaturan unggahan yang dipakai halaman Media dan pemilih foto di editor.</p>
            <p class="hint m-0 mt-1">Ukuran dihitung dari data basis data (agregasi) dan dari berkas di disk. Ukuran folder dihitung pukul {{ $ukuranDisk['dihitung'] }}.</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.media') }}" class="btn btn-secondary"><x-ikon nama="am-gambar" :ukuran="16" /> Kelola media</a>
            <button type="button" class="btn btn-secondary" wire:click="hitungUlang"><x-ikon nama="am-segarkan" :ukuran="16" /> Hitung ulang</button>
        </div>
    </div>

    <section aria-label="Ringkasan" class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="kartu kpi"><div class="kpi-l">Terpakai (media)</div><div class="kpi-v">{{ $ukur($total) }}</div><div class="hint">di disk termasuk thumbnail: {{ $ukur($ukuranDisk['byte']) }}</div></div>
        <div class="kartu kpi"><div class="kpi-l">Ruang disk server</div><div class="kpi-v">{{ $ukur($disk['bebas']) }}</div><div class="hint">bebas dari {{ $ukur($disk['total']) }} ({{ $pctDisk }}% terpakai)</div></div>
        <div class="kartu kpi"><div class="kpi-l">Jumlah file</div><div class="kpi-v">{{ number_format($jumlah, 0, ',', '.') }}</div><div class="hint">{{ $ukuranDisk['file'] }} berkas di disk (dengan thumbnail)</div></div>
        <div class="kartu kpi"><div class="kpi-l">File tidak terpakai</div><div class="kpi-v">{{ number_format((int) $takTerpakai->n, 0, ',', '.') }}</div><div class="hint">{{ (int) $takTerpakai->n ? 'Ukuran '.$ukur((int) $takTerpakai->ukuran) : 'Semua file ditandai dipakai' }}</div></div>
    </section>

    <div class="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
        <section class="kartu p-5" aria-labelledby="st-komposisi">
            <div class="flex items-start justify-between gap-3">
                <div><h2 id="st-komposisi" class="m-0 text-base font-semibold text-ink">Komposisi media</h2><div class="sub">Kuota {{ $konfig['kuota_mb'] }} MB per pengguna · aset sistem tanpa kuota</div></div>
            </div>
            @if ($penggunaMelebihi > 0)
                <div role="alert" class="mt-3 rounded-xl border border-[#fedf89] bg-[#fffaeb] px-3 py-2 text-[13px] text-[#93370d]">{{ $penggunaMelebihi }} pengguna memakai lebih dari kuota saat ini (mis. kuota diturunkan). Unggahan baru mereka ditolak sampai ada ruang.</div>
            @endif
            <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-gray-100" role="img" aria-label="Komposisi: @foreach ($labelTipe as $k => $l){{ $l }} {{ $ukur((int) ($perTipe[$k]->ukuran ?? 0)) }}{{ ! $loop->last ? ', ' : '' }}@endforeach">
                @foreach ($labelTipe as $k => $l)
                    <span style="width: {{ round((int) ($perTipe[$k]->ukuran ?? 0) / $pembagi * 100, 2) }}%; background: {{ $warna[$k] }}"></span>
                @endforeach
            </div>
            <ul class="m-0 mt-4 flex list-none flex-col p-0">
                @foreach ($labelTipe as $k => $l)
                    @php $u = (int) ($perTipe[$k]->ukuran ?? 0); @endphp
                    <li class="flex items-center gap-3 border-b border-[#eef0f3] py-2 text-[13px] last:border-b-0">
                        <span class="size-2.5 shrink-0 rounded-full" style="background: {{ $warna[$k] }}"></span>
                        <span class="flex-1"><span class="font-medium text-ink">{{ $l }}</span> <span class="text-muted">{{ (int) ($perTipe[$k]->n ?? 0) }} file</span></span>
                        <span class="font-medium text-ink">{{ $ukur($u) }}</span>
                        <span class="w-12 text-right text-muted">{{ $total > 0 ? round($u / $total * 100) : 0 }}%</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="kartu flex flex-col gap-4 p-5" aria-labelledby="st-bersih">
            <div><h2 id="st-bersih" class="m-0 text-base font-semibold text-ink">Pembersihan</h2><div class="sub">Kosongkan ruang dari file yang tidak dipakai.</div></div>
            <div class="flex items-start gap-3">
                <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand"><x-ikon nama="am-sapu" :ukuran="16" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-[13px] font-medium text-ink">File tidak terpakai</div>
                    <div class="hint">Hanya file yang tidak ditandai dipakai, tidak dirujuk undangan, tema, atau lagu, dan diunggah lebih dari {{ $umurMin }} jam lalu. Maks {{ $maksBersih }} file sekali jalan.</div>
                </div>
            </div>
            <button type="button" class="btn btn-secondary self-start" wire:click="tanyaBersih" @disabled((int) $takTerpakai->n === 0)>Bersihkan file tidak terpakai</button>
            <div class="border-t border-[#eef0f3] pt-3 text-[13px] text-muted">Batas server PHP: {{ $batasPhp }} per file, {{ $batasPost }} per permintaan. Batas per file di bawah tidak bisa melebihi {{ \App\Services\KonfigStorage::MAKS_MAKS }} MB (batas unggah sementara Livewire).</div>
        </section>
    </div>

    <div class="grid gap-5 lg:grid-cols-2">
        <section class="kartu overflow-hidden" aria-labelledby="st-pengguna">
            <div class="p-5 pb-3"><h2 id="st-pengguna" class="m-0 text-base font-semibold text-ink">Pemakaian terbesar</h2><div class="sub">Per pemilik, diurutkan menurut ukuran</div></div>
            @if ($perPengguna->isEmpty())
                <x-kosong judul="Belum ada media" teks="Belum ada file yang diunggah." ikon="am-disk" />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <caption class="sr-only">Pemakaian per pemilik</caption>
                        <thead><tr><th>Pemilik</th><th>File</th><th>Ukuran</th><th>Kuota</th></tr></thead>
                        <tbody>
                            @foreach ($perPengguna as $p)
                                @php $pct = $p->user_id ? min(100, (int) round($p->ukuran / max($konfig['kuota_byte'], 1) * 100)) : null; @endphp
                                <tr>
                                    <td class="max-w-[200px] truncate font-medium text-ink">{{ $p->user_id ? ($p->nama ?? 'Pengguna #'.$p->user_id) : 'Aset sistem' }}</td>
                                    <td>{{ $p->n }}</td>
                                    <td>{{ $ukur((int) $p->ukuran) }}</td>
                                    <td class="min-w-28">
                                        @if ($pct === null)<span class="text-muted">Tanpa kuota</span>
                                        @else
                                            <div class="text-xs text-muted">{{ $pct }}%</div>
                                            <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-gray-100" role="progressbar" aria-label="Kuota terpakai" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}"><div @class(['h-full rounded-full', 'bg-brand' => $pct < 80, 'bg-[#f79009]' => $pct >= 80]) style="width: {{ $pct }}%"></div></div>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="kartu overflow-hidden" aria-labelledby="st-terbesar">
            <div class="flex items-start justify-between gap-3 p-5 pb-3">
                <div><h2 id="st-terbesar" class="m-0 text-base font-semibold text-ink">File terbesar</h2><div class="sub">Dari pustaka Media</div></div>
                <a href="{{ route('admin.media') }}" class="btn btn-ghost btn-sm">Kelola media</a>
            </div>
            @if ($terbesar->isEmpty())
                <x-kosong judul="Pustaka media kosong" teks="Unggah file di halaman Media." ikon="am-gambar" />
            @else
                <div class="overflow-x-auto">
                    <table class="tabel">
                        <caption class="sr-only">File terbesar</caption>
                        <thead><tr><th>File</th><th>Ukuran</th><th>Status</th></tr></thead>
                        <tbody>
                            @foreach ($terbesar as $m)
                                <tr>
                                    <td><div class="max-w-[220px] truncate font-medium text-ink" title="{{ $m->nama }}">{{ $m->nama }}</div><div class="text-xs text-muted">{{ $m->labelTipe() }}</div></td>
                                    <td>{{ $ukur($m->ukuran) }}</td>
                                    <td><x-pill :warna="$m->dipakai ? 'hijau' : 'abu'">{{ $m->dipakai ? 'Dipakai' : 'Tak terpakai' }}</x-pill></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="kartu p-5" aria-labelledby="st-cfg">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 id="st-cfg" class="m-0 text-base font-semibold text-ink">Pengaturan storage</h2><div class="sub">Dipakai halaman Media dan pemilih foto di editor untuk memvalidasi unggahan.</div></div>
            @if ($kotor)<x-pill warna="kuning">Belum disimpan</x-pill>@elseif ($bawaan)<x-pill warna="abu">Memakai bawaan</x-pill>@endif
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div>
                <label for="st-kuota" class="lbl">Kuota penyimpanan per pengguna (MB)</label>
                <input id="st-kuota" type="text" inputmode="numeric" class="inp" maxlength="6" wire:model.live.debounce.300ms="kuota" aria-describedby="st-kuota-h" @error('kuota') aria-invalid="true" @enderror>
                @error('kuota')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                <div id="st-kuota-h" class="hint mt-1">Bawaan {{ \App\Services\KonfigStorage::BAWAAN['kuota_mb'] }} MB. Menurunkan kuota tidak menghapus file yang sudah ada; pengguna yang melebihi hanya tidak bisa mengunggah lagi.</div>
            </div>
            <div>
                <label for="st-maks" class="lbl">Maksimal ukuran unggah per file (MB)</label>
                <input id="st-maks" type="text" inputmode="decimal" class="inp" maxlength="6" wire:model.live.debounce.300ms="maks" aria-describedby="st-maks-h" @error('maks') aria-invalid="true" @enderror>
                @error('maks')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
                <div id="st-maks-h" class="hint mt-1">Bawaan {{ \App\Services\KonfigStorage::BAWAAN['maks_mb'] }} MB, maksimum {{ \App\Services\KonfigStorage::MAKS_MAKS }} MB.</div>
            </div>
        </div>
        <div class="mt-5">
            <div class="lbl" id="st-tipe-l">Tipe file yang diizinkan</div>
            <div class="flex flex-col rounded-xl border border-line" role="group" aria-labelledby="st-tipe-l">
                @foreach ($labelTipe as $k => $l)
                    <div class="flex items-center justify-between gap-3 border-b border-[#eef0f3] px-4 py-3 last:border-b-0">
                        <div><div class="text-[13px] font-medium text-ink" id="st-t-{{ $k }}">{{ $l }}</div><div class="hint">{{ $deskTipe[$k] }}</div></div>
                        <button type="button" role="switch" class="sakelar" aria-checked="{{ ($tipe[$k] ?? false) ? 'true' : 'false' }}" aria-labelledby="st-t-{{ $k }}" wire:click="ubahTipe('{{ $k }}')"></button>
                    </div>
                @endforeach
            </div>
            @error('tipe')<div class="galat mt-1" role="alert">{{ $message }}</div>@enderror
        </div>
        <div class="mt-5 flex flex-wrap justify-end gap-2">
            <button type="button" class="btn btn-ghost" wire:click="kembalikanBawaan">Kembalikan bawaan</button>
            <button type="button" class="btn btn-secondary" wire:click="batalkanUbahan" @disabled(! $kotor)>Batalkan perubahan</button>
            <button type="button" class="btn btn-primary" wire:click="simpan" wire:loading.attr="disabled" wire:target="simpan">Simpan pengaturan</button>
        </div>
    </section>

    <x-dialog wire:model="dialogBersih" judul="Bersihkan file tidak terpakai?" sub="Tindakan ini tidak bisa dibatalkan." lebar="max-w-[520px]">
        <p class="m-0 text-[13px] text-body">{{ $kandidat->count() }} file ({{ $ukur((int) $kandidat->sum('ukuran')) }}) akan dihapus dari disk. Daftar dihitung ulang di server saat Anda menekan tombol hapus.</p>
        <div class="max-h-56 overflow-auto rounded-xl border border-line">
            @foreach ($kandidat->take(8) as $m)
                <div class="flex items-center justify-between gap-3 border-b border-[#eef0f3] px-3 py-2 text-[13px] last:border-b-0"><span class="min-w-0 truncate text-ink">{{ $m->nama }}</span><span class="shrink-0 text-muted">{{ $ukur($m->ukuran) }}</span></div>
            @endforeach
            @if ($kandidat->count() > 8)<div class="px-3 py-2 text-[13px] text-muted">dan {{ $kandidat->count() - 8 }} file lainnya</div>@endif
        </div>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" x-on:click="buka = false">Batal</button>
            <button type="button" class="btn btn-danger-solid" wire:click="bersihkan" wire:loading.attr="disabled" wire:target="bersihkan">Hapus {{ $kandidat->count() }} file</button>
        </x-slot:footer>
    </x-dialog>
</div>
