<?php

namespace App\Livewire\Notifikasi;

use App\Models\Notifikasi as ModelNotifikasi;
use App\Services\Notifikasi as LayananNotifikasi;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Daftar extends Component
{
    private const LANGKAH = 30;

    #[Url(as: 'filter', except: 'semua')]
    public string $filter = 'semua';

    public int $batas = self::LANGKAH;

    /** Id yang baru disembunyikan, untuk tombol Urungkan. Dikunci agar klien tak bisa mengisinya; pemilik tetap dicek di layanan. */
    #[Locked]
    public array $urungkan = [];

    public string $pesan = '';

    /** @return list<string> */
    private function filterValid(): array
    {
        return ['semua', 'unread', ...array_keys(ModelNotifikasi::JENIS)];
    }

    public function pilihFilter(string $filter): void
    {
        $this->filter = in_array($filter, $this->filterValid(), true) ? $filter : 'semua';
        $this->batas = self::LANGKAH;
    }

    public function muatLagi(): void
    {
        $this->batas += self::LANGKAH;
    }

    public function tandaiDibaca(int $id, LayananNotifikasi $layanan): void
    {
        $layanan->tandaiDibaca(auth()->id(), $id);
        $this->segarkanBadge($layanan);
    }

    public function tandaiBelumDibaca(int $id, LayananNotifikasi $layanan): void
    {
        $layanan->tandaiBelumDibaca(auth()->id(), $id);
        $this->segarkanBadge($layanan);
    }

    public function tandaiSemuaDibaca(LayananNotifikasi $layanan): void
    {
        $layanan->tandaiSemuaDibaca(auth()->id());
        $this->segarkanBadge($layanan);
    }

    public function hapus(int $id, LayananNotifikasi $layanan): void
    {
        if ($layanan->sembunyikan(auth()->id(), $id) > 0) {
            $this->urungkan = [$id];
            $this->pesan = 'Notifikasi dihapus.';
        }
        $this->segarkanBadge($layanan);
    }

    public function hapusYangDibaca(LayananNotifikasi $layanan): void
    {
        $ids = $layanan->sembunyikanYangDibaca(auth()->id());
        if ($ids->isNotEmpty()) {
            $this->urungkan = $ids->all();
            $this->pesan = $ids->count().' notifikasi yang sudah dibaca dihapus.';
        }
        $this->segarkanBadge($layanan);
    }

    public function batalkanHapus(LayananNotifikasi $layanan): void
    {
        $layanan->pulihkan(auth()->id(), $this->urungkan);
        $this->tutupPesan();
        $this->segarkanBadge($layanan);
    }

    public function tutupPesan(): void
    {
        $this->pesan = '';
        $this->urungkan = [];
    }

    /** Buka tautan notifikasi: tandai dibaca lalu arahkan. Hanya notifikasi milik sendiri. */
    public function buka(int $id, LayananNotifikasi $layanan)
    {
        $n = ModelNotifikasi::query()->where('user_id', auth()->id())->tampil()->findOrFail($id);
        $layanan->tandaiDibaca(auth()->id(), $n->id);

        $tautan = ModelNotifikasi::tautanAman($n->tautan);
        if ($tautan === null) {
            $this->segarkanBadge($layanan);

            return null;
        }

        return $this->redirect($tautan, navigate: str_starts_with($tautan, '/'));
    }

    private function segarkanBadge(LayananNotifikasi $layanan): void
    {
        $this->dispatch('notifikasi-dihitung', jumlah: $layanan->jumlahBelumDibaca(auth()->id()));
    }

    public function render()
    {
        $filter = in_array($this->filter, $this->filterValid(), true) ? $this->filter : 'semua';
        $milik = ModelNotifikasi::query()->where('user_id', auth()->id())->tampil();

        // Satu kueri untuk semua hitungan filter dan ringkasan.
        $baris = (clone $milik)->select('tipe', DB::raw('count(*) as total'), DB::raw('sum(case when dibaca_at is null then 1 else 0 end) as belum'))->groupBy('tipe')->get();
        $perTipe = array_fill_keys(array_keys(ModelNotifikasi::JENIS), 0);
        $total = 0;
        $belum = 0;
        foreach ($baris as $b) {
            $perTipe[ModelNotifikasi::tipeValid($b->tipe)] += (int) $b->total;
            $total += (int) $b->total;
            $belum += (int) $b->belum;
        }
        $sudah = $total - $belum;

        $daftar = clone $milik;
        if ($filter === 'unread') {
            $daftar->belumDibaca();
        } elseif ($filter === ModelNotifikasi::TIPE_SISTEM) {
            $daftar->where(fn ($q) => $q->whereNotIn('tipe', array_diff(array_keys(ModelNotifikasi::JENIS), [ModelNotifikasi::TIPE_SISTEM])));
        } elseif ($filter !== 'semua') {
            $daftar->where('tipe', $filter);
        }

        $jumlahFilter = match ($filter) {
            'semua' => $total,
            'unread' => $belum,
            default => $perTipe[$filter],
        };
        $item = $daftar->latest()->latest('id')->limit($this->batas)->get();

        $filters = [['semua', 'Semua', $total], ['unread', 'Belum dibaca', $belum]];
        foreach (ModelNotifikasi::JENIS as $k => $j) {
            $filters[] = [$k, $j[0], $perTipe[$k]];
        }

        $hariIni = now()->startOfDay();
        $grup = [];
        foreach ($item as $n) {
            $label = $n->created_at >= $hariIni ? 'Hari ini' : ($n->created_at >= $hariIni->copy()->subDay() ? 'Kemarin' : 'Lebih lama');
            $grup[$label][] = $n;
        }

        return view('livewire.notifikasi.daftar', [
            'filters' => $filters,
            'aktif' => $filter,
            'grup' => $grup,
            'jumlahBelum' => $belum,
            'jumlahTotal' => $total,
            'jumlahSudah' => $sudah,
            'sisa' => max(0, $jumlahFilter - $item->count()),
            'kosongSemua' => $total === 0,
            'kosongFilter' => $total > 0 && $item->isEmpty(),
        ])->layout('layouts::app', ['judul' => 'Notifikasi', 'sub' => 'Akun']);
    }

    /** Waktu relatif ringkas: dalam 7 hari "x jam lalu", selebihnya tanggal. */
    public static function waktu(Carbon $t): string
    {
        $detik = max(0, (int) $t->diffInSeconds(now(), false));
        if ($detik >= 7 * 86400) {
            return $t->format('j').' '.['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'][$t->month - 1].' '.$t->year;
        }

        return match (true) {
            $detik < 60 => 'baru saja',
            $detik < 3600 => intdiv($detik, 60).' mnt lalu',
            $detik < 86400 => intdiv($detik, 3600).' jam lalu',
            default => intdiv($detik, 86400).' hari lalu',
        };
    }
}
