// El mapa interactivo de "Cobertura": dibuja cada zona activa con polígonos propios, se puede acercar y alejar, y al
// tocar o señalar una zona se ve su nombre; y en todo eso el navegador no hace ninguna petición a servicios de mapas
// de pago (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 7; ADR-0009, decisión 5). En Chromium, Firefox y
// WebKit, en escritorio y en teléfono.
//
// Las zonas son las de tests/Browser/Soporte/PortadaDePrueba.php: dos activas de una plaza activa, una inactiva y una
// de una plaza inactiva.
//
// Escenarios: landing-E12, landing-E13 y landing-E14 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 3.2
import { expect, test } from '@playwright/test';

import { CONFIGURACION_DE_PRUEBA } from '../Soporte/configuracion.js';
import { ANCHOS } from '../Soporte/portada.js';

const ZONAS_DIBUJADAS = ['Zona de prueba Centro', 'Zona de prueba Norte'];
const ZONAS_NO_DIBUJADAS = ['Zona de prueba inactiva', 'Zona de prueba de la plaza inactiva'];

/** Servicios de mapas de pago o de terceros: teselas, la API de mapas para web y la geocodificación. */
const SERVICIOS_DE_MAPAS = [
    /maps\.googleapis\.com/i,
    /maps\.gstatic\.com/i,
    /maps\.google\./i,
    /mapbox/i,
    /mapkit|apple-mapkit|maps\.apple\.com/i,
    /arcgis|virtualearth|bing\.com\/maps/i,
    /here\.com|hereapi/i,
    /tile\.|tiles\.|openstreetmap|mapquest|tomtom|geocod/i,
];

/**
 * El contenedor de medición de prueba que la portada pide por medicion-E01 (openspec/changes/medicion-de-la-landing/):
 * no es un servicio de mapas. La prueba lo sirve vacío, sin salir a internet, y lo deja fuera de los otros servidores.
 */
const CONTENEDOR_DE_MEDICION = `https://www.googletagmanager.com/gtm.js?id=${CONFIGURACION_DE_PRUEBA.MEDICION_GTM}`;

async function viewBox(page) {
    const valor = await page.locator('[data-mapa-lienzo]').getAttribute('viewBox');
    return valor.trim().split(/\s+/).map(Number);
}

for (const [ancho, viewport] of Object.entries(ANCHOS)) {
    test.describe(`con ancho de ${ancho}`, () => {
        test.use({ viewport });

        test('landing-E12 y landing-E14: el mapa dibuja cada zona activa de las plazas activas, y ninguna otra', async ({ page }) => {
            await page.goto('/');

            const mapa = page.locator('[data-seccion="cobertura"] [data-mapa]');
            await mapa.scrollIntoViewIfNeeded();
            await expect(mapa).toBeVisible();

            const zonas = mapa.locator('[data-zona]');
            await expect(zonas).toHaveCount(ZONAS_DIBUJADAS.length);
            expect(await zonas.evaluateAll((trazos) => trazos.map((trazo) => trazo.getAttribute('data-zona')))).toEqual(ZONAS_DIBUJADAS);

            for (const nombre of ZONAS_DIBUJADAS) {
                const zona = mapa.locator(`[data-zona="${nombre}"]`);
                await expect(zona).toBeVisible();
                const caja = await zona.boundingBox();
                expect(caja.width).toBeGreaterThan(10);
                expect(caja.height).toBeGreaterThan(10);
            }

            for (const nombre of ZONAS_NO_DIBUJADAS) {
                await expect(mapa.locator(`[data-zona="${nombre}"]`)).toHaveCount(0);
            }
        });

        test('landing-E12: el mapa se puede acercar y alejar', async ({ page }) => {
            await page.goto('/');
            await page.locator('[data-mapa]').scrollIntoViewIfNeeded();

            const inicial = await viewBox(page);
            const zona = page.locator('[data-zona="Zona de prueba Centro"]');
            const anchoInicial = (await zona.boundingBox()).width;

            await page.getByRole('button', { name: 'Acercar' }).click();
            const acercado = await viewBox(page);
            expect(acercado[2]).toBeLessThan(inicial[2]);
            expect(acercado[3]).toBeLessThan(inicial[3]);
            expect((await zona.boundingBox()).width).toBeGreaterThan(anchoInicial);

            await page.getByRole('button', { name: 'Alejar' }).click();
            const alejado = await viewBox(page);
            expect(alejado[2]).toBeCloseTo(inicial[2], 3);
            expect(alejado[3]).toBeCloseTo(inicial[3], 3);

            // No se aleja más allá del territorio completo.
            await page.getByRole('button', { name: 'Alejar' }).click();
            expect((await viewBox(page))[2]).toBeCloseTo(inicial[2], 3);
        });

        test('landing-E12: al señalar una zona, al tocarla o al llegar a ella con el teclado se ve su nombre', async ({ page }) => {
            await page.goto('/');
            await page.locator('[data-mapa]').scrollIntoViewIfNeeded();

            const nombre = page.locator('[data-mapa-nombre]');

            await page.locator('[data-zona="Zona de prueba Centro"]').hover();
            await expect(nombre).toHaveText('Zona de prueba Centro');

            await page.locator('[data-zona="Zona de prueba Norte"]').click();
            await expect(nombre).toHaveText('Zona de prueba Norte');

            await page.locator('[data-zona="Zona de prueba Centro"]').focus();
            await expect(nombre).toHaveText('Zona de prueba Centro');
        });
    });
}

test('landing-E13: al abrir la portada y usar el mapa, el navegador no pide nada a un servicio de mapas ni a otro servidor', async ({ page, baseURL }) => {
    const peticiones = [];
    page.on('request', (peticion) => peticiones.push(peticion.url()));
    await page.route(CONTENEDOR_DE_MEDICION, (ruta) => ruta.fulfill({ contentType: 'application/javascript', body: '' }));

    await page.goto('/');
    await page.waitForLoadState('networkidle');
    await page.locator('[data-mapa]').scrollIntoViewIfNeeded();
    await page.locator('[data-zona="Zona de prueba Centro"]').hover();
    await page.locator('[data-zona="Zona de prueba Norte"]').click();
    await page.getByRole('button', { name: 'Acercar' }).click();
    await page.getByRole('button', { name: 'Acercar' }).click();
    await page.getByRole('button', { name: 'Alejar' }).click();
    await page.waitForLoadState('networkidle');

    expect(peticiones.length).toBeGreaterThan(0);

    const aServiciosDeMapas = peticiones.filter((url) => SERVICIOS_DE_MAPAS.some((servicio) => servicio.test(new URL(url).host + new URL(url).pathname)));
    expect(aServiciosDeMapas).toEqual([]);

    const origen = new URL(baseURL).origin;
    const aOtrosServidores = peticiones.filter(
        (url) => !url.startsWith('data:') && url !== CONTENEDOR_DE_MEDICION && new URL(url).origin !== origen,
    );
    expect(aOtrosServidores).toEqual([]);
});
