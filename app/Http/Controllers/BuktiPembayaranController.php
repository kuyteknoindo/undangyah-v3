<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use App\Services\KonfirmasiPesanan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Menayangkan bukti transfer dari disk privat, hanya kepada pemilik pesanan (atau admin untuk verifikasi). */
class BuktiPembayaranController extends Controller
{
    public function __invoke(Request $request, Pesanan $pesanan): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user && ($pesanan->user_id === $user->id || $user->isAdmin()), 404);

        $disk = Storage::disk(KonfirmasiPesanan::DISK_BUKTI);
        abort_unless($pesanan->bukti_path && $disk->exists($pesanan->bukti_path), 404);

        return $disk->response($pesanan->bukti_path, null, [
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; sandbox",
        ]);
    }
}
