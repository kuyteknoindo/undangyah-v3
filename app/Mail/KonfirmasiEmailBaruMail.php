<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class KonfirmasiEmailBaruMail extends Mailable
{
    public function __construct(
        public string $nama,
        public string $emailBaru,
        public string $tautan,
        public int $menit,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Konfirmasi email baru akun undangyah');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.konfirmasi-email-baru');
    }
}
