<?php

namespace Tests\Feature\AdministracionEnLaApp\Soporte;

use App\Identidad\TokensDeLaApp;
use App\Models\Lavanderia;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/**
 * Las sesiones de las pruebas de la administración en la app (openspec/changes/administracion-en-la-app/, design.md,
 * sección 6). Cada token es real: lo crea App\Identidad\TokensDeLaApp, la misma pieza que da los tokens de la app, y va
 * con withToken(). Nunca Sanctum::actingAs(), que simula can() y no la lista de habilidades (ADR-0022, decisión 8).
 *
 * Da la cuenta de administración con su token; un token de esa cuenta sin la habilidad, como los "*" que salieron por
 * Google antes de ADR-0022; uno de administración vencido; las cuentas de usuario, de lavandería que opera y de
 * repartidor que opera, con su token; y la aserción de que una ruta solo la abre la administración
 * (administracion-app-E06 y E07).
 *
 * Cada petición olvida la cuenta de la anterior, como dos peticiones reales al servidor.
 */
trait SesionesDeLaApp
{
    /** Lo único que trae el 403 de una cuenta que no es de administración, o de un token sin la habilidad (ADR-0019, decisión 2). */
    protected const RESPUESTA_SIN_ACCESO = ['message' => 'Esta cuenta no tiene acceso.'];

    /** Los roles de los Módulos A, B y C, que no entran a la administración (administracion-app-E07). */
    protected const ROLES_QUE_NO_SON_DE_ADMINISTRACION = [Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR];

    /**
     * Una cuenta de administración, como la que crea el comando (administracion-E26): con la contraseña de la fábrica y
     * solo ese rol.
     *
     * @param  array<string, mixed>  $atributos
     */
    protected function cuentaDeAdministracion(array $atributos = []): Usuario
    {
        return Usuario::factory()->conRol(Rol::ADMINISTRACION)->create($atributos);
    }

    /**
     * El token de la app de la cuenta, como el que recibe al iniciar sesión: el de administración, si la cuenta es de
     * administración (administracion-app-E01). Como al iniciar sesión, el de administración borra los tokens de la
     * cuenta que no llevan la habilidad (administracion-app-E06): el token sin la habilidad se pide después.
     */
    protected function tokenDeLaApp(Usuario $cuenta): string
    {
        return app(TokensDeLaApp::class)->crear($cuenta)->plainTextToken;
    }

    /**
     * Un token de la cuenta sin la habilidad de administración: con "*", como los que salieron por Google antes de
     * ADR-0022 (administracion-app-E06).
     */
    protected function tokenSinLaHabilidad(Usuario $cuenta): string
    {
        return $cuenta->createToken(TokensDeLaApp::NOMBRE, ['*'])->plainTextToken;
    }

    /**
     * Un token de administración que ya venció: el de la app, con su vencimiento un segundo antes de ahora
     * (administracion-app-E05). No mueve el reloj de la prueba.
     */
    protected function tokenVencido(Usuario $cuenta): string
    {
        $nuevo = app(TokensDeLaApp::class)->crear($cuenta);
        $nuevo->accessToken->forceFill(['expires_at' => now()->subSecond()])->save();

        return $nuevo->plainTextToken;
    }

    /**
     * Una cuenta de ese rol que opera en su módulo: la de usuario; la que administra una lavandería activa; o la de un
     * repartidor activo con su plaza.
     */
    protected function cuentaQueOperaComo(string $rol): Usuario
    {
        return match ($rol) {
            Rol::LAVANDERIA => Lavanderia::factory()->create()->administrador,
            Rol::REPARTIDOR => Repartidor::factory()->create()->cuenta,
            default => Usuario::factory()->conRol($rol)->create(),
        };
    }

    /**
     * La llamada a la API con ese token, o sin ninguno. La respuesta sale como en producción, sin el detalle de
     * depuración del entorno local.
     *
     * @param  array<string, mixed>  $cuerpo  El cuerpo, o la consulta de un GET.
     */
    protected function llamarConToken(?string $token, string $metodo, string $url, array $cuerpo = []): TestResponse
    {
        config(['app.debug' => false]);
        $this->app['auth']->forgetGuards();
        $this->withoutToken();

        if ($token !== null) {
            $this->withToken($token);
        }

        return $metodo === 'GET'
            ? $this->getJson($cuerpo === [] ? $url : $url.'?'.http_build_query($cuerpo))
            : $this->json($metodo, $url, $cuerpo);
    }

    /**
     * Que la ruta solo la abre la administración con su token: responde 403 con solo su mensaje a las cuentas de
     * usuario, de lavandería que opera y de repartidor que opera, y a un token de administración sin la habilidad
     * (administracion-app-E06 y E07); 401 sin token y con un token de administración vencido (administracion-app-E05); y
     * ninguna de esas llamadas cambia la base.
     *
     * @param  array<string, mixed>  $cuerpo
     */
    protected function assertSoloEntraLaAdministracion(string $metodo, string $url, array $cuerpo = []): void
    {
        $administracion = $this->cuentaDeAdministracion();
        // Primero el vencido: crearlo borra los tokens de la cuenta que no llevan la habilidad.
        $vencido = $this->tokenVencido($administracion);
        $llamadas = ['un token sin la habilidad' => $this->tokenSinLaHabilidad($administracion)];

        foreach (self::ROLES_QUE_NO_SON_DE_ADMINISTRACION as $rol) {
            $llamadas["la cuenta de {$rol}"] = $this->tokenDeLaApp($this->cuentaQueOperaComo($rol));
        }

        $antes = $this->fotoDeLaBase();
        $donde = "{$metodo} {$url}";

        foreach ($llamadas as $quien => $token) {
            $respuesta = $this->llamarConToken($token, $metodo, $url, $cuerpo);

            $this->assertSame(403, $respuesta->status(), "{$donde} · {$quien}: {$respuesta->getContent()}");
            $this->assertSame(self::RESPUESTA_SIN_ACCESO, $respuesta->json(), "{$donde} · {$quien}");
        }

        foreach (['sin token' => null, 'un token vencido' => $vencido] as $quien => $token) {
            $respuesta = $this->llamarConToken($token, $metodo, $url, $cuerpo);

            $this->assertSame(401, $respuesta->status(), "{$donde} · {$quien}: {$respuesta->getContent()}");
            $this->assertSame(['message'], array_keys((array) $respuesta->json()), "{$donde} · {$quien}");
        }

        $this->assertSame($antes, $this->fotoDeLaBase(), "{$donde}: una llamada rechazada cambió la base.");
    }

    /**
     * La huella de cada tabla de la base, para saber si una llamada cambió algo. De los tokens no cuentan la hora de su
     * último uso ni la de su cambio, que Sanctum escribe en cada petición con sesión, también en las que rechaza. Sin la
     * tabla de referencias espaciales de PostGIS, que nadie escribe.
     *
     * @return array<string, string>
     */
    protected function fotoDeLaBase(): array
    {
        $tablas = DB::select(
            "select table_name from information_schema.tables where table_schema = current_schema() and table_type = 'BASE TABLE' and table_name <> 'spatial_ref_sys' order by table_name",
        );

        $foto = [];

        foreach ($tablas as $tabla) {
            $filas = $tabla->table_name === 'personal_access_tokens'
                ? 'select id, tokenable_type, tokenable_id, name, token, abilities, expires_at, created_at from personal_access_tokens'
                : sprintf('select * from "%s"', $tabla->table_name);

            $foto[$tabla->table_name] = DB::selectOne(
                "select md5(coalesce(string_agg(fila::text, '|' order by fila::text), '')) as huella from ({$filas}) as fila",
            )->huella;
        }

        return $foto;
    }
}
