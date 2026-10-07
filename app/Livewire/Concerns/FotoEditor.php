<?php

namespace App\Livewire\Concerns;

use App\Models\Media;
use App\Models\Undangan;
use App\Services\IsiUndangan;
use App\Services\MediaDitolak;
use App\Services\PenyimpanMedia;
use App\Services\ReferensiMedia;
use App\Support\Editor\Papan;
use App\Support\Editor\PustakaBawaan;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

/**
 * Foto di Editor Undangan: slot foto, galeri, potong gambar, pemilih gambar, dan Background Fixed (gambar, video, slideshow).
 *
 * Disimpan di `isi['_foto'][slot] = {src, mid|sid, crop?}`, `isi['_potong'][ref] = crop` (potongan berlaku untuk semua slot yang
 * memakai foto itu), `isi['_galeri'] = {layout, video}`, `isi['_sampelSembunyi'] = [id sampel]`, dan
 * `isi['_latarTetap'] = {on, kind: image|video|slide, src, mid, vsrc, vmid, vurl, vname, bgs1..bgs4, sd, oc, oo, op, blur}`.
 *
 * Keamanan: klien hanya mengirim id. `src` SELALU diturunkan di server dari `Media::url()` milik pengguna yang sedang masuk
 * (gambar atau, khusus video latar, video) atau dari tabel PustakaBawaan (berkas lokal sistem), jadi URL dari klien tidak pernah
 * disimpan kecuali link video latar yang divalidasi ketat (http/https, berakhir .mp4/.webm). Parameter crop/latar dibatasi rentangnya.
 * Dipakai oleh EditorUndangan (memakai undangan(), simpan(), $daftar).
 */
trait FotoEditor
{
    /** Slot tetap => nama tampil. Slot galeri: "g{id}" untuk id yang ada di daftar `gl`. */
    public const SLOT_TETAP = [
        'cover' => 'Foto sampul', 'latar' => 'Foto latar pembuka', 'penutup' => 'Foto penutup',
        'pria' => 'Foto mempelai pria', 'wanita' => 'Foto mempelai wanita', 'share' => 'Thumbnail saat dibagikan',
    ];

    public const LAYOUT_GALERI = ['grid' => 'Kotak', 'masonry' => 'Mosaik', 'slider' => 'Geser'];

    public const BGF_JENIS = ['image', 'video', 'slide'];

    public const BGF_VIDEO_MAKS_BYTE = 20 * 1024 * 1024;

    public const BGF_VIDEO_MAKS_DETIK = 30;

    private const BATAS_AKSI_FOTO = 120;

    private const MAKS_UNGGAH_SEKALI = 10;

    private const LANGKAH_PUSTAKA = 48;

    /** slot | galeri | bgf | bgs: untuk apa pemilih gambar dibuka. */
    #[Locked]
    public string $modeFoto = 'slot';

    #[Locked]
    public ?string $slotFoto = null;

    /** Pilihan di pemilih: int = id media milik pengguna, string = id sampel bawaan ("i3"). @var list<int|string> */
    #[Locked]
    public array $pilihanFoto = [];

    #[Locked]
    public int $mukaFoto = self::LANGKAH_PUSTAKA;

    public bool $dialogFoto = false;

    public string $cariFoto = '';

    #[Locked]
    public string $tabFoto = 'gallery';

    #[Locked]
    public string $katFoto = 'Semua';

    #[Locked]
    public string $catatanUnggah = '';

    /** Penghapusan dari pustaka yang menunggu "Urungkan": {ref, nama, was: slot => entri}. Berkas baru dihapus saat pemilih ditutup. @var array<string,mixed>|null */
    #[Locked]
    public ?array $hapusTertunda = null;

    /** @var array<int,mixed> */
    public array $unggahFoto = [];

    /** @var list<string> */
    public array $galatFoto = [];

    public bool $dialogCrop = false;

    /** Foto yang sedang dipotong: "m{id}" (media) atau id sampel. */
    #[Locked]
    public ?string $refCrop = null;

    public bool $dialogBgf = false;

    public string $videoGaleri = '';

    public string $galatVideo = '';

    public string $galatBgf = '';

    private function muatFoto(Undangan $u): void
    {
        $g = is_array($u->isi['_galeri'] ?? null) ? $u->isi['_galeri'] : [];
        $this->videoGaleri = is_string($g['video'] ?? null) ? $g['video'] : '';
    }

    // ---------------------------------------------------------------- bantu

    private function slotSah(string $slot): bool
    {
        if (isset(self::SLOT_TETAP[$slot])) {
            return true;
        }

        return (bool) preg_match('/^g(\d{1,9})$/', $slot, $m) && in_array((int) $m[1], $this->daftar['gl'] ?? [], true);
    }

    private function slotGaleri(string $slot): bool
    {
        return str_starts_with($slot, 'g') && $slot !== 'g' && ctype_digit(substr($slot, 1));
    }

    private function lajuFotoOk(): bool
    {
        $kunci = 'foto-editor:'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_AKSI_FOTO)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak perubahan foto. Coba lagi sebentar lagi.', tipe: 'galat');

            return false;
        }
        RateLimiter::hit($kunci, 60);

        return true;
    }

    /** Media milik pengguna yang sedang masuk dengan jenis tertentu; selain itu (milik orang lain, aset sistem, jenis lain) null. */
    private function mediaMilik(int $id, string $tipe = Media::GAMBAR): ?Media
    {
        return Media::milik(auth()->user())->where('tipe', $tipe)->find($id);
    }

    private function fotoTersimpan(): array
    {
        $f = $this->undangan()->isi['_foto'] ?? [];

        return is_array($f) ? $f : [];
    }

    /** @return array<string,array> ref => crop */
    private function potongTersimpan(): array
    {
        $p = $this->undangan()->isi['_potong'] ?? [];

        return is_array($p) ? $p : [];
    }

    /** @return list<string> */
    private function sampelSembunyi(): array
    {
        $s = $this->undangan()->isi['_sampelSembunyi'] ?? [];

        return is_array($s) ? array_values(array_filter($s, fn ($x) => is_string($x) && PustakaBawaan::ada($x))) : [];
    }

    private function jumlahTerisiGaleri(): int
    {
        return IsiUndangan::jumlahFotoGaleri($this->undangan()->isi ?? [], $this->daftar);
    }

    private function batasGaleri(): int
    {
        return max(0, app(PenyimpanMedia::class)->batasFotoGaleri(auth()->user()));
    }

    /** Tulis kunci "_..." ke isi undangan lalu simpan dan sinkronkan penanda `dipakai` media pengguna. */
    private function tulisFoto(array $perubahan): void
    {
        $u = $this->undangan();
        $u->isi = array_merge($u->isi ?? [], $perubahan);
        $this->simpan();
        app(ReferensiMedia::class)->sinkronPemilik($u->user_id);
    }

    private function temaMendukungBgf(): bool
    {
        $tema = $this->temaSekarang($this->undangan());

        return $tema !== null && ! empty($tema->latar_tetap);
    }

    // ---------------------------------------------------------------- rujukan foto (media / sampel)

    /** Id pilihan (int media atau string sampel) menjadi rujukan kanonik "m{id}" / "i{n}"; null bila bukan bentuk yang dikenal. */
    private function refDariPilihan(mixed $id): ?string
    {
        if (is_int($id) || (is_string($id) && ctype_digit($id))) {
            return 'm'.(int) $id;
        }

        return is_string($id) && PustakaBawaan::ada($id) ? $id : null;
    }

    private function refDariEntri(mixed $f): ?string
    {
        if (! is_array($f)) {
            return null;
        }
        if (isset($f['mid']) && is_numeric($f['mid'])) {
            return 'm'.(int) $f['mid'];
        }
        if (isset($f['sid']) && is_string($f['sid']) && PustakaBawaan::ada($f['sid'])) {
            return $f['sid'];
        }

        return null;
    }

    /**
     * Entri `_foto` baru ({src, mid|sid}) dari rujukan. Media harus milik pengguna dan bergambar; sampel harus ada dan belum disembunyikan.
     *
     * @return array{src:string,mid?:int,sid?:string}|null
     */
    private function entriDariRef(string $ref): ?array
    {
        if (preg_match('/^m(\d{1,12})$/', $ref, $m)) {
            $media = $this->mediaMilik((int) $m[1]);

            return $media ? ['src' => $media->url(), 'mid' => $media->id] : null;
        }
        if (PustakaBawaan::ada($ref) && ! in_array($ref, $this->sampelSembunyi(), true)) {
            return ['src' => (string) PustakaBawaan::url($ref), 'sid' => $ref];
        }

        return null;
    }

    /** Entri dengan potongan tersimpan milik rujukan itu (potongan berlaku di semua tempat foto dipakai). */
    private function entriDenganPotongan(string $ref): ?array
    {
        $e = $this->entriDariRef($ref);
        if ($e === null) {
            return null;
        }
        $crop = Papan::normalCrop($this->potongTersimpan()[$ref] ?? null);
        if ($crop !== null) {
            $e['crop'] = $crop;
        }

        return $e;
    }

    // ---------------------------------------------------------------- dialog pustaka

    public function bukaFoto(string $slot): void
    {
        if (! $this->slotSah($slot)) {
            return;
        }
        $this->bukaPustaka('slot', $slot);
    }

    public function bukaGaleri(): void
    {
        if ($this->batasGaleri() - $this->jumlahTerisiGaleri() <= 0) {
            $this->dispatch('toast', pesan: 'Batas foto galeri paketmu ('.$this->batasGaleri().') sudah tercapai.', tipe: 'galat');

            return;
        }
        $this->bukaPustaka('galeri', null);
    }

    public function bukaBgf(): void
    {
        if (! $this->temaMendukungBgf() && empty($this->undangan()->isi['_latarTetap']['on'])) {
            $this->dispatch('toast', pesan: 'Tema ini tidak memakai Background Fixed.', tipe: 'info');

            return;
        }
        $this->galatBgf = '';
        $this->dialogBgf = true;
    }

    /** Pilih gambar Background Fixed lewat pemilih (dialog pengaturan tetap terbuka di belakangnya). */
    public function bukaFotoBgf(): void
    {
        $this->bukaPustaka('bgf', null);
    }

    /** Pilih foto slide 1..4 untuk Background Fixed jenis slideshow. */
    public function bukaFotoBgs(int $n): void
    {
        if ($n < 1 || $n > 4) {
            return;
        }
        $this->bukaPustaka('bgs', 'bgs'.$n);
    }

    private function bukaPustaka(string $mode, ?string $slot): void
    {
        $this->selesaikanHapus();
        $this->modeFoto = $mode;
        $this->slotFoto = $slot;
        $sekarang = null;
        if ($mode === 'slot' && $slot !== null) {
            $sekarang = $this->refDariEntri($this->fotoTersimpan()[$slot] ?? null);
        } elseif ($mode === 'bgf') {
            $sekarang = $this->refDariEntri($this->bgf());
        } elseif ($mode === 'bgs' && $slot !== null) {
            $sekarang = $this->refDariEntri($this->bgf()[$slot] ?? null);
        }
        $this->pilihanFoto = $sekarang === null ? [] : [str_starts_with($sekarang, 'm') ? (int) substr($sekarang, 1) : $sekarang];
        $this->cariFoto = '';
        $this->tabFoto = 'gallery';
        $this->katFoto = 'Semua';
        $this->catatanUnggah = '';
        $this->mukaFoto = self::LANGKAH_PUSTAKA;
        $this->galatFoto = [];
        $this->dialogFoto = true;
    }

    public function tutupFoto(): void
    {
        $this->dialogFoto = false;
        $this->pilihanFoto = [];
        $this->catatanUnggah = '';
        $this->galatFoto = [];
        $this->selesaikanHapus();
    }

    public function setTabFoto(string $tab): void
    {
        if (in_array($tab, ['gallery', 'upload', 'drive'], true)) {
            $this->tabFoto = $tab;
        }
    }

    public function setKatFoto(string $kat): void
    {
        if (in_array($kat, Papan::KATEGORI, true)) {
            $this->katFoto = $kat;
            $this->mukaFoto = self::LANGKAH_PUSTAKA;
        }
    }

    public function updatedCariFoto(): void
    {
        $this->cariFoto = mb_substr(trim($this->cariFoto), 0, 60);
        $this->mukaFoto = self::LANGKAH_PUSTAKA;
    }

    public function lebihFoto(): void
    {
        $this->mukaFoto = min(480, $this->mukaFoto + self::LANGKAH_PUSTAKA);
    }

    public function pilihFoto(int|string $id): void
    {
        if (! $this->dialogFoto) {
            return;
        }
        $this->galatFoto = [];
        $ref = $this->refDariPilihan($id);
        $nilai = $ref === null ? null : (str_starts_with($ref, 'm') ? (int) substr($ref, 1) : $ref);
        if ($ref === null || $this->entriDariRef($ref) === null) {
            $this->galatFoto = ['Foto tidak ditemukan di pustakamu.'];

            return;
        }
        $this->catatanUnggah = '';
        if ($this->modeFoto !== 'galeri') {
            $this->pilihanFoto = [$nilai];

            return;
        }
        if (in_array($nilai, $this->pilihanFoto, true)) {
            $this->pilihanFoto = array_values(array_filter($this->pilihanFoto, fn ($x) => $x !== $nilai));

            return;
        }
        $sisa = $this->batasGaleri() - $this->jumlahTerisiGaleri();
        if (count($this->pilihanFoto) >= $sisa) {
            $this->galatFoto = ['Paketmu maksimal '.$this->batasGaleri().' foto galeri; kamu bisa menambah '.max(0, $sisa).' lagi.'];

            return;
        }
        $this->pilihanFoto = [...$this->pilihanFoto, $nilai];
    }

    public function terapkanPilihan(): void
    {
        if (! $this->dialogFoto || ! $this->lajuFotoOk()) {
            return;
        }
        $this->galatFoto = [];
        $refs = [];
        foreach ($this->pilihanFoto as $id) {
            $ref = $this->refDariPilihan($id);
            if ($ref !== null && $this->entriDariRef($ref) !== null && ! in_array($ref, $refs, true)) {
                $refs[] = $ref;
            }
        }
        if ($refs === []) {
            $this->galatFoto = ['Pilih satu foto dulu.'];

            return;
        }

        $galat = match ($this->modeFoto) {
            'galeri' => $this->tambahKeGaleri($refs),
            'bgf' => $this->setGambarBgf($refs[0]),
            'bgs' => $this->setSlideBgf((string) $this->slotFoto, $refs[0]),
            default => $this->setSlot((string) $this->slotFoto, $refs[0]),
        };
        if ($galat !== null) {
            $this->galatFoto = [$galat];

            return;
        }
        $this->tutupFoto();
        $this->dispatch('toast', pesan: $this->modeFoto === 'galeri' ? 'Foto ditambahkan ke galeri.' : 'Foto dipasang.', tipe: 'sukses');
    }

    // ---------------------------------------------------------------- unggah

    public function updatedUnggahFoto(): void
    {
        $berkas = array_slice(array_values((array) $this->unggahFoto), 0, self::MAKS_UNGGAH_SEKALI);
        $lebih = count((array) $this->unggahFoto) - count($berkas);
        $this->unggahFoto = [];
        $user = auth()->user();
        $penyimpan = app(PenyimpanMedia::class);
        $galat = [];
        $baru = [];

        foreach ($berkas as $b) {
            if (! $b instanceof UploadedFile) {
                continue;
            }
            try {
                $baru[] = $penyimpan->simpan($b, $user, ['tipe' => [Media::GAMBAR], 'folder' => 'Foto'])->id;
            } catch (MediaDitolak $e) {
                $galat[] = $e->getMessage();
            } catch (\Throwable $e) {
                report($e);
                $galat[] = 'Foto tidak bisa diproses. Coba file lain.';
            } finally {
                if (method_exists($b, 'delete')) {
                    $b->delete();
                }
            }
        }
        if ($lebih > 0) {
            $galat[] = 'Maksimal '.self::MAKS_UNGGAH_SEKALI." foto sekali unggah; {$lebih} foto diabaikan.";
        }

        $this->catatanUnggah = '';
        if ($baru !== []) {
            if ($this->modeFoto === 'galeri') {
                $sisa = $this->batasGaleri() - $this->jumlahTerisiGaleri();
                $this->pilihanFoto = array_slice(array_values(array_unique([...$this->pilihanFoto, ...$baru], SORT_REGULAR)), 0, max(0, $sisa));
            } else {
                $this->pilihanFoto = [end($baru)];
            }
            $this->tabFoto = 'gallery';
            $this->katFoto = 'Semua';
            $this->cariFoto = '';
            $this->catatanUnggah = count($baru).' foto diunggah dan dipilih.';
        }
        $this->galatFoto = $galat;
    }

    // ---------------------------------------------------------------- hapus dari pustaka (dengan Urungkan)

    /** Hapus foto dari pustaka: tempat yang memakainya dikosongkan, berkas baru benar-benar dihapus saat pemilih ditutup. */
    public function hapusDariPustaka(int|string $id): void
    {
        if (! $this->dialogFoto || ! $this->lajuFotoOk()) {
            return;
        }
        $this->selesaikanHapus();
        $ref = $this->refDariPilihan($id);
        $entri = $ref === null ? null : $this->entriDariRef($ref);
        if ($ref === null || $entri === null) {
            return;
        }
        $nama = isset($entri['mid']) ? (string) Media::milik(auth()->user())->whereKey($entri['mid'])->value('nama') : (string) (PustakaBawaan::ITEM[$ref]['nama'] ?? $ref);

        $foto = $this->fotoTersimpan();
        $was = [];
        foreach ($foto as $slot => $f) {
            if ($this->refDariEntri($f) === $ref) {
                $was[$slot] = $f;
                unset($foto[$slot]);
            }
        }
        $perubahan = ['_foto' => $foto];
        $bgf = $this->bgf();
        $bgfAwal = $this->undangan()->isi['_latarTetap'] ?? null;
        if (is_array($bgfAwal)) {
            $kunciBgf = [];
            foreach (['src' => ['mid', 'sid'], 'bgs1' => null, 'bgs2' => null, 'bgs3' => null, 'bgs4' => null] as $k => $_) {
                $e = $k === 'src' ? $bgf : ($bgf[$k] ?? null);
                if ($this->refDariEntri($e) === $ref) {
                    $kunciBgf[] = $k;
                }
            }
            if ($kunciBgf !== []) {
                $baru = $bgfAwal;
                foreach ($kunciBgf as $k) {
                    $was['_bgf:'.$k] = $k === 'src' ? array_intersect_key($bgfAwal, array_flip(['src', 'mid', 'sid'])) : ($bgfAwal[$k] ?? null);
                    if ($k === 'src') {
                        unset($baru['src'], $baru['mid'], $baru['sid']);
                    } else {
                        unset($baru[$k]);
                    }
                }
                $perubahan['_latarTetap'] = $baru;
            }
        }
        if (isset($entri['sid'])) {
            $perubahan['_sampelSembunyi'] = array_values(array_unique([...$this->sampelSembunyi(), $ref]));
        }
        $this->pilihanFoto = array_values(array_filter($this->pilihanFoto, fn ($x) => $this->refDariPilihan($x) !== $ref));
        $this->hapusTertunda = ['ref' => $ref, 'nama' => $nama, 'was' => $was];
        $this->tulisFoto($perubahan);
    }

    public function urungkanHapusPustaka(): void
    {
        $t = $this->hapusTertunda;
        if ($t === null || ! $this->lajuFotoOk()) {
            return;
        }
        $ref = (string) $t['ref'];
        $foto = $this->fotoTersimpan();
        $perubahan = [];
        $bgf = $this->undangan()->isi['_latarTetap'] ?? null;
        foreach ((array) $t['was'] as $slot => $f) {
            if (str_starts_with((string) $slot, '_bgf:')) {
                if (is_array($bgf) || $bgf === null) {
                    $bgf = (array) $bgf;
                    $k = substr((string) $slot, 5);
                    if ($k === 'src') {
                        $bgf = array_merge($bgf, is_array($f) ? $f : []);
                    } elseif (is_array($f)) {
                        $bgf[$k] = $f;
                    }
                    $perubahan['_latarTetap'] = $bgf;
                }

                continue;
            }
            if ($this->slotSah((string) $slot) && is_array($f)) {
                $foto[$slot] = $f;
            }
        }
        $perubahan['_foto'] = $foto;
        if (PustakaBawaan::ada($ref)) {
            $perubahan['_sampelSembunyi'] = array_values(array_diff($this->sampelSembunyi(), [$ref]));
        }
        $this->hapusTertunda = null;
        $this->tulisFoto($perubahan);
    }

    /** Hapus berkas yang menunggu; bila masih dirujuk (undangan lain/terbitan) berkas dipertahankan dan foto muncul lagi di pustaka. */
    private function selesaikanHapus(): void
    {
        $t = $this->hapusTertunda;
        $this->hapusTertunda = null;
        if ($t === null || ! preg_match('/^m(\d{1,12})$/', (string) $t['ref'], $m)) {
            return;
        }
        $media = $this->mediaMilik((int) $m[1]);
        if ($media === null) {
            return;
        }
        try {
            app(PenyimpanMedia::class)->hapus($media);
        } catch (MediaDitolak) {
            $this->dispatch('toast', pesan: 'Foto masih dipakai di undangan yang sudah terbit atau undangan lain, jadi tetap ada di pustaka.', tipe: 'info');
        }
    }

    // ---------------------------------------------------------------- slot foto

    private function setSlot(string $slot, string $ref): ?string
    {
        if (! $this->slotSah($slot)) {
            return 'Slot foto tidak dikenal.';
        }
        $entri = $this->entriDenganPotongan($ref);
        if ($entri === null) {
            return 'Foto tidak ditemukan di pustakamu.';
        }
        $foto = $this->fotoTersimpan();
        if ($this->slotGaleri($slot) && ! IsiUndangan::fotoAda($this->undangan()->isi ?? [], $slot) && $this->jumlahTerisiGaleri() >= $this->batasGaleri()) {
            return 'Batas foto galeri paketmu ('.$this->batasGaleri().') sudah tercapai.';
        }
        $foto[$slot] = $entri;
        $this->tulisFoto(['_foto' => $foto]);

        return null;
    }

    public function hapusFoto(string $slot): void
    {
        if (! $this->slotSah($slot) || ! $this->lajuFotoOk()) {
            return;
        }
        if ($this->slotGaleri($slot)) {
            $this->hapusSlotGaleri((int) substr($slot, 1));

            return;
        }
        $foto = $this->fotoTersimpan();
        unset($foto[$slot]);
        $this->tulisFoto(['_foto' => $foto]);
        if ($this->terpilih === 'foto:'.$slot) {
            $this->terpilih = null;
        }
    }

    // ---------------------------------------------------------------- potong gambar

    /** Rujukan kanonik dari slot ("cover", "g3") atau rujukan langsung ("m12", "i3"); null bila tak sah atau tak bisa dipotong. */
    private function refUntukCrop(string $x): ?string
    {
        if ($this->slotSah($x)) {
            if ($x === 'share') {
                return null;
            }
            $ref = $this->refDariEntri($this->fotoTersimpan()[$x] ?? null);

            return $ref !== null && $this->entriDariRef($ref) !== null ? $ref : null;
        }
        if (preg_match('/^m\d{1,12}$/', $x) || PustakaBawaan::ada($x)) {
            return $this->entriDariRef($x) !== null ? $x : null;
        }

        return null;
    }

    public function bukaCrop(string $ref): void
    {
        $ref = $this->refUntukCrop($ref);
        if ($ref === null) {
            return;
        }
        $this->refCrop = $ref;
        $this->dialogCrop = true;
    }

    /** Parameter dari kontrol Alpine; apa pun yang bukan angka ditolak, angka di luar rentang dipaksa masuk rentang. */
    public function simpanCrop(mixed $ratio, mixed $zoom, mixed $x, mixed $y): void
    {
        $ref = (string) $this->refCrop;
        if ($this->refUntukCrop($ref) === null || ! $this->lajuFotoOk()) {
            return;
        }
        if (! is_string($ratio) || ! in_array($ratio, [...array_column(Papan::RASIO_CROP, 0), 'bingkai'], true) || ! is_numeric($zoom) || ! is_numeric($x) || ! is_numeric($y)) {
            $this->dispatch('toast', pesan: 'Pengaturan crop tidak valid.', tipe: 'galat');

            return;
        }
        $crop = Papan::normalCrop(['ratio' => $ratio, 'zoom' => $zoom, 'x' => $x, 'y' => $y]);
        $this->terapkanCrop($ref, $crop);
        $this->dialogCrop = false;
        $this->dispatch('toast', pesan: 'Crop diterapkan.', tipe: 'sukses');
    }

    /** Simpan potongan pada rujukan dan salin ke semua slot yang memakai foto itu. */
    private function terapkanCrop(string $ref, ?array $crop): void
    {
        $p = $this->potongTersimpan();
        $foto = $this->fotoTersimpan();
        foreach ($foto as $slot => $f) {
            if (is_array($f) && $this->refDariEntri($f) === $ref) {
                unset($f['crop']);
                if ($crop !== null) {
                    $f['crop'] = $crop;
                }
                $foto[$slot] = $f;
            }
        }
        if ($crop === null) {
            unset($p[$ref]);
        } else {
            $p[$ref] = $crop;
        }
        $this->tulisFoto(['_foto' => $foto, '_potong' => $p]);
    }

    // ---------------------------------------------------------------- galeri

    /** @param  list<string>  $refs */
    private function tambahKeGaleri(array $refs): ?string
    {
        $isi = $this->undangan()->isi ?? [];
        $foto = $this->fotoTersimpan();
        $sisa = $this->batasGaleri() - $this->jumlahTerisiGaleri();
        if ($sisa <= 0) {
            return 'Batas foto galeri paketmu ('.$this->batasGaleri().') sudah tercapai.';
        }
        $ada = [];
        foreach ($this->daftar['gl'] ?? [] as $id) {
            $r = $this->refDariEntri($foto['g'.$id] ?? null);
            if ($r !== null) {
                $ada[$r] = true;
            }
        }

        $daftar = $this->daftar['gl'] ?? [];
        $kosong = array_values(array_filter($daftar, fn ($id) => ! IsiUndangan::fotoAda($isi, 'g'.$id)));
        $dipasang = 0;
        foreach ($refs as $ref) {
            if ($dipasang >= $sisa || isset($ada[$ref])) {
                continue;
            }
            $entri = $this->entriDenganPotongan($ref);
            if ($entri === null) {
                continue;
            }
            if ($kosong !== []) {
                $id = array_shift($kosong);
            } elseif (count($daftar) < IsiUndangan::MAKS_ITEM) {
                $this->daftar = [...$this->daftar, 'gl' => $daftar];
                $id = $this->idBaru();
                $daftar[] = $id;
            } else {
                return 'Galeri sudah penuh (maksimal '.IsiUndangan::MAKS_ITEM.' slot).';
            }
            $foto['g'.$id] = $entri;
            $ada[$ref] = true;
            $dipasang++;
        }
        $this->daftar = [...$this->daftar, 'gl' => array_values($daftar)];
        $this->tulisFoto(['_foto' => $foto]);

        return null;
    }

    public function geserFotoGaleri(int $id, int $arah): void
    {
        $arr = $this->daftar['gl'] ?? [];
        $i = array_search($id, $arr, true);
        if ($i === false || ! in_array($arah, [-1, 1], true) || ! isset($arr[$i + $arah]) || ! $this->lajuFotoOk()) {
            return;
        }
        [$arr[$i], $arr[$i + $arah]] = [$arr[$i + $arah], $arr[$i]];
        $this->daftar = [...$this->daftar, 'gl' => $arr];
        $this->simpan();
    }

    public function hapusSlotGaleri(int $id): void
    {
        $arr = $this->daftar['gl'] ?? [];
        if (! in_array($id, $arr, true)) {
            return;
        }
        $this->daftar = [...$this->daftar, 'gl' => array_values(array_diff($arr, [$id]))];
        $foto = $this->fotoTersimpan();
        unset($foto['g'.$id]);
        if ($this->terpilih === 'foto:g'.$id) {
            $this->terpilih = null;
        }
        $this->tulisFoto(['_foto' => $foto]);
    }

    public function aturLayoutGaleri(string $layout): void
    {
        if (! isset(self::LAYOUT_GALERI[$layout]) || ! $this->lajuFotoOk()) {
            return;
        }
        $g = is_array($this->undangan()->isi['_galeri'] ?? null) ? $this->undangan()->isi['_galeri'] : [];
        $this->tulisFoto(['_galeri' => [...$g, 'layout' => $layout]]);
    }

    public function updatedVideoGaleri(): void
    {
        $this->galatVideo = '';
        $v = trim($this->videoGaleri);
        if ($v !== '' && (mb_strlen($v) > IsiUndangan::MAKS_URL || ! app(IsiUndangan::class)->urlSah($v))) {
            $this->galatVideo = IsiUndangan::PESAN_URL;

            return;
        }
        $this->videoGaleri = $v;
        $g = is_array($this->undangan()->isi['_galeri'] ?? null) ? $this->undangan()->isi['_galeri'] : [];
        $this->tulisFoto(['_galeri' => [...$g, 'video' => $v]]);
    }

    // ---------------------------------------------------------------- Background Fixed

    private function bgfBawaan(): array
    {
        return ['on' => false, 'kind' => 'image', 'vurl' => '', 'vsrc' => '', 'vname' => '', 'sd' => 4, 'oc' => '', 'oo' => 45, 'op' => 100, 'blur' => 0];
    }

    /** `_latarTetap` tersimpan, dinormalkan dengan nilai bawaan dan rentang aman. @return array<string,mixed> */
    private function bgf(): array
    {
        $l = is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : [];
        $b = $this->bgfBawaan();
        $hasil = [
            'on' => ! empty($l['on']),
            'kind' => in_array($l['kind'] ?? '', self::BGF_JENIS, true) ? $l['kind'] : $b['kind'],
            'oc' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($l['oc'] ?? '')) ? strtoupper($l['oc']) : '',
            'oo' => max(0, min(90, (int) ($l['oo'] ?? $b['oo']))),
            'op' => max(20, min(100, (int) ($l['op'] ?? $b['op']))),
            'blur' => max(0, min(20, (int) ($l['blur'] ?? $b['blur']))),
            'sd' => max(2, min(10, (int) ($l['sd'] ?? $b['sd']))),
            'vurl' => is_string($l['vurl'] ?? null) ? $l['vurl'] : '',
            'vsrc' => is_string($l['vsrc'] ?? null) ? $l['vsrc'] : '',
            'vname' => is_string($l['vname'] ?? null) ? mb_substr($l['vname'], 0, 120) : '',
        ];
        foreach (['src', 'mid', 'sid', 'vmid'] as $k) {
            if (isset($l[$k])) {
                $hasil[$k] = $l[$k];
            }
        }
        foreach (['bgs1', 'bgs2', 'bgs3', 'bgs4'] as $k) {
            if (is_array($l[$k] ?? null)) {
                $hasil[$k] = $l[$k];
            }
        }

        return $hasil;
    }

    private function setGambarBgf(string $ref): ?string
    {
        if (! $this->temaMendukungBgf()) {
            return 'Tema ini tidak memakai Background Fixed.';
        }
        $e = $this->entriDariRef($ref);
        if ($e === null) {
            return 'Foto tidak ditemukan di pustakamu.';
        }
        $l = is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : [];
        unset($l['mid'], $l['sid']);
        $this->tulisFoto(['_latarTetap' => [...$this->bgfBawaan(), ...$l, 'kind' => 'image', ...$e]]);

        return null;
    }

    private function setSlideBgf(string $slot, string $ref): ?string
    {
        if (! preg_match('/^bgs[1-4]$/', $slot)) {
            return 'Slot slide tidak dikenal.';
        }
        if (! $this->temaMendukungBgf()) {
            return 'Tema ini tidak memakai Background Fixed.';
        }
        $e = $this->entriDariRef($ref);
        if ($e === null) {
            return 'Foto tidak ditemukan di pustakamu.';
        }
        $l = is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : [];
        $this->tulisFoto(['_latarTetap' => [...$this->bgfBawaan(), ...$l, 'kind' => 'slide', $slot => $e]]);

        return null;
    }

    /**
     * Ubah sebagian pengaturan Background Fixed. Kunci yang dikenal: on, kind, oc, oo, op, blur, sd, vurl, vmid (id media video milik
     * pengguna, dari unggahan video latar), hapusVideo, hapusGambar, lepasSlide (1..4). Semua nilai divalidasi dan dipaksa ke rentang.
     *
     * @param  array<string,mixed>  $ubah
     */
    public function aturBgf(array $ubah): void
    {
        if (! $this->lajuFotoOk()) {
            return;
        }
        $this->galatBgf = '';
        $l = is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : [];
        $baru = [...$this->bgfBawaan(), ...$l];

        foreach ($ubah as $k => $v) {
            switch ($k) {
                case 'on':
                    if (! is_bool($v)) {
                        return $this->galatLatar('Pengaturan Background Fixed tidak valid.');
                    }
                    if ($v && ! $this->temaMendukungBgf()) {
                        return $this->galatLatar('Tema ini tidak memakai Background Fixed.');
                    }
                    $baru['on'] = $v;
                    break;
                case 'kind':
                    if (! is_string($v) || ! in_array($v, self::BGF_JENIS, true)) {
                        return $this->galatLatar('Pengaturan Background Fixed tidak valid.');
                    }
                    $baru['kind'] = $v;
                    break;
                case 'oc':
                    if (! is_string($v) || ($v !== '' && ! preg_match('/^#[0-9a-fA-F]{6}$/', $v))) {
                        return $this->galatLatar('Pengaturan Background Fixed tidak valid.');
                    }
                    $baru['oc'] = strtoupper($v);
                    break;
                case 'oo':
                case 'op':
                case 'blur':
                case 'sd':
                    if (! is_numeric($v)) {
                        return $this->galatLatar('Pengaturan Background Fixed tidak valid.');
                    }
                    [$a, $z] = ['oo' => [0, 90], 'op' => [20, 100], 'blur' => [0, 20], 'sd' => [2, 10]][$k];
                    $baru[$k] = (int) max($a, min($z, round((float) $v)));
                    break;
                case 'vurl':
                    $v = is_string($v) ? trim($v) : null;
                    if ($v === null || mb_strlen($v) > IsiUndangan::MAKS_URL || ($v !== '' && ! $this->linkVideoSah($v))) {
                        return $this->galatLatar('Link video harus diawali https:// dan berakhir .mp4 atau .webm.');
                    }
                    $baru['vurl'] = $v;
                    if ($v !== '') {
                        $baru['vsrc'] = '';
                        $baru['vname'] = '';
                        unset($baru['vmid']);
                    }
                    break;
                case 'vmid':
                    $media = is_int($v) || (is_string($v) && ctype_digit($v)) ? $this->mediaMilik((int) $v, Media::VIDEO) : null;
                    if ($media === null) {
                        return $this->galatLatar('Video tidak ditemukan. Unggah ulang videonya.');
                    }
                    $baru['vsrc'] = $media->url();
                    $baru['vmid'] = $media->id;
                    $baru['vname'] = mb_substr((string) $media->nama, 0, 120);
                    $baru['vurl'] = '';
                    break;
                case 'hapusVideo':
                    $baru['vsrc'] = '';
                    $baru['vname'] = '';
                    $baru['vurl'] = '';
                    unset($baru['vmid']);
                    break;
                case 'hapusGambar':
                    unset($baru['src'], $baru['mid'], $baru['sid']);
                    break;
                case 'lepasSlide':
                    if (! is_int($v) || $v < 1 || $v > 4) {
                        return $this->galatLatar('Pengaturan Background Fixed tidak valid.');
                    }
                    unset($baru['bgs'.$v]);
                    break;
                default:
                    return $this->galatLatar('Pengaturan Background Fixed tidak valid.');
            }
        }
        $this->tulisFoto(['_latarTetap' => $baru]);
    }

    private function galatLatar(string $pesan): void
    {
        $this->galatBgf = $pesan;
        $this->dispatch('toast', pesan: $pesan, tipe: 'galat');
    }

    private function linkVideoSah(string $url): bool
    {
        if (! app(IsiUndangan::class)->urlSah($url)) {
            return false;
        }
        $path = (string) parse_url($url, PHP_URL_PATH);

        return (bool) preg_match('/\.(mp4|webm)$/i', $path);
    }

    /** Kompatibilitas: simpan semua pengaturan latar sekaligus (jenis gambar). */
    public function simpanBgf(mixed $on, mixed $oc, mixed $oo, mixed $op, mixed $blur): void
    {
        if (! is_bool($on) || ! is_string($oc) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $oc) || ! is_numeric($oo) || ! is_numeric($op) || ! is_numeric($blur)) {
            $this->dispatch('toast', pesan: 'Pengaturan Background Fixed tidak valid.', tipe: 'galat');

            return;
        }
        $this->aturBgf(['on' => $on, 'oc' => $oc, 'oo' => $oo, 'op' => $op, 'blur' => $blur]);
    }

    public function hapusBgf(): void
    {
        if (! $this->lajuFotoOk()) {
            return;
        }
        $isi = $this->undangan()->isi ?? [];
        unset($isi['_latarTetap']);
        $u = $this->undangan();
        $u->isi = $isi;
        $this->simpan();
        app(ReferensiMedia::class)->sinkronPemilik($u->user_id);
        $this->galatBgf = '';
    }

    public function tutupBgf(): void
    {
        $this->dialogBgf = false;
        $this->galatBgf = '';
    }

    // ---------------------------------------------------------------- data untuk tampilan

    /**
     * Informasi satu slot/entri foto: ada, src, thumb, nama, crop, ref. Hanya sumber aman (path situs atau http/https) yang tampil.
     *
     * @return array{ada:bool,src:?string,thumb:?string,nama:?string,crop:?array,ref:?string,warna:?string}
     */
    private function infoFoto(mixed $f, ?Media $m): array
    {
        $src = is_array($f) ? (string) ($f['src'] ?? '') : (is_string($f) ? $f : '');
        $aman = $src !== '' && ((str_starts_with($src, '/') && ! str_starts_with($src, '//')) || app(IsiUndangan::class)->urlSah($src));
        $sid = is_array($f) && isset($f['sid']) && is_string($f['sid']) ? $f['sid'] : null;
        $sampel = $sid !== null && PustakaBawaan::ada($sid) ? PustakaBawaan::info($sid) : null;
        $cocok = $m !== null && $m->url() === $src;
        $crop = is_array($f) ? Papan::normalCrop($f['crop'] ?? null) : null;

        return [
            'ada' => $aman, 'src' => $aman ? $src : null,
            'thumb' => $cocok ? $m->urlThumb() : ($aman ? $src : null),
            'nama' => $cocok ? $m->nama : ($sampel ? $sampel['nama'] : ($aman ? 'Foto dari tautan' : null)),
            'crop' => $crop, 'ref' => $this->refDariEntri($f), 'warna' => $sampel['color'] ?? null,
        ];
    }

    /** Media milik pengguna yang dirujuk isi (foto + latar), diambil sekali. @return \Illuminate\Support\Collection<int,Media> */
    private function mediaDirujuk(array $foto, array $latar): \Illuminate\Support\Collection
    {
        $mids = [];
        foreach ([...array_values($foto), $latar, ...array_filter([$latar['bgs1'] ?? null, $latar['bgs2'] ?? null, $latar['bgs3'] ?? null, $latar['bgs4'] ?? null])] as $f) {
            if (is_array($f) && isset($f['mid']) && is_numeric($f['mid'])) {
                $mids[] = (int) $f['mid'];
            }
        }

        return $mids === [] ? collect() : Media::milik(auth()->user())->whereIn('id', array_unique($mids))->get()->keyBy('id');
    }

    /**
     * Daftar pemilih gambar: sampel bawaan (kecuali yang disembunyikan) lalu foto milik pengguna, disaring kategori dan pencarian.
     *
     * @return array<string,mixed>
     */
    private function dataPustaka(): array
    {
        $user = auth()->user();
        $penyimpan = app(PenyimpanMedia::class);
        $q = mb_strtolower($this->cariFoto);
        $kat = $this->katFoto;
        $tertunda = $this->hapusTertunda['ref'] ?? null;

        $item = [];
        $sembunyi = $this->sampelSembunyi();
        foreach (PustakaBawaan::ITEM as $id => $s) {
            if (in_array($id, $sembunyi, true) || ($kat !== 'Semua' && $s['kat'] !== $kat) || ($q !== '' && ! str_contains(mb_strtolower($s['nama']), $q))) {
                continue;
            }
            $item[] = ['id' => $id, 'nilai' => $id, 'nama' => $s['nama'], 'thumb' => PustakaBawaan::url($id), 'warna' => $s['color'], 'kat' => $s['kat'], 'ukuran' => 'Bawaan'];
        }

        $total = 0;
        $lagi = false;
        if ($kat === 'Semua' || $kat === 'Unggahan') {
            $mq = Media::milik($user)->where('tipe', Media::GAMBAR)->latest('id');
            if ($tertunda !== null && preg_match('/^m(\d+)$/', (string) $tertunda, $m)) {
                $mq->where('id', '!=', (int) $m[1]);
            }
            if ($this->cariFoto !== '') {
                $cari = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
                $mq->where(fn ($w) => $w->whereRaw("lower(nama) like ? escape '\\'", [$cari])->orWhereRaw("lower(coalesce(alt, '')) like ? escape '\\'", [$cari]));
            }
            $total = (clone $mq)->count();
            $media = $mq->limit($this->mukaFoto)->get();
            $lagi = $total > $media->count();
            foreach ($media as $m) {
                $item[] = ['id' => $m->id, 'nilai' => $m->id, 'nama' => $m->nama, 'thumb' => $m->urlThumb(), 'warna' => null, 'kat' => 'Unggahan', 'ukuran' => PenyimpanMedia::teksUkuran($m->ukuran)];
            }
        }
        $kuota = (int) $penyimpan->kuotaBytes($user);
        $terpakai = $penyimpan->terpakaiBytes($user);

        return [
            'item' => $item, 'total' => $total, 'lagi' => $lagi,
            'kuota' => PenyimpanMedia::teksUkuran($kuota), 'terpakai' => PenyimpanMedia::teksUkuran($terpakai),
            'persen' => $kuota > 0 ? min(100, (int) round($terpakai / $kuota * 100)) : 0, 'penuh' => $penyimpan->sisaBytes($user) <= 0,
            'sisaGaleri' => max(0, $this->batasGaleri() - $this->jumlahTerisiGaleri()),
            'maksUnggah' => PenyimpanMedia::teksUkuran((int) \App\Services\KonfigStorage::ambil()['maks_byte']),
        ];
    }
}
