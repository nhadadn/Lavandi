<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La tabla dispositivos: el token de Firebase Cloud Messaging de cada teléfono, ligado a la cuenta, con su plataforma y
 * la última vez que la app lo registró o lo renovó (openspec/changes/avisos/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md; ADR-0016; ADR-0008).
 *
 * - Sin id_plazas: está en la lista sin_plaza de config/modelo.php. El teléfono es de la cuenta, y la cuenta no
 *   pertenece a una plaza.
 * - token_fcm es único: un teléfono es de una sola cuenta a la vez (avisos-E21). Registrar un token que ya existe lo
 *   pasa a la cuenta que lo registra (App\Avisos\TelefonosRegistrados).
 * - Sin id_publico: la API no expone la fila; la app registra y borra por el token mismo.
 * - Sin borrado suave ni el trigger impedir_cambio(): un token que ya no sirve se borra (avisos-E05, E06), no es
 *   historial.
 *
 * Toda llave foránea lleva su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispositivos', function (Blueprint $tabla) {
            $tabla->id('id_dispositivos');
            // La cuenta a la que van los avisos de este teléfono.
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            // El token que da Firebase al teléfono. Texto: Firebase no fija su largo.
            $tabla->text('token_fcm')->unique();
            // La manda la app junto con el token.
            $tabla->string('plataforma');
            $tabla->timestampTz('ultimo_uso_en');
            $tabla->timestampsTz();
        });

        DB::statement("alter table dispositivos add constraint dispositivos_plataforma_check check (plataforma in ('ios', 'android'))");
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
