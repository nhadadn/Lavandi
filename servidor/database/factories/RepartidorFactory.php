<?php

namespace Database\Factories;

use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Los repartidores de las pruebas: ficticios, activos, dados de alta en una plaza y con una cuenta nueva con el rol de
 * repartidor, con su nombre y su teléfono de prueba. La plaza se pide con en(); sin plaza, crea una plaza de prueba
 * activa.
 *
 *     Repartidor::factory()->en($plaza)->create();
 *     Repartidor::factory()->pendiente()->create();            // registrado desde la app, sin alta ni plaza
 *     Repartidor::factory()->en($plaza)->suspendido()->create();
 *
 * @extends Factory<Repartidor>
 */
class RepartidorFactory extends Factory
{
    protected $model = Repartidor::class;

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
            'id_usuarios' => fn (): int => self::cuentaDeRepartidor()->getKey(),
            'tipo_vehiculo' => 'Motocicleta',
            'estado' => Repartidor::ACTIVO,
            'aprobado_en' => now(),
        ];
    }

    /**
     * Una cuenta nueva con el rol de repartidor, su nombre y su teléfono de prueba.
     *
     * @param  array<string, mixed>  $datos
     */
    public static function cuentaDeRepartidor(array $datos = []): Usuario
    {
        $cuenta = Usuario::factory()->conRol(Rol::REPARTIDOR)->create($datos);
        $cuenta->forceFill([
            'nombre' => $datos['nombre'] ?? 'Repartidor de prueba '.fake()->unique()->numberBetween(1, 999999),
            'telefono' => $datos['telefono'] ?? '6180000000',
        ])->save();

        return $cuenta;
    }

    /**
     * Dado de alta en esa plaza.
     */
    public function en(Plaza $plaza): static
    {
        return $this->state(['id_plazas' => $plaza->getKey()]);
    }

    /**
     * Con esa cuenta.
     */
    public function deLaCuenta(Usuario $cuenta): static
    {
        return $this->state(['id_usuarios' => $cuenta->getKey()]);
    }

    /**
     * Registrado desde la app, todavía sin alta ni plaza (repartidor-E01).
     */
    public function pendiente(): static
    {
        return $this->state(['id_plazas' => null, 'estado' => Repartidor::PENDIENTE, 'aprobado_en' => null]);
    }

    public function suspendido(): static
    {
        return $this->state(['estado' => Repartidor::SUSPENDIDO]);
    }

    /**
     * Dado de baja: con su estado y borrado en suave (repartidor-E07).
     */
    public function deBaja(): static
    {
        return $this->state(['estado' => Repartidor::BAJA, 'deleted_at' => now()]);
    }
}
