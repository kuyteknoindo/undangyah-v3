<!DOCTYPE html>
<html lang="id">
<body style="margin:0;background:#f4f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;color:#262a31">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 12px">
    <tr><td align="center">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#fff;border:1px solid #e6e8ec;border-radius:16px;padding:32px">
            <tr><td style="font-size:18px;font-weight:700;color:#1d4ed8">undangyah</td></tr>
            <tr><td style="padding-top:20px;font-size:15px;line-height:24px">
                Halo{{ $nama !== '' ? ', '.$nama : '' }}.<br>
                Seseorang (semoga kamu) meminta mengganti email akun undangyah menjadi <strong>{{ $emailBaru }}</strong>.
                Tekan tombol di bawah untuk mengonfirmasi. Email akun baru berubah setelah tautan ini dibuka.
            </td></tr>
            <tr><td align="center" style="padding:24px 0">
                <a href="{{ $tautan }}" style="display:inline-block;background:#1d4ed8;color:#fff;text-decoration:none;border-radius:10px;padding:12px 22px;font-size:15px;font-weight:600">Konfirmasi email baru</a>
            </td></tr>
            <tr><td style="font-size:13px;line-height:20px;color:#6b7280">
                Tautan berlaku {{ $menit }} menit dan hanya bisa dipakai sekali. Kalau tombol tidak berfungsi, salin tautan ini ke peramban:<br>
                <span style="word-break:break-all;color:#1d4ed8">{{ $tautan }}</span><br><br>
                Kalau kamu tidak memintanya, abaikan email ini; email akunmu tidak akan berubah.
            </td></tr>
        </table>
        <div style="padding-top:16px;font-size:12px;color:#8b919c">&copy; {{ date('Y') }} PT Undangyah Digital</div>
    </td></tr>
</table>
</body>
</html>
