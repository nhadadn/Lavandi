<?php

namespace App\Console\Commands;

use App\Direcciones\CargaDelCatalogo;
use App\Direcciones\CatalogoNoValido;
use Illuminate\Console\Command;

/**
 * Carga el catálogo del territorio para la dirección asistida, desde los archivos del Catálogo Nacional de Códigos
 * Postales de Correos de México (direcciones-E16; C-238). Qué entra lo dice config/territorio.php, no el comando; la
 * carga está en App\Direcciones\CargaDelCatalogo.
 *
 *     php artisan direcciones:cargar-catalogo ruta/Durango.txt "ruta/Coahuila de Zaragoza.txt"
 *
 * Abrir el territorio es cargar datos, no migrar, como plazas:cargar. El archivo no se versiona (DD-138): cada entorno
 * lo descarga, y la carga se repite cuando Correos de México lo actualiza. Cargarlo en producción espera que Nadir
 * verifique su licencia. Cómo se consigue: openspec/changes/direcciones/design.md, "La carga del catálogo".
 */
class CargarCatalogo extends Command
{
    protected $signature = 'direcciones:cargar-catalogo
        {archivos* : Los archivos TXT de Correos de México, uno por estado}';

    protected $description = 'Carga los estados, los municipios y las colonias del territorio desde los archivos de Correos de México';

    public function handle(CargaDelCatalogo $carga): int
    {
        try {
            $resumen = $carga->cargar(array_values((array) $this->argument('archivos')));
        } catch (CatalogoNoValido $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        foreach ($resumen->primerasLineas as $archivo => $primeraLinea) {
            $this->line("Primera línea de {$archivo}: {$primeraLinea}");
        }

        foreach ($resumen->porEstado as $codigo => $estado) {
            $this->info(sprintf(
                '%s (%s): %s y %s.',
                $estado['nombre'],
                $codigo,
                self::cuantos($estado['municipios'], 'municipio'),
                self::cuantos($estado['colonias'], 'colonia'),
            ));
        }

        if ($resumen->fueraDelTerritorio > 0) {
            $this->line(sprintf('Fuera del territorio: %s, que no se cargaron.', self::cuantos($resumen->fueraDelTerritorio, 'fila')));
        }

        foreach ($resumen->estadosQueNoVinieron as $codigo => $nombre) {
            $this->warn("Ningún archivo trajo {$nombre} ({$codigo}).");
        }

        return self::SUCCESS;
    }

    private static function cuantos(int $numero, string $cosa): string
    {
        return $numero.' '.$cosa.($numero === 1 ? '' : 's');
    }
}
