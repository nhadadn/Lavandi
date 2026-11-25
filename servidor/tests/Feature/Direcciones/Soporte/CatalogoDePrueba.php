<?php

namespace Tests\Feature\Direcciones\Soporte;

use App\Models\Colonia;
use App\Models\EntidadFederativa;
use App\Models\Municipio;

/**
 * El catálogo del territorio en las pruebas, sin el archivo de Correos de México (openspec/changes/direcciones/
 * design.md, "La carga del catálogo"): estados, municipios y colonias escritos directo en la base.
 *
 * - El de omisión es ficticio: "Municipio de prueba", del "Estado de prueba", con códigos que ningún estado usa.
 * - territorio() pone cuatro municipios reales del territorio del contrato, con sus códigos del archivo de Correos de
 *   México (Durango, Gómez Palacio, Torreón y Matamoros), y colonias de prueba en sus códigos postales. Saltillo no
 *   está: queda fuera del territorio (NSV-2026-001-v8.pdf:4:Cláusula I).
 *
 * Crear dos veces el mismo municipio no lo repite.
 */
final class CatalogoDePrueba
{
    /** El municipio ficticio de omisión. */
    public const MUNICIPIO_DE_PRUEBA = '99001';

    public const DURANGO = '10005';

    public const GOMEZ_PALACIO = '10007';

    public const TORREON = '05035';

    public const MATAMOROS = '05017';

    /** Saltillo, Coahuila: fuera del territorio, nunca en el catálogo. */
    public const SALTILLO = '05030';

    /** Los municipios de territorio(), por su código: su nombre y el de su estado. */
    private const DEL_TERRITORIO = [
        self::DURANGO => ['Durango', 'Durango'],
        self::GOMEZ_PALACIO => ['Gómez Palacio', 'Durango'],
        self::TORREON => ['Torreón', 'Coahuila de Zaragoza'],
        self::MATAMOROS => ['Matamoros', 'Coahuila de Zaragoza'],
    ];

    /**
     * Un municipio del catálogo, con su estado, que se crea si no existe. Los dos primeros números de su código son
     * los del estado.
     */
    public static function municipio(
        string $codigo = self::MUNICIPIO_DE_PRUEBA,
        string $nombre = 'Municipio de prueba',
        string $estado = 'Estado de prueba',
    ): Municipio {
        $entidad = EntidadFederativa::query()->firstOrCreate(['codigo' => substr($codigo, 0, 2)], ['nombre' => $estado]);

        return Municipio::query()
            ->firstOrCreate(['codigo' => $codigo], ['id_entidades_federativas' => $entidad->getKey(), 'nombre' => $nombre])
            ->load('entidadFederativa');
    }

    /**
     * Los cuatro municipios reales y sus colonias de prueba:
     *
     *     34000  Durango        Colonia de prueba Centro (Colonia) y Fraccionamiento de prueba (Fraccionamiento)
     *     35000  Gómez Palacio  Colonia de prueba Gómez (Colonia)
     *     27000  Torreón        Colonia de prueba Torreón (Colonia)
     *     27440  Torreón y Matamoros, un código postal en dos municipios: Ejido de prueba (Ejido) en cada uno
     *
     * @return array<string, Municipio> Por su código.
     */
    public static function territorio(): array
    {
        $municipios = [];

        foreach (self::DEL_TERRITORIO as $codigo => [$nombre, $estado]) {
            $municipios[$codigo] = self::municipio($codigo, $nombre, $estado);
        }

        $colonias = [
            [self::DURANGO, '34000', 'Fraccionamiento de prueba', 'Fraccionamiento'],
            [self::DURANGO, '34000', 'Colonia de prueba Centro', 'Colonia'],
            [self::GOMEZ_PALACIO, '35000', 'Colonia de prueba Gómez', 'Colonia'],
            [self::TORREON, '27000', 'Colonia de prueba Torreón', 'Colonia'],
            [self::TORREON, '27440', 'Ejido de prueba', 'Ejido'],
            [self::MATAMOROS, '27440', 'Ejido de prueba', 'Ejido'],
        ];

        foreach ($colonias as [$municipio, $codigoPostal, $nombre, $tipo]) {
            Colonia::query()->firstOrCreate([
                'id_municipios' => $municipios[$municipio]->getKey(),
                'codigo_postal' => $codigoPostal,
                'nombre' => $nombre,
                'tipo' => $tipo,
            ]);
        }

        return $municipios;
    }
}
