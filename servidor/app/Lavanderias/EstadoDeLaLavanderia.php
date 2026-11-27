<?php

namespace App\Lavanderias;

use App\Models\Lavanderia;
use Illuminate\Support\Facades\DB;

/**
 * Los cambios de estado de una lavandería que hace la administración desde el panel (NSV-2026-001-v8.pdf:19:ANEXO A,
 * Sección A.3, Módulo D #1, "baja, suspensión"; openspec/changes/alta-de-lavanderias/design.md, "Cómo usa los estados
 * este cambio"). Solo una lavandería activa aparece al usuario y recibe pedidos nuevos; quién aparece lo decide
 * Lavanderia::visiblesParaElUsuario(), que ya excluye las suspendidas y las de baja.
 *
 * - Suspender (alta-lavanderias-E14): de activa o pendiente a suspendida.
 * - Levantar la suspensión (alta-lavanderias-E15): de suspendida al estado que tenía, sin otra columna: activa si ya
 *   tuvo alta (aprobada_en con fecha), pendiente si no.
 * - Dar de baja (alta-lavanderias-E17): de cualquier otro estado a baja. La fila no se borra, ni en suave, y sus pedidos
 *   pasados se quedan.
 *
 * Lo que pasa con los pedidos en curso al suspender o dar de baja espera DD-104 (alta-lavanderias-E16): no se tocan.
 */
final class EstadoDeLaLavanderia
{
    public function puedeSuspenderse(Lavanderia $lavanderia): bool
    {
        return in_array($lavanderia->estado, [Lavanderia::ACTIVA, Lavanderia::PENDIENTE], true);
    }

    public function puedeLevantarseLaSuspension(Lavanderia $lavanderia): bool
    {
        return $lavanderia->estado === Lavanderia::SUSPENDIDA;
    }

    /**
     * Ninguna transición sale de baja: el contrato no pide deshacerla.
     */
    public function puedeDarseDeBaja(Lavanderia $lavanderia): bool
    {
        return $lavanderia->estado !== Lavanderia::BAJA;
    }

    /**
     * La baja no borra la fila, ni en suave: deleted_at queda vacío, para que el panel la siga listando y sus pedidos
     * la sigan encontrando (alta-lavanderias-E17).
     *
     * @throws TransicionNoPermitida
     */
    public function darDeBaja(Lavanderia $lavanderia): void
    {
        $this->cambiar($lavanderia, 'dar de baja', $this->puedeDarseDeBaja(...), fn (): string => Lavanderia::BAJA);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function suspender(Lavanderia $lavanderia): void
    {
        $this->cambiar($lavanderia, 'suspender', $this->puedeSuspenderse(...), fn (): string => Lavanderia::SUSPENDIDA);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function levantarSuspension(Lavanderia $lavanderia): void
    {
        $this->cambiar(
            $lavanderia,
            'levantar de una suspensión',
            $this->puedeLevantarseLaSuspension(...),
            fn (Lavanderia $actual): string => $actual->aprobada_en === null ? Lavanderia::PENDIENTE : Lavanderia::ACTIVA,
        );
    }

    /**
     * El cambio, con la fila bloqueada: la regla se revisa sobre el estado de la base, no sobre el que se leyó al abrir
     * el panel.
     *
     * @param  callable(Lavanderia): bool  $permitido
     * @param  callable(Lavanderia): string  $nuevo
     *
     * @throws TransicionNoPermitida
     */
    private function cambiar(Lavanderia $lavanderia, string $accion, callable $permitido, callable $nuevo): void
    {
        DB::transaction(function () use ($lavanderia, $accion, $permitido, $nuevo): void {
            /** @var Lavanderia $actual */
            $actual = Lavanderia::query()->whereKey($lavanderia->getKey())->lockForUpdate()->firstOrFail();

            if (! $permitido($actual)) {
                throw TransicionNoPermitida::de($actual->estado, $accion);
            }

            $actual->update(['estado' => $nuevo($actual)]);
            $lavanderia->setRawAttributes($actual->getAttributes(), sync: true);
        });
    }
}
