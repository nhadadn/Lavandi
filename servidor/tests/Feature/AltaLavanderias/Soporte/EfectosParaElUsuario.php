<?php

namespace Tests\Feature\AltaLavanderias\Soporte;

use App\Models\Lavanderia;
use App\Models\Usuario;
use Illuminate\Testing\TestResponse;

/**
 * Lo que el usuario de la app ve de una lavandería según su estado: si aparece en la búsqueda de cercanas, si abre su
 * perfil y si el servidor le crea un pedido nuevo. Se usa junto con PedidosDePrueba, después de prepararPedido(), que
 * deja $this->lavanderia en la zona de prueba con su horario y su precio, y el reloj a las 09:30 del jueves de prueba.
 */
trait EfectosParaElUsuario
{
    /**
     * Si la lavandería sale en la búsqueda de cercanas, desde su mismo punto.
     */
    protected function apareceEnLaBusqueda(Lavanderia $lavanderia, Usuario $cuenta): bool
    {
        $respuesta = $this->pedirComoUsuario('/api/lavanderias/cercanas', ['latitud' => 10.5, 'longitud' => -119.5], $cuenta)
            ->assertOk();

        return in_array($lavanderia->id_publico, array_column($respuesta->json('lavanderias'), 'id_publico'), true);
    }

    /**
     * La cuenta intenta un pedido nuevo con la lavandería, con la ventana de 11:00 a 12:00 del jueves de prueba y una
     * dirección suya en la zona.
     */
    protected function pedirA(Lavanderia $lavanderia, Usuario $cuenta): TestResponse
    {
        return $this->aceptarResumen($cuenta, $this->resumen($this->direccionDe($cuenta), datos: [
            'lavanderia' => $lavanderia->id_publico,
        ]));
    }
}
