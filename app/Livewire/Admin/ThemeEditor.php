<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\RiwayatTema;
use App\Models\Tema;
use App\Services\DokumenTema;
use App\Services\PratinjauUndangan;
use App\Services\RenderUndangan;
use App\Support\SkemaUndangan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Editor tema (Theme Creator): nama, paket, palet warna, font judul, ukuran teks dasar, Background Fixed.
 * Pratinjau langsung memakai RenderUndangan dengan isi contoh di iframe sandbox tanpa skrip.
 *
 * Yang disimpan hanya nilai tervalidasi (hex #RRGGBB, font dari daftar putih, angka berbatas); renderer tidak
 * pernah menerima CSS/HTML bebas. Penyunting visual berbasis widget (kanvas, animasi, Cover Gate, musik global)
 * di prototipe desain belum dibuat: renderer undangan memakai 18 bagian tetap.
 */
class ThemeEditor extends Component
{
    use HakAdminKatalog;

    private const MAKS_RIWAYAT = 20;

    private const BGFIXED = ['global' => true, 'jenis' => 'image', 'section' => []];

    #[Locked]
    public ?int $temaId = null;

    /** Versi tema saat formulir dimuat; dipakai mendeteksi suntingan bersamaan oleh admin lain. */
    #[Locked]
    public int $versiDasar = 0;

    #[Locked]
    public string $status = 'draft';

    #[Locked]
    public bool $arsip = false;

    /** @var array<string,mixed> */
    public array $f = [];

    public bool $kotor = false;

    public bool $dialogRiwayat = false;

    /** @param  int|'baru'|null  $sasaran  id tema yang disunting, atau "baru" */
    public function mount(int|string|null $sasaran = null): void
    {
        $this->pastikanAdmin();
        if ($sasaran === 'baru' || $sasaran === null) {
            $this->f = $this->formBaru();

            return;
        }
        $t = Tema::withTrashed()->findOrFail((int) $sasaran);
        $this->temaId = $t->id;
        $this->versiDasar = (int) $t->versi;
        $this->status = $t->status === 'publish' ? 'publish' : 'draft';
        $this->arsip = $t->deleted_at !== null;
        $this->f = $this->formDari($t);
    }

    /** @return array<string,mixed> */
    private function formBaru(): array
    {
        return ['nama' => '', 'tier' => 'Gratis', 'font_judul' => 'playfair', 'base' => DokumenTema::BASE_BAWAAN, 'bgfixed' => false] + DokumenTema::BAWAAN;
    }

    /** @return array<string,mixed> */
    private function formDari(Tema $t): array
    {
        $f = ['nama' => $t->nama, 'tier' => $t->tier, 'font_judul' => $t->font_judul, 'base' => DokumenTema::base($t->dokumen), 'bgfixed' => ! empty($t->latar_tetap)];
        foreach (array_keys(DokumenTema::WARNA) as $k) {
            $f[$k] = DokumenTema::hex($t->{$k}) ?? DokumenTema::BAWAAN[$k];
        }

        return $f;
    }

    /** Isi yang disalin ke riwayat versi (hanya kolom tervalidasi). @return array<string,mixed> */
    public static function potret(Tema $t): array
    {
        return [
            'nama' => $t->nama, 'tier' => $t->tier, 'font_judul' => $t->font_judul,
            'warna_utama' => $t->warna_utama, 'warna_aksen' => $t->warna_aksen, 'warna_permukaan' => $t->warna_permukaan,
            'warna_halaman' => $t->warna_halaman, 'warna_teks' => $t->warna_teks,
            'dokumen' => DokumenTema::bersihkan($t->dokumen), 'latar_tetap' => $t->latar_tetap,
        ];
    }

    public function updated(string $nama): void
    {
        if (str_starts_with($nama, 'f.')) {
            $this->kotor = true;
            if (is_string($this->f['nama'] ?? null)) {
                $this->f['nama'] = mb_substr($this->f['nama'], 0, 100);
            }
        }
    }

    public function pilihFont(string $font): void
    {
        $this->pastikanAdmin();
        if (array_key_exists($font, DokumenTema::FONT)) {
            $this->f['font_judul'] = $font;
            $this->kotor = true;
        }
    }

    public function pilihTier(string $tier): void
    {
        $this->pastikanAdmin();
        if (in_array($tier, Tema::TIER, true)) {
            $this->f['tier'] = $tier;
            $this->kotor = true;
        }
    }

    public function ubahBgfixed(): void
    {
        $this->pastikanAdmin();
        $this->f['bgfixed'] = empty($this->f['bgfixed']);
        $this->kotor = true;
    }

    public function terapkanPalet(int $i): void
    {
        $this->pastikanAdmin();
        if (! isset(DokumenTema::PALET[$i])) {
            return;
        }
        $this->f = array_merge($this->f, DokumenTema::PALET[$i][1]);
        $this->kotor = true;
    }

    // ------------------------------------------------------------------ simpan

    /** @return array<string,mixed> kolom tema yang sudah divalidasi dan dinormalkan */
    private function validasi(): array
    {
        $this->f['nama'] = trim((string) ($this->f['nama'] ?? ''));
        $aturan = [
            'f.nama' => ['required', 'string', 'min:3', 'max:100'],
            'f.tier' => ['required', 'in:'.implode(',', Tema::TIER)],
            'f.font_judul' => ['required', 'in:'.implode(',', array_keys(DokumenTema::FONT))],
            'f.base' => ['required', 'integer', 'between:'.DokumenTema::BASE_MIN.','.DokumenTema::BASE_MAX],
            'f.bgfixed' => ['boolean'],
        ];
        $atribut = ['f.nama' => 'Nama tema', 'f.tier' => 'Paket', 'f.font_judul' => 'Font judul', 'f.base' => 'Ukuran teks dasar', 'f.bgfixed' => 'Background Fixed'];
        foreach (DokumenTema::WARNA as $k => $label) {
            $aturan['f.'.$k] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
            $atribut['f.'.$k] = $label;
        }
        $this->validate($aturan, ['regex' => ':attribute harus berupa warna #RRGGBB, contoh #1D4ED8.'], $atribut);

        $sama = Tema::query()->when($this->temaId, fn ($q) => $q->whereKeyNot($this->temaId))->whereRaw('lower(nama) = ?', [mb_strtolower($this->f['nama'])])->exists();
        if ($sama) {
            throw ValidationException::withMessages(['f.nama' => 'Nama ini sudah dipakai tema lain.']);
        }

        $data = ['nama' => $this->f['nama'], 'tier' => $this->f['tier'], 'font_judul' => $this->f['font_judul']];
        foreach (array_keys(DokumenTema::WARNA) as $k) {
            $data[$k] = DokumenTema::hex($this->f[$k]);
        }
        $data['dokumen'] = DokumenTema::bersihkan(['global' => ['base' => (int) $this->f['base']]]);

        return $data;
    }

    public function simpan(string $status = 'draft'): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        if ($this->arsip) {
            $this->dispatch('toast', pesan: 'Tema ini diarsipkan. Pulihkan dari daftar tema sebelum mengubahnya.', tipe: 'galat');

            return;
        }
        $status = $status === 'publish' ? 'publish' : 'draft';
        $data = $this->validasi();
        $bgfixed = (bool) $this->f['bgfixed'];

        $periksa = DokumenTema::periksa($data);
        if ($status === 'publish' && DokumenTema::adaGalat($periksa)) {
            $this->dispatch('toast', pesan: 'Belum bisa dipublikasikan: '.$periksa[0]['teks'], tipe: 'galat');

            return;
        }

        $baru = $this->temaId === null;
        $hasil = DB::transaction(function () use ($data, $status, $bgfixed, $baru) {
            if ($baru) {
                $t = Tema::create($data + [
                    'status' => $status, 'latar_tetap' => $bgfixed ? self::BGFIXED : null,
                    'versi' => 1, 'urutan' => (int) Tema::withTrashed()->max('urutan') + 1,
                ]);
                $this->catatRiwayat($t, 'Dibuat');

                return ['tema' => $t, 'berubah' => true];
            }

            $t = Tema::withTrashed()->lockForUpdate()->findOrFail($this->temaId);
            if ((int) $t->versi !== $this->versiDasar) {
                throw ValidationException::withMessages(['f.nama' => 'Tema ini diubah di tempat lain sejak dibuka. Muat ulang halaman agar tidak menimpa perubahan itu.']);
            }
            // Pertahankan bentuk latar_tetap yang sudah ada selama fiturnya tetap menyala.
            $latar = $bgfixed ? (! empty($t->latar_tetap) ? $t->latar_tetap : self::BGFIXED) : null;
            $berubah = self::potret($t) != ($data + ['latar_tetap' => $latar]);
            $t->fill($data + ['latar_tetap' => $latar, 'status' => $status]);
            if ($berubah) {
                $t->versi = (int) $t->versi + 1;
            }
            $t->save();
            if ($berubah) {
                $this->catatRiwayat($t, $status === 'publish' ? 'Dipublikasikan' : 'Simpan draf');
            }

            return ['tema' => $t, 'berubah' => $berubah];
        });

        /** @var Tema $t */
        $t = $hasil['tema'];
        if ($baru) {
            session()->flash('toast', ['pesan' => '"'.$t->nama.'" dibuat'.($status === 'publish' ? ' dan dipublikasikan.' : ' sebagai draf.'), 'tipe' => 'sukses']);
            $this->redirectRoute('admin.theme', ['tema' => $t->id], navigate: true);

            return;
        }
        $this->versiDasar = (int) $t->versi;
        $this->status = $status;
        $this->kotor = false;
        $this->dispatch('toast', pesan: $status === 'publish' ? '"'.$t->nama.'" dipublikasikan. Tampil di tab Tema pengguna.' : 'Draf "'.$t->nama.'" disimpan.', tipe: 'sukses');
    }

    private function catatRiwayat(Tema $t, string $label): void
    {
        $t->riwayat()->create(['versi' => $t->versi, 'label' => $label, 'data' => self::potret($t), 'user_id' => auth()->id()]);
        $buang = $t->riwayat()->orderByDesc('versi')->orderByDesc('id')->pluck('id')->slice(self::MAKS_RIWAYAT);
        if ($buang->isNotEmpty()) {
            RiwayatTema::whereIn('id', $buang)->delete();
        }
    }

    // ------------------------------------------------------------------ riwayat

    public function bukaRiwayat(): void
    {
        $this->pastikanAdmin();
        $this->dialogRiwayat = $this->temaId !== null;
    }

    public function pulihkanVersi(int $riwayatId): void
    {
        $this->pastikanAdmin();
        abort_unless($this->temaId, 404);
        // Hanya riwayat milik tema ini; isinya divalidasi ulang seperti input baru.
        $r = RiwayatTema::where('tema_id', $this->temaId)->findOrFail($riwayatId);
        $d = (array) $r->data;
        $f = $this->f;
        $f['nama'] = is_string($d['nama'] ?? null) ? mb_substr($d['nama'], 0, 100) : $f['nama'];
        $f['tier'] = in_array($d['tier'] ?? null, Tema::TIER, true) ? $d['tier'] : $f['tier'];
        $f['font_judul'] = array_key_exists($d['font_judul'] ?? '', DokumenTema::FONT) ? $d['font_judul'] : $f['font_judul'];
        foreach (array_keys(DokumenTema::WARNA) as $k) {
            $f[$k] = DokumenTema::hex($d[$k] ?? null) ?? $f[$k];
        }
        $f['base'] = DokumenTema::base($d['dokumen'] ?? null);
        $f['bgfixed'] = ! empty($d['latar_tetap']);
        $this->f = $f;
        $this->kotor = true;
        $this->dialogRiwayat = false;
        $this->dispatch('toast', pesan: 'Versi '.$r->versi.' dimuat ke formulir. Tekan Simpan draf untuk menyimpannya.', tipe: 'info');
    }

    // ------------------------------------------------------------------ pratinjau

    /** Warna/font tidak valid (saat mengetik) diganti nilai bawaan hanya untuk pratinjau; penyimpanan tetap menolaknya. */
    private function temaPratinjau(): Tema
    {
        $atribut = ['kode' => 'pratinjau', 'nama' => (string) ($this->f['nama'] ?? ''), 'tier' => in_array($this->f['tier'] ?? '', Tema::TIER, true) ? $this->f['tier'] : 'Gratis'];
        foreach (array_keys(DokumenTema::WARNA) as $k) {
            $atribut[$k] = DokumenTema::hex($this->f[$k] ?? null) ?? DokumenTema::BAWAAN[$k];
        }
        $atribut['font_judul'] = array_key_exists($this->f['font_judul'] ?? '', DokumenTema::FONT) ? $this->f['font_judul'] : 'playfair';
        $atribut['dokumen'] = DokumenTema::bersihkan(['global' => ['base' => $this->f['base'] ?? null]]);

        return new Tema($atribut);
    }

    private function dokumenPratinjau(): string
    {
        $isi = SkemaUndangan::isiAwal();
        $html = RenderUndangan::html($isi, ['urutan' => SkemaUndangan::urutanAwal(), 'sembunyi' => []], $this->temaPratinjau(), ['tamu' => PratinjauUndangan::contohTamu($isi)]);

        return '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            ."<meta http-equiv=\"Content-Security-Policy\" content=\"default-src 'none'; img-src http: https: data:; style-src 'unsafe-inline'\">"
            .'<style>html,body{margin:0;padding:0;background:#fff}</style></head><body>'.$html.'</body></html>';
    }

    public function render()
    {
        $this->pastikanAdmin();
        $warna = [];
        foreach (array_keys(DokumenTema::WARNA) as $k) {
            $warna[$k] = DokumenTema::hex($this->f[$k] ?? null) ?? '';
        }
        $periksa = DokumenTema::periksa($warna);
        $tema = $this->temaId ? Tema::withTrashed()->withCount('undangan')->find($this->temaId) : null;

        return view('livewire.admin.theme.editor', [
            'dokumen' => $this->dokumenPratinjau(),
            'periksa' => $periksa,
            'tema' => $tema,
            'riwayat' => $this->dialogRiwayat && $this->temaId ? RiwayatTema::with('pembuat:id,name')->where('tema_id', $this->temaId)->orderByDesc('versi')->orderByDesc('id')->get() : collect(),
        ]);
    }
}
