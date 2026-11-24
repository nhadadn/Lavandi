<?php

namespace App\Comisiones;

use App\Models\Comision;
use App\Models\Lavanderia;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Los porcentajes de comisión: el global y el propio de cada lavandería, que prevalece sobre el global
 * (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5, "Porcentaje de comisión por socio o global";
 * openspec/changes/administracion/design.md, lectura 2; administracion-E11 a E13).
 *
 * - El global es uno solo para todo el sistema: la comisión sin plaza ni lavandería (design.md, Open Questions 4). Le
 *   aplica a toda lavandería sin porcentaje propio, de cualquier plaza. El propio lleva su lavandería y su plaza.
 * - Ninguno viene de fábrica: sin porcentaje guardado no aplica ninguno, y queAplica() da null (administracion-E11).
 * - Un porcentaje va de 0 a 100, con a lo más dos decimales. La regla vive aquí, además del CHECK de la base: el panel
 *   valida con las mismas reglas (administracion-E13).
 * - Fijar un porcentaje cierra el vigente en este instante y abre uno nuevo; el anterior queda como historial
 *   (vault/arquitectura/modelo-de-datos.md). Uno que empezó en este mismo instante nunca rigió: se reemplaza, en lugar
 *   de dejar una vigencia vacía.
 * - Una vigencia rige desde vigente_desde, incluido, hasta vigente_hasta, excluido; sin fin, sigue vigente.
 *
 * El cálculo de la comisión de cada pedido espera la base que fije EL CLIENTE (DD-020 y DD-013, administracion-E15 y
 * E16): cuando llegue, parte de queAplica() en el instante que diga DD-020.
 */
final class PorcentajesDeComision
{
    /**
     * Las reglas del porcentaje, para el panel y para fijar().
     *
     * @return array<string, list<string>>
     */
    public static function reglas(): array
    {
        return ['porcentaje' => ['required', 'numeric', 'between:0,100', 'decimal:0,2']];
    }

    /**
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            'porcentaje.required' => 'Escribe el porcentaje.',
            'porcentaje.numeric' => 'El porcentaje es un número.',
            'porcentaje.between' => 'El porcentaje va de 0 a 100.',
            'porcentaje.decimal' => 'El porcentaje lleva a lo más dos decimales.',
        ];
    }

    /**
     * Fija el porcentaje global, para toda lavandería sin porcentaje propio, de cualquier plaza (administracion-E11).
     *
     * @throws ValidationException
     */
    public function fijarGlobal(mixed $porcentaje): Comision
    {
        $this->validar($porcentaje);

        return DB::transaction(function () use ($porcentaje): Comision {
            // Un cambio del global a la vez: el candado vive lo que la transacción. El índice único de la global abierta
            // rechaza además dos abiertas.
            DB::statement('select pg_advisory_xact_lock(hashtext(?))', ['comisiones.global']);

            return $this->abrir($this->delGlobal(), [], $porcentaje);
        });
    }

    /**
     * Fija el porcentaje propio de la lavandería: le aplica el suyo y no el global (administracion-E12).
     *
     * @throws ValidationException
     */
    public function fijarPropio(Lavanderia $lavanderia, mixed $porcentaje): Comision
    {
        $this->validar($porcentaje);

        return DB::transaction(function () use ($lavanderia, $porcentaje): Comision {
            Lavanderia::withTrashed()->whereKey($lavanderia->getKey())->lockForUpdate()->sole();

            return $this->abrir(
                $this->delPropio($lavanderia),
                ['id_plazas' => $lavanderia->id_plazas, 'id_lavanderias' => $lavanderia->getKey()],
                $porcentaje,
            );
        });
    }

    /**
     * Quita el porcentaje propio de la lavandería: le vuelve a aplicar el global (administracion-E12). Sin propio
     * vigente, no hace nada.
     */
    public function quitarPropio(Lavanderia $lavanderia): void
    {
        DB::transaction(function () use ($lavanderia): void {
            Lavanderia::withTrashed()->whereKey($lavanderia->getKey())->lockForUpdate()->sole();

            $abierto = $this->delPropio($lavanderia)->whereNull('vigente_hasta')->first();

            if ($abierto === null) {
                return;
            }

            $ahora = self::ahora();

            // Uno que empezó en este mismo instante nunca rigió.
            $abierto->vigente_desde->greaterThanOrEqualTo($ahora)
                ? $abierto->delete()
                : $abierto->update(['vigente_hasta' => $ahora]);
        });
    }

    /**
     * El porcentaje global vigente en ese instante; sin instante, ahora.
     */
    public function globalVigente(?CarbonImmutable $en = null): ?Comision
    {
        return $this->vigenteEn($this->delGlobal(), $en)->first();
    }

    /**
     * El porcentaje propio de la lavandería vigente en ese instante; sin instante, ahora.
     */
    public function propioVigente(Lavanderia $lavanderia, ?CarbonImmutable $en = null): ?Comision
    {
        return $this->vigenteEn($this->delPropio($lavanderia), $en)->first();
    }

    /**
     * El porcentaje que le aplica a la lavandería en ese instante: el propio si tiene; si no, el global; si tampoco hay
     * global, ninguno (administracion-E11 y E12).
     */
    public function queAplica(Lavanderia $lavanderia, ?CarbonImmutable $en = null): ?Comision
    {
        return $this->propioVigente($lavanderia, $en) ?? $this->globalVigente($en);
    }

    /**
     * Cierra el vigente y abre el nuevo en este instante. El que empezó en este mismo instante se reemplaza.
     *
     * @param  Builder<Comision>  $deLaMisma
     * @param  array<string, int|null>  $llaves
     */
    private function abrir(Builder $deLaMisma, array $llaves, mixed $porcentaje): Comision
    {
        $ahora = self::ahora();
        $abierto = $deLaMisma->whereNull('vigente_hasta')->first();

        if ($abierto !== null && $abierto->vigente_desde->greaterThanOrEqualTo($ahora)) {
            $abierto->update(['porcentaje' => $porcentaje]);

            return $abierto;
        }

        $abierto?->update(['vigente_hasta' => $ahora]);

        return Comision::create([...$llaves, 'porcentaje' => $porcentaje, 'vigente_desde' => $ahora]);
    }

    /**
     * @return Builder<Comision>
     */
    private function delGlobal(): Builder
    {
        return Comision::query()->whereNull('id_lavanderias')->whereNull('id_plazas');
    }

    /**
     * @return Builder<Comision>
     */
    private function delPropio(Lavanderia $lavanderia): Builder
    {
        return Comision::query()->where('id_lavanderias', $lavanderia->getKey());
    }

    /**
     * @param  Builder<Comision>  $consulta
     * @return Builder<Comision>
     */
    private function vigenteEn(Builder $consulta, ?CarbonImmutable $en): Builder
    {
        $instante = ($en ?? CarbonImmutable::now())->utc();

        return $consulta
            ->where('vigente_desde', '<=', $instante)
            ->where(fn (Builder $fin) => $fin->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>', $instante))
            ->orderByDesc('vigente_desde');
    }

    /**
     * Este instante en UTC, al segundo: la precisión con que la base guarda la vigencia (timestamptz(0)).
     */
    private static function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now()->utc()->startOfSecond();
    }

    /**
     * @throws ValidationException
     */
    private function validar(mixed $porcentaje): void
    {
        Validator::make(['porcentaje' => $porcentaje], self::reglas(), self::mensajes())->validate();
    }
}
