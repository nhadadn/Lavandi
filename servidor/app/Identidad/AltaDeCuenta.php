<?php

namespace App\Identidad;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Da de alta la cuenta de una persona que pide el servicio: siempre con el rol de usuario, sin importar lo que traiga
 * la petición (registro-E01). El registro de lavanderías y repartidores es otro camino (DD-053).
 *
 * La usan el registro con correo y contraseña y, sin contraseña, la entrada con Google o con Apple (registro-E16).
 *
 * La contraseña se cifra aquí, sin dejárselo al cast hashed del modelo: el cast guarda tal cual un texto que ya tiene
 * forma de hash, y quien lo mandara entraría con la contraseña de la que ese texto es hash (registro-E05).
 */
final class AltaDeCuenta
{
    public function deUsuario(string $correo, ?string $password): Usuario
    {
        $hash = $password === null ? null : Hash::make($password);

        return DB::transaction(function () use ($correo, $hash): Usuario {
            $usuario = Usuario::create(['correo' => $correo, 'password' => $hash]);

            $usuario->roles()->attach(Rol::where('codigo', Rol::USUARIO)->sole());

            return $usuario->load('roles');
        });
    }
}
