<?php

namespace App\Livewire\Admin;

use App\Models\LogAksiAdmin;
use App\Support\LabelAksiAdmin;
use App\Support\PenyamarLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Panel Admin > Log Audit: BACA SAJA atas `log_aksi_admin` (siapa, apa, kapan, dari IP mana). Tidak ada aksi ubah, hapus,
 * atau ekspor di sini; jejak audit tidak boleh bisa dirapikan dari panel. Metadata di dialog detail disamarkan lagi saat dibaca.
 */
class LogAudit extends Component
{
    public const PER_HALAMAN = 20;

    #[Url(as: 'cari', except: '')]
    public string $cari = '';

    #[Url(as: 'dari', except: '')]
    public string $dari = '';

    #[Url(as: 'sampai', except: '')]
    public string $sampai = '';

    #[Url(as: 'admin', except: '')]
    public string $admin = '';

    #[Url(as: 'aksi', except: '')]
    public string $aksi = '';

    public int $halaman = 1;

    public bool $siap = false;

    #[Locked]
    public ?int $detailId = null;

    public bool $dialogDetail = false;

    public function mount(): void
    {
        $this->pastikanAdmin();
        $this->normalisasi();
    }

    public function muat(): void
    {
        $this->pastikanAdmin();
        $this->siap = true;
    }

    private function pastikanAdmin(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    private function normalisasi(): void
    {
        $this->cari = mb_substr(trim($this->cari), 0, 100);
        $this->dari = $this->tanggalSah($this->dari);
        $this->sampai = $this->tanggalSah($this->sampai);
        if ($this->dari !== '' && $this->sampai !== '' && $this->dari > $this->sampai) {
            [$this->dari, $this->sampai] = [$this->sampai, $this->dari];
        }
        $this->admin = mb_substr(trim($this->admin), 0, 190);
        $this->aksi = mb_substr(trim($this->aksi), 0, 60);
    }

    private function tanggalSah(string $nilai): string
    {
        $nilai = trim($nilai);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $nilai, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return '';
        }

        return $nilai;
    }

    public function updated(string $nama): void
    {
        if (in_array($nama, ['cari', 'dari', 'sampai', 'admin', 'aksi'], true)) {
            $this->pastikanAdmin();
            $this->normalisasi();
            $this->halaman = 1;
        }
    }

    public function aturUlangFilter(): void
    {
        $this->pastikanAdmin();
        $this->reset('cari', 'dari', 'sampai', 'admin', 'aksi');
        $this->halaman = 1;
    }

    public function ke(int $halaman): void
    {
        $this->pastikanAdmin();
        $this->halaman = max(1, $halaman);
    }

    /** Hanya membuka dialog baca-saja; tidak mengubah data apa pun. */
    public function bukaDetail(int $id): void
    {
        $this->pastikanAdmin();
        $this->detailId = LogAksiAdmin::query()->findOrFail($id)->id;
        $this->dialogDetail = true;
    }

    public function tutupDetail(): void
    {
        $this->dialogDetail = false;
        $this->detailId = null;
    }

    private function kueri(): Builder
    {
        $q = LogAksiAdmin::query();
        if ($this->admin !== '') {
            $q->where('admin_email', $this->admin);
        }
        if ($this->aksi !== '') {
            $q->where('aksi', $this->aksi);
        }
        // Rentang setengah terbuka (>= awal hari, < awal hari berikutnya): tanpa fungsi tanggal khusus driver, memakai indeks created_at.
        if ($this->dari !== '') {
            $q->where('created_at', '>=', Carbon::parse($this->dari)->startOfDay());
        }
        if ($this->sampai !== '') {
            $q->where('created_at', '<', Carbon::parse($this->sampai)->addDay()->startOfDay());
        }

        $mentah = mb_strtolower(trim($this->cari));
        if ($mentah !== '') {
            $kata = addcslashes($mentah, '\\%_');
            $kodeCocok = LabelAksiAdmin::kodeCocok($mentah);
            $q->where(function (Builder $w) use ($kata, $kodeCocok) {
                foreach (['aksi', 'admin_email', 'target_email', 'sasaran', 'ip'] as $kolom) {
                    $w->orWhereRaw("lower({$kolom}) like ? escape '\\'", ["%{$kata}%"]);
                }
                if ($kodeCocok !== []) {
                    $w->orWhereIn('aksi', $kodeCocok);
                }
            });
        }

        return $q->orderByDesc('created_at')->orderByDesc('id');
    }

    public function render()
    {
        $this->pastikanAdmin();
        $data = [
            'daftarAdmin' => collect(),
            'daftarAksi' => collect(),
            'baris' => null,
            'detail' => null,
            'rincian' => null,
        ];

        if ($this->siap) {
            $data['daftarAdmin'] = LogAksiAdmin::query()->select('admin_email')->distinct()->orderBy('admin_email')->pluck('admin_email');
            $data['daftarAksi'] = LogAksiAdmin::query()->select('aksi')->distinct()->pluck('aksi')
                ->mapWithKeys(fn (string $kode) => [$kode => LabelAksiAdmin::label($kode)])->sort();

            $kolom = ['id', 'admin_id', 'admin_email', 'target_email', 'sasaran', 'aksi', 'ip', 'created_at'];
            $baris = $this->kueri()->with('admin:id,name')->paginate(self::PER_HALAMAN, $kolom, 'halaman', max(1, $this->halaman));
            if ($baris->isEmpty() && $baris->total() > 0) {
                $this->halaman = $baris->lastPage();
                $baris = $this->kueri()->with('admin:id,name')->paginate(self::PER_HALAMAN, $kolom, 'halaman', $this->halaman);
            }
            $data['baris'] = $baris;

            if ($this->detailId && ($detail = LogAksiAdmin::query()->with('admin:id,name')->find($this->detailId))) {
                $data['detail'] = $detail;
                $data['rincian'] = PenyamarLog::saring($detail->rincian);
            }
        }

        $data['adaFilter'] = $this->cari !== '' || $this->dari !== '' || $this->sampai !== '' || $this->admin !== '' || $this->aksi !== '';

        return view('livewire.admin.audit.index', $data)
            ->layout('layouts::app', ['judul' => 'Log Audit', 'sub' => 'Jejak aksi admin: siapa melakukan apa, kapan, dan dari mana.']);
    }
}
