<?php

namespace Database\Seeders;

use App\Agenda\ParametrosDeAgenda;
use App\Models\Plaza;
use App\Plazas\AperturaDePlaza;
use Illuminate\Database\Seeder;

/**
 * Las plazas de prueba: ficticias, con nombres que lo dicen, y cargadas igual que una plaza real, con
 * App\Plazas\AperturaDePlaza, la carga del comando plazas:cargar (openspec/changes/plazas-y-zonas/design.md,
 * decisión 6). Sus archivos están en database/datos/plazas-de-prueba/.
 *
 * Las de número tienen sus zonas en cuadrados del océano Pacífico, lejos de cualquier domicilio. La Plaza de prueba
 * Durango está en tierra, con una zona de unos 6 por 6 km sobre el centro de la ciudad de Durango, para probar la app
 * desde un teléfono (plazas-E16; DD-134, punto 7). Ninguna es el territorio inicial, que espera DD-093 y DD-094, ni
 * cuenta para plazas-E10.
 *
 * Cada una lleva la agenda de prueba (AGENDA), para que la app pida ventanas (agenda-de-recoleccion, tarea 1.2).
 *
 * Solo se siembran en local y en prueba, nunca en producción (ADR-0012), aunque alguien fuerce el sembrado. Sembrar
 * dos veces no las duplica.
 */
class PlazasDePruebaSeeder extends Seeder
{
    /**
     * La agenda de prueba: 2 horas de anticipación y 3 días hacia adelante, los valores de prueba de agenda-E12. No son
     * los de producción, que fija EL CLIENTE (DD-099).
     */
    private const AGENDA = [
        ParametrosDeAgenda::ANTICIPACION_MINIMA => ['minutos' => 120],
        ParametrosDeAgenda::DIAS_HACIA_ADELANTE => ['dias' => 3],
    ];

    /** Desde cuándo rige la agenda de prueba; fijo, para que sembrar dos veces no la duplique. */
    private const VIGENTE_DESDE = '2026-01-01 00:00:00+00';

    public function run(AperturaDePlaza $apertura): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('Las plazas de prueba no se siembran en producción (ADR-0012).');

            return;
        }

        foreach (glob(database_path('datos/plazas-de-prueba/*.geojson')) ?: [] as $archivo) {
            $this->sembrarAgenda($apertura->cargarArchivo($archivo));
        }
    }

    private function sembrarAgenda(Plaza $plaza): void
    {
        foreach (self::AGENDA as $clave => $valor) {
            $plaza->parametros()->firstOrCreate(
                ['clave' => $clave, 'vigente_desde' => self::VIGENTE_DESDE],
                ['valor' => $valor],
            );
        }
    }
}
