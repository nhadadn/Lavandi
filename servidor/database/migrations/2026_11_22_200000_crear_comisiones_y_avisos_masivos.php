<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las dos tablas de administracion: comisiones y avisos_masivos (openspec/changes/administracion/design.md, "Tablas y
 * migración"; vault/arquitectura/modelo-de-datos.md; ADR-0016).
 *
 * - comisiones: el porcentaje global de cada plaza (id_lavanderias vacío) y el propio de cada lavandería, con su
 *   vigencia (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5). La llave hacia la lavandería es compuesta con la
 *   plaza (modelo-E06). Un cambio de porcentaje es una fila nueva y la anterior queda como historial, con su fin de
 *   vigencia; por eso hay a lo más una abierta, sin fin, por plaza y por lavandería. No se siembra ningún porcentaje: los
 *   captura EL CLIENTE (administracion-E11).
 * - avisos_masivos: el aviso al celular de la administración (Módulo D #7). id_plazas es opcional, vacía para un aviso a
 *   todas las plazas: la tabla está en la lista plaza_opcional de config/modelo.php. Los criterios del segmento son
 *   DD-113. enviado_en es cuándo lo envió la administración; destinatarios, a cuántos teléfonos salió, que se llena al
 *   mandarlo (administracion-E23).
 *
 * Ninguna lleva el trigger impedir_cambio(), que es solo del historial del pedido y del dinero. Toda llave foránea lleva
 * su índice y ON DELETE RESTRICT (modelo-E09 y modelo-E10).
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->crearComisiones();
        $this->crearAvisosMasivos();
    }

    private function crearComisiones(): void
    {
        Schema::create('comisiones', function (Blueprint $tabla) {
            $tabla->id('id_comisiones');
            $tabla->foreignId('id_plazas')->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Vacía, la comisión global de la plaza; con valor, la propia de esa lavandería.
            $tabla->unsignedBigInteger('id_lavanderias')->nullable();
            $tabla->decimal('porcentaje', 5, 2);
            $tabla->timestampTz('vigente_desde');
            // Vacía, sigue vigente.
            $tabla->timestampTz('vigente_hasta')->nullable();
            $tabla->timestampsTz();
            $tabla->index(['id_lavanderias', 'id_plazas']);
            $tabla->foreign(['id_lavanderias', 'id_plazas'])
                ->references(['id_lavanderias', 'id_plazas'])
                ->on('lavanderias')
                ->restrictOnDelete();
        });

        DB::statement(
            'alter table comisiones add constraint comisiones_porcentaje_check check (porcentaje between 0 and 100)',
        );
        DB::statement(
            'alter table comisiones add constraint comisiones_vigencia_check
                check (vigente_hasta is null or vigente_hasta > vigente_desde)',
        );
        // A lo más una abierta: la global de cada plaza y la propia de cada lavandería.
        DB::statement(
            'create unique index comisiones_global_abierta_unique on comisiones (id_plazas)
                where id_lavanderias is null and vigente_hasta is null',
        );
        DB::statement(
            'create unique index comisiones_propia_abierta_unique on comisiones (id_lavanderias)
                where id_lavanderias is not null and vigente_hasta is null',
        );
    }

    private function crearAvisosMasivos(): void
    {
        Schema::create('avisos_masivos', function (Blueprint $tabla) {
            $tabla->id('id_avisos_masivos');
            // Vacía: el aviso es para todas las plazas.
            $tabla->foreignId('id_plazas')->nullable()->index()->constrained('plazas', 'id_plazas')->restrictOnDelete();
            // Vacío: sin segmento. Sus criterios son DD-113.
            $tabla->jsonb('segmento')->nullable();
            $tabla->string('titulo');
            $tabla->text('mensaje');
            // La cuenta de la administración que lo envió.
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->timestampTz('enviado_en');
            // Vacío hasta que sale: el número de teléfonos a los que se mandó.
            $tabla->integer('destinatarios')->nullable();
            $tabla->timestampsTz();
        });

        DB::statement(
            'alter table avisos_masivos add constraint avisos_masivos_destinatarios_check check (destinatarios >= 0)',
        );
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
