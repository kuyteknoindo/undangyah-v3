<?php

namespace Database\Factories;

use App\Models\Tamu;
use App\Models\Undangan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tamu> */
class TamuFactory extends Factory
{
    protected $model = Tamu::class;

    public function definition(): array
    {
        return [
            'undangan_id' => Undangan::factory(),
            'nama' => fake()->name(),
            'hp' => '08'.fake()->numerify('##########'),
            'grup' => fake()->randomElement(['Keluarga', 'Teman', 'Kolega']),
            'jumlah' => fake()->numberBetween(1, 4),
        ];
    }
}
