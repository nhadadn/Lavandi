<?php

namespace App\Repartidores;

use App\Models\Plaza;
use App\Models\Repartidor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Los cambios de estado del repartidor que hace la administración desde el panel (NSV-2026-001-v8.pdf:19:ANEXO A,
 * Sección A.3, Módulo D #2, "Alta, baja, suspensión"; openspec/changes/repartidor/design.md, lecturas 1 y 2). Quién
 * opera con cada estado lo decide App\Autorizacion\Autorizacion::repartidorQueOpera(): solo uno activo y con su plaza.
 *
 * - Dar de alta (repartidor-E05): de pendiente a activo, en la plaza que elige la administración, con la hora en
 *   aprobado_en. Es autorizar el registro que ya existe (lectura 1); la validación ocurre fuera de la plataforma.
 * - Suspender (repartidor-E06): de activo a suspendido. Quitar la suspensión: de suspendido a activo, en su misma plaza.
 * - Dar de baja (repartidor-E07): de activo o suspendido a baja, en borrado suave, y con sus sesiones revocadas: ya no
 *   inicia sesión. Sus entregas se conservan. También el pendiente que nunca se dio de alta, con aprobado_en vacía: es el
 *   registro que EL CLIENTE no autoriza, y queda de baja sin plaza, como lo admite repartidores_plaza_check
 *   (repartidor-E31; acta-2026-11-20-modelo-y-pasarela.md, decisión 54). Ninguna transición sale de baja: el contrato no
 *   pide deshacerla. Lo que puede hacer después una cuenta dada de baja es DD-104.
 *
 * Lo que pasa con sus pedidos en curso al suspenderlo o darlo de baja no lo fija la spec: no se tocan.
 */
final class EstadoDelRepartidor
{
    public function puedeDarseDeAlta(Repartidor $repartidor): bool
    {
        return $repartidor->estado === Repartidor::PENDIENTE && ! $repartidor->trashed();
    }

    public function puedeSuspenderse(Repartidor $repartidor): bool
    {
        return $repartidor->estado === Repartidor::ACTIVO && ! $repartidor->trashed();
    }

    public function puedeLevantarseLaSuspension(Repartidor $repartidor): bool
    {
        return $repartidor->estado === Repartidor::SUSPENDIDO && ! $repartidor->trashed();
    }

    public function puedeDarseDeBaja(Repartidor $repartidor): bool
    {
        if ($repartidor->trashed()) {
            return false;
        }

        return in_array($repartidor->estado, [Repartidor::ACTIVO, Repartidor::SUSPENDIDO], true)
            // El registro que nunca se autorizó (repartidor-E31).
            || ($repartidor->estado === Repartidor::PENDIENTE && $repartidor->aprobado_en === null);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function darDeAlta(Repartidor $repartidor, Plaza $plaza): void
    {
        $this->cambiar($repartidor, 'dar de alta', $this->puedeDarseDeAlta(...), fn (): array => [
            'estado' => Repartidor::ACTIVO,
            'id_plazas' => $plaza->getKey(),
            // En UTC, como toda fecha que se guarda.
            'aprobado_en' => CarbonImmutable::now()->utc(),
        ]);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function suspender(Repartidor $repartidor): void
    {
        $this->cambiar($repartidor, 'suspender', $this->puedeSuspenderse(...), fn (): array => ['estado' => Repartidor::SUSPENDIDO]);
    }

    /**
     * @throws TransicionNoPermitida
     */
    public function levantarSuspension(Repartidor $repartidor): void
    {
        $this->cambiar(
            $repartidor,
            'levantar de una suspensión',
            $this->puedeLevantarseLaSuspension(...),
            fn (): array => ['estado' => Repartidor::ACTIVO],
        );
    }

    /**
     * La baja deja la fila, con estado baja y en borrado suave, y revoca todos los tokens de la cuenta: la siguiente
     * petición de la app con su sesión abierta se rechaza (repartidor-E07 y E31). La cuenta no se borra.
     *
     * @throws TransicionNoPermitida
     */
    public function darDeBaja(Repartidor $repartidor): void
    {
        $this->cambiar($repartidor, 'dar de baja', $this->puedeDarseDeBaja(...), fn (): array => [
            'estado' => Repartidor::BAJA,
            'deleted_at' => CarbonImmutable::now()->utc(),
        ], function (Repartidor $actual): void {
            $actual->cuenta()->sole()->tokens()->delete();
        });
    }

    /**
     * El cambio, con la fila bloqueada: la regla se revisa sobre el estado de la base, no sobre el que se leyó al abrir
     * el panel.
     *
     * @param  callable(Repartidor): bool  $permitido
     * @param  callable(Repartidor): array<string, mixed>  $cambios
     * @param  (callable(Repartidor): void)|null  $despues  Lo que se hace en la misma transacción, ya cambiado.
     *
     * @throws TransicionNoPermitida
     */
    private function cambiar(Repartidor $repartidor, string $accion, callable $permitido, callable $cambios, ?callable $despues = null): void
    {
        DB::transaction(function () use ($repartidor, $accion, $permitido, $cambios, $despues): void {
            /** @var Repartidor $actual */
            $actual = Repartidor::withTrashed()->whereKey($repartidor->getKey())->lockForUpdate()->firstOrFail();

            if (! $permitido($actual)) {
                throw TransicionNoPermitida::de($actual->estado, $accion);
            }

            $actual->forceFill($cambios($actual))->save();

            if ($despues !== null) {
                $despues($actual);
            }

            $repartidor->setRawAttributes($actual->getAttributes(), sync: true);
        });
    }
}
