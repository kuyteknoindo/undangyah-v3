<?php

namespace App\Services;

use App\Models\Tema;
use App\Models\Undangan;
use App\Support\SkemaUndangan;
use Illuminate\Support\Carbon;

/**
 * Renderer undangan: isi + bagian + tema -> satu fragmen HTML mandiri (`<div class="uy-undangan">`).
 *
 * - CSS terlingkup ke `.uy-undangan` (tidak bocor, tidak memakai Tailwind aplikasi).
 * - Semua nilai di-escape; tidak ada HTML mentah dari isi. URL hanya http/https (gambar: juga path situs "/...").
 * - Mode edit (`$opsi['edit']`): elemen yang bisa diedit diberi `data-kunci`. Kunci: kunci bidang skema
 *   ("priaP", "ev1_nama"), foto ("foto:cover", "foto:g3"), bagian ("bagian:acara"). Item daftar: `data-item="ev:1"`.
 *   Elemen terpilih diberi kelas `uy-pilih` oleh editor.
 * - Titik sambung Fase 4: `data-uy-bagian` = rsvp | ucapan | hadiah | qr | musik | hitung-mundur; `data-uy-aksi` = buka | salin.
 *
 * Konvensi isi di luar bidang skema (dibaca bila ada):
 *   _daftar[tpl]      urutan id item berulang
 *   _foto[slot]       cover|latar|penutup|pria|wanita|share|g{id}|bgf => "url" atau {src, crop:{ratio,zoom,x,y}}
 *   _galeri           {layout: grid|masonry|slider, video: url}
 *   _cincin           false untuk menyembunyikan lingkaran hias sampul (bawaan tampil)
 *   _gaya[kunci]      timpaan gaya {fs, b, ta, c}
 *   _sembunyi[kunci]  bidang yang disembunyikan (truthy)
 *   _latarTetap       {on, src, oc, oo, op, blur} latar tetap gambar (video/slideshow belum didukung)
 *
 * Opsi: edit(bool), lebar (px atau satuan CSS), tamu {nama, alamat, kode} (menampilkan bagian "tamu" dan nama di sampul),
 *       tamu.qr (string tautan yang dikodekan jadi QR sungguhan; kunci ada tapi kosong = bagian "tamu" disembunyikan; tanpa kunci = QR penanda tempat untuk pratinjau),
 *       ucapan list<{nama, pesan, waktu?}>.
 */
class RenderUndangan
{
    private const SERIF = "ui-serif,'New York',Georgia,serif";

    private const SANS = "-apple-system,BlinkMacSystemFont,'SF Pro Text','SF Pro Display','Inter',system-ui,sans-serif";

    private const SKRIP = "'Snell Roundhand','Apple Chancery','Brush Script MT','Segoe Script',cursive";

    private const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

    private const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    private const DASAR = ['fs' => 14, 'w' => 500, 'ta' => 'left', 'c' => 'ink', 'ff' => 'sans', 'lh' => 1.35, 'rows' => 0, 'ls' => '0', 'up' => false, 'ex' => '', 'kind' => 'text', 'canvas' => true, 'opt' => false, 'label' => '', 'v' => ''];

    private const PLATFORM_SIARAN = ['youtube' => 'YouTube Live', 'zoom' => 'Zoom', 'meet' => 'Google Meet', 'instagram' => 'Instagram Live', 'tiktok' => 'TikTok Live'];

    private const PLATFORM_SOSIAL = ['instagram' => 'Instagram', 'tiktok' => 'TikTok', 'facebook' => 'Facebook', 'youtube' => 'YouTube', 'x' => 'X (Twitter)'];

    private const IKON = [
        'kutip' => '<path d="M3 21c3 0 7-1 7-8V5c0-1.25-.76-2-2-2H4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .13-1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2-2-2h-4c-1.25 0-2 .75-2 2v6c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3z"/>',
        'musik' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
        'video' => '<path d="m22 8-6 4 6 4V8Z"/><rect width="14" height="12" x="2" y="6" rx="2"/>',
        'kalender' => '<rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    ];

    /** Warna turunan tema (setara mkTheme di desain). @var array<string,string|bool> */
    private array $w;

    private string $hf;

    /** Pengali ukuran huruf dari dokumen tema (DokumenTema); 1.0 = bawaan. */
    private float $skala = 1.0;

    private bool $edit;

    /** @var array<string,mixed> */
    private array $isi;

    /** @var array<string,mixed> */
    private array $opsi;

    /** @var array<string,?array> */
    private array $cacheMeta = [];

    /** @var array<string,bool> */
    private array $sembunyiBagian = [];

    /**
     * @param  array<string,mixed>  $isi
     * @param  array<string,mixed>  $bagian  {urutan: list<string>, sembunyi: list<string>|map<string,bool>}
     * @param  array<string,mixed>  $opsi
     */
    public static function html(array $isi, array $bagian, ?Tema $tema, array $opsi = []): string
    {
        return (new self($isi, $tema, $opsi))->render($bagian);
    }

    /** Memakai salinan terbitan bila diminta dan ada; selain itu draf. Terbitan: {isi, bagian, tema?: kolom tema}. */
    public static function untukUndangan(Undangan $u, bool $terbit = false, array $opsi = []): string
    {
        $isi = (array) $u->isi;
        $bagian = (array) $u->bagian;
        $tema = $u->tema;

        $t = $terbit ? $u->terbitan : null;
        if (is_array($t) && isset($t['isi']) && is_array($t['isi'])) {
            $isi = $t['isi'];
            $bagian = (array) ($t['bagian'] ?? $bagian);
            if (isset($t['tema']) && is_array($t['tema'])) {
                $tema = new Tema(array_intersect_key($t['tema'], array_flip([
                    'kode', 'nama', 'tier', 'warna_utama', 'warna_aksen', 'warna_permukaan', 'warna_halaman', 'warna_teks', 'font_judul', 'latar_tetap', 'dokumen',
                ])));
            }
        }

        return self::html($isi, $bagian, $tema, $opsi);
    }

    private function __construct(array $isi, ?Tema $tema, array $opsi)
    {
        $this->isi = $isi;
        $this->opsi = $opsi;
        $this->edit = ! empty($opsi['edit']);
        $this->w = $this->warnaTema($tema);
        $this->skala = DokumenTema::skala($tema?->dokumen);
        $this->hf = match ($tema?->font_judul) {
            'jakarta' => self::SANS,
            'script' => self::SKRIP,
            default => self::SERIF,
        };
    }

    // ------------------------------------------------------------------ rangka

    private function render(array $bagian): string
    {
        foreach ((array) ($bagian['sembunyi'] ?? []) as $k => $v) {
            if (is_int($k)) {
                $this->sembunyiBagian[(string) $v] = true;
            } elseif ($v) {
                $this->sembunyiBagian[(string) $k] = true;
            }
        }

        $badan = '';
        foreach ($this->urutan((array) ($bagian['urutan'] ?? [])) as $id) {
            if (isset($this->sembunyiBagian[$id])) {
                continue;
            }
            $badan .= $this->bagian($id);
        }

        $lebar = $this->lebar($this->opsi['lebar'] ?? null);
        $latar = $this->latarTetap();
        $kelas = 'uy-undangan'.($latar !== '' ? ' uy-bgf-on' : '');
        $atribut = ' data-uy-tema="'.e((string) ($this->w['id'] ?? '')).'"'.($this->edit ? ' data-uy-edit="1"' : '');

        return '<div class="'.$kelas.'"'.$atribut.' style="'.$this->variabel().'max-width:'.$lebar.';">'
            .'<style>'.$this->css().'</style>'
            .$latar.$badan
            .'</div>';
    }

    /** @return list<string> */
    private function urutan(array $urutan): array
    {
        $semua = array_keys(SkemaUndangan::bagian());
        $hasil = [];
        foreach ($urutan as $id) {
            if (is_string($id) && in_array($id, $semua, true) && ! in_array($id, $hasil, true)) {
                $hasil[] = $id;
            }
        }
        foreach (SkemaUndangan::urutanAwal() as $id) {
            if (! in_array($id, $hasil, true)) {
                $hasil[] = $id;
            }
        }
        // Sampul selalu di awal, penutup selalu di akhir (aturan editor).
        $tengah = array_values(array_diff($hasil, ['sampul', 'penutup']));

        return array_merge(['sampul'], $tengah, ['penutup']);
    }

    private function lebar(mixed $v): string
    {
        if (is_int($v) || (is_string($v) && ctype_digit($v))) {
            return max(240, min(1600, (int) $v)).'px';
        }
        if (is_string($v) && preg_match('/^\d{2,4}(px|rem|em|vw|%)$/', $v)) {
            return $v;
        }

        return '480px';
    }

    private function bagian(string $id): string
    {
        $isi = match ($id) {
            'sampul' => $this->bSampul(),
            'quotes' => $this->bQuotes(),
            'mempelai' => $this->bMempelai(),
            'acara' => $this->bAcara(),
            'lovestory' => $this->bLoveStory(),
            'foto' => $this->bFoto(),
            'gallery' => $this->bGaleri(),
            'musik' => $this->bMusik(),
            'rundown' => $this->bRundown(),
            'turut' => $this->bTurut(),
            'kado' => $this->bKado(),
            'livestream' => $this->bSiaran(),
            'gcal' => $this->bKalender(),
            'social' => $this->bSosial(),
            'dresscode' => $this->bDresscode(),
            'rsvp' => $this->bRsvp(),
            'tamu' => $this->bTamu(),
            'penutup' => $this->bPenutup(),
            default => '',
        };
        if ($isi === '') {
            return '';
        }

        $latar = match ($id) {
            'sampul' => 'cov',
            'mempelai', 'lovestory', 'musik', 'turut', 'gcal', 'dresscode', 'rsvp', 'penutup' => 'bgb',
            default => 'page',
        };

        return '<section class="uy-bag uy-bag-'.$id.' uy-l-'.$latar.'" data-bagian="'.$id.'"'
            .($this->edit ? ' data-kunci="bagian:'.$id.'"' : '').'>'.$isi.'</section>';
    }

    // ------------------------------------------------------------------ bagian

    private function bSampul(): string
    {
        $cincin = ($this->isi['_cincin'] ?? true) !== false && ($this->isi['_cincin'] ?? true) !== '0';
        $h = $cincin
            ? '<div class="uy-cincin" aria-hidden="true"><i class="a"></i><i class="b"></i><i class="c"></i></div>'
            : '';
        $h .= $this->foto('cover', 'cover');
        $h .= $this->teks('sapaan').$this->namaTamuSampul().$this->teks('tagline').$this->teks('priaP');
        if ($this->v('priaP') !== '' || $this->v('wanitaP') !== '' || $this->edit) {
            $h .= '<span class="uy-amp" aria-hidden="true" style="color:var(--uy-ona)">&amp;</span>';
        }
        $h .= $this->teks('wanitaP').$this->teks('tglCover');
        $h .= $this->teks('bukaBtn', $this->edit ? 'div' : 'button', 'uy-tombol', $this->edit ? '' : ' type="button" data-uy-aksi="buka"');
        $h .= $this->edit ? '' : $this->teks('bukaHint', 'div', 'uy-kecil', '', ['c' => 'onA', 'ta' => 'center', 'fs' => 12]);

        return $h;
    }

    /** Nama tamu (dari tautan pribadi) di bawah sapaan sampul; hanya di halaman tamu. */
    private function namaTamuSampul(): string
    {
        $nama = $this->edit ? '' : trim((string) ($this->opsi['tamu']['nama'] ?? ''));
        if ($nama === '') {
            return '';
        }

        return '<div class="uy-t uy-tamu-sampul" style="font-family:'.$this->hf.';font-size:22px;line-height:28px;font-weight:700;text-align:center;color:var(--uy-ona)">'.e($nama).'</div>';
    }

    private function bQuotes(): string
    {
        $isi = $this->teks('quote').$this->teks('quoteSrc');
        if ($isi === '' || (! $this->edit && $this->v('quote') === '')) {
            return '';
        }

        return '<span class="uy-bulat" aria-hidden="true">'.$this->svg('kutip', 22).'</span>'.$isi;
    }

    private function bMempelai(): string
    {
        $h = $this->teks('mTitle');
        $h .= $this->edit ? '' : $this->teks('mpIntro', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13]);
        $orang = '';
        foreach (['pria', 'wanita'] as $i => $p) {
            if ($i === 1 && ! $this->edit && $this->v('mpAmp') !== '') {
                $orang .= '<div class="uy-pemisah" aria-hidden="true" style="font-family:'.$this->hf.';color:var(--uy-acc)">'.e($this->v('mpAmp')).'</div>';
            }
            $nama = $this->v($p.'Nama');
            $kartu = $this->fotoOrang($p, $this->v($p.'P'));
            $kartu .= $this->edit ? '' : $this->teks($p.'Peran', 'div', 'uy-label', '', ['ta' => 'center', 'c' => 'acc', 'fs' => 11, 'w' => 700, 'ls' => '0.12em', 'up' => true]);
            $kartu .= $this->teks($p.'Nama');
            $kartu .= $this->edit ? '' : $this->teks($p.'Anak', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 12]);
            $kartu .= $this->teks($p.'Ortu');
            $kartu .= $this->akunInstagram($p.'Ig');
            $ada = $nama !== '' || $this->v($p.'Ortu') !== '' || $this->v($p.'Ig') !== '' || $this->sumberFoto($p) !== null;
            if (! $ada && ! $this->edit) {
                continue;
            }
            $orang .= '<div class="uy-kartu uy-polos">'.$kartu.'</div>';
        }
        if ($orang === '' && $this->v('mTitle') === '') {
            return '';
        }

        return $h.$orang;
    }

    private function bAcara(): string
    {
        $h = $this->teks('aTitle');
        $h .= $this->hitungMundur();
        $kartu = '';
        foreach ($this->daftar('ev') as $id) {
            $k = fn (string $f) => "ev{$id}_{$f}";
            $maps = $this->urlAman($this->v($k('maps')));
            $tombol = $this->edit
                ? $this->teks($k('mapsBtn'), 'div', 'uy-tombol')
                : ($maps !== null ? $this->teks($k('mapsBtn'), 'a', 'uy-tombol', ' href="'.e($maps).'" target="_blank" rel="noopener noreferrer"') : '');
            $dalam = $this->teks($k('nama')).$this->teks($k('tgl')).$this->teks($k('jam'))
                .'<span class="uy-garis" aria-hidden="true"></span>'
                .$this->teks($k('lokasi')).$this->teks($k('alamat')).$tombol;
            if (! $this->edit && trim(strip_tags(str_replace('<span class="uy-garis" aria-hidden="true"></span>', '', $dalam))) === '') {
                continue;
            }
            $kartu .= '<div class="uy-kartu uy-timbul"'.$this->itemAttr('ev', $id).'>'.$dalam.'</div>';
        }
        if ($kartu === '' && $this->edit) {
            $kartu = '<div class="uy-kosong-kotak">Belum ada acara.</div>';
        }
        if ($kartu === '' && ! $this->edit && $this->v('aTitle') === '' && $h === '') {
            return '';
        }

        return $h.$kartu;
    }

    private function bLoveStory(): string
    {
        $h = $this->teks('lsTitle');
        $baris = '';
        $ids = $this->daftar('ls');
        foreach ($ids as $n => $id) {
            $k = fn (string $f) => "ls{$id}_{$f}";
            $dalam = '<div class="uy-ls-kepala">'.$this->teks($k('y')).'</div>'.$this->teks($k('t')).$this->teks($k('b'));
            if (! $this->edit && $this->v($k('t')) === '' && $this->v($k('b')) === '' && $this->v($k('y')) === '') {
                continue;
            }
            $akhir = $n === count($ids) - 1;
            $baris .= '<div class="uy-ls"'.$this->itemAttr('ls', $id).'><div class="uy-ls-rel" aria-hidden="true"><i class="t"></i>'.($akhir ? '' : '<i class="g"></i>').'</div><div class="uy-ls-isi">'.$dalam.'</div></div>';
        }
        if ($baris === '') {
            if (! $this->edit) {
                return '';
            }
            $baris = '<div class="uy-kosong-kotak">Belum ada cerita. Tambahkan cerita pertama Anda.</div>';
        }

        return $h.'<div class="uy-ls-daftar">'.$baris.'</div>';
    }

    private function bFoto(): string
    {
        $foto = $this->foto('latar', 'banner');
        if ($foto === '') {
            return '';
        }

        return $foto.($this->edit ? '<div class="uy-catatan">Foto latar pembuka</div>' : '');
    }

    private function bGaleri(): string
    {
        $h = $this->teks('glTitle');
        $g = (array) ($this->isi['_galeri'] ?? []);
        $layout = in_array($g['layout'] ?? '', ['grid', 'masonry', 'slider'], true) ? $g['layout'] : 'grid';
        $rasio = [0.8, 1, 1.25, 1, 1.25, 0.8];
        $item = '';
        foreach ($this->daftar('gl') as $i => $id) {
            $f = $this->foto('g'.$id, 'galeri', $layout === 'slider' ? 0.8 : ($layout === 'masonry' ? $rasio[$i % 6] : 1.0));
            if ($f !== '') {
                $item .= $f;
            }
        }
        if ($item === '') {
            if (! $this->edit) {
                return $h.$this->teks('glCap');
            }
            $item = '<div class="uy-kosong-kotak">Belum ada foto.</div>';
        }
        $h .= '<div class="uy-galeri uy-galeri-'.$layout.'">'.$item.'</div>';
        $video = $this->urlAman((string) ($g['video'] ?? ''));
        if ($video !== null) {
            $h .= '<a class="uy-tautan-kartu" href="'.e($video).'" target="_blank" rel="noopener noreferrer"><span class="uy-merah" aria-hidden="true">'.$this->svg('video', 20).'</span><span class="uy-tk-isi"><b>Video galeri</b><span>'.e($this->pendekUrl($video)).'</span></span></a>';
        }

        return $h.$this->teks('glCap');
    }

    private function bMusik(): string
    {
        $judul = $this->v('musJudul');
        $artis = $this->v('musArtis');
        $sumber = $this->urlAman($this->v('musUrl'));
        $yt = $this->urlAman($this->v('musLink'));
        // Lagu pilihan dari katalog admin (hanya yang publik). Judul/artis bawaan skema berarti "belum diubah pengguna" -> pakai data lagu;
        // link audio/YouTube yang diisi sendiri oleh pengguna tetap diutamakan.
        $lagu = KatalogLagu::cari($this->v('musLagu'));
        if ($lagu !== null) {
            $awal = SkemaUndangan::bidang();
            $judul = $judul === '' || $judul === ($awal['musJudul']['v'] ?? null) ? $lagu->judul : $judul;
            $artis = $artis === '' || $artis === ($awal['musArtis']['v'] ?? null) ? ($lagu->keterangan ?: $lagu->artis) : $artis;
            $sumber ??= $this->urlAman((string) $lagu->urlAudio(), true);
            $yt ??= $this->urlAman((string) $lagu->urlYoutube());
        }
        if (! $this->edit && $judul === '' && $sumber === null && $yt === null) {
            return '';
        }
        $kartu = '<span class="uy-bulat" aria-hidden="true">'.$this->svg('musik', 26).'</span>'
            .'<div class="uy-musik-judul" style="font-family:'.$this->hf.'"'.($this->edit ? ' data-kunci="musLagu"' : '').'>'.e($judul !== '' ? $judul : 'Belum memilih lagu').'</div>'
            .($artis !== '' ? '<div class="uy-musik-meta">'.e($artis).'</div>' : '');
        if (! $this->edit) {
            $label = $this->v('musLabelOff') !== '' ? $this->v('musLabelOff') : 'Play';
            $labelOn = $this->v('musLabelOn') !== '' ? $this->v('musLabelOn') : 'Pause';
            $kartu .= '<button type="button" class="uy-tombol-kecil" data-uy-aksi="musik" data-label="'.e($label).'" data-label-on="'.e($labelOn).'">'.e($label).'</button>';
        } elseif ($yt !== null) {
            $kartu .= '<span class="uy-chip uy-biru" data-kunci="musLink">'.e($this->pendekUrl($yt)).'</span>';
        }
        $data = ' data-uy-bagian="musik" data-auto="'.($this->v('musAuto') === '1' ? '1' : '0').'"'
            .($sumber !== null ? ' data-sumber="'.e($sumber).'"' : '').($yt !== null ? ' data-youtube="'.e($yt).'"' : '');

        return $this->teks('musTitle').'<div class="uy-kartu uy-timbul"'.$data.'>'.$kartu.'</div>';
    }

    private function bRundown(): string
    {
        $h = $this->teks('rdTitle');
        $h .= $this->edit ? '' : $this->teks('rdDesk', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13]);
        $baris = '';
        $ids = $this->daftar('rd');
        foreach ($ids as $n => $id) {
            $t = $this->teks("rd{$id}_t");
            $l = $this->teks("rd{$id}_l");
            if (! $this->edit && $t === '' && $l === '') {
                continue;
            }
            $akhir = $n === count($ids) - 1;
            $baris .= '<div class="uy-rd'.($akhir ? '' : ' uy-rd-garis').'"'.$this->itemAttr('rd', $id).'>'.$t.$l.'</div>';
        }
        if ($baris === '') {
            if (! $this->edit) {
                return '';
            }
            $baris = '<div class="uy-kosong-kotak">Belum ada sesi. Tambahkan sesi pertama.</div>';
        }

        return $h.'<div class="uy-rd-kartu">'.$baris.'</div>'
            .($this->edit ? '' : $this->teks('rdCatatan', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13]));
    }

    private function bTurut(): string
    {
        $h = $this->teks('tuTitle');
        $ada = false;
        foreach (['tp' => 'tuJudulPria', 'tw' => 'tuJudulWanita'] as $tpl => $kj) {
            $kartu = '';
            foreach ($this->daftar($tpl) as $id) {
                $dalam = $this->teks("{$tpl}{$id}_nama").$this->teks("{$tpl}{$id}_ket");
                if (! $this->edit && $this->v("{$tpl}{$id}_nama") === '') {
                    continue;
                }
                $kartu .= '<div class="uy-kartu uy-polos uy-kecilpad"'.$this->itemAttr($tpl, $id).'>'.$dalam.'</div>';
            }
            if ($kartu === '') {
                if (! $this->edit) {
                    continue;
                }
                $kartu = '<div class="uy-kosong-kotak">Belum ada nama.</div>';
            }
            $ada = true;
            $judul = $this->v($kj) !== '' ? $this->v($kj) : ($tpl === 'tp' ? 'Keluarga mempelai pria' : 'Keluarga mempelai wanita');
            $h .= '<div class="uy-grup"><div class="uy-label" style="color:var(--uy-acc)">'.e($judul).'</div>'.$kartu.'</div>';
        }

        return $ada ? $h : '';
    }

    private function bKado(): string
    {
        $h = $this->teks('kdTitle').$this->teks('kdDesc');
        $kartu = '';
        foreach ($this->daftar('kd') as $id) {
            $no = $this->v("kd{$id}_no");
            $dalam = $this->teks("kd{$id}_bank").$this->teks("kd{$id}_no").$this->teks("kd{$id}_atas");
            if (! $this->edit && $this->v("kd{$id}_bank") === '' && $no === '') {
                continue;
            }
            $salin = ! $this->edit && $no !== ''
                ? '<button type="button" class="uy-tombol-kecil uy-terbalik" data-uy-aksi="salin" data-salin="'.e(preg_replace('/\s+/', '', $no)).'">Salin</button>'
                : '';
            $kartu .= '<div class="uy-kartu uy-rekening"'.$this->itemAttr('kd', $id).'>'.$dalam.$salin.'</div>';
        }
        $alamat = '';
        if (! $this->edit && $this->v('kdAlamat') !== '') {
            $alamat = '<div class="uy-kartu uy-timbul">'
                .$this->teks('kdPenerima', 'div', '', '', ['ta' => 'center', 'w' => 700])
                .$this->teks('kdAlamat', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13])
                .$this->teks('kdTelp', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13])
                .'<button type="button" class="uy-tombol-kecil" data-uy-aksi="salin" data-salin="'.e($this->v('kdAlamat')).'">'.e($this->v('kdBtnSalin') !== '' ? $this->v('kdBtnSalin') : 'Salin Alamat').'</button>'
                .'</div>';
        }
        if ($kartu === '' && $alamat === '') {
            if (! $this->edit) {
                return '';
            }
            $kartu = '<div class="uy-kosong-kotak">Belum ada rekening. Tambahkan rekening untuk kado digital.</div>';
        }

        return $h.'<div class="uy-grup" data-uy-bagian="hadiah">'.$kartu.$alamat.'</div>';
    }

    private function bSiaran(): string
    {
        $plat = self::PLATFORM_SIARAN[$this->v('lvPlat')] ?? 'Siaran langsung';
        $link = $this->urlAman($this->v('lvLink'));
        $tombolAktif = $this->v('lvBtn') === '1';
        $jadwal = $this->jadwal($this->v('lvTgl'), $this->v('lvMulai'), '');
        $kartu = '<span class="uy-bulat uy-merah" aria-hidden="true">'.$this->svg('video', 26).'</span>'
            .'<span class="uy-chip uy-tint"'.($this->edit ? ' data-kunci="lvPlat"' : '').'>'.e($plat).'</span>'
            .($jadwal !== '' ? '<div class="uy-jadwal"'.($this->edit ? ' data-kunci="lvTgl"' : '').'>'.e($jadwal).'</div>' : '')
            .$this->teks('lvCat');
        $teksTombol = $this->v('lvBtnText') !== '' ? $this->v('lvBtnText') : 'Tonton Siaran Langsung';
        if ($tombolAktif && $link !== null) {
            $kartu .= $this->edit
                ? '<div class="uy-tombol uy-tombol-pil" data-kunci="lvBtnText">'.e($teksTombol).'</div>'
                : '<a class="uy-tombol uy-tombol-pil" href="'.e($link).'" target="_blank" rel="noopener noreferrer">'.e($teksTombol).'</a>';
        } elseif ($this->edit) {
            $kartu .= $tombolAktif
                ? '<span class="uy-chip uy-kuning" data-kunci="lvLink">Tambah link siaran</span>'
                : '<span class="uy-chip uy-abu" data-kunci="lvBtn">Tombol siaran dimatikan</span>';
        } elseif ($tombolAktif) {
            $kartu .= $this->teks('lvTunggu', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13]);
        }

        return $this->teks('lvTitle').'<div class="uy-kartu uy-timbul">'.$kartu.'</div>';
    }

    private function bKalender(): string
    {
        $aktif = $this->v('gcAktif') === '1';
        $url = $this->urlKalender();
        $jadwal = $this->jadwal($this->v('gcTgl'), $this->v('gcMulai'), $this->v('gcSelesai'));
        if (! $this->edit && ! $aktif) {
            return '';
        }
        $kartu = '<span class="uy-bulat" aria-hidden="true">'.$this->svg('kalender', 26).'</span>'
            .$this->teks('gcJudul')
            .($jadwal !== '' ? '<div class="uy-jadwal"'.($this->edit ? ' data-kunci="gcTgl"' : '').'>'.e($jadwal).'</div>' : '')
            .$this->teks('gcLokasi');
        $label = $this->v('gcBtn') !== '' ? $this->v('gcBtn') : 'Tambah ke Google Calendar';
        if ($aktif && $url !== '') {
            $kartu .= $this->edit
                ? '<div class="uy-tombol uy-tombol-pil" data-kunci="gcBtn">'.e($label).'</div>'
                : '<a class="uy-tombol uy-tombol-pil" href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.e($label).'</a>';
        } elseif ($this->edit) {
            $kartu .= $aktif
                ? '<span class="uy-chip uy-kuning" data-kunci="gcTgl">Lengkapi tanggal dan jam</span>'
                : '<span class="uy-chip uy-abu" data-kunci="gcAktif">Tombol kalender dimatikan</span>';
        }

        return $this->teks('gcTitle').'<div class="uy-kartu uy-timbul">'.$kartu.'</div>';
    }

    private function bSosial(): string
    {
        $baris = '';
        foreach ($this->daftar('ak') as $id) {
            $user = $this->v("ak{$id}_username");
            $plat = $this->v("ak{$id}_platform");
            if (! $this->edit && $user === '') {
                continue;
            }
            $href = $this->urlSosial($plat, $user);
            $nama = $this->teks("ak{$id}_username", ! $this->edit && $href ? 'a' : 'div', '', ! $this->edit && $href ? ' href="'.e($href).'" target="_blank" rel="noopener noreferrer"' : '');
            $baris .= '<div class="uy-kartu uy-baris"'.$this->itemAttr('ak', $id).'><span class="uy-chip uy-tint"'.($this->edit ? ' data-kunci="ak'.$id.'_platform"' : '').'>'.e(self::PLATFORM_SOSIAL[$plat] ?? 'Akun').'</span>'.$nama.'</div>';
        }
        if ($baris === '' && $this->edit) {
            $baris = '<div class="uy-kosong-kotak">Belum ada akun. Tambahkan akun media sosial.</div>';
        }
        $hash = $this->v('scHashOn') === '1' ? $this->teks('scHash') : '';
        if ($baris === '' && $hash === '') {
            return '';
        }

        return $this->teks('scTitle').$baris.$hash;
    }

    private function bDresscode(): string
    {
        $titik = '';
        foreach ($this->daftar('wr') as $id) {
            $hex = $this->hexAman($this->v("wr{$id}_hex"));
            if (! $this->edit && $hex === null) {
                continue;
            }
            $titik .= '<div class="uy-warna"'.$this->itemAttr('wr', $id).'><span class="uy-titik" style="background:'.($hex ?? '#CCCCCC').'"'.($this->edit ? ' data-kunci="wr'.$id.'_hex"' : '').'></span>'.$this->teks("wr{$id}_nama").'</div>';
        }
        $hindari = '';
        if ($this->v('dcHindari') !== '' || $this->edit) {
            $hindari = '<div class="uy-grup"><span class="uy-label">Hindari</span>'.$this->teks('dcHindari').'</div>';
        }
        $isi = $this->teks('dcTema').$this->teks('dcCat').($titik !== '' ? '<div class="uy-palet">'.$titik.'</div>' : '').$hindari;
        if (trim(strip_tags($isi)) === '' && $titik === '') {
            return '';
        }
        if ($titik === '' && $this->edit) {
            $isi = $this->teks('dcTema').$this->teks('dcCat').'<div class="uy-kosong-kotak">Belum ada warna. Tambahkan palet dresscode.</div>'.$hindari;
        }

        return $this->teks('dcTitle').$isi;
    }

    private function bRsvp(): string
    {
        $kotak = fn (string $label, string $ph = '', string $tambah = '') => '<span class="uy-fl">'.e($label).'</span><span class="uy-fi">'.$tambah.e($ph).'</span>';
        $form = $kotak($this->v('rsLabelNama'), $this->v('rsPhNama'))
            .$kotak($this->v('rsLabelWa'), $this->v('rsPhWa'))
            .'<span class="uy-fl">'.e($this->v('rsLabelHadir')).'</span><span class="uy-fpilih"><i>Hadir</i><i>Tidak hadir</i></span>'
            .$kotak($this->v('rsLabelJumlah'), '1')
            .$kotak($this->v('rsLabelPesan'), $this->v('rsPhPesan'))
            .'<span class="uy-tombol uy-tombol-pil">'.e($this->v('rsBtn') !== '' ? $this->v('rsBtn') : 'Kirim').'</span>'
            .($this->v('rsCatatan') !== '' ? '<span class="uy-fcatatan">'.e($this->v('rsCatatan')).'</span>' : '');

        $ucapan = '';
        foreach (array_slice((array) ($this->opsi['ucapan'] ?? []), 0, 50) as $u) {
            if (! is_array($u) || trim((string) ($u['pesan'] ?? '')) === '') {
                continue;
            }
            $ucapan .= '<div class="uy-ucapan"><div class="uy-ucapan-k"><b>'.e((string) ($u['nama'] ?? '')).'</b><span>'.e((string) ($u['waktu'] ?? '')).'</span></div><div>'.e((string) $u['pesan']).'</div></div>';
        }
        if ($ucapan === '' && ! $this->edit) {
            $ucapan = $this->teks('wsKosong', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13]);
        }

        // Di halaman tamu kartu ini membuka lembar isian (skrip publik); beri peran tombol agar bisa dipakai papan ketik.
        $tombolLembar = $this->edit ? '' : ' role="button" tabindex="0"';

        return $this->teks('rsTitle').$this->teks('rsNote')
            .'<div class="uy-kartu uy-timbul uy-form" data-uy-bagian="rsvp"'.$tombolLembar.'>'.$form.'</div>'
            .'<div class="uy-jarak"></div>'
            .$this->teks('wsTitle').$this->teks('wsNote')
            .'<div class="uy-kartu uy-timbul uy-form" data-uy-bagian="ucapan"'.$tombolLembar.'><span class="uy-fi">'.e($this->v('rsPhPesan')).'</span><span class="uy-tombol uy-tombol-pil">'.e($this->v('wsBtn') !== '' ? $this->v('wsBtn') : 'Kirim').'</span></div>'
            .($ucapan !== '' ? '<div class="uy-ucapan-daftar">'.$ucapan.'</div>' : '');
    }

    private function bTamu(): string
    {
        $tamu = $this->opsi['tamu'] ?? null;
        if (! $this->edit && (! is_array($tamu) || (array_key_exists('qr', $tamu) && trim((string) $tamu['qr']) === ''))) {
            return '';
        }
        $tamu = is_array($tamu) ? $tamu : [];
        $nama = trim((string) ($tamu['nama'] ?? '')) ?: ($this->edit ? $this->v('tmNama') : $this->v('tmFallback'));
        $alamat = trim((string) ($tamu['alamat'] ?? ($this->edit ? $this->v('tmAlamat') : '')));
        $kode = trim((string) ($tamu['kode'] ?? ($this->edit ? $this->v('tmKode') : '')));

        $kartu = '<span class="uy-qr" aria-hidden="true" data-uy-bagian="qr">'.(! empty($tamu['qr']) ? KodeQr::svg((string) $tamu['qr'], 116) : $this->svgQr()).'</span>'
            .($this->v('qrLabel') !== '' ? '<span class="uy-fl uy-tengah">'.e($this->v('qrLabel')).'</span>' : '')
            .($this->v('sapaan') !== '' ? '<span class="uy-label uy-tengah">'.e($this->v('sapaan')).'</span>' : '')
            .'<span class="uy-tamu-nama">'.e($nama !== '' ? $nama : 'Nama tamu').'</span>'
            .($alamat !== '' ? '<span class="uy-tamu-s">'.e($alamat).'</span>' : '')
            .($kode !== '' ? '<span class="uy-tamu-kode">'.e($kode).'</span>' : '')
            .($this->v('tmSuffix') !== '' ? '<span class="uy-tamu-s">'.e($this->v('tmSuffix')).'</span>' : '');

        return $this->teks('qrTitle').$this->teks('qrNote').'<div class="uy-kartu uy-timbul">'.$kartu.'</div>';
    }

    private function bPenutup(): string
    {
        $isi = $this->foto('penutup', 'bulat')
            .$this->teks('ptText').$this->teks('ptKel').$this->teks('ptNama');

        return $isi.'<div class="uy-merek">Dibuat dengan undangyah.com</div>';
    }

    // ------------------------------------------------------------------ elemen dasar

    /**
     * Teks sebuah bidang. Kosong disembunyikan (kecuali mode edit, yang menampilkan petunjuk).
     *
     * @param  array<string,mixed>  $timpa  timpaan gaya meta (mis. untuk bidang tamu-saja)
     */
    private function teks(string $k, string $tag = 'div', string $kelas = '', string $attr = '', array $timpa = []): string
    {
        $m = $this->meta($k);
        if ($m === null || ! empty($this->isi['_sembunyi'][$k])) {
            return '';
        }
        $kanvas = (bool) ($m['canvas'] ?? true);
        if ($this->edit && ! $kanvas && $timpa === []) {
            return '';
        }
        $val = $this->v($k);
        if ($val === '' && ! $this->edit) {
            return '';
        }
        $kosong = $val === '';
        $kunci = $this->edit && $kanvas ? ' data-kunci="'.e($k).'"' : '';
        $cls = trim('uy-t '.$kelas.($kosong ? ' uy-kosong' : ''));
        $ph = $kosong ? ' data-ph="'.e((string) $m['label']).'"' : '';

        return '<'.$tag.' class="'.$cls.'"'.$kunci.$ph.$attr.' style="'.e($this->gaya($k, $m, $timpa)).'">'.e($val).'</'.$tag.'>';
    }

    /** @return array<string,mixed>|null */
    private function meta(string $k): ?array
    {
        if (array_key_exists($k, $this->cacheMeta)) {
            return $this->cacheMeta[$k];
        }
        $hasil = null;
        $bidang = SkemaUndangan::bidang();
        if (isset($bidang[$k])) {
            $hasil = array_merge(self::DASAR, $bidang[$k]);
        } elseif (preg_match('/^([a-z]{2})(\d+)_([A-Za-z]+)$/', $k, $mm) && isset(SkemaUndangan::templat()[$mm[1]])) {
            foreach (SkemaUndangan::templat()[$mm[1]]['f'] as $f) {
                if ($f[0] === $mm[3]) {
                    $hasil = array_merge(self::DASAR, ['label' => $f[1], 'v' => $f[2]], $f[3] ?? []);
                }
            }
        }

        return $this->cacheMeta[$k] = $hasil;
    }

    private function v(string $k): string
    {
        $x = $this->isi[$k] ?? '';

        return is_scalar($x) ? trim((string) $x) : '';
    }

    /** @return list<int> */
    private function daftar(string $tpl): array
    {
        $ids = $this->isi['_daftar'][$tpl] ?? [];

        return is_array($ids) ? array_values(array_filter(array_map('intval', $ids), fn ($i) => $i > 0)) : [];
    }

    private function itemAttr(string $tpl, int $id): string
    {
        return $this->edit ? ' data-item="'.$tpl.':'.$id.'"' : '';
    }

    /** @param array<string,mixed> $m */
    private function gaya(string $k, array $m, array $timpa = []): string
    {
        $m = array_merge($m, $timpa);
        $o = is_array($this->isi['_gaya'][$k] ?? null) ? $this->isi['_gaya'][$k] : [];
        $fs = isset($o['fs']) && is_numeric($o['fs']) ? max(8, min(96, (int) $o['fs'])) : max(8, min(96, (int) round((int) $m['fs'] * $this->skala)));
        $w = ! isset($o['b']) ? (int) $m['w'] : ($o['b'] ? 700 : 400);
        $ta = in_array($o['ta'] ?? $m['ta'], ['left', 'center', 'right'], true) ? ($o['ta'] ?? $m['ta']) : 'left';
        $c = $this->warna((string) ($o['c'] ?? $m['c']));
        $ls = preg_match('/^-?\d*\.?\d+(px|em)?$/', (string) $m['ls']) ? (string) $m['ls'] : '0';

        $css = 'font-family:'.($m['ff'] === 'serif' ? $this->hf : self::SANS).';font-size:'.$fs.'px;line-height:'.round($fs * (float) $m['lh']).'px;'
            .'font-weight:'.$w.';letter-spacing:'.$ls.';text-align:'.$ta.';color:'.$c.';'
            .(! empty($m['up']) ? 'text-transform:uppercase;' : '');
        if ($m['ex'] !== '') {
            $css .= strtr((string) $m['ex'], ['%tint%' => $this->w['tint'], '%acc%' => $this->w['acc'], '%dep%' => $this->w['dep'], '%btn%' => $this->w['btn']]);
            if (str_contains((string) $m['ex'], 'height:')) {
                $css .= 'display:inline-flex;align-items:center;justify-content:center;text-decoration:none;';
            }
        }

        return $css;
    }

    private function warna(string $c): string
    {
        return match ($c) {
            'ink', 'mut', 'acc' => 'var(--uy-'.$c.')',
            'onA' => 'var(--uy-ona)',
            'onAcc' => 'var(--uy-onacc)',
            'btnTx' => 'var(--uy-btntx)',
            default => $this->hexAman($c) ?? 'var(--uy-ink)',
        };
    }

    private function hexAman(string $c): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', trim($c)) ? strtoupper(trim($c)) : null;
    }

    /** Hanya http/https dengan host. Mengembalikan null bila tidak aman. */
    private function urlAman(string $u, bool $gambar = false): ?string
    {
        $u = trim($u);
        if ($u === '' || strlen($u) > 2000 || preg_match('/[\s\x00-\x1F\x7F\\\\<>"\']/', $u)) {
            return null;
        }
        if ($gambar && preg_match('#^/(?!/)[^?\#]*#', $u)) {
            return $u;
        }
        $p = parse_url($u);
        if (! is_array($p) || ! in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) || empty($p['host']) || ! str_contains($p['host'], '.')) {
            return null;
        }

        return $u;
    }

    private function pendekUrl(string $u): string
    {
        $x = rtrim((string) preg_replace('#^https?://#i', '', $u), '/');

        return mb_strlen($x) > 30 ? mb_substr($x, 0, 29).'…' : $x;
    }

    // ------------------------------------------------------------------ foto

    private function sumberFoto(string $slot): ?array
    {
        $f = $this->isi['_foto'][$slot] ?? null;
        $src = is_array($f) ? ($f['src'] ?? '') : $f;
        $src = is_string($src) ? $this->urlAman($src, true) : null;
        if ($src === null) {
            return null;
        }

        return ['src' => $src, 'crop' => is_array($f) && is_array($f['crop'] ?? null) ? $f['crop'] : null];
    }

    private function gayaGambar(?array $crop): string
    {
        $css = 'position:absolute;top:0;left:0;width:100%;height:100%;display:block;object-fit:'.(($crop['ratio'] ?? '') === 'asli' ? 'contain' : 'cover').';';
        if ($crop) {
            $x = max(0, min(100, (float) ($crop['x'] ?? 50)));
            $y = max(0, min(100, (float) ($crop['y'] ?? 50)));
            $z = max(1, min(3, (float) ($crop['zoom'] ?? 100) / 100));
            $css .= "object-position:{$x}% {$y}%;transform:scale({$z});transform-origin:{$x}% {$y}%;";
        }

        return $css;
    }

    private const FOTO_LABEL = ['cover' => 'Tambah foto sampul', 'latar' => 'Tambah foto latar pembuka', 'penutup' => 'Tambah foto', 'banner' => ''];

    private function foto(string $slot, string $jenis, float $rasio = 1.0): string
    {
        $f = $this->sumberFoto($slot);
        if ($f === null && ! $this->edit) {
            return '';
        }
        $isi = $f
            ? '<img src="'.e($f['src']).'" alt="" loading="lazy" decoding="async" style="'.e($this->gayaGambar($f['crop'])).'">'
            : '<span class="uy-foto-kosong">'.e(self::FOTO_LABEL[$slot] ?? 'Tambah foto').'</span>';
        $gaya = $jenis === 'galeri' ? ' style="aspect-ratio:'.$rasio.' / 1"' : ($jenis === 'banner' ? ' style="aspect-ratio:4 / 3"' : '');

        return '<div class="uy-foto uy-foto-'.$jenis.($f ? '' : ' uy-foto-ph').'"'.$gaya.($this->edit ? ' data-kunci="foto:'.e($slot).'"' : '').'>'.$isi.'</div>';
    }

    private function fotoOrang(string $p, string $panggilan): string
    {
        $f = $this->sumberFoto($p);
        if ($f === null) {
            $ini = mb_strtoupper(mb_substr($panggilan !== '' ? $panggilan : '?', 0, 1));

            return '<div class="uy-foto uy-foto-orang uy-foto-ph"'.($this->edit ? ' data-kunci="foto:'.$p.'"' : '').'><span class="uy-inisial" style="font-family:'.$this->hf.'">'.e($ini).'</span>'.($this->edit ? '<span class="uy-foto-kosong">Tambah foto</span>' : '').'</div>';
        }

        return $this->foto($p, 'orang');
    }

    // ------------------------------------------------------------------ elemen khusus

    private function akunInstagram(string $k): string
    {
        $v = $this->v($k);
        if ($this->edit || $v === '' || ! preg_match('/^@?([A-Za-z0-9._]{1,30})$/', $v, $m)) {
            return $this->teks($k);
        }

        return $this->teks($k, 'a', '', ' href="https://instagram.com/'.e($m[1]).'" target="_blank" rel="noopener noreferrer"');
    }

    private function urlSosial(string $plat, string $user): ?string
    {
        if (! preg_match('/^@?([A-Za-z0-9._-]{1,50})$/', $user, $m)) {
            return null;
        }
        $u = $m[1];

        return match ($plat) {
            'instagram' => 'https://instagram.com/'.$u,
            'tiktok' => 'https://www.tiktok.com/@'.$u,
            'facebook' => 'https://facebook.com/'.$u,
            'youtube' => 'https://www.youtube.com/@'.$u,
            'x' => 'https://x.com/'.$u,
            default => null,
        };
    }

    private function tanggalIso(string $s): ?Carbon
    {
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return Carbon::create((int) $m[1], (int) $m[2], (int) $m[3], 0, 0, 0, 'Asia/Jakarta');
    }

    private function jadwal(string $tgl, string $mulai, string $selesai): string
    {
        $d = $this->tanggalIso($tgl);
        $jam = fn (string $t) => preg_match('/^\d{2}:\d{2}$/', $t) ? str_replace(':', '.', $t) : '';
        $teks = $d ? self::HARI[$d->dayOfWeek].', '.$d->day.' '.self::BULAN[$d->month - 1].' '.$d->year : '';
        $a = $jam($mulai);
        $b = $jam($selesai);
        $waktu = $a !== '' && $b !== '' ? "$a – $b WIB" : ($a !== '' && $selesai === '' ? "$a WIB" : '');

        return trim($teks.($teks !== '' && $waktu !== '' ? ' · ' : '').$waktu);
    }

    private function urlKalender(): string
    {
        $d = $this->tanggalIso($this->v('gcTgl'));
        $a = $this->v('gcMulai');
        $b = $this->v('gcSelesai');
        if (! $d || ! preg_match('/^\d{2}:\d{2}$/', $a) || ! preg_match('/^\d{2}:\d{2}$/', $b)) {
            return '';
        }
        $zona = preg_match('#^[A-Za-z_]+(/[A-Za-z_+-]+)*$#', $this->v('gcZona')) ? $this->v('gcZona') : 'Asia/Jakarta';
        $ds = $d->format('Ymd');

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE&text='.rawurlencode($this->v('gcJudul'))
            .'&dates='.$ds.'T'.str_replace(':', '', $a).'00/'.$ds.'T'.str_replace(':', '', $b).'00&ctz='.rawurlencode($zona)
            .'&location='.rawurlencode($this->v('gcLokasi')).'&details='.rawurlencode($this->v('gcDesk'));
    }

    private function hitungMundur(): string
    {
        $d = $this->tanggalIso($this->v('hmTgl'));
        if ($this->edit || ! $d) {
            return '';
        }
        $jam = preg_match('/^(\d{2}):(\d{2})$/', $this->v('hmJam'), $m) ? [(int) $m[1], (int) $m[2]] : [0, 0];
        $target = $d->copy()->setTime(min(23, $jam[0]), min(59, $jam[1]));
        $sisa = max(0, now('Asia/Jakarta')->diffInSeconds($target, false));
        $cap = $this->teks('hmCap', 'div', '', '', ['ta' => 'center', 'c' => 'mut', 'fs' => 13]);
        if ($sisa <= 0) {
            return $this->teks('hmSelesai', 'div', '', ' data-uy-bagian="hitung-mundur"', ['ta' => 'center', 'fs' => 14, 'w' => 600]);
        }
        $sisa = (int) $sisa;
        $unit = [
            [intdiv($sisa, 86400), $this->v('hmLabelHari') ?: 'Hari'],
            [intdiv($sisa % 86400, 3600), $this->v('hmLabelJam') ?: 'Jam'],
            [intdiv($sisa % 3600, 60), $this->v('hmLabelMenit') ?: 'Menit'],
            [$sisa % 60, $this->v('hmLabelDetik') ?: 'Detik'],
        ];
        $kotak = '';
        foreach ($unit as [$n, $label]) {
            $kotak .= '<div class="uy-hm"><b>'.$n.'</b><span>'.e($label).'</span></div>';
        }

        return $cap.'<div class="uy-hitung" data-uy-bagian="hitung-mundur" data-target="'.e($target->toIso8601String()).'">'.$kotak.'</div>';
    }

    private function latarTetap(): string
    {
        $l = $this->isi['_latarTetap'] ?? null;
        if (! is_array($l) || empty($l['on'])) {
            return '';
        }
        $src = $this->urlAman((string) ($l['src'] ?? ''), true);
        if ($src === null) {
            return '';
        }
        $oo = max(0, min(90, (int) ($l['oo'] ?? 45)));
        $op = max(20, min(100, (int) ($l['op'] ?? 100)));
        $blur = max(0, min(20, (int) ($l['blur'] ?? 0)));
        $oc = $this->hexAman((string) ($l['oc'] ?? '')) ?? '#000000';

        return '<div class="uy-bgf" aria-hidden="true"><div class="uy-bgf-isi"><div class="uy-bgf-gambar" style="opacity:'.($op / 100).($blur ? ';filter:blur('.$blur.'px)' : '').'">'
            .'<img src="'.e($src).'" alt="" loading="lazy"></div><div class="uy-bgf-tutup" style="background:'.$oc.';opacity:'.($oo / 100).'"></div></div></div>';
    }

    private function svg(string $nama, int $ukuran): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="'.$ukuran.'" height="'.$ukuran.'" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'.self::IKON[$nama].'</svg>';
    }

    /** Pola penanda tempat QR untuk pratinjau/editor (QR sungguhan memakai KodeQr bila opsi tamu.qr ada). */
    private function svgQr(): string
    {
        $r = '';
        $finder = function (int $x, int $y) use (&$r) {
            $r .= '<rect x="'.$x.'" y="'.$y.'" width="7" height="7" fill="currentColor"/><rect x="'.($x + 1).'" y="'.($y + 1).'" width="5" height="5" fill="#fff"/><rect x="'.($x + 2).'" y="'.($y + 2).'" width="3" height="3" fill="currentColor"/>';
        };
        $finder(0, 0);
        $finder(14, 0);
        $finder(0, 14);
        foreach ([[9, 1], [11, 2], [9, 4], [12, 5], [9, 9], [11, 10], [14, 9], [16, 11], [18, 9], [20, 12], [9, 13], [12, 14], [15, 15], [18, 16], [10, 17], [13, 19], [16, 19], [19, 19], [9, 20], [1, 9], [3, 11], [5, 9], [6, 12], [2, 13]] as [$x, $y]) {
            $r .= '<rect x="'.$x.'" y="'.$y.'" width="2" height="2" fill="currentColor"/>';
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 21 21" width="96" height="96" shape-rendering="crispEdges">'.$r.'</svg>';
    }

    // ------------------------------------------------------------------ tema & CSS

    private static function rgb(string $hex): array
    {
        $h = ltrim($hex, '#');

        return [hexdec(substr($h, 0, 2)), hexdec(substr($h, 2, 2)), hexdec(substr($h, 4, 2))];
    }

    private static function luminans(string $hex): float
    {
        [$r, $g, $b] = array_map(function ($v) {
            $q = $v / 255;

            return $q <= 0.03928 ? $q / 12.92 : (($q + 0.055) / 1.055) ** 2.4;
        }, self::rgb($hex));

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private static function campur(string $a, string $b, float $t): string
    {
        $x = self::rgb($a);
        $y = self::rgb($b);

        return '#'.strtoupper(implode('', array_map(fn ($i) => str_pad(dechex((int) round($x[$i] + ($y[$i] - $x[$i]) * $t)), 2, '0', STR_PAD_LEFT), [0, 1, 2])));
    }

    /** @return array<string,string|bool> */
    private function warnaTema(?Tema $t): array
    {
        $h = fn (?string $c, string $bawaan) => $this->hexAman((string) $c) ?? $bawaan;
        $utama = $h($t?->warna_utama, '#1D4ED8');
        $aksen = $h($t?->warna_aksen, '#E8C46E');
        $permukaan = $h($t?->warna_permukaan, '#F3F6FB');
        $halaman = $h($t?->warna_halaman, '#FFFFFF');
        $teks = $h($t?->warna_teks, '#10203A');

        $acc = abs(self::luminans($utama) - self::luminans($halaman)) > 0.3 ? $utama : $aksen;
        $terangSampul = self::luminans($utama) > 0.6;
        $kontras = fn (string $c) => self::luminans($c) > 0.42 ? '#10203A' : '#FFFFFF';

        return [
            'id' => (string) ($t?->kode ?? $t?->id ?? ''),
            'acc' => $acc, 'dep' => self::campur($acc, '#000000', 0.25), 'tint' => self::campur($acc, $halaman, 0.88),
            'bgb' => $permukaan, 'page' => $halaman, 'ink' => $teks, 'mut' => self::campur($teks, $halaman, 0.38),
            'cov' => $utama, 'ona' => $kontras($utama), 'onacc' => $kontras($acc),
            'btn' => $terangSampul ? $teks : '#FFFFFF', 'btntx' => $terangSampul ? '#FFFFFF' : $utama,
        ];
    }

    private function variabel(): string
    {
        $w = $this->w;

        return '--uy-acc:'.$w['acc'].';--uy-dep:'.$w['dep'].';--uy-tint:'.$w['tint'].';--uy-bgb:'.$w['bgb'].';--uy-page:'.$w['page']
            .';--uy-ink:'.$w['ink'].';--uy-mut:'.$w['mut'].';--uy-cov:'.$w['cov'].';--uy-ona:'.$w['ona'].';--uy-onacc:'.$w['onacc']
            .';--uy-btn:'.$w['btn'].';--uy-btntx:'.$w['btntx'].';--uy-hf:'.$this->hf.';';
    }

    private function css(): string
    {
        $s = self::SANS;
        $p = '.uy-undangan';

        return <<<CSS
{$p}{position:relative;width:100%;margin:0 auto;background:var(--uy-page);color:var(--uy-ink);font-family:{$s};font-size:14px;line-height:1.35;overflow:clip;text-align:left;-webkit-text-size-adjust:100%}
{$p} *,{$p} *::before,{$p} *::after{box-sizing:border-box}
{$p} a{text-decoration:none}
{$p} img{max-width:100%}
{$p} .uy-bag{position:relative;z-index:1;width:100%;display:flex;flex-direction:column;align-items:center;gap:22px;padding:56px 28px}
{$p} .uy-l-cov{background:var(--uy-cov)}
{$p} .uy-l-bgb{background:var(--uy-bgb)}
{$p} .uy-l-page{background:var(--uy-page)}
{$p}.uy-bgf-on .uy-bag:not(.uy-bag-sampul){background:transparent}
{$p} .uy-bag-sampul{min-height:640px;justify-content:center;gap:12px;padding:56px 28px 60px;overflow:hidden}
{$p} .uy-bag-quotes,{$p} .uy-bag-kado,{$p} .uy-bag-rsvp,{$p} .uy-bag-tamu{gap:14px}
{$p} .uy-bag-rundown{gap:18px}
{$p} .uy-bag-social,{$p} .uy-bag-dresscode{gap:16px}
{$p} .uy-bag-foto{padding:0;gap:0}
{$p} .uy-bag-gallery{padding:56px 24px;gap:18px}
{$p} .uy-bag-penutup{padding:56px 32px 44px;gap:12px}
{$p} .uy-t{position:relative;width:100%;min-width:0;margin:0;padding:3px 6px;border:0;background:transparent;white-space:pre-line;overflow-wrap:anywhere}
{$p} .uy-kecil{opacity:.85}
{$p} .uy-tombol{cursor:pointer;border:0}
{$p} button.uy-t{font:inherit}
{$p} .uy-cincin{position:absolute;inset:0;overflow:hidden;pointer-events:none}
{$p} .uy-cincin i{position:absolute;border-radius:50%}
{$p} .uy-cincin .a{bottom:-110px;left:-70px;width:240px;height:240px;background:rgba(255,255,255,.07)}
{$p} .uy-cincin .b{top:-90px;right:-70px;width:260px;height:260px;border:2px solid rgba(255,255,255,.28)}
{$p} .uy-cincin .c{top:-50px;right:-30px;width:170px;height:170px;border:2px solid rgba(255,255,255,.16)}
{$p} .uy-amp{position:relative;font-family:var(--uy-hf);font-size:30px;line-height:34px;font-weight:500;opacity:.85}
{$p} .uy-bulat{width:56px;height:56px;border-radius:50%;background:var(--uy-tint);color:var(--uy-acc);display:flex;align-items:center;justify-content:center;flex:none}
{$p} .uy-bag-quotes .uy-bulat{width:48px;height:48px}
{$p} .uy-merah{background:#FFE8E8;color:#D83A3A}
{$p} .uy-kartu{position:relative;width:100%;border:2px solid #E2E5E9;border-radius:24px;padding:22px 18px;display:flex;flex-direction:column;align-items:center;gap:8px;background:#fff}
{$p} .uy-polos{background:var(--uy-page)}
{$p} .uy-timbul{box-shadow:0 4px 0 #E2E5E9}
{$p} .uy-kecilpad{padding:10px 12px;border-radius:18px}
{$p} .uy-baris{flex-direction:row;padding:8px 10px;border-radius:18px;gap:8px}
{$p} .uy-baris .uy-t{flex:1}
{$p} .uy-rekening{background:var(--uy-acc);border-color:var(--uy-acc);align-items:flex-start;gap:6px;box-shadow:0 4px 0 var(--uy-dep)}
{$p} .uy-rekening .uy-t{padding-left:0}
{$p} .uy-garis{width:40px;height:2px;background:var(--uy-tint);margin:4px 0}
{$p} .uy-pemisah{font-size:26px;line-height:28px;font-weight:600}
{$p} .uy-label{font-size:11px;line-height:13px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#666C76;text-align:center}
{$p} .uy-grup{width:100%;display:flex;flex-direction:column;align-items:center;gap:8px}
{$p} .uy-foto{position:relative;overflow:hidden;background:var(--uy-tint);flex:none}
{$p} .uy-foto-cover{width:200px;height:250px;border-radius:120px 120px 24px 24px;margin-bottom:10px;background:rgba(255,255,255,.14)}
{$p} .uy-foto-orang{width:112px;height:112px;border-radius:56px;margin-bottom:10px;display:flex;align-items:center;justify-content:center;flex-direction:column}
{$p} .uy-foto-bulat{width:140px;height:140px;border-radius:70px;margin-bottom:10px}
{$p} .uy-foto-banner{width:100%;border-radius:0}
{$p} .uy-foto-galeri{width:100%;border-radius:14px}
{$p} .uy-foto-ph{border:2px dashed var(--uy-acc);color:var(--uy-acc);display:flex;align-items:center;justify-content:center;text-align:center}
{$p} .uy-foto-cover.uy-foto-ph{border-color:rgba(255,255,255,.6);color:var(--uy-ona)}
{$p} .uy-foto-kosong{font-size:11px;line-height:14px;font-weight:700;letter-spacing:.04em;padding:6px}
{$p} .uy-inisial{font-size:38px;line-height:40px;font-weight:600}
{$p} .uy-bag-mempelai .uy-foto-orang.uy-foto-ph{border:0;background:var(--uy-tint)}
{$p} .uy-galeri{width:100%}
{$p} .uy-galeri-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}
{$p} .uy-galeri-masonry{column-count:2;column-gap:8px}
{$p} .uy-galeri-masonry .uy-foto{break-inside:avoid;margin-bottom:8px}
{$p} .uy-galeri-slider{display:flex;gap:10px;overflow-x:auto;scroll-snap-type:x mandatory}
{$p} .uy-galeri-slider .uy-foto{width:74%;scroll-snap-align:center}
{$p} .uy-catatan{width:100%;padding:12px 24px 16px;text-align:center;font-size:12px;font-weight:600;color:#666C76}
{$p} .uy-tautan-kartu{width:100%;border:2px solid #E2E5E9;border-radius:16px;padding:10px 14px;display:flex;align-items:center;gap:12px;box-shadow:0 3px 0 #E2E5E9;color:inherit;background:#fff}
{$p} .uy-tautan-kartu .uy-merah{width:40px;height:40px;border-radius:50%;flex:none;display:flex;align-items:center;justify-content:center}
{$p} .uy-tk-isi{flex:1;min-width:0}
{$p} .uy-tk-isi b{display:block;font-size:13px;line-height:18px}
{$p} .uy-tk-isi span{display:block;font-size:12px;line-height:16px;color:#666C76;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
{$p} .uy-musik-judul{font-size:18px;line-height:24px;font-weight:600;text-align:center}
{$p} .uy-musik-meta{font-size:12px;line-height:16px;color:#666C76;text-align:center}
{$p} .uy-chip{display:inline-block;max-width:100%;padding:4px 12px;border-radius:999px;font-size:12px;line-height:16px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
{$p} .uy-tint{background:var(--uy-tint);color:var(--uy-acc)}
{$p} .uy-biru{background:#E3ECFD;color:#1D4ED8}
{$p} .uy-kuning{background:#FFF4CC;color:#B25E00}
{$p} .uy-abu{background:#ECEEF1;color:#666C76}
{$p} .uy-jadwal{text-align:center;font-size:14px;line-height:19px;font-weight:700}
{$p} .uy-tombol-pil{min-height:48px;padding:0 22px;border-radius:16px;background:var(--uy-acc);color:var(--uy-onacc);box-shadow:0 4px 0 var(--uy-dep);display:inline-flex;align-items:center;justify-content:center;gap:8px;font-size:13px;line-height:18px;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
{$p} .uy-tombol-kecil{border:0;cursor:pointer;min-height:36px;padding:0 16px;border-radius:12px;background:var(--uy-acc);color:var(--uy-onacc);font:inherit;font-size:12px;font-weight:700}
{$p} .uy-terbalik{background:var(--uy-onacc);color:var(--uy-acc)}
{$p} .uy-rekening .uy-tombol-kecil{position:absolute;top:14px;right:14px}
{$p} .uy-ls-daftar{width:100%;display:flex;flex-direction:column}
{$p} .uy-ls{display:flex;gap:14px}
{$p} .uy-ls-rel{flex:none;width:16px;display:flex;flex-direction:column;align-items:center}
{$p} .uy-ls-rel .t{flex:none;width:14px;height:14px;border-radius:50%;background:var(--uy-acc);margin-top:8px}
{$p} .uy-ls-rel .g{flex:1;width:2px;background:var(--uy-tint)}
{$p} .uy-ls-isi{flex:1;min-width:0;display:flex;flex-direction:column;align-items:flex-start;gap:4px;padding-bottom:24px}
{$p} .uy-ls-kepala{width:100%}
{$p} .uy-rd-kartu{width:100%;border:2px solid #E2E5E9;border-radius:20px;padding:6px 14px;background:var(--uy-page);display:flex;flex-direction:column}
{$p} .uy-rd{display:flex;align-items:center;gap:10px;padding:10px 0}
{$p} .uy-rd-garis{border-bottom:2px solid #E2E5E9}
{$p} .uy-palet{width:100%;display:flex;flex-wrap:wrap;justify-content:center;gap:14px 10px}
{$p} .uy-warna{width:96px;display:flex;flex-direction:column;align-items:center;gap:4px}
{$p} .uy-titik{width:56px;height:56px;border-radius:50%;border:2px solid #E2E5E9;display:block}
{$p} .uy-form{gap:8px}
{$p} .uy-fl{width:100%;font-size:12px;line-height:16px;font-weight:700;text-align:left}
{$p} .uy-fi{display:block;width:100%;min-height:36px;border:2px solid #E2E5E9;border-radius:12px;background:#fff;font-size:12px;line-height:32px;padding:0 10px;color:#9AA0A9;text-align:left;overflow:hidden;white-space:nowrap;text-overflow:ellipsis}
{$p} .uy-fpilih{width:100%;display:flex;gap:8px}
{$p} .uy-fpilih i{flex:1;font-style:normal;text-align:center;border:2px solid #E2E5E9;border-radius:12px;font-size:12px;line-height:32px;font-weight:700;color:#666C76}
{$p} .uy-fcatatan{width:100%;background:var(--uy-tint);border-radius:14px;padding:10px 12px;font-size:12px;line-height:17px;font-weight:600;text-align:center}
{$p} .uy-jarak{height:10px}
{$p} .uy-ucapan-daftar{width:100%;display:flex;flex-direction:column;gap:8px}
{$p} .uy-ucapan{width:100%;background:#fff;border:2px solid #E2E5E9;border-radius:16px;padding:10px 14px;font-size:13px;line-height:18px;white-space:pre-line;overflow-wrap:anywhere}
{$p} .uy-ucapan-k{display:flex;justify-content:space-between;gap:8px;margin-bottom:2px}
{$p} .uy-ucapan-k span{font-size:11px;color:#666C76}
{$p} .uy-qr{width:132px;height:132px;border-radius:16px;border:2px solid #E2E5E9;background:#fff;color:var(--uy-ink);display:flex;align-items:center;justify-content:center}
{$p} .uy-tamu-nama{font-size:18px;line-height:24px;font-weight:700;text-align:center;overflow-wrap:anywhere}
{$p} .uy-tamu-s{font-size:13px;line-height:18px;color:#666C76;text-align:center}
{$p} .uy-tamu-kode{font-size:12px;line-height:16px;font-weight:700;color:var(--uy-acc)}
{$p} .uy-hitung{width:100%;display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}
{$p} .uy-hm{border:2px solid #E2E5E9;border-radius:16px;background:var(--uy-page);padding:10px 4px;text-align:center}
{$p} .uy-hm b{display:block;font-size:22px;line-height:26px;font-weight:700;color:var(--uy-acc);font-variant-numeric:tabular-nums}
{$p} .uy-hm span{display:block;font-size:11px;line-height:14px;font-weight:600;color:#666C76}
{$p} .uy-merek{margin-top:14px;font-size:11px;line-height:13px;font-weight:600;color:#666C76}
{$p} .uy-kosong-kotak{width:100%;border:2px dashed #E2E5E9;border-radius:16px;padding:14px;text-align:center;font-size:13px;line-height:18px;font-weight:500;color:#666C76}
{$p} .uy-bgf{position:absolute;inset:0;overflow:clip;pointer-events:none;z-index:0}
{$p} .uy-bgf-isi{position:sticky;top:0;width:100%;height:100vh;overflow:hidden}
{$p} .uy-bgf-gambar,{$p} .uy-bgf-tutup{position:absolute;inset:0}
{$p} .uy-bgf-gambar img{width:100%;height:100%;object-fit:cover;display:block}
{$p} .uy-tengah,{$p} .uy-fl.uy-tengah{text-align:center}
{$p} .uy-kosong::before{content:attr(data-ph);opacity:.45}
{$p}[data-uy-edit] [data-kunci]{cursor:pointer}
{$p}[data-uy-edit] [data-kunci]:hover{outline:2px dashed rgba(29,78,216,.55);outline-offset:1px}
{$p}[data-uy-edit] .uy-pilih{outline:2px solid #1D4ED8;outline-offset:1px}
{$p}[data-uy-edit] .uy-bag-sampul .uy-pilih{outline-color:#fff}
CSS;
    }
}
