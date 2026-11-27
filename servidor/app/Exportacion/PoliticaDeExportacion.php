<?php

namespace App\Exportacion;

use App\Autorizacion\Autorizacion;
use App\Models\Usuario;
use Illuminate\Auth\Access\Response;

/**
 * El puente entre la ruta de descarga de la exportación de Filament y el componente único de autorización
 * (exportacion-E11; ADR-0019). La ruta es de Filament (filament/exports/{export}/download) y solo sabe preguntar a una
 * Policy de Laravel; esta no decide nada: le pregunta a App\Autorizacion\Autorizacion::puedeDescargarLaExportacion().
 *
 * A una cuenta que no puede bajar el archivo le responde como si no existiera, 404 (ADR-0019, decisión 3), y no 403,
 * que es lo que haría Filament sin esta Policy.
 */
final class PoliticaDeExportacion
{
    public function view(Usuario $usuario, Exportacion $exportacion): Response
    {
        return app(Autorizacion::class)->puedeDescargarLaExportacion($usuario, $exportacion)
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
