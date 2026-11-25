<?php

namespace App\Repartidores;

use App\Fotos\AlmacenDeFotos;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * El registro del repartidor desde la app (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #1;
 * openspec/changes/repartidor/design.md, lecturas 1 a 4; repartidor-E01).
 *
 * Crea, en una transacción, la cuenta con el rol de repartidor, su nombre, su teléfono, su contraseña con hash y su foto
 * de perfil, y la fila de repartidores pendiente, sin plaza ni alta: la plaza la fija la administración al darlo de alta
 * (lectura 2). Mientras está pendiente no ve ni mueve pedidos (repartidor-E03); lo decide App\Autorizacion\Autorizacion.
 *
 * La foto va al almacenamiento privado de fotos del entorno, en la carpeta de la cuenta, como la foto de perfil de
 * cualquier cuenta (App\Identidad\FotoDePerfil; ADR-0006). Si algo falla después de guardarla, el archivo se borra y no
 * queda nada.
 *
 * La contraseña se cifra aquí, sin dejárselo al cast hashed del modelo, como en App\Identidad\AltaDeCuenta: el cast
 * guarda tal cual un texto que ya tiene forma de hash (registro-E05).
 *
 * Qué roles tiene la cuenta y cómo llega la persona a este registro dentro de la app espera DD-053 (repartidor-E04): hoy,
 * el de repartidor y ninguno más, como recomienda DD-053 ("una cuenta, un rol en la v1").
 */
final class RegistroDeRepartidor
{
    /** El estado con que nace el registro: pendiente, hasta que EL CLIENTE lo dé de alta (repartidor-E01). */
    public const ESTADO_AL_REGISTRARSE = Repartidor::PENDIENTE;

    public function __construct(private readonly AlmacenDeFotos $almacen) {}

    /**
     * Registra al repartidor con sus datos, ya validados por App\Http\Requests\RegistroDeRepartidorRequest.
     *
     * @param  array{nombre: string, telefono: string, correo: string, password: string, tipo_vehiculo: string}  $datos
     */
    public function registrar(array $datos, UploadedFile $foto): Repartidor
    {
        $hash = Hash::make($datos['password']);
        $ruta = null;

        try {
            return DB::transaction(function () use ($datos, $foto, $hash, &$ruta): Repartidor {
                $cuenta = Usuario::create(['correo' => $datos['correo'], 'password' => $hash]);
                $cuenta->roles()->attach(Rol::query()->where('codigo', Rol::REPARTIDOR)->sole());

                $ruta = $this->almacen->guardar($foto, 'usuarios/'.$cuenta->id_publico);
                $cuenta->forceFill([
                    'nombre' => $datos['nombre'],
                    'telefono' => $datos['telefono'],
                    'foto_ruta' => $ruta,
                ])->save();

                $repartidor = Repartidor::create([
                    'id_usuarios' => $cuenta->getKey(),
                    'tipo_vehiculo' => $datos['tipo_vehiculo'],
                    'estado' => self::ESTADO_AL_REGISTRARSE,
                ]);

                return $repartidor->setRelation('cuenta', $cuenta->load('roles'))->setRelation('plaza', null);
            });
        } catch (Throwable $error) {
            if ($ruta !== null) {
                $this->almacen->borrar([$ruta]);
            }

            throw $error;
        }
    }
}
