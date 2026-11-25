<?php

namespace Database\Factories;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Las cuentas de las pruebas. Nacen activas, con la contraseña CONTRASENA y sin rol; el rol se pide con conRol().
 *
 *     Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'persona@ejemplo.com']);
 *     Usuario::factory()->conRol(Rol::USUARIO)->create(['password' => null]); // entra con Google o con Apple
 *
 * @extends Factory<Usuario>
 */
class UsuarioFactory extends Factory
{
    /** La contraseña de toda cuenta de prueba; el modelo la guarda con hash. */
    public const CONTRASENA = 'contrasena-de-prueba';

    protected $model = Usuario::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'correo' => fake()->unique()->safeEmail(),
            'password' => self::CONTRASENA,
        ];
    }

    /**
     * La cuenta con el rol de ese código: Rol::USUARIO, Rol::ADMINISTRACION, etcétera.
     */
    public function conRol(string $codigo): static
    {
        return $this->afterCreating(function (Usuario $usuario) use ($codigo): void {
            $usuario->roles()->attach(Rol::where('codigo', $codigo)->sole());
        });
    }
}
