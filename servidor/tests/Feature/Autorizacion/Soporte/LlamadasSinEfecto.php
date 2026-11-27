<?php

namespace Tests\Feature\Autorizacion\Soporte;

use App\Identidad\TokensDeLaApp;
use App\Models\Lavanderia;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Las llamadas de las pruebas que recorren todas las rutas con la sesión de cada rol (autorizacion-E02 y E14). Cada una
 * corre dentro de su propio punto de guardado de la base y se deshace al terminar: lo que cambie una ruta, como borrar la
 * cuenta o cerrar la sesión, no alcanza a la siguiente. El token de la sesión nace dentro del mismo punto, y es el real:
 * lo crea App\Identidad\TokensDeLaApp, así que la cuenta de administración llega con su habilidad (ADR-0022, decisión 3).
 *
 * Cada petición lleva un cuerpo que pasa la validación de las acciones de los módulos B y C, para que la respuesta salga
 * de la autorización y no de la validación: un motivo para rechazar y un botón para el estado.
 */
trait LlamadasSinEfecto
{
    /** El cuerpo de toda petición que no es GET, con lo que se pase encima. */
    private const CUERPO_DE_LA_LLAMADA = ['motivo' => 'Motivo de prueba.', 'estado' => 'recolectado'];

    /** Lo único que trae el 403 de un rol que no corresponde (ADR-0019, decisión 2). */
    private const SIN_ACCESO = ['message' => 'Esta cuenta no tiene acceso.'];

    /**
     * La cuenta llama a la ruta con su sesión de la app. Lo que la llamada cambie se deshace.
     *
     * @param  array<string, mixed>  $datos  El cuerpo, o la consulta de un GET, que se suma al de la llamada.
     */
    protected function llamarSinEfecto(Usuario $cuenta, string $metodo, string $url, array $datos = []): TestResponse
    {
        $this->app['auth']->forgetGuards();
        DB::beginTransaction();

        try {
            $token = app(TokensDeLaApp::class)->crear($cuenta)->plainTextToken;

            return $metodo === 'GET'
                ? $this->withToken($token)->getJson($datos === [] ? $url : $url.'?'.http_build_query($datos))
                : $this->withToken($token)->json($metodo, $url, [...self::CUERPO_DE_LA_LLAMADA, ...$datos]);
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    /**
     * Una cuenta de ese rol que opera en su módulo: la de usuario; la que administra una lavandería activa; la de un
     * repartidor activo con su plaza; o la de administración.
     */
    protected function cuentaQueOpera(string $rol): Usuario
    {
        return match ($rol) {
            Rol::LAVANDERIA => Lavanderia::factory()->create()->administrador,
            Rol::REPARTIDOR => Repartidor::factory()->create()->cuenta,
            default => Usuario::factory()->conRol($rol)->create(),
        };
    }
}
