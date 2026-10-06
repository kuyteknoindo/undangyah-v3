<?php

namespace App\Livewire\Concerns;

use App\Models\Media;
use App\Models\Undangan;
use App\Services\IsiUndangan;
use App\Services\MediaDitolak;
use App\Services\PenyimpanMedia;
use App\Services\ReferensiMedia;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;

/**
 * Foto di Editor Undangan (Fase 8b): slot foto, galeri, crop, dan Background Fixed.
 *
 * Disimpan di `isi['_foto'][slot] = {src, mid, crop?}`, `isi['_galeri'] = {layout, video}`, `isi['_latarTetap'] = {on, src, mid, oc, oo, op, blur}`.
 * Keamanan: klien hanya mengirim id media. `src` SELALU diturunkan di server dari `Media::url()` milik pengguna yang sedang masuk
 * (gambar, bukan aset sistem), jadi string URL dari klien tidak pernah disimpan. Parameter crop/latar dibatasi rentangnya.
 * Dipakai oleh EditorUndangan (memakai undangan(), simpan(), $daftar).
 */
trait FotoEditor
{
    /** Slot tetap => nama tampil. Slot galeri: "g{id}" untuk id yang ada di daftar `gl`. */
    public const SLOT_TETAP = [
        'cover' => 'Foto sampul', 'latar' => 'Foto latar pembuka', 'penutup' => 'Foto penutup',
        'pria' => 'Foto mempelai pria', 'wanita' => 'Foto mempelai wanita', 'share' => 'Foto saat dibagikan',
    ];

    /** Bagian => slot yang diatur di panel kanan (galeri punya panel sendiri). */
    public const SLOT_BAGIAN = [
        'sampul' => ['cover'], 'foto' => ['latar'], 'mempelai' => ['pria', 'wanita'], 'social' => ['share'], 'penutup' => ['penutup'],
    ];

    public const LAYOUT_GALERI = ['grid' => 'Grid', 'masonry' => 'Masonry', 'slider' => 'Slider'];

    private const BENTUK_CROP = [
        'cover' => 'width:200px;height:250px;border-radius:120px 120px 24px 24px',
        'pria' => 'width:112px;height:112px;border-radius:56px',
        'wanita' => 'width:112px;height:112px;border-radius:56px',
        'penutup' => 'width:140px;height:140px;border-radius:70px',
        'latar' => 'width:100%;aspect-ratio:4 / 3',
        'galeri' => 'width:180px;aspect-ratio:1 / 1;border-radius:14px',
    ];

    private const BATAS_AKSI_FOTO = 120;

    private const MAKS_UNGGAH_SEKALI = 10;

    private const LANGKAH_PUSTAKA = 48;

    /** slot | galeri | bgf: untuk apa dialog pustaka dibuka. */
    #[Locked]
    public string $modeFoto = 'slot';

    #[Locked]
    public ?string $slotFoto = null;

    /** Id media yang dipilih di dialog (satu untuk slot/bgf, banyak untuk galeri). @var list<int> */
    #[Locked]
    public array $pilihanFoto = [];

    #[Locked]
    public int $mukaFoto = self::LANGKAH_PUSTAKA;

    public bool $dialogFoto = false;

    public string $cariFoto = '';

    /** @var array<int,mixed> */
    public array $unggahFoto = [];

    /** @var list<string> */
    public array $galatFoto = [];

    public bool $dialogCrop = false;

    #[Locked]
    public ?string $slotCrop = null;

    public bool $dialogBgf = false;

    public string $videoGaleri = '';

    public string $galatVideo = '';

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

    /** Media gambar milik pengguna yang sedang masuk; selain itu (milik orang lain, aset sistem, bukan gambar) null. */
    private function mediaMilik(int $id): ?Media
    {
        return Media::milik(auth()->user())->where('tipe', Media::GAMBAR)->find($id);
    }

    private function fotoTersimpan(): array
    {
        $f = $this->undangan()->isi['_foto'] ?? [];

        return is_array($f) ? $f : [];
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
        $this->dialogBgf = true;
    }

    /** Pilih gambar Background Fixed lewat pustaka (dialog pengaturan tetap terbuka di belakangnya). */
    public function bukaFotoBgf(): void
    {
        $this->bukaPustaka('bgf', null);
    }

    private function bukaPustaka(string $mode, ?string $slot): void
    {
        $this->modeFoto = $mode;
        $this->slotFoto = $slot;
        $this->pilihanFoto = [];
        $this->cariFoto = '';
        $this->mukaFoto = self::LANGKAH_PUSTAKA;
        $this->galatFoto = [];
        $this->dialogFoto = true;
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

    public function pilihFoto(int $id): void
    {
        if (! $this->dialogFoto) {
            return;
        }
        $this->galatFoto = [];
        if ($this->mediaMilik($id) === null) {
            $this->galatFoto = ['Foto tidak ditemukan di pustakamu.'];

            return;
        }
        if ($this->modeFoto !== 'galeri') {
            $this->pilihanFoto = [$id];

            return;
        }
        if (in_array($id, $this->pilihanFoto, true)) {
            $this->pilihanFoto = array_values(array_diff($this->pilihanFoto, [$id]));

            return;
        }
        $sisa = $this->batasGaleri() - $this->jumlahTerisiGaleri();
        if (count($this->pilihanFoto) >= $sisa) {
            $this->galatFoto = ['Paketmu maksimal '.$this->batasGaleri().' foto galeri; kamu bisa menambah '.max(0, $sisa).' lagi.'];

            return;
        }
        $this->pilihanFoto = [...$this->pilihanFoto, $id];
    }

    public function terapkanPilihan(): void
    {
        if (! $this->dialogFoto || ! $this->lajuFotoOk()) {
            return;
        }
        $this->galatFoto = [];
        $media = array_values(array_filter(array_map(fn ($id) => $this->mediaMilik((int) $id), $this->pilihanFoto)));
        if ($media === []) {
            $this->galatFoto = ['Pilih satu foto dulu.'];

            return;
        }

        $galat = match ($this->modeFoto) {
            'galeri' => $this->tambahKeGaleri($media),
            'bgf' => $this->setGambarBgf($media[0]),
            default => $this->setSlot((string) $this->slotFoto, $media[0]),
        };
        if ($galat !== null) {
            $this->galatFoto = [$galat];

            return;
        }
        $this->dialogFoto = false;
        $this->pilihanFoto = [];
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

        if ($baru !== []) {
            if ($this->modeFoto === 'galeri') {
                $sisa = $this->batasGaleri() - $this->jumlahTerisiGaleri();
                $this->pilihanFoto = array_slice(array_values(array_unique([...$this->pilihanFoto, ...$baru])), 0, max(0, $sisa));
            } else {
                $this->pilihanFoto = [end($baru)];
            }
        }
        $this->galatFoto = $galat;
    }

    // ---------------------------------------------------------------- slot foto

    private function setSlot(string $slot, Media $m): ?string
    {
        if (! $this->slotSah($slot)) {
            return 'Slot foto tidak dikenal.';
        }
        $foto = $this->fotoTersimpan();
        if ($this->slotGaleri($slot) && ! IsiUndangan::fotoAda($this->undangan()->isi ?? [], $slot) && $this->jumlahTerisiGaleri() >= $this->batasGaleri()) {
            return 'Batas foto galeri paketmu ('.$this->batasGaleri().') sudah tercapai.';
        }
        $lama = $foto[$slot] ?? null;
        $baru = ['src' => $m->url(), 'mid' => $m->id];
        // Foto yang sama dipasang ulang tidak menghapus crop; foto lain mulai dari bingkai bersih.
        if (is_array($lama) && ($lama['mid'] ?? null) === $m->id && is_array($lama['crop'] ?? null)) {
            $baru['crop'] = $lama['crop'];
        }
        $foto[$slot] = $baru;
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

    // ---------------------------------------------------------------- crop

    public function bukaCrop(string $slot): void
    {
        if (! $this->slotSah($slot) || $slot === 'share' || ! IsiUndangan::fotoAda($this->undangan()->isi ?? [], $slot)) {
            return;
        }
        $this->slotCrop = $slot;
        $this->dialogCrop = true;
    }

    /** Parameter dari kontrol Alpine; apa pun yang bukan angka ditolak, angka di luar rentang dipaksa masuk rentang. */
    public function simpanCrop(mixed $ratio, mixed $zoom, mixed $x, mixed $y): void
    {
        $slot = (string) $this->slotCrop;
        if (! $this->slotSah($slot) || $slot === 'share' || ! IsiUndangan::fotoAda($this->undangan()->isi ?? [], $slot) || ! $this->lajuFotoOk()) {
            return;
        }
        if (! in_array($ratio, ['bingkai', 'asli'], true) || ! is_numeric($zoom) || ! is_numeric($x) || ! is_numeric($y)) {
            $this->dispatch('toast', pesan: 'Pengaturan crop tidak valid.', tipe: 'galat');

            return;
        }
        $crop = [
            'ratio' => $ratio,
            'zoom' => (int) max(100, min(300, round((float) $zoom))),
            'x' => (int) max(0, min(100, round((float) $x))),
            'y' => (int) max(0, min(100, round((float) $y))),
        ];
        $this->terapkanCrop($slot, $crop === ['ratio' => 'bingkai', 'zoom' => 100, 'x' => 50, 'y' => 50] ? null : $crop);
        $this->dialogCrop = false;
        $this->dispatch('toast', pesan: 'Crop diterapkan.', tipe: 'sukses');
    }

    private function terapkanCrop(string $slot, ?array $crop): void
    {
        $foto = $this->fotoTersimpan();
        $f = is_array($foto[$slot] ?? null) ? $foto[$slot] : ['src' => (string) ($foto[$slot] ?? '')];
        unset($f['crop']);
        if ($crop !== null) {
            $f['crop'] = $crop;
        }
        $foto[$slot] = $f;
        $this->tulisFoto(['_foto' => $foto]);
    }

    // ---------------------------------------------------------------- galeri

    /** @param  list<Media>  $media */
    private function tambahKeGaleri(array $media): ?string
    {
        $isi = $this->undangan()->isi ?? [];
        $foto = $this->fotoTersimpan();
        $sisa = $this->batasGaleri() - $this->jumlahTerisiGaleri();
        if ($sisa <= 0) {
            return 'Batas foto galeri paketmu ('.$this->batasGaleri().') sudah tercapai.';
        }
        $ada = [];
        foreach ($this->daftar['gl'] ?? [] as $id) {
            $f = $foto['g'.$id] ?? null;
            if (is_array($f) && isset($f['mid'])) {
                $ada[(int) $f['mid']] = true;
            }
        }

        $daftar = $this->daftar['gl'] ?? [];
        $kosong = array_values(array_filter($daftar, fn ($id) => ! IsiUndangan::fotoAda($isi, 'g'.$id)));
        $dipasang = 0;
        foreach ($media as $m) {
            if ($dipasang >= $sisa || isset($ada[$m->id])) {
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
            $foto['g'.$id] = ['src' => $m->url(), 'mid' => $m->id];
            $ada[$m->id] = true;
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

    private function setGambarBgf(Media $m): ?string
    {
        if (! $this->temaMendukungBgf()) {
            return 'Tema ini tidak memakai Background Fixed.';
        }
        $l = is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : [];
        $this->tulisFoto(['_latarTetap' => [...$this->bgfBawaan(), ...$l, 'on' => true, 'src' => $m->url(), 'mid' => $m->id]]);

        return null;
    }

    private function bgfBawaan(): array
    {
        return ['on' => false, 'oc' => '#000000', 'oo' => 45, 'op' => 100, 'blur' => 0];
    }

    public function simpanBgf(mixed $on, mixed $oc, mixed $oo, mixed $op, mixed $blur): void
    {
        if (! $this->lajuFotoOk()) {
            return;
        }
        $l = is_array($this->undangan()->isi['_latarTetap'] ?? null) ? $this->undangan()->isi['_latarTetap'] : [];
        if (! is_bool($on) || ! is_string($oc) || ! preg_match('/^#[0-9a-fA-F]{6}$/', $oc) || ! is_numeric($oo) || ! is_numeric($op) || ! is_numeric($blur)) {
            $this->dispatch('toast', pesan: 'Pengaturan Background Fixed tidak valid.', tipe: 'galat');

            return;
        }
        if ($on && empty($l['src'])) {
            $this->dispatch('toast', pesan: 'Pilih gambar dulu sebelum mengaktifkan Background Fixed.', tipe: 'galat');

            return;
        }
        if ($on && ! $this->temaMendukungBgf()) {
            $this->dispatch('toast', pesan: 'Tema ini tidak memakai Background Fixed.', tipe: 'galat');

            return;
        }
        $this->tulisFoto(['_latarTetap' => [...$this->bgfBawaan(), ...$l,
            'on' => $on, 'oc' => strtoupper($oc),
            'oo' => (int) max(0, min(90, round((float) $oo))),
            'op' => (int) max(20, min(100, round((float) $op))),
            'blur' => (int) max(0, min(20, round((float) $blur))),
        ]]);
        $this->dialogBgf = false;
        $this->dispatch('toast', pesan: 'Background Fixed disimpan.', tipe: 'sukses');
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
        $this->dialogBgf = false;
    }

    // ---------------------------------------------------------------- data untuk tampilan

    private function infoSumber(mixed $f, ?Media $m): array
    {
        $src = is_array($f) ? (string) ($f['src'] ?? '') : (is_string($f) ? $f : '');
        $aman = $src !== '' && (str_starts_with($src, '/') && ! str_starts_with($src, '//') || app(IsiUndangan::class)->urlSah($src));
        $thumb = $m !== null && $m->url() === $src ? $m->urlThumb() : ($aman ? $src : null);

        return [
            'ada' => $aman, 'src' => $aman ? $src : null, 'thumb' => $thumb,
            'nama' => $m !== null && $m->url() === $src ? $m->nama : ($aman ? 'Foto dari tautan' : null),
            'crop' => is_array($f) && is_array($f['crop'] ?? null) ? $f['crop'] : null,
        ];
    }

    /** @return array<string,mixed> */
    private function dataFoto(string $aktif): array
    {
        $user = auth()->user();
        $isi = $this->undangan()->isi ?? [];
        $foto = $this->fotoTersimpan();
        $latar = is_array($isi['_latarTetap'] ?? null) ? $isi['_latarTetap'] : [];

        $mids = [];
        foreach ($foto as $f) {
            if (is_array($f) && isset($f['mid']) && is_numeric($f['mid'])) {
                $mids[] = (int) $f['mid'];
            }
        }
        if (isset($latar['mid']) && is_numeric($latar['mid'])) {
            $mids[] = (int) $latar['mid'];
        }
        $medias = $mids === [] ? collect() : Media::milik($user)->whereIn('id', array_unique($mids))->get()->keyBy('id');
        $ambil = fn (string $slot) => $this->infoSumber($foto[$slot] ?? null, is_array($foto[$slot] ?? null) ? $medias->get((int) ($foto[$slot]['mid'] ?? 0)) : null);

        $slot = [];
        foreach (self::SLOT_BAGIAN[$aktif] ?? [] as $s) {
            $slot[] = ['slot' => $s, 'label' => self::SLOT_TETAP[$s], 'bisaCrop' => $s !== 'share'] + $ambil($s);
        }

        $galeri = null;
        if ($aktif === 'gallery') {
            $g = is_array($isi['_galeri'] ?? null) ? $isi['_galeri'] : [];
            $item = [];
            $ids = $this->daftar['gl'] ?? [];
            foreach ($ids as $i => $id) {
                $item[] = ['id' => $id, 'slot' => 'g'.$id, 'no' => $i + 1, 'pertama' => $i === 0, 'terakhir' => $i === count($ids) - 1] + $ambil('g'.$id);
            }
            $galeri = [
                'layout' => in_array($g['layout'] ?? '', array_keys(self::LAYOUT_GALERI), true) ? $g['layout'] : 'grid',
                'item' => $item, 'terisi' => $this->jumlahTerisiGaleri(), 'batas' => $this->batasGaleri(),
            ];
        }

        $bgf = null;
        $dukung = $this->temaMendukungBgf();
        if ($dukung || ! empty($latar['on'])) {
            $info = $this->infoSumber(['src' => $latar['src'] ?? '', 'mid' => $latar['mid'] ?? null], $medias->get((int) ($latar['mid'] ?? 0)));
            $bgf = $info + [
                'on' => ! empty($latar['on']), 'oc' => preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($latar['oc'] ?? '')) ? strtoupper($latar['oc']) : '#000000',
                'oo' => max(0, min(90, (int) ($latar['oo'] ?? 45))), 'op' => max(20, min(100, (int) ($latar['op'] ?? 100))), 'blur' => max(0, min(20, (int) ($latar['blur'] ?? 0))),
                'tersedia' => $dukung,
            ];
        }

        $crop = null;
        if ($this->dialogCrop && $this->slotCrop !== null && $this->slotSah($this->slotCrop)) {
            $info = $ambil($this->slotCrop);
            $kunci = $this->slotGaleri($this->slotCrop) ? 'galeri' : $this->slotCrop;
            $crop = $info + [
                'slot' => $this->slotCrop, 'bentuk' => self::BENTUK_CROP[$kunci] ?? self::BENTUK_CROP['galeri'],
                'label' => self::SLOT_TETAP[$this->slotCrop] ?? 'Foto galeri '.(array_search((int) substr($this->slotCrop, 1), $this->daftar['gl'] ?? [], true) + 1),
            ];
        }

        return ['fotoSlot' => $slot, 'fotoGaleri' => $galeri, 'fotoBgf' => $bgf, 'fotoCrop' => $crop, 'pustaka' => $this->dialogFoto ? $this->dataPustaka() : null];
    }

    /** @return array<string,mixed> */
    private function dataPustaka(): array
    {
        $user = auth()->user();
        $penyimpan = app(PenyimpanMedia::class);
        $q = Media::milik($user)->where('tipe', Media::GAMBAR)->latest('id');
        if ($this->cariFoto !== '') {
            $cari = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($this->cariFoto)).'%';
            $q->where(fn ($w) => $w->whereRaw("lower(nama) like ? escape '\\'", [$cari])->orWhereRaw("lower(coalesce(alt, '')) like ? escape '\\'", [$cari]));
        }
        $total = (clone $q)->count();
        $item = $q->limit($this->mukaFoto)->get()->map(fn (Media $m) => [
            'id' => $m->id, 'nama' => $m->nama, 'thumb' => $m->urlThumb(), 'ukuran' => PenyimpanMedia::teksUkuran($m->ukuran),
        ])->all();
        $kuota = (int) $penyimpan->kuotaBytes($user);
        $terpakai = $penyimpan->terpakaiBytes($user);

        return [
            'item' => $item, 'total' => $total, 'lagi' => $total > count($item),
            'kuota' => PenyimpanMedia::teksUkuran($kuota), 'terpakai' => PenyimpanMedia::teksUkuran($terpakai),
            'persen' => $kuota > 0 ? min(100, (int) round($terpakai / $kuota * 100)) : 0, 'penuh' => $penyimpan->sisaBytes($user) <= 0,
            'sisaGaleri' => max(0, $this->batasGaleri() - $this->jumlahTerisiGaleri()),
        ];
    }
}
