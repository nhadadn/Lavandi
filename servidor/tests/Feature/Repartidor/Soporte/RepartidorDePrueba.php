<?php

namespace Tests\Feature\Repartidor\Soporte;

use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Lo que comparten las pruebas del Módulo C: el registro del repartidor desde la app, con sus datos de prueba, los
 * repartidores activos, la oferta de un pedido con su fábrica y las peticiones con la sesión de su cuenta
 * (openspec/changes/repartidor/design.md, "Contrato de la API"). Lo de los pedidos se usa junto con AgendaDePrueba,
 * EstadosDePrueba, LavanderiasDePrueba, PedidosDeLaLavanderiaDePrueba, PedidosDePrueba y PlazasDePrueba, después de
 * prepararPedido().
 *
 * Todo es ficticio: los nombres, los teléfonos y los correos son de prueba. Cada petición olvida la cuenta de la
 * anterior (forgetGuards), como dos peticiones reales al servidor.
 */
trait RepartidorDePrueba
{
    /**
     * Lo que manda la app al registrarse como repartidor: los cinco datos y la contraseña, con lo que se pase encima
     * (repartidor-E01). Un dato con null no se manda.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function datosDelRegistro(array $datos = []): array
    {
        return array_filter([
            'nombre' => 'Repartidor de prueba',
            'telefono' => '618 123 4567',
            'correo' => 'repartidor.prueba@ejemplo.com',
            'password' => 'contrasena-de-prueba',
            'foto' => $this->fotoDePrueba(),
            'tipo_vehiculo' => 'Motocicleta',
            ...$datos,
        ], fn (mixed $valor): bool => $valor !== null);
    }

    /**
     * La app manda el registro, sin sesión, en multipart.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function registrarRepartidor(array $datos): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/repartidor/registro', $datos);
    }

    /**
     * Las peticiones que siguen van con la sesión de la cuenta del repartidor.
     */
    protected function comoRepartidor(Repartidor|Usuario $repartidor): static
    {
        $cuenta = $repartidor instanceof Repartidor ? $repartidor->cuenta : $repartidor;
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken);
    }

    /**
     * Una foto de prueba, como la manda la app ya comprimida.
     */
    protected function fotoDePrueba(string $nombre = 'foto.jpg', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->image($nombre, 800, 600)->size($kilobytes);
    }

    /**
     * Un repartidor activo, dado de alta en esa plaza; sin plaza, en la de prepararPedido().
     */
    protected function repartidorActivo(?Plaza $plaza = null): Repartidor
    {
        return Repartidor::factory()->en($plaza ?? $this->plaza)->create();
    }

    /**
     * Se le ofrece el pedido al repartidor, directo en la base: quién asigna espera DD-014 (repartidor-E13).
     */
    protected function ofrecerA(Pedido $pedido, Repartidor $repartidor): PedidoAsignacion
    {
        return PedidoAsignacion::factory()->para($pedido, $repartidor)->create();
    }

    protected function listaDelRepartidor(Repartidor $repartidor): TestResponse
    {
        return $this->comoRepartidor($repartidor)->getJson('/api/repartidor/pedidos');
    }

    protected function pedidoComoRepartidor(Repartidor $repartidor, string $pedido): TestResponse
    {
        return $this->comoRepartidor($repartidor)->getJson("/api/repartidor/pedidos/{$pedido}");
    }

    /**
     * Los id_publico de la lista del repartidor, en el orden en que llegaron.
     *
     * @return list<string>
     */
    protected function idsDeLaListaDelRepartidor(TestResponse $respuesta): array
    {
        return array_column($respuesta->json('pedidos') ?? [], 'id_publico');
    }

    /**
     * El repartidor entrega el pedido confirmado en ese instante, escrito en la hora de la plaza del pedido
     * ("2026-12-03 19:30"): la lavandería lo acepta, se le ofrece al repartidor y él lo acepta, la lavandería lo lleva
     * hasta En proceso de entrega y él marca Entregado, todo con las operaciones reales y en ese instante. Usa
     * EstadosDePrueba.
     */
    protected function entregaDe(Repartidor $repartidor, Pedido $pedido, string $instante): Pedido
    {
        $this->travelTo(CarbonImmutable::parse($instante, $pedido->plaza->zona_horaria));
        $this->aceptarComoLaLavanderia($pedido);
        $this->repartidorQueLoAcepta($pedido, $repartidor);

        return $this->llevarHastaEntregado($pedido);
    }

    protected function historialDelRepartidor(Repartidor $repartidor): TestResponse
    {
        return $this->comoRepartidor($repartidor)->getJson('/api/repartidor/historial');
    }

    protected function estadisticasDelRepartidor(Repartidor $repartidor): TestResponse
    {
        return $this->comoRepartidor($repartidor)->getJson('/api/repartidor/estadisticas');
    }

    /**
     * Una segunda plaza de prueba con su agenda y su zona (el cuadrado de un grado con su esquina suroeste en (-118, 10)),
     * y una lavandería activa en ella. Para los pedidos de otra plaza (repartidor-E12).
     *
     * @return array{Plaza, Lavanderia}
     */
    protected function otraPlazaConLavanderia(): array
    {
        $plaza = $this->plazaConAgenda('Plaza de prueba 2', zonaHoraria: self::ZONA_HORARIA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zonaDePrueba($plaza, 'Centro 2', $this->cuadrado(-118.0, 10.0));

        return [$plaza, $this->otraLavanderia($plaza)];
    }
}
