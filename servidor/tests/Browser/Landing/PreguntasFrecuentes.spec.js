// "Preguntas frecuentes" en un acordeón: las siete preguntas de prueba empiezan plegadas; al tocar una se despliega su
// respuesta y al tocarla otra vez se pliega; lo mismo con el teclado, con el foco visible (NSV-2026-001-v8.pdf:15:
// ANEXO A, Sección A.2, sección 9; componentes.md, "Foco visible siempre"). En Chromium, Firefox y WebKit.
//
// Las preguntas son las de tests/Browser/Soporte/preguntas-frecuentes-de-prueba.json (configuracion.js).
//
// Escenario: landing-E20 · Spec: openspec/changes/landing/specs/landing/spec.md · Tarea 4.3
import { expect, test } from '@playwright/test';

const PREGUNTAS = '[data-seccion="preguntas-frecuentes"] details[data-pregunta]';

test('landing-E20: las siete preguntas se ven con sus respuestas plegadas', async ({ page }) => {
    await page.goto('/');

    const preguntas = page.locator(PREGUNTAS);
    await expect(preguntas).toHaveCount(7);

    for (let i = 0; i < 7; i++) {
        const pregunta = preguntas.nth(i);
        await expect(pregunta.locator('summary')).toHaveText(`¿Pregunta de prueba ${i + 1}?`);
        await expect(pregunta.locator('summary')).toBeVisible();
        await expect(pregunta.locator('[data-respuesta]')).toBeHidden();
    }
});

test('landing-E20: al tocar una pregunta se despliega su respuesta, y al tocarla otra vez se pliega', async ({ page }) => {
    await page.goto('/');

    const pregunta = page.locator(PREGUNTAS).nth(2);
    const respuesta = pregunta.locator('[data-respuesta]');

    await pregunta.locator('summary').click();
    await expect(respuesta).toBeVisible();
    await expect(respuesta).toHaveText('Respuesta de prueba 3.');
    await expect(page.locator(PREGUNTAS).nth(1).locator('[data-respuesta]')).toBeHidden();

    await pregunta.locator('summary').click();
    await expect(respuesta).toBeHidden();
});

test('landing-E20: con el teclado se llega a la pregunta con el foco visible, y Enter y Espacio la despliegan y la pliegan', async ({ page }) => {
    await page.goto('/');

    const primera = page.locator(PREGUNTAS).nth(0).locator('summary');
    const segunda = page.locator(PREGUNTAS).nth(1);
    const respuesta = segunda.locator('[data-respuesta]');

    await primera.focus();
    await page.keyboard.press('Tab');
    await expect(segunda.locator('summary')).toBeFocused();

    const foco = await segunda.locator('summary').evaluate((titulo) => {
        const estilo = getComputedStyle(titulo);
        return { visible: titulo.matches(':focus-visible'), estilo: estilo.outlineStyle, grosor: estilo.outlineWidth, color: estilo.outlineColor };
    });
    expect(foco).toEqual({ visible: true, estilo: 'solid', grosor: '3px', color: 'rgb(41, 120, 153)' });

    await page.keyboard.press('Enter');
    await expect(respuesta).toBeVisible();
    await page.keyboard.press('Enter');
    await expect(respuesta).toBeHidden();

    await page.keyboard.press('Space');
    await expect(respuesta).toBeVisible();
    await page.keyboard.press('Space');
    await expect(respuesta).toBeHidden();
});
