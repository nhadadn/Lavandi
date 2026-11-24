<?php

namespace Tests\Feature\Autorizacion\Soporte;

use App\Models\Usuario;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Las rutas del Módulo B, las de la lavandería, recorridas desde la tabla de rutas: una ruta nueva bajo api/lavanderia
 * entra sola a las pruebas de autorizacion-por-rol que la usan (autorizacion-E09, E10 y E12).
 *
 * Cada petición lleva un cuerpo válido para cualquier acción, para que la respuesta salga de la autorización y no de la
 * validación: un motivo para rechazar y un botón para el estado.
 */
trait RutasDelModuloB
{
    /** Un cuerpo que pasa la validación de cualquier acción del Módulo B. */
    private const CUERPO_VALIDO = ['motivo' => 'Motivo de prueba.', 'estado' => 'recolectado'];

    /**
     * Las rutas de la API de la lavandería que reciben un pedido por su id_publico, por "MÉTODO uri", sin HEAD.
     *
     * @return array<string, Ruta>
     */
    protected function rutasDeLaLavanderiaConPedido(): array
    {
        $rutas = [];

        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            if (! Str::startsWith($ruta->uri(), 'api/lavanderia/') || ! str_contains($ruta->uri(), '{pedido}')) {
                continue;
            }

            foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                $rutas["{$metodo} {$ruta->uri()}"] = $ruta;
            }
        }

        ksort($rutas);

        return $rutas;
    }

    /**
     * La cuenta llama a la ruta con ese id_publico en el lugar del pedido.
     */
    protected function llamarConElPedido(Usuario $cuenta, string $nombre, Ruta $ruta, string $pedido): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->json(
            Str::before($nombre, ' '),
            '/'.str_replace('{pedido}', $pedido, $ruta->uri()),
            self::CUERPO_VALIDO,
        );
    }
}
