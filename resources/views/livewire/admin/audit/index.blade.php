@php
    use App\Support\LabelAksiAdmin;

    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $waktu = fn ($t) => $t ? $t->locale('id')->translatedFormat('j M Y, H:i') : '–';
    $warnaAksi = function (string $kode): string {
        if (preg_match('/hapus|tolak|batal|refund|tangguh|gagal|terkunci|kedaluwarsa/', $kode)) {
            return 'merah';
        }
        if (preg_match('/lunas|aktifkan|buat|konfirmasi_sandi$/', $kode)) {
            return 'hijau';
        }

        return 'abu';
    };
@endphp
<div wire:init="muat" class="mx-auto flex max-w-6xl flex-col gap-5">
    <p class="sub m-0 max-w-2xl">Catatan aksi admin yang sensitif: pesanan, rekening, akun pengguna, backup, dan ekspor data. Halaman ini hanya untuk dibaca; catatan tidak bisa diubah atau dihapus dari panel.</p>

    @unless ($siap)
        <div role="status" aria-busy="true" class="kartu flex flex-col gap-3 p-5">
            <span class="sr-only">Memuat log audit…</span>
            <span class="skel h-5 w-40"></span>
            <span class="skel h-10 w-full"></span>
            @for ($i = 0; $i < 6; $i++)
                <div class="flex items-center gap-3"><span class="skel h-3.5 w-28"></span><span class="skel h-3.5 flex-1"></span><span class="skel hidden h-3.5 w-24 sm:block"></span></div>
            @endfor
        </div>
    @else
        <section aria-labelledby="la-h" class="kartu overflow-hidden">
            <div class="flex flex-col gap-3 p-4 sm:p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 id="la-h" class="h2">Riwayat aksi admin</h2>
                        <div class="sub">{{ $angka($baris->total()) }} catatan{{ $adaFilter ? ' cocok dengan filter' : '' }}</div>
                    </div>
                    <div class="w-full sm:w-80">
                        <label for="la-q" class="sr-only">Cari log audit</label>
                        <input id="la-q" type="search" wire:model.live.debounce.400ms="cari" maxlength="100" placeholder="Cari aksi, sasaran, email admin, atau IP" class="inp" autocomplete="off">
                    </div>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)] gap-2.5 sm:grid-cols-2 xl:grid-cols-[repeat(4,minmax(0,1fr))_auto] xl:items-end">
                    <div class="flex flex-col gap-1">
                        <label for="la-dari" class="lbl">Dari tanggal</label>
                        <input id="la-dari" type="date" wire:model.live="dari" @if ($sampai !== '') max="{{ $sampai }}" @endif class="inp">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="la-sampai" class="lbl">Sampai tanggal</label>
                        <input id="la-sampai" type="date" wire:model.live="sampai" @if ($dari !== '') min="{{ $dari }}" @endif class="inp">
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="la-admin" class="lbl">Admin</label>
                        <select id="la-admin" wire:model.live="admin" class="inp">
                            <option value="">Semua admin</option>
                            @foreach ($daftarAdmin as $email)<option value="{{ $email }}">{{ $email }}</option>@endforeach
                        </select>
                    </div>
                    <div class="flex flex-col gap-1">
                        <label for="la-aksi" class="lbl">Jenis aksi</label>
                        <select id="la-aksi" wire:model.live="aksi" class="inp">
                            <option value="">Semua aksi</option>
                            @foreach ($daftarAksi as $kode => $label)<option value="{{ $kode }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                    @if ($adaFilter)
                        <button type="button" wire:click="aturUlangFilter" class="btn btn-ghost btn-sm">Reset filter</button>
                    @endif
                </div>
            </div>

            @if ($baris->total() === 0)
                <x-kosong :judul="$adaFilter ? 'Tidak ada catatan yang cocok' : 'Belum ada catatan audit'" :teks="$adaFilter ? 'Coba ubah kata kunci, rentang tanggal, admin, atau jenis aksi.' : 'Aksi admin yang sensitif akan muncul di sini begitu dilakukan.'" ikon="admin-audit">
                    @if ($adaFilter)<button type="button" wire:click="aturUlangFilter" class="btn btn-secondary btn-sm">Reset filter</button>@endif
                </x-kosong>
            @else
                {{-- Desktop: tabel --}}
                <div class="hidden overflow-x-auto md:block">
                    <table class="tabel min-w-[860px]">
                        <caption class="sr-only">Log audit aksi admin, terbaru dulu</caption>
                        <thead><tr><th>Waktu</th><th>Admin</th><th>Aksi</th><th>Sasaran</th><th>IP</th><th class="text-right"><span class="sr-only">Detail</span></th></tr></thead>
                        <tbody>
                            @foreach ($baris as $b)
                                <tr wire:key="audit-{{ $b->id }}">
                                    <td class="whitespace-nowrap tabular-nums">{{ $waktu($b->created_at) }}</td>
                                    <td>
                                        <div class="max-w-[220px] truncate font-semibold text-ink">{{ $b->admin?->name ?? 'Admin dihapus' }}</div>
                                        <div class="max-w-[220px] truncate text-xs text-muted">{{ $b->admin_email }}</div>
                                    </td>
                                    <td><x-pill :warna="$warnaAksi($b->aksi)" title="{{ $b->aksi }}">{{ LabelAksiAdmin::label($b->aksi) }}</x-pill></td>
                                    <td>
                                        <div class="max-w-[260px] truncate">{{ $b->sasaran ?? '–' }}</div>
                                        @if ($b->target_email && $b->target_email !== $b->sasaran)<div class="max-w-[260px] truncate text-xs text-muted">{{ $b->target_email }}</div>@endif
                                    </td>
                                    <td class="whitespace-nowrap tabular-nums text-muted">{{ $b->ip ?? '–' }}</td>
                                    <td class="text-right"><button type="button" wire:click="bukaDetail({{ $b->id }})" class="btn btn-secondary btn-sm" aria-label="Lihat detail catatan {{ $b->id }}">Detail</button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Ponsel: daftar kartu --}}
                <ul class="m-0 flex list-none flex-col p-0 md:hidden">
                    @foreach ($baris as $b)
                        <li wire:key="audit-m-{{ $b->id }}" class="flex flex-col gap-1.5 border-t border-line px-4 py-3.5">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <x-pill :warna="$warnaAksi($b->aksi)" title="{{ $b->aksi }}">{{ LabelAksiAdmin::label($b->aksi) }}</x-pill>
                                <span class="text-xs tabular-nums text-muted">{{ $waktu($b->created_at) }}</span>
                            </div>
                            <div class="break-words text-sm text-ink">{{ $b->sasaran ?? '–' }}</div>
                            <div class="break-all text-xs text-muted">{{ $b->admin?->name ?? 'Admin dihapus' }} · {{ $b->admin_email }} · {{ $b->ip ?? '–' }}</div>
                            <div><button type="button" wire:click="bukaDetail({{ $b->id }})" class="btn btn-secondary btn-sm" aria-label="Lihat detail catatan {{ $b->id }}">Detail</button></div>
                        </li>
                    @endforeach
                </ul>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                    <span class="hint">Menampilkan {{ $angka($baris->firstItem()) }}–{{ $angka($baris->lastItem()) }} dari {{ $angka($baris->total()) }}</span>
                    @if ($baris->lastPage() > 1)
                        <nav aria-label="Paginasi" class="flex items-center gap-2">
                            <button type="button" wire:click="ke({{ $baris->currentPage() - 1 }})" @disabled($baris->onFirstPage()) class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya"><x-ikon nama="ap-kiri" :ukuran="14" /></button>
                            <span class="text-[13px] text-muted">{{ $baris->currentPage() }} / {{ $baris->lastPage() }}</span>
                            <button type="button" wire:click="ke({{ $baris->currentPage() + 1 }})" @disabled(! $baris->hasMorePages()) class="btn btn-secondary btn-sm" aria-label="Halaman berikutnya"><x-ikon nama="panah-kanan" :ukuran="14" /></button>
                        </nav>
                    @endif
                </div>
            @endif
        </section>
    @endunless

    {{-- Detail (baca saja) --}}
    <x-dialog wire:model="dialogDetail" :judul="$detail ? LabelAksiAdmin::label($detail->aksi) : 'Detail catatan'" :sub="$detail ? $waktu($detail->created_at) : null" lebar="max-w-[640px]">
        @if ($detail)
            <dl class="m-0 grid grid-cols-[minmax(0,1fr)] gap-x-4 gap-y-2.5 text-sm sm:grid-cols-[110px_minmax(0,1fr)]">
                <dt class="text-muted">Admin</dt>
                <dd class="m-0 break-words text-ink">{{ $detail->admin?->name ?? 'Admin dihapus' }} <span class="text-muted">({{ $detail->admin_email }})</span></dd>
                <dt class="text-muted">Aksi</dt>
                <dd class="m-0 text-ink">{{ LabelAksiAdmin::label($detail->aksi) }} <code class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs">{{ $detail->aksi }}</code></dd>
                <dt class="text-muted">Sasaran</dt>
                <dd class="m-0 break-words text-ink">{{ $detail->sasaran ?? '–' }}@if ($detail->target_email && $detail->target_email !== $detail->sasaran) <span class="text-muted">({{ $detail->target_email }})</span>@endif</dd>
                <dt class="text-muted">Alamat IP</dt>
                <dd class="m-0 tabular-nums text-ink">{{ $detail->ip ?? '–' }}</dd>
                <dt class="text-muted">Agen</dt>
                <dd class="m-0 break-words text-xs text-muted">{{ $detail->agen ?? '–' }}</dd>
            </dl>
            <div>
                <div class="lbl mb-1.5">Metadata</div>
                @if ($rincian)
                    <pre class="m-0 max-h-72 overflow-auto whitespace-pre-wrap break-words rounded-lg border border-line bg-gray-50 p-3 text-xs leading-5 text-ink">{{ json_encode($rincian, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    <p class="hint mt-1.5 mb-0">Nilai sensitif (kata sandi, token, kode OTP, isi pesan) disembunyikan dan nomor rekening hanya menampilkan 4 digit terakhir.</p>
                @else
                    <p class="sub m-0">Tidak ada metadata tambahan.</p>
                @endif
            </div>
        @endif
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" wire:click="tutupDetail">Tutup</button>
        </x-slot:footer>
    </x-dialog>
</div>
