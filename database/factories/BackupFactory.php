<?php

namespace Database\Factories;

use App\Models\Backup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Backup> */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    public function definition(): array
    {
        $kode = Str::lower(Str::random(6));

        return [
            'tipe' => 'database',
            'nama' => 'backup-uji',
            'berkas' => 'backup-database-'.now()->format('Ymd-His').'-'.$kode.'.dump',
            'format' => 'pgdump-custom',
            'ukuran' => 2048,
            'checksum' => hash('sha256', $kode),
            'status' => Backup::SUKSES,
            'sumber' => 'manual',
            'selesai_at' => now(),
        ];
    }

    public function gagal(string $pesan = 'pg_dump gagal.'): static
    {
        return $this->state(['status' => Backup::GAGAL, 'berkas' => null, 'ukuran' => 0, 'checksum' => null, 'pesan_galat' => $pesan]);
    }
}
