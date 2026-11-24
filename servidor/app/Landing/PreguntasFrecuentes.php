<?php

namespace App\Landing;

use JsonException;
use RuntimeException;

/**
 * Las preguntas frecuentes de la portada (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 9). Son de EL CLIENTE
 * (DD-117) y entran como un archivo JSON, sin programar (NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7): una lista de
 * pares {"pregunta": "…", "respuesta": "…"}, en el orden en que se muestran. Su ruta es config('landing.
 * preguntas_frecuentes'), absoluta o relativa a la carpeta del servidor.
 *
 * - El A.2 pide de 6 a 8: se muestran a lo más las primeras ocho (landing-E20).
 * - Un par sin pregunta o sin respuesta no se muestra.
 * - Sin archivo, la sección muestra su marcador de contenido de prueba. Un archivo que falta o que no es una lista de
 *   JSON se reporta en la bitácora, y la portada sigue con el marcador en vez de caerse.
 */
final class PreguntasFrecuentes
{
    /** El máximo del A.2. */
    public const MAXIMO = 8;

    /**
     * @return list<array{pregunta: string, respuesta: string}>
     */
    public function todas(): array
    {
        $ruta = config('landing.preguntas_frecuentes');

        if (! is_string($ruta) || trim($ruta) === '') {
            return [];
        }

        try {
            $lista = $this->leer($this->absoluta(trim($ruta)));
        } catch (RuntimeException $error) {
            report($error);

            return [];
        }

        $preguntas = [];
        foreach ($lista as $par) {
            $pregunta = is_array($par) && is_string($par['pregunta'] ?? null) ? trim($par['pregunta']) : '';
            $respuesta = is_array($par) && is_string($par['respuesta'] ?? null) ? trim($par['respuesta']) : '';

            if ($pregunta !== '' && $respuesta !== '') {
                $preguntas[] = ['pregunta' => $pregunta, 'respuesta' => $respuesta];
            }
        }

        return array_slice($preguntas, 0, self::MAXIMO);
    }

    private function absoluta(string $ruta): string
    {
        $esAbsoluta = str_starts_with($ruta, '/') || str_starts_with($ruta, '\\') || preg_match('/^[A-Za-z]:[\\\\\/]/', $ruta) === 1;

        return $esAbsoluta ? $ruta : base_path($ruta);
    }

    /**
     * @return list<mixed>
     */
    private function leer(string $archivo): array
    {
        if (! is_file($archivo) || ! is_readable($archivo)) {
            throw new RuntimeException("No se puede leer el archivo de preguntas frecuentes: {$archivo}");
        }

        try {
            $contenido = json_decode((string) file_get_contents($archivo), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException("El archivo de preguntas frecuentes no es JSON válido: {$archivo}", 0, $error);
        }

        if (! is_array($contenido) || ! array_is_list($contenido)) {
            throw new RuntimeException("El archivo de preguntas frecuentes no es una lista: {$archivo}");
        }

        return $contenido;
    }
}
