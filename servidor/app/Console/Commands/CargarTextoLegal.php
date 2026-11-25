<?php

namespace App\Console\Commands;

use App\TextosLegales\DocumentosLegales;
use Illuminate\Console\Command;

/**
 * Carga la versión nueva del aviso de privacidad o de los términos que entregue EL CLIENTE: deja el archivo, tal cual,
 * como el texto vigente del documento en el almacenamiento del entorno, en lugar del anterior. Su dirección lo muestra
 * desde ese momento y no cambia; no se modifica ni se despliega el código (textos-legales-E04;
 * NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7).
 *
 *     php artisan textos-legales:cargar aviso-de-privacidad /ruta/al/aviso-de-privacidad.txt
 *     php artisan textos-legales:cargar terminos /ruta/a/los/terminos.txt
 *
 * El archivo es el texto del documento en UTF-8. No se redacta ni se cambia (DD-080). Un documento que no existe, un
 * archivo que no se puede leer, vacío o que no está en UTF-8 no se carga, y la versión publicada se queda.
 */
class CargarTextoLegal extends Command
{
    protected $signature = 'textos-legales:cargar
        {documento : aviso-de-privacidad o terminos}
        {archivo : El archivo de texto UTF-8 con la versión nueva, tal como la entregó EL CLIENTE}';

    protected $description = 'Carga la versión nueva del aviso de privacidad o de los términos, sin tocar el código';

    public function handle(DocumentosLegales $documentos): int
    {
        $documento = (string) $this->argument('documento');
        $archivo = (string) $this->argument('archivo');

        if (! $documentos->existe($documento)) {
            return $this->fallar(sprintf(
                'No existe el documento "%s". Los documentos son: %s.',
                $documento,
                implode(', ', $documentos->documentos()),
            ));
        }

        $texto = is_file($archivo) && is_readable($archivo) ? file_get_contents($archivo) : false;

        if ($texto === false) {
            return $this->fallar("No se pudo leer el archivo {$archivo}.");
        }

        if (trim($texto) === '') {
            return $this->fallar('El archivo está vacío.');
        }

        if (! mb_check_encoding($texto, 'UTF-8')) {
            return $this->fallar('El archivo no está en UTF-8: guárdalo como texto UTF-8 y vuelve a cargarlo.');
        }

        if (! $documentos->cargar($documento, $texto)) {
            return $this->fallar('El almacenamiento no guardó el archivo.');
        }

        $this->components->info(sprintf(
            '%s: versión nueva cargada. Se publica en %s.',
            $documentos->titulo($documento),
            $documentos->direccion($documento),
        ));

        return self::SUCCESS;
    }

    private function fallar(string $motivo): int
    {
        $this->components->error($motivo.' No se cargó nada: la versión publicada se queda.');

        return self::FAILURE;
    }
}
