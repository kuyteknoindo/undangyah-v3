@php
    use App\Support\FormatPapan as F;
    $berbayar = $aktif !== 'gratis';
    $pernahBerbayar = $info['kode'] !== 'gratis';
    $namaAktif = $pernahBerbayar ? 'Paket '.$info['nama'] : 'Paket Gratis';
    [$chipTeks, $chipGaya] = match (true) {
        $berbayar => ['AKTIF', 'background:#D7FFB8;color:#3C7A00'],
        $pernahBerbayar => ['BERAKHIR', 'background:#FFE0E0;color:#EA2B2B'],
        default => ['GRATIS', 'background:#ECEEF1;color:#454B55'],
    };
    $persenKuota = $batas ? min(100, (int) round($terpakai / max(1, $batas) * 100)) : 0;
    $filterLabel = ['semua' => 'Semua', 'menunggu' => 'Menunggu', 'lunas' => 'Lunas', 'lain' => 'Lainnya'];
    $jumlahKeranjang = count($h['item']);
    $judulH2 = 'margin:0;font-size:18px;line-height:24px;font-weight:700;letter-spacing:-0.01em;font-size:22px;line-height:28px';
    $subH2 = 'font-size:13px;line-height:19px;font-weight:500;color:#666C76';
    $kecil = 'min-height:36px;padding:0 12px;font-size:12px';
@endphp
<x-billing.gaya />
<div class="bl-root" style="box-sizing:border-box;width:100%;max-width:1136px;margin:0 auto;display:flex;flex-direction:column;gap:24px">
    @if ($menunggu)
        <div role="status" style="box-sizing:border-box;background:#FFF4CC;border:2px solid #FFE08A;border-radius:24px;padding:18px 24px;display:flex;flex-wrap:wrap;align-items:center;gap:16px">
            <span aria-hidden="true" style="flex:none;width:44px;height:44px;border-radius:22px;background:#FFFFFF;color:#9A6400;display:flex;align-items:center;justify-content:center"><x-ikon nama="beranda-jam" :ukuran="22" /></span>
            <div style="flex:1 1 280px;min-width:0"><div style="font-size:15px;line-height:21px;font-weight:700">Ada pesanan yang menunggu pembayaran</div><div style="font-size:13px;line-height:19px;font-weight:500;color:#7A5200">{{ $menunggu->nomor }} &middot; {{ F::rp($menunggu->total) }} &middot; batas bayar {{ F::sisaWaktu($menunggu->kedaluwarsa_at) }}</div></div>
            <a href="{{ route('konfirmasi-pembayaran', ['pesanan' => $menunggu->nomor]) }}" wire:navigate class="bl-btn bl-btn-p">Selesaikan pembayaran</a>
        </div>
    @endif

    <div style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(0,1fr);gap:24px;align-items:stretch">
        {{-- Paket saat ini --}}
        <section class="bl-card" aria-labelledby="h-aktif" style="padding:28px;display:flex;flex-direction:column;gap:18px">
            <div style="display:flex;align-items:center;gap:16px">
                <span aria-hidden="true" style="flex:none;width:56px;height:56px;border-radius:28px;background:#FFF4CC;color:#9A6400;display:flex;align-items:center;justify-content:center"><x-ikon nama="bintang" :ukuran="28" /></span>
                <div style="flex:1;min-width:0">
                    <div style="font-size:11px;line-height:14px;font-weight:800;letter-spacing:.7px;color:#1D4ED8">PAKET SAAT INI</div>
                    <h2 id="h-aktif" style="margin:2px 0 0;font-size:26px;line-height:32px;font-weight:700;letter-spacing:-0.01em">{{ $namaAktif }}</h2>
                </div>
                <span class="bl-chip" style="{{ $chipGaya }}">{{ $chipTeks }}</span>
            </div>
            <div style="font-size:14px;line-height:20px;font-weight:500;color:#454B55">
                @if ($berbayar)
                    {{ $info['sampai'] ? 'Aktif sampai '.F::tgl($info['sampai']).'. ' : 'Aktif tanpa batas waktu. ' }}Semua template dan tanpa logo undangyah.com.
                @elseif ($pernahBerbayar)
                    Masa aktif {{ $info['nama'] }} berakhir pada {{ F::tgl($info['sampai']) }}. Perpanjang agar fitur {{ $info['nama'] }} aktif lagi.
                @else
                    Tanpa batas waktu, dengan {{ $paketAktif?->kuota_undangan ?? 1 }} undangan aktif. Upgrade untuk template penuh, galeri lebih besar, dan tanpa logo.
                @endif
            </div>
            @if ($info['sisaHari'] !== null)
                <div style="display:flex;flex-direction:column;gap:8px">
                    <div style="display:flex;justify-content:space-between;font-size:12px;line-height:16px;font-weight:700;color:#666C76"><span>Sisa masa aktif</span><span>{{ $info['sisaHari'] }} hari</span></div>
                    <div role="progressbar" aria-label="Sisa masa aktif" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $persenWaktu }}" style="height:12px;border-radius:6px;background:#E2E5E9;overflow:hidden"><div style="height:100%;border-radius:6px;background:#1D4ED8;width:{{ $persenWaktu }}%"></div></div>
                </div>
            @endif
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:auto">
                @php $beli = $daftarPaket->first(fn ($p) => $p->dapatDibeli() && ! isset($bentrok[$p->kode])); @endphp
                @if ($beli && ! $berbayar)
                    <button type="button" wire:click="pilihPaket('{{ $beli->kode }}')" class="bl-btn bl-btn-p">{{ $pernahBerbayar ? 'Perpanjang ke '.$beli->nama : 'Upgrade ke '.$beli->nama }}</button>
                @endif
                <a href="#riwayat" class="bl-btn bl-btn-s">Lihat riwayat pesanan</a>
            </div>
            @if ($bentrok !== [] && $berbayar)
                <div style="{{ $subH2 }}">Paket yang lebih rendah tidak bisa dibeli selama {{ $namaAktif }} masih aktif. Add-on tetap bisa dibeli.</div>
            @endif
        </section>

        {{-- Kuota dan add-on --}}
        <section class="bl-card" aria-labelledby="h-kuota" style="padding:28px;display:flex;flex-direction:column;gap:16px">
            <h2 id="h-kuota" style="margin:0;font-size:18px;line-height:24px;font-weight:700;letter-spacing:-0.01em">Kuota dan add-on</h2>
            <div style="display:flex;flex-direction:column;gap:8px">
                <div style="display:flex;justify-content:space-between;align-items:baseline"><span style="font-size:13px;line-height:18px;font-weight:700">Undangan aktif</span><span style="font-size:14px;line-height:20px;font-weight:700">{{ $terpakai }} / {{ $batas ?? 'tanpa batas' }}</span></div>
                @if ($batas !== null)
                    <div role="progressbar" aria-label="Kuota undangan aktif" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $persenKuota }}" style="height:12px;border-radius:6px;background:#E2E5E9;overflow:hidden"><div style="height:100%;border-radius:6px;width:{{ $persenKuota }}%;background:{{ $persenKuota >= 100 ? '#FF9600' : '#58CC02' }}"></div></div>
                    <div style="{{ $subH2 }}">{{ $terpakai >= $batas ? 'Kuota penuh. Tambah kuota undangan lewat add-on atau upgrade paket.' : 'Sisa '.($batas - $terpakai).' undangan aktif.' }}</div>
                @endif
            </div>
            <div style="display:flex;justify-content:space-between;align-items:baseline"><span style="font-size:13px;line-height:18px;font-weight:700">Galeri per undangan</span><span style="font-size:14px;line-height:20px;font-weight:700">hingga {{ ($paketAktif?->kuota_foto) ?? 3 }} foto</span></div>
            <div style="border-top:2px solid #E2E5E9;padding-top:14px;display:flex;flex-direction:column;gap:8px">
                <span style="font-size:13px;line-height:18px;font-weight:700">Add-on aktif</span>
                @if ($milikList->isEmpty())
                    <div style="{{ $subH2 }}">Belum ada add-on yang dibeli.</div>
                @endif
                <div style="display:flex;flex-wrap:wrap;gap:6px">
                    @foreach ($milikList as $a)<span class="bl-chip" style="background:#E3ECFD;color:#1D4ED8">{{ $a->nama }}{{ $dimiliki[$a->kode] > 1 ? ' ×'.$dimiliki[$a->kode] : '' }}</span>@endforeach
                </div>
            </div>
        </section>
    </div>

    {{-- Bandingkan paket --}}
    <section aria-labelledby="h-paket" style="display:flex;flex-direction:column;gap:16px">
        <div><h2 id="h-paket" style="{{ $judulH2 }}">Bandingkan paket</h2><div style="{{ $subH2 }}">Harga sekali bayar. Pembayaran lewat transfer manual dan diverifikasi admin.</div></div>
        <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;align-items:stretch">
            @foreach ($daftarPaket as $p)
                @php
                    $sekarang = $p->kode === $aktif;
                    $bisaBeli = $p->dapatDibeli();
                @endphp
                <article wire:key="paket-{{ $p->kode }}" class="bl-card" style="padding:24px;display:flex;flex-direction:column;gap:16px;{{ $p->populer ? 'border-color:#1D4ED8;box-shadow:0 0 0 2px #1D4ED8,0 8px 24px rgba(29,78,216,0.12)' : '' }}">
                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;min-height:28px"><h3 style="margin:0;font-size:20px;line-height:26px;font-weight:700">{{ $p->nama }}</h3>
                        @if ($p->populer)<span class="bl-chip" style="background:#1D4ED8;color:#FFFFFF">POPULER</span>@endif
                        @if ($sekarang)<span class="bl-chip" style="background:#D7FFB8;color:#3C7A00">PAKET SAAT INI</span>@endif</div>
                    <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76;min-height:38px">{{ $p->tagline }}</div>
                    <div style="display:flex;align-items:baseline;gap:6px;flex-wrap:wrap">
                        @if ($p->harga_coret > $p->harga && $p->harga > 0)<span style="font-size:14px;line-height:20px;font-weight:500;color:#666C76;text-decoration:line-through">{{ F::rp($p->harga_coret) }}</span>@endif
                        <span style="font-size:28px;line-height:34px;font-weight:700;letter-spacing:-0.02em">{{ F::rp($p->harga) }}</span>
                        <span style="{{ $subH2 }}">{{ $p->harga > 0 ? 'sekali bayar, berlaku '.($p->durasi_bulan > 0 ? $p->durasi_bulan.' bulan' : 'selamanya') : 'selamanya' }}</span>
                    </div>
                    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px;flex:1">
                        @foreach ($p->fitur ?? [] as $f)
                            <li style="display:flex;gap:10px;align-items:flex-start;font-size:14px;line-height:20px;font-weight:500"><span aria-hidden="true" style="flex:none;margin-top:1px;color:#58A700"><x-ikon nama="centang" :ukuran="18" /></span><span>{{ $f }}</span></li>
                        @endforeach
                    </ul>
                    @if ($bisaBeli && isset($bentrok[$p->kode]))
                        <button type="button" class="bl-btn bl-btn-off" style="width:100%" disabled aria-describedby="bentrok-{{ $p->kode }}">Tidak tersedia</button>
                        <div id="bentrok-{{ $p->kode }}" style="font-size:13px;line-height:19px;font-weight:500;color:#666C76;text-align:center">{{ $bentrok[$p->kode] }}</div>
                    @elseif ($bisaBeli && (! $sekarang || $berbayar))
                        <button type="button" wire:click="pilihPaket('{{ $p->kode }}')" class="bl-btn {{ $sekarang ? 'bl-btn-s' : 'bl-btn-p' }}" style="width:100%">{{ $sekarang ? 'Perpanjang '.$p->durasi_bulan.' bulan' : ($pernahBerbayar ? 'Perpanjang ke '.$p->nama : 'Upgrade ke '.$p->nama) }}</button>
                    @elseif ($sekarang)
                        <button type="button" disabled class="bl-btn bl-btn-off" style="width:100%">Paket saat ini</button>
                    @elseif ($p->status === 'draf')
                        <button type="button" disabled class="bl-btn bl-btn-off" style="width:100%">Segera hadir</button>
                    @endif
                    @if ($p->harga === 0 && $berbayar)
                        <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76;text-align:center;min-height:44px;display:flex;align-items:center;justify-content:center">Berlaku otomatis setelah masa aktif {{ $info['nama'] }} berakhir.</div>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    {{-- Add-on --}}
    <section aria-labelledby="h-addon" style="display:flex;flex-direction:column;gap:16px">
        <div><h2 id="h-addon" style="{{ $judulH2 }}">Add-on</h2><div style="{{ $subH2 }}">Tambahan fitur tanpa harus ganti paket. Pilih lalu lanjut ke Checkout.</div></div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">
            @foreach ($addon as $a)
                @php
                    $status = $hitung->statusAddon($a, $h['efektif'], $dimiliki);
                    $dipilih = in_array($a->kode, array_column($h['item'], 'kode'), true);
                    $n = $dimiliki[$a->kode] ?? 0;
                @endphp
                <div wire:key="addon-{{ $a->kode }}" class="bl-card" style="padding:20px;display:flex;flex-direction:column;gap:12px;{{ $dipilih ? 'border-color:#1D4ED8;background:#F5F8FF' : '' }}">
                    <div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><span style="font-size:15px;line-height:20px;font-weight:700">{{ $a->nama }}</span><span style="flex:none;font-size:13px;line-height:20px;font-weight:700;color:#1D4ED8">{{ $a->hargaTampil() }}</span></div>
                    <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76;flex:1">{{ $a->deskripsi }}</div>
                    @if ($n > 0)<div style="font-size:12px;line-height:16px;font-weight:700;color:#3C7A00">{{ $a->multi ? 'Sudah dibeli '.$n.' kali' : 'Sudah aktif di akunmu' }}</div>@endif
                    @if ($status === 'termasuk')
                        <span class="bl-chip" style="background:#D7FFB8;color:#3C7A00;align-self:flex-start">Termasuk di paket</span>
                    @elseif ($status === 'terkunci')
                        <span class="bl-chip" style="background:#ECEEF1;color:#454B55;align-self:flex-start">Perlu paket Premium</span>
                    @endif
                    @if ($dipilih)
                        <button type="button" wire:click="ubahAddon('{{ $a->kode }}')" aria-pressed="true" class="bl-btn bl-btn-p" style="width:100%" aria-label="Batalkan {{ $a->nama }} dari keranjang"><x-ikon nama="centang" :ukuran="16" /> Dipilih</button>
                    @elseif ($status === 'bisa')
                        <button type="button" wire:click="ubahAddon('{{ $a->kode }}')" class="bl-btn bl-btn-s" style="width:100%" aria-label="Tambah {{ $a->nama }} ke keranjang"><x-ikon nama="tambah" :ukuran="16" /> Tambah</button>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Riwayat pesanan --}}
    <section id="riwayat" aria-labelledby="h-riw" class="bl-card" style="padding:28px;display:flex;flex-direction:column;gap:18px">
        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between">
            <div><h2 id="h-riw" style="{{ $judulH2 }}">Riwayat pesanan</h2><div style="{{ $subH2 }}">Pesanan dan invoice dari akun ini.</div></div>
            <div role="group" aria-label="Saring status pesanan" style="display:flex;flex-wrap:wrap;gap:8px">
                @foreach ($filterLabel as $kode => $label)
                    @php $on = $filter === $kode; @endphp
                    <button type="button" wire:click="setFilter('{{ $kode }}')" aria-pressed="{{ $on ? 'true' : 'false' }}" style="min-height:36px;padding:0 14px;border-radius:18px;font-size:13px;font-weight:700;border:2px solid {{ $on ? '#1D4ED8' : '#E2E5E9' }};background:{{ $on ? '#E3ECFD' : '#FFFFFF' }};color:{{ $on ? '#1D4ED8' : '#454B55' }}">{{ $label }} <span style="opacity:.8">{{ $hitungStatus[$kode] }}</span></button>
                @endforeach
            </div>
        </div>

        @if ($pesanan->isEmpty())
            <div style="border:2px dashed #E2E5E9;border-radius:20px;padding:36px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:10px">
                <span aria-hidden="true" style="width:56px;height:56px;border-radius:28px;background:#F4F5F7;color:#666C76;display:flex;align-items:center;justify-content:center"><x-ikon nama="billing-kwitansi" :ukuran="26" /></span>
                <div style="font-size:16px;line-height:22px;font-weight:700">{{ $hitungStatus['semua'] === 0 ? 'Belum ada pesanan' : 'Tidak ada pesanan dengan status ini' }}</div>
                <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76;max-width:420px">{{ $hitungStatus['semua'] === 0 ? 'Pesanan dari Checkout akan muncul di sini, lengkap dengan invoice yang bisa dicetak.' : 'Pilih status lain di atas untuk melihat pesanan.' }}</div>
            </div>
        @else
            <div style="overflow-x:auto">
                <table class="bl-tbl">
                    <caption class="sr-only">Daftar pesanan</caption>
                    <thead><tr><th scope="col">No. invoice</th><th scope="col">Tanggal</th><th scope="col">Item</th><th scope="col">Metode</th><th scope="col" style="text-align:right">Total</th><th scope="col">Status</th><th scope="col" style="text-align:right">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($pesanan as $p)
                            @php [$labelS, $gayaS] = F::status($p->statusEfektif()); @endphp
                            <tr class="bl-rowh" wire:key="p-{{ $p->id }}">
                                <td style="font-weight:700;white-space:nowrap">{{ $p->nomor }}</td>
                                <td style="white-space:nowrap;color:#454B55">{{ F::tgl($p->created_at) }}</td>
                                <td style="min-width:200px;max-width:320px">{{ $p->ringkasItem() }}</td>
                                <td style="color:#454B55">{{ $p->metode_label ?? '-' }}</td>
                                <td style="text-align:right;font-weight:700;white-space:nowrap">{{ F::rp($p->total) }}</td>
                                <td><span class="bl-chip" style="{{ $gayaS }}">{{ $labelS }}</span></td>
                                <td style="text-align:right;white-space:nowrap">
                                    @if ($p->menunggu())<a href="{{ route('konfirmasi-pembayaran', ['pesanan' => $p->nomor]) }}" wire:navigate class="bl-btn bl-btn-p" style="{{ $kecil }}">Bayar</a>@endif
                                    <button type="button" wire:click="bukaInvoice('{{ $p->nomor }}')" class="bl-btn bl-btn-s" style="{{ $kecil }}" aria-label="Lihat invoice {{ $p->nomor }}">Invoice</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($totalDisaring > $batasRingkas)
                <button type="button" wire:click="ubahSemua" class="bl-btn bl-btn-s" style="align-self:center">{{ $semua ? 'Tampilkan lebih sedikit' : 'Tampilkan semua ('.$totalDisaring.')' }}</button>
            @endif
        @endif
    </section>

    {{-- Data tagihan --}}
    <section aria-labelledby="h-tag" class="bl-card" style="padding:28px;display:flex;flex-direction:column;gap:18px">
        <div><h2 id="h-tag" style="{{ $judulH2 }}">Data tagihan</h2><div style="{{ $subH2 }}">Dipakai untuk mengisi otomatis kolom penagihan di Checkout dan tertera di invoice.</div></div>
        @if ($catatan !== '')
            <div role="status" aria-live="polite" style="border-radius:14px;padding:10px 14px;font-size:13px;line-height:19px;font-weight:600;{{ $catatanGalat ? 'background:#FFE0E0;color:#EA2B2B' : 'background:#D7FFB8;color:#3C7A00' }}">{{ $catatan }}</div>
        @endif
        <form wire:submit="simpanTagihan" style="display:contents">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px 20px">
                <div style="display:flex;flex-direction:column;gap:6px"><label for="b-nama" style="font-size:13px;line-height:18px;font-weight:700">Nama pada tagihan</label><input id="b-nama" type="text" autocomplete="name" maxlength="80" wire:model="nama" style="{{ F::gayaInput($errors->has('nama')) }}">@error('nama')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                <div style="display:flex;flex-direction:column;gap:6px"><label for="b-email" style="font-size:13px;line-height:18px;font-weight:700">Email tagihan</label><input id="b-email" type="email" autocomplete="email" maxlength="120" wire:model="email" style="{{ F::gayaInput($errors->has('email')) }}">@error('email')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                <div style="display:flex;flex-direction:column;gap:6px"><label for="b-hp" style="font-size:13px;line-height:18px;font-weight:700">No. HP <span style="font-weight:500;color:#666C76">(opsional)</span></label><input id="b-hp" type="tel" autocomplete="tel" maxlength="30" wire:model="hp" style="{{ F::gayaInput($errors->has('hp')) }}">@error('hp')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                <div style="display:flex;flex-direction:column;gap:6px"><label for="b-alamat" style="font-size:13px;line-height:18px;font-weight:700">Alamat <span style="font-weight:500;color:#666C76">(opsional)</span></label><input id="b-alamat" type="text" autocomplete="street-address" maxlength="200" wire:model="alamat" style="{{ F::gayaInput($errors->has('alamat')) }}">@error('alamat')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
            </div>
            <div><button type="submit" class="bl-btn bl-btn-p" wire:loading.attr="disabled" wire:target="simpanTagihan">Simpan data tagihan</button></div>
        </form>
    </section>

    {{-- Bilah keranjang --}}
    @if ($jumlahKeranjang > 0)
        <div role="region" aria-label="Keranjang" style="position:sticky;bottom:16px;z-index:5;box-sizing:border-box;background:#FFFFFF;border:2px solid #1D4ED8;border-radius:24px;box-shadow:0 8px 24px rgba(29,78,216,0.16);padding:14px 20px;display:flex;flex-wrap:wrap;align-items:center;gap:14px">
            <span aria-hidden="true" style="flex:none;width:44px;height:44px;border-radius:22px;background:#E3ECFD;color:#1D4ED8;display:flex;align-items:center;justify-content:center"><x-ikon nama="pb-label" :ukuran="22" /></span>
            <div style="flex:1 1 240px;min-width:0"><div style="font-size:14px;line-height:19px;font-weight:700">{{ collect($h['item'])->pluck('nama')->implode(', ') }}</div><div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">Total sebelum voucher dan biaya pembayaran</div></div>
            <div style="font-size:20px;line-height:26px;font-weight:700">{{ F::rp($h['subtotal']) }}</div>
            <button type="button" wire:click="kosongkanKeranjang" class="bl-btn bl-btn-s">Kosongkan</button>
            <a href="{{ route('checkout') }}" wire:navigate class="bl-btn bl-btn-p">Lanjut ke Checkout <x-ikon nama="panah-kanan" :ukuran="16" /></a>
        </div>
    @endif

    {{-- Dialog invoice --}}
    @if ($faktur)
        @php
            $io = $faktur;
            $statusIo = $io->statusEfektif();
            [$labelIo, $gayaIo] = F::status($statusIo);
        @endphp
        <div style="position:fixed;inset:0;z-index:50;background:rgba(38,42,49,0.5);display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box" wire:click="tutupInvoice" x-data x-on:keydown.escape.window="$wire.tutupInvoice()">
            <div role="dialog" aria-modal="true" aria-labelledby="inv-h" class="bl-dlg" x-trap.noscroll="true" wire:click.stop style="box-sizing:border-box;width:100%;max-width:600px;max-height:calc(100vh - 48px);overflow:auto;background:#FFFFFF;border-radius:24px;padding:28px;display:flex;flex-direction:column;gap:18px">
                <div style="display:flex;align-items:flex-start;gap:12px">
                    <div style="flex:1;min-width:0"><div style="font-size:11px;line-height:14px;font-weight:800;letter-spacing:.7px;color:#1D4ED8">INVOICE</div><h2 id="inv-h" style="margin:2px 0 0;font-size:22px;line-height:28px;font-weight:700">{{ $io->nomor }}</h2></div>
                    <span class="bl-chip" style="{{ $gayaIo }}">{{ $labelIo }}</span>
                    <button type="button" wire:click="tutupInvoice" aria-label="Tutup invoice" style="flex:none;width:44px;height:44px;border-radius:22px;border:2px solid #E2E5E9;display:flex;align-items:center;justify-content:center"><x-ikon nama="tutup" :ukuran="18" /></button>
                </div>
                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px 20px;font-size:13px;line-height:18px">
                    <div><div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">Tanggal pesanan</div><div style="font-weight:700">{{ F::tglJam($io->created_at) }}</div></div>
                    <div><div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">Metode</div><div style="font-weight:700">{{ $io->metode_label ?? '-' }}</div></div>
                    <div><div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">Pembeli</div><div style="font-weight:700">{{ $io->pembeli_nama ?: auth()->user()->name }}</div><div style="color:#454B55;word-break:break-word">{{ $io->pembeli_email ?: auth()->user()->email }}</div></div>
                    <div><div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">Status pembayaran</div><div style="font-weight:700">{{ $statusIo === 'lunas' && $io->dibayar_at ? 'Lunas, '.F::tglJam($io->dibayar_at) : ($statusIo === 'menunggu' ? 'Menunggu pembayaran, batas '.F::tglJam($io->kedaluwarsa_at) : $labelIo) }}</div></div>
                </div>
                <table class="bl-tbl"><caption class="sr-only">Rincian item</caption>
                    <thead><tr><th scope="col">Item</th><th scope="col" style="text-align:right">Harga</th></tr></thead>
                    <tbody>@foreach ($io->item as $it)<tr><td>{{ $it->namaTampil() }}</td><td style="text-align:right;white-space:nowrap">{{ F::rp($it->harga) }}</td></tr>@endforeach</tbody>
                </table>
                <div style="display:flex;flex-direction:column;gap:6px;font-size:14px;line-height:20px">
                    <div style="display:flex;justify-content:space-between"><span style="color:#454B55">Subtotal</span><span>{{ F::rp($io->subtotal) }}</span></div>
                    @if ($io->diskon > 0)<div style="display:flex;justify-content:space-between;color:#3C7A00"><span>Voucher {{ $io->voucher_kode }}</span><span>- {{ F::rp($io->diskon) }}</span></div>@endif
                    @if ($io->biaya > 0)<div style="display:flex;justify-content:space-between"><span style="color:#454B55">Biaya pembayaran</span><span>{{ F::rp($io->biaya) }}</span></div>@endif
                    @if ($io->kode_unik > 0)<div style="display:flex;justify-content:space-between"><span style="color:#454B55">Kode unik</span><span>{{ F::rp($io->kode_unik) }}</span></div>@endif
                    <div style="display:flex;justify-content:space-between;border-top:2px solid #E2E5E9;padding-top:10px;margin-top:4px;font-size:18px;line-height:24px;font-weight:700"><span>Total</span><span>{{ F::rp($io->total) }}</span></div>
                </div>
                <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">{{ config('app.name') }}. Dokumen ini bukan faktur pajak.</div>
                <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:flex-end">
                    @if ($statusIo === 'menunggu')<a href="{{ route('konfirmasi-pembayaran', ['pesanan' => $io->nomor]) }}" wire:navigate class="bl-btn bl-btn-p">Selesaikan pembayaran</a>@endif
                    <a href="{{ route('invoice', $io) }}" target="_blank" rel="noopener" class="bl-btn bl-btn-s"><x-ikon nama="pb-unduh" :ukuran="16" /> Cetak / PDF</a>
                    <button type="button" wire:click="tutupInvoice" class="bl-btn bl-btn-s">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
