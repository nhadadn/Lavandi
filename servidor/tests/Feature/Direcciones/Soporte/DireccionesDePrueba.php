<?php

namespace Tests\Feature\Direcciones\Soporte;

use App\Direcciones\Geocodificador;
use App\Direcciones\Punto;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Lo que comparten las pruebas de direcciones: el servicio de geocodificación simulado, las cuentas de usuario con su
 * sesión y la lectura del punto guardado.
 *
 * Las plazas y las zonas son las ficticias de Tests\Feature\Plazas\Soporte\PlazasDePrueba, en el océano Pacífico. El
 * punto de omisión del servicio simulado, 10.5 de latitud y -119.5 de longitud, cae en el cuadrado de un grado con su
 * esquina suroeste en (-120, 10).
 */
trait DireccionesDePrueba
{
    /**
     * Pone el servicio simulado en lugar del real y lo devuelve, para contar sus consultas.
     */
    protected function simularGeocodificacion(?Punto $respuesta = new Punto(10.5, -119.5)): GeocodificadorSimulado
    {
        $simulado = new GeocodificadorSimulado($respuesta);
        $this->app->instance(Geocodificador::class, $simulado);

        return $simulado;
    }

    protected function cuentaDeUsuario(): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create();
    }

    /**
     * Las peticiones que siguen van con la sesión de la cuenta. Olvida la cuenta de la petición anterior
     * (forgetGuards), como dos peticiones reales al servidor.
     */
    protected function comoCuenta(Usuario $cuenta): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken);
    }

    /**
     * Una dirección escrita completa, con lo que se pase encima. Su municipio es el ficticio del catálogo de prueba,
     * "Municipio de prueba" del "Estado de prueba", que se crea si no existe (CatalogoDePrueba).
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function direccionEscrita(array $datos = []): array
    {
        return [
            'origen' => 'escrita',
            'calle' => 'Calle de prueba',
            'numero_exterior' => '123',
            'colonia' => 'Colonia de prueba',
            'municipio' => CatalogoDePrueba::municipio()->codigo,
            ...$datos,
        ];
    }

    /**
     * El punto guardado de la dirección, leído de la base con PostGIS.
     *
     * @return array{latitud: float, longitud: float}
     */
    protected function puntoGuardado(string $idPublico): array
    {
        return array_map('floatval', (array) DB::selectOne(
            'select ST_Y(ubicacion::geometry) as latitud, ST_X(ubicacion::geometry) as longitud
               from direcciones where id_publico = ?',
            [$idPublico],
        ));
    }
}
