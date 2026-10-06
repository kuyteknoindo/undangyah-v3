<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $judulHalaman }}</title>
    <meta name="description" content="{{ $meta['deskripsi'] }}">
    <link rel="canonical" href="{{ $meta['url'] }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $meta['situs'] }}">
    <meta property="og:locale" content="id_ID">
    <meta property="og:title" content="{{ $meta['judul'] }}">
    <meta property="og:description" content="{{ $meta['deskripsi'] }}">
    <meta property="og:image" content="{{ $meta['gambar'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['judul'] }}">
    <meta name="twitter:description" content="{{ $meta['deskripsi'] }}">
    <meta name="twitter:image" content="{{ $meta['gambar'] }}">
    <meta name="theme-color" content="{{ $latar }}">
    <link rel="stylesheet" href="/undangan/tamu.css?v={{ @filemtime(public_path('undangan/tamu.css')) }}">
</head>
<body style="--uy-latar: {{ $latar }}">
<main class="uyp-kolom" id="uyp-halaman">
    {!! $html !!}
</main>

<button type="button" class="uyp-musik" id="uyp-musik" hidden aria-pressed="false" aria-label="Putar musik latar">
    <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
    <span class="uyp-musik-garis" aria-hidden="true"></span>
</button>

<div class="uyp-lembar" id="uyp-lembar" hidden>
    <button type="button" class="uyp-latar" data-uyp-tutup aria-label="Tutup"></button>
    <section class="uyp-panel" role="dialog" aria-modal="true" aria-labelledby="uyp-judul" tabindex="-1">
        <div class="uyp-kepala">
            <h2 id="uyp-judul"></h2>
            <button type="button" class="uyp-x" data-uyp-tutup aria-label="Tutup">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
            </button>
        </div>

        <form id="uyp-form" novalidate autocomplete="off">
            <div class="uyp-baris">
                <label for="uyp-nama">Nama Anda</label>
                <input id="uyp-nama" name="nama" type="text" maxlength="60" autocomplete="name" required>
            </div>
            <div class="uyp-baris" id="uyp-blok-status">
                <span id="uyp-lbl-status">Apakah Anda hadir?</span>
                <div class="uyp-pilih" role="group" aria-labelledby="uyp-lbl-status">
                    <button type="button" data-status="hadir" aria-pressed="true">Hadir</button>
                    <button type="button" data-status="tidak" aria-pressed="false">Tidak hadir</button>
                </div>
            </div>
            <div class="uyp-baris uyp-jumlah" id="uyp-blok-jumlah">
                <span id="uyp-lbl-jumlah">Jumlah tamu</span>
                <div class="uyp-stepper" role="group" aria-labelledby="uyp-lbl-jumlah">
                    <button type="button" id="uyp-kurang" aria-label="Kurangi">&minus;</button>
                    <output id="uyp-jumlah" aria-live="polite">1</output>
                    <button type="button" id="uyp-tambah" aria-label="Tambah">+</button>
                </div>
            </div>
            <div class="uyp-baris">
                <label for="uyp-pesan" id="uyp-lbl-pesan">Ucapan &amp; doa</label>
                <textarea id="uyp-pesan" name="pesan" rows="4" maxlength="500"></textarea>
                <small class="uyp-hitung" id="uyp-hitung" aria-hidden="true">0/500</small>
            </div>
            <div class="uyp-sarang" aria-hidden="true">
                <label for="uyp-situs">Situs web</label>
                <input id="uyp-situs" name="situs_web" type="text" tabindex="-1" autocomplete="off">
            </div>
            <div class="uyp-galat" id="uyp-galat" role="alert" hidden></div>
            <button type="submit" class="uyp-kirim" id="uyp-kirim">Kirim</button>
        </form>

        <div class="uyp-selesai" id="uyp-selesai" role="status" hidden>
            <div class="uyp-selesai-judul" id="uyp-selesai-judul"></div>
            <div id="uyp-selesai-isi"></div>
            <button type="button" class="uyp-kirim uyp-sekunder" data-uyp-tutup>Tutup</button>
        </div>

        <div class="uyp-ucapan" id="uyp-ucapan" hidden>
            <div class="uyp-ucapan-judul">Ucapan dari tamu</div>
            <div id="uyp-ucapan-isi"></div>
        </div>
    </section>
</div>

<script type="application/json" id="uyp-konfig">{!! json_encode($konfig, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="/undangan/tamu.js?v={{ @filemtime(public_path('undangan/tamu.js')) }}" defer></script>
</body>
</html>
