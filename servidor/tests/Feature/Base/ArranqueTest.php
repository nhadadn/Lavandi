<?php

namespace Tests\Feature\Base;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * El servidor responde y la base de datos nace desde cero con PostGIS.
 *
 * Escenarios: base-E01 y base-E02 · Spec: openspec/changes/base-tecnica/specs/base/spec.md
 */
class ArranqueTest extends TestCase
{
    /**
     * Las tablas que deja este cambio: las del framework, la de Sanctum y la de PostGIS (design.md, "Tablas y
     * migración"). Las del negocio llegan con sus cambios, desde registro-e-inicio-de-sesion, y las revisa la prueba
     * de esquema de modelo-de-datos. La tabla users no se crea nunca.
     */
    private const TABLAS_DEL_FRAMEWORK = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'spatial_ref_sys',
    ];

    #[TestDox('base-E01: la API responde en local')]
    public function test_base_e01_la_api_responde_en_local(): void
    {
        $this->get('/up')->assertOk();
    }

    #[TestDox('base-E01: la raíz no sirve la página de bienvenida de la plantilla de Laravel: sirve la portada de la landing')]
    public function test_base_e01_la_raiz_no_sirve_la_pagina_de_bienvenida_de_la_plantilla_de_laravel(): void
    {
        // La raíz la ocupa la portada del cambio landing (ADR-0011; landing-E01). Antes respondía 404.
        $this->withoutVite();
        $this->get('/')->assertOk()->assertViewIs('portada.index');
        $this->assertFalse(view()->exists('welcome'), 'La vista de bienvenida de la plantilla de Laravel no se usa.');

        // La salud sigue en /up.
        $this->get('/up')->assertOk();
    }

    #[TestDox('base-E02: las migraciones corren desde cero y dejan PostGIS listo')]
    public function test_base_e02_las_migraciones_corren_desde_cero_y_dejan_postgis_listo(): void
    {
        $this->dejarLaBaseVacia();

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful()->run();

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertMatchesRegularExpression('/^\d+\.\d+/', (string) DB::scalar('select postgis_version()'));
        $this->assertStringContainsString(
            'postgis',
            (string) DB::table('migrations')->orderBy('id')->value('migration'),
            'La primera migración debe ser la que habilita PostGIS (ADR-0004).',
        );
    }

    #[TestDox('base-E02: las migraciones crean las tablas del framework y no la tabla users')]
    public function test_base_e02_las_migraciones_crean_las_tablas_del_framework_y_no_la_tabla_users(): void
    {
        $this->dejarLaBaseVacia();

        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful()->run();

        // Se lee de information_schema y no de Schema::getTableListing(), que omite las tablas de una extensión,
        // como spatial_ref_sys de PostGIS.
        $tablas = DB::table('information_schema.tables')
            ->whereRaw('table_schema = current_schema()')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $this->assertSame([], array_values(array_diff(self::TABLAS_DEL_FRAMEWORK, $tablas)));
        $this->assertNotContains('users', $tablas);
    }

    #[TestDox('base-E02: no existen el modelo User ni su fábrica')]
    public function test_base_e02_no_existen_el_modelo_user_ni_su_fabrica(): void
    {
        $this->assertFalse(class_exists('App\\Models\\User'), 'La cuenta será App\\Models\\Usuario (ADR-0016).');
        $this->assertFalse(class_exists('Database\\Factories\\UserFactory'));
    }

    /**
     * Deja la base como recién creada: sin PostGIS. Las tablas que queden las borra migrate:fresh.
     */
    private function dejarLaBaseVacia(): void
    {
        DB::statement('DROP EXTENSION IF EXISTS postgis CASCADE');

        $this->assertFalse(
            (bool) DB::scalar("select exists (select 1 from pg_extension where extname = 'postgis')"),
            'La base de la prueba debe arrancar sin PostGIS.',
        );
    }
}
