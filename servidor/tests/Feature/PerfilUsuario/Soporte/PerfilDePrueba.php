<?php

namespace Tests\Feature\PerfilUsuario\Soporte;

use App\Models\Direccion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pedido;
use App\Models\Plaza;
use App\Models\Rol;
use App\Models\Usuario;

/**
 * Lo que comparten las pruebas de perfil-y-soporte: las cuentas de usuario con su sesión, y los pedidos y las
 * direcciones de una cuenta en una plaza. Se usa junto con Tests\Feature\Plazas\Soporte\PlazasDePrueba.
 *
 * Todo es ficticio: las plazas son las de PlazasDePrueba y los puntos caen en el océano Pacífico.
 */
trait PerfilDePrueba
{
    protected function cuentaDeUsuario(array $datos = []): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create($datos);
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
     * Un pedido de la cuenta, de una lavandería nueva de la plaza, en el estado previo al pago, con lo que se pase
     * encima.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function pedidoDe(Usuario $cuenta, Plaza $plaza, array $datos = []): Pedido
    {
        $pedido = Pedido::create([
            'id_plazas' => $plaza->getKey(),
            'id_usuarios' => $cuenta->getKey(),
            'id_lavanderias' => Lavanderia::factory()->en($plaza)->create()->getKey(),
            'id_estados_pedido' => EstadoPedido::query()->where('codigo', EstadoPedido::POR_PAGAR)->value('id_estados_pedido'),
            'ventana_inicio' => '2026-12-03 17:00:00+00',
            'ventana_fin' => '2026-12-03 18:00:00+00',
        ]);

        if (isset($datos['created_at'])) {
            $pedido->forceFill(['created_at' => $datos['created_at']])->save();
        }

        return $pedido;
    }

    /**
     * Una dirección guardada de la cuenta, en la plaza que se pase o sin plaza, con lo que se pase encima.
     *
     * @param  array<string, mixed>  $datos
     */
    protected function direccionDe(Usuario $cuenta, ?Plaza $plaza, array $datos = []): Direccion
    {
        $direccion = Direccion::create([
            'id_usuarios' => $cuenta->getKey(),
            'id_plazas' => $plaza?->getKey(),
            'colonia' => 'Colonia de prueba',
            'ubicacion' => ['latitud' => 10.5, 'longitud' => -119.5],
        ]);

        if (isset($datos['created_at'])) {
            $direccion->forceFill(['created_at' => $datos['created_at']])->save();
        }

        return $direccion;
    }
}
