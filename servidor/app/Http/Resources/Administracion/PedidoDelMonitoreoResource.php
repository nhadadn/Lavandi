<?php

namespace App\Http\Resources\Administracion;

use App\Models\Pedido;
use App\Pedidos\NombresDelEstado;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un pedido activo como lo ve la administración en el monitoreo de la app (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
 * Módulo D #4; administracion-app-E28): su folio, su estado operativo y su plaza, como la tabla del monitoreo del
 * panel, y su recolección (design.md, F-D04; imagen 25 de la Propuesta 6). El estado se nombra con
 * App\Pedidos\NombresDelEstado, como la tabla y los marcadores del mapa (ADR-0022, decisión 9). La forma exacta está en
 * App\Http\Controllers\Api\Administracion\MonitoreoController.
 *
 * No lleva nada del repartidor, ni las direcciones: el mapa va en los marcadores de App\Pedidos\MarcadoresDelMonitoreo, y
 * la posición del repartidor está fuera del contrato (DD-077). Tampoco la llave interna ni los montos.
 *
 * @mixin Pedido
 */
class PedidoDelMonitoreoResource extends JsonResource
{
    /** Lo que la respuesta lee del pedido, para cargarlo junto con la lista. */
    public const RELACIONES = ['estado', 'plaza'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $inicio = $this->plaza->horaLocal($this->ventana_inicio);
        $fin = $this->plaza->horaLocal($this->ventana_fin);

        return [
            'id_publico' => $this->id_publico,
            'folio' => $this->folio,
            'estado' => [
                'codigo' => $this->estado->codigo,
                'nombre' => NombresDelEstado::nombre($this->estado),
            ],
            'plaza' => $this->plaza->nombre,
            // La recolección, como en los pedidos de la lavandería y del repartidor: instantes con el desfase de la plaza,
            // y la fecha y las horas en su hora.
            'ventana' => [
                'inicio' => $inicio->toIso8601String(),
                'fin' => $fin->toIso8601String(),
                'fecha' => $inicio->toDateString(),
                'hora_inicio' => $inicio->format('H:i'),
                'hora_fin' => $fin->format('H:i'),
            ],
        ];
    }
}
