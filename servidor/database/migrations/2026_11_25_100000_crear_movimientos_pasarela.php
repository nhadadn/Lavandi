<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * movimientos_pasarela: los pagos que acreditó la pasarela, como los da su propia API, para conciliarlos con los de la
 * plataforma (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6, "conciliación con Conekta";
 * openspec/changes/administracion/, tarea 0.2; segunda adenda del 2026-11-22 de ADR-0010, decisiones 6 y 7, que cierra
 * DD-112).
 *
 * - Una fila por orden de la pasarela, con su monto, la comisión si la trae y la fecha del pago. El único (pasarela,
 *   id_pasarela_orden) hace que leer dos veces el mismo periodo no repita ningún movimiento: la segunda lectura
 *   actualiza la fila.
 * - Se llena cuando la administración concilia un periodo (App\Finanzas\Conciliacion), no con una tarea programada.
 * - Sin plaza propia: la cuenta de la pasarela es una para todas las plazas, y la plaza de un movimiento es la del pedido
 *   con el que cuadra. Por eso no apunta a pagos: un movimiento sin pedido es justo una diferencia (administracion-E21).
 *   Está en la lista sin_plaza de config/modelo.php.
 * - Ningún nombre de tabla ni de columna nombra al proveedor (modelo-E14): la que cobró se dice con el valor de la
 *   columna pasarela, con los mismos valores que pagos: conekta, la del contrato (ADR-0010), y simulada, la de local y
 *   las pruebas. Ningún dato de tarjeta (pago-E05).
 * - La comisión se guarda como llega; mostrarla y descontarla espera a saber quién la absorbe (administracion-E22,
 *   DD-013).
 *
 * Sin el trigger impedir_cambio(): no es una de las cuatro tablas del historial.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_pasarela', function (Blueprint $tabla) {
            $tabla->id('id_movimientos_pasarela');
            // Cuál pasarela lo acreditó: la de la configuración del entorno al conciliar.
            $tabla->string('pasarela');
            // La orden en la pasarela: con ella cuadra con pagos.id_pasarela_orden.
            $tabla->string('id_pasarela_orden');
            // En pesos (ADR-0016, regla 7).
            $tabla->decimal('monto', 12, 2);
            // La que retiene la pasarela, si la trae (administracion-E22, DD-013).
            $tabla->decimal('comision', 12, 2)->nullable();
            // Cuándo la pasarela dio el pago por bueno.
            $tabla->timestampTz('pagado_en');
            $tabla->timestampsTz();
            $tabla->unique(['pasarela', 'id_pasarela_orden']);
        });

        DB::statement("alter table movimientos_pasarela add constraint movimientos_pasarela_pasarela_check check (pasarela in ('conekta', 'simulada'))");
        DB::statement('alter table movimientos_pasarela add constraint movimientos_pasarela_monto_check check (monto > 0)');
        DB::statement('alter table movimientos_pasarela add constraint movimientos_pasarela_comision_check check (comision is null or comision >= 0)');
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
