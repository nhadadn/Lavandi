<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa lavanderias para el alta real (openspec/changes/alta-de-lavanderias/design.md, "Tablas y migración";
 * vault/arquitectura/modelo-de-datos.md; ADR-0016, regla 9). La tabla nace en perfil-de-lavanderia con estado, su CHECK
 * de los cuatro valores y aprobada_en; esta migración no los repite:
 *
 * - estado vale activa por omisión, para que las lavanderías de prueba que ya existen sigan apareciendo. El estado con
 *   que nace un registro de la app no lo da la omisión: lo pone el registro (App\Lavanderias\RegistroDeLavanderia).
 * - aprobada_en se llena con la hora en que corre en las filas que ya existían, porque esas lavanderías ya tenían alta,
 *   aunque hoy estén suspendidas o de baja. Una pendiente nunca la tuvo y queda vacía: levantarle una suspensión la
 *   regresa a pendiente (alta-lavanderias-E15).
 * - id_municipios, opcional: el municipio del catálogo de la dirección, como en direcciones (C-238). La geocodificación
 *   lo necesita para no confundir, por ejemplo, Matamoros, Coahuila, con Matamoros, Tamaulipas (direcciones, design.md,
 *   decisión 10), y la edición lo necesita para volver a geocodificar. Las lavanderías de prueba no lo tienen; la API lo
 *   exige en el registro.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("alter table lavanderias alter column estado set default 'activa'");

        $this->llenarAprobadaEn();

        Schema::table('lavanderias', function (Blueprint $tabla) {
            // Nunca se borra un municipio: las direcciones y las lavanderías lo apuntan.
            $tabla->foreignId('id_municipios')->nullable()->index()
                ->constrained('municipios', 'id_municipios')->restrictOnDelete();
        });
    }

    /**
     * Las lavanderías sin aprobada_en que no están pendientes ya tenían alta: reciben la hora en que corre. Pública para
     * que su prueba la corra sobre filas sembradas (tests/Feature/AltaLavanderias/MigracionTest.php).
     */
    public function llenarAprobadaEn(): void
    {
        DB::table('lavanderias')
            ->whereNull('aprobada_en')
            ->where('estado', '<>', 'pendiente')
            ->update(['aprobada_en' => now()]);
    }

    /**
     * Solo hacia adelante (ADR-0016, regla 9).
     */
    public function down(): void
    {
        //
    }
};
