<?php

namespace Database\Seeders;

use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Seeder;

/**
 * Las cuentas para probar a mano en la máquina de desarrollo (openspec/changes/alta-de-lavanderias/design.md, "Cómo se
 * entra al panel en local"):
 *
 * - admin@ejemplo.com, con el rol de administración: entra al panel web en /admin.
 * - lavanderia@ejemplo.com, con el rol de socio lavandería y sin lavandería: entra a la app para probar el registro de
 *   la lavandería. Cómo llega un socio real a su cuenta lo fija DD-053 (alta-lavanderias-E04).
 *
 * Las dos con la contraseña de las cuentas de prueba, UsuarioFactory::CONTRASENA, guardada con hash. Solo en local:
 * nunca en el entorno de prueba, que está en internet, ni en producción (ADR-0012). Sembrar dos veces no las duplica.
 */
class CuentasDeDesarrolloSeeder extends Seeder
{
    /** Las cuentas, por su correo, con su rol. */
    public const CUENTAS = [
        'admin@ejemplo.com' => Rol::ADMINISTRACION,
        'lavanderia@ejemplo.com' => Rol::LAVANDERIA,
    ];

    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('Las cuentas de desarrollo solo se siembran en local.');

            return;
        }

        foreach (self::CUENTAS as $correo => $rol) {
            $cuenta = Usuario::withTrashed()->firstOrCreate(['correo' => $correo], ['password' => UsuarioFactory::CONTRASENA]);

            if ($cuenta->trashed()) {
                $cuenta->restore();
            }

            $cuenta->roles()->syncWithoutDetaching([Rol::where('codigo', $rol)->sole()->getKey()]);
        }
    }
}
