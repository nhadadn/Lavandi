<?php

namespace App\Exportacion;

/**
 * En qué va una descarga de un reporte (ADR-0022, decisión 15; openspec/changes/administracion-en-la-app/design.md,
 * sección 4, "Descargas en CSV y Excel"; administracion-app-E57). Lo da App\Exportacion\Exportacion::estado(), sin
 * columna propia: la cola de Filament marca completed_at aunque falle el trabajo que escribe el CSV, así que el estado
 * sale de los archivos.
 *
 * El valor es el código que va en la API.
 */
enum EstadoDeLaDescarga: string
{
    /** La cola todavía no la da por terminada: completed_at vacío. */
    case Generandose = 'generandose';

    /** La cola la dio por terminada y sus dos CSV, el del encabezado y el de las filas, están en su disco. */
    case Lista = 'lista';

    /** La cola la dio por terminada sin alguno de esos dos archivos: el trabajo falló o la cuenta ya no podía bajarla. */
    case NoSePudo = 'no_se_pudo';
}
