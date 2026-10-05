<?php

namespace App\Mail;

use App\Services\LayananOtp;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class KodeOtpMail extends Mailable
{
    public function __construct(
        public string $kode,
        public string $nama,
        public string $tujuan,
        public int $menit,
    ) {}

    public function envelope(): Envelope
    {
        $judul = $this->tujuan === LayananOtp::TUJUAN_RESET
            ? 'Kode untuk mengatur ulang kata sandi undangyah'
            : 'Kode verifikasi email undangyah';

        return new Envelope(subject: $judul);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.kode-otp');
    }
}
