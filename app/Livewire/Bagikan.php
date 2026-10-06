<?php

namespace App\Livewire;

use App\Livewire\Concerns\PilihUndangan;
use App\Models\Rsvp;
use App\Models\Tamu;
use App\Models\Tayangan;
use App\Models\Undangan;
use App\Services\CatatPengiriman;
use App\Services\KodeQr;
use App\Services\PesanUndangan;
use App\Support\NomorWa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Bagikan undangan: tautan, QR, kirim ke tamu lewat WhatsApp (`wa.me`), dan pilihan template pesan. */
class Bagikan extends Component
{
    use PilihUndangan;

    public const MAKS_TAMU = 500;

    public bool $siap = false;

    public string $tab = 'tamu';

    public string $q = '';

    public bool $filterBuka = false;

    public string $grup = '';

    /** semua | dibuka | belum */
    public string $status = 'semua';

    public int $batas = 15;

    /** @var list<int|string> */
    public array $sel = [];

    public string $pTemplate = '';

    public string $pSalam = '';

    public bool $dialogTamu = false;

    /** @var list<int> */
    #[Locked]
    public array $modalIds = [];

    public string $mTpl = '';

    public string $mSalam = '';

    public bool $dialogQr = false;

    #[Locked]
    public ?int $qrTamuId = null;

    /** @var list<int> */
    #[Locked]
    public array $dilewati = [];

    public function mount(?string $undangan = null): void
    {
        $this->pilihAwal($undangan);
        $p = app(PesanUndangan::class)->pengaturan(auth()->user());
        $this->pTemplate = $p['template'];
        $this->pSalam = $p['salam'];
    }

    public function muat(): void
    {
        $this->siap = true;
    }

    public function pilih(int $id): void
    {
        $milik = Undangan::where('user_id', auth()->id())->find($id);
        abort_unless($milik, 404);
        $this->undanganId = $milik->id;
        $this->reset('q', 'grup', 'status', 'sel', 'dilewati', 'dialogTamu', 'dialogQr', 'modalIds', 'qrTamuId', 'filterBuka');
        $this->batas = 15;
        $this->resetErrorBag();
    }

    public function ubahTab(string $tab): void
    {
        $this->tab = $tab === 'pesan' ? 'pesan' : 'tamu';
    }

    public function updatedQ(): void
    {
        $this->q = mb_substr($this->q, 0, 80);
        $this->batas = 15;
    }

    public function updatedGrup(): void
    {
        $this->batas = 15;
    }

    public function updatedStatus(): void
    {
        if (! in_array($this->status, ['semua', 'dibuka', 'belum'], true)) {
            $this->status = 'semua';
        }
        $this->batas = 15;
    }

    public function updatedSel(): void
    {
        $this->sel = array_slice(array_values($this->sel), 0, self::MAKS_TAMU);
    }

    public function aturStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedStatus();
    }

    public function resetFilter(): void
    {
        $this->reset('q', 'grup', 'status');
        $this->batas = 15;
    }

    /** `batas` bisa diubah langsung dari klien; jaga di rentang wajar agar render tidak memuat seluruh tamu. */
    public function updatedBatas(): void
    {
        $this->batas = max(1, min(self::MAKS_TAMU, $this->batas));
    }

    public function lagi(): void
    {
        $this->batas = min(self::MAKS_TAMU, $this->batas + 15);
    }

    // ------------------------------------------------------------------ tamu

    private function tamuQuery()
    {
        return Tamu::query()->where('undangan_id', $this->undanganId);
    }

    /** Hanya id milik undangan ini; id lain (dimanipulasi klien) dibuang. @return list<int> */
    private function idMilik(array $id): array
    {
        $bersih = array_values(array_unique(array_filter(array_map('intval', $id), fn (int $x) => $x > 0)));
        $bersih = array_slice($bersih, 0, self::MAKS_TAMU);
        if ($bersih === [] || ! $this->undanganId) {
            return [];
        }

        return $this->tamuQuery()->whereIn('id', $bersih)->pluck('id')->map(fn ($x) => (int) $x)->all();
    }

    /** id tamu yang sudah membuka undangan (tayangan) atau mengisi RSVP. @return list<int> */
    private function idDibuka(): array
    {
        $a = Tayangan::where('undangan_id', $this->undanganId)->whereNotNull('tamu_id')->distinct()->pluck('tamu_id');
        $b = Rsvp::where('undangan_id', $this->undanganId)->whereNotNull('tamu_id')->distinct()->pluck('tamu_id');

        return $a->merge($b)->map(fn ($x) => (int) $x)->unique()->values()->all();
    }

    private function terapkanFilter($query, array $dibuka)
    {
        $kata = trim($this->q);
        if ($kata !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($kata)).'%';
            $query->where(function ($w) use ($like, $kata) {
                $w->whereRaw("lower(nama) like ? escape '\\'", [$like])
                    ->orWhereRaw("lower(coalesce(grup, '')) like ? escape '\\'", [$like]);
                $angka = preg_replace('/\D/', '', $kata) ?? '';
                if (strlen($angka) >= 3) {
                    $w->orWhere('hp', 'like', '%'.NomorWa::ringkas($angka).'%');
                }
            });
        }
        if ($this->grup !== '') {
            $query->where('grup', $this->grup);
        }
        if ($this->status === 'dibuka') {
            $query->whereIn('id', $dibuka ?: [0]);
        } elseif ($this->status === 'belum') {
            $query->whereNotIn('id', $dibuka);
        }

        return $query;
    }

    public function pilihSemuaTampil(): void
    {
        $tampil = $this->terapkanFilter($this->tamuQuery(), $this->idDibuka())->orderByDesc('id')->limit($this->batas)->pluck('id')->map(fn ($x) => (int) $x)->all();
        $terpilih = array_map('intval', $this->sel);
        $this->sel = count(array_diff($tampil, $terpilih)) === 0
            ? array_values(array_diff($terpilih, $tampil))
            : array_values(array_unique([...$terpilih, ...$tampil]));
    }

    public function kosongkanPilihan(): void
    {
        $this->sel = [];
    }

    // ------------------------------------------------------------------ kirim

    public function catatKirim(int $tamuId): void
    {
        $undangan = $this->undanganTerpilih();
        abort_unless($undangan, 404);
        $tamu = $this->tamuQuery()->find($tamuId);
        abort_unless($tamu, 404);
        if (! $undangan->sudahTerbit()) {
            $this->dispatch('toast', pesan: 'Terbitkan undangan dulu sebelum mengirim.', tipe: 'galat');

            return;
        }
        if (! app(CatatPengiriman::class)->catat(auth()->user(), $undangan, $tamu)) {
            $this->dispatch('toast', pesan: 'Terlalu banyak pengiriman dalam semenit. Tunggu sebentar.', tipe: 'galat');
        }
    }

    public function lewati(int $tamuId): void
    {
        $id = $this->idMilik([$tamuId]);
        if ($id !== []) {
            $this->dilewati = array_values(array_unique([...$this->dilewati, $id[0]]));
        }
    }

    public function ulangiAntrean(): void
    {
        $this->dilewati = [];
    }

    // ------------------------------------------------------------------ template per tamu

    public function bukaTemplateTamu(?int $id = null): void
    {
        $ids = $this->idMilik($id ? [$id] : $this->sel);
        if ($ids === []) {
            return;
        }
        $this->modalIds = $ids;
        $pertama = count($ids) === 1 ? $this->tamuQuery()->find($ids[0]) : null;
        $this->mTpl = (string) $pertama?->template_pesan;
        $this->mSalam = (string) $pertama?->salam;
        $this->resetErrorBag();
        $this->dialogTamu = true;
    }

    public function simpanTemplateTamu(): void
    {
        $ids = $this->idMilik($this->modalIds);
        abort_if($ids === [], 404);
        $daftar = app(PesanUndangan::class)->daftar(auth()->user());
        $this->validate([
            'mTpl' => ['nullable', 'string', function ($atribut, $nilai, $gagal) use ($daftar) {
                if ($nilai !== '' && (! isset($daftar[$nilai]) || $daftar[$nilai]['kanal'] !== 'whatsapp')) {
                    $gagal('Template tidak ditemukan.');
                }
            }],
            'mSalam' => ['nullable', 'string', 'in:'.implode(',', array_keys(PesanUndangan::salam()))],
        ], [], ['mTpl' => 'template', 'mSalam' => 'salam']);

        $this->tamuQuery()->whereIn('id', $ids)->update([
            'template_pesan' => $this->mTpl !== '' ? $this->mTpl : null,
            'salam' => $this->mSalam !== '' ? $this->mSalam : null,
        ]);
        $this->dialogTamu = false;
        $this->modalIds = [];
        $this->dispatch('toast', pesan: 'Template pesan disimpan untuk '.count($ids).' tamu.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ QR

    public function bukaQr(int $tamuId): void
    {
        $id = $this->idMilik([$tamuId]);
        abort_if($id === [], 404);
        $this->qrTamuId = $id[0];
        $this->dialogQr = true;
    }

    // ------------------------------------------------------------------ template default

    public function simpanDefault(): void
    {
        $daftar = app(PesanUndangan::class)->daftar(auth()->user());
        $this->validate([
            'pTemplate' => ['required', 'string', function ($atribut, $nilai, $gagal) use ($daftar) {
                if (! isset($daftar[$nilai]) || $daftar[$nilai]['kanal'] !== 'whatsapp') {
                    $gagal('Template tidak ditemukan.');
                }
            }],
            'pSalam' => ['required', 'string', 'in:'.implode(',', array_keys(PesanUndangan::salam()))],
        ], [], ['pTemplate' => 'template', 'pSalam' => 'salam']);

        app(PesanUndangan::class)->simpanPengaturan(auth()->user(), $this->pTemplate, $this->pSalam);
        $this->tab = 'tamu';
        $this->dispatch('toast', pesan: 'Template default disimpan: '.$daftar[$this->pTemplate]['nama'].' · '.PesanUndangan::salam()[$this->pSalam]['pendek'].'.', tipe: 'sukses');
    }

    // ------------------------------------------------------------------ tampilan

    private function tautan(string $nama, array $param = []): ?string
    {
        return Route::has($nama) ? route($nama, $param) : null;
    }

    public function render()
    {
        $data = ['siap' => $this->siap, 'undangan' => null, 'daftar' => collect(), 'tautanBuat' => $this->tautan('buat-undangan')];
        if (! $this->siap) {
            return view('livewire.bagikan', $data)->layout('layouts::app', ['judul' => 'Bagikan Undangan', 'sub' => 'Kirim ke tamu']);
        }

        $undangan = $this->undanganTerpilih();
        $data['daftar'] = $this->daftarUndangan();
        $data['undangan'] = $undangan;
        if (! $undangan) {
            return view('livewire.bagikan', $data)->layout('layouts::app', ['judul' => 'Bagikan Undangan', 'sub' => 'Kirim ke tamu']);
        }

        $layanan = app(PesanUndangan::class);
        $terbit = $undangan->sudahTerbit();
        $daftarTemplate = $layanan->daftar(auth()->user());
        $pengaturan = $layanan->pengaturan(auth()->user(), $daftarTemplate);
        $salam = PesanUndangan::salam();
        $link = PesanUndangan::linkUndangan($undangan);
        $host = parse_url(url('/'), PHP_URL_HOST);

        $dibuka = $this->idDibuka();
        $total = $this->tamuQuery()->count();
        $sudahKirim = $this->tamuQuery()->whereNotNull('dikirim_at')->count();
        $terfilter = $this->terapkanFilter($this->tamuQuery(), $dibuka);
        $jumlahTerfilter = (clone $terfilter)->count();
        $tamu = $terfilter->orderByDesc('id')->limit(max(1, min(self::MAKS_TAMU, $this->batas)))->get();
        $rsvp = Rsvp::where('undangan_id', $undangan->id)->whereIn('tamu_id', $tamu->pluck('id'))->orderBy('id')->get()->keyBy('tamu_id');

        $baris = $tamu->map(function (Tamu $t) use ($undangan, $layanan, $daftarTemplate, $pengaturan, $dibuka, $rsvp, $salam) {
            $khusus = $t->template_pesan && isset($daftarTemplate[$t->template_pesan]) ? $daftarTemplate[$t->template_pesan]['nama'] : null;
            $salamKhusus = $t->salam && isset($salam[$t->salam]) ? $salam[$t->salam]['pendek'] : null;
            $pesan = $layanan->untukTamu($undangan, $t, $daftarTemplate, $pengaturan);

            return [
                'model' => $t,
                'dibuka' => in_array($t->id, $dibuka, true),
                'rsvp' => $rsvp[$t->id]->status ?? null,
                'labelTemplate' => $khusus || $salamKhusus ? trim(($khusus ?? 'Default').($salamKhusus ? ' · '.$salamKhusus : '')) : 'Default',
                'khusus' => (bool) ($khusus || $salamKhusus),
                'urlWa' => PesanUndangan::urlWa($t->hp, $pesan),
                'link' => PesanUndangan::linkUndangan($undangan, $t->kode),
            ];
        });

        $kandidatAntrean = null;
        $sisaAntrean = 0;
        if ($terbit) {
            $antrean = $this->tamuQuery()->whereNull('dikirim_at')->whereNotIn('id', $this->dilewati ?: [0]);
            $terpilih = array_map('intval', $this->sel);
            if ($terpilih !== []) {
                $antrean->whereIn('id', $terpilih);
            }
            $sisaAntrean = (clone $antrean)->count();
            $kandidatAntrean = $antrean->orderBy('id')->first();
        }

        $contoh = $tamu->first(fn (Tamu $t) => in_array($t->id, array_map('intval', $this->sel), true)) ?? $tamu->first();
        $templatePratinjau = $daftarTemplate[$this->pTemplate] ?? $daftarTemplate[$pengaturan['template']];
        $salamPratinjau = isset($salam[$this->pSalam]) ? $this->pSalam : $pengaturan['salam'];
        $pesanUmum = PesanUndangan::isi($daftarTemplate[$pengaturan['template']]['isi'], $undangan, PesanUndangan::SAPAAN_UMUM, $pengaturan['salam']);

        $data += [
            'terbit' => $terbit,
            'link' => $link,
            'linkTampil' => $link ? $host.'/'.$undangan->slug : '',
            'qrUmum' => $link ? KodeQr::svg($link, 240) : '',
            'pesanUmum' => $pesanUmum,
            'judulSurel' => 'Undangan: '.$undangan->judul,
            'total' => $total,
            'sudahKirim' => $sudahKirim,
            'terfilter' => $jumlahTerfilter,
            'baris' => $baris,
            'adaLagi' => $jumlahTerfilter > $tamu->count(),
            'grupOpsi' => $this->tamuQuery()->whereNotNull('grup')->where('grup', '!=', '')->distinct()->orderBy('grup')->pluck('grup'),
            'jumlahAktif' => ($this->grup !== '' ? 1 : 0) + ($this->status !== 'semua' ? 1 : 0),
            'jumlahDibuka' => $this->tamuQuery()->whereIn('id', $dibuka ?: [0])->count(),
            'daftarTemplate' => $daftarTemplate->filter(fn ($t) => $t['kanal'] === 'whatsapp'),
            'salam' => $salam,
            'labelDefault' => $daftarTemplate[$pengaturan['template']]['nama'].' · '.$salam[$pengaturan['salam']]['pendek'],
            'pratinjau' => PesanUndangan::isi($templatePratinjau['isi'], $undangan, $contoh?->nama ?? 'Budi Santoso', $salamPratinjau, $contoh?->kode),
            'namaPratinjau' => $contoh?->nama ?? 'Budi Santoso',
            'terpilih' => count($this->sel),
            'kandidat' => $kandidatAntrean,
            'urlKandidat' => $kandidatAntrean ? PesanUndangan::urlWa($kandidatAntrean->hp, $layanan->untukTamu($undangan, $kandidatAntrean, $daftarTemplate, $pengaturan)) : null,
            'sisaAntrean' => $sisaAntrean,
            'tautanRsvp' => $this->tautan('rsvp', ['undangan' => $undangan->id]) ?? $this->tautan('rsvp'),
            'tautanEditor' => $this->tautan('edit-undangan', ['undangan' => $undangan->id]),
            'tautanTemplate' => $this->tautan('template-pesan', ['undangan' => $undangan->id]),
            'tautanRiwayat' => $this->tautan('riwayat-kirim', ['undangan' => $undangan->id]),
            'qrTamu' => $this->qrTamuData($undangan),
            'modalPratinjau' => $this->pratinjauModal($undangan, $daftarTemplate, $pengaturan),
            'modalJumlah' => count($this->modalIds),
            'modalNama' => count($this->modalIds) === 1 ? $this->tamuQuery()->whereKey($this->modalIds[0])->value('nama') : null,
        ];

        return view('livewire.bagikan', $data)->layout('layouts::app', ['judul' => 'Bagikan Undangan', 'sub' => 'Kirim ke tamu']);
    }

    /** @return ?array{nama:string,link:string,svg:string,berkas:string} */
    private function qrTamuData(Undangan $undangan): ?array
    {
        if (! $this->dialogQr || ! $this->qrTamuId) {
            return null;
        }
        $tamu = $this->tamuQuery()->find($this->qrTamuId);
        if (! $tamu || ! $undangan->slug) {
            return null;
        }
        $link = PesanUndangan::linkUndangan($undangan, $tamu->kode);

        return ['nama' => $tamu->nama, 'link' => $link, 'svg' => KodeQr::svg($link, 240), 'berkas' => 'qr-'.$undangan->slug.'-'.$tamu->kode];
    }

    private function pratinjauModal(Undangan $undangan, Collection $daftar, array $pengaturan): string
    {
        if (! $this->dialogTamu || $this->modalIds === []) {
            return '';
        }
        $tamu = $this->tamuQuery()->find($this->modalIds[0]);
        if (! $tamu) {
            return '';
        }
        $template = $this->mTpl !== '' && isset($daftar[$this->mTpl]) && $daftar[$this->mTpl]['kanal'] === 'whatsapp' ? $daftar[$this->mTpl] : $daftar[$pengaturan['template']];
        $salam = isset(PesanUndangan::salam()[$this->mSalam]) ? $this->mSalam : $pengaturan['salam'];

        return PesanUndangan::isi($template['isi'], $undangan, $tamu->nama, $salam, $tamu->kode);
    }
}
