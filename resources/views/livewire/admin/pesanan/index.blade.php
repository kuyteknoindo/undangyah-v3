@php
    use App\Models\Pesanan;
    use App\Services\TampilPesananAdmin as T;

    $angka = fn (int|float $n): string => number_format($n, 0, ',', '.');
    $rp = fn (int|float $n): string => T::rp($n);
    $labelStatus = fn (string $s): string => $s === Pesanan::REFUND ? 'Refund' : (Pesanan::STATUS[$s][0] ?? ucfirst($s));
    $warnaStatus = fn (string $s): string => $s === Pesanan::REFUND ? 'biru' : (Pesanan::STATUS[$s][1] ?? 'abu');
    $chips = [['', 'Semua', 'semua'], ['menunggu', 'Menunggu', 'menunggu'], ['lunas', 'Lunas', 'lunas'], ['kedaluwarsa', 'Kedaluwarsa', 'kedaluwarsa'], ['batal', 'Dibatalkan', 'batal'], ['refund', 'Refund', 'refund']];
    $konversi = $kpi['semua'] > 0 ? (int) round($kpi['pernahBayar'] / $kpi['semua'] * 100) : 0;
@endphp
<div class="mx-auto flex max-w-[1180px] flex-col gap-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="sub m-0 max-w-2xl">Semua pesanan dari Checkout. Menandai lunas langsung mengaktifkan paket atau add-on pembeli dan mengirim notifikasi.</p>
        <div class="flex flex-wrap gap-2">
            @if (Route::has('admin.invoices'))
                <a href="{{ route('admin.invoices') }}" wire:navigate class="btn btn-secondary btn-sm"><x-ikon nama="ap-faktur" :ukuran="15" /> Invoices</a>
            @endif
            <button type="button" wire:click="ekspor" wire:loading.attr="disabled" wire:target="ekspor" class="btn btn-secondary btn-sm"><x-ikon nama="ap-unduh" :ukuran="15" /> Ekspor CSV</button>
        </div>
    </div>

    <section aria-label="Ringkasan pesanan" class="grid grid-cols-2 gap-4 sm:grid-cols-3 xl:grid-cols-5">
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Pesanan hari ini</span><x-ikon nama="ap-tas" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $angka($kpi['hari']) }}</div><div class="hint">{{ $rp($kpi['hariNilai']) }} lunas hari ini</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Pesanan bulan ini</span><x-ikon nama="ap-tas" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $angka($kpi['bulan']) }}</div><div class="hint">{{ $angka($kpi['bulanLunas']) }} lunas</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Pendapatan lunas</span><x-ikon nama="ap-dompet" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $rp($kpi['lunasNilai']) }}</div><div class="hint">Bulan ini {{ $rp($kpi['lunasBulan']) }}</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Menunggu bayar</span><x-ikon nama="ap-jam" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $angka($kpi['menunggu']) }}</div><div class="hint">{{ $rp($kpi['menungguNilai']) }} belum dibayar</div></div>
        <div class="kartu kpi"><div class="flex items-center justify-between"><span class="kpi-l">Konversi bayar</span><x-ikon nama="ap-naik" :ukuran="16" class="text-subtle" /></div><div class="kpi-v">{{ $konversi }}%</div><div class="hint">{{ $angka($kpi['pernahBayar']) }} dari {{ $angka($kpi['semua']) }} pesanan pernah dibayar</div></div>
    </section>

    <section aria-labelledby="dp-h" class="kartu flex flex-col">
        <div class="flex flex-col gap-4 p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="dp-h" class="h2">Daftar pesanan</h2>
                    <div class="sub">{{ $item->total() === $hitung['semua']['n'] && ! $adaFilter ? $angka($item->total()).' pesanan' : $angka($item->total()).' pesanan cocok' }}</div>
                </div>
                <div class="flex w-full flex-col gap-1.5 sm:w-80">
                    <label for="dp-q" class="label">Cari pesanan</label>
                    <div class="field">
                        <x-ikon nama="ap-cari" :ukuran="16" class="text-muted" />
                        <input id="dp-q" type="search" class="field-input" placeholder="No. pesanan, nama, email, atau item" maxlength="100" autocomplete="off" wire:model.live.debounce.400ms="cari">
                    </div>
                </div>
            </div>

            <div role="group" aria-label="Filter status" class="flex flex-wrap items-center gap-2">
                @foreach ($chips as [$kunci, $label, $hk])
                    <button type="button" wire:click="pilihStatus('{{ $kunci }}')" aria-pressed="{{ $status === $kunci ? 'true' : 'false' }}"
                            class="inline-flex min-h-8 items-center gap-1.5 rounded-full border px-3 text-[13px] leading-5 font-medium transition {{ $status === $kunci ? 'border-brand bg-brand-50 text-brand' : 'border-line-strong bg-white text-nav hover:bg-gray-50' }}">
                        {{ $label }}<span class="tag">{{ $angka($hitung[$hk]['n']) }}</span>
                    </button>
                @endforeach
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-[repeat(3,minmax(0,1fr))_auto]">
                <div class="flex flex-col gap-1.5">
                    <label for="dp-m" class="label">Metode pembayaran</label>
                    <select id="dp-m" class="inp" wire:model.live="metode">
                        <option value="">Semua metode</option>
                        @foreach ($daftarMetode as $m)<option value="{{ $m }}">{{ $m }}</option>@endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="dp-p" class="label">Periode</label>
                    <select id="dp-p" class="inp" wire:model.live="periode">
                        <option value="">Semua waktu</option>
                        <option value="today">Hari ini</option>
                        <option value="7">7 hari terakhir</option>
                        <option value="30">30 hari terakhir</option>
                        <option value="month">Bulan ini</option>
                        <option value="custom">Rentang tanggal...</option>
                    </select>
                </div>
                <div class="flex flex-col gap-1.5">
                    <label for="dp-u" class="label">Urutkan</label>
                    <select id="dp-u" class="inp" wire:model.live="urut">
                        <option value="baru">Terbaru</option>
                        <option value="lama">Terlama</option>
                        <option value="tinggi">Total tertinggi</option>
                        <option value="rendah">Total terendah</option>
                    </select>
                </div>
                @if ($adaFilter)
                    <div class="flex items-end"><button type="button" wire:click="resetFilter" class="btn btn-ghost btn-sm"><x-ikon nama="tutup" :ukuran="14" /> Reset filter</button></div>
                @endif
            </div>

            @if ($periode === 'custom')
                <div class="grid max-w-xl gap-3 sm:grid-cols-2">
                    <div class="flex flex-col gap-1.5"><label for="dp-d1" class="label">Dari tanggal</label><input id="dp-d1" type="date" class="inp" wire:model.live="dari"></div>
                    <div class="flex flex-col gap-1.5"><label for="dp-d2" class="label">Sampai tanggal</label><input id="dp-d2" type="date" class="inp" wire:model.live="sampai"></div>
                    @if ($rentangSalah)<div role="alert" class="galat sm:col-span-2">Tanggal awal tidak boleh setelah tanggal akhir.</div>@endif
                </div>
            @endif
        </div>

        @if ($item->isEmpty())
            <div class="border-t border-line" role="status">
                <x-kosong :judul="$hitung['semua']['n'] === 0 && ! $adaFilter ? 'Belum ada pesanan' : 'Tidak ada pesanan yang cocok'"
                          :teks="$adaFilter ? 'Coba ubah kata kunci, status, metode, atau periode.' : 'Pesanan dari Checkout akan muncul di sini.'" ikon="ap-tas">
                    @if ($adaFilter)<button type="button" wire:click="resetFilter" class="btn btn-secondary btn-sm">Reset filter</button>@endif
                </x-kosong>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar pesanan</caption>
                    <thead><tr><th>No. pesanan &amp; tanggal</th><th>Pembeli</th><th>Item</th><th class="text-right">Total</th><th>Metode</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($item as $p)
                            @php
                                $st = $p->statusEfektif();
                            @endphp
                            <tr wire:key="pesanan-{{ $p->id }}">
                                <td>
                                    <button type="button" wire:click="buka({{ $p->id }})" class="font-semibold whitespace-nowrap text-brand hover:underline">{{ $p->nomor }}</button>
                                    <div class="hint">{{ T::tgl($p->created_at) }}</div>
                                </td>
                                <td class="max-w-[200px]"><div class="truncate font-medium text-ink">{{ $p->pembeli_nama }}</div><div class="hint truncate">{{ $p->pembeli_email }}</div></td>
                                <td class="max-w-[220px]"><div class="truncate" title="{{ $p->ringkasItem() }}">{{ $p->ringkasItem() }}</div>@if ($p->item->count() > 1)<div class="hint">{{ $p->item->count() }} item</div>@endif</td>
                                <td class="text-right font-medium whitespace-nowrap text-ink">{{ $rp($p->total) }}</td>
                                <td class="whitespace-nowrap">{{ $p->metode_label ?? '-' }}</td>
                                <td>
                                    <x-pill :warna="$warnaStatus($st)">{{ $labelStatus($st) }}</x-pill>
                                    @if ($p->bukti_path && $st === 'menunggu')<div class="hint mt-1">Bukti {{ $p->ditunda_at ? '(Pending)' : 'perlu dicek' }}</div>@endif
                                </td>
                                <td>
                                    <div class="flex items-center gap-1.5">
                                        @if (in_array($p->status, ['menunggu', 'kedaluwarsa'], true))
                                            <button type="button" wire:click="tanya({{ $p->id }}, 'lunas')" class="btn-icon" aria-label="Tandai {{ $p->nomor }} lunas" title="Tandai lunas"><x-ikon nama="ap-lunas" :ukuran="16" class="text-success" /></button>
                                        @endif
                                        <button type="button" wire:click="buka({{ $p->id }})" class="btn btn-secondary btn-sm" aria-label="Detail {{ $p->nomor }}">Detail</button>
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

    {{-- Detail pesanan --}}
    <x-dialog wire:model="dialogDetail" :judul="$detail ? 'Pesanan '.$detail['p']->nomor : 'Detail pesanan'" :sub="$detail ? 'Dibuat '.T::tgl($detail['p']->created_at).' · '.($detail['p']->metode_label ?? '-') : null" lebar="max-w-[880px]">
        @if ($detail)
            @php
                $p = $detail['p'];
                $st = $detail['status'];
                $aksi = $detail['aksi'];
                $v = $detail['versiBukti'];
            @endphp
            <div class="flex flex-wrap items-center gap-2">
                <x-pill :warna="$warnaStatus($st)">{{ $labelStatus($st) }}</x-pill>
                @if ($p->ditunda_at && $p->status === 'menunggu')<x-pill warna="kuning">Pending</x-pill>@endif
                @if ($p->status === 'menunggu' && $st === 'menunggu')<span class="hint">Batas bayar {{ T::tgl($p->kedaluwarsa_at) }} ({{ T::sisa($p->kedaluwarsa_at) }})</span>@endif
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-line p-4">
                    <h3 class="m-0 mb-2 text-[13px] leading-[18px] font-semibold text-ink">Rincian item</h3>
                    @foreach ($detail['garis']['item'] as $l)
                        <div class="flex justify-between gap-3 py-1 text-[13px] leading-[18px]"><span class="min-w-0">{{ $l['label'] }}</span><span class="whitespace-nowrap">{{ $l['jumlah'] }}</span></div>
                    @endforeach
                    <div class="mt-2 border-t border-dashed border-line pt-2">
                        @foreach ($detail['garis']['ringkas'] as $l)
                            <div class="flex justify-between gap-3 py-0.5 text-[13px] leading-[18px] text-muted"><span>{{ $l['label'] }}</span><span class="whitespace-nowrap">{{ $l['jumlah'] }}</span></div>
                        @endforeach
                        <div class="mt-1 flex justify-between gap-3 text-sm leading-5 font-semibold text-ink"><span>Total</span><span>{{ $rp($p->total) }}</span></div>
                    </div>
                </div>
                <div class="rounded-xl border border-line p-4">
                    <h3 class="m-0 mb-2 text-[13px] leading-[18px] font-semibold text-ink">Pembeli &amp; pembayaran</h3>
                    <dl class="m-0 grid grid-cols-[110px_minmax(0,1fr)] gap-x-3 gap-y-1.5 text-[13px] leading-[18px]">
                        <dt class="text-muted">Nama</dt><dd class="m-0 break-words">{{ $p->pembeli_nama }}</dd>
                        <dt class="text-muted">Email</dt><dd class="m-0 break-all">{{ $p->pembeli_email }}</dd>
                        <dt class="text-muted">HP</dt><dd class="m-0">{{ $p->pembeli_hp ?: '-' }}</dd>
                        <dt class="text-muted">Alamat</dt><dd class="m-0 break-words">{{ $p->pembeli_alamat ?: '-' }}</dd>
                        <dt class="text-muted">Akun</dt><dd class="m-0 break-words">{{ $p->pengguna?->name ?? '-' }} (#{{ $p->user_id }})</dd>
                        <dt class="text-muted">Tujuan bayar</dt><dd class="m-0 break-words">{{ $detail['tujuan'] }}</dd>
                        <dt class="text-muted">Voucher</dt><dd class="m-0">{{ $p->voucher_kode ? $p->voucher_kode.' (- '.$rp($p->diskon).')' : '-' }}</dd>
                        <dt class="text-muted">Dibayar</dt><dd class="m-0">{{ $p->dibayar_at ? T::tgl($p->dibayar_at) : '-' }}</dd>
                    </dl>
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-line p-4">
                    <h3 class="m-0 mb-2 text-[13px] leading-[18px] font-semibold text-ink">Riwayat status</h3>
                    <ol class="m-0 flex list-none flex-col gap-3 p-0">
                        @foreach ($detail['riwayat'] as $r)
                            <li class="flex gap-2.5"><span class="mt-1.5 size-2 shrink-0 rounded-full bg-brand" aria-hidden="true"></span>
                                <div class="min-w-0 text-[13px] leading-[18px]"><div class="font-medium text-ink">{{ $r['judul'] }}</div><div class="hint">{{ $r['waktu'] }}</div>@if ($r['rinci'])<div class="text-muted break-words">{{ $r['rinci'] }}</div>@endif</div>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <div class="rounded-xl border border-line p-4">
                    <h3 class="m-0 mb-2 text-[13px] leading-[18px] font-semibold text-ink">Bukti transfer</h3>
                    @if ($p->bukti_path)
                        <figure class="m-0">
                            <a href="{{ route('pesanan.bukti', $p) }}?v={{ $v }}" target="_blank" rel="noopener"><img src="{{ route('pesanan.bukti', $p) }}?v={{ $v }}" alt="Bukti transfer {{ $p->nomor }}" loading="lazy" class="max-h-72 w-full rounded-lg border border-line bg-gray-50 object-contain"></a>
                            <figcaption class="hint mt-1.5 break-words">{{ $p->bukti_nama }} · diunggah {{ T::tgl($p->bukti_at) }}</figcaption>
                        </figure>
                        <p class="hint m-0 mt-2">Cocokkan nominal (termasuk kode unik) dan nama pengirim dengan mutasi rekening sebelum menandai lunas.</p>
                    @else
                        <div class="sub">{{ $p->bukti_ditolak_at ? 'Bukti sebelumnya ditolak; pembeli belum mengunggah ulang.' : 'Pembeli belum mengunggah bukti transfer. Minta bukti lewat WhatsApp sebelum menandai lunas.' }}</div>
                    @endif
                </div>
            </div>

            <div class="flex flex-col gap-1.5">
                <label for="dp-catatan" class="label">Catatan admin <span class="hint">(internal, tidak terlihat pembeli)</span></label>
                <textarea id="dp-catatan" class="inp" rows="2" maxlength="1000" wire:model="catatan" @if ($errors->has('catatan')) aria-invalid="true" @endif></textarea>
                @error('catatan')<div role="alert" class="galat">{{ $message }}</div>@enderror
                <div><button type="button" wire:click="simpanCatatan" wire:loading.attr="disabled" wire:target="simpanCatatan" class="btn btn-secondary btn-sm">Simpan catatan</button></div>
            </div>
        @endif
        <x-slot:footer>
            @if ($detail)
                <a href="{{ route('invoice', $detail['p']) }}" target="_blank" rel="noopener" class="btn btn-secondary mr-auto"><x-ikon nama="ap-faktur" :ukuran="16" /> Lihat invoice</a>
                @if (in_array('kedaluwarsa', $detail['aksi'], true))<button type="button" wire:click="tanya({{ $detail['p']->id }}, 'kedaluwarsa')" class="btn btn-secondary"><x-ikon nama="ap-jam" :ukuran="16" /> Tandai kedaluwarsa</button>@endif
                @if ($detail['p']->status === 'menunggu' && $detail['p']->bukti_path)<button type="button" wire:click="tunda({{ $detail['p']->id }})" class="btn btn-secondary"><x-ikon nama="ap-tunda" :ukuran="16" /> {{ $detail['p']->ditunda_at ? 'Lepas Pending' : 'Pending' }}</button>@endif
                @if (in_array('tolak', $detail['aksi'], true))<button type="button" wire:click="tanya({{ $detail['p']->id }}, 'tolak')" class="btn btn-danger"><x-ikon nama="ap-tolak" :ukuran="16" /> Tolak bukti</button>@endif
                @if (in_array('batal', $detail['aksi'], true))<button type="button" wire:click="tanya({{ $detail['p']->id }}, 'batal')" class="btn btn-danger"><x-ikon nama="tutup" :ukuran="16" /> Batalkan</button>@endif
                @if (in_array('refund', $detail['aksi'], true))<button type="button" wire:click="tanya({{ $detail['p']->id }}, 'refund')" class="btn btn-danger"><x-ikon nama="ap-refund" :ukuran="16" /> Refund</button>@endif
                @if (in_array('lunas', $detail['aksi'], true))<button type="button" wire:click="tanya({{ $detail['p']->id }}, 'lunas')" class="btn btn-primary"><x-ikon nama="ap-lunas" :ukuran="16" /> Tandai lunas</button>@endif
                @if ($detail['aksi'] === [])<button type="button" wire:click="tutupDetail" class="btn btn-secondary">Tutup</button>@endif
            @endif
        </x-slot:footer>
    </x-dialog>

    @include('livewire.admin.pesanan._konfirmasi')
</div>
