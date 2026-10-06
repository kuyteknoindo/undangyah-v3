<?php

namespace App\Livewire;

use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Undangan;
use App\Services\CsvTamu;
use App\Services\KuotaTamu;
use App\Support\NomorWa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** RSVP masuk dan daftar tamu satu undangan milik pengguna: ringkasan, filter, CRUD tamu, impor/ekspor CSV, pengingat. */
class RsvpTamu extends Component
{
    use WithFileUploads;

    public const PER_HALAMAN = 10;

    private const MAKS_EKSPOR = 5000;

    private const MAKS_PENGINGAT = 200;

    private const STATUS_TAMU = ['semua', 'hadir', 'tidak', 'belum', 'belum_kirim'];

    private const STATUS_JAWABAN = ['semua', 'hadir', 'tidak'];

    #[Locked]
    public ?int $undanganId = null;

    public bool $siap = false;

    public string $tab = 'jawaban';

    public string $cari = '';

    public string $status = 'semua';

    public string $grup = 'semua';

    public string $urut = 'baru';

    public int $halaman = 1;

    /** @var list<int|string> */
    public array $terpilih = [];

    // Dialog tambah/ubah tamu
    public bool $tampilForm = false;

    #[Locked]
    public ?int $tamuId = null;

    public string $nama = '';

    public string $hp = '';

    public string $namaGrup = '';

    public int|string $jumlah = 1;

    // Dialog hapus (satu tamu, satu jawaban, massal)
    public bool $tampilHapus = false;

    #[Locked]
    public ?int $hapusTamuId = null;

    public bool $tampilHapusRsvp = false;

    #[Locked]
    public ?int $hapusRsvpId = null;

    public bool $tampilHapusMassal = false;

    // Dialog ubah RSVP tamu
    public bool $tampilUbahRsvp = false;

    #[Locked]
    public ?int $rsvpTamuId = null;

    public string $rsvpStatus = 'hadir';

    public int|string $rsvpJumlah = 1;

    // Dialog impor CSV
    public bool $tampilImpor = false;

    public $berkasCsv = null;

    /** @var list<array{nama:string,hp:?string,grup:?string,jumlah:int}> */
    #[Locked]
    public array $impor = [];

    /** @var list<array{baris:int,alasan:string}> */
    #[Locked]
    public array $imporGagal = [];

    #[Locked]
    public int $imporDuplikat = 0;

    #[Locked]
    public string $imporGalat = '';

    public bool $tampilPengingat = false;

    public function mount(?string $undangan = null): void
    {
        $milik = Undangan::where('user_id', auth()->id());
        if ($undangan !== null) {
            $dipilih = (clone $milik)->find((int) $undangan);
            abort_unless($dipilih, 404);
        } else {
            $dipilih = (clone $milik)->where('status', Undangan::TERBIT)->latest('updated_at')->latest('id')->first()
                ?? $milik->latest('updated_at')->latest('id')->first();
        }
        $this->undanganId = $dipilih?->id;
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    private function undangan(): ?Undangan
    {
        return $this->undanganId ? Undangan::where('user_id', auth()->id())->find($this->undanganId) : null;
    }

    private function undanganAtauGagal(): Undangan
    {
        $undangan = $this->undangan();
        abort_unless($undangan, 404);

        return $undangan;
    }

    public function pilih(int $id): void
    {
        $milik = Undangan::where('user_id', auth()->id())->find($id);
        abort_unless($milik, 404);
        $this->undanganId = $milik->id;
        $this->setelUbahTampilan(true);
        $this->grup = 'semua';
        $this->cari = '';
        $this->tutupDialog();
    }

    // ---- tampilan: tab, filter, halaman ----

    public function gantiTab(string $tab): void
    {
        $this->tab = $tab === 'tamu' ? 'tamu' : 'jawaban';
        $this->status = 'semua';
        $this->urut = 'baru';
        $this->setelUbahTampilan();
    }

    public function updatedCari(): void
    {
        $this->cari = mb_substr($this->cari, 0, 100);
        $this->setelUbahTampilan();
    }

    public function updatedStatus(): void
    {
        $this->setelUbahTampilan();
    }

    public function updatedGrup(): void
    {
        $this->setelUbahTampilan();
    }

    public function updatedUrut(): void
    {
        $this->setelUbahTampilan();
    }

    public function aturUlangFilter(): void
    {
        $this->cari = '';
        $this->status = 'semua';
        $this->grup = 'semua';
        $this->setelUbahTampilan();
    }

    public function pilihStatus(string $status): void
    {
        $this->status = $status;
        $this->setelUbahTampilan();
    }

    public function sebelumnya(): void
    {
        $this->halaman = max(1, $this->halaman - 1);
        $this->terpilih = [];
    }

    public function berikutnya(): void
    {
        $this->halaman++;
        $this->terpilih = [];
    }

    private function setelUbahTampilan(bool $kosongkanStatus = false): void
    {
        $this->halaman = 1;
        $this->terpilih = [];
        $this->rapikanTampilan();
        if ($kosongkanStatus) {
            $this->status = 'semua';
        }
    }

    /** Nilai filter berasal dari klien: kembalikan ke bawaan bila di luar daftar yang dikenal. */
    private function rapikanTampilan(): void
    {
        if (! in_array($this->tab, ['jawaban', 'tamu'], true)) {
            $this->tab = 'jawaban';
        }
        if (! in_array($this->status, $this->tab === 'tamu' ? self::STATUS_TAMU : self::STATUS_JAWABAN, true)) {
            $this->status = 'semua';
        }
        if (! in_array($this->urut, ['baru', 'lama', 'nama', $this->tab === 'tamu' ? 'status' : 'jumlah'], true)) {
            $this->urut = 'baru';
        }
        $this->grup = mb_substr($this->grup, 0, CsvTamu::MAKS_GRUP);
        $this->cari = mb_substr($this->cari, 0, 100);
        $this->halaman = max(1, $this->halaman);
    }

    // ---- query terfilter (dipakai tampilan dan ekspor) ----

    private function kataKunci(): string
    {
        return addcslashes(mb_strtolower(trim($this->cari)), '\\%_');
    }

    private function queryTamu(Undangan $undangan): Builder
    {
        $q = $undangan->tamu()->getQuery();
        if ($this->grup !== 'semua') {
            $q->where('grup', $this->grup);
        }
        if (($kata = $this->kataKunci()) !== '') {
            $q->where(fn ($w) => $w->whereRaw("lower(nama) like ? escape '\\'", ["%{$kata}%"])
                ->orWhereRaw("lower(coalesce(grup, '')) like ? escape '\\'", ["%{$kata}%"]));
        }
        match ($this->status) {
            'hadir', 'tidak' => $q->whereExists($this->rsvpTamu($this->status)),
            'belum' => $q->whereNotExists($this->rsvpTamu()),
            'belum_kirim' => $q->whereNull('dikirim_at'),
            default => null,
        };
        match ($this->urut) {
            'lama' => $q->orderBy('id'),
            'nama' => $q->orderByRaw('lower(nama)')->orderBy('id'),
            'status' => $q->orderByRaw('case when exists (select 1 from rsvp where rsvp.tamu_id = tamu.id) then 1 else 0 end')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        };

        return $q;
    }

    private function rsvpTamu(?string $status = null): \Closure
    {
        return function ($sub) use ($status) {
            $sub->from('rsvp')->selectRaw('1')->whereColumn('rsvp.tamu_id', 'tamu.id');
            if ($status !== null) {
                $sub->where('rsvp.status', $status);
            }
        };
    }

    private function queryRsvp(Undangan $undangan): Builder
    {
        $q = $undangan->rsvp()->getQuery()->with('tamu:id,nama,grup,kode');
        if (in_array($this->status, ['hadir', 'tidak'], true)) {
            $q->where('status', $this->status);
        }
        if ($this->grup !== 'semua') {
            $q->whereHas('tamu', fn ($t) => $t->where('grup', $this->grup));
        }
        if (($kata = $this->kataKunci()) !== '') {
            $q->where(fn ($w) => $w->whereRaw("lower(nama) like ? escape '\\'", ["%{$kata}%"])
                ->orWhereRaw("lower(coalesce(pesan, '')) like ? escape '\\'", ["%{$kata}%"]));
        }
        match ($this->urut) {
            'lama' => $q->orderBy('id'),
            'nama' => $q->orderByRaw('lower(nama)')->orderBy('id'),
            'jumlah' => $q->orderByDesc('jumlah')->orderByDesc('id'),
            default => $q->orderByDesc('id'),
        };

        return $q;
    }

    // ---- tambah / ubah / hapus tamu ----

    private function bersihkanForm(): void
    {
        $this->nama = CsvTamu::bersih($this->nama);
        $this->hp = trim($this->hp);
        $this->namaGrup = CsvTamu::bersih($this->namaGrup);
    }

    private function batasi(string $kunci, int $maks, int $detik, string $pesan): bool
    {
        $kunci = $kunci.':'.auth()->id();
        if (RateLimiter::tooManyAttempts($kunci, $maks)) {
            $this->dispatch('toast', pesan: $pesan, tipe: 'galat');

            return true;
        }
        RateLimiter::hit($kunci, $detik);

        return false;
    }

    public function bukaTambah(): void
    {
        $this->undanganAtauGagal();
        $this->resetValidation();
        $this->tamuId = null;
        $this->nama = '';
        $this->hp = '';
        $this->namaGrup = '';
        $this->jumlah = 1;
        $this->tampilForm = true;
    }

    public function bukaUbah(int $id): void
    {
        $tamu = $this->undanganAtauGagal()->tamu()->findOrFail($id);
        $this->resetValidation();
        $this->tamuId = $tamu->id;
        $this->nama = $tamu->nama;
        $this->hp = $tamu->hp ? '0'.NomorWa::ringkas($tamu->hp) : '';
        $this->namaGrup = (string) $tamu->grup;
        $this->jumlah = $tamu->jumlah;
        $this->tampilForm = true;
    }

    public function simpanTamu(): void
    {
        $undangan = $this->undanganAtauGagal();
        if ($this->batasi('rsvp-tamu-simpan', 60, 60, 'Terlalu banyak perubahan. Coba lagi sebentar lagi.')) {
            return;
        }
        $this->bersihkanForm();

        $this->validate([
            'nama' => ['required', 'string', 'max:'.CsvTamu::MAKS_NAMA],
            'hp' => ['nullable', 'string', 'max:20', function ($atribut, $nilai, $gagal) {
                if ($nilai !== '' && CsvTamu::normalisasiHp($nilai) === null) {
                    $gagal('Nomor WhatsApp tidak valid. Contoh: 0812 3456 7890.');
                }
            }],
            'namaGrup' => ['nullable', 'string', 'max:'.CsvTamu::MAKS_GRUP],
            'jumlah' => ['required', 'integer', 'min:1', 'max:'.CsvTamu::MAKS_JUMLAH],
        ], [], ['nama' => 'Nama', 'hp' => 'Nomor WhatsApp', 'namaGrup' => 'Grup', 'jumlah' => 'Jumlah tamu']);

        $hp = $this->hp !== '' ? CsvTamu::normalisasiHp($this->hp) : null;
        $kunci = CsvTamu::kunci($this->nama, $hp);
        $sama = $undangan->tamu()
            ->when($this->tamuId, fn ($q) => $q->where('id', '!=', $this->tamuId))
            ->whereRaw('lower(nama) = ?', [mb_strtolower($this->nama)])
            ->get(['nama', 'hp'])
            ->contains(fn ($t) => CsvTamu::kunci($t->nama, $t->hp) === $kunci);
        if ($sama) {
            throw ValidationException::withMessages(['nama' => 'Tamu dengan nama dan nomor yang sama sudah ada.']);
        }

        $data = ['nama' => $this->nama, 'hp' => $hp, 'grup' => $this->namaGrup !== '' ? $this->namaGrup : null, 'jumlah' => (int) $this->jumlah];

        if ($this->tamuId) {
            $tamu = $undangan->tamu()->findOrFail($this->tamuId);
            $tamu->update($data);
            $pesan = 'Data tamu diperbarui.';
        } else {
            if (($sisa = app(KuotaTamu::class)->sisa($undangan)) !== null && $sisa < 1) {
                throw ValidationException::withMessages(['nama' => 'Kuota tamu paketmu untuk undangan ini sudah penuh.']);
            }
            $undangan->tamu()->create($data);
            $pesan = 'Tamu ditambahkan.';
        }

        $this->tampilForm = false;
        $this->dispatch('toast', pesan: $pesan, tipe: 'sukses');
    }

    public function mintaHapus(int $id): void
    {
        $this->undanganAtauGagal()->tamu()->findOrFail($id);
        $this->hapusTamuId = $id;
        $this->tampilHapus = true;
    }

    public function hapusTamu(): void
    {
        $undangan = $this->undanganAtauGagal();
        $tamu = $undangan->tamu()->findOrFail($this->hapusTamuId);
        $nama = $tamu->nama;
        $tamu->delete();
        $this->terpilih = array_values(array_diff($this->idTerpilih(), [$tamu->id]));
        $this->tampilHapus = false;
        $this->hapusTamuId = null;
        $this->dispatch('toast', pesan: 'Tamu '.$nama.' dihapus.', tipe: 'sukses');
    }

    // ---- hapus massal ----

    /** @return list<int> */
    private function idTerpilih(): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $this->terpilih), fn ($i) => $i > 0)));
    }

    public function mintaHapusMassal(): void
    {
        $this->undanganAtauGagal();
        if ($this->idTerpilih() === []) {
            return;
        }
        $this->tampilHapusMassal = true;
    }

    public function hapusMassal(): void
    {
        $undangan = $this->undanganAtauGagal();
        if ($this->batasi('rsvp-tamu-massal', 10, 60, 'Terlalu banyak penghapusan massal. Coba lagi sebentar lagi.')) {
            return;
        }
        $ids = array_slice($this->idTerpilih(), 0, 500);
        $jumlah = $ids === [] ? 0 : $undangan->tamu()->whereIn('id', $ids)->delete();
        $this->terpilih = [];
        $this->tampilHapusMassal = false;
        $this->halaman = 1;
        $this->dispatch('toast', pesan: $jumlah.' tamu dihapus.', tipe: 'sukses');
    }

    public function pilihHalaman(): void
    {
        $undangan = $this->undanganAtauGagal();
        $ids = $this->queryTamu($undangan)->forPage($this->halaman, self::PER_HALAMAN)->pluck('id')->map(fn ($i) => (int) $i)->all();
        $this->terpilih = array_diff($ids, $this->idTerpilih()) === [] ? [] : array_values(array_unique([...$this->idTerpilih(), ...$ids]));
    }

    // ---- RSVP: ubah manual, balik status, hapus ----

    public function bukaUbahRsvp(int $tamuId): void
    {
        $undangan = $this->undanganAtauGagal();
        $tamu = $undangan->tamu()->findOrFail($tamuId);
        $rsvp = $undangan->rsvp()->where('tamu_id', $tamu->id)->first();
        $this->rsvpTamuId = $tamu->id;
        $this->rsvpStatus = $rsvp?->status ?? Rsvp::HADIR;
        $this->rsvpJumlah = $rsvp && $rsvp->jumlah > 0 ? $rsvp->jumlah : $tamu->jumlah;
        $this->resetValidation();
        $this->tampilUbahRsvp = true;
    }

    public function simpanRsvp(): void
    {
        $undangan = $this->undanganAtauGagal();
        $tamu = $undangan->tamu()->findOrFail($this->rsvpTamuId);
        if ($this->batasi('rsvp-tamu-simpan', 60, 60, 'Terlalu banyak perubahan. Coba lagi sebentar lagi.')) {
            return;
        }
        $this->validate([
            'rsvpStatus' => ['required', 'in:'.Rsvp::HADIR.','.Rsvp::TIDAK],
            'rsvpJumlah' => [$this->rsvpStatus === Rsvp::HADIR ? 'required' : 'nullable', 'integer', 'min:1', 'max:'.CsvTamu::MAKS_JUMLAH],
        ], [], ['rsvpStatus' => 'Kehadiran', 'rsvpJumlah' => 'Jumlah tamu']);

        $hadir = $this->rsvpStatus === Rsvp::HADIR;
        Rsvp::updateOrCreate(
            ['undangan_id' => $undangan->id, 'tamu_id' => $tamu->id],
            ['nama' => $tamu->nama, 'status' => $this->rsvpStatus, 'jumlah' => $hadir ? max(1, (int) $this->rsvpJumlah) : 0, 'kode_tamu' => $tamu->kode],
        );
        $this->tampilUbahRsvp = false;
        $this->dispatch('toast', pesan: 'RSVP '.$tamu->nama.' diubah menjadi '.($hadir ? 'hadir' : 'tidak hadir').'.', tipe: 'sukses');
    }

    public function balikStatus(int $id): void
    {
        $rsvp = $this->undanganAtauGagal()->rsvp()->findOrFail($id);
        $hadir = $rsvp->status !== Rsvp::HADIR;
        $rsvp->update([
            'status' => $hadir ? Rsvp::HADIR : Rsvp::TIDAK,
            'jumlah' => $hadir ? max(1, (int) ($rsvp->tamu?->jumlah ?: 1)) : 0,
        ]);
        $this->dispatch('toast', pesan: 'Status '.$rsvp->nama.' diubah menjadi '.($hadir ? 'hadir' : 'tidak hadir').'.', tipe: 'sukses');
    }

    public function mintaHapusRsvp(int $id): void
    {
        $this->undanganAtauGagal()->rsvp()->findOrFail($id);
        $this->hapusRsvpId = $id;
        $this->tampilHapusRsvp = true;
    }

    public function hapusRsvp(): void
    {
        $rsvp = $this->undanganAtauGagal()->rsvp()->findOrFail($this->hapusRsvpId);
        $rsvp->delete();
        $this->tampilHapusRsvp = false;
        $this->hapusRsvpId = null;
        $this->dispatch('toast', pesan: 'Jawaban RSVP '.$rsvp->nama.' dihapus.', tipe: 'sukses');
    }

    // ---- pengingat ----

    public function tandaiDiingatkan(int $id): void
    {
        $tamu = $this->undanganAtauGagal()->tamu()->findOrFail($id);
        $tamu->update(['diingatkan_at' => now()]);
    }

    // ---- impor CSV ----

    public function bukaImpor(): void
    {
        $this->undanganAtauGagal();
        $this->resetImpor();
        $this->tampilImpor = true;
    }

    private function resetImpor(): void
    {
        $this->berkasCsv = null;
        $this->impor = [];
        $this->imporGagal = [];
        $this->imporDuplikat = 0;
        $this->imporGalat = '';
        $this->resetValidation('berkasCsv');
    }

    public function updatedBerkasCsv(): void
    {
        $undangan = $this->undanganAtauGagal();
        $this->impor = [];
        $this->imporGagal = [];
        $this->imporDuplikat = 0;
        $this->imporGalat = '';
        if ($this->batasi('rsvp-tamu-impor', 10, 60, 'Terlalu banyak percobaan impor. Coba lagi sebentar lagi.')) {
            $this->berkasCsv = null;

            return;
        }
        $this->validate(
            ['berkasCsv' => ['required', 'file', 'mimes:csv,txt', 'max:'.(CsvTamu::MAKS_BYTE / 1024)]],
            [],
            ['berkasCsv' => 'Berkas'],
        );

        $ada = $undangan->tamu()->get(['nama', 'hp'])->mapWithKeys(fn ($t) => [CsvTamu::kunci($t->nama, $t->hp) => true])->all();
        $hasil = app(CsvTamu::class)->urai((string) file_get_contents($this->berkasCsv->getRealPath()), $ada);
        $this->imporGalat = (string) $hasil['galat'];
        $this->impor = $hasil['baris'];
        $this->imporGagal = array_slice($hasil['gagal'], 0, 200);
        $this->imporDuplikat = $hasil['duplikat'];
    }

    public function jalankanImpor(): void
    {
        $undangan = $this->undanganAtauGagal();
        if ($this->impor === []) {
            return;
        }
        if ($this->batasi('rsvp-tamu-impor-simpan', 5, 60, 'Terlalu banyak percobaan impor. Coba lagi sebentar lagi.')) {
            return;
        }

        $dibuat = 0;
        $dilewati = 0;
        $terlewatKuota = 0;
        DB::transaction(function () use ($undangan, &$dibuat, &$dilewati, &$terlewatKuota) {
            $ada = $undangan->tamu()->lockForUpdate()->get(['nama', 'hp'])->mapWithKeys(fn ($t) => [CsvTamu::kunci($t->nama, $t->hp) => true])->all();
            $sisa = app(KuotaTamu::class)->sisa($undangan);
            foreach ($this->impor as $baris) {
                $kunci = CsvTamu::kunci($baris['nama'], $baris['hp']);
                if (isset($ada[$kunci])) {
                    $dilewati++;

                    continue;
                }
                if ($sisa !== null && $sisa <= 0) {
                    $terlewatKuota++;

                    continue;
                }
                $undangan->tamu()->create([
                    'nama' => $baris['nama'], 'hp' => $baris['hp'], 'grup' => $baris['grup'], 'jumlah' => $baris['jumlah'],
                ]);
                $ada[$kunci] = true;
                $dibuat++;
                if ($sisa !== null) {
                    $sisa--;
                }
            }
        });

        $this->tampilImpor = false;
        $this->resetImpor();
        $this->halaman = 1;
        $pesan = $dibuat.' tamu diimpor.';
        if ($dilewati > 0) {
            $pesan .= ' '.$dilewati.' duplikat dilewati.';
        }
        if ($terlewatKuota > 0) {
            $pesan .= ' '.$terlewatKuota.' tidak masuk karena kuota tamu penuh.';
        }
        $this->dispatch('toast', pesan: $pesan, tipe: $terlewatKuota > 0 ? 'info' : 'sukses');
    }

    // ---- ekspor CSV ----

    public function ekspor(): ?StreamedResponse
    {
        $undangan = $this->undanganAtauGagal();
        $this->rapikanTampilan();
        if ($this->batasi('rsvp-tamu-ekspor', 10, 60, 'Terlalu banyak ekspor. Coba lagi sebentar lagi.')) {
            return null;
        }
        $csv = app(CsvTamu::class);

        if ($this->tab === 'jawaban') {
            $baris = $this->queryRsvp($undangan)->limit(self::MAKS_EKSPOR)->get()->map(fn (Rsvp $r) => [
                $r->nama, $r->tamu?->grup, $r->status === Rsvp::HADIR ? 'Hadir' : 'Tidak hadir', $r->jumlah, $r->pesan, $r->created_at?->format('Y-m-d H:i'),
            ]);
            $isi = $csv->tulis(['Nama', 'Grup', 'Status', 'Jumlah orang', 'Pesan', 'Waktu'], $baris);
            $awalan = 'rsvp-';
        } else {
            $tamu = $this->queryTamu($undangan)->limit(self::MAKS_EKSPOR)->get();
            $rsvp = $undangan->rsvp()->whereIn('tamu_id', $tamu->pluck('id'))->pluck('status', 'tamu_id');
            $baris = $tamu->map(fn (Tamu $t) => [
                $t->nama, $t->grup, $t->hp, $t->jumlah, $t->kode, $this->tautanTamu($undangan, $t),
                $t->dikirim_at ? 'Terkirim' : 'Belum dikirim',
                match ($rsvp[$t->id] ?? null) {
                    Rsvp::HADIR => 'Hadir', Rsvp::TIDAK => 'Tidak hadir', default => 'Belum RSVP'
                },
            ]);
            $isi = $csv->tulis(['Nama', 'Grup', 'Nomor WhatsApp', 'Jumlah tamu', 'Kode', 'Tautan pribadi', 'Status kirim', 'RSVP'], $baris);
            $awalan = 'tamu-';
        }

        $nama = $awalan.($undangan->slug ?: 'undangan').'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($isi) {
            echo $isi;
        }, $nama, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ---- bantu ----

    public function tautanTamu(Undangan $undangan, Tamu $tamu): string
    {
        return route('undangan.publik', $undangan->slug).'?to='.$tamu->kode;
    }

    public function tutupDialog(): void
    {
        $this->tampilForm = $this->tampilHapus = $this->tampilHapusRsvp = $this->tampilHapusMassal = false;
        $this->tampilUbahRsvp = $this->tampilImpor = $this->tampilPengingat = false;
        $this->resetImpor();
    }

    private function ringkasan(Undangan $undangan): array
    {
        $rsvp = $undangan->rsvp()->selectRaw('status, count(*) as n, sum(jumlah) as jiwa')->groupBy('status')->get()->keyBy('status');
        $tamu = $undangan->tamu()->selectRaw('count(*) as n, coalesce(sum(jumlah), 0) as jiwa, sum(case when dikirim_at is null then 0 else 1 end) as terkirim')->first();
        $belum = $undangan->tamu()->whereNotExists($this->rsvpTamu())->count();
        $hadir = (int) ($rsvp[Rsvp::HADIR]->n ?? 0);
        $tidak = (int) ($rsvp[Rsvp::TIDAK]->n ?? 0);
        $totalRsvp = $hadir + $tidak;
        $dasar = max((int) $tamu->n, $totalRsvp);

        return [
            'tamu' => (int) $tamu->n,
            'jiwaDiundang' => (int) $tamu->jiwa,
            'terkirim' => (int) $tamu->terkirim,
            'hadir' => $hadir,
            'tidak' => $tidak,
            'belum' => $belum,
            'orangHadir' => (int) ($rsvp[Rsvp::HADIR]->jiwa ?? 0),
            'totalRsvp' => $totalRsvp,
            'tingkat' => $dasar > 0 ? (int) round($totalRsvp / $dasar * 100) : 0,
        ];
    }

    public function render()
    {
        $user = auth()->user();
        $daftar = Undangan::where('user_id', $user->id)->latest('updated_at')->latest('id')->get(['id', 'judul', 'slug', 'status']);
        $undangan = $this->siap ? $this->undangan() : null;
        $data = ['daftar' => $daftar, 'undangan' => $undangan];

        if ($undangan) {
            $this->rapikanTampilan();
            $ringkasan = $this->ringkasan($undangan);
            $kuota = app(KuotaTamu::class);
            $query = $this->tab === 'tamu' ? $this->queryTamu($undangan) : $this->queryRsvp($undangan);
            $total = (clone $query)->count();
            $halamanMaks = max(1, (int) ceil($total / self::PER_HALAMAN));
            $this->halaman = min(max(1, $this->halaman), $halamanMaks);
            $baris = $query->forPage($this->halaman, self::PER_HALAMAN)->get();

            $rsvpTamu = $this->tab === 'tamu'
                ? $undangan->rsvp()->whereIn('tamu_id', $baris->pluck('id'))->get()->keyBy('tamu_id')
                : collect();

            $pengingat = $this->tampilPengingat
                ? $undangan->tamu()->whereNotExists($this->rsvpTamu())->orderByRaw('case when diingatkan_at is null then 0 else 1 end')->orderBy('id')->limit(self::MAKS_PENGINGAT)->get()
                : collect();

            $data += [
                'r' => $ringkasan,
                'baris' => $baris,
                'rsvpTamu' => $rsvpTamu,
                'total' => $total,
                'halamanMaks' => $halamanMaks,
                'dari' => $total === 0 ? 0 : ($this->halaman - 1) * self::PER_HALAMAN + 1,
                'sampai' => min($this->halaman * self::PER_HALAMAN, $total),
                'daftarGrup' => $undangan->tamu()->whereNotNull('grup')->where('grup', '!=', '')->distinct()->orderBy('grup')->pluck('grup'),
                'chips' => $this->chips($undangan, $ringkasan),
                'adaData' => $ringkasan['tamu'] > 0 || $ringkasan['totalRsvp'] > 0,
                'batasTamu' => $kuota->batas($undangan),
                'sisaTamu' => $kuota->sisa($undangan),
                'pengingat' => $pengingat,
                'tautanPublik' => route('undangan.publik', $undangan->slug),
                'maksPengingat' => self::MAKS_PENGINGAT,
            ];
        }

        return view('livewire.rsvp-tamu', $data)->layout('layouts::app', ['judul' => 'RSVP & Tamu', 'sub' => 'Kelola undangan']);
    }

    /** @return list<array{nilai:string,label:string,jumlah:int}> */
    private function chips(Undangan $undangan, array $r): array
    {
        if ($this->tab === 'jawaban') {
            return [
                ['nilai' => 'semua', 'label' => 'Semua', 'jumlah' => $r['totalRsvp']],
                ['nilai' => 'hadir', 'label' => 'Hadir', 'jumlah' => $r['hadir']],
                ['nilai' => 'tidak', 'label' => 'Tidak hadir', 'jumlah' => $r['tidak']],
            ];
        }

        return [
            ['nilai' => 'semua', 'label' => 'Semua', 'jumlah' => $r['tamu']],
            ['nilai' => 'hadir', 'label' => 'Hadir', 'jumlah' => $undangan->tamu()->whereExists($this->rsvpTamu(Rsvp::HADIR))->count()],
            ['nilai' => 'tidak', 'label' => 'Tidak hadir', 'jumlah' => $undangan->tamu()->whereExists($this->rsvpTamu(Rsvp::TIDAK))->count()],
            ['nilai' => 'belum', 'label' => 'Belum RSVP', 'jumlah' => $r['belum']],
            ['nilai' => 'belum_kirim', 'label' => 'Belum dikirim', 'jumlah' => $r['tamu'] - $r['terkirim']],
        ];
    }
}
