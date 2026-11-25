// Las 12 secciones del A.2 en la portada pública, en su orden, visibles y sin salirse del ancho de la pantalla, en
// Chromium, Firefox y WebKit, con ancho de escritorio y de teléfono (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2 y
// NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-1). Con el contenido de prueba: la acreditación con el contenido de
// EL CLIENTE es landing-E03 (tarea 7.1, bloqueada por DD-117 y DD-023).
//
// Escenarios: landing-E01 y landing-E02 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 1.1
import { expect, test } from '@playwright/test';

import { ANCHOS, SECCIONES } from '../Soporte/portada.js';

const ESCENARIO = { escritorio: 'landing-E01', telefono: 'landing-E02' };

for (const [ancho, viewport] of Object.entries(ANCHOS)) {
    test.describe(`${ESCENARIO[ancho]}: con ancho de ${ancho}`, () => {
        test.use({ viewport });

        test(`${ESCENARIO[ancho]}: las 12 secciones existen, son visibles y siguen el orden del A.2`, async ({ page }) => {
            const respuesta = await page.goto('/');
            expect(respuesta?.status()).toBe(200);

            const enOrden = await page.locator('[data-seccion]').evaluateAll((secciones) =>
                secciones.map((seccion) => seccion.getAttribute('data-seccion')),
            );
            expect(enOrden).toEqual(SECCIONES);

            for (const seccion of SECCIONES) {
                const elemento = page.locator(`[data-seccion="${seccion}"]`);
                await elemento.scrollIntoViewIfNeeded();
                await expect(elemento, `la sección ${seccion} se ve`).toBeVisible();
            }
        });

        test(`${ESCENARIO[ancho]}: las secciones van una debajo de otra, en el mismo orden`, async ({ page }) => {
            await page.goto('/');

            const cajas = await page.locator('[data-seccion]').evaluateAll((secciones) =>
                secciones.map((seccion) => {
                    const caja = seccion.getBoundingClientRect();
                    return { seccion: seccion.getAttribute('data-seccion'), arriba: caja.top + window.scrollY, abajo: caja.bottom + window.scrollY };
                }),
            );

            for (let i = 1; i < cajas.length; i++) {
                expect(cajas[i].arriba, `${cajas[i].seccion} empieza debajo de ${cajas[i - 1].seccion}`).toBeGreaterThanOrEqual(cajas[i - 1].abajo - 1);
            }
        });

        test(`${ESCENARIO[ancho]}: la página no se desplaza a lo ancho y ningún elemento se sale de la pantalla`, async ({ page }) => {
            await page.goto('/');

            const desborde = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
            expect(desborde).toBe(0);

            const fuera = await page.evaluate(() => {
                const anchoVisible = document.documentElement.clientWidth;
                return [...document.querySelectorAll('body *')]
                    .filter((elemento) => !elemento.closest('svg') || elemento.tagName.toLowerCase() === 'svg')
                    .filter((elemento) => {
                        const caja = elemento.getBoundingClientRect();
                        return caja.width > 0 && caja.height > 0 && (caja.left < -0.5 || caja.right > anchoVisible + 0.5);
                    })
                    .map((elemento) => `${elemento.tagName.toLowerCase()}.${elemento.className}`);
            });
            expect(fuera).toEqual([]);
        });

        test(`${ESCENARIO[ancho]}: la portada usa las variables de los tokens`, async ({ page }) => {
            await page.goto('/');

            const fondo = await page.evaluate(() => getComputedStyle(document.body).backgroundColor);
            const variable = await page.evaluate(() =>
                getComputedStyle(document.documentElement).getPropertyValue('--ds-color-semantico-fondo').trim(),
            );

            // Hueso, el fondo de color.semantico.claro de tokens.json.
            expect(variable.toUpperCase()).toBe('#F7F4EF');
            expect(fondo).toBe('rgb(247, 244, 239)');
        });
    });
}
