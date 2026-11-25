// El contenedor de etiquetas de prueba de medicion-E03 a medicion-E05 (openspec/changes/medicion-de-la-landing/). Las
// pruebas de navegador lo sirven en lugar del gtm.js de Google Tag Manager, así que ninguna petición sale a internet.
//
// Hace con la capa de datos de la página lo que harían, en un contenedor de GTM, las etiquetas de GA4 y de Meta Pixel
// del plan de eventos (design.md, "Cómo quedó implementado"):
//
//   gtm.js, al abrir cada página           → page_view a GA4           y PageView a Meta Pixel
//   descarga, con la tienda                → descarga a GA4            y Descarga a Meta Pixel
//   formulario_enviado, con el formulario  → formulario_enviado a GA4  y Lead a Meta Pixel
//
// A propósito, manda con cada evento todo lo que la página puso en la capa de datos, salvo el nombre del evento: si la
// página pusiera ahí algo que la persona escribió, saldría en la petición y la prueba lo vería (medicion-E05). Las
// peticiones van con sendBeacon, como las de GA4, para que salgan aunque la página se vaya a la tienda.
//
// Los identificadores de GA4 y de Meta Pixel son inventados; no son de ninguna cuenta. Anota el estado de la página al
// cargarse el contenedor, para ver que se carga en diferido (medicion-E11).
(function () {
    var GA4 = 'https://www.google-analytics.com/g/collect';
    var META_PIXEL = 'https://www.facebook.com/tr/';
    var MEDICION_GA4 = 'G-PRUEBA0000';
    var PIXEL = '000000000000000';

    var EVENTOS = {
        'gtm.js': { ga4: 'page_view', meta: 'PageView' },
        descarga: { ga4: 'descarga', meta: 'Descarga' },
        formulario_enviado: { ga4: 'formulario_enviado', meta: 'Lead' },
    };

    window.__contenedorDePrueba = { estadoAlCargar: document.readyState };

    function enviar(base, parametros) {
        var direccion = base + '?' + new URLSearchParams(parametros).toString();

        if (!(navigator.sendBeacon && navigator.sendBeacon(direccion))) {
            fetch(direccion, { method: 'POST', keepalive: true, mode: 'no-cors' });
        }
    }

    function datosDelEvento(dato) {
        var datos = {};

        Object.keys(dato).forEach(function (clave) {
            if (clave !== 'event' && clave.indexOf('gtm.') !== 0) {
                datos[clave] = String(dato[clave]);
            }
        });

        return datos;
    }

    function procesar(dato) {
        if (!dato || typeof dato !== 'object' || !EVENTOS[dato.event]) {
            return;
        }

        var evento = EVENTOS[dato.event];
        var datos = datosDelEvento(dato);
        var ga4 = { v: '2', tid: MEDICION_GA4, en: evento.ga4, dl: location.href, dt: document.title };
        var meta = { id: PIXEL, ev: evento.meta, dl: location.href };

        Object.keys(datos).forEach(function (clave) {
            ga4['ep.' + clave] = datos[clave];
            meta['cd[' + clave + ']'] = datos[clave];
        });

        enviar(GA4, ga4);
        enviar(META_PIXEL, meta);
    }

    var capa = (window.dataLayer = window.dataLayer || []);
    capa.forEach(procesar);

    capa.push = function () {
        var resultado = Array.prototype.push.apply(capa, arguments);

        for (var i = 0; i < arguments.length; i++) {
            procesar(arguments[i]);
        }

        return resultado;
    };
})();
