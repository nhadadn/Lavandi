<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * El sembrado de local y de prueba (ADR-0012).
 *
 * Corre con los eventos de los modelos: algunas reglas del negocio viven en ellos, como la zona horaria válida de la
 * plaza (plazas-E02), y un sembrado no se las salta. Por eso no usa WithoutModelEvents.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Las plazas ficticias y después sus lavanderías; cada sembrador no hace nada en producción.
        $this->call(PlazasDePruebaSeeder::class);
        $this->call(LavanderiasDePruebaSeeder::class);
        // Las cuentas para entrar a mano al panel y a la app; solo en local.
        $this->call(CuentasDeDesarrolloSeeder::class);
        // El repartidor de prueba, con pedidos ofrecidos a él (repartidor); solo en local.
        $this->call(RepartidorDeDesarrolloSeeder::class);
    }
}
