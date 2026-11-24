<?php

namespace App\Identidad;

use App\Fotos\AlmacenDeFotos;
use App\Models\Usuario;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * La foto de perfil de la cuenta (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15, "foto"; ADR-0006;
 * perfil-usuario-E03). Vive en el almacenamiento privado de fotos del entorno, en la carpeta de la cuenta; en la base
 * solo va su ruta, en usuarios.foto_ruta, y la app la ve con una URL firmada que vence.
 *
 * La cuenta tiene a lo más una: una foto nueva reemplaza a la anterior, cuyo archivo se borra cuando la nueva ya quedó
 * guardada. Si la nueva no se guarda, su archivo se borra y la anterior sigue (perfil-usuario-E04).
 */
final class FotoDePerfil
{
    public function __construct(private readonly AlmacenDeFotos $almacen) {}

    /**
     * Pone la foto, ya validada, como la de la cuenta.
     */
    public function poner(Usuario $cuenta, UploadedFile $foto): void
    {
        $anterior = $cuenta->foto_ruta;
        $ruta = $this->almacen->guardar($foto, 'usuarios/'.$cuenta->id_publico);

        try {
            $cuenta->forceFill(['foto_ruta' => $ruta])->save();
        } catch (Throwable $error) {
            $this->almacen->borrar([$ruta]);

            throw $error;
        }

        if ($anterior !== null) {
            $this->almacen->borrar([$anterior]);
        }
    }

    /**
     * La URL firmada de la foto de la cuenta y cuándo vence, o null si todavía no pone una.
     *
     * @return array{url: string, vence_en: string}|null
     */
    public function url(Usuario $cuenta): ?array
    {
        return $cuenta->foto_ruta === null ? null : $this->almacen->urlFirmada($cuenta->foto_ruta);
    }
}
