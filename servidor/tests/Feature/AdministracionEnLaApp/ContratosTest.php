<?php

namespace Tests\Feature\AdministracionEnLaApp;

use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Autorizacion\Soporte\TablaDeRutas;
use Tests\TestCase;

/**
 * Cada ruta de /api/administracion tiene su contrato con la app (openspec/changes/administracion-en-la-app/design.md,
 * sección 6, "Los contratos con la app"; administracion-app-E11).
 *
 * Los contratos son los archivos de tests/Contratos/administracion/, una respuesta real por ruta y por estado, que
 * escriben y vigilan las pruebas de cada función con Soporte\ContratosDeLaApp, y que la app lee con sus funciones leer…
 * (movil/__tests__/administracion/contratos.test.ts). Esta prueba lee la tabla de rutas del enrutador, no una lista
 * escrita a mano: una ruta nueva de la administración sin su contrato falla aquí, y un contrato de una ruta que ya no
 * existe también.
 *
 * Escenario: administracion-app-E11 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md
 */
class ContratosTest extends TestCase
{
    use TablaDeRutas;

    #[TestDox('administracion-app-E11: cada ruta de /api/administracion tiene al menos un contrato con la app, y cada contrato es de una ruta que existe')]
    public function test_administracion_app_e11_cada_ruta_de_la_administracion_tiene_su_contrato(): void
    {
        $rutas = [];

        foreach (array_keys($this->rutasDeLaApi()) as $nombre) {
            [$metodo, $uri] = explode(' ', $nombre, 2);

            if (str_starts_with($uri, 'api/administracion/')) {
                $rutas[] = "{$metodo} /{$uri}";
            }
        }

        $contratos = [];

        foreach (glob(base_path('tests/Contratos/administracion/*.json')) ?: [] as $archivo) {
            $contratos[] = json_decode((string) file_get_contents($archivo), false, 512, JSON_THROW_ON_ERROR)->peticion;
        }

        sort($rutas);
        $contratos = array_values(array_unique($contratos));
        sort($contratos);

        $this->assertNotEmpty($rutas);
        $this->assertSame($rutas, $contratos);
    }
}
