// Los iconos de los pilares para usuarios están animados y, con la preferencia de movimiento reducido, se quedan
// quietos (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 3; vault/design-system/componentes.md, regla
// transversal "Movimiento reducido"). En Chromium, Firefox y WebKit.
//
// Escenarios: landing-E06 (el icono animado) y landing-E07 · Spec: openspec/changes/landing/specs/landing/spec.md ·
// Tarea 2.2
import { expect, test } from '@playwright/test';

/** El nombre de la animación de cada icono de los pilares para usuarios, como la calcula el navegador. */
async function animacionesDeLosIconos(page) {
    const iconos = page.locator('[data-seccion="beneficios-usuarios"] [data-pilar] [data-icono-animado]');
    await expect(iconos).toHaveCount(3);

    return iconos.evaluateAll((elementos) => elementos.map((icono) => getComputedStyle(icono).animationName));
}

/** Las cuatro duraciones de los tokens, en milisegundos: la hoja compilada puede escribir 0ms como 0s. */
async function duracionesEnMilisegundos(page) {
    return page.evaluate(() =>
        ['instantanea', 'rapida', 'base', 'lenta'].map((nombre) => {
            const valor = getComputedStyle(document.documentElement).getPropertyValue(`--ds-duracion-${nombre}`).trim();
            const [, numero, unidad] = /^(-?\d*\.?\d+)(ms|s)$/.exec(valor) ?? [];
            return unidad === 's' ? Number(numero) * 1000 : Number(numero);
        }),
    );
}

test.describe('landing-E06: sin preferencia de movimiento reducido', () => {
    test.beforeEach(async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'no-preference' });
    });

    test('landing-E06: cada icono de los pilares está animado', async ({ page }) => {
        await page.goto('/');

        const animaciones = await animacionesDeLosIconos(page);
        expect(animaciones).toHaveLength(3);
        for (const animacion of animaciones) {
            expect(animacion).not.toBe('none');
        }
        expect(await duracionesEnMilisegundos(page)).toEqual([0, 120, 200, 320]);
    });
});

test.describe('landing-E07: con la preferencia de movimiento reducido activa', () => {
    test.beforeEach(async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
    });

    test('landing-E07: los iconos de los pilares se muestran sin animación', async ({ page }) => {
        await page.goto('/');

        expect(await animacionesDeLosIconos(page)).toEqual(['none', 'none', 'none']);

        const enCurso = await page.evaluate(() => document.getAnimations().length);
        expect(enCurso).toBe(0);
    });

    test('landing-E07: las duraciones de los tokens valen 0', async ({ page }) => {
        await page.goto('/');

        expect(await duracionesEnMilisegundos(page)).toEqual([0, 0, 0, 0]);
    });
});
