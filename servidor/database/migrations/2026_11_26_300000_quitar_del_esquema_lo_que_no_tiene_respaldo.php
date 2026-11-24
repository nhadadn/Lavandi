<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quita del esquema dos cosas que no tienen respaldo en el contrato ni en una decisión tomada (ADR-0016, regla 9: hacia
 * adelante, sin editar las migraciones que las crearon, que alguien ya pudo correr).
 *
 * - usuarios.terminos_aceptados_en: la constancia de que la persona aceptó el aviso y los términos. Los consentimientos
 *   son de EL CLIENTE (NSV-2026-001-v8.pdf:10:Cláusula X), textos-legales los deja fuera (design.md, Non-Goals) y si el
 *   servidor la guarda es DD-080 (registro-E29, SIN RESPUESTA). Nada la llenaba. Si DD-080 la pide, entra con su propia
 *   migración.
 * - El valor 'recoleccion' del CHECK de evidencias.tipo: solo queda la evidencia de entrega (NSV-2026-001-v8.pdf:20:
 *   ANEXO A, Sección A.3, Almacenamiento de imágenes, "las evidencias de entrega"; repartidor-E23). La foto al
 *   recolectar es la propuesta de DD-110, sin respuesta (repartidor-E24), y ninguna pantalla la tomaba. Si hubiera una
 *   fila de recolección, el CHECK nuevo no entra y la migración falla: no se borra una evidencia en silencio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $tabla) {
            $tabla->dropColumn('terminos_aceptados_en');
        });

        DB::statement('alter table evidencias drop constraint evidencias_tipo_check');
        DB::statement("alter table evidencias add constraint evidencias_tipo_check check (tipo in ('entrega'))");
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
