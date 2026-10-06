<?php

namespace App\Livewire;

use App\Models\Tema;
use App\Models\Undangan;
use App\Services\IsiUndangan;
use App\Services\KuotaUndangan;
use App\Services\PenerbitUndangan;
use App\Services\RenderUndangan;
use App\Services\SlugUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor undangan (Fase 3): panel Bagian/Tema/Pengaturan di kiri, kanvas pratinjau di tengah, isian bagian di kanan.
 *
 * Keamanan: undangan SELALU dimuat ulang dari database lewat pemilik yang sedang masuk (id tidak bisa diganti: #[Locked]).
 * `$isi` boleh ditulis klien (wire:model), jadi tiap perubahan dicocokkan dengan skema: kunci asing dibuang, nilai dibersihkan
 * per jenis bidang. Daftar item, urutan/sembunyi bagian, tema, dan status hanya berubah lewat aksi di server.
 */
class EditorUndangan extends Component
{
    private const MODE = ['edit', 'tamu'];

    private const BATAS_TERBIT = 10;

    /** Slot foto (kunci `foto:<slot>` dari kanvas) => bagian pemiliknya. */
    private const BAGIAN_FOTO = ['cover' => 'sampul', 'latar' => 'foto', 'penutup' => 'penutup', 'pria' => 'mempelai', 'wanita' => 'mempelai', 'share' => 'social', 'bgf' => 'foto'];

    #[Locked]
    public int $undanganId = 0;

    /** Isian bidang: kunci => teks. Daftar item ada di $daftar, bidang tersembunyi di $sembunyi. */
    public array $isi = [];

    /** @var array<string,list<int>> tpl => id item berurutan (termasuk 'gl' milik galeri, diteruskan apa adanya) */
    #[Locked]
    public array $daftar = [];

    /** @var array{urutan:list<string>,sembunyi:list<string>} */
    #[Locked]
    public array $bagian = ['urutan' => [], 'sembunyi' => []];

    #[Locked]
    public array $pengaturan = [];

    /** Kunci bidang opsional yang disembunyikan dari undangan. @var list<string> */
    #[Locked]
    public array $sembunyi = [];

    #[Locked]
    public string $bagianAktif = 'sampul';

    #[Locked]
    public ?string $terpilih = null;

    #[Locked]
    public string $mode = 'edit';

    /** Pesan galat per kunci (nilai ditolak, yang tersimpan tetap nilai lama). @var array<string,string> */
    #[Locked]
    public array $galatBidang = [];

    public string $judul = '';

    public string $slug = '';

    public bool $dialogTerbit = false;

    public string $slugTerbit = '';

    #[Locked]
    public string $tahapTerbit = 'form';

    public string $galatTerbit = '';

    public string $galatSlug = '';

    public string $galatJudul = '';

    private ?Undangan $model = null;

    public function mount(?int $undangan = null): void
    {
        $user = auth()->user();
        if ($undangan === null) {
            $utama = Undangan::where('user_id', $user->id)->latest('updated_at')->latest('id')->first();
            if ($utama === null) {
                session()->flash('toast', ['pesan' => 'Buat undangan dulu, lalu isi dan terbitkan di editor.', 'tipe' => 'info']);

                $this->redirectRoute('buat-undangan', navigate: true);

                return;
            }
            $this->redirectRoute('edit-undangan', ['undangan' => $utama->id], navigate: true);

            return;
        }

        $u = Undangan::where('user_id', $user->id)->find($undangan) ?? abort(404);
        $this->undanganId = $u->id;
        $this->model = $u;
        $this->muat($u);
    }

    /** Muat state dari database: nilai awal skema ditimpa isi tersimpan; kunci tak dikenal dibuang. */
    private function muat(Undangan $u): void
    {
        $skema = SkemaUndangan::isiAwal();
        $simpan = is_array($u->isi) ? $u->isi : [];
        $gabung = array_merge($skema, $simpan);

        $daftar = [];
        foreach ([...array_keys(SkemaUndangan::templat()), 'gl'] as $tpl) {
            $daftar[$tpl] = array_values(array_unique(array_map('intval', array_filter((array) ($gabung['_daftar'][$tpl] ?? []), 'is_numeric'))));
        }
        $this->daftar = $daftar;

        $isi = [];
        $iso = app(IsiUndangan::class);
        foreach (SkemaUndangan::bidang() as $k => $b) {
            $isi[$k] = $this->nilaiMuat($iso, $k, $gabung[$k] ?? $b['v'], $b['v']);
        }
        foreach (SkemaUndangan::templat() as $tpl => $t) {
            foreach ($daftar[$tpl] as $id) {
                foreach ($iso->kunciItem($tpl, $id) as $k) {
                    $meta = $iso->meta($k, $daftar);
                    $isi[$k] = $this->nilaiMuat($iso, $k, $gabung[$k] ?? $meta['v'], $meta['v']);
                }
            }
        }
        $this->isi = $isi;

        $this->sembunyi = $this->bacaSembunyi($simpan['_sembunyi'] ?? [], $iso);
        $this->bagian = $this->normalBagian($u->bagian ?? []);
        $this->pengaturan = array_merge(['lingkaran' => ($simpan['_cincin'] ?? true) !== false], is_array($u->pengaturan) ? $u->pengaturan : []);
        $this->judul = (string) $u->judul;
        $this->slug = (string) $u->slug;
    }

    /** `_sembunyi` tersimpan sebagai peta kunci => true (konvensi RenderUndangan); daftar lama juga dibaca. @return list<string> */
    private function bacaSembunyi(mixed $simpan, IsiUndangan $iso): array
    {
        $kunci = [];
        foreach ((array) $simpan as $k => $v) {
            $nama = is_int($k) ? $v : ($v ? $k : null);
            if (is_string($nama) && $iso->meta($nama, $this->daftar)) {
                $kunci[] = $nama;
            }
        }

        return array_values(array_unique($kunci));
    }

    private function nilaiMuat(IsiUndangan $iso, string $kunci, mixed $nilai, string $awal): string
    {
        $meta = $iso->meta($kunci, $this->daftar) ?? ['kind' => 'text', 'rows' => 0, 'opts' => null];
        [$bersih] = $iso->bersihkan($nilai, $meta);

        // Lagu yang dihapus/didrafkan admin tidak boleh diam-diam diganti lagu awal.
        return $bersih ?? ($kunci === 'musLagu' ? '' : $awal);
    }

    /** Urutan sah: semua bagian tepat sekali, Sampul pertama, Penutup terakhir; sembunyi hanya id sah (Sampul tak bisa). */
    private function normalBagian(array $b): array
    {
        $semua = array_keys(SkemaUndangan::bagian());
        $urutan = array_values(array_unique(array_filter((array) ($b['urutan'] ?? []), fn ($x) => in_array($x, $semua, true))));
        $tengah = array_values(array_filter($urutan, fn ($x) => ! in_array($x, ['sampul', 'penutup'], true)));
        foreach (SkemaUndangan::urutanAwal() as $x) {
            if (! in_array($x, ['sampul', 'penutup'], true) && ! in_array($x, $tengah, true)) {
                $tengah[] = $x;
            }
        }
        $sembunyi = array_values(array_unique(array_filter((array) ($b['sembunyi'] ?? []), fn ($x) => in_array($x, $semua, true) && $x !== 'sampul')));

        return ['urutan' => ['sampul', ...$tengah, 'penutup'], 'sembunyi' => $sembunyi];
    }

    private function undangan(): Undangan
    {
        return $this->model ??= Undangan::where('user_id', auth()->id())->findOrFail($this->undanganId);
    }

    private function iso(): IsiUndangan
    {
        return app(IsiUndangan::class);
    }

    // ---------------------------------------------------------------- isian

    /** Dipanggil Livewire tiap `isi.<kunci>` diubah klien. */
    public function updatedIsi(mixed $nilai, ?string $kunci = null): void
    {
        if ($kunci === null || $kunci === '' || str_contains($kunci, '.')) {
            $this->pulihkanIsi();

            return;
        }
        $this->terapkan($kunci, $nilai);
    }

    /** Aksi untuk pilihan (select/switch): nilai dikirim sebagai argumen, dicek dengan aturan yang sama. */
    public function setBidang(string $kunci, string $nilai): void
    {
        $this->terapkan($kunci, $nilai);
    }

    private function terapkan(string $kunci, mixed $nilai): void
    {
        $meta = $this->iso()->meta($kunci, $this->daftar);
        if ($meta === null) {
            unset($this->isi[$kunci]);

            return;
        }

        [$bersih, $galat] = $this->iso()->bersihkan($nilai, $meta);
        if ($galat !== null) {
            $this->galatBidang = [...$this->galatBidang, $kunci => $galat];
            // Teks yang sedang diketik tetap tampil; yang disimpan dan dirender tetap nilai terakhir yang sah.
            $this->isi[$kunci] = is_string($nilai) ? mb_substr($nilai, 0, IsiUndangan::MAKS_URL) : $this->tersimpan($kunci, $meta);

            return;
        }

        $baru = $this->galatBidang;
        unset($baru[$kunci]);
        $this->galatBidang = $baru;
        $this->isi[$kunci] = $bersih;
        $this->simpan();
    }

    private function tersimpan(string $kunci, array $meta): string
    {
        $lama = $this->undangan()->isi ?? [];

        return (string) ($lama[$kunci] ?? $meta['v']);
    }

    private function pulihkanIsi(): void
    {
        $this->muat($this->undangan());
    }

    public function toggleBidang(string $kunci): void
    {
        $meta = $this->iso()->meta($kunci, $this->daftar);
        if ($meta === null || ! $meta['canvas']) {
            return;
        }
        $this->sembunyi = in_array($kunci, $this->sembunyi, true)
            ? array_values(array_diff($this->sembunyi, [$kunci]))
            : [...$this->sembunyi, $kunci];
        $this->simpan();
    }

    // ---------------------------------------------------------------- pilihan

    public function pilihBagian(string $id): void
    {
        if (! isset(SkemaUndangan::bagian()[$id])) {
            return;
        }
        $this->bagianAktif = $id;
        $this->terpilih = null;
        $this->dispatch('bagian-dipilih', id: $id);
    }

    /** Dari klik di kanvas atau dari panel: pilih bidang dan buka bagian pemiliknya. */
    public function pilihBidang(string $kunci): void
    {
        if (str_starts_with($kunci, 'bagian:')) {
            $this->pilihBagian(substr($kunci, 7));

            return;
        }
        if (str_starts_with($kunci, 'foto:')) {
            $slot = substr($kunci, 5);
            $this->pilihBagian(self::BAGIAN_FOTO[$slot] ?? (str_starts_with($slot, 'g') ? 'gallery' : 'foto'));

            return;
        }
        $meta = $this->iso()->meta($kunci, $this->daftar);
        if ($meta === null) {
            return;
        }
        $this->bagianAktif = $meta['g'];
        $this->terpilih = $kunci;
        $this->dispatch('bidang-dipilih', kunci: $kunci);
    }

    public function setMode(string $mode): void
    {
        if (in_array($mode, self::MODE, true)) {
            $this->mode = $mode;
        }
    }

    // ---------------------------------------------------------------- bagian

    public function geserBagian(string $id, int $arah): void
    {
        $anggota = $this->anggotaGrup($id);
        $i = array_search($id, $anggota, true);
        if ($i === false || ! in_array($arah, [-1, 1], true) || ! isset($anggota[$i + $arah])) {
            return;
        }
        $this->pindahBagian($id, $anggota[$i + $arah]);
    }

    /** Pindahkan $id ke posisi $target, hanya di dalam grup yang sama (Konten / Fitur); Sampul dan Penutup tetap. */
    public function pindahBagian(string $id, string $target): void
    {
        $anggota = $this->anggotaGrup($id);
        $dari = array_search($id, $anggota, true);
        $ke = array_search($target, $anggota, true);
        if ($dari === false || $ke === false || $dari === $ke) {
            return;
        }
        array_splice($anggota, $ke, 0, array_splice($anggota, $dari, 1));

        $n = 0;
        $grup = $this->grupBagian($id);
        $this->bagian = [...$this->bagian, 'urutan' => array_map(
            function ($x) use ($grup, $anggota, &$n) {
                return $this->grupBagian($x) === $grup ? $anggota[$n++] : $x;
            },
            $this->bagian['urutan'],
        )];
        $this->bagianAktif = $id;
        $this->simpan();
    }

    public function toggleBagian(string $id): void
    {
        if (! isset(SkemaUndangan::bagian()[$id]) || $id === 'sampul') {
            return;
        }
        $s = $this->bagian['sembunyi'];
        $s = in_array($id, $s, true) ? array_values(array_diff($s, [$id])) : [...$s, $id];
        $this->bagian = [...$this->bagian, 'sembunyi' => $s];
        $this->simpan();
    }

    /** @return 'konten'|'fitur'|null */
    private function grupBagian(string $id): ?string
    {
        return in_array($id, SkemaUndangan::konten(), true) ? 'konten' : (in_array($id, SkemaUndangan::fitur(), true) ? 'fitur' : null);
    }

    /** @return list<string> bagian segrup dengan $id menurut urutan sekarang */
    private function anggotaGrup(string $id): array
    {
        $grup = $this->grupBagian($id);
        if ($grup === null) {
            return [];
        }

        return array_values(array_filter($this->bagian['urutan'], fn ($x) => $this->grupBagian($x) === $grup));
    }

    // ---------------------------------------------------------------- daftar berulang

    public function tambahItem(string $tpl): void
    {
        $templat = SkemaUndangan::templat()[$tpl] ?? null;
        if ($templat === null || count($this->daftar[$tpl] ?? []) >= IsiUndangan::MAKS_ITEM) {
            if ($templat !== null) {
                $this->dispatch('toast', pesan: 'Maksimal '.IsiUndangan::MAKS_ITEM.' item.', tipe: 'galat');
            }

            return;
        }
        $id = $this->idBaru();
        $this->daftar = [...$this->daftar, $tpl => [...($this->daftar[$tpl] ?? []), $id]];
        $this->isi = [...$this->isi, ...$this->iso()->nilaiAwalItem($tpl, $id)];
        $this->bagianAktif = $templat['g'];
        $this->simpan();
        $this->pilihBidang($this->iso()->kunciItem($tpl, $id)[0]);
    }

    public function hapusItem(string $tpl, int $id): void
    {
        $templat = SkemaUndangan::templat()[$tpl] ?? null;
        $arr = $this->daftar[$tpl] ?? [];
        if ($templat === null || ! in_array($id, $arr, true)) {
            return;
        }
        if (count($arr) <= $templat['min']) {
            $this->dispatch('toast', pesan: 'Minimal ada '.$templat['min'].' item di bagian ini.', tipe: 'galat');

            return;
        }
        $kunci = $this->iso()->kunciItem($tpl, $id);
        $this->daftar = [...$this->daftar, $tpl => array_values(array_diff($arr, [$id]))];
        $this->isi = array_diff_key($this->isi, array_flip($kunci));
        $this->sembunyi = array_values(array_diff($this->sembunyi, $kunci));
        $this->galatBidang = array_diff_key($this->galatBidang, array_flip($kunci));
        if ($this->terpilih !== null && in_array($this->terpilih, $kunci, true)) {
            $this->terpilih = null;
        }
        $this->simpan();
    }

    public function gandakanItem(string $tpl, int $id): void
    {
        $templat = SkemaUndangan::templat()[$tpl] ?? null;
        $arr = $this->daftar[$tpl] ?? [];
        if ($templat === null || ! in_array($id, $arr, true) || count($arr) >= IsiUndangan::MAKS_ITEM) {
            return;
        }
        $baru = $this->idBaru();
        $salin = [];
        foreach ($templat['f'] as $f) {
            $salin["{$tpl}{$baru}_{$f[0]}"] = $this->isi["{$tpl}{$id}_{$f[0]}"] ?? (string) $f[2];
            if (in_array("{$tpl}{$id}_{$f[0]}", $this->sembunyi, true)) {
                $this->sembunyi = [...$this->sembunyi, "{$tpl}{$baru}_{$f[0]}"];
            }
        }
        array_splice($arr, array_search($id, $arr, true) + 1, 0, [$baru]);
        $this->daftar = [...$this->daftar, $tpl => $arr];
        $this->isi = [...$this->isi, ...$salin];
        $this->simpan();
    }

    public function geserItem(string $tpl, int $id, int $arah): void
    {
        $arr = $this->daftar[$tpl] ?? [];
        $i = array_search($id, $arr, true);
        if (! isset(SkemaUndangan::templat()[$tpl]) || $i === false || ! in_array($arah, [-1, 1], true) || ! isset($arr[$i + $arah])) {
            return;
        }
        [$arr[$i], $arr[$i + $arah]] = [$arr[$i + $arah], $arr[$i]];
        $this->daftar = [...$this->daftar, $tpl => $arr];
        $this->simpan();
    }

    /** Id unik di semua daftar (kunci "{tpl}{id}_..." tidak boleh pernah bertabrakan, juga dengan item yang sudah dihapus). */
    private function idBaru(): int
    {
        $maks = 0;
        foreach ($this->daftar as $ids) {
            $maks = max($maks, ...($ids ?: [0]));
        }
        foreach (array_keys($this->undangan()->isi ?? []) as $k) {
            if (preg_match('/^[a-z]{2}(\d{1,9})_/', (string) $k, $m)) {
                $maks = max($maks, (int) $m[1]);
            }
        }

        return max($maks + 1, 100);
    }

    // ---------------------------------------------------------------- tema & pengaturan

    public function pilihTema(int $id): void
    {
        $tema = Tema::terbit()->find($id);
        if ($tema === null) {
            $this->dispatch('toast', pesan: 'Tema tidak tersedia.', tipe: 'galat');

            return;
        }
        if (app(KuotaUndangan::class)->temaTerkunci($tema, auth()->user())) {
            $this->dispatch('toast', pesan: 'Tema ini belum tersedia di paketmu.', tipe: 'galat');

            return;
        }
        $u = $this->undangan();
        $u->tema_id = $tema->id;
        $this->simpan();
    }

    public function toggleLingkaran(): void
    {
        $this->pengaturan = [...$this->pengaturan, 'lingkaran' => ! ($this->pengaturan['lingkaran'] ?? true)];
        $this->simpan();
    }

    public function updatedJudul(): void
    {
        $this->judul = trim(preg_replace('/\s+/', ' ', strip_tags($this->judul)));
        $this->galatJudul = '';
        if ($this->judul === '') {
            $this->galatJudul = 'Nama undangan wajib diisi.';

            return;
        }
        if (mb_strlen($this->judul) > 120) {
            $this->judul = mb_substr($this->judul, 0, 120);
        }
        $this->simpan();
    }

    public function updatedSlug(): void
    {
        $u = $this->undangan();
        $this->galatSlug = '';
        if ($u->sudahTerbit()) {
            // Alamat aktif hanya diganti lewat dialog Terbitkan ulang.
            $this->slug = (string) $u->slug;

            return;
        }
        $this->slug = trim((string) preg_replace('/[^a-z0-9-]/', '-', mb_strtolower($this->slug)), ' ');
        if ($pesan = app(SlugUndangan::class)->galat($this->slug, $u->id)) {
            $this->galatSlug = $pesan;

            return;
        }
        $u->slug = $this->slug;
        try {
            $this->simpan();
        } catch (UniqueConstraintViolationException) {
            $this->galatSlug = 'Alamat ini sudah dipakai undangan lain.';
            $u->slug = $u->getOriginal('slug');
        }
    }

    // ---------------------------------------------------------------- terbit

    public function bukaTerbit(): void
    {
        $u = $this->undangan();
        $this->galatTerbit = '';
        $this->galatSlug = '';
        $this->slugTerbit = (string) $u->slug;
        $this->tahapTerbit = ($u->sudahTerbit() && ! $u->ada_perubahan) ? 'selesai' : 'form';
        $this->dialogTerbit = true;
    }

    public function updatedSlugTerbit(): void
    {
        $this->slugTerbit = trim((string) preg_replace('/[^a-z0-9-]/', '-', mb_strtolower($this->slugTerbit)));
        $this->galatTerbit = '';
        $this->galatSlug = app(SlugUndangan::class)->galat($this->slugTerbit, $this->undanganId) ?? '';
    }

    public function ulangTerbit(): void
    {
        $this->galatTerbit = '';
        $this->slugTerbit = (string) $this->undangan()->slug;
        $this->tahapTerbit = 'form';
    }

    public function terbitkan(): void
    {
        $user = auth()->user();
        $u = $this->undangan();
        $this->galatTerbit = '';
        $this->galatSlug = '';

        $kunci = 'terbit-undangan:'.$user->id;
        if (RateLimiter::tooManyAttempts($kunci, self::BATAS_TERBIT)) {
            $this->galatTerbit = 'Terlalu banyak percobaan. Coba lagi sebentar lagi.';

            return;
        }
        RateLimiter::hit($kunci, 60);

        // Salinan beku diambil dari keadaan terbaru, termasuk bidang yang belum sempat tersimpan otomatis.
        $this->simpan();
        $u = $this->undangan();

        try {
            app(PenerbitUndangan::class)->terbitkan($u, $this->slugTerbit);
        } catch (ValidationException $e) {
            $this->galatSlug = $e->errors()['slug'][0] ?? '';
            $this->galatTerbit = $e->errors()['umum'][0] ?? '';

            return;
        }

        $this->slug = (string) $u->slug;
        $this->tahapTerbit = 'selesai';
        $this->dispatch('toast', pesan: 'Undangan diterbitkan.', tipe: 'sukses');
    }

    public function jadikanDraf(): void
    {
        $u = $this->undangan();
        if (! $u->sudahTerbit()) {
            return;
        }
        app(PenerbitUndangan::class)->jadikanDraf($u);
        $this->dialogTerbit = false;
        $this->tahapTerbit = 'form';
        $this->dispatch('toast', pesan: 'Undangan kembali menjadi draf. Tamu tidak bisa membukanya sampai diterbitkan lagi.', tipe: 'info');
    }

    // ---------------------------------------------------------------- simpan

    /** Susun `isi` lengkap untuk disimpan/dirender: bidang sah + _daftar + _sembunyi (+ kunci "_..." lain milik fitur lain tetap). */
    private function susunIsi(): array
    {
        $lama = $this->undangan()->isi ?? [];
        $isi = [];
        foreach ($lama as $k => $v) {
            if (is_string($k) && str_starts_with($k, '_')) {
                $isi[$k] = $v;
            }
        }
        foreach ($this->isi as $k => $v) {
            $meta = $this->iso()->meta((string) $k, $this->daftar);
            if ($meta === null) {
                continue;
            }
            $isi[$k] = isset($this->galatBidang[$k]) ? $this->tersimpan((string) $k, $meta) : (string) $v;
        }
        $isi['_daftar'] = $this->daftar;
        $isi['_sembunyi'] = array_fill_keys($this->sembunyi, true);
        $isi['_cincin'] = (bool) ($this->pengaturan['lingkaran'] ?? true);

        return $isi;
    }

    private function simpan(): void
    {
        $u = $this->undangan();
        $u->isi = $this->susunIsi();
        $u->bagian = $this->bagian;
        // Hanya kunci milik editor yang ditulis; kunci halaman lain (buku_tamu, hadiah, riwayat_slug) dibiarkan.
        $u->pengaturan = array_merge(is_array($u->pengaturan) ? $u->pengaturan : [], ['lingkaran' => (bool) ($this->pengaturan['lingkaran'] ?? true)]);
        if (trim($this->judul) !== '') {
            $u->judul = $this->judul;
        }
        $u->draf_disimpan_at = now();

        $tgl = $u->isi['hmTgl'] ?? '';
        if ($tgl !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) {
            $u->tanggal_acara = $tgl;
        }
        if (($lokasi = trim((string) ($u->isi['gcLokasi'] ?? ''))) !== '') {
            $u->lokasi_ringkas = mb_substr($lokasi, 0, 255);
        }
        $ada = fn (string $k) => trim((string) ($u->isi[$k] ?? '')) !== '';
        $u->langkah = array_merge($u->langkah ?? [], [
            'tema' => $u->tema_id !== null,
            'data' => $ada('priaP') && $ada('wanitaP') && $ada('tglCover'),
        ]);

        app(PenerbitUndangan::class)->sinkron($u);
        $u->save();
    }

    // ---------------------------------------------------------------- tampilan

    private function temaSekarang(Undangan $u): ?Tema
    {
        return ($u->tema_id ? Tema::withTrashed()->find($u->tema_id) : null) ?? Tema::terbit()->urut()->first();
    }

    private function kanvas(Undangan $u, ?Tema $tema): ?string
    {
        $kelas = RenderUndangan::class;
        if (! class_exists($kelas)) {
            return null;
        }
        try {
            return $kelas::html($this->susunIsi(), $this->bagian, $tema, ['edit' => $this->mode === 'edit', 'lebar' => 500]);
        } catch (\Throwable $e) {
            report($e);

            return '';
        }
    }

    public function render()
    {
        $u = $this->undangan();
        $iso = $this->iso();
        $kuota = app(KuotaUndangan::class);
        $tema = $this->temaSekarang($u);
        $isiPenuh = $this->susunIsi();
        $definisi = SkemaUndangan::bagian();

        $baris = [];
        foreach ($this->bagian['urutan'] as $id) {
            $st = $iso->status($id, $isiPenuh, $this->daftar);
            $sembunyi = in_array($id, $this->bagian['sembunyi'], true);
            $teks = in_array($id, ['foto', 'gallery'], true)
                ? 'Foto menyusul'
                : ($st['done'] ? (($definisi[$id]['req'] ?? false) ? 'Lengkap' : 'Terisi') : ($st['any'] ? 'Belum lengkap' : 'Belum diisi'));
            $baris[$id] = [
                'id' => $id, 'nama' => $definisi[$id]['name'], 'opsional' => ! ($definisi[$id]['req'] ?? false) && ! in_array($id, ['sampul', 'penutup'], true),
                'selesai' => $st['done'], 'sebagian' => $st['any'], 'sembunyi' => $sembunyi, 'teks' => $teks.($sembunyi ? ' · disembunyikan' : ''),
            ];
        }
        $urutanGrup = fn (array $ids) => array_values(array_filter($this->bagian['urutan'], fn ($x) => in_array($x, $ids, true)));
        $konten = $urutanGrup(SkemaUndangan::konten());
        $fitur = $urutanGrup(SkemaUndangan::fitur());

        $aktif = $this->bagianAktif;
        $anggota = $this->anggotaGrup($aktif);
        $posisi = array_search($aktif, $anggota, true);

        $grup = [];
        $tetap = $iso->kunciTetap($aktif);
        if ($tetap) {
            $grup[] = ['jenis' => 'tetap', 'judul' => 'Teks bagian', 'kunci' => $tetap];
        }
        foreach (SkemaUndangan::daftarBagian()[$aktif] ?? [] as $sl) {
            $arr = $this->daftar[$sl['t']] ?? [];
            if (isset($sl['head'])) {
                $grup[] = ['jenis' => 'kepala', 'judul' => $sl['head'].' · '.count($arr)];
            }
            foreach ($arr as $i => $id) {
                $kunci = $iso->kunciItem($sl['t'], $id);
                $ringkas = collect($kunci)->filter(fn ($k) => ($m = $iso->meta($k, $this->daftar)) && $m['canvas'] && $m['kind'] === 'text')
                    ->map(fn ($k) => trim((string) ($this->isi[$k] ?? '')))->filter()->take(2)->implode(' · ');
                $grup[] = [
                    'jenis' => 'item', 'tpl' => $sl['t'], 'id' => $id, 'judul' => $sl['item'].' '.($i + 1), 'nama' => mb_strtolower($sl['item']).' '.($i + 1),
                    'kunci' => $kunci, 'pertama' => $i === 0, 'terakhir' => $i === count($arr) - 1,
                    'bisaHapus' => count($arr) > SkemaUndangan::templat()[$sl['t']]['min'], 'ringkas' => mb_strimwidth($ringkas, 0, 40, '…'),
                ];
            }
            $grup[] = ['jenis' => 'tambah', 'tpl' => $sl['t'], 'teks' => $sl['add']];
        }
        $jumlahKolom = array_sum(array_map(fn ($g) => count($g['kunci'] ?? []), $grup));
        $metaBidang = [];
        foreach ($grup as $g) {
            foreach ($g['kunci'] ?? [] as $k) {
                $metaBidang[$k] = $iso->meta($k, $this->daftar);
            }
        }

        $semuaTema = Tema::terbit()->urut()->get();
        $terkunci = $semuaTema->mapWithKeys(fn (Tema $t) => [$t->id => $kuota->temaTerkunci($t, auth()->user())]);

        $penerbit = app(PenerbitUndangan::class);
        $blokir = $this->dialogTerbit ? $penerbit->blokir($u) : [];
        $saran = $this->dialogTerbit ? $penerbit->peringatan($u) : [];

        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'app.undangyah.com';
        $alamat = $host.'/'.($u->slug ?? '');
        $url = rtrim((string) config('app.url'), '/').'/'.($u->slug ?? '');

        return view('livewire.editor-undangan', [
            'u' => $u,
            'tema' => $tema,
            'kanvas' => $this->kanvas($u, $tema),
            'baris' => $baris, 'konten' => $konten, 'fitur' => $fitur,
            'aktif' => $aktif, 'definisiAktif' => $definisi[$aktif], 'bisaNaik' => $posisi !== false && $posisi > 0, 'bisaTurun' => $posisi !== false && $posisi < count($anggota) - 1,
            'bisaSembunyi' => $aktif !== 'sampul', 'bagianTersembunyi' => in_array($aktif, $this->bagian['sembunyi'], true),
            'adaFoto' => in_array($aktif, ['sampul', 'foto', 'mempelai', 'social', 'gallery', 'penutup'], true),
            'grup' => $grup, 'jumlahKolom' => $jumlahKolom, 'metaBidang' => $metaBidang,
            'semuaTema' => $semuaTema, 'terkunci' => $terkunci,
            'blokir' => $blokir, 'saran' => $saran,
            'alamat' => $alamat, 'url' => $url,
            'tersimpanJam' => $u->draf_disimpan_at ? Carbon::parse($u->draf_disimpan_at)->timezone(config('app.timezone'))->format('H:i') : null,
            'rutaPratinjau' => Route::has('pratinjau') ? route('pratinjau', ['undangan' => $u->id]) : null,
            'rutaBagikan' => Route::has('bagikan') ? route('bagikan') : null,
            'tahap' => $this->tahapTerbit,
        ])->layout('layouts::editor', ['judul' => 'Edit Undangan']);
    }
}
