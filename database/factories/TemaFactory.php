<?php

namespace Database\Factories;

use App\Models\Tema;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tema> */
class TemaFactory extends Factory
{
    protected $model = Tema::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'tier' => 'Gratis',
            'status' => 'publish',
            'warna_utama' => '#1D4ED8',
            'warna_aksen' => '#E8C46E',
            'warna_permukaan' => '#F3F6FB',
            'warna_halaman' => '#FFFFFF',
            'warna_teks' => '#10203A',
            'font_judul' => 'playfair',
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => 'draft']);
    }

    public function tier(string $tier): static
    {
        return $this->state(['tier' => $tier]);
    }
}
