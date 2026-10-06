<?php

namespace Database\Factories;

use App\Models\Tema;
use App\Models\Undangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Undangan> */
class UndanganFactory extends Factory
{
    protected $model = Undangan::class;

    public function definition(): array
    {
        $judul = 'Undangan '.fake()->firstName().' & '.fake()->firstName();

        return [
            'user_id' => User::factory(),
            'tema_id' => null,
            'judul' => $judul,
            'jenis' => 'Pernikahan',
            'slug' => Str::slug($judul).'-'.Str::lower(Str::random(4)),
            'status' => Undangan::DRAF,
            'isi' => [],
            'tanggal_acara' => now()->addMonths(3)->toDateString(),
            'lokasi_ringkas' => 'Jakarta',
        ];
    }

    public function terbit(): static
    {
        return $this->state(fn () => ['status' => Undangan::TERBIT, 'diterbitkan_at' => now(), 'terbitan' => ['judul' => 'x']]);
    }

    public function dengan(Tema $tema): static
    {
        return $this->state(['tema_id' => $tema->id]);
    }
}
