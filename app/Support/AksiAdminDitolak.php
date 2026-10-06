<?php

namespace App\Support;

use RuntimeException;

/** Aksi admin atas pengguna yang melanggar aturan; pesannya aman ditampilkan (Bahasa Indonesia). */
class AksiAdminDitolak extends RuntimeException {}
