<?php

namespace Database\Seeders;

use App\Agenda\Agenda;
use App\Agenda\Ventana;
use App\Cobro\Desarrollo\CobroSimulado;
use App\Cobro\PasarelaNoDisponible;
use App\Direcciones\DireccionesDelPedido;
use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use App\Pedidos\CambioDeEstado;
use App\Pedidos\CreacionDePedido;
use App\Pedidos\EstadoDelPedidoCambiado;
use App\Pedidos\PedidoConfirmado;
use App\Plazas\ResolucionDePunto;
use Carbon\CarbonImmutable;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

/**
 * El repartidor de desarrollo, para probar a mano la app del Módulo C (openspec/changes/repartidor/design.md, "Cómo se
 * prueba en local"):
 *
 * - repartidor@ejemplo.com, con el rol de repartidor, activo en la plaza de prueba de Durango, con la contraseña de las
 *   cuentas de prueba (UsuarioFactory::CONTRASENA) guardada con hash;
 * - tres pedidos de prueba de usuario.de.prueba@ejemplo.com a la lavandería de prueba Durango Norte, ofrecidos a él: uno
 *   que la lavandería todavía no responde, uno que ya aceptó y uno listo para el reparto. Así se prueban aceptar,
 *   rechazar con motivo y los tres botones (repartidor-E10, E15 a E21 y E23).
 *
 * Los pedidos nacen por el camino real: el resumen aceptado (CreacionDePedido) en la primera ventana que ofrece la
 * agenda, el pago con la pasarela simulada, cuyo aviso deja el cobro pagado y confirma el pedido (CobroSimulado, C-188;
 * pago-E18), y los botones de la lavandería (CambioDeEstado), sin mandar avisos ni correos. El cobro necesita
 * PASARELA=simulada y PASARELA_SIMULADA_LLAVE en el .env; sin ellos se siembra el repartidor sin pedidos. La oferta al repartidor se crea directo en la base: quién asigna espera
 * DD-014 (repartidor-E13).
 *
 * Solo en local: nunca en el entorno de prueba, que está en internet, ni en producción (ADR-0012). Necesita las plazas y
 * las lavanderías de prueba (PlazasDePruebaSeeder y LavanderiasDePruebaSeeder). Sembrar dos veces no duplica nada: si el
 * repartidor ya tiene pedidos ofrecidos, no se crean otros. Son cuentas ficticias, no secretos.
 */
class RepartidorDeDesarrolloSeeder extends Seeder
{
    public const CORREO = 'repartidor@ejemplo.com';

    public const CORREO_DEL_USUARIO = 'usuario.de.prueba@ejemplo.com';

    private const LAVANDERIA = 'Lavandería de prueba Durango Norte';

    /** Un punto de la zona de prueba Durango Centro, cerca de la lavandería de prueba Durango Norte. */
    private const RECOGIDA = ['latitud' => 24.0420, 'longitud' => -104.6550];

    /** Cuántos días adelante se busca la primera ventana de la agenda, contando hoy. */
    private const DIAS_DE_BUSQUEDA = 4;

    public function run(
        ResolucionDePunto $resolucion,
        Agenda $agenda,
        CreacionDePedido $creacion,
        CobroSimulado $cobro,
        CambioDeEstado $cambio,
    ): void {
        if (! app()->isLocal()) {
            $this->command?->warn('El repartidor de desarrollo solo se siembra en local.');

            return;
        }

        $lavanderia = Lavanderia::query()->where('nombre', self::LAVANDERIA)->with('plaza')->first();
        $punto = $resolucion->resolver(self::RECOGIDA['latitud'], self::RECOGIDA['longitud']);

        if ($lavanderia === null || $punto === null || ! $punto->plaza->is($lavanderia->plaza)) {
            $this->command?->warn('Faltan las plazas o las lavanderías de prueba de Durango; no se siembra el repartidor.');

            return;
        }

        $repartidor = $this->repartidor($lavanderia);

        if ($repartidor->asignaciones()->exists()) {
            return;
        }

        $ventana = $this->primeraVentana($agenda, $lavanderia);

        if ($ventana === null) {
            $this->command?->warn('La agenda de prueba no ofrece ventanas en los próximos días; no se siembran pedidos.');

            return;
        }

        try {
            $this->sembrarPedidos($lavanderia, $repartidor, $ventana, $punto->plaza->getKey(), $creacion, $cobro, $cambio);
        } catch (PasarelaNoDisponible) {
            $this->command?->warn('Sin la pasarela simulada (PASARELA=simulada y PASARELA_SIMULADA_LLAVE) no se siembran pedidos.');
        }
    }

    /**
     * Los tres pedidos ofrecidos al repartidor, en una transacción: si el cobro simulado no está disponible, no queda
     * ninguno.
     *
     * @throws PasarelaNoDisponible sin la pasarela simulada
     */
    private function sembrarPedidos(
        Lavanderia $lavanderia,
        Repartidor $repartidor,
        Ventana $ventana,
        int $plaza,
        CreacionDePedido $creacion,
        CobroSimulado $cobro,
        CambioDeEstado $cambio,
    ): void {
        // Sin avisos al teléfono ni correo de confirmación: son pedidos de prueba.
        Event::fakeFor(function () use ($lavanderia, $repartidor, $ventana, $plaza, $creacion, $cobro, $cambio): void {
            DB::transaction(function () use ($lavanderia, $repartidor, $ventana, $plaza, $creacion, $cobro, $cambio): void {
                $usuario = $this->cuenta(self::CORREO_DEL_USUARIO, Rol::USUARIO, 'Usuario de prueba');
                $direccion = $this->direccion($usuario, $plaza);
                $direcciones = new DireccionesDelPedido($direccion, $direccion, true);
                $socio = $lavanderia->administrador;

                // Uno que la lavandería todavía no responde.
                $porResponder = $this->pedido($creacion, $cobro, $usuario, $lavanderia, $ventana, $direcciones);

                // Uno que la lavandería ya aceptó: el repartidor, al aceptarlo, puede marcar Recolectado.
                $aceptado = $this->pedido($creacion, $cobro, $usuario, $lavanderia, $ventana, $direcciones);
                $cambio->aceptar($aceptado, $socio);

                // Uno listo para el reparto: el repartidor, al aceptarlo, puede marcar En tránsito y Entregado.
                $listo = $this->pedido($creacion, $cobro, $usuario, $lavanderia, $ventana, $direcciones);
                $cambio->aceptar($listo, $socio);
                $cambio->marcar($listo, $socio, EstadoPedido::RECOLECTADO);
                $cambio->marcar($listo, $socio, EstadoPedido::EN_PROCESO);
                $cambio->marcar($listo, $socio, EstadoPedido::LISTO);

                foreach ([$porResponder, $aceptado, $listo] as $pedido) {
                    PedidoAsignacion::factory()->para($pedido, $repartidor)->create();
                }
            });
        }, [PedidoConfirmado::class, EstadoDelPedidoCambiado::class]);
    }

    /**
     * La cuenta del repartidor y su fila, activa en la plaza de la lavandería de prueba.
     */
    private function repartidor(Lavanderia $lavanderia): Repartidor
    {
        $cuenta = $this->cuenta(self::CORREO, Rol::REPARTIDOR, 'Repartidor de prueba');

        /** @var Repartidor $repartidor */
        $repartidor = Repartidor::withTrashed()->firstOrNew(['id_usuarios' => $cuenta->getKey()]);
        $repartidor->forceFill([
            'id_plazas' => $lavanderia->id_plazas,
            'tipo_vehiculo' => 'Motocicleta',
            'estado' => Repartidor::ACTIVO,
            'aprobado_en' => $repartidor->aprobado_en ?? CarbonImmutable::now()->utc(),
            'deleted_at' => null,
        ])->save();

        return $repartidor;
    }

    /**
     * Una cuenta de prueba con ese correo y ese rol, la contraseña de prueba, su nombre y un teléfono de prueba.
     */
    private function cuenta(string $correo, string $rol, string $nombre): Usuario
    {
        $cuenta = Usuario::withTrashed()->firstOrCreate(['correo' => $correo], ['password' => UsuarioFactory::CONTRASENA]);

        if ($cuenta->trashed()) {
            $cuenta->restore();
        }

        $cuenta->forceFill(['nombre' => $cuenta->nombre ?? $nombre, 'telefono' => $cuenta->telefono ?? '6180000000'])->save();
        $cuenta->roles()->syncWithoutDetaching([Rol::query()->where('codigo', $rol)->sole()->getKey()]);

        return $cuenta;
    }

    private function direccion(Usuario $usuario, int $plaza): Direccion
    {
        return Direccion::query()->firstOrCreate(
            ['id_usuarios' => $usuario->getKey(), 'alias' => 'Casa de prueba'],
            [
                'id_plazas' => $plaza,
                'calle' => 'Calle de prueba',
                'numero_exterior' => '10',
                'colonia' => 'Colonia de prueba',
                'codigo_postal' => '34000',
                'referencias' => 'Dirección ficticia para probar la app.',
                'ubicacion' => self::RECOGIDA,
            ],
        );
    }

    /**
     * La primera ventana que ofrece la agenda a la lavandería, de hoy en adelante, en la hora de su plaza.
     */
    private function primeraVentana(Agenda $agenda, Lavanderia $lavanderia): ?Ventana
    {
        $hoy = CarbonImmutable::now($lavanderia->plaza->zona_horaria);

        for ($dia = 0; $dia < self::DIAS_DE_BUSQUEDA; $dia++) {
            $ventanas = $agenda->dia($lavanderia, $hoy->addDays($dia)->toDateString())->ventanas;

            if ($ventanas->isNotEmpty()) {
                return $ventanas->first();
            }
        }

        return null;
    }

    /**
     * Un pedido del lavado estándar en esa ventana, creado como al aceptar el resumen y pagado con la pasarela simulada,
     * que lo confirma por su cobro.
     */
    private function pedido(
        CreacionDePedido $creacion,
        CobroSimulado $cobro,
        Usuario $usuario,
        Lavanderia $lavanderia,
        Ventana $ventana,
        DireccionesDelPedido $direcciones,
    ): Pedido {
        $datos = $ventana->toArray();
        $pedido = $creacion->crear($usuario, $lavanderia, 'estandar', ['inicio' => $datos['inicio'], 'fin' => $datos['fin']], $direcciones);

        return $cobro->pagar($pedido);
    }
}
