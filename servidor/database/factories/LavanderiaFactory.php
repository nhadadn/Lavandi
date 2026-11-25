<?php

namespace Database\Factories;

use App\Models\Lavanderia;
use App\Models\Plaza;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Las lavanderías de las pruebas: ficticias, activas y administradas por una cuenta nueva con el rol de lavandería.
 * La plaza y el punto se piden con en(); sin plaza, crea una plaza de prueba activa.
 *
 *     Lavanderia::factory()->en($plaza, latitud: 10.5, longitud: -119.5)->create();
 *     Lavanderia::factory()->en($plaza)->suspendida()->create();
 *
 * El punto de omisión, 10.5 de latitud y -119.5 de longitud, cae en el océano Pacífico, dentro de la zona de prueba 1
 * Centro (database/datos/plazas-de-prueba/).
 *
 * @extends Factory<Lavanderia>
 */
class LavanderiaFactory extends Factory
{
    protected $model = Lavanderia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id_plazas' => fn (): int => Plaza::create([
                'nombre' => 'Plaza de prueba '.fake()->unique()->numberBetween(1000, 999999),
                'zona_horaria' => 'America/Mexico_City',
                'activa' => true,
            ])->getKey(),
            'id_usuarios' => fn (): int => Usuario::factory()->conRol(Rol::LAVANDERIA)->create()->getKey(),
            'nombre' => 'Lavandería de prueba '.fake()->unique()->numberBetween(1, 999999),
            'descripcion' => 'Descripción de prueba.',
            'calle' => 'Calle de prueba',
            'numero' => '1',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '00000',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
            'estado' => Lavanderia::ACTIVA,
            'aprobada_en' => now(),
        ];
    }

    /**
     * En esa plaza y en ese punto.
     */
    public function en(Plaza $plaza, float $latitud = 10.5, float $longitud = -119.5): static
    {
        return $this->state([
            'id_plazas' => $plaza->getKey(),
            'ubicacion' => ['latitud' => $latitud, 'longitud' => $longitud],
        ]);
    }

    public function suspendida(): static
    {
        return $this->state(['estado' => Lavanderia::SUSPENDIDA]);
    }

    public function deBaja(): static
    {
        return $this->state(['estado' => Lavanderia::BAJA]);
    }

    public function pendiente(): static
    {
        return $this->state(['estado' => Lavanderia::PENDIENTE, 'aprobada_en' => null]);
    }
}
