// La visita, el toque en un botón de descarga y el envío completo de un formulario salen hacia Google Analytics 4 y
// Meta Pixel, sin los datos que la persona escribió, en Chromium, Firefox y WebKit (NSV-2026-001-v8.pdf:15:ANEXO A,
// Sección A.2; NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.2, A42-2; NSV-2026-001-v8.pdf:10:Cláusula X).
//
// Con un contenedor de prueba (Soporte/contenedor-de-prueba.js) en lugar del de la cuenta de GTM: las pruebas
// interceptan las peticiones hacia GA4 y hacia Meta Pixel y ninguna sale a internet. Que los eventos aparezcan en los
// paneles de EL CLIENTE es medicion-E06 (tarea 1.3, bloqueada por DD-118 y DD-023).
//
// Escenarios: medicion-E03, medicion-E04 y medicion-E05 · Spec: openspec/changes/medicion-de-la-landing/specs/medicion/
// spec.md · Tarea 1.2
import { expect, test } from '@playwright/test';

import { CONTENEDOR, eventosDeGa4, eventosDeMeta, interceptarMedicion } from '../Soporte/medicion.js';

/** Lo que escribe la persona en cada formulario: inventado, y fácil de buscar en las peticiones. */
const ESCRITO = {
    interes: {
        nombre: 'Nombre Secreto De Prueba',
        lavanderia: 'Lavanderia Secreta De Prueba',
        correo: 'correo.secreto@ejemplo.test',
    },
    contacto: {
        nombre: 'Nombre Secreto De Prueba',
        correo: 'correo.secreto@ejemplo.test',
        mensaje: 'Mensaje secreto de prueba',
    },
};

/** Cada forma en que un texto escrito podría viajar en una petición: tal cual, codificado en la dirección o en un formulario. */
function formasDe(texto) {
    return [texto, encodeURIComponent(texto), new URLSearchParams({ x: texto }).toString().slice(2)];
}

async function llenar(page, formulario, campos) {
    for (const [campo, valor] of Object.entries(campos)) {
        await page.locator(`#campo-${formulario}-${campo}`).fill(valor);
    }
}

async function enviar(page, formulario) {
    await Promise.all([
        page.waitForURL((direccion) => direccion.pathname === '/' && direccion.hash !== ''),
        page.locator(`[data-formulario="${formulario}"] button[type="submit"]`).click(),
    ]);
}

let registro;

test.beforeEach(async ({ context, baseURL }) => {
    registro = await interceptarMedicion(context, baseURL);
});

test.afterEach(() => {
    expect(registro.ajenas, 'ninguna petición a otro servidor fuera de la medición').toEqual([]);
});

test('medicion-E03: al abrir la portada, la página pide el contenedor configurado una sola vez y sale una vista de página hacia GA4 y otra hacia Meta Pixel', async ({ page }) => {
    await page.goto('/');

    await expect.poll(() => eventosDeGa4(registro, 'page_view').length, 'vista de página hacia GA4').toBe(1);
    await expect.poll(() => eventosDeMeta(registro, 'PageView').length, 'vista de página hacia Meta Pixel').toBe(1);
    expect(registro.contenedores).toEqual([CONTENEDOR]);

    const [vista] = eventosDeGa4(registro, 'page_view');
    expect(new URL(vista.parametros.dl).pathname).toBe('/');
});

for (const [tienda, nombre] of [['app_store', 'App Store'], ['google_play', 'Google Play']]) {
    test(`medicion-E04: al tocar el botón de ${nombre}, sale un evento de descarga hacia GA4 y hacia Meta Pixel que dice qué tienda fue`, async ({ page }) => {
        await page.goto('/');
        await expect.poll(() => eventosDeGa4(registro, 'page_view').length).toBe(1);

        await page.locator(`[data-tienda="${tienda}"]`).click();

        await expect.poll(() => eventosDeGa4(registro, 'descarga').map((evento) => evento.parametros['ep.tienda'])).toEqual([tienda]);
        await expect.poll(() => eventosDeMeta(registro, 'Descarga').map((evento) => evento.parametros['cd[tienda]'])).toEqual([tienda]);
    });
}

for (const formulario of ['interes', 'contacto']) {
    test(`medicion-E05: al enviar completo el formulario de ${formulario}, sale un evento hacia GA4 y hacia Meta Pixel que dice qué formulario fue, sin nada de lo que la persona escribió`, async ({ page }) => {
        await page.goto('/');
        await llenar(page, formulario, ESCRITO[formulario]);
        await enviar(page, formulario);

        await expect.poll(() => eventosDeGa4(registro, 'formulario_enviado').map((evento) => evento.parametros['ep.formulario'])).toEqual([formulario]);
        await expect.poll(() => eventosDeMeta(registro, 'Lead').map((evento) => evento.parametros['cd[formulario]'])).toEqual([formulario]);

        const capaDeDatos = await page.evaluate(() => JSON.stringify(window.dataLayer));
        for (const valor of Object.values(ESCRITO[formulario])) {
            for (const forma of formasDe(valor)) {
                for (const peticion of [...registro.ga4, ...registro.meta]) {
                    expect(peticion.url, `la petición no lleva «${valor}»`).not.toContain(forma);
                    expect(peticion.cuerpo, `la petición no lleva «${valor}»`).not.toContain(forma);
                }
                expect(capaDeDatos, `la capa de datos no lleva «${valor}»`).not.toContain(forma);
            }
        }
    });

    test(`medicion-E05: un envío del formulario de ${formulario} rechazado por incompleto no genera evento`, async ({ page }) => {
        await page.goto('/');
        const [primero, ...resto] = Object.entries(ESCRITO[formulario]);
        await llenar(page, formulario, Object.fromEntries(resto));
        await enviar(page, formulario);

        await expect(page.locator(`[data-formulario="${formulario}"] [data-error-de="${primero[0]}"]`)).toBeVisible();
        // La portada de vuelta ya cargó el contenedor: su vista de página salió.
        await expect.poll(() => eventosDeGa4(registro, 'page_view').length).toBe(2);
        await expect.poll(() => eventosDeMeta(registro, 'PageView').length).toBe(2);

        expect(eventosDeGa4(registro, 'formulario_enviado')).toEqual([]);
        expect(eventosDeMeta(registro, 'Lead')).toEqual([]);
    });
}
