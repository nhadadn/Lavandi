<?php

namespace Tests\Feature\PerfilLavanderia\Soporte;

use App\Models\Lavanderia;
use App\Models\LavanderiaServicio;
use App\Models\Rol;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas del perfil y de las cercanas: servicios y calificaciones de prueba para una lavandería,
 * y las peticiones de la app con la sesión de una cuenta de usuario.
 *
 * Cada petición olvida la cuenta de la anterior (forgetGuards), como dos peticiones reales al servidor.
 */
trait LavanderiasDePrueba
{
    /**
     * Un servicio de la lavandería, por kilo, con el precio de prueba que se pasa.
     */
    protected function servicioDePrueba(
        Lavanderia $lavanderia,
        string $tipo,
        string $precio,
        bool $activo = true,
    ): LavanderiaServicio {
        return $lavanderia->servicios()->create([
            'id_tipos_servicio' => TipoServicio::where('codigo', $tipo)->sole()->getKey(),
            'id_unidades_medida' => UnidadMedida::where('codigo', UnidadMedida::KILO)->sole()->getKey(),
            'precio' => $precio,
            'activo' => $activo,
        ]);
    }

    /**
     * Una calificación de la lavandería por cada número de estrellas, cada una de una cuenta de usuario distinta.
     *
     * @param  list<int>  $estrellas
     */
    protected function calificar(Lavanderia $lavanderia, array $estrellas, ?string $comentario = null): void
    {
        foreach ($estrellas as $numero) {
            $lavanderia->calificaciones()->create([
                'id_usuarios' => Usuario::factory()->conRol(Rol::USUARIO)->create()->getKey(),
                'estrellas' => $numero,
                'comentario' => $comentario,
            ]);
        }
    }

    /**
     * Una petición GET de la app con la sesión de la cuenta; sin cuenta, una de usuario nueva.
     *
     * @param  array<string, mixed>  $parametros
     */
    protected function pedirComoUsuario(string $direccion, array $parametros = [], ?Usuario $cuenta = null): TestResponse
    {
        $cuenta ??= Usuario::factory()->conRol(Rol::USUARIO)->create();
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)
            ->getJson($direccion.($parametros === [] ? '' : '?'.http_build_query($parametros)));
    }

    /**
     * La misma petición, sin sesión.
     *
     * @param  array<string, mixed>  $parametros
     */
    protected function pedirSinSesion(string $direccion, array $parametros = []): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withoutToken()
            ->getJson($direccion.($parametros === [] ? '' : '?'.http_build_query($parametros)));
    }
}
