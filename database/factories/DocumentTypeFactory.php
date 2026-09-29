<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->word());

        return [
            'code' => Str::slug($name),
            'name' => $name,
            'description' => null,
            'schema' => null,
            'version' => '1.0',
            'is_active' => true,
        ];
    }
}
