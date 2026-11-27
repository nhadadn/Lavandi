<?php

namespace Tests\Feature\Confirmacion\Soporte;

use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\LavanderiaServicio;
use App\Models\Plaza;
use App\Models\Rol;
use App\Models\Usuario;
use App\Models\Zona;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Direcciones\Soporte\CatalogoDePrueba;

/**
 * Lo que comparten las pruebas de resumen-y-confirmacion: una plaza de prueba con su agenda y su zona, una lavandería
 * con su horario y su precio por kilo, las direcciones de una cuenta dentro de la zona, el estado previo al pago y la
 * petición con que la app acepta el resumen. Se usa junto con AgendaDePrueba, LavanderiasDePrueba y
 * PlazasDePrueba.
 *
 * Todo es ficticio: la plaza y su zona están en el océano Pacífico (PlazasDePrueba), y los precios, la anticipación y
 * el horizonte son de prueba; los de producción los fija EL CLIENTE (DD-010, DD-099).
 *
 * El reloj se fija en el jueves de prueba a las 09:30 en la hora de la plaza (UTC-6). La ventana de omisión, 11:00 a
 * 12:00 de ese día, es en UTC 17:00 a 18:00.
 */
trait PedidosDePrueba
{
    protected const ZONA_HORARIA = 'America/Mexico_City';

    protected const JUEVES_DE_PRUEBA = '2026-12-03';

    protected const PRECIO_POR_KILO = '18.00';

    protected Plaza $plaza;

    protected Zona $zona;

    protected Lavanderia $lavanderia;

    protected LavanderiaServicio $servicio;

    /**
     * El reloj en esa hora del jueves de prueba, en la hora de la plaza.
     */
    protected function elJuevesALas(string $hora): void
    {
        $this->travelTo(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' '.$hora, self::ZONA_HORARIA));
    }

    /**
     * El estado previo al pago: por_pagar, el que siembra la migración y nombra config/pedidos.php (ADR-0021).
     */
    protected function estadoPrevioAlPago(): EstadoPedido
    {
        $this->assertSame(EstadoPedido::POR_PAGAR, config('pedidos.estado_previo_al_pago'));

        return EstadoPedido::query()->where('codigo', EstadoPedido::POR_PAGAR)->sole();
    }

    /**
     * La plaza de prueba con su agenda y su zona (el cuadrado de un grado con su esquina suroeste en (-120, 10)), y una
     * lavandería activa en ella, abierta todos los días de 9:00 a 13:00, con el lavado estándar por kilo. El reloj
     * queda a las 09:30 del jueves de prueba y el estado previo al pago, configurado.
     */
    protected function prepararPedido(): void
    {
        $this->elJuevesALas('09:30:00');
        $this->estadoPrevioAlPago();

        $this->plaza = $this->plazaConAgenda(zonaHoraria: self::ZONA_HORARIA, anticipacionMinutos: 0, diasHaciaAdelante: 3);
        $this->zona = $this->zonaDePrueba($this->plaza, 'Centro', $this->cuadrado(-120.0, 10.0));
        $this->lavanderia = $this->lavanderiaConHorario(
            $this->plaza,
            array_map(fn (int $dia): array => [$dia, '09:00', '13:00'], range(self::LUNES, self::DOMINGO)),
        );
        $this->servicio = $this->servicioDePrueba($this->lavanderia, 'estandar', self::PRECIO_POR_KILO);
    }

    protected function cuentaDeUsuario(): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create();
    }

    /**
     * Una dirección guardada de la cuenta, en la plaza de prueba y dentro de su zona, con lo que se pase encima. Su
     * municipio es el ficticio del catálogo de prueba, "Municipio de prueba" (CatalogoDePrueba).
     *
     * @param  array<string, mixed>  $datos
     */
    protected function direccionDe(Usuario $cuenta, array $datos = []): Direccion
    {
        return Direccion::create([
            'id_usuarios' => $cuenta->getKey(),
            'id_plazas' => $this->plaza->getKey(),
            'calle' => 'Calle de prueba',
            'numero_exterior' => '123',
            'numero_interior' => '4',
            'colonia' => 'Colonia de prueba',
            'codigo_postal' => '00000',
            'id_municipios' => CatalogoDePrueba::municipio()->getKey(),
            'referencias' => 'Portón de prueba',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
            ...$datos,
        ]);
    }

    /**
     * Lo que manda la app al aceptar el resumen: la lavandería, el servicio, la ventana tal como la dio la agenda y las
     * direcciones, con lo que se pase encima.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    protected function resumen(Direccion $recogida, ?Direccion $entrega = null, array $datos = []): array
    {
        return [
            'lavanderia' => $this->lavanderia->id_publico,
            'servicio' => 'estandar',
            'ventana' => [
                'inicio' => self::JUEVES_DE_PRUEBA.'T11:00:00-06:00',
                'fin' => self::JUEVES_DE_PRUEBA.'T12:00:00-06:00',
            ],
            'recogida' => $recogida->id_publico,
            'entrega' => $entrega?->id_publico,
            ...$datos,
        ];
    }

    /**
     * La app acepta el resumen con la sesión de la cuenta. Olvida la cuenta de la petición anterior (forgetGuards),
     * como dos peticiones reales al servidor.
     *
     * @param  array<string, mixed>  $resumen
     */
    protected function aceptarResumen(Usuario $cuenta, array $resumen): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($cuenta->createToken('app')->plainTextToken)->postJson('/api/pedidos', $resumen);
    }
}
