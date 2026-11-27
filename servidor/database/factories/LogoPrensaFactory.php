<?php

namespace Database\Factories;

use App\Models\LogoPrensa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los logos de prensa de las pruebas: inventados, nunca de EL CLIENTE (DD-117). Nacen inactivos, como en la base;
 * activo() los publica. La imagen es solo la ruta en la carpeta prensa del disco de fotos: la prueba que la pide pone el
 * archivo.
 *
 *     LogoPrensa::factory()->activo()->create(['orden' => 2]);
 *
 * @extends Factory<LogoPrensa>
 */
class LogoPrensaFactory extends Factory
{
    protected $model = LogoPrensa::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numero = fake()->unique()->numberBetween(1, 999999);

        return [
            'nombre' => "Medio de prueba {$numero}",
            'imagen' => LogoPrensa::CARPETA."/logo-de-prueba-{$numero}.png",
            'activo' => false,
            'orden' => 0,
        ];
    }

    public function activo(): static
    {
        return $this->state(['activo' => true]);
    }
}
