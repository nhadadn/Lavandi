<?php

namespace Tests\Feature\Plazas;

use App\Models\Rol;
use App\Models\Usuario;
use App\Plazas\PuntoEnPlaza;
use App\Plazas\ResolucionDePunto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Resolver un punto: en qué zona y en qué plaza activa cae una latitud y una longitud, o que no cae en ninguna. Una
 * plaza que no está activa no da servicio, y la respuesta depende del punto, nunca de la cuenta de quien pregunta
 * (NSV-2026-001-v8.pdf:4:Cláusula I y NSV-2026-001-v8.pdf:38:ANEXO C, Sección C.3; ADR-0004).
 *
 * Las plazas y zonas son ficticias: cuadrados de un grado en el océano Pacífico.
 *
 * Escenarios: plazas-E04, plazas-E05, plazas-E06 y plazas-E07 · Spec: openspec/changes/plazas-y-zonas/specs/plazas/spec.md
 */
class ResolucionDePuntoTest extends TestCase
{
    use PlazasDePrueba;
    use RefreshDatabase;

    #[TestDox('plazas-E04: un punto dentro de una zona resuelve a esa zona y a esa plaza')]
    public function test_plazas_e04_un_punto_dentro_de_una_zona_resuelve_a_esa_zona_y_a_esa_plaza(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $zona = $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        // Otra zona de la misma plaza, al lado, para que la respuesta no sea la única zona que hay.
        $this->zonaDePrueba($plaza, 'Zona de prueba Oriente', $this->cuadrado(oeste: -119.0, sur: 10.0));

        $resultado = $this->resolver(latitud: 10.5, longitud: -119.5);

        $this->assertInstanceOf(PuntoEnPlaza::class, $resultado);
        $this->assertTrue($zona->is($resultado->zona));
        $this->assertTrue($plaza->is($resultado->plaza));
    }

    #[TestDox('plazas-E05: un punto fuera de todas las zonas no cae en ninguna plaza')]
    public function test_plazas_e05_un_punto_fuera_de_todas_las_zonas_no_cae_en_ninguna_plaza(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1');
        $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));

        // Al oriente y al norte de la zona.
        $this->assertNull($this->resolver(latitud: 10.5, longitud: -110.5));
        $this->assertNull($this->resolver(latitud: 15.5, longitud: -119.5));
    }

    #[TestDox('plazas-E06: una plaza que no está activa no da servicio')]
    public function test_plazas_e06_una_plaza_que_no_esta_activa_no_da_servicio(): void
    {
        $plaza = $this->plazaDePrueba('Plaza de prueba 1', activa: false);
        $this->zonaDePrueba($plaza, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));

        $this->assertNull($this->resolver(latitud: 10.5, longitud: -119.5));

        // La misma zona da servicio en cuanto su plaza se activa.
        $plaza->update(['activa' => true]);
        $this->assertTrue($plaza->is($this->resolver(latitud: 10.5, longitud: -119.5)?->plaza));
    }

    #[TestDox('plazas-E07: el usuario no pertenece a una plaza; cada punto resuelve a su plaza')]
    public function test_plazas_e07_el_usuario_no_pertenece_a_una_plaza_cada_punto_resuelve_a_su_plaza(): void
    {
        $usuario = Usuario::factory()->conRol(Rol::USUARIO)->create();

        $una = $this->plazaDePrueba('Plaza de prueba 1');
        $otra = $this->plazaDePrueba('Plaza de prueba 2');
        $this->zonaDePrueba($una, 'Zona de prueba Centro', $this->cuadrado(oeste: -120.0, sur: 10.0));
        $this->zonaDePrueba($otra, 'Zona de prueba Centro', $this->cuadrado(oeste: -110.0, sur: 20.0));

        // La respuesta es la misma con la sesión del usuario y sin ella: depende del punto, no de la cuenta.
        foreach ([null, $usuario] as $sesion) {
            if ($sesion !== null) {
                $this->actingAs($sesion);
            }

            $this->assertTrue($una->is($this->resolver(latitud: 10.5, longitud: -119.5)?->plaza));
            $this->assertTrue($otra->is($this->resolver(latitud: 20.5, longitud: -109.5)?->plaza));
        }

        // La cuenta no guarda ninguna plaza.
        $this->assertFalse(Schema::hasColumn('usuarios', 'id_plazas'));
        $this->assertArrayNotHasKey('id_plazas', $usuario->fresh()->getAttributes());
    }

    private function resolver(float $latitud, float $longitud): ?PuntoEnPlaza
    {
        return app(ResolucionDePunto::class)->resolver($latitud, $longitud);
    }
}
