<?php

namespace Database\Factories;

use App\Models\Opd;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Opd>
 */
class OpdFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(Str::random(2)).fake()->unique()->numberBetween(1, 99).'.'.fake()->numberBetween(10, 99),
            'name' => 'Dinas '.fake()->unique()->company(),
            'is_active' => true,
        ];
    }
}
