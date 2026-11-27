<?php

namespace App\Filament\Resources\Usuarios;

use App\Models\Usuario;
use App\Pedidos\HistorialDelUsuario as Historial;
use Illuminate\Support\Number;

/**
 * El historial de pedidos de la ficha del usuario en el panel, como lo escribe su tabla (administracion-E01): la fecha
 * DD/MM/AAAA y el monto en pesos, o null, y entonces la ficha dice "Sin monto".
 *
 * Qué pedidos van y en qué orden, su fecha en la hora de la plaza, su lavandería, su estado y su monto los decide la
 * clase de dominio App\Pedidos\HistorialDelUsuario, la misma de la ficha de la API de la administración (ADR-0022,
 * decisión 9; openspec/changes/administracion-en-la-app/, tarea 7.1). Aquí queda solo cómo los escribe el panel. La
 * llama la ficha (Schemas\UsuarioInfolist).
 */
final class HistorialDelUsuario
{
    public function __construct(private readonly Historial $historial) {}

    /**
     * @return list<array{folio: string, fecha: string, lavanderia: string, estado: string, monto: string|null}>
     */
    public function de(Usuario $quienMira, Usuario $usuario): array
    {
        return array_map(fn (array $fila): array => [
            ...$fila,
            'fecha' => $fila['fecha']->format('d/m/Y'),
            'monto' => $fila['monto'] === null ? null : Number::currency((float) $fila['monto'], in: 'MXN', locale: 'es_MX'),
        ], $this->historial->de($quienMira, $usuario));
    }
}
