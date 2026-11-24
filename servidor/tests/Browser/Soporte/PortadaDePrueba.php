<?php

namespace Tests\Browser\Soporte;

use Illuminate\Database\Seeder;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;

/**
 * El contenido de prueba de la portada para las pruebas de navegador (playwright.config.js): lo siembra el servidor de
 * prueba sobre la base recién migrada. Todo es inventado; nada es de EL CLIENTE (DD-117).
 *
 * - Cobertura (landing-E12 a landing-E14): una plaza activa con dos zonas activas y una inactiva, y una plaza inactiva
 *   con una zona activa. Son cuadrados ficticios en el océano Pacífico (PlazasDePrueba), no del territorio inicial
 *   (DD-093, DD-094).
 */
class PortadaDePrueba extends Seeder
{
    use PlazasDePrueba;

    /** Las zonas que el mapa debe dibujar, en el orden en que salen. */
    public const ZONAS_DIBUJADAS = ['Zona de prueba Centro', 'Zona de prueba Norte'];

    public function run(): void
    {
        $activa = $this->plazaDePrueba('Plaza de prueba 1');
        $this->zonaDePrueba($activa, 'Zona de prueba Centro', $this->cuadrado(-120, 10));
        $this->zonaDePrueba($activa, 'Zona de prueba Norte', $this->cuadrado(-120, 11));
        $this->zonaDePrueba($activa, 'Zona de prueba inactiva', $this->cuadrado(-119, 10), activa: false);

        $inactiva = $this->plazaDePrueba('Plaza de prueba inactiva', activa: false);
        $this->zonaDePrueba($inactiva, 'Zona de prueba de la plaza inactiva', $this->cuadrado(-119, 11));
    }
}
