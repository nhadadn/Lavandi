<?php

namespace App\Fotos;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * El almacenamiento privado de fotos del entorno: el disco de config/fotos.php (ADR-0006; ADR-0012). Por la adenda del
 * 2026-11-24 de ADR-0006 (acta-2026-11-20-modelo-y-pasarela.md, decisión 49), fotos_local solo es para la máquina local
 * y las pruebas automáticas, y el entorno de prueba y producción usan fotos_s3; hasta que exista el bucket del entorno
 * de prueba (DD-070), el valor por omisión sigue siendo fotos_local fuera de producción. Guarda las fotos de las
 * lavanderías y la foto de perfil de cada cuenta.
 *
 * En la base solo va la ruta de cada foto; la app y el panel la ven con una URL firmada que vence, siempre con la misma
 * forma, { url, vence_en }. La ruta nunca sale como campo de la API.
 */
final class AlmacenDeFotos
{
    /**
     * Guarda la foto que subió la app en esa carpeta, con un nombre nuevo, y devuelve su ruta.
     */
    public function guardar(UploadedFile $foto, string $carpeta): string
    {
        return (string) $foto->store($carpeta, $this->nombreDelDisco());
    }

    /**
     * Guarda en esa ruta una foto ya hecha, como las de prueba del sembrado.
     */
    public function guardarContenido(string $ruta, string $contenido): void
    {
        $this->disco()->put($ruta, $contenido);
    }

    /**
     * La URL firmada con que la app o el panel ven la foto, y cuándo vence, en UTC. Al volver a pedir el dato que la
     * trae, llega una nueva.
     *
     * @return array{url: string, vence_en: string}
     */
    public function urlFirmada(string $ruta): array
    {
        $vence = CarbonImmutable::now()->addMinutes((int) config('fotos.vigencia_minutos'));

        return [
            'url' => $this->disco()->temporaryUrl($ruta, $vence),
            'vence_en' => $vence->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * Borra los archivos, sin fallar si alguno ya no está.
     *
     * @param  list<string>  $rutas
     */
    public function borrar(array $rutas): void
    {
        if ($rutas !== []) {
            $this->disco()->delete($rutas);
        }
    }

    private function disco(): Filesystem
    {
        return Storage::disk($this->nombreDelDisco());
    }

    private function nombreDelDisco(): string
    {
        return (string) config('fotos.disco');
    }
}
