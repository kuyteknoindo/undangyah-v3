@php
    $rp = fn (int|float $n): string => 'Rp '.number_format($n, 0, ',', '.');
    $tgl = fn ($t) => $t?->locale('id')->translatedFormat('j F Y, H.i').' WIB';
    [$labelStatus] = $pesanan->infoStatus();
    $status = $pesanan->statusEfektif();
    // Admin yang membuka invoice milik pengguna lain kembali ke daftar Invoices, bukan ke Billing miliknya.
    $adminLihat = auth()->user()?->isAdmin() && $pesanan->user_id !== auth()->id() && Route::has('admin.invoices');
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Invoice {{ $pesanan->nomor }} · {{ config('app.name') }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f4f5f7; color: #262a31; font-family: -apple-system, 'Segoe UI', Inter, system-ui, sans-serif; font-size: 14px; line-height: 1.5; }
        .aksi { display: flex; flex-wrap: wrap; gap: 8px; justify-content: space-between; max-width: 720px; margin: 20px auto 0; padding: 0 16px; }
        .tombol { display: inline-flex; align-items: center; min-height: 38px; padding: 0 14px; border-radius: 10px; border: 1px solid #d9dce1; background: #fff; color: #262b33; font: inherit; font-weight: 600; text-decoration: none; cursor: pointer; }
        .tombol.utama { background: #1d4ed8; border-color: #1d4ed8; color: #fff; }
        .kertas { max-width: 720px; margin: 16px auto 40px; padding: 40px; background: #fff; border: 1px solid #e6e8ec; border-radius: 16px; }
        h1 { margin: 0 0 4px; font-size: 22px; }
        .m { color: #666c76; font-size: 13px; }
        .cap { display: inline-block; border: 2px solid #262a31; border-radius: 8px; padding: 2px 10px; font-weight: 700; font-size: 12px; letter-spacing: .06em; text-transform: uppercase; }
        .g { display: flex; flex-wrap: wrap; gap: 24px 40px; margin: 18px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td, th { padding: 10px 6px; border-bottom: 1px solid #e2e5e9; text-align: left; vertical-align: top; }
        th { font-size: 11px; letter-spacing: .06em; text-transform: uppercase; color: #666c76; }
        .r { text-align: right; white-space: nowrap; }
        .ln { display: flex; justify-content: space-between; gap: 16px; padding: 4px 6px; }
        .tot { font-weight: 700; font-size: 18px; border-top: 2px solid #262a31; margin-top: 8px; padding-top: 10px; }
        @media (max-width: 560px) { .kertas { padding: 20px; margin: 12px 8px 32px; } }
        @media print {
            body { background: #fff; }
            .aksi { display: none; }
            .kertas { border: 0; margin: 0; padding: 0; max-width: none; border-radius: 0; }
        }
    </style>
</head>
<body>
    <div class="aksi">
        <a class="tombol" href="{{ $adminLihat ? route('admin.invoices') : route('billing') }}">&larr; {{ $adminLihat ? 'Invoices' : 'Paket & Billing' }}</a>
        <button type="button" class="tombol utama" onclick="window.print()">Cetak / simpan PDF</button>
    </div>
    <main class="kertas">
        <h1>INVOICE {{ $pesanan->nomor }}</h1>
        <div class="m">{{ config('app.name') }} &middot; undangyah.com</div>
        <p><span class="cap">{{ $labelStatus }}</span></p>
        <div class="g">
            <div><div class="m">Tanggal pesanan</div>{{ $tgl($pesanan->created_at) }}</div>
            <div><div class="m">Pembeli</div>{{ $pesanan->pembeli_nama }}<br>{{ $pesanan->pembeli_email }}@if ($pesanan->pembeli_hp)<br>{{ $pesanan->pembeli_hp }}@endif @if ($pesanan->pembeli_alamat)<br>{{ $pesanan->pembeli_alamat }}@endif</div>
            <div><div class="m">Metode</div>{{ $pesanan->metode_label ?? '-' }}</div>
        </div>
        <table>
            <caption class="m" style="text-align:left;caption-side:top">Rincian item</caption>
            <thead><tr><th>Item</th><th class="r">Harga</th></tr></thead>
            <tbody>
                @foreach ($pesanan->item as $it)
                    <tr><td>{{ $it->namaTampil() }}</td><td class="r">{{ $rp($it->harga) }}</td></tr>
                @endforeach
            </tbody>
        </table>
        <div class="ln"><span>Subtotal</span><span>{{ $rp($pesanan->subtotal) }}</span></div>
        @if ($pesanan->diskon > 0)<div class="ln"><span>Voucher {{ $pesanan->voucher_kode }}</span><span>- {{ $rp($pesanan->diskon) }}</span></div>@endif
        @if ($pesanan->biaya > 0)<div class="ln"><span>Biaya pembayaran</span><span>{{ $rp($pesanan->biaya) }}</span></div>@endif
        @if ($pesanan->kode_unik > 0)<div class="ln"><span>Kode unik</span><span>{{ $rp($pesanan->kode_unik) }}</span></div>@endif
        <div class="ln tot"><span>Total</span><span>{{ $rp($pesanan->total) }}</span></div>
        <p class="m">
            @if ($status === 'lunas' && $pesanan->dibayar_at)Dibayar pada {{ $tgl($pesanan->dibayar_at) }}.
            @elseif ($status === 'menunggu')Menunggu pembayaran, batas bayar {{ $tgl($pesanan->kedaluwarsa_at) }}.
            @endif
            Dokumen ini bukan faktur pajak.
        </p>
    </main>
</body>
</html>
