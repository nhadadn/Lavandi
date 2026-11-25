<?php

namespace Tests\Feature\Indicadores\Soporte;

use App\Cobro\Desarrollo\CobroSimulado;
use App\Models\Pedido;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Lo que comparten las pruebas del panel de indicadores: pedidos confirmados a una hora dada de la plaza, con la
 * operación real, y la lectura de lo que da cada widget. Se usa junto con AgendaDePrueba, LavanderiasDePrueba,
 * PanelDePrueba, PedidosDePrueba y PlazasDePrueba, después de prepararPedido().
 *
 * Cada pedido nace al aceptar el resumen y lo confirma su cobro cuando la pasarela simulada acredita el pago
 * (C-188), a la hora que se pide: es su confirmado_en, la fecha del pedido en los indicadores (design.md, lectura 1).
 * Su ventana es de 11:00 a 12:00 del día siguiente, dentro del horario de la lavandería de prueba.
 *
 * Todo es ficticio: las cifras que esperan las pruebas salen de las filas que ellas mismas siembran.
 */
trait IndicadoresDePrueba
{
    /**
     * Un pedido de la cuenta, creado y confirmado a esa fecha y hora en la hora de la plaza de prueba. Sin cuenta, crea
     * una cuenta de usuario nueva. La recogida es una dirección nueva de la cuenta con lo que se pase encima
     * (PedidosDePrueba::direccionDe()); la entrega, otra si se pasa, o la misma.
     *
     * @param  array<string, mixed>  $recogida
     * @param  array<string, mixed>|null  $entrega
     */
    protected function pedidoConfirmadoEl(string $fechaYHora, ?Usuario $cuenta = null, array $recogida = [], ?array $entrega = null): Pedido
    {
        $pedido = $this->pedidoSinPagarEl($fechaYHora, $cuenta, $recogida, $entrega);

        // La pasarela simulada acredita el pago con su aviso, por el camino del webhook, y el cobro confirma el pedido.
        return app(CobroSimulado::class)->pagar($pedido)->fresh();
    }

    /**
     * Un pedido de la cuenta, creado a esa fecha y hora al aceptar el resumen, que nunca se paga: queda en por_pagar,
     * sin confirmado_en. Las direcciones, como en pedidoConfirmadoEl().
     *
     * @param  array<string, mixed>  $recogida
     * @param  array<string, mixed>|null  $entrega
     */
    protected function pedidoSinPagarEl(string $fechaYHora, ?Usuario $cuenta = null, array $recogida = [], ?array $entrega = null): Pedido
    {
        $instante = CarbonImmutable::parse($fechaYHora, self::ZONA_HORARIA);
        $this->travelTo($instante);

        $cuenta ??= $this->cuentaDeUsuario();
        $inicio = $instante->addDay()->setTime(11, 0);

        $id = $this->aceptarResumen($cuenta, $this->resumen(
            $this->direccionDe($cuenta, $recogida),
            $entrega === null ? null : $this->direccionDe($cuenta, $entrega),
            datos: [
                'ventana' => [
                    'inicio' => $inicio->toIso8601String(),
                    'fin' => $inicio->addHour()->toIso8601String(),
                ],
            ]))->assertCreated()->json('pedido.id_publico');

        return Pedido::query()->where('id_publico', $id)->sole();
    }

    /**
     * El widget montado como en la página de indicadores, con los filtros de la página.
     *
     * @param  class-string<Widget>  $widget
     * @param  array<string, mixed>  $filtros
     */
    protected function widget(string $widget, array $filtros = []): Testable
    {
        return Livewire::test($widget, ['pageFilters' => $filtros]);
    }

    /**
     * Lo que la gráfica le da a Chart.js: sus etiquetas y sus series. Se lee del método protegido getData() del
     * widget, sin cambiar su visibilidad.
     *
     * @return array<string, mixed>
     */
    protected function datosDeLaGrafica(Testable $grafica): array
    {
        $widget = $grafica->instance();

        return (fn (): array => $this->getData())->call($widget);
    }

    /**
     * Las claves de las filas de una tabla del panel, en su orden. Las tablas de indicadores no son de Eloquent: sus
     * filas son arreglos, y la clave de cada una es la de su periodo o su renglón.
     *
     * @return list<string>
     */
    protected function clavesDeLaTabla(Testable $tabla): array
    {
        return array_map('strval', $tabla->instance()->getTableRecords()->keys()->all());
    }

    /**
     * Los filtros de la página: el rango, los dos días incluidos, y la granularidad.
     *
     * @return array{desde: string, hasta: string, granularidad: string}
     */
    protected function filtros(string $desde, string $hasta, string $granularidad = 'dia'): array
    {
        return ['desde' => $desde, 'hasta' => $hasta, 'granularidad' => $granularidad];
    }
}
