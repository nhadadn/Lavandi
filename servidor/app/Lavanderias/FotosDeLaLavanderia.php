<?php

namespace App\Lavanderias;

use App\Fotos\AlmacenDeFotos;
use App\Models\Lavanderia;
use App\Models\LavanderiaFoto;
use Illuminate\Http\UploadedFile;

/**
 * Las fotos de la lavandería en el almacenamiento privado de fotos del entorno (App\Fotos\AlmacenDeFotos; ADR-0006). En
 * la base solo va su ruta, en lavanderia_fotos, con su orden; la app y el panel las ven con una URL firmada que vence.
 * La ruta nunca sale como campo de la API.
 *
 * El registro pide una foto (Módulo B #1): es la principal, la de menor orden. Cambiarla reemplaza esa misma fila y
 * borra el archivo anterior; las demás fotos de la lavandería, si las hay, no se tocan. El perfil que ve el usuario las
 * muestra todas, en su orden (perfil-lavanderia-E03).
 */
final class FotosDeLaLavanderia
{
    public function __construct(private readonly AlmacenDeFotos $almacen) {}

    /**
     * Guarda la foto nueva como la principal de la lavandería. Devuelve la ruta del archivo que reemplazó, que se borra
     * cuando la transacción quede guardada (borrar()), o null si no había.
     *
     * @param  list<string>  $guardadas  Aquí se anota la ruta nueva, para borrarla si la transacción no se guarda.
     */
    public function reemplazarPrincipal(Lavanderia $lavanderia, UploadedFile $foto, array &$guardadas): ?string
    {
        $ruta = $this->almacen->guardar($foto, 'lavanderias/'.$lavanderia->id_publico);
        $guardadas[] = $ruta;

        $principal = $this->principal($lavanderia);

        if ($principal === null) {
            $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => 1]);

            return null;
        }

        $anterior = $principal->ruta;
        $principal->update(['ruta' => $ruta]);

        return $anterior;
    }

    public function principal(Lavanderia $lavanderia): ?LavanderiaFoto
    {
        return $lavanderia->fotos()->orderBy('orden')->orderBy('id_lavanderia_fotos')->first();
    }

    /**
     * La URL firmada con que la app o el panel ven la foto, y cuándo vence, en UTC.
     *
     * @return array{url: string, vence_en: string}
     */
    public function url(LavanderiaFoto $foto): array
    {
        return $this->almacen->urlFirmada($foto->ruta);
    }

    /**
     * Borra los archivos, sin fallar si alguno ya no está.
     *
     * @param  list<string>  $rutas
     */
    public function borrar(array $rutas): void
    {
        $this->almacen->borrar($rutas);
    }
}
