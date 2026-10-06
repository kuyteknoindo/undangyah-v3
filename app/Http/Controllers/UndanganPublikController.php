<?php

namespace App\Http\Controllers;

use App\Models\Notifikasi as ModelNotifikasi;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Ucapan;
use App\Models\Undangan;
use App\Services\BukuTamuPengaturan;
use App\Services\CatatTayangan;
use App\Services\MetaUndangan;
use App\Services\Notifikasi;
use App\Services\PenahananSlug;
use App\Services\RenderUndangan;
use App\Services\SlugUndangan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Halaman tamu /{slug}: hanya undangan berstatus terbit, dari salinan `terbitan` (bukan draf).
 * Hak tamu hanya lewat kode `?to=` milik undangan itu; kode asing tidak pernah dipakai sebagai identitas.
 */
class UndanganPublikController extends Controller
{
    public const HALAMAN_UCAPAN = 10;

    public const MAKS_NAMA = 60;

    public const MAKS_PESAN = 500;

    public const MAKS_JUMLAH = 20;

    public const BATAS_RSVP = 5;

    public const BATAS_UCAPAN = 3;

    /** Jendela pembatasan pengiriman, detik. */
    public const JENDELA = 600;

    /** Semua permintaan tulis (termasuk yang ditolak validasi) per IP + undangan per jendela. */
    public const BATAS_UMUM = 30;

    public function __construct(private SlugUndangan $slugLayanan, private MetaUndangan $meta, private CatatTayangan $tayangan) {}

    // ------------------------------------------------------------------ halaman

    public function tampil(Request $request, string $slug): Response|RedirectResponse
    {
        $kecil = strtolower($slug);
        if ($kecil !== $slug) {
            return redirect()->to(route('undangan.publik', ['slug' => $kecil] + $request->query(), false), 301);
        }
        abort_if($this->slugLayanan->galatFormat($slug) !== null, 404);

        $u = Undangan::query()->where('slug', $slug)->first();
        if (! $u) {
            return $this->alihkanSlugLama($request, $slug);
        }

        if (! $this->terbitan($u)) {
            // Pemilik yang membuka alamat drafnya sendiri diarahkan ke pratinjau; orang lain melihat 404 biasa.
            if ($request->user() && (int) $request->user()->id === (int) $u->user_id && Route::has('pratinjau')) {
                return redirect()->route('pratinjau', ['undangan' => $u->id]);
            }
            abort(404);
        }

        ['tamu' => $tamu, 'nama' => $nama] = $this->kenaliTamu($request, $u);

        // Sematan selalu di atas (hanya di halaman pertama); yang disembunyikan pemilik tidak pernah tampil.
        $sematan = Ucapan::query()->tampil()->where('undangan_id', $u->id)->where('disematkan', true)->orderByDesc('id')->limit(Ucapan::MAKS_SEMATAN)->get();
        $semua = Ucapan::query()->tampil()->where('undangan_id', $u->id)->where('disematkan', false)->orderByDesc('id')->limit(self::HALAMAN_UCAPAN + 1)->get();
        $adaLagi = $semua->count() > self::HALAMAN_UCAPAN;
        $biasa = $semua->take(self::HALAMAN_UCAPAN);
        $ucapan = $sematan->concat($biasa);

        $opsi = ['ucapan' => $ucapan->map(fn (Ucapan $x) => $this->itemUcapan($x))->all()];
        if ($tamu || $nama !== '') {
            $opsi['tamu'] = ['nama' => $tamu?->nama ?? $nama, 'alamat' => '', 'kode' => $tamu?->kode ?? '',
                // QR hanya untuk tamu terdaftar (kode valid); tanpa itu bagian QR disembunyikan, bukan QR palsu.
                'qr' => $tamu ? route('undangan.publik', $u->slug).'?to='.$tamu->kode : ''];
        }

        $isi = (array) $u->terbitan['isi'];
        $tema = (array) ($u->terbitan['tema'] ?? []);
        $maks = $tamu ? max(1, (int) $tamu->jumlah) : self::MAKS_JUMLAH;

        $this->tayangan->catat($request, $u, $tamu);
        $meta = $this->meta->untuk($u);

        return response()->view('publik.undangan', [
            'u' => $u,
            'html' => RenderUndangan::untukUndangan($u, true, $opsi),
            'meta' => $meta,
            'judulHalaman' => $meta['judul'].($tamu || $nama !== '' ? ' · untuk '.($tamu?->nama ?? $nama) : ''),
            'latar' => $this->hex($tema['warna_permukaan'] ?? null) ?? '#F3F6FB',
            'konfig' => [
                'urlRsvp' => route('undangan.publik.rsvp', $u->slug, false),
                'urlUcapan' => route('undangan.publik.ucapan', $u->slug, false),
                'kode' => $tamu?->kode ?? '',
                'nama' => $tamu?->nama ?? $nama,
                'maksJumlah' => $maks,
                'tampilJumlah' => trim((string) ($isi['rsLabelJumlah'] ?? '')) !== '',
                'adaLagi' => $adaLagi,
                'kursor' => $biasa->last()?->id,
                'judulRsvp' => trim((string) ($isi['rsTitle'] ?? '')) ?: 'Konfirmasi Kehadiran',
                'judulUcapan' => trim((string) ($isi['wsTitle'] ?? '')) ?: 'Ucapan & Doa',
                'tombolRsvp' => trim((string) ($isi['rsBtn'] ?? '')) ?: 'Kirim konfirmasi',
                'tombolUcapan' => trim((string) ($isi['wsBtn'] ?? '')) ?: 'Kirim ucapan',
                'musikAuto' => ($isi['musAuto'] ?? '1') === '1',
            ],
        ], 200, $this->headerHalaman());
    }

    /**
     * Slug lama yang masih ditahan mengalihkan 301 ke slug undangan yang sama sekarang. Tujuan selalu dibentuk dari
     * baris undangan di basis data (bukan dari input), dan hanya bila undangan itu masih ada dan terbit; selain itu 404.
     * `/{slug lama}/rsvp` dan `/ucapan` sengaja tetap 404: form lama tidak boleh mengirim data lewat alamat yang sudah berpindah.
     */
    private function alihkanSlugLama(Request $request, string $slug): RedirectResponse
    {
        $tahan = app(PenahananSlug::class)->aktif($slug);
        $tujuan = $tahan?->undangan_id ? Undangan::query()->find($tahan->undangan_id) : null;
        abort_unless($tujuan && $tujuan->sudahTerbit() && filled($tujuan->slug) && $tujuan->slug !== $slug, 404);

        return redirect()->to(route('undangan.publik', ['slug' => $tujuan->slug] + $request->query(), false), 301);
    }

    // ------------------------------------------------------------------ ucapan (baca)

    public function daftarUcapan(Request $request, string $slug): JsonResponse
    {
        $limiter = 'uy-baca:'.$this->sidikIp($request);
        if (RateLimiter::tooManyAttempts($limiter, 60)) {
            return $this->galat('Terlalu sering memuat. Coba lagi sebentar lagi.', 429, RateLimiter::availableIn($limiter));
        }
        RateLimiter::hit($limiter, 60);

        $u = $this->terbitOr404($slug);
        $sebelum = (int) $request->query('sebelum', 0);

        $semua = Ucapan::query()->tampil()->where('undangan_id', $u->id)->where('disematkan', false)
            ->when($sebelum > 0, fn ($q) => $q->where('id', '<', $sebelum))
            ->orderByDesc('id')->limit(self::HALAMAN_UCAPAN + 1)->get();
        $ucapan = $semua->take(self::HALAMAN_UCAPAN);

        return $this->json([
            'ok' => true,
            'data' => $ucapan->map(fn (Ucapan $x) => $this->itemUcapan($x))->values()->all(),
            'ada_lagi' => $semua->count() > self::HALAMAN_UCAPAN,
            'kursor' => $ucapan->last()?->id,
        ]);
    }

    // ------------------------------------------------------------------ RSVP

    public function kirimRsvp(Request $request, string $slug): JsonResponse
    {
        $u = $this->terbitOr404($slug);
        if ($tolak = $this->batasUmum($request, $u)) {
            return $tolak;
        }
        if ($this->sarang($request)) {
            return $this->json(['ok' => true, 'pesan' => 'Terima kasih.']);
        }
        $limiter = $this->kunci('rsvp', $u, $request);
        if (RateLimiter::tooManyAttempts($limiter, self::BATAS_RSVP)) {
            return $this->terlalu($limiter);
        }

        $data = $this->bersihkan($request);
        $tamu = null;
        if ($data['kode'] !== '') {
            $tamu = Tamu::query()->where('undangan_id', $u->id)->where('kode', $data['kode'])->first();
            if (! $tamu) {
                return $this->galat('Tautan tamu tidak dikenali. Muat ulang halaman dari tautan yang Anda terima.', 422);
            }
        }
        $maks = $tamu ? max(1, (int) $tamu->jumlah) : self::MAKS_JUMLAH;
        if ($data['status'] === Rsvp::TIDAK) {
            $data['jumlah'] = null;
        }

        $v = Validator::make($data, [
            'nama' => ['required', 'string', 'max:'.self::MAKS_NAMA],
            'status' => ['required', 'in:'.Rsvp::HADIR.','.Rsvp::TIDAK],
            'jumlah' => ['nullable', 'integer', 'min:1', 'max:'.$maks],
            'pesan' => ['nullable', 'string', 'max:'.self::MAKS_PESAN],
        ], $this->pesanGalat($maks), ['nama' => 'Nama', 'status' => 'Kehadiran', 'jumlah' => 'Jumlah tamu', 'pesan' => 'Pesan']);
        if ($v->fails()) {
            return $this->galatValidasi($v->errors()->toArray());
        }

        $hadir = $data['status'] === Rsvp::HADIR;
        $nilai = [
            'nama' => $data['nama'],
            'status' => $data['status'],
            'jumlah' => $hadir ? max(1, (int) ($data['jumlah'] ?: 1)) : 0,
            'pesan' => $data['pesan'] !== '' ? $data['pesan'] : null,
            'kode_tamu' => $tamu?->kode,
        ];

        RateLimiter::hit($limiter, self::JENDELA);

        $ucapan = null;
        $rsvp = null;
        $baru = true;
        $buku = app(BukuTamuPengaturan::class)->ambil($u);
        DB::transaction(function () use ($u, $tamu, $nilai, $buku, &$ucapan, &$baru, &$rsvp) {
            if ($tamu) {
                $rsvp = Rsvp::query()->updateOrCreate(['undangan_id' => $u->id, 'tamu_id' => $tamu->id], $nilai);
                $baru = $rsvp->wasRecentlyCreated;
            } else {
                $rsvp = Rsvp::query()->create($nilai + ['undangan_id' => $u->id, 'tamu_id' => null]);
            }

            // Seperti di desain, pesan pada RSVP ikut tampil sebagai ucapan (sekali per isi pesan yang sama untuk tamu bertaut).
            if ($nilai['pesan'] !== null && $buku['buka']) {
                $terakhir = $tamu ? Ucapan::query()->where('undangan_id', $u->id)->where('tamu_id', $tamu->id)->latest('id')->value('pesan') : null;
                if ($terakhir !== $nilai['pesan']) {
                    $ucapan = Ucapan::query()->create([
                        'undangan_id' => $u->id, 'tamu_id' => $tamu?->id, 'nama' => $nilai['nama'],
                        'pesan' => $nilai['pesan'], 'kode_tamu' => $tamu?->kode, 'disembunyikan' => $buku['setujui'],
                    ]);
                }
            }
        });

        $orang = $nilai['jumlah'];
        $this->beritahu($u, ModelNotifikasi::TIPE_RSVP,
            ($baru ? 'RSVP baru dari ' : 'RSVP diperbarui oleh ').$nilai['nama'],
            $nilai['nama'].($hadir ? ' akan hadir ('.$orang.' orang)' : ' tidak dapat hadir').' di "'.($u->judul).'".',
            'rsvp', ['undangan_id' => $u->id, 'rsvp_id' => $rsvp->id]);

        return $this->json([
            'ok' => true,
            'pesan' => $hadir
                ? 'Kehadiran Anda tercatat untuk '.$orang.' orang. Sampai jumpa di hari bahagia kami.'
                : 'Konfirmasi Anda sudah kami terima. Kami tetap berterima kasih atas doanya.',
            'nama' => $nilai['nama'],
            'ucapan' => $ucapan && ! $ucapan->disembunyikan ? $this->itemUcapan($ucapan) : null,
        ]);
    }

    // ------------------------------------------------------------------ ucapan (kirim)

    public function kirimUcapan(Request $request, string $slug): JsonResponse
    {
        $u = $this->terbitOr404($slug);
        if ($tolak = $this->batasUmum($request, $u)) {
            return $tolak;
        }
        if ($this->sarang($request)) {
            return $this->json(['ok' => true, 'pesan' => 'Terima kasih.']);
        }
        $buku = app(BukuTamuPengaturan::class)->ambil($u);
        if (! $buku['buka']) {
            return $this->galat('Kolom ucapan sedang ditutup oleh pemilik undangan.', 403);
        }
        $limiter = $this->kunci('ucapan', $u, $request);
        if (RateLimiter::tooManyAttempts($limiter, self::BATAS_UCAPAN)) {
            return $this->terlalu($limiter);
        }

        $data = $this->bersihkan($request);
        $tamu = null;
        if ($data['kode'] !== '') {
            $tamu = Tamu::query()->where('undangan_id', $u->id)->where('kode', $data['kode'])->first();
            if (! $tamu) {
                return $this->galat('Tautan tamu tidak dikenali. Muat ulang halaman dari tautan yang Anda terima.', 422);
            }
        }

        $v = Validator::make($data, [
            'nama' => ['required', 'string', 'max:'.self::MAKS_NAMA],
            'pesan' => ['required', 'string', 'max:'.self::MAKS_PESAN],
        ], $this->pesanGalat(0), ['nama' => 'Nama', 'pesan' => 'Ucapan']);
        if ($v->fails()) {
            return $this->galatValidasi($v->errors()->toArray());
        }

        RateLimiter::hit($limiter, self::JENDELA);

        $ucapan = Ucapan::query()->create([
            'undangan_id' => $u->id, 'tamu_id' => $tamu?->id, 'nama' => $data['nama'],
            'pesan' => $data['pesan'], 'kode_tamu' => $tamu?->kode, 'disembunyikan' => $buku['setujui'],
        ]);

        $this->beritahu($u, ModelNotifikasi::TIPE_UCAPAN, 'Ucapan baru dari '.$data['nama'],
            $data['nama'].' mengirim ucapan untuk "'.$u->judul.'": '.Str::limit($data['pesan'], 120).($buku['setujui'] ? ' (menunggu persetujuanmu)' : ''),
            'buku-tamu', ['undangan_id' => $u->id, 'ucapan_id' => $ucapan->id]);

        return $this->json([
            'ok' => true,
            'pesan' => $buku['setujui'] ? 'Ucapan Anda sudah kami terima dan akan tampil setelah disetujui pemilik undangan.' : 'Ucapan Anda sudah kami terima dan tampil di daftar.',
            'nama' => $data['nama'],
            'ucapan' => $buku['setujui'] ? null : $this->itemUcapan($ucapan),
        ]);
    }

    // ------------------------------------------------------------------ pembantu

    /** Salinan terbitan yang dapat dirender, atau null bila belum terbit/rusak. */
    private function terbitan(Undangan $u): ?array
    {
        // Undangan milik akun yang ditangguhkan admin tidak ditayangkan.
        if ($u->pengguna?->ditangguhkan_at) {
            return null;
        }

        return $u->sudahTerbit() && is_array($u->terbitan['isi'] ?? null) ? $u->terbitan : null;
    }

    private function terbitOr404(string $slug): Undangan
    {
        abort_if($this->slugLayanan->galatFormat($slug) !== null, 404);
        $u = Undangan::query()->where('slug', $slug)->first();
        abort_unless($u && $this->terbitan($u), 404);

        return $u;
    }

    /** @return array{tamu:?Tamu,nama:string} */
    private function kenaliTamu(Request $request, Undangan $u): array
    {
        $to = $request->query('to');
        if (! is_string($to) || trim($to) === '') {
            return ['tamu' => null, 'nama' => ''];
        }
        $to = trim($to);

        if (preg_match('/^[A-Za-z0-9]{4,24}$/', $to)) {
            $tamu = Tamu::query()->where('undangan_id', $u->id)->where('kode', strtolower($to))->first();
            if ($tamu) {
                return ['tamu' => $tamu, 'nama' => ''];
            }
            // Bentuk kode (10 huruf/angka) yang tidak dikenal diabaikan; kata lain dianggap nama sapaan.
            if (strlen($to) === 10 && $to === strtolower($to)) {
                return ['tamu' => null, 'nama' => ''];
            }
        }

        $nama = $this->satuBaris($to);

        return ['tamu' => null, 'nama' => mb_strlen($nama) > self::MAKS_NAMA ? '' : $nama];
    }

    /** Buang tag, karakter kontrol, dan spasi berlebih (satu baris). */
    private function satuBaris(string $teks): string
    {
        $teks = strip_tags($teks);
        $teks = preg_replace('/[\x00-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', ' ', $teks) ?? '';

        return trim((string) preg_replace('/\s+/u', ' ', $teks));
    }

    private function banyakBaris(string $teks): string
    {
        $teks = strip_tags(str_replace(["\r\n", "\r"], "\n", $teks));
        $teks = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]+/u', '', $teks) ?? '';
        $teks = preg_replace('/[ \t]+/u', ' ', $teks) ?? '';
        $teks = preg_replace("/\n{3,}/", "\n\n", $teks) ?? '';

        return trim($teks);
    }

    /** @return array{nama:string,pesan:string,status:string,jumlah:?string,kode:string} */
    private function bersihkan(Request $request): array
    {
        $ambil = fn (string $k) => is_scalar($request->input($k)) ? (string) $request->input($k) : '';
        $kode = strtolower(trim($ambil('kode')));

        return [
            'nama' => $this->satuBaris($ambil('nama')),
            'pesan' => $this->banyakBaris($ambil('pesan')),
            'status' => strtolower(trim($ambil('status'))),
            'jumlah' => $ambil('jumlah') === '' ? null : $ambil('jumlah'),
            'kode' => preg_match('/^[a-z0-9]{1,40}$/', $kode) ? $kode : ($kode === '' ? '' : '!'),
        ];
    }

    /** Kolom jebakan: manusia tidak melihatnya, bot biasanya mengisinya. */
    private function sarang(Request $request): bool
    {
        return trim((string) $request->input('situs_web', '')) !== '';
    }

    private function sidikIp(Request $request): string
    {
        return substr(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 0, 24);
    }

    private function kunci(string $jenis, Undangan $u, Request $request): string
    {
        return 'uy-'.$jenis.':'.$u->id.':'.$this->sidikIp($request);
    }

    private function batasUmum(Request $request, Undangan $u): ?JsonResponse
    {
        $limiter = $this->kunci('umum', $u, $request);
        if (RateLimiter::tooManyAttempts($limiter, self::BATAS_UMUM)) {
            return $this->terlalu($limiter);
        }
        RateLimiter::hit($limiter, self::JENDELA);

        return null;
    }

    private function terlalu(string $limiter): JsonResponse
    {
        $detik = max(1, RateLimiter::availableIn($limiter));

        return $this->galat('Terlalu banyak pengiriman. Coba lagi dalam '.max(1, (int) ceil($detik / 60)).' menit.', 429, $detik);
    }

    /** @return array<string,string> */
    private function pesanGalat(int $maksJumlah): array
    {
        return [
            'nama.required' => 'Isi nama Anda dulu.',
            'nama.max' => 'Nama maksimal '.self::MAKS_NAMA.' karakter.',
            'pesan.required' => 'Tulis ucapan Anda dulu.',
            'pesan.max' => 'Pesan maksimal '.self::MAKS_PESAN.' karakter.',
            'status.required' => 'Pilih hadir atau tidak hadir.',
            'status.in' => 'Pilih hadir atau tidak hadir.',
            'jumlah.integer' => 'Jumlah tamu harus berupa angka.',
            'jumlah.min' => 'Jumlah tamu minimal 1.',
            'jumlah.max' => 'Jumlah tamu maksimal '.$maksJumlah.' orang.',
        ];
    }

    /** @param array<string,list<string>> $galat */
    private function galatValidasi(array $galat): JsonResponse
    {
        $pertama = collect($galat)->flatten()->first() ?? 'Data tidak valid.';

        return $this->json(['ok' => false, 'pesan' => $pertama, 'galat' => array_map(fn ($m) => $m[0] ?? '', $galat)], 422);
    }

    private function galat(string $pesan, int $status, ?int $ulangDetik = null): JsonResponse
    {
        return $this->json(['ok' => false, 'pesan' => $pesan], $status, $ulangDetik ? ['Retry-After' => $ulangDetik] : []);
    }

    private function json(array $isi, int $status = 200, array $header = []): JsonResponse
    {
        return response()->json($isi, $status, $header + ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @return array{nama:string,pesan:string,waktu:string,id:int} */
    private function itemUcapan(Ucapan $x): array
    {
        return [
            'id' => (int) $x->id,
            'nama' => (string) $x->nama,
            'pesan' => (string) $x->pesan,
            'waktu' => $x->created_at ? $x->created_at->copy()->locale('id')->diffForHumans() : '',
        ];
    }

    /** Gagal memberi tahu pemilik tidak boleh menggagalkan kiriman tamu. */
    private function beritahu(Undangan $u, string $tipe, string $judul, string $isi, string $rute, array $data): void
    {
        try {
            $tautan = Route::has($rute) ? route($rute, [], false) : null;
            app(Notifikasi::class)->kirim($u->user_id, $tipe, $judul, $isi, $tautan, $data);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function hex(mixed $c): ?string
    {
        return is_string($c) && preg_match('/^#[0-9a-fA-F]{6}$/', trim($c)) ? strtoupper(trim($c)) : null;
    }

    /** @return array<string,string> */
    private function headerHalaman(): array
    {
        return [
            'Cache-Control' => 'no-cache, private',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            // Skrip hanya dari situs sendiri; gambar/audio boleh dari mana saja (isi pengguna), selebihnya ditutup.
            'Content-Security-Policy' => "default-src 'none'; script-src 'self' https://static.cloudflareinsights.com; style-src 'self' 'unsafe-inline'; img-src 'self' data: http: https:; media-src http: https: 'self'; connect-src 'self' https://cloudflareinsights.com; base-uri 'none'; form-action 'none'; frame-ancestors 'none'; object-src 'none'",
        ];
    }
}
