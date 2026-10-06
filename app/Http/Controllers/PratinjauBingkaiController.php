<?php

namespace App\Http\Controllers;

use App\Models\Tema;
use App\Models\Undangan;
use App\Services\PratinjauUndangan;
use App\Services\RenderUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Dokumen HTML murni berisi undangan, untuk iframe halaman Pratinjau. Hanya pemilik. */
class PratinjauBingkaiController extends Controller
{
    public function undangan(Request $request, int $undangan): Response
    {
        $u = Undangan::query()->where('user_id', $request->user()->id)->find($undangan);
        abort_unless($u, 404);

        $info = PratinjauUndangan::tentukan($u, $request->query('v'));
        abort_if($info['mode'] === 'kosong', 404);

        $terbit = $info['versi'] === 'terbit';
        $isi = $terbit && is_array($u->terbitan['isi'] ?? null) ? $u->terbitan['isi'] : (array) $u->isi;
        $html = RenderUndangan::untukUndangan($u, $terbit, ['tamu' => PratinjauUndangan::contohTamu($isi)]);

        return $this->dokumen($html, $u->judul);
    }

    public function contoh(): Response
    {
        $isi = SkemaUndangan::isiAwal();
        $tema = Tema::query()->terbit()->urut()->first();
        $html = RenderUndangan::html($isi, ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []], $tema, ['tamu' => PratinjauUndangan::contohTamu($isi)]);

        return $this->dokumen($html, 'Undangan contoh');
    }

    private function dokumen(string $html, string $judul): Response
    {
        $dokumen = '<!doctype html><html lang="id"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">'
            .'<title>'.e($judul).'</title><style>html,body{margin:0;padding:0;background:#fff}</style></head><body>'.$html.'</body></html>';

        return response($dokumen, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            // Dokumen ini tanpa skrip; gambar boleh dari mana saja, selebihnya ditutup.
            'Content-Security-Policy' => "default-src 'none'; img-src http: https: 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'self'",
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
