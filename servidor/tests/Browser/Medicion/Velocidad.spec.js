// El contenedor de etiquetas se carga en diferido: la página termina de cargar antes de pedirlo, para que la medición no
// retrase lo que la persona ve en el teléfono (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.2; ADR-0011, criterio de
// reversión). La visita se sigue registrando (medicion-E03). En Chromium, Firefox y WebKit, con el ancho de teléfono.
//
// La puntuación de Lighthouse la mide servidor/lighthouserc.json en la integración continua.
//
// Escenario: medicion-E11 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/spec.md · Tarea 3.1
import { expect, test } from '@playwright/test';

import { eventosDeGa4, interceptarMedicion } from '../Soporte/medicion.js';
import { ANCHOS } from '../Soporte/portada.js';

test.use({ viewport: ANCHOS.telefono });

test('medicion-E11: el contenedor de etiquetas se pide después de que la página terminó de cargar, y la visita sale igual', async ({ page, context, baseURL }) => {
    const registro = await interceptarMedicion(context, baseURL);

    await page.goto('/');

    await expect.poll(() => eventosDeGa4(registro, 'page_view').length).toBe(1);
    const estado = await page.evaluate(() => window.__contenedorDePrueba?.estadoAlCargar);
    expect(estado, 'cuando corre el contenedor, la página ya cargó').toBe('complete');
    expect(registro.ajenas).toEqual([]);
});
