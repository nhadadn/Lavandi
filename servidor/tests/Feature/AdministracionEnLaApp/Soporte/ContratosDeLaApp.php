<?php

namespace Tests\Feature\AdministracionEnLaApp\Soporte;

use Illuminate\Testing\TestResponse;
use stdClass;

/**
 * Los contratos de la API de la administración con la app (openspec/changes/administracion-en-la-app/design.md, sección
 * 6, "Los contratos con la app"; administracion-app-E11).
 *
 * Cada contrato es un archivo de tests/Contratos/administracion/: la respuesta real de una ruta en un estado (con datos,
 * vacía, con un indicador en espera, un 422, un 409 o un 404), con la petición y el código, como la da el servidor para
 * los datos que siembra la prueba. La app pasa cada archivo por sus funciones leer… y compara con él las respuestas de su
 * servidor simulado (movil/__tests__/administracion/contratos.test.ts). Así una diferencia entre lo que manda el servidor
 * y lo que la app exige falla en una de las dos pruebas, y no en el teléfono.
 *
 * Aquí se compara la forma de la respuesta con la del archivo: cada llave, en cada nivel, con el tipo de su valor (texto,
 * número, booleano, nulo, objeto o lista; en una lista, los de todos sus elementos). Los valores no cuentan, porque
 * cambian con cada siembra. Si la forma cambia, la prueba falla y nombra las llaves que cambiaron. Para aceptar la forma
 * nueva se corre con CONTRATOS_ESCRIBIR=1, que reescribe el archivo cuya forma cambió y crea el que falta, y entonces la
 * prueba de la app dice si la app la sigue leyendo.
 *
 * Las direcciones de las fotos firmadas se escriben sin el servidor de la prueba: el archivo no lleva el dominio de ningún
 * entorno, y la forma es la misma.
 */
trait ContratosDeLaApp
{
    /** La dirección con que el archivo escribe cada URL de la respuesta. */
    private const DIRECCION_DEL_CONTRATO = 'https://archivos.prueba.invalid/archivo?firma=prueba';

    /**
     * Que la respuesta tiene el código y la forma de su contrato. $peticion es la ruta con sus parámetros por nombre, como
     * "GET /api/administracion/repartidores/{repartidor}".
     */
    protected function assertCumpleSuContrato(string $nombre, string $peticion, TestResponse $respuesta, int $estado = 200): void
    {
        $this->assertSame($estado, $respuesta->status(), "{$peticion}: {$respuesta->getContent()}");

        $cuerpo = json_decode((string) $respuesta->getContent(), false, 512, JSON_THROW_ON_ERROR);
        $archivo = base_path("tests/Contratos/administracion/{$nombre}.json");

        if (getenv('CONTRATOS_ESCRIBIR') === '1' && ! $this->contratoConLaMismaForma($archivo, $peticion, $estado, $cuerpo)) {
            $this->escribirElContrato($archivo, $peticion, $estado, $cuerpo);
        }

        $this->assertFileExists($archivo, "Falta el contrato {$nombre}.json: se escribe con CONTRATOS_ESCRIBIR=1.");
        $contrato = json_decode((string) file_get_contents($archivo), false, 512, JSON_THROW_ON_ERROR);

        $this->assertSame([$peticion, $estado], [$contrato->peticion, $contrato->estado], "El contrato {$nombre}.json es de otra petición.");
        $this->assertSame(
            self::formaDelJson($contrato->cuerpo),
            self::formaDelJson($cuerpo),
            "{$peticion} ya no responde con la forma de {$nombre}.json. Si el cambio es del contrato (design.md, sección 4), "
            .'se reescribe con CONTRATOS_ESCRIBIR=1 y se corre la prueba de la app.',
        );
    }

    /**
     * La forma de un valor de JSON, leído con objetos (json_decode sin arreglos asociativos): un renglón por llave, en
     * orden, con su ruta desde la raíz ($) y los tipos de su valor. Es la misma que arma la app
     * (movil/__tests__/apoyo/contratos.ts).
     *
     * @return list<string>
     */
    protected static function formaDelJson(mixed $valor): array
    {
        $tipos = [];
        self::recorrer($valor, '$', $tipos);
        ksort($tipos, SORT_STRING);

        $renglones = [];

        foreach ($tipos as $ruta => $deLaRuta) {
            $deLaRuta = array_keys($deLaRuta);
            sort($deLaRuta, SORT_STRING);
            $renglones[] = "{$ruta}: ".implode('|', $deLaRuta);
        }

        return $renglones;
    }

    /**
     * @param  array<string, array<string, true>>  $tipos
     */
    private static function recorrer(mixed $valor, string $ruta, array &$tipos): void
    {
        $tipo = match (true) {
            $valor === null => 'nulo',
            is_bool($valor) => 'booleano',
            is_int($valor), is_float($valor) => 'numero',
            is_string($valor) => 'texto',
            is_array($valor) => 'lista',
            default => 'objeto',
        };
        $tipos[$ruta][$tipo] = true;

        if ($valor instanceof stdClass) {
            foreach (get_object_vars($valor) as $llave => $deLaLlave) {
                self::recorrer($deLaLlave, "{$ruta}.{$llave}", $tipos);
            }
        }

        if (is_array($valor)) {
            foreach ($valor as $elemento) {
                self::recorrer($elemento, "{$ruta}[]", $tipos);
            }
        }
    }

    private function contratoConLaMismaForma(string $archivo, string $peticion, int $estado, mixed $cuerpo): bool
    {
        if (! is_file($archivo)) {
            return false;
        }

        $contrato = json_decode((string) file_get_contents($archivo), false, 512, JSON_THROW_ON_ERROR);

        return $contrato->peticion === $peticion
            && $contrato->estado === $estado
            && self::formaDelJson($contrato->cuerpo) === self::formaDelJson($cuerpo);
    }

    private function escribirElContrato(string $archivo, string $peticion, int $estado, mixed $cuerpo): void
    {
        if (! is_dir(dirname($archivo))) {
            mkdir(dirname($archivo), 0777, true);
        }

        $contrato = ['peticion' => $peticion, 'estado' => $estado, 'cuerpo' => self::sinDirecciones($cuerpo)];

        file_put_contents($archivo, json_encode(
            $contrato,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
        )."\n");
    }

    /**
     * El valor con cada URL cambiada por la del contrato: la de una foto firmada lleva el servidor de la prueba.
     */
    private static function sinDirecciones(mixed $valor): mixed
    {
        if (is_string($valor)) {
            return preg_match('#^https?://#', $valor) === 1 ? self::DIRECCION_DEL_CONTRATO : $valor;
        }

        if (is_array($valor)) {
            return array_map(self::sinDirecciones(...), $valor);
        }

        if ($valor instanceof stdClass) {
            $limpio = new stdClass;

            foreach (get_object_vars($valor) as $llave => $deLaLlave) {
                $limpio->{$llave} = self::sinDirecciones($deLaLlave);
            }

            return $limpio;
        }

        return $valor;
    }
}
