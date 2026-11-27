<?php

namespace Database\Factories;

use App\Models\Testimonio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los testimonios de las pruebas: inventados, nunca de EL CLIENTE (DD-117). Nacen inactivos, como en la base; activo()
 * los publica.
 *
 *     Testimonio::factory()->activo()->create(['orden' => 2]);
 *
 * @extends Factory<Testimonio>
 */
class TestimonioFactory extends Factory
{
    protected $model = Testimonio::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 999999);

        return [
            'autor' => "Persona de prueba {$numero}",
            'texto' => "Opinión de prueba {$numero}.",
            'activo' => false,
            'orden' => 0,
        ];
    }

    public function activo(): static
    {
        return $this->state(['activo' => true]);
    }
}
