<?php

namespace App\Http\Controllers;

use App\Services\SesiImpersonasi;
use Illuminate\Http\RedirectResponse;

class AkhiriImpersonasiController extends Controller
{
    public function __invoke(SesiImpersonasi $impersonasi): RedirectResponse
    {
        if (! $impersonasi->aktif()) {
            return redirect()->route('beranda');
        }

        $admin = $impersonasi->akhiri();
        session()->flash('toast', ['pesan' => $admin ? 'Kamu kembali ke akun admin.' : 'Sesi berakhir. Silakan masuk lagi.', 'tipe' => 'info']);

        return $admin ? redirect()->route('admin.users') : redirect()->route('masuk');
    }
}
