<?php

namespace App\Support;

enum HasilOtp
{
    case Berhasil;
    case Salah;
    case TidakAda;      // tidak ada kode aktif, sudah dipakai, atau sudah kedaluwarsa
    case Terkunci;      // percobaan salah melebihi batas
}
