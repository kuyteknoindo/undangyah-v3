@php
    $rp = fn (int|float $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $kosong = $h['item'] === [];
    $baris = [];
    foreach ($h['item'] as $it) {
        $baris[] = ['label' => ($it['tipe'] === 'addon' ? 'Add-on: ' : '').$it['nama'], 'jumlah' => $rp($it['harga']), 'gaya' => ''];
    }
    if ($kosong) {
        $baris[] = ['label' => 'Belum ada item', 'jumlah' => $rp(0), 'gaya' => 'text-muted'];
    }
    $baris[] = ['label' => 'Subtotal', 'jumlah' => $rp($h['subtotal']), 'gaya' => 'border-t border-dashed border-line pt-2.5'];
    if ($h['diskon'] > 0) {
        $baris[] = ['label' => 'Voucher '.$h['voucher']->kode.' ('.$h['voucher_label'].')', 'jumlah' => '- '.$rp($h['diskon']), 'gaya' => 'text-success'];
    }
    if ($h['biaya'] > 0) {
        $baris[] = ['label' => 'Biaya pembayaran', 'jumlah' => $rp($h['biaya']), 'gaya' => 'text-muted'];
    }
    if ($h['kode_unik'] > 0) {
        $baris[] = ['label' => 'Kode unik untuk verifikasi transfer', 'jumlah' => '+ '.$rp($h['kode_unik']), 'gaya' => 'text-muted'];
    }
    $bisaBayar = ! $kosong && $paketDitolak === null && $metodeDaftar->isNotEmpty() && $h['metode'] !== null;
@endphp
<div class="mx-auto flex max-w-6xl flex-col gap-5">
    @if ($galat)
        <div role="alert" class="flex items-start gap-2 rounded-xl bg-danger-50 px-4 py-3 text-sm font-medium text-danger"><x-ikon nama="peringatan" :ukuran="18" class="mt-0.5" /> {{ $galat }}</div>
    @endif

    <div class="grid items-start gap-5 grid-cols-[minmax(0,1fr)] xl:grid-cols-[minmax(0,1fr)_360px]">
        <div class="flex min-w-0 flex-col gap-5">
            {{-- Item pesanan --}}
            <section aria-labelledby="c-item" class="kartu flex min-w-0 flex-col gap-4 p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="c-item" class="h2">Item pesanan</h2>
                        <div class="sub">{{ $paketSaatIni !== 'gratis' ? 'Paket '.ucfirst($paketSaatIni).' aktif'.($sampai ? ' sampai '.$sampai->locale('id')->translatedFormat('j M Y') : '') : 'Kamu memakai paket Gratis.' }}</div>
                    </div>
                    <a href="{{ $tautanBilling }}" wire:navigate class="btn btn-secondary btn-sm">Ubah di Paket &amp; Billing</a>
                </div>

                @if ($paketDitolak)
                    <div role="alert" class="flex flex-wrap items-start gap-3 rounded-xl bg-danger-50 px-4 py-3 text-danger">
                        <x-ikon nama="peringatan" :ukuran="18" class="mt-0.5 shrink-0" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm leading-5 font-semibold">{{ $paketDitolak['nama'] }} tidak bisa dibeli sekarang</div>
                            <div class="text-[13px] leading-[18px]">{{ $paketDitolak['pesan'] }}</div>
                        </div>
                        <button type="button" wire:click="hapusPaket" class="btn btn-secondary btn-sm">Hapus dari pesanan</button>
                    </div>
                @endif

                @if ($kosong)
                    <x-kosong judul="Keranjang masih kosong" teks="Tambahkan paket atau add-on di bawah, atau pilih dari halaman Paket & Billing." ikon="billing-keranjang" class="rounded-xl border border-dashed border-line-strong" />
                @else
                    <ul class="m-0 flex list-none flex-col gap-2 p-0">
                        @foreach ($h['item'] as $it)
                            <li wire:key="item-{{ $it['tipe'] }}-{{ $it['kode'] }}" class="flex items-center gap-3 rounded-xl border border-line px-3.5 py-3">
                                <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-xs font-bold text-brand">{{ $it['tipe'] === 'paket' ? 'PK' : 'AD' }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-sm leading-5 font-semibold text-ink">{{ $it['tipe'] === 'addon' ? 'Add-on: ' : '' }}{{ $it['nama'] }}</div>
                                    <div class="text-[13px] leading-[18px] text-muted">
                                        @if ($it['tipe'] === 'paket')
                                            {{ $h['perpanjang'] ? 'Perpanjangan paket' : 'Berlaku' }}{{ ($it['efek']['durasi_bulan'] ?? 0) > 0 ? ', '.$it['efek']['durasi_bulan'].' bulan' : '' }}{{ $h['perpanjang'] ? '' : ' sejak pembayaran dikonfirmasi' }}
                                        @else
                                            Fitur tambahan
                                        @endif
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-ink tabular-nums">{{ $rp($it['harga']) }}</div>
                                @if ($it['tipe'] === 'paket')
                                    <button type="button" wire:click="hapusPaket" class="btn-icon" aria-label="Hapus {{ $it['nama'] }} dari pesanan"><x-ikon nama="tutup" :ukuran="16" /></button>
                                @else
                                    <button type="button" wire:click="hapusAddon('{{ $it['kode'] }}')" class="btn-icon" aria-label="Hapus {{ $it['nama'] }} dari pesanan"><x-ikon nama="tutup" :ukuran="16" /></button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($paketBisaDitambah)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl bg-brand-50 px-4 py-3">
                        <div class="min-w-0">
                            <div class="text-sm leading-5 font-semibold text-brand">{{ $paketSaatIni === $paketBisaDitambah->kode ? 'Perpanjang' : 'Upgrade ke' }} {{ $paketBisaDitambah->nama }} &middot; {{ $rp($paketBisaDitambah->harga) }}</div>
                            <div class="text-[13px] leading-[18px] text-muted">{{ $paketBisaDitambah->tagline }}</div>
                        </div>
                        <button type="button" wire:click="tambahPaket('{{ $paketBisaDitambah->kode }}')" class="btn btn-primary btn-sm"><x-ikon nama="tambah" :ukuran="16" /> Tambah paket</button>
                    </div>
                @endif

                <div class="flex flex-col gap-2">
                    <div class="text-[13px] leading-[18px] font-semibold text-ink">Tambah add-on</div>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                        @foreach ($addon as $a)
                            @php $m = $a['model']; @endphp
                            <div wire:key="ad-{{ $m->kode }}" class="flex min-w-0 items-center gap-2.5 rounded-xl border px-3 py-2.5 {{ $a['dipilih'] ? 'border-brand bg-brand-50/60' : 'border-line' }}">
                                <div class="min-w-0 flex-1">
                                    <div class="truncate text-[13px] leading-[18px] font-semibold text-ink">{{ $m->nama }}</div>
                                    <div class="truncate text-xs leading-4 text-muted">{{ $m->hargaTampil() }} &middot; {{ $m->deskripsi }}</div>
                                </div>
                                @if ($a['dipilih'])
                                    <button type="button" wire:click="ubahAddon('{{ $m->kode }}')" class="btn btn-primary btn-sm" aria-label="Batalkan {{ $m->nama }}"><x-ikon nama="centang" :ukuran="14" /> Dipilih</button>
                                @elseif ($a['status'] === 'bisa')
                                    <button type="button" wire:click="ubahAddon('{{ $m->kode }}')" class="btn btn-secondary btn-sm" aria-label="Tambah {{ $m->nama }}">Tambah</button>
                                @else
                                    <span class="tag">{{ ['termasuk' => 'Termasuk', 'dimiliki' => 'Sudah aktif', 'terkunci' => 'Perlu paket berbayar'][$a['status']] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Voucher --}}
            <section aria-labelledby="c-vc" class="kartu flex flex-col gap-3 p-5">
                <h2 id="c-vc" class="h2">Kode voucher</h2>
                @if ($voucher === '')
                    <form wire:submit="pakaiVoucher" class="flex items-start gap-2.5">
                        <div class="min-w-0 flex-1">
                            <label for="c-vin" class="sr-only">Kode voucher</label>
                            <input id="c-vin" type="text" wire:model="vInput" class="inp uppercase" placeholder="Masukkan kode voucher" autocomplete="off" maxlength="30" @if ($pesanVoucher) aria-invalid="true" aria-describedby="c-vin-galat" @endif>
                            @if ($pesanVoucher)<div id="c-vin-galat" role="alert" class="galat mt-1.5">{{ $pesanVoucher }}</div>@endif
                        </div>
                        <button type="submit" class="btn btn-secondary" wire:loading.attr="disabled" wire:target="pakaiVoucher">Pakai</button>
                    </form>
                @else
                    <div class="flex items-center gap-3 rounded-xl px-4 py-3 {{ $h['voucher'] ? 'bg-success-50 text-success' : 'bg-warning-50 text-warning' }}">
                        <x-ikon :nama="$h['voucher'] ? 'centang' : 'peringatan'" :ukuran="18" />
                        <div class="min-w-0 flex-1">
                            <div class="text-sm leading-5 font-semibold">{{ $voucher }}</div>
                            <div class="text-[13px] leading-[18px]">{{ $h['voucher'] ? $h['voucher_label'].', hemat '.$rp($h['diskon']) : $h['voucher_galat'] }}</div>
                        </div>
                        <button type="button" wire:click="hapusVoucher" class="btn btn-secondary btn-sm">Hapus</button>
                    </div>
                @endif
            </section>

            {{-- Metode pembayaran --}}
            <section aria-labelledby="c-met" class="kartu flex flex-col gap-3 p-5">
                <div>
                    <h2 id="c-met" class="h2">Metode pembayaran</h2>
                    <div class="sub">Pembayaran lewat transfer, lalu kamu mengunggah bukti. Admin memverifikasi dan mengaktifkan pesananmu.</div>
                </div>
                <div role="radiogroup" aria-label="Metode pembayaran" class="flex flex-col gap-2">
                    @foreach ($metodeDaftar as $m)
                        @php $on = $h['metode']?->kode === $m->kode; @endphp
                        <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="pilihMetode('{{ $m->kode }}')" wire:key="mt-{{ $m->kode }}"
                                class="flex min-h-16 items-center gap-3 rounded-xl border-2 px-3.5 py-3 text-left {{ $on ? 'border-brand bg-brand-100' : 'border-line bg-white hover:bg-gray-50' }}">
                            <span class="flex size-[22px] shrink-0 items-center justify-center rounded-full border-2 {{ $on ? 'border-brand bg-brand' : 'border-[#aeb3bb] bg-white' }}">@if ($on)<span class="size-2 rounded-full bg-white"></span>@endif</span>
                            <span class="flex min-w-0 flex-1 flex-col"><span class="text-sm leading-5 font-semibold text-ink">{{ $m->label }}</span><span class="text-[13px] leading-[18px] text-muted">{{ $m->keterangan }}</span></span>
                            <span class="text-[13px] leading-[18px] font-semibold text-nav">{{ $m->teksBiaya() }}</span>
                        </button>
                    @endforeach
                </div>
                @if ($metodeDaftar->isEmpty())
                    <div role="alert" class="galat">Belum ada metode pembayaran aktif. Hubungi layanan pelanggan.</div>
                @endif
            </section>

            {{-- Data penagihan --}}
            <section aria-labelledby="c-bill" class="kartu flex flex-col gap-3 p-5">
                <div>
                    <h2 id="c-bill" class="h2">Data penagihan</h2>
                    <div class="sub">Tertera di invoice. Disimpan di akunmu untuk pesanan berikutnya.</div>
                </div>
                <div class="grid gap-3.5 sm:grid-cols-2">
                    <div>
                        <label for="c-nama" class="lbl">Nama lengkap</label>
                        <input id="c-nama" type="text" wire:model="nama" class="inp" autocomplete="name" maxlength="80" @error('nama') aria-invalid="true" aria-describedby="c-nama-g" @enderror>
                        @error('nama')<div id="c-nama-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="c-email" class="lbl">Email</label>
                        <input id="c-email" type="email" wire:model="email" class="inp" autocomplete="email" maxlength="120" @error('email') aria-invalid="true" aria-describedby="c-email-g" @enderror>
                        @error('email')<div id="c-email-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="c-hp" class="lbl">No. HP <span class="font-normal text-muted">(opsional)</span></label>
                        <input id="c-hp" type="tel" wire:model="hp" class="inp" autocomplete="tel" maxlength="30" @error('hp') aria-invalid="true" aria-describedby="c-hp-g" @enderror>
                        @error('hp')<div id="c-hp-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label for="c-alamat" class="lbl">Alamat <span class="font-normal text-muted">(opsional)</span></label>
                        <input id="c-alamat" type="text" wire:model="alamat" class="inp" autocomplete="street-address" maxlength="200" @error('alamat') aria-invalid="true" aria-describedby="c-alamat-g" @enderror>
                        @error('alamat')<div id="c-alamat-g" role="alert" class="galat mt-1.5">{{ $message }}</div>@enderror
                    </div>
                </div>
            </section>

            {{-- Persetujuan --}}
            <section aria-labelledby="c-syarat" class="kartu flex flex-col gap-3 p-5">
                <h2 id="c-syarat" class="h2">Syarat dan ketentuan</h2>
                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox" wire:model="setuju" class="mt-0.5 size-5 shrink-0 rounded border-line-strong accent-[#1D4ED8]" @error('setuju') aria-invalid="true" @enderror>
                    <span class="text-sm leading-5 text-body">Saya menyetujui @if ($tautanSyarat)<a href="{{ $tautanSyarat }}" wire:navigate class="font-semibold text-brand">Syarat &amp; Ketentuan</a>@else Syarat &amp; Ketentuan @endif undangyah.com dan memahami bahwa pesanan aktif setelah admin memverifikasi bukti transfer.</span>
                </label>
                @error('setuju')<div role="alert" class="galat">{{ $message }}</div>@enderror
            </section>
        </div>

        {{-- Ringkasan --}}
        <aside aria-labelledby="c-sum" class="kartu flex flex-col gap-4 p-5 xl:sticky xl:top-24">
            <h2 id="c-sum" class="h2">Ringkasan pesanan</h2>
            <div class="flex flex-col gap-2.5 text-sm leading-5">
                @foreach ($baris as $b)
                    <div class="flex items-start justify-between gap-3 {{ $b['gaya'] }}"><span class="min-w-0">{{ $b['label'] }}</span><span class="shrink-0 font-medium tabular-nums">{{ $b['jumlah'] }}</span></div>
                @endforeach
            </div>
            <div class="flex items-baseline justify-between gap-3 border-t border-line pt-3">
                <span class="text-sm leading-5 font-semibold text-ink">{{ $h['kode_unik'] > 0 ? 'Total transfer' : 'Total bayar' }}</span>
                <span class="text-xl leading-7 font-bold text-ink tabular-nums">{{ $rp($h['total']) }}</span>
            </div>
            @if ($h['diskon'] > 0)
                <div class="rounded-lg bg-success-50 px-3 py-2 text-[13px] leading-[18px] font-medium text-success">Kamu hemat {{ $rp($h['diskon']) }} dengan voucher</div>
            @endif
            @if ($dicoba && $errors->any())
                <div role="alert" class="galat">Lengkapi kolom yang ditandai merah sebelum membayar.</div>
            @endif
            <button type="button" wire:click="bayar" wire:loading.attr="disabled" wire:target="bayar" class="btn btn-primary btn-lg" @disabled(! $bisaBayar)>
                <span wire:loading.remove wire:target="bayar">{{ $kosong ? 'Belum ada item' : 'Buat pesanan' }}</span>
                <span wire:loading wire:target="bayar">Membuat pesanan…</span>
            </button>
            <div class="hint">Setelah pesanan dibuat, kamu mendapat instruksi transfer dan bisa mengunggah bukti pembayaran. Pesanan kedaluwarsa bila belum dibayar sebelum batas waktu.</div>
        </aside>
    </div>
</div>
