// Lo que comparten las pruebas de navegador de la medición (openspec/changes/medicion-de-la-landing/): interceptar las
// peticiones a otros servidores, servir el contenedor de etiquetas de prueba en lugar del de Google Tag Manager y
// anotar cada petición que sale hacia GA4 y hacia Meta Pixel. Ninguna petición sale a internet.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { CONFIGURACION_DE_PRUEBA } from './configuracion.js';

/** El identificador del contenedor de prueba que el servidor de las pruebas tiene configurado (MEDICION_GTM). */
export const CONTENEDOR = CONFIGURACION_DE_PRUEBA.MEDICION_GTM;

const CONTENEDOR_DE_PRUEBA = readFileSync(fileURLToPath(new URL('./contenedor-de-prueba.js', import.meta.url)), 'utf8');

/** Las tiendas de prueba a las que llevan los botones de descarga (LANDING_*_ENLACE). */
const TIENDAS = new URL(CONFIGURACION_DE_PRUEBA.LANDING_APP_STORE_ENLACE).host;

/**
 * Intercepta en el contexto del navegador toda petición a otro servidor y devuelve lo que anota:
 * - contenedores: el identificador de cada contenedor de GTM que pidió la página;
 * - ga4 y meta: cada petición hacia GA4 y hacia Meta Pixel, con sus parámetros y su cuerpo;
 * - ajenas: cualquier otra petición a otro servidor, que se corta.
 */
export async function interceptarMedicion(context, baseURL) {
    const propio = new URL(baseURL).host;
    const registro = { contenedores: [], ga4: [], meta: [], ajenas: [] };

    await context.route(
        (direccion) => direccion.host !== propio,
        async (ruta) => {
            const peticion = ruta.request();
            const direccion = new URL(peticion.url());
            const anotada = {
                url: peticion.url(),
                cuerpo: peticion.postData() ?? '',
                parametros: Object.fromEntries(direccion.searchParams),
            };

            if (direccion.host === 'www.googletagmanager.com' && direccion.pathname === '/gtm.js') {
                registro.contenedores.push(direccion.searchParams.get('id'));
                await ruta.fulfill({ status: 200, contentType: 'application/javascript', body: CONTENEDOR_DE_PRUEBA });
            } else if (direccion.host === 'www.google-analytics.com' && direccion.pathname === '/g/collect') {
                registro.ga4.push(anotada);
                await ruta.fulfill({ status: 204 });
            } else if (direccion.host === 'www.facebook.com' && direccion.pathname === '/tr/') {
                registro.meta.push(anotada);
                await ruta.fulfill({ status: 204 });
            } else if (direccion.host === TIENDAS) {
                await ruta.fulfill({ status: 200, contentType: 'text/html', body: '<!DOCTYPE html><title>Tienda de prueba</title>' });
            } else {
                registro.ajenas.push(peticion.url());
                await ruta.abort();
            }
        },
    );

    return registro;
}

/** Las peticiones hacia GA4 de un evento, por su nombre (en). */
export function eventosDeGa4(registro, evento) {
    return registro.ga4.filter((peticion) => peticion.parametros.en === evento);
}

/** Las peticiones hacia Meta Pixel de un evento, por su nombre (ev). */
export function eventosDeMeta(registro, evento) {
    return registro.meta.filter((peticion) => peticion.parametros.ev === evento);
}
