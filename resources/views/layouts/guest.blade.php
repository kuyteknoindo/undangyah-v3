<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <x-meta-brand :og="true" :judul="($judul ?? 'Masuk').' · '.config('app.name')" />
    <title>{{ $judul ?? 'Masuk' }} · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Gaya halaman auth: nilai persis dari papan Login/Register/LupaSandi (kelas berawalan au-). --}}
    <style>
        .au-halaman { min-height: 100vh; box-sizing: border-box; display: flex; flex-direction: column; align-items: center; padding: 40px 24px 28px; color: #111827; background: radial-gradient(900px 420px at 50% -120px, #E6EDFD 0%, rgba(230, 237, 253, 0) 70%) #F6F7F9; }
        .au-utama { flex: 1 1 0%; width: 100%; display: flex; align-items: center; justify-content: center; padding: 28px 0; }
        .au-kartu { width: 100%; max-width: 440px; box-sizing: border-box; display: flex; flex-direction: column; gap: 24px; padding: 32px 32px 28px; background: #fff; border: 1px solid #E6E8EC; border-radius: 16px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04), 0 12px 32px -12px rgba(16, 24, 40, .12); }
        .au-kepala { display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; }
        .au-logo { display: block; margin-bottom: 10px; }
        .au-logo img { display: block; width: 56px; height: 56px; }
        .au-judul { margin: 0; font-size: 24px; line-height: 32px; font-weight: 600; letter-spacing: -.02em; color: #111827; }
        .au-sub { font-size: 14px; line-height: 21px; color: #6B7280; }
        .au-form { display: flex; flex-direction: column; gap: 16px; }
        .au-baris { display: flex; flex-direction: column; gap: 6px; }
        .au-lbl { font-size: 13px; line-height: 18px; font-weight: 500; color: #374151; }
        .au-fld { display: flex; align-items: center; gap: 10px; box-sizing: border-box; min-height: 44px; padding: 0 4px 0 12px; border-radius: 10px; background: #fff; border: 1px solid #AEB3BB; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); transition: border-color 120ms, background-color 120ms, box-shadow 120ms; }
        .au-fld:focus-within { background: #fff; border-color: #1D4ED8; box-shadow: 0 0 0 4px rgba(29, 78, 216, .12); }
        .au-fld[data-invalid], .au-polos[aria-invalid="true"] { border-color: #EA2B2B; }
        .au-ikon { flex: 0 0 auto; display: flex; color: #8B919C; }
        .au-input { flex: 1 1 0%; min-width: 0; width: 100%; box-sizing: border-box; height: 42px; border: 0; outline: none; background: transparent; padding: 0; font: inherit; font-size: 14px; line-height: 20px; color: #111827; }
        .au-input::placeholder, .au-polos::placeholder { color: #666C76; opacity: 1; }
        .au-polos { width: 100%; height: 44px; padding: 0 12px; box-sizing: border-box; border-radius: 10px; background: #fff; border: 1px solid #AEB3BB; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); font: inherit; font-size: 14px; color: #111827; outline: none; transition: border-color 120ms; }
        .au-polos:focus { border-color: #1D4ED8; }
        .au-mata { flex: 0 0 auto; width: 36px; height: 36px; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #6B7280; background: none; border: 0; padding: 0; cursor: pointer; }
        .au-galat { display: flex; align-items: center; gap: 6px; font-size: 12px; line-height: 16px; font-weight: 500; color: #B42318; }
        .au-galat svg { flex: none; }
        .au-info { font-size: 12px; line-height: 17px; color: #6B7280; }
        .au-cek { display: flex; align-items: center; gap: 10px; min-height: 44px; font-size: 13px; line-height: 18px; font-weight: 600; cursor: pointer; }
        .au-cek input { width: 20px; height: 20px; margin: 0; accent-color: #1D4ED8; }
        .au-tautan { font-weight: 600; color: #1D4ED8; text-decoration: none; }
        .au-lupa { display: inline-flex; align-items: center; min-height: 44px; font-size: 13px; line-height: 18px; font-weight: 600; color: #1D4ED8; text-decoration: none; }
        .au-tombol { box-sizing: border-box; display: inline-flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 44px; padding: 0 14px; border-radius: 10px; border: 0; font: inherit; font-size: 14px; line-height: 20px; font-weight: 600; white-space: nowrap; cursor: pointer; text-decoration: none; transition: transform 120ms cubic-bezier(.22, 1, .36, 1), filter 120ms; }
        .au-tombol:disabled { opacity: .5; cursor: not-allowed; }
        .au-tombol:not(:disabled):active { transform: scale(.97); }
        .au-utama-tombol { background: #1D4ED8; color: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .12); }
        .au-kedua { background: #fff; color: #262B33; border: 1px solid #D9DCE1; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); }
        @media (hover: hover) { .au-tombol:not(:disabled):hover, .au-tautan:hover, .au-lupa:hover { filter: brightness(1.04); } .au-kedua:not(:disabled):hover { background: #F9FAFB; filter: none; } }
        .au-pembatas { display: flex; align-items: center; gap: 12px; }
        .au-pembatas span:not(.au-pembatas-teks) { flex: 1 1 0%; height: 2px; background: #E2E5E9; }
        .au-pembatas-teks { font-size: 12px; line-height: 16px; font-weight: 600; color: #6B7280; }
        .au-sosial { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
        .au-sosial .au-tombol { width: auto; }
        .au-g { display: flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 11px; background: #F4F5F7; font-size: 13px; font-weight: 600; }
        .au-bawah { text-align: center; font-size: 14px; line-height: 19px; font-weight: 500; color: #6B7280; }
        .au-bawah a { display: inline-block; min-height: 44px; line-height: 44px; padding: 0 4px; font-weight: 600; color: #1D4ED8; text-decoration: none; }
        .au-kabar { display: flex; align-items: flex-start; gap: 8px; border-radius: 10px; padding: 10px 6px 10px 14px; font-size: 13px; line-height: 18px; font-weight: 600; background: #FFF4CC; color: #7A5200; }
        .au-kabar > span { flex: 1 1 0%; min-width: 0; padding-top: 1px; }
        .au-kabar button { flex: 0 0 auto; width: 44px; height: 44px; margin: -10px 0; font-size: 20px; line-height: 20px; font-weight: 600; text-align: center; background: none; border: 0; padding: 0; cursor: pointer; color: inherit; }
        .au-kabar-galat { background: #FEF3F2; color: #B42318; padding-right: 14px; }
        .au-kabar-ok { background: #F0FBE3; color: #3C7A00; padding-right: 14px; align-items: center; }
        .au-footer { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 18px; font-size: 12px; line-height: 16px; color: #8B919C; }
        .au-footer a { color: #6B7280; text-decoration: none; }
        .au-kuat { display: flex; align-items: center; gap: 10px; }
        .au-kuat-batang { flex: 1 1 0%; display: flex; gap: 4px; }
        .au-kuat-batang span { flex: 1 1 0%; height: 4px; border-radius: 2px; background: #EAECF0; transition: background-color 150ms; }
        .au-kuat-label { flex: 0 0 auto; min-width: 48px; text-align: right; font-size: 12px; line-height: 16px; font-weight: 600; color: #666C76; }
        .au-otp { display: flex; flex-direction: column; gap: 8px; }
        .au-otp-kotak { display: flex; gap: 8px; }
        .au-otp-kotak input { box-sizing: border-box; width: 100%; min-width: 0; height: 52px; text-align: center; font: inherit; font-size: 22px; font-weight: 600; color: #111827; border-radius: 10px; border: 1px solid #AEB3BB; background: #fff; box-shadow: 0 1px 2px rgba(16, 24, 40, .05); outline: none; padding: 0; }
        .au-otp-kotak input:focus { border-color: #1D4ED8; box-shadow: 0 0 0 4px rgba(29, 78, 216, .12); }
        .au-otp-kotak input[data-invalid] { border-color: #EA2B2B; }
        .au-teks-kecil { font-size: 13px; line-height: 18px; color: #6B7280; text-align: center; }
        .au-teks-kecil button, .au-tombol-teks { background: none; border: 0; padding: 0; font: inherit; font-weight: 600; color: #1D4ED8; cursor: pointer; }
        @media (max-width: 480px) {
            .au-halaman { padding: 24px 16px 20px; }
            .au-kartu { padding: 24px 20px 20px; }
        }
        @media (prefers-reduced-motion: no-preference) {
            [role="alert"].au-galat, [role="alert"].au-kabar { animation: au-getar .45s ease-in-out; }
        }
        @keyframes au-getar { 0%, 100% { transform: translateX(0); } 20% { transform: translateX(-6px); } 40% { transform: translateX(5px); } 60% { transform: translateX(-3px); } 80% { transform: translateX(2px); } }
    </style>
</head>
<body>
<div class="au-halaman">
    <main class="au-utama">
        <div class="au-kartu">
            {{ $slot }}
        </div>
    </main>
    <footer class="au-footer">
        <span>&copy; {{ date('Y') }} PT Undangyah Digital</span>
        <a href="{{ route('syarat') }}">Syarat &amp; Privasi</a>
        <a href="{{ route('bantuan') }}">Pusat Bantuan</a>
    </footer>
</div>
<x-toast />
</body>
</html>
