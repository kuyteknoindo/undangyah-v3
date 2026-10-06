<?php

namespace App\Services;

use RuntimeException;

/** Kegagalan backup/verifikasi/uji pulih. Pesannya sudah aman ditampilkan (tanpa sandi atau jalur sistem). */
class BackupGagal extends RuntimeException {}
