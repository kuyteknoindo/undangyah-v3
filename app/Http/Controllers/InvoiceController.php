<?php

namespace App\Http\Controllers;

use App\Models\Pesanan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Invoice HTML sisi server yang bisa dicetak/disimpan sebagai PDF lewat browser. Hanya pemilik (atau admin). */
class InvoiceController extends Controller
{
    public function __invoke(Request $request, Pesanan $pesanan): Response
    {
        $user = $request->user();
        abort_unless($user && ($pesanan->user_id === $user->id || $user->isAdmin()), 404);

        $pesanan->load('item');

        return response()
            ->view('invoice.tampil', ['pesanan' => $pesanan])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
