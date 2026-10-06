<?php

namespace App\Support;

use RuntimeException;

/** Konfirmasi kata sandi admin ditolak (salah, terkunci sementara, atau akun tidak bisa mengonfirmasi). Pesannya aman ditampilkan. */
class KonfirmasiSandiGagal extends RuntimeException {}
