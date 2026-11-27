<?php

namespace Tests\Feature\Autorizacion\Soporte;

use App\Exportacion\CreacionDeDescarga;
use App\Exportacion\Exportacion;
use App\Exportacion\Exportadores\PedidosTotalesExportador;
use App\Models\Usuario;
use Closure;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * El barrido contra fugas: una cuenta recorre todas las rutas de la API con su sesión, pidiendo en cada parámetro de la
 * ruta los datos que no le corresponden, y se busca en cada respuesta cada dato ajeno (autorizacion-E07, E11 y E14; SEG-3;
 * vault/arquitectura/c3-api.md, la prueba anti-fuga).
 *
 * Qué se espera de cada llamada (ADR-0019):
 *
 * - si la ruta no admite el rol de la cuenta, 403 con solo su mensaje (decisión 2);
 * - si la admite y uno de sus parámetros es un dato ajeno, 404 con solo su mensaje, como si no existiera (decisión 3);
 * - en cualquier caso, ningún dato ajeno en la respuesta.
 *
 * Cada parámetro de las rutas tiene que tener datos en el barrido, ajenos o públicos para esa cuenta: una ruta nueva con
 * un parámetro que el barrido no conoce hace fallar la prueba, que lo nombra, en lugar de pasarla sin revisar.
 *
 * Los datos ajenos se buscan sin distinguir mayúsculas, como texto en el JSON: van en ASCII, para que el JSON no los
 * escape.
 */
trait BarridoDeRutas
{
    use LlamadasSinEfecto;
    use TablaDeRutas;

    /**
     * Las fugas de una cuenta en todas las rutas de la API, como "ruta · rol · qué pidió: qué pasó"; ninguna si no hay.
     *
     * @param  array<string, array<string, string>>  $ajenos  Por parámetro de la ruta, los valores que no le
     *                                                        corresponden, cada uno con su etiqueta: ['pedido' => [id => 'pedido 2a']].
     * @param  array<string, string>  $publicos  Por parámetro, un valor que la cuenta sí puede pedir: ['codigo_postal' => '00000'].
     * @param  array<string, string>  $datosAjenos  Lo que no puede traer ninguna respuesta, con su etiqueta: [folio => 'folio del pedido 2a'].
     * @param  array<string, mixed>  $datos  El cuerpo de cada llamada, o la consulta de un GET.
     * @param  (Closure(Ruta): bool)|null  $soloLasRutas  Si viene, el barrido recorre solo las rutas de la API para las
     *                                                    que da true; si no, todas.
     * @return list<string>
     */
    protected function fugasDe(
        Usuario $cuenta,
        string $rol,
        array $ajenos,
        array $publicos,
        array $datosAjenos,
        array $datos = [],
        ?Closure $soloLasRutas = null,
    ): array {
        // Las respuestas se revisan como salen en producción, sin el detalle de depuración del entorno local.
        config(['app.debug' => false]);
        $fugas = [];
        $llamadas = 0;
        $rutas = $soloLasRutas === null ? $this->rutasDeLaApi() : array_filter($this->rutasDeLaApi(), $soloLasRutas);

        foreach ($rutas as $nombre => $ruta) {
            $roles = $this->rolesDe($ruta);
            $admite = $roles === [] || in_array($rol, $roles, true);

            foreach ($this->pedidosDeLaRuta($ruta, $ajenos, $publicos) as [$valores, $pide, $esAjeno]) {
                if ($valores === null) {
                    $fugas[] = "{$nombre} · {$rol} · {$pide}: el barrido no tiene datos para ese parámetro";

                    continue;
                }

                $respuesta = $this->llamarSinEfecto($cuenta, $this->metodoDe($nombre), $this->urlDe($ruta, $valores), $datos);
                $llamadas++;
                $donde = "{$nombre} · {$rol} · {$pide}";

                if (! $admite) {
                    $this->esperar($fugas, $donde, $respuesta, 403);
                } elseif ($esAjeno) {
                    $this->esperar($fugas, $donde, $respuesta, 404);
                }

                foreach ($datosAjenos as $dato => $etiqueta) {
                    if (stripos($respuesta->getContent(), $dato) !== false) {
                        $fugas[] = "{$donde}: trae {$etiqueta}";
                    }
                }
            }
        }

        // La cuenta sí llamó a las rutas: el barrido no pasa en vacío.
        $this->assertGreaterThan(0, $llamadas);

        return $fugas;
    }

    /**
     * La descarga de un reporte, lista, que pidió esa cuenta de administración: el dato de {descarga} en el barrido, que
     * solo baja la cuenta que la pidió (exportacion-E11; administracion-app-E62). La crea la misma clase que el botón
     * del panel y la API (App\Exportacion\CreacionDeDescarga), con su archivo en un disco de prueba.
     */
    protected function descargaDeLaAdministracion(Usuario $administracion): Exportacion
    {
        Storage::fake((string) config('exportacion.disco'));

        return app(CreacionDeDescarga::class)->crear(
            $administracion,
            PedidosTotalesExportador::class,
            ['desde' => '2026-12-03', 'hasta' => '2026-12-03', 'granularidad' => 'dia'],
        );
    }

    /**
     * Las llamadas que se hacen a la ruta: una por cada valor ajeno de cada parámetro, con los demás parámetros en un
     * valor público, o una sola si no tiene parámetros. Cada una, con [los valores, qué pide, si pide un dato ajeno];
     * los valores vienen en null si un parámetro no tiene datos en el barrido.
     *
     * @param  array<string, array<string, string>>  $ajenos
     * @param  array<string, string>  $publicos
     * @return list<array{0: ?array<string, string>, 1: string, 2: bool}>
     */
    private function pedidosDeLaRuta(Ruta $ruta, array $ajenos, array $publicos): array
    {
        $parametros = $this->parametrosDe($ruta);

        foreach ($parametros as $parametro) {
            if (! array_key_exists($parametro, $ajenos) && ! array_key_exists($parametro, $publicos)) {
                return [[null, "{{$parametro}}", false]];
            }
        }

        $base = [];
        foreach ($parametros as $parametro) {
            $base[$parametro] = $publicos[$parametro] ?? (string) array_key_first($ajenos[$parametro]);
        }

        $pedidos = [];
        foreach ($parametros as $parametro) {
            foreach ($ajenos[$parametro] ?? [] as $valor => $etiqueta) {
                $pedidos[] = [[...$base, $parametro => (string) $valor], "pide {$etiqueta}", true];
            }

            if (! array_key_exists($parametro, $ajenos)) {
                $pedidos[] = [$base, "pide {{$parametro}} público", false];
            }
        }

        return $pedidos === [] ? [[[], 'sin parámetros', false]] : $pedidos;
    }

    /**
     * La respuesta tiene que ser ese estado, con solo su mensaje y ningún otro dato.
     *
     * @param  list<string>  $fugas
     */
    private function esperar(array &$fugas, string $donde, TestResponse $respuesta, int $estado): void
    {
        $cuerpo = $respuesta->json();

        if ($respuesta->status() !== $estado || ! is_array($cuerpo) || array_keys($cuerpo) !== ['message']) {
            $fugas[] = "{$donde}: responde {$respuesta->status()} {$respuesta->getContent()}; se esperaba {$estado} sin datos";
        }
    }
}
