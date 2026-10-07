@php
    use App\Support\FormatPapan as F;
    $kosong = $h['item'] === [];
    $baris = [];
    foreach ($h['item'] as $it) {
        $baris[] = ['label' => ($it['tipe'] === 'addon' ? 'Add-on: ' : '').$it['nama'], 'jumlah' => F::rp($it['harga']), 'gaya' => ''];
    }
    if ($kosong) {
        $baris[] = ['label' => 'Belum ada item', 'jumlah' => F::rp(0), 'gaya' => 'color:#666C76'];
    }
    $baris[] = ['label' => 'Subtotal', 'jumlah' => F::rp($h['subtotal']), 'gaya' => 'border-top:1px dashed #E2E5E9;padding-top:10px'];
    if ($h['diskon'] > 0) {
        $baris[] = ['label' => 'Voucher '.$h['voucher']->kode.' ('.$h['voucher_label'].')', 'jumlah' => '- '.F::rp($h['diskon']), 'gaya' => 'color:#3C7A00'];
    }
    if ($h['biaya'] > 0) {
        $baris[] = ['label' => 'Biaya pembayaran', 'jumlah' => F::rp($h['biaya']), 'gaya' => 'color:#666C76'];
    }
    if ($h['kode_unik'] > 0) {
        $baris[] = ['label' => 'Kode unik untuk verifikasi transfer', 'jumlah' => '+ '.F::rp($h['kode_unik']), 'gaya' => 'color:#666C76'];
    }
    $bisaBayar = ! $kosong && $paketDitolak === null && $metodeDaftar->isNotEmpty() && $h['metode'] !== null;
    $judulH2 = 'margin:0;font-size:18px;line-height:24px;font-weight:700;letter-spacing:-0.01em';
    $subH2 = 'font-size:13px;line-height:19px;font-weight:500;color:#666C76';
    $adaGalatKolom = $dicoba && ($errors->has('nama') || $errors->has('email') || $errors->has('hp') || $errors->has('alamat') || $errors->has('setuju'));
    $kecilS = 'min-height:36px;padding:0 12px;font-size:12px;flex:none';
@endphp
<x-billing.gaya />
<style>
.bc-grid{display:grid;grid-template-columns:minmax(0,1fr);gap:24px;align-items:start}
.bc-aside{min-width:0}
@media (min-width:1280px){.bc-grid{grid-template-columns:minmax(0,1fr) 340px}.bc-aside{position:sticky;top:24px}}
</style>
<div class="bl-root" style="box-sizing:border-box;width:100%;max-width:1136px;margin:0 auto;display:flex;flex-direction:column;gap:24px">
    <div class="bc-grid">
        <div style="display:flex;flex-direction:column;gap:20px;min-width:0">

            <section class="bl-card" aria-labelledby="c-item" style="padding:28px;display:flex;flex-direction:column;gap:18px">
                <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;justify-content:space-between">
                    <div><h2 id="c-item" style="{{ $judulH2 }}">Item pesanan</h2><div style="{{ $subH2 }}">{{ $paketSaatIni !== 'gratis' ? 'Paket '.ucfirst($paketSaatIni).' aktif'.($sampai ? ' sampai '.F::tgl($sampai) : '') : 'Kamu memakai paket Gratis.' }}</div></div>
                    <a href="{{ $tautanBilling }}" wire:navigate class="bl-btn bl-btn-s" style="min-height:36px;font-size:12px">Ubah di Paket &amp; Billing</a>
                </div>

                @if ($paketDitolak)
                    <div role="alert" style="display:flex;flex-wrap:wrap;align-items:flex-start;gap:12px;background:#FFE0E0;color:#EA2B2B;border-radius:18px;padding:12px 16px">
                        <div style="flex:1 1 240px;min-width:0"><div style="font-size:14px;line-height:19px;font-weight:700">{{ $paketDitolak['nama'] }} tidak bisa dibeli sekarang</div><div style="font-size:13px;line-height:19px;font-weight:500">{{ $paketDitolak['pesan'] }}</div></div>
                        <button type="button" wire:click="hapusPaket" class="bl-btn bl-btn-s" style="min-height:36px;font-size:12px">Hapus dari pesanan</button>
                    </div>
                @endif

                @if ($kosong)
                    <div style="border:2px dashed #E2E5E9;border-radius:20px;padding:28px 20px;text-align:center;display:flex;flex-direction:column;align-items:center;gap:10px">
                        <span aria-hidden="true" style="width:56px;height:56px;border-radius:28px;background:#F4F5F7;color:#666C76;display:flex;align-items:center;justify-content:center"><x-ikon nama="pb-label" :ukuran="26" /></span>
                        <div style="font-size:16px;line-height:22px;font-weight:700">Keranjang masih kosong</div>
                        <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76;max-width:380px">Tambahkan paket atau add-on di bawah, atau pilih dari halaman Paket &amp; Billing.</div>
                    </div>
                @else
                    <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:10px">
                        @foreach ($h['item'] as $it)
                            <li wire:key="item-{{ $it['tipe'] }}-{{ $it['kode'] }}" style="display:flex;align-items:center;gap:12px;border:2px solid #E2E5E9;border-radius:18px;padding:12px 14px">
                                <span aria-hidden="true" style="flex:none;width:40px;height:40px;border-radius:20px;background:#E3ECFD;color:#1D4ED8;display:flex;align-items:center;justify-content:center">{{ $it['tipe'] === 'paket' ? 'PK' : 'AD' }}</span>
                                <div style="flex:1;min-width:0">
                                    <div style="font-size:15px;line-height:20px;font-weight:700">{{ $it['tipe'] === 'addon' ? 'Add-on: ' : '' }}{{ $it['nama'] }}</div>
                                    <div style="font-size:13px;line-height:19px;font-weight:500;color:#666C76">
                                        @if ($it['tipe'] === 'paket')
                                            {{ $h['perpanjang'] ? 'Perpanjangan paket' : 'Berlaku' }}{{ ($it['efek']['durasi_bulan'] ?? 0) > 0 ? ($h['perpanjang'] ? ', berlaku ' : ' ').$it['efek']['durasi_bulan'].' bulan' : '' }}{{ $h['perpanjang'] ? '' : ' sejak pembayaran dikonfirmasi' }}
                                        @else
                                            Fitur tambahan
                                        @endif
                                    </div>
                                </div>
                                <div style="font-size:15px;line-height:20px;font-weight:700;white-space:nowrap">{{ F::rp($it['harga']) }}</div>
                                <button type="button" @if ($it['tipe'] === 'paket') wire:click="hapusPaket" @else wire:click="hapusAddon('{{ $it['kode'] }}')" @endif aria-label="Hapus {{ $it['nama'] }} dari pesanan" style="flex:none;width:44px;height:44px;border-radius:22px;color:#EA2B2B;display:flex;align-items:center;justify-content:center"><x-ikon nama="pb-sampah" :ukuran="18" /></button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($paketBisaDitambah)
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;background:#F5F8FF;border:2px solid #BFD3FF;border-radius:18px;padding:14px 16px">
                        <div style="flex:1 1 240px"><div style="font-size:14px;line-height:19px;font-weight:700">{{ $paketSaatIni === $paketBisaDitambah->kode ? 'Perpanjang' : 'Upgrade ke' }} {{ $paketBisaDitambah->nama }} &middot; {{ F::rp($paketBisaDitambah->harga) }}</div><div style="{{ $subH2 }}">{{ $paketBisaDitambah->tagline }}</div></div>
                        <button type="button" wire:click="tambahPaket('{{ $paketBisaDitambah->kode }}')" class="bl-btn bl-btn-p"><x-ikon nama="tambah" :ukuran="16" /> Tambah paket</button>
                    </div>
                @endif

                <div style="border-top:2px solid #E2E5E9;padding-top:16px;display:flex;flex-direction:column;gap:12px">
                    <div style="font-size:13px;line-height:18px;font-weight:700">Tambah add-on</div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px">
                        @foreach ($addon as $a)
                            @php $m = $a['model']; @endphp
                            <div wire:key="ad-{{ $m->kode }}" style="display:flex;align-items:center;gap:10px;border:2px solid {{ $a['dipilih'] ? '#1D4ED8' : '#E2E5E9' }};background:{{ $a['dipilih'] ? '#F5F8FF' : '#FFFFFF' }};border-radius:16px;padding:10px 12px">
                                <div style="flex:1;min-width:0"><div style="font-size:13px;line-height:18px;font-weight:700">{{ $m->nama }}</div><div style="font-size:12px;line-height:16px;font-weight:500;color:#666C76">{{ $m->hargaTampil() }} &middot; {{ $m->deskripsi }}</div></div>
                                @if ($a['dipilih'])
                                    <button type="button" wire:click="ubahAddon('{{ $m->kode }}')" aria-pressed="true" class="bl-btn bl-btn-p" style="{{ $kecilS }}" aria-label="Batalkan {{ $m->nama }}">Dipilih</button>
                                @elseif ($a['status'] === 'bisa')
                                    <button type="button" wire:click="ubahAddon('{{ $m->kode }}')" class="bl-btn bl-btn-s" style="{{ $kecilS }}" aria-label="Tambah {{ $m->nama }}">Tambah</button>
                                @else
                                    <span class="bl-chip" style="background:#ECEEF1;color:#454B55;flex:none">{{ ['termasuk' => 'Termasuk', 'dimiliki' => 'Sudah aktif', 'terkunci' => 'Perlu Premium'][$a['status']] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Voucher --}}
            <section class="bl-card" aria-labelledby="c-vc" style="padding:28px;display:flex;flex-direction:column;gap:14px">
                <h2 id="c-vc" style="{{ $judulH2 }}">Kode voucher</h2>
                @if ($voucher === '')
                    <form wire:submit="pakaiVoucher" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap">
                        <div style="flex:1 1 240px;display:flex;flex-direction:column;gap:6px"><label for="c-vin" class="sr-only">Kode voucher</label><input id="c-vin" type="text" autocomplete="off" autocapitalize="characters" maxlength="30" placeholder="Masukkan kode voucher" wire:model="vInput" style="{{ F::gayaInput($pesanVoucher !== '') }}">@if ($pesanVoucher)<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $pesanVoucher }}</div>@endif</div>
                        <button type="submit" class="bl-btn bl-btn-s" style="min-height:48px" wire:loading.attr="disabled" wire:target="pakaiVoucher">Pakai</button>
                    </form>
                @else
                    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-radius:18px;padding:12px 16px;{{ $h['voucher'] ? 'background:#D7FFB8;color:#3C7A00' : 'background:#FFF4CC;color:#9A6400' }}">
                        <span aria-hidden="true"><x-ikon nama="pb-label" :ukuran="20" /></span>
                        <div style="flex:1 1 200px;min-width:0"><div style="font-size:14px;line-height:19px;font-weight:700">{{ $voucher }}</div><div style="font-size:13px;line-height:18px;font-weight:600">{{ $h['voucher'] ? $h['voucher_label'].', hemat '.F::rp($h['diskon']) : $h['voucher_galat'] }}</div></div>
                        <button type="button" wire:click="hapusVoucher" class="bl-btn bl-btn-s" style="min-height:36px;font-size:12px">Hapus</button>
                    </div>
                @endif
            </section>

            {{-- Metode pembayaran --}}
            <section class="bl-card" aria-labelledby="c-met" style="padding:28px;display:flex;flex-direction:column;gap:14px">
                <div><h2 id="c-met" style="{{ $judulH2 }}">Metode pembayaran</h2><div style="{{ $subH2 }}">Pembayaran lewat transfer, lalu kamu mengunggah bukti. Admin memverifikasi dan mengaktifkan pesananmu.</div></div>
                <div role="radiogroup" aria-label="Metode pembayaran" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:12px">
                    @foreach ($metodeDaftar as $m)
                        @php $on = $h['metode']?->kode === $m->kode; @endphp
                        <button type="button" role="radio" aria-checked="{{ $on ? 'true' : 'false' }}" wire:click="pilihMetode('{{ $m->kode }}')" wire:key="mt-{{ $m->kode }}" style="display:flex;align-items:center;gap:12px;min-height:64px;box-sizing:border-box;padding:12px 14px;border-radius:18px;border:2px solid {{ $on ? '#1D4ED8' : '#E2E5E9' }};background:{{ $on ? '#E3ECFD' : '#FFFFFF' }}">
                            <span aria-hidden="true" style="flex:none;width:22px;height:22px;box-sizing:border-box;border-radius:11px;display:flex;align-items:center;justify-content:center;border:2px solid {{ $on ? '#1D4ED8' : '#AEB3BB' }};background:{{ $on ? '#1D4ED8' : '#FFFFFF' }}">@if ($on)<span style="width:8px;height:8px;border-radius:4px;background:#FFFFFF"></span>@endif</span>
                            <span style="flex:1;min-width:0;display:flex;flex-direction:column;gap:2px"><span style="font-size:14px;line-height:19px;font-weight:700">{{ $m->label }}</span><span style="font-size:12px;line-height:16px;font-weight:500;color:#666C76">{{ $m->keterangan }}</span></span>
                            <span style="flex:none;font-size:12px;line-height:16px;font-weight:700;color:#454B55">{{ $m->teksBiaya() }}</span>
                        </button>
                    @endforeach
                </div>
                @if ($metodeDaftar->isEmpty())
                    <div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">Belum ada metode pembayaran aktif. Hubungi layanan pelanggan.</div>
                @endif
            </section>

            {{-- Data penagihan --}}
            <section class="bl-card" aria-labelledby="c-bill" style="padding:28px;display:flex;flex-direction:column;gap:18px">
                <div><h2 id="c-bill" style="{{ $judulH2 }}">Data penagihan</h2><div style="{{ $subH2 }}">Tertera di invoice. Disimpan di akunmu untuk pesanan berikutnya.</div></div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px 20px">
                    <div style="display:flex;flex-direction:column;gap:6px"><label for="c-nama" style="font-size:13px;line-height:18px;font-weight:700">Nama lengkap</label><input id="c-nama" type="text" autocomplete="name" maxlength="80" wire:model="nama" style="{{ F::gayaInput($errors->has('nama')) }}">@error('nama')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                    <div style="display:flex;flex-direction:column;gap:6px"><label for="c-email" style="font-size:13px;line-height:18px;font-weight:700">Email</label><input id="c-email" type="email" autocomplete="email" maxlength="120" wire:model="email" style="{{ F::gayaInput($errors->has('email')) }}">@error('email')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                    <div style="display:flex;flex-direction:column;gap:6px"><label for="c-hp" style="font-size:13px;line-height:18px;font-weight:700">No. HP <span style="font-weight:500;color:#666C76">(opsional)</span></label><input id="c-hp" type="tel" autocomplete="tel" maxlength="30" wire:model="hp" style="{{ F::gayaInput($errors->has('hp')) }}">@error('hp')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                    <div style="display:flex;flex-direction:column;gap:6px"><label for="c-alamat" style="font-size:13px;line-height:18px;font-weight:700">Alamat <span style="font-weight:500;color:#666C76">(opsional)</span></label><input id="c-alamat" type="text" autocomplete="street-address" maxlength="200" wire:model="alamat" style="{{ F::gayaInput($errors->has('alamat')) }}">@error('alamat')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror</div>
                </div>
            </section>

            {{-- Persetujuan --}}
            <section class="bl-card" aria-labelledby="c-syarat" style="padding:24px 28px;display:flex;flex-direction:column;gap:10px">
                <h2 id="c-syarat" class="sr-only">Syarat dan ketentuan</h2>
                <div style="display:flex;align-items:flex-start;gap:12px;min-height:44px">
                    <button type="button" role="checkbox" id="c-setuju" aria-checked="{{ $setuju ? 'true' : 'false' }}" aria-labelledby="c-setuju-teks" wire:click="$toggle('setuju')" style="flex:none;width:24px;height:24px;box-sizing:border-box;border-radius:8px;display:flex;align-items:center;justify-content:center;margin-top:1px;border:2px solid {{ $errors->has('setuju') ? '#EA2B2B' : ($setuju ? '#1D4ED8' : '#AEB3BB') }};background:{{ $setuju ? '#1D4ED8' : '#FFFFFF' }};color:#FFFFFF">@if ($setuju)<x-ikon nama="centang" :ukuran="16" />@endif</button>
                    <span id="c-setuju-teks" style="font-size:14px;line-height:21px;font-weight:500">Saya menyetujui @if ($tautanSyarat)<a href="{{ $tautanSyarat }}" wire:navigate style="font-weight:700;color:#1D4ED8">Syarat &amp; Ketentuan</a>@else Syarat &amp; Ketentuan @endif undangyah.com dan memahami bahwa pesanan aktif setelah admin memverifikasi bukti transfer.</span>
                </div>
                @error('setuju')<div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">{{ $message }}</div>@enderror
            </section>
        </div>

        {{-- Ringkasan --}}
        <aside aria-labelledby="c-sum" class="bl-card bc-aside" style="padding:28px;display:flex;flex-direction:column;gap:16px">
            <h2 id="c-sum" style="{{ $judulH2 }}">Ringkasan pesanan</h2>
            <div style="display:flex;flex-direction:column;gap:10px">
                @foreach ($baris as $b)
                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;line-height:20px;{{ $b['gaya'] }}"><span style="min-width:0">{{ $b['label'] }}</span><span style="white-space:nowrap;font-weight:600">{{ $b['jumlah'] }}</span></div>
                @endforeach
            </div>
            <div style="border-top:2px solid #E2E5E9;padding-top:14px;display:flex;justify-content:space-between;align-items:baseline"><span style="font-size:15px;line-height:20px;font-weight:700">{{ $h['kode_unik'] > 0 ? 'Total transfer' : 'Total bayar' }}</span><span style="font-size:26px;line-height:32px;font-weight:700;letter-spacing:-0.02em">{{ F::rp($h['total']) }}</span></div>
            @if ($h['diskon'] > 0)
                <div style="background:#D7FFB8;color:#3C7A00;border-radius:14px;padding:8px 12px;font-size:13px;line-height:18px;font-weight:700">Kamu hemat {{ F::rp($h['diskon']) }} dengan voucher</div>
            @endif
            @if ($galat)
                <div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B;background:#FFE0E0;border-radius:14px;padding:10px 12px">{{ $galat }}</div>
            @endif
            @if ($adaGalatKolom)
                <div role="alert" style="font-size:12px;line-height:16px;font-weight:600;color:#EA2B2B">Lengkapi kolom yang ditandai merah sebelum membayar.</div>
            @endif
            @if ($bisaBayar)
                <button type="button" wire:click="bayar" wire:loading.attr="disabled" wire:target="bayar" class="bl-btn bl-btn-p" style="min-height:52px;width:100%"><span wire:loading.remove wire:target="bayar">Bayar sekarang</span><span wire:loading wire:target="bayar">Membuat pesanan…</span></button>
            @else
                <button type="button" disabled class="bl-btn bl-btn-off" style="min-height:52px;width:100%">{{ $kosong ? 'Belum ada item' : 'Bayar sekarang' }}</button>
            @endif
            <div style="font-size:12px;line-height:17px;font-weight:500;color:#666C76">Setelah pesanan dibuat, kamu mendapat instruksi transfer dan bisa mengunggah bukti pembayaran. Pesanan kedaluwarsa bila belum dibayar sebelum batas waktu.</div>
            <a href="{{ route('hubungi') }}" wire:navigate style="font-size:13px;line-height:18px;font-weight:700;color:#1D4ED8">Butuh bantuan? Hubungi layanan</a>
        </aside>
    </div>
</div>
