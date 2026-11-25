<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las cuatro primeras tablas del negocio: roles, usuarios, rol_usuario y cuentas_externas
 * (openspec/changes/registro-e-inicio-de-sesion/design.md, "Tablas y migración"; ADR-0016).
 *
 * Ninguna lleva id_plazas: la persona no pertenece a una plaza, su domicilio sí (modelo-multi-plaza.md). Las cuatro
 * están en la lista sin_plaza de config/modelo.php, y rol_usuario en la de enlace.
 */
return new class extends Migration
{
    /**
     * Los roles de la plataforma, uno por módulo de la app (NSV-2026-001-v8.pdf:16-19:ANEXO A, Sección A.3).
     */
    private const ROLES = [
        'usuario' => 'Usuario',
        'lavanderia' => 'Lavandería',
        'repartidor' => 'Repartidor',
        'administracion' => 'Administración',
    ];

    public function up(): void
    {
        Schema::create('roles', function (Blueprint $tabla) {
            $tabla->id('id_roles');
            $tabla->string('codigo')->unique();
            $tabla->string('nombre');
            $tabla->timestampsTz();
        });

        $ahora = now();

        DB::table('roles')->insert(array_map(
            fn (string $codigo, string $nombre): array => [
                'codigo' => $codigo,
                'nombre' => $nombre,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ],
            array_keys(self::ROLES),
            self::ROLES,
        ));

        Schema::create('usuarios', function (Blueprint $tabla) {
            $tabla->id('id_usuarios');
            $tabla->ulid('id_publico')->unique();
            // Vacío hasta el perfil (C-225): el registro no pide el nombre.
            $tabla->string('nombre')->nullable();
            $tabla->string('correo');
            // Opcional: quien entra con Google o con Apple no tiene contraseña. Si existe, va con hash.
            $tabla->string('password')->nullable();
            $tabla->string('telefono')->nullable();
            $tabla->string('foto_ruta')->nullable();
            $tabla->string('estado')->default('activo');
            $tabla->timestampTz('bloqueado_en')->nullable();
            $tabla->timestampTz('terminos_aceptados_en')->nullable();
            $tabla->timestampsTz();
            $tabla->softDeletesTz();
        });

        // El correo es único sin distinguir mayúsculas, también entre las cuentas borradas.
        DB::statement('create unique index usuarios_correo_unique on usuarios (lower(correo))');
        DB::statement("alter table usuarios add constraint usuarios_estado_check check (estado in ('activo', 'bloqueado'))");

        Schema::create('rol_usuario', function (Blueprint $tabla) {
            $tabla->foreignId('id_usuarios')->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->foreignId('id_roles')->constrained('roles', 'id_roles')->restrictOnDelete();
            $tabla->primary(['id_usuarios', 'id_roles']);
            // La llave compuesta solo cubre id_usuarios.
            $tabla->index('id_roles');
        });

        Schema::create('cuentas_externas', function (Blueprint $tabla) {
            $tabla->id('id_cuentas_externas');
            $tabla->foreignId('id_usuarios')->index()->constrained('usuarios', 'id_usuarios')->restrictOnDelete();
            $tabla->string('proveedor');
            // El identificador estable de la persona en el proveedor: el claim sub del token.
            $tabla->string('sujeto');
            $tabla->string('correo');
            $tabla->timestampsTz();
            $tabla->unique(['proveedor', 'sujeto']);
        });

        DB::statement(
            "alter table cuentas_externas add constraint cuentas_externas_proveedor_check check (proveedor in ('google', 'apple'))",
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
