<!DOCTYPE html>
<html lang="id">
<body style="margin:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#262a31">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#fff;border:1px solid #e6e8ec;border-radius:16px;padding:32px">
            <tr><td style="font-size:18px;font-weight:700;color:#1d4ed8">undangyah</td></tr>
            <tr><td style="padding-top:20px;font-size:15px;line-height:24px">
                Halo{{ $nama !== '' ? ', '.$nama : '' }}.<br>
                @if ($tujuan === \App\Services\LayananOtp::TUJUAN_RESET)
                    Gunakan kode ini untuk mengatur ulang kata sandi akunmu:
                @elseif ($tujuan === \App\Services\LayananOtp::TUJUAN_UJI)
                    Ini kode uji dari Panel Admin untuk memeriksa pengaturan OTP:
                @else
                    Gunakan kode ini untuk memverifikasi email akunmu:
                @endif
            </td></tr>
            <tr><td align="center" style="padding:20px 0">
                <div style="display:inline-block;background:#eef3fe;color:#1d4ed8;border-radius:12px;padding:14px 24px;font-size:32px;font-weight:700;letter-spacing:8px">{{ $kode }}</div>
            </td></tr>
            <tr><td style="font-size:13px;line-height:20px;color:#6b7280">
                Kode berlaku {{ $menit }} menit dan hanya bisa dipakai sekali. Jangan bagikan kode ini kepada siapa pun, termasuk yang mengaku dari undangyah.
                Kalau kamu tidak memintanya, abaikan email ini.
            </td></tr>
        </table>
        <div style="padding-top:16px;font-size:12px;color:#8b919c">&copy; {{ date('Y') }} PT Undangyah Digital</div>
    </td></tr>
</table>
</body>
</html>
