@php
    use App\Models\Pesanan;
    use App\Services\TampilPesananAdmin as T;

    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $rp = fn (int|float $n): string => T::rp($n);
    $labelStatus = fn (string $s): string => $s === Pesanan::REFUND ? 'Refund' : (Pesanan::STATUS[$s][0] ?? ucfirst($s));
    $warnaStatus = fn (string $s): string => $s === Pesanan::REFUND ? 'biru' : (Pesanan::STATUS[$s][1] ?? 'abu');
    $chips = [['', 'Semua', 'semua'], ['menunggu', 'Menunggu', 'menunggu'], ['lunas', 'Lunas', 'lunas'], ['batal', 'Batal', 'batal'], ['kedaluwarsa', 'Kedaluwarsa', 'kedaluwarsa'], ['refund', 'Refund', 'refund']];
@endphp
<div class="mx-auto flex max-w-[1180px] flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Invoice terbit otomatis dari setiap pesanan. Untuk transfer manual, lihat bukti lalu setujui, tahan (Pending), atau tolak.</p>
        <div class="flex flex-wrap gap-2">
            @if (Route::has('admin.pesanan'))
                <a href="{{ route('admin.pesanan') }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="ap-tas" :ukuran="15" /> Data Pesanan</a>
            @endif
            <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary btn-sm"><x-ikon nama="ap-unduh" :ukuran="15" /> Unduh CSV</button>
        </div>
    </div>

    <section aria-label="Ringkasan invoice" class="grid grid-cols-2 gap-4 xl:grid-cols-4">
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Invoice terbit</span><x-ikon nama="ap-faktur" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $angka($kpi['semua']) }}</div><div class="hint">{{ $rp($kpi['tertagih']) }} ditagihkan, di luar batal</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Sudah dibayar</span><x-ikon nama="ap-lunas" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $rp($kpi['lunasNilai']) }}</div><div class="hint">{{ $angka($kpi['lunas']) }} invoice lunas</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Belum dibayar</span><x-ikon nama="ap-jam" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $rp($kpi['menungguNilai']) }}</div><div class="hint">{{ $angka($kpi['menunggu']) }} invoice · {{ $angka($kpi['segera']) }} jatuh tempo &lt; 24 jam</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Perlu ditinjau</span><x-ikon nama="ap-gambar" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $angka($kpi['antrean']) }}</div><div class="hint">Transfer manual dengan bukti</div></div>
    </section>

    <section aria-labelledby="iv-pd" class="kartu flex flex-col gap-3 p-5">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="iv-pd" class="h2">Pendapatan lunas per bulan</h2>
            <span class="sub">6 bulan terakhir, berdasarkan tanggal bayar. Bulan ini {{ $rp($kpi['lunasBulan']) }}.</span>
        </div>
        <ul class="m-0 flex list-none flex-col gap-2 p-0">
            @foreach ($bulanan as $b)
                <li class="grid grid-cols-[72px_minmax(0,1fr)_auto] items-center gap-3 text-[13px] leading-[18px]">
                    <span class="text-muted">{{ $b['bulan']->copy()->locale('id')->translatedFormat('M Y') }}</span>
                    <span class="h-2.5 overflow-hidden rounded-full bg-gray-100" aria-hidden="true"><span class="block h-full rounded-full bg-brand" style="width: {{ $b['nilai'] > 0 ? max(2, round($b['nilai'] / $maksBulanan * 100)) : 0 }}%"></span></span>
                    <span class="text-right whitespace-nowrap text-ink">{{ $rp($b['nilai']) }} <span class="hint">· {{ $angka($b['jumlah']) }} invoice</span></span>
                </li>
            @endforeach
        </ul>
    </section>

    <section aria-labelledby="rv-h" class="kartu flex flex-col">
        <div class="flex flex-col gap-1 p-5 pb-3">
            <div class="flex items-center gap-2"><h2 id="rv-h" class="h2">Perlu ditinjau</h2><span class="tag">{{ $angka($kpi['antrean']) }}</span></div>
            <div class="sub">Pesanan yang sudah mengunggah bukti dan masih menunggu verifikasi.</div>
        </div>
        @if ($perluTinjau->isEmpty())
            <div class="border-t border-line" role="status"><x-kosong judul="Tidak ada bukti transfer yang menunggu" teks="Bukti yang diunggah pembeli di halaman Konfirmasi Pembayaran akan muncul di sini." ikon="ap-lunas" /></div>
        @else
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($perluTinjau as $v)
                    <li wire:key="tinjau-{{ $v->id }}" class="flex flex-wrap items-center gap-4 border-t border-line px-5 py-3">
                        <button type="button" wire:click="tinjau({{ $v->id }})" aria-label="Lihat bukti transfer {{ $v->nomor }}" class="shrink-0">
                            <img src="{{ route('pesanan.bukti', $v) }}?v={{ $v->bukti_at?->timestamp ?? 0 }}" alt="" loading="lazy" class="size-14 rounded-lg border border-line bg-gray-50 object-cover">
                        </button>
                        <div class="min-w-0 flex-1 basis-60">
                            <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-semibold text-ink">{{ $v->nomor }}</span>@if ($v->ditunda_at)<x-pill warna="kuning">Pending</x-pill>@endif</div>
                            <div class="truncate text-[13px] leading-[18px]">{{ $v->pembeli_nama }} · <b class="font-semibold text-ink">{{ $rp($v->total) }}</b> · {{ $v->ringkasItem() ?: '-' }}</div>
                            <div class="hint">Bukti diunggah {{ T::tgl($v->bukti_at) }} · batas bayar {{ T::sisa($v->kedaluwarsa_at) }}</div>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button type="button" wire:click="tinjau({{ $v->id }})" class="btn btn-secondary btn-sm"><x-ikon nama="mata" :ukuran="14" /> Lihat bukti</button>
                            <button type="button" wire:click="tunda({{ $v->id }})" class="btn btn-secondary btn-sm" @disabled($v->ditunda_at)><x-ikon nama="ap-tunda" :ukuran="14" /> Pending</button>
                            <button type="button" wire:click="tanya({{ $v->id }}, 'tolak')" class="btn btn-danger btn-sm">Tolak</button>
                            <button type="button" wire:click="tanya({{ $v->id }}, 'lunas')" class="btn btn-primary btn-sm"><x-ikon nama="ap-lunas" :ukuran="14" /> Setujui</button>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($kpi['antrean'] > $perluTinjau->count())<div class="hint border-t border-line px-5 py-3">Menampilkan {{ $perluTinjau->count() }} dari {{ $angka($kpi['antrean']) }}. Selesaikan yang di atas untuk melihat sisanya.</div>@endif
        @endif
    </section>

    <section aria-labelledby="iv-h" class="kartu flex flex-col">
        <div class="flex flex-col gap-4 p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div><h2 id="iv-h" class="h2">Semua invoice</h2><div class="sub">{{ $adaFilter ? $angka($item->total()).' invoice cocok' : $angka($item->total()).' invoice' }}</div></div>
                <div class="flex w-full flex-col gap-1.5 sm:w-80">
                    <label for="iv-q" class="label">Cari invoice</label>
                    <div class="field">
                        <x-ikon nama="ap-cari" :ukuran="16" class="text-muted" />
                        <input id="iv-q" type="search" class="field-input" placeholder="No. invoice, nama, atau email" maxlength="100" autocomplete="off" wire:model.live.debounce.400ms="cari">
                    </div>
                </div>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <div role="group" aria-label="Filter status invoice" class="flex flex-wrap items-center gap-2">
                    @foreach ($chips as [$kunci, $label, $hk])
                        <button type="button" wire:click="pilihStatus('{{ $kunci }}')" aria-pressed="{{ $status === $kunci ? 'true' : 'false' }}"
                                class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-[13px] leading-5 font-medium transition {{ $status === $kunci ? 'border-brand bg-brand-50 text-brand' : 'border-line-strong bg-white text-nav hover:bg-gray-50' }}">
                            {{ $label }}<span class="tag">{{ $angka($hitung[$hk]['n']) }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="ml-auto flex items-end gap-2">
                    <div class="flex flex-col gap-1.5">
                        <label for="iv-u" class="label">Urutkan</label>
                        <select id="iv-u" class="inp" wire:model.live="urut">
                            <option value="baru">Terbaru</option><option value="lama">Terlama</option><option value="tinggi">Total tertinggi</option><option value="rendah">Total terendah</option>
                        </select>
                    </div>
                    @if ($adaFilter)<button type="button" wire:click="resetFilter" class="btn btn-ghost btn-sm"><x-ikon nama="tutup" :ukuran="14" /> Reset filter</button>@endif
                </div>
            </div>
        </div>

        @if ($item->isEmpty())
            <div class="border-t border-line" role="status">
                <x-kosong :judul="$hitung['semua']['n'] === 0 && ! $adaFilter ? 'Belum ada invoice' : 'Tidak ada invoice yang cocok'"
                          :teks="$adaFilter ? 'Coba ubah kata kunci atau filter status.' : 'Invoice terbit otomatis saat pengguna membuat pesanan di Checkout.'" ikon="ap-faktur">
                    @if ($adaFilter)<button type="button" wire:click="resetFilter" class="btn btn-secondary btn-sm">Reset filter</button>@endif
                </x-kosong>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar invoice</caption>
                    <thead><tr><th>No. invoice</th><th>Ditagihkan kepada</th><th>Diterbitkan</th><th>Jatuh tempo</th><th class="text-right">Total</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($item as $p)
                            @php
                                $st = $p->statusEfektif();
                                $catatanTempo = match ($st) {
                                    'menunggu' => T::sisa($p->kedaluwarsa_at),
                                    'lunas' => 'Dibayar '.T::tgl($p->dibayar_at, false),
                                    'refund' => 'Di-refund '.T::tgl($p->refund_at, false),
                                    'batal' => 'Dibatalkan',
                                    default => 'Lewat batas',
                                };
                            @endphp
                            <tr wire:key="invoice-{{ $p->id }}">
                                <td>
                                    <a href="{{ route('invoice', $p) }}" target="_blank" rel="noopener" class="inline-flex min-h-6 items-center font-semibold whitespace-nowrap text-brand hover:underline">{{ $p->nomor }}</a>
                                    <div class="hint">{{ $p->metode_label ?? '-' }}</div>
                                </td>
                                <td class="max-w-[200px]"><div class="truncate font-medium text-ink">{{ $p->pembeli_nama }}</div><div class="hint truncate">{{ $p->pembeli_email }}</div></td>
                                <td class="whitespace-nowrap">{{ T::tgl($p->created_at, false) }}</td>
                                <td class="whitespace-nowrap"><div>{{ T::tgl($p->kedaluwarsa_at) }}</div><div class="hint">{{ $catatanTempo }}</div></td>
                                <td class="text-right font-medium whitespace-nowrap text-ink">{{ $rp($p->total) }}</td>
                                <td><x-pill :warna="$warnaStatus($st)">{{ $labelStatus($st) }}</x-pill></td>
                                <td>
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if (in_array($p->status, ['menunggu', 'kedaluwarsa'], true))
                                            <button type="button" wire:click="tinjau({{ $p->id }})" class="btn btn-secondary btn-sm">Tinjau</button>
                                        @endif
                                        <a href="{{ route('invoice', $p) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" aria-label="Lihat invoice {{ $p->nomor }}"><x-ikon nama="ap-buka" :ukuran="14" /> Lihat</a>
                                        @unless (in_array($p->status, ['batal', 'refund'], true))
                                            <button type="button" wire:click="kirimUlang({{ $p->id }})" class="btn-icon" aria-label="Kirim ulang tagihan {{ $p->nomor }}" title="Kirim ulang tagihan"><x-ikon nama="ap-kirim" :ukuran="15" /></button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-line p-4">
                <span class="hint">Menampilkan {{ $angka($item->firstItem()) }}–{{ $angka($item->lastItem()) }} dari {{ $angka($item->total()) }}</span>
                @if ($item->lastPage() > 1)
                    <nav aria-label="Paginasi" class="flex items-center gap-2">
                        <button type="button" wire:click="ke({{ $item->currentPage() - 1 }})" @disabled($item->onFirstPage()) class="btn btn-secondary btn-sm" aria-label="Halaman sebelumnya"><x-ikon nama="ap-kiri" :ukuran="14" /></button>
                        <span class="text-[13px] text-muted">{{ $item->currentPage() }} / {{ $item->lastPage() }}</span>
                        <button type="button" wire:click="ke({{ $item->currentPage() + 1 }})" @disabled(! $item->hasMorePages()) class="btn btn-secondary btn-sm" aria-label="Halaman berikutnya"><x-ikon nama="panah-kanan" :ukuran="14" /></button>
                    </nav>
                @endif
            </div>
        @endif
    </section>

    {{-- Tinjau bukti transfer --}}
    <x-dialog wire:model="dialogTinjau" :judul="$tinjau ? 'Bukti transfer '.$tinjau['p']->nomor : 'Bukti transfer'" :sub="$tinjau ? $tinjau['p']->pembeli_nama.' · '.$rp($tinjau['p']->total) : null" lebar="max-w-[760px]">
        @if ($tinjau)
            @php
                $p = $tinjau['p'];
                $st = $tinjau['status'];
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <x-pill :warna="$warnaStatus($st)">{{ $labelStatus($st) }}</x-pill>
                @if ($p->ditunda_at && $p->status === 'menunggu')<x-pill warna="kuning">Pending</x-pill>@endif
            </div>
            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div>
                    @if ($p->bukti_path)
                        <figure class="m-0">
                            <a href="{{ route('pesanan.bukti', $p) }}?v={{ $tinjau['versiBukti'] }}" target="_blank" rel="noopener"><img src="{{ route('pesanan.bukti', $p) }}?v={{ $tinjau['versiBukti'] }}" alt="Bukti transfer {{ $p->nomor }} dari {{ $p->pembeli_nama }}" class="max-h-80 w-full rounded-lg border border-line bg-gray-50 object-contain"></a>
                            <figcaption class="hint mt-1.5 break-words">{{ $p->bukti_nama }}</figcaption>
                        </figure>
                    @else
                        <div class="sub rounded-xl border border-dashed border-line-strong p-4">Pembeli belum melampirkan gambar bukti transfer. Minta bukti lewat WhatsApp sebelum menyetujui.</div>
                    @endif
                </div>
                <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] content-start gap-x-3 gap-y-1.5 text-[13px] leading-[18px]">
                    <dt class="text-muted">Pembeli</dt><dd class="m-0 break-words">{{ $p->pembeli_nama }}</dd>
                    <dt class="text-muted">Email</dt><dd class="m-0 break-all">{{ $p->pembeli_email }}</dd>
                    <dt class="text-muted">Invoice</dt><dd class="m-0">{{ $p->nomor }}</dd>
                    <dt class="text-muted">Item</dt><dd class="m-0 break-words">{{ $p->ringkasItem() ?: '-' }}</dd>
                    <dt class="text-muted">Tagihan</dt><dd class="m-0">{{ $rp($p->total) }}@if ($p->kode_unik > 0) (kode unik {{ $p->kode_unik }})@endif</dd>
                    <dt class="text-muted">Tujuan bayar</dt><dd class="m-0 break-words">{{ $tinjau['tujuan'] }}</dd>
                    <dt class="text-muted">Diunggah</dt><dd class="m-0">{{ $p->bukti_at ? T::tgl($p->bukti_at) : '-' }}</dd>
                    <dt class="text-muted">Batas bayar</dt><dd class="m-0">{{ T::tgl($p->kedaluwarsa_at) }}</dd>
                </dl>
            </div>
            <div role="note" class="rounded-xl bg-brand-50 px-3 py-2.5 text-[13px] leading-[18px] text-brand-dark">Cocokkan nominal (termasuk kode unik) dan nama pengirim dengan mutasi rekening sebelum menyetujui. Aplikasi ini tidak bisa memeriksa mutasi bank.</div>
        @endif
        <x-slot:footer>
            <button type="button" wire:click="tutupTinjau" class="btn btn-secondary mr-auto">Tutup</button>
            @if ($tinjau)
                @if ($tinjau['p']->status === 'menunggu' && $tinjau['p']->bukti_path)<button type="button" wire:click="tunda({{ $tinjau['p']->id }})" class="btn btn-secondary"><x-ikon nama="ap-tunda" :ukuran="16" /> {{ $tinjau['p']->ditunda_at ? 'Lepas Pending' : 'Pending' }}</button>@endif
                @if (in_array('tolak', $tinjau['aksi'], true))<button type="button" wire:click="tanya({{ $tinjau['p']->id }}, 'tolak')" class="btn btn-danger"><x-ikon nama="ap-tolak" :ukuran="16" /> Tolak bukti</button>@endif
                @if (in_array('lunas', $tinjau['aksi'], true))<button type="button" wire:click="tanya({{ $tinjau['p']->id }}, 'lunas')" class="btn btn-primary"><x-ikon nama="ap-lunas" :ukuran="16" /> Setujui</button>@endif
            @endif
        </x-slot:footer>
    </x-dialog>

    @include('livewire.admin.pesanan._konfirmasi')

    <x-admin.dialog-konfirmasi-sandi :label="$tertundaLabel" :pesan="$tertundaPesan" />
</div>
