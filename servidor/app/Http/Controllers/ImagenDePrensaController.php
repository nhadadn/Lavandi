<?php

namespace App\Http\Controllers;

use App\Models\LogoPrensa;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * La imagen de un logo de prensa de la portada, por una ruta pública (adenda del 2026-11-24 de ADR-0006, decisión 2;
 * NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 10). La imagen vive en el disco privado de fotos del entorno
 * (config('fotos.disco')), en la carpeta prensa; el servidor la lee y la entrega con su tipo y con caché pública, sin
 * URL firmada: una URL que vence no sirve en una página pública.
 *
 * La ruta identifica la imagen por el nombre de su archivo, nunca por la llave interna (ADR-0016, regla 5), y solo
 * entrega la de un logo activo: la de uno inactivo, la de uno sin archivo o cualquier otro nombre responden 404. Nunca
 * entrega otra foto del disco privado. No pide sesión: lo declara Autorizacion::rutasSinSesion().
 *
 * Escenario: landing-E17 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 4.2
 */
class ImagenDePrensaController extends Controller
{
    /**
     * Un día. El nombre del archivo cambia con cada imagen que se sube, así que una imagen nueva nunca toma la anterior
     * de la caché; un logo que se desactiva deja de salir en la portada, que no se guarda en caché.
     */
    private const CACHE = 'public, max-age=86400';

    public function __invoke(string $archivo): StreamedResponse
    {
        $logo = LogoPrensa::query()
            ->where('activo', true)
            ->where('imagen', LogoPrensa::CARPETA.'/'.$archivo)
            ->first();

        /** @var FilesystemAdapter $disco */
        $disco = Storage::disk((string) config('fotos.disco'));

        abort_if($logo === null || ! $disco->exists($logo->imagen), 404);

        return $disco->response($logo->imagen, $archivo, ['Cache-Control' => self::CACHE]);
    }
}
