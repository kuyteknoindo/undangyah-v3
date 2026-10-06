@php
    $rp = fn (int|float $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $tgl = fn ($t) => $t?->locale('id')->translatedFormat('j M Y');
    $berbayar = $aktif !== 'gratis';
    $pernahBerbayar = $info['kode'] !== 'gratis';
    $namaAktif = $pernahBerbayar ? 'Paket '.$info['nama'] : 'Paket Gratis';
    [$chipTeks, $chipWarna] = match (true) {
        $berbayar => ['Aktif', 'hijau'],
        $pernahBerbayar => ['Berakhir', 'merah'],
        default => ['Gratis', 'abu'],
    };
    $persenKuota = $batas ? min(100, (int) round($terpakai / max(1, $batas) * 100)) : 0;
    $filterLabel = ['semua' => 'Semua', 'menunggu' => 'Menunggu', 'lunas' => 'Lunas', 'lain' => 'Lainnya'];
    $jumlahKeranjang = count($h['item']);
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    @if ($menunggu)
        <div role="status" class="flex flex-wrap items-center gap-3 rounded-xl bg-warning-50 px-4 py-3 text-warning">
            <x-ikon nama="beranda-jam" :ukuran="20" />
            <div class="min-w-0 flex-1">
                <div class="text-sm leading-5 font-semibold">Ada pesanan yang menunggu pembayaran</div>
                <div class="text-[13px] leading-[18px]">{{ $menunggu->nomor }} &middot; {{ $rp($menunggu->total) }} &middot; batas bayar {{ $menunggu->kedaluwarsa_at->locale('id')->translatedFormat('j M Y, H.i') }} WIB</div>
            </div>
            <a href="{{ route('konfirmasi-pembayaran', ['pesanan' => $menunggu->nomor]) }}" wire:navigate class="btn btn-secondary btn-sm">Selesaikan pembayaran</a>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-2">
        {{-- Paket saat ini --}}
        <section aria-labelledby="h-aktif" class="kartu flex flex-col gap-4 p-5">
            <div class="flex items-center gap-3">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="bintang" :ukuran="22" /></span>
                <div class="min-w-0 flex-1">
                    <div class="text-[11px] leading-[14px] font-semibold tracking-[0.06em] text-subtle uppercase">Paket saat ini</div>
                    <h2 id="h-aktif" class="m-0 text-xl leading-7 font-semibold text-ink">{{ $namaAktif }}</h2>
                </div>
                <x-pill :warna="$chipWarna">{{ $chipTeks }}</x-pill>
            </div>
            <div class="text-sm leading-5 text-body">
                @if ($berbayar)
                    {{ $info['sampai'] ? 'Aktif sampai '.$tgl($info['sampai']).'. ' : 'Aktif tanpa batas waktu. ' }}Semua template dan tanpa logo undangyah.com.
                @elseif ($pernahBerbayar)
                    Masa aktif {{ $info['nama'] }} berakhir pada {{ $tgl($info['sampai']) }}. Perpanjang agar fitur {{ $info['nama'] }} aktif lagi.
                @else
                    Tanpa batas waktu, dengan {{ $paketAktif?->kuota_undangan ?? 1 }} undangan aktif. Upgrade untuk template penuh, galeri lebih besar, dan tanpa logo.
                @endif
            </div>
            @if ($info['sisaHari'] !== null)
                <div class="flex flex-col gap-1.5">
                    <div class="flex items-center justify-between text-[13px] leading-[18px]"><span class="text-muted">Sisa masa aktif</span><span class="font-semibold text-ink">{{ $info['sisaHari'] }} hari</span></div>
                    <div role="progressbar" aria-label="Sisa masa aktif" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $persenWaktu }}" class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full bg-brand" style="width: {{ $persenWaktu }}%"></div></div>
                </div>
            @endif
            <div class="flex flex-wrap gap-2.5">
                @php $beli = $daftarPaket->first(fn ($p) => $p->dapatDibeli()); @endphp
                @if ($beli && ! $berbayar)
                    <button type="button" wire:click="pilihPaket('{{ $beli->kode }}')" class="btn btn-primary">{{ $pernahBerbayar ? 'Perpanjang ke '.$beli->nama : 'Upgrade ke '.$beli->nama }}</button>
                @elseif ($beli && $aktif === $beli->kode)
                    <button type="button" wire:click="pilihPaket('{{ $beli->kode }}')" class="btn btn-secondary">Perpanjang {{ $beli->durasi_bulan }} bulan</button>
                @endif
                <a href="#riwayat" class="btn btn-secondary">Lihat riwayat pesanan</a>
            </div>
        </section>

        {{-- Kuota dan add-on --}}
        <section aria-labelledby="h-kuota" class="kartu flex flex-col gap-4 p-5">
            <h2 id="h-kuota" class="h2">Kuota dan add-on</h2>
            <div class="flex flex-col gap-1.5">
                <div class="flex items-center justify-between text-[13px] leading-[18px]"><span class="text-muted">Undangan</span><span class="font-semibold text-ink tabular-nums">{{ $terpakai }} / {{ $batas ?? 'tanpa batas' }}</span></div>
                @if ($batas !== null)
                    <div role="progressbar" aria-label="Kuota undangan" aria-valuemin="0" aria-valuemax="{{ $batas }}" aria-valuenow="{{ min($terpakai, $batas) }}" class="h-2 overflow-hidden rounded-full bg-gray-100"><div class="h-full rounded-full {{ $persenKuota >= 100 ? 'bg-[#ff9600]' : 'bg-[#58cc02]' }}" style="width: {{ $persenKuota }}%"></div></div>
                    <div class="hint">
                        {{ $terpakai >= $batas ? 'Kuota penuh. Tambah kuota undangan lewat add-on atau upgrade paket.' : 'Sisa '.($batas - $terpakai).' undangan.' }}
                        @if ($tambahanKuota > 0) Termasuk +{{ $tambahanKuota }} dari add-on. @endif
                    </div>
                @endif
            </div>
            <div class="flex items-center justify-between text-[13px] leading-[18px]"><span class="text-muted">Galeri per undangan</span><span class="font-semibold text-ink">hingga {{ ($paketAktif?->kuota_foto) ?? 3 }} foto</span></div>
            <div class="flex flex-col gap-2">
                <span class="text-[13px] leading-[18px] text-muted">Add-on aktif</span>
                @if ($milikList->isEmpty())
                    <div class="text-[13px] leading-[18px] text-subtle">Belum ada add-on yang dibeli.</div>
                @else
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($milikList as $a)<span class="pill pill-biru">{{ $a->nama }}{{ $dimiliki[$a->kode] > 1 ? ' ×'.$dimiliki[$a->kode] : '' }}</span>@endforeach
                    </div>
                @endif
            </div>
        </section>
    </div>

    {{-- Bandingkan paket --}}
    <section aria-labelledby="h-paket" class="flex flex-col gap-3">
        <div><h2 id="h-paket" class="h2">Bandingkan paket</h2><div class="sub">Harga sekali bayar. Pembayaran lewat transfer manual dan diverifikasi admin.</div></div>
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($daftarPaket as $p)
                @php
                    $sekarang = $p->kode === $aktif;
                    $bisaBeli = $p->dapatDibeli();
                @endphp
                <article wire:key="paket-{{ $p->kode }}" class="kartu flex flex-col gap-4 p-6 {{ $p->populer ? 'border-brand shadow-[0_0_0_2px_#1D4ED8,0_8px_24px_rgba(29,78,216,0.12)]' : '' }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="m-0 text-lg leading-6 font-semibold text-ink">{{ $p->nama }}</h3>
                        @if ($p->populer)<span class="tag bg-brand text-white">POPULER</span>@endif
                        @if ($sekarang)<span class="tag bg-brand-50 text-brand">PAKET SAAT INI</span>@endif
                    </div>
                    <div class="text-[13px] leading-[18px] text-muted">{{ $p->tagline }}</div>
                    <div class="flex flex-wrap items-baseline gap-x-2">
                        @if ($p->harga_coret > $p->harga && $p->harga > 0)<span class="text-sm text-subtle line-through">{{ $rp($p->harga_coret) }}</span>@endif
                        <span class="text-[28px] leading-9 font-bold tracking-tight text-ink">{{ $rp($p->harga) }}</span>
                        <span class="text-[13px] leading-[18px] text-muted">{{ $p->harga > 0 ? 'sekali bayar, berlaku '.($p->durasi_bulan > 0 ? $p->durasi_bulan.' bulan' : 'selamanya') : 'selamanya' }}</span>
                    </div>
                    <ul class="m-0 flex list-none flex-col gap-2 p-0">
                        @foreach ($p->fitur ?? [] as $f)
                            <li class="flex items-start gap-2 text-sm leading-5 text-body"><x-ikon nama="centang" :ukuran="16" class="mt-0.5 text-[#58a700]" /> <span>{{ $f }}</span></li>
                        @endforeach
                    </ul>
                    <div class="mt-auto flex flex-col gap-2">
                        @if ($bisaBeli && (! $sekarang || $berbayar))
                            <button type="button" wire:click="pilihPaket('{{ $p->kode }}')" class="btn {{ $sekarang ? 'btn-secondary' : 'btn-primary' }} btn-lg">{{ $sekarang ? 'Perpanjang '.$p->durasi_bulan.' bulan' : ($pernahBerbayar ? 'Perpanjang ke '.$p->nama : 'Upgrade ke '.$p->nama) }}</button>
                        @elseif ($sekarang)
                            <button type="button" class="btn btn-secondary btn-lg" disabled>Paket saat ini</button>
                        @elseif ($p->status === 'draf')
                            <button type="button" class="btn btn-secondary btn-lg" disabled>Segera hadir</button>
                        @endif
                        @if ($p->harga === 0 && $berbayar)
                            <div class="hint">Berlaku otomatis setelah masa aktif paket berbayar berakhir.</div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    {{-- Add-on --}}
    <section aria-labelledby="h-addon" class="flex flex-col gap-3">
        <div><h2 id="h-addon" class="h2">Add-on</h2><div class="sub">Tambahan fitur tanpa harus ganti paket. Pilih lalu lanjut ke Checkout.</div></div>
        <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($addon as $a)
                @php
                    $status = $hitung->statusAddon($a, $h['efektif'], $dimiliki);
                    $dipilih = in_array($a->kode, array_column($h['item'], 'kode'), true);
                    $n = $dimiliki[$a->kode] ?? 0;
                @endphp
                <div wire:key="addon-{{ $a->kode }}" class="kartu flex flex-col gap-3 p-5 {{ $dipilih ? 'border-brand bg-[#f5f8ff]' : '' }}">
                    <div class="flex items-start justify-between gap-2"><span class="text-sm leading-5 font-semibold text-ink">{{ $a->nama }}</span><span class="shrink-0 text-[13px] leading-5 font-semibold text-brand">{{ $a->hargaTampil() }}</span></div>
                    <div class="text-[13px] leading-[18px] text-muted">{{ $a->deskripsi }}</div>
                    @if ($n > 0)<div class="text-xs leading-4 font-medium text-success">{{ $a->multi ? 'Sudah dibeli '.$n.' kali' : 'Sudah aktif di akunmu' }}</div>@endif
                    <div class="mt-auto">
                        @if ($dipilih)
                            <button type="button" wire:click="ubahAddon('{{ $a->kode }}')" class="btn btn-primary btn-sm" aria-label="Batalkan {{ $a->nama }} dari keranjang"><x-ikon nama="centang" :ukuran="14" /> Dipilih</button>
                        @elseif ($status === 'termasuk')
                            <span class="tag">Termasuk di paket</span>
                        @elseif ($status === 'terkunci')
                            <span class="tag">Perlu paket berbayar</span>
                        @elseif ($status === 'dimiliki')
                            <span class="tag">Sudah aktif</span>
                        @else
                            <button type="button" wire:click="ubahAddon('{{ $a->kode }}')" class="btn btn-secondary btn-sm" aria-label="Tambah {{ $a->nama }} ke keranjang"><x-ikon nama="tambah" :ukuran="14" /> Tambah</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Riwayat pesanan --}}
    <section id="riwayat" aria-labelledby="h-riw" class="kartu flex flex-col gap-4 p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div><h2 id="h-riw" class="h2">Riwayat pesanan</h2><div class="sub">Pesanan dan invoice dari akun ini.</div></div>
            <div role="group" aria-label="Saring status pesanan" class="seg">
                @foreach ($filterLabel as $kode => $label)
                    <button type="button" wire:click="setFilter('{{ $kode }}')" aria-pressed="{{ $filter === $kode ? 'true' : 'false' }}">{{ $label }} <span class="text-muted">{{ $hitungStatus[$kode] }}</span></button>
                @endforeach
            </div>
        </div>

        @if ($pesanan->isEmpty())
            <x-kosong :judul="$hitungStatus['semua'] === 0 ? 'Belum ada pesanan' : 'Tidak ada pesanan dengan status ini'" :teks="$hitungStatus['semua'] === 0 ? 'Pesanan dari Checkout akan muncul di sini, lengkap dengan invoice yang bisa dicetak.' : 'Pilih status lain di atas untuk melihat pesanan.'" ikon="billing-kwitansi" />
        @else
            <div class="-mx-5 overflow-x-auto">
                <table class="tabel">
                    <caption class="sr-only">Daftar pesanan</caption>
                    <thead><tr><th>No. invoice</th><th>Tanggal</th><th>Item</th><th>Metode</th><th>Total</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($pesanan as $p)
                            @php [$labelS, $warnaS] = $p->infoStatus(); @endphp
                            <tr wire:key="p-{{ $p->id }}">
                                <td class="font-semibold whitespace-nowrap text-ink">{{ $p->nomor }}</td>
                                <td class="whitespace-nowrap">{{ $tgl($p->created_at) }}</td>
                                <td class="min-w-48">{{ $p->ringkasItem() }}</td>
                                <td class="whitespace-nowrap">{{ $p->metode_label ?? '-' }}</td>
                                <td class="font-semibold whitespace-nowrap text-ink tabular-nums">{{ $rp($p->total) }}</td>
                                <td><x-pill :warna="$warnaS">{{ $labelS }}</x-pill></td>
                                <td>
                                    <div class="flex gap-1.5">
                                        @if ($p->menunggu())<a href="{{ route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]) }}" wire:navigate class="btn btn-primary btn-sm">Bayar</a>@endif
                                        <a href="{{ route('invoice', $p) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" aria-label="Lihat invoice {{ $p->nomor }}">Invoice</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($totalDisaring > $batasRingkas)
                <div><button type="button" wire:click="ubahSemua" class="btn btn-secondary btn-sm">{{ $semua ? 'Tampilkan lebih sedikit' : 'Tampilkan semua ('.$totalDisaring.')' }}</button></div>
            @endif
        @endif
    </section>

    {{-- Data tagihan --}}
    <section aria-labelledby="h-tag" class="kartu flex flex-col gap-4 p-5">
        <div><h2 id="h-tag" class="h2">Data tagihan</h2><div class="sub">Dipakai untuk mengisi otomatis kolom penagihan di Checkout dan tertera di invoice.</div></div>
        <form wire:submit="simpanTagihan" class="flex flex-col gap-4">
            <div class="grid gap-3.5 sm:grid-cols-2">
                <div>
                    <label for="b-nama" class="lbl">Nama pada tagihan</label>
                    <input id="b-nama" type="text" wire:model="nama" class="inp" autocomplete="name" maxlength="80" @error('nama') aria-invalid="true" aria-describedby="b-nama-g" @enderror>
                    @error('nama')<div id="b-nama-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="b-email" class="lbl">Email tagihan</label>
                    <input id="b-email" type="email" wire:model="email" class="inp" autocomplete="email" maxlength="120" @error('email') aria-invalid="true" aria-describedby="b-email-g" @enderror>
                    @error('email')<div id="b-email-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="b-hp" class="lbl">No. HP <span class="font-normal text-muted">(opsional)</span></label>
                    <input id="b-hp" type="tel" wire:model="hp" class="inp" autocomplete="tel" maxlength="30" @error('hp') aria-invalid="true" aria-describedby="b-hp-g" @enderror>
                    @error('hp')<div id="b-hp-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="b-alamat" class="lbl">Alamat <span class="font-normal text-muted">(opsional)</span></label>
                    <input id="b-alamat" type="text" wire:model="alamat" class="inp" autocomplete="street-address" maxlength="200" @error('alamat') aria-invalid="true" aria-describedby="b-alamat-g" @enderror>
                    @error('alamat')<div id="b-alamat-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                </div>
            </div>
            <div><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="simpanTagihan">Simpan data tagihan</button></div>
        </form>
    </section>

    {{-- Bilah keranjang --}}
    @if ($jumlahKeranjang > 0)
        <div role="region" aria-label="Keranjang" class="sticky bottom-4 z-30 flex flex-wrap items-center gap-3 rounded-2xl border border-line bg-white p-3.5 shadow-[0_12px_32px_-8px_rgba(16,24,40,0.25)]">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand"><x-ikon nama="billing-keranjang" :ukuran="20" /></span>
            <div class="min-w-0 flex-1">
                <div class="truncate text-sm leading-5 font-semibold text-ink">{{ collect($h['item'])->pluck('nama')->implode(', ') }}</div>
                <div class="text-[13px] leading-[18px] text-muted">Total sebelum voucher dan biaya pembayaran</div>
            </div>
            <div class="text-base font-bold text-ink tabular-nums">{{ $rp($h['subtotal']) }}</div>
            <button type="button" wire:click="kosongkanKeranjang" class="btn btn-secondary btn-sm">Kosongkan</button>
            <a href="{{ route('checkout') }}" wire:navigate class="btn btn-primary">Lanjut ke Checkout <x-ikon nama="panah-kanan" :ukuran="16" /></a>
        </div>
    @endif
</div>
