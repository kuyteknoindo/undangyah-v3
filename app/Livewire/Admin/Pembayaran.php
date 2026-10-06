<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\HakAdminKatalog;
use App\Models\MetodePembayaran;
use App\Models\Pengaturan;
use App\Models\Pesanan;
use App\Services\DataMetodePembayaran;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Admin > Metode Pembayaran: rekening transfer manual / tujuan e-wallet, biaya, kode unik, urutan, dan batas waktu bayar. */
class Pembayaran extends Component
{
    use HakAdminKatalog;

    private const MAKS_LANGKAH = 8;

    /** Contoh biaya di panel pratinjau. */
    private const DASAR_CONTOH = 249000;

    public bool $dialogForm = false;

    #[Locked]
    public ?int $editId = null;

    /** @var array<string,mixed> */
    public array $f = [];

    public bool $dialogHapus = false;

    #[Locked]
    public ?int $hapusId = null;

    public string $kedaluwarsaJam = '24';

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->f = $this->formKosong();
        $this->kedaluwarsaJam = (string) max(1, min(168, (int) Pengaturan::ambil('pembayaran.kedaluwarsa_jam', 24)));
    }

    private function formKosong(): array
    {
        return [
            'label' => '', 'keterangan' => '', 'tipe' => MetodePembayaran::MANUAL, 'biaya_tetap' => '0', 'biaya_persen' => '0', 'kode_unik' => true, 'aktif' => false,
            'bank' => '', 'nomor' => '', 'atas' => '', 'tujuan_label' => 'Nomor e-wallet', 'tujuan_nilai' => '', 'instruksi' => '', 'langkah' => '',
        ];
    }

    // ------------------------------------------------------------------ form

    public function bukaBaru(): void
    {
        $this->pastikanAdmin();
        $this->resetErrorBag();
        $this->editId = null;
        $this->f = $this->formKosong();
        $this->dialogForm = true;
    }

    public function bukaEdit(int $id): void
    {
        $this->pastikanAdmin();
        $m = MetodePembayaran::findOrFail($id);
        $d = (array) ($m->data ?? []);
        $this->resetErrorBag();
        $this->editId = $m->id;
        $this->f = [
            'label' => $m->label, 'keterangan' => (string) $m->keterangan, 'tipe' => $m->tipe === MetodePembayaran::INSTRUKSI ? MetodePembayaran::INSTRUKSI : MetodePembayaran::MANUAL,
            'biaya_tetap' => (string) $m->biaya_tetap, 'biaya_persen' => rtrim(rtrim(number_format((float) $m->biaya_persen, 2, ',', ''), '0'), ',') ?: '0',
            'kode_unik' => (bool) $m->kode_unik, 'aktif' => (bool) $m->aktif,
            'bank' => (string) ($d['bank'] ?? ''), 'nomor' => (string) ($d['nomor'] ?? ''), 'atas' => (string) ($d['atas'] ?? ''),
            'tujuan_label' => (string) ($d['tujuan_label'] ?? 'Nomor e-wallet'), 'tujuan_nilai' => (string) ($d['tujuan_nilai'] ?? ''),
            'instruksi' => (string) ($d['instruksi'] ?? ''), 'langkah' => implode("\n", array_map('strval', (array) ($d['langkah'] ?? []))),
        ];
        $this->dialogForm = true;
    }

    public function simpan(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $metode = $this->editId ? MetodePembayaran::findOrFail($this->editId) : null;
        $instruksi = ($this->f['tipe'] ?? '') === MetodePembayaran::INSTRUKSI;
        $tipe = $instruksi ? MetodePembayaran::INSTRUKSI : MetodePembayaran::MANUAL;
        $this->f['tipe'] = $tipe;

        foreach (['label', 'keterangan', 'bank', 'nomor', 'atas', 'tujuan_label', 'tujuan_nilai', 'instruksi'] as $k) {
            $this->f[$k] = trim(preg_replace('/[\x00-\x08\x0B-\x1F\x7F]+/u', ' ', (string) ($this->f[$k] ?? '')) ?? '');
        }
        $this->f['biaya_tetap'] = $this->angka($this->f['biaya_tetap'] ?? '') ?: '0';
        $this->f['biaya_persen'] = str_replace(',', '.', trim((string) ($this->f['biaya_persen'] ?? '0'))) ?: '0';
        $langkah = array_values(array_filter(array_map(fn ($b) => trim($b), preg_split('/\R/u', (string) ($this->f['langkah'] ?? '')) ?: []), fn ($b) => $b !== ''));
        if ($tipe === MetodePembayaran::MANUAL) {
            $this->f['nomor'] = preg_replace('/[\s.\-]+/', '', $this->f['nomor']) ?? '';
        }

        $aturan = [
            'f.label' => ['required', 'string', 'min:2', 'max:80'],
            'f.keterangan' => ['nullable', 'string', 'max:160'],
            'f.biaya_tetap' => ['required', 'integer', 'min:0', 'max:1000000'],
            'f.biaya_persen' => ['required', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'f.atas' => ['required', 'string', 'max:60'],
            'f.instruksi' => ['nullable', 'string', 'max:300'],
        ];
        if ($tipe === MetodePembayaran::MANUAL) {
            $aturan['f.bank'] = ['required', 'string', 'max:40'];
            $aturan['f.nomor'] = ['required', 'regex:/^\d{6,20}$/'];
        } else {
            $aturan['f.tujuan_label'] = ['required', 'string', 'max:40'];
            $aturan['f.tujuan_nilai'] = ['required', 'string', 'max:40'];
        }
        $this->validate($aturan, [
            'f.nomor.regex' => 'Nomor rekening 6-20 digit angka.',
            'f.biaya_persen.regex' => 'Isi persen 0-100 dengan maksimal 2 angka desimal, contoh 0,7 atau 2.',
            'f.biaya_persen.numeric' => 'Isi persen 0-100, contoh 0,7 atau 2.',
        ], [
            'f.label' => 'Nama metode', 'f.keterangan' => 'Keterangan', 'f.biaya_tetap' => 'Biaya tetap', 'f.biaya_persen' => 'Biaya persen', 'f.atas' => 'Nama pemilik',
            'f.instruksi' => 'Instruksi', 'f.bank' => 'Nama bank', 'f.nomor' => 'Nomor rekening', 'f.tujuan_label' => 'Label tujuan', 'f.tujuan_nilai' => 'Nomor tujuan',
        ]);

        $galat = [];
        if (MetodePembayaran::query()->when($metode, fn ($q) => $q->whereKeyNot($metode->id))->whereRaw('lower(label) = ?', [mb_strtolower($this->f['label'])])->exists()) {
            $galat['f.label'] = 'Nama ini sudah dipakai metode lain.';
        }
        if (count($langkah) > self::MAKS_LANGKAH) {
            $galat['f.langkah'] = 'Maksimal '.self::MAKS_LANGKAH.' langkah.';
        } elseif (collect($langkah)->contains(fn ($b) => mb_strlen($b) > 160)) {
            $galat['f.langkah'] = 'Tiap langkah maksimal 160 karakter.';
        }
        if ($galat) {
            throw ValidationException::withMessages($galat);
        }

        $data = $tipe === MetodePembayaran::MANUAL
            ? ['bank' => $this->f['bank'], 'nomor' => $this->f['nomor'], 'atas' => $this->f['atas']]
            : ['tujuan_label' => $this->f['tujuan_label'], 'tujuan_nilai' => $this->f['tujuan_nilai'], 'atas' => $this->f['atas']];
        $data['instruksi'] = $this->f['instruksi'];
        $data['langkah'] = $langkah;
        // Tanda contoh hanya hilang bila yang tersimpan memang rekening/tujuan nyata.
        $data['placeholder'] = app(DataMetodePembayaran::class)->terlihatContoh($tipe, $data);

        $atribut = [
            'label' => $this->f['label'],
            'keterangan' => $this->f['keterangan'] === '' ? null : $this->f['keterangan'],
            'tipe' => $tipe,
            'biaya_tetap' => (int) $this->f['biaya_tetap'],
            'biaya_persen' => round((float) $this->f['biaya_persen'], 2),
            'kode_unik' => (bool) $this->f['kode_unik'],
            'aktif' => (bool) $this->f['aktif'],
            'data' => $data,
        ];
        $metode ? $metode->update($atribut) : MetodePembayaran::create($atribut + ['kode' => $this->kodeBaru($atribut['label']), 'urutan' => (int) MetodePembayaran::max('urutan') + 1]);

        $this->dialogForm = false;
        $this->dispatch('toast', pesan: 'Metode '.$atribut['label'].' disimpan.'.($data['placeholder'] && $atribut['aktif'] ? ' Perhatian: rekening masih data contoh.' : ''), tipe: $data['placeholder'] && $atribut['aktif'] ? 'info' : 'sukses');
    }

    /** Kode sistem (slug): disalin ke pesanan sebagai metode_kode dan dipakai Checkout; tidak berubah setelah dibuat. */
    private function kodeBaru(string $label): string
    {
        $dasar = Str::slug(Str::limit($label, 24, ''), '-') ?: 'metode';
        $kode = $dasar;
        for ($n = 2; MetodePembayaran::where('kode', $kode)->exists(); $n++) {
            $kode = $dasar.'-'.$n;
        }

        return $kode;
    }

    // ------------------------------------------------------------------ aksi baris

    public function ubahAktif(int $id): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $m = MetodePembayaran::findOrFail($id);
        $m->update(['aktif' => ! $m->aktif]);
        $contoh = $m->aktif && app(DataMetodePembayaran::class)->masihPlaceholder($m);
        $this->dispatch('toast', pesan: $m->label.($m->aktif ? ' diaktifkan.' : ' dinonaktifkan.').($contoh ? ' Perhatian: rekening masih data contoh, ganti dengan rekening nyata.' : ''), tipe: $contoh ? 'info' : 'sukses');
    }

    public function geser(int $id, int $arah): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $daftar = MetodePembayaran::orderBy('urutan')->orderBy('id')->pluck('id')->all();
        $i = array_search($id, $daftar, true);
        $j = $i === false ? false : $i + ($arah < 0 ? -1 : 1);
        if ($i === false || ! isset($daftar[$j])) {
            return;
        }
        [$daftar[$i], $daftar[$j]] = [$daftar[$j], $daftar[$i]];
        DB::transaction(function () use ($daftar) {
            foreach ($daftar as $urut => $metodeId) {
                MetodePembayaran::whereKey($metodeId)->update(['urutan' => $urut + 1]);
            }
        });
    }

    public function tanyaHapus(int $id): void
    {
        $this->pastikanAdmin();
        $m = MetodePembayaran::findOrFail($id);
        if ($this->dipakai($m)) {
            $this->dispatch('toast', pesan: 'Metode ini sudah dipakai pesanan. Nonaktifkan saja agar riwayat tetap utuh.', tipe: 'galat');

            return;
        }
        $this->hapusId = $m->id;
        $this->dialogHapus = true;
    }

    public function hapus(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $m = $this->hapusId ? MetodePembayaran::find($this->hapusId) : null;
        $this->dialogHapus = false;
        if (! $m) {
            return;
        }
        if ($this->dipakai($m)) {
            $this->dispatch('toast', pesan: 'Metode ini sudah dipakai pesanan. Nonaktifkan saja.', tipe: 'galat');

            return;
        }
        $m->delete();
        $this->dispatch('toast', pesan: 'Metode '.$m->label.' dihapus.', tipe: 'sukses');
    }

    private function dipakai(MetodePembayaran $m): bool
    {
        return Pesanan::where('metode_id', $m->id)->orWhere('metode_kode', $m->kode)->exists();
    }

    public function simpanBatasWaktu(): void
    {
        if (! $this->izinTulis()) {
            return;
        }
        $this->kedaluwarsaJam = $this->angka($this->kedaluwarsaJam);
        $this->validate(['kedaluwarsaJam' => ['required', 'integer', 'min:1', 'max:168']], [], ['kedaluwarsaJam' => 'Batas waktu pembayaran']);
        $lama = Pengaturan::ambil('pembayaran', []);
        Pengaturan::simpan('pembayaran', array_merge(is_array($lama) ? $lama : [], ['kedaluwarsa_jam' => (int) $this->kedaluwarsaJam]));
        $this->dispatch('toast', pesan: 'Batas waktu pembayaran '.(int) $this->kedaluwarsaJam.' jam disimpan. Berlaku untuk pesanan baru.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ tampilan

    public function render()
    {
        $this->pastikanAdmin();
        $cek = app(DataMetodePembayaran::class);
        $semua = MetodePembayaran::orderBy('urutan')->orderBy('id')->get();
        $aktif = $semua->where('aktif', true)->values();
        $contohAktif = $aktif->filter(fn ($m) => $cek->masihPlaceholder($m))->values();
        $menungguContoh = Pesanan::where('status', Pesanan::MENUNGGU)->whereNotNull('metode_data')->limit(500)->get(['id', 'metode_data'])
            ->filter(fn ($p) => (bool) (($p->metode_data ?? [])['placeholder'] ?? false))->count();
        $pesananPakai = Pesanan::whereIn('metode_kode', $semua->pluck('kode'))->groupBy('metode_kode')->selectRaw('metode_kode, count(*) as n')->pluck('n', 'metode_kode');

        return view('livewire.admin.pembayaran.index', [
            'semua' => $semua,
            'aktif' => $aktif,
            'contohAktif' => $contohAktif,
            'menungguContoh' => $menungguContoh,
            'pesananPakai' => $pesananPakai,
            'cek' => $cek,
            'dasarContoh' => self::DASAR_CONTOH,
            'editMetode' => $this->editId ? $semua->firstWhere('id', $this->editId) : null,
            'hapusMetode' => $this->hapusId ? $semua->firstWhere('id', $this->hapusId) : null,
            'jamAktif' => max(1, min(168, (int) Pengaturan::ambil('pembayaran.kedaluwarsa_jam', 24))),
            'formContoh' => $this->dialogForm ? app(DataMetodePembayaran::class)->terlihatContoh(($this->f['tipe'] ?? '') === MetodePembayaran::INSTRUKSI ? MetodePembayaran::INSTRUKSI : MetodePembayaran::MANUAL, [
                'bank' => $this->f['bank'] ?? '', 'nomor' => $this->f['nomor'] ?? '', 'tujuan_nilai' => $this->f['tujuan_nilai'] ?? '', 'atas' => $this->f['atas'] ?? '',
            ]) : false,
        ])->layout('layouts::app', ['judul' => 'Metode Pembayaran', 'sub' => 'Penjualan', 'admin' => true]);
    }
}
