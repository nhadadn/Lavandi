<?php

namespace Tests\Feature\Autorizacion\Soporte;

use App\Autorizacion\Autorizacion;
use App\Http\Middleware\Autorizar;
use Filament\Http\Middleware\Authenticate as PuertaDelPanel;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * La tabla de rutas de servidor/, leída del enrutador y no de una lista escrita a mano: una ruta nueva entra sola a las
 * pruebas de autorizacion-por-rol que la recorren (autorizacion-E01, E02, E03 y E14) y a la de registro-E14.
 *
 * Cada ruta se nombra "MÉTODO uri", sin HEAD, que es el GET sin cuerpo. Su regla es una de tres:
 *
 * - **componente:** una ruta de la API con la sesión de Sanctum y el middleware autorizar:<rol> (registro-E14);
 * - **panel:** una ruta del panel de Filament detrás de su puerta, que pregunta al componente con
 *   Usuario::canAccessPanel() (registro-E15 y autorizacion-E03);
 * - **sin sesión:** una ruta que el componente declara en Autorizacion::rutasSinSesion(), con su razón.
 *
 * Una ruta sin ninguna de las tres no tiene regla.
 */
trait TablaDeRutas
{
    /**
     * Todas las rutas registradas, por "MÉTODO uri", sin HEAD.
     *
     * @return array<string, Ruta>
     */
    protected function rutasRegistradas(): array
    {
        $rutas = [];

        foreach (Route::getRoutes()->getRoutes() as $ruta) {
            foreach (array_diff($ruta->methods(), ['HEAD']) as $metodo) {
                $rutas["{$metodo} {$ruta->uri()}"] = $ruta;
            }
        }

        ksort($rutas);

        return $rutas;
    }

    /**
     * Las rutas de la API, por "MÉTODO uri", sin HEAD.
     *
     * @return array<string, Ruta>
     */
    protected function rutasDeLaApi(): array
    {
        return array_filter(
            $this->rutasRegistradas(),
            fn (Ruta $ruta): bool => Str::startsWith($ruta->uri(), 'api/'),
        );
    }

    /**
     * Las rutas de la API que pasan por el componente con autorizar:<rol>, con los roles que admite cada una.
     *
     * @return array<string, array{ruta: Ruta, roles: list<string>}>
     */
    protected function rutasDeLaApiConRol(): array
    {
        $conRol = [];

        foreach ($this->rutasDeLaApi() as $nombre => $ruta) {
            $roles = $this->rolesDe($ruta);

            if ($roles !== []) {
                $conRol[$nombre] = ['ruta' => $ruta, 'roles' => $roles];
            }
        }

        return $conRol;
    }

    /**
     * Las rutas que no tienen regla, por "MÉTODO uri".
     *
     * @return list<string>
     */
    protected function rutasSinRegla(): array
    {
        $sinSesion = app(Autorizacion::class)->rutasSinSesion();
        $sinRegla = [];

        foreach ($this->rutasRegistradas() as $nombre => $ruta) {
            if (! array_key_exists($nombre, $sinSesion) && $this->reglaDe($ruta) === null) {
                $sinRegla[] = $nombre;
            }
        }

        return $sinRegla;
    }

    /**
     * La regla que lleva la ruta en su middleware: componente o panel. Ninguna, si no lleva ninguna de las dos; que la
     * declare sin sesión lo dice Autorizacion::rutasSinSesion().
     */
    protected function reglaDe(Ruta $ruta): ?string
    {
        $middleware = $this->middlewareDe($ruta);

        if (Str::startsWith($ruta->uri(), 'api/')) {
            $conSesion = in_array(Authenticate::class.':sanctum', $middleware, true);

            return $conSesion && $this->rolesDe($ruta) !== [] ? 'componente' : null;
        }

        return in_array(PuertaDelPanel::class, $middleware, true) ? 'panel' : null;
    }

    /**
     * Los roles que admite la ruta en autorizar:<rol>[,<rol>...]; ninguno si no pasa por el componente.
     *
     * @return list<string>
     */
    protected function rolesDe(Ruta $ruta): array
    {
        foreach ($this->middlewareDe($ruta) as $middleware) {
            if (Str::startsWith($middleware, Autorizar::class.':')) {
                return explode(',', Str::after($middleware, Autorizar::class.':'));
            }
        }

        return [];
    }

    /**
     * El middleware de la ruta, con los alias y los grupos resueltos a sus clases.
     *
     * @return list<string>
     */
    protected function middlewareDe(Ruta $ruta): array
    {
        return array_values(array_filter(
            app('router')->gatherRouteMiddleware($ruta),
            fn (mixed $middleware): bool => is_string($middleware),
        ));
    }

    /**
     * Los parámetros de la uri de la ruta, como {pedido} o {lavanderia}, sin llaves.
     *
     * @return list<string>
     */
    protected function parametrosDe(Ruta $ruta): array
    {
        preg_match_all('/\{(\w+)\??\}/', $ruta->uri(), $coincidencias);

        return $coincidencias[1];
    }

    protected function metodoDe(string $nombre): string
    {
        return Str::before($nombre, ' ');
    }

    /**
     * La dirección (url) de la ruta con el valor de cada parámetro; el que no venga, con un ULID cualquiera.
     *
     * @param  array<string, string>  $valores
     */
    protected function urlDe(Ruta $ruta, array $valores = []): string
    {
        return '/'.preg_replace_callback(
            '/\{(\w+)\??\}/',
            fn (array $parametro): string => $valores[$parametro[1]] ?? (string) Str::ulid(),
            $ruta->uri(),
        );
    }
}
