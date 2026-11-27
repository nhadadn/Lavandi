// Spec: openspec/changes/pago/specs/pago/spec.md, pago-E02 (tarea 4.1). La pantalla de pago del pedido,
// /pedido/pago/[id], que abre "Confirmar y pagar" desde el resumen: al tocar "Pagar con tarjeta", la app pide el checkout
// a servidor/ mandando solo el pedido y el método (POST /api/pagos/checkout) y abre la URL que recibe en la vista de
// navegador del sistema, dentro de la app, con openAuthSessionAsync de expo-web-browser y la dirección de regreso; en
// iPhone, con preferEphemeralSession (decisión 32; adenda del 2026-11-22 de ADR-0010). Ninguna pantalla de la app tiene
// campos de número de tarjeta, vencimiento o código de seguridad (SEG-4): la tarjeta se escribe en el checkout de la
// pasarela.
//
// Regresar del checkout no confirma nada (pago-E07): con cualquier regreso, la app pasa a la espera del cobro, que
// consulta el servidor (pago-E08, procesando-pago.test.tsx). En Android, el regreso llega además como un enlace a la app;
// no abre otra pantalla (app/+native-intent.tsx).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado con el
// "Contrato de la API" de openspec/changes/pago/design.md (__tests__/apoyo/pago.ts). La vista real de cada sistema y su
// regreso se prueban en un teléfono.
import { readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Linking } from 'react-native';

// La función con que expo-router decide a dónde lleva un enlace que abre la app.
import { redirectSystemPath } from '../../app/+native-intent';
import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrir,
  COBRO,
  enlaceDeRegreso,
  limpiarElServidor,
  navegador,
  PEDIDO,
  PLATAFORMAS,
  prepararElServidor,
  REGRESO,
  respuesta,
  RUTA_DE_LA_ESPERA,
  RUTA_DEL_PAGO,
  URL_DEL_CHECKOUT,
  type Plataforma,
  type ServidorDelPago,
} from '../apoyo/pago';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const BASE = 'http://api.prueba.invalid/pago-checkout';

let servidor: ServidorDelPago;

beforeEach(() => {
  servidor = prepararElServidor(BASE);
});

afterEach(() => {
  limpiarElServidor();
  jest.restoreAllMocks();
});

async function abrirElPago(plataforma: Plataforma) {
  await abrir(plataforma, RUTA_DEL_PAGO);
  await screen.findByRole('button', { name: 'Pagar con tarjeta' });
}

async function pagarConTarjeta() {
  await fireEvent.press(screen.getByRole('button', { name: 'Pagar con tarjeta' }));
}

describe('pago-E02: la app pide el checkout y lo abre dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'pago-E02 — (%s) la pantalla de pago lleva el encabezado "Pago", dice dónde se escribe la tarjeta y ofrece pagar con tarjeta',
    async (plataforma) => {
      await abrirElPago(plataforma);

      expect(screen.getByRole('header', { name: 'Pago' })).toBeOnTheScreen();
      expect(screen.getByRole('header', { name: 'Paga tu pedido' })).toBeOnTheScreen();
      expect(
        screen.getByText(
          'Pagas con tarjeta de crédito o débito en la página segura de pago, que se abre aquí mismo, sin salir de la app.',
        ),
      ).toBeOnTheScreen();
      expect(screen.getByText('La app nunca ve ni guarda los datos de tu tarjeta.')).toBeOnTheScreen();
      // Nada se pide al servidor hasta tocar el botón.
      expect(servidor.pedidosDeCheckout()).toEqual([]);
    },
  );

  test.each(PLATAFORMAS)(
    'pago-E02 — (%s) al tocar "Pagar con tarjeta", pide el checkout mandando solo el pedido y el método, con la sesión',
    async (plataforma) => {
      await abrirElPago(plataforma);

      await pagarConTarjeta();

      await waitFor(() => expect(servidor.pedidosDeCheckout()).toHaveLength(1));
      expect(servidor.pedidosDeCheckout()).toEqual([{ pedido: PEDIDO, metodo: 'tarjeta' }]);
      const [peticion] = servidor.peticiones().filter(({ ruta }) => ruta === '/pagos/checkout');
      expect(peticion.autorizacion).toBe('Bearer token-del-pago');
    },
  );

  test('pago-E02 — (ios) abre la URL del checkout con openAuthSessionAsync, la dirección de regreso y preferEphemeralSession', async () => {
    const abrirFuera = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
    await abrirElPago('ios');

    await pagarConTarjeta();

    await waitFor(() => expect(navegador.openAuthSessionAsync).toHaveBeenCalledTimes(1));
    expect(navegador.openAuthSessionAsync.mock.calls[0]).toEqual([
      URL_DEL_CHECKOUT,
      REGRESO,
      { preferEphemeralSession: true },
    ]);
    // Dentro de la app: ni Safari ni otra app.
    expect(abrirFuera).not.toHaveBeenCalled();
    expect(navegador.openBrowserAsync).not.toHaveBeenCalled();
  });

  test('pago-E02 — (android) abre la URL del checkout con openAuthSessionAsync y la dirección de regreso, sin la opción de iPhone', async () => {
    const abrirFuera = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
    await abrirElPago('android');

    await pagarConTarjeta();

    await waitFor(() => expect(navegador.openAuthSessionAsync).toHaveBeenCalledTimes(1));
    expect(navegador.openAuthSessionAsync.mock.calls[0]).toEqual([URL_DEL_CHECKOUT, REGRESO]);
    // Dentro de la app: ni Chrome ni otra app.
    expect(abrirFuera).not.toHaveBeenCalled();
    expect(navegador.openBrowserAsync).not.toHaveBeenCalled();
  });

  test.each([
    ['ios', 'el regreso de éxito', { type: 'success', url: enlaceDeRegreso('exito') }],
    ['ios', 'el regreso de error', { type: 'success', url: enlaceDeRegreso('error') }],
    ['ios', 'la vista cerrada sin regreso', { type: 'cancel' }],
    ['android', 'el regreso de éxito', { type: 'success', url: enlaceDeRegreso('exito') }],
    ['android', 'el regreso de error', { type: 'success', url: enlaceDeRegreso('error') }],
    ['android', 'la vista cerrada sin regreso', { type: 'dismiss' }],
  ] as const)(
    'pago-E02 y pago-E07 — (%s) con %s, la app pasa a la espera del cobro y no le dice al servidor que ya pagó',
    async (plataforma, _caso, resultado) => {
      navegador.__alTerminarLaSesion(resultado as Parameters<typeof navegador.__alTerminarLaSesion>[0]);
      await abrirElPago(plataforma);

      await pagarConTarjeta();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      // La única petición que cambia algo fue pedir el checkout: el regreso no se le avisa al servidor (pago-E07).
      const cambios = servidor.peticiones().filter(({ metodo }) => metodo !== 'GET');
      expect(cambios.map(({ metodo, ruta }) => `${metodo} ${ruta}`)).toEqual(['POST /pagos/checkout']);
    },
  );

  test('pago-E02 — mientras pide el checkout, el botón queda ocupado y un segundo toque no pide otro', async () => {
    let soltar: (valor: unknown) => void = () => {};
    servidor.interceptar = ({ ruta }) =>
      ruta === '/pagos/checkout' ? new Promise((resolver) => (soltar = resolver)) : undefined;
    await abrirElPago('android');

    await pagarConTarjeta();
    await waitFor(() => expect(servidor.pedidosDeCheckout()).toHaveLength(1));
    const boton = screen.getByRole('button', { name: 'Pagar con tarjeta' });
    expect(boton).toBeBusy();
    await fireEvent.press(boton);

    servidor.interceptar = null;
    soltar(
      respuesta(200, {
        checkout: { url: URL_DEL_CHECKOUT, regreso: REGRESO },
        pago: { id_publico: COBRO, pedido: PEDIDO, tipo: 'cobro', metodo: 'tarjeta', estado: 'pendiente', monto: '180.00', pagado_en: null },
      }),
    );
    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
    expect(servidor.pedidosDeCheckout()).toHaveLength(1);
    expect(navegador.openAuthSessionAsync).toHaveBeenCalledTimes(1);
  });

  test.each(PLATAFORMAS)(
    'pago-E02 — (%s) si el servidor no tiene dirección de regreso, abre el checkout en la vista de navegador sin esperar el enlace y pasa a la espera',
    async (plataforma) => {
      servidor.regreso = null;
      await abrirElPago(plataforma);

      await pagarConTarjeta();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(navegador.openBrowserAsync).toHaveBeenCalledWith(URL_DEL_CHECKOUT);
      expect(navegador.openAuthSessionAsync).not.toHaveBeenCalled();
    },
  );
});

describe('pago-E02: cuando el checkout no se abre', () => {
  // Las respuestas del contrato en que servidor/ no da el checkout: la app dice su mensaje y no abre nada.
  test.each([
    ['el pedido no tiene cobro (409)', () => (servidor.cobro = null), 'El cobro de este pedido todavía no está listo.'],
    ['el pedido ya está pagado (409)', () => (servidor.cobro = 'pagado'), 'Este pedido ya está pagado.'],
    ['el cobro ya no se puede pagar (409)', () => (servidor.cobro = 'vencido'), 'Este cobro ya no se puede pagar.'],
    [
      'el servidor no tiene pasarela (503)',
      () =>
        (servidor.interceptar = ({ ruta }) =>
          ruta === '/pagos/checkout' ? respuesta(503, { message: 'El pago no está disponible por ahora.' }) : undefined),
      'El pago no está disponible por ahora.',
    ],
  ])('pago-E02 — si %s, dice el mensaje del servidor y no abre el checkout', async (_caso, preparar, mensaje) => {
    preparar();
    await abrirElPago('ios');

    await pagarConTarjeta();

    expect(await screen.findByRole('alert')).toHaveTextContent(mensaje);
    expect(navegador.openAuthSessionAsync).not.toHaveBeenCalled();
    expect(screen).toHavePathname(RUTA_DEL_PAGO);
    expect(screen.getByRole('button', { name: 'Pagar con tarjeta' })).not.toBeBusy();
  });

  test('pago-E02 — si el pedido no existe o es de otra cuenta (404), dice que no lo encontró', async () => {
    servidor.interceptar = ({ ruta }) =>
      ruta === '/pagos/checkout' ? respuesta(404, { message: 'No existe el pedido.' }) : undefined;
    await abrirElPago('android');

    await pagarConTarjeta();

    expect(await screen.findByRole('alert')).toHaveTextContent('No encontramos este pedido.');
    expect(navegador.openAuthSessionAsync).not.toHaveBeenCalled();
  });

  // Sin escenario propio: sin conexión o con el servidor caído, los mensajes generales de la app, como el resumen.
  test.each([
    ['sin conexión', MENSAJES.sinConexion, () => Promise.reject(new TypeError('Network request failed'))],
    ['una falla del servidor (500)', MENSAJES.servidor, () => respuesta(500, { message: 'Server Error' })],
    ['una respuesta fuera del contrato', MENSAJES.servidor, () => respuesta(200, { checkout: { url: 5 } })],
  ])('pago-E02 — con %s, lo dice con el mensaje de la app y no abre el checkout', async (_caso, mensaje, contestar) => {
    servidor.interceptar = ({ ruta }) => (ruta === '/pagos/checkout' ? contestar() : undefined);
    await abrirElPago('ios');

    await pagarConTarjeta();

    expect(await screen.findByRole('alert')).toHaveTextContent(mensaje);
    expect(navegador.openAuthSessionAsync).not.toHaveBeenCalled();
  });

  test('pago-E02 — si la vista de navegador no se puede abrir, lo dice y no pasa a la espera', async () => {
    navegador.__alTerminarLaSesion(() => Promise.reject(new Error('Another WebBrowser is already being presented.')));
    await abrirElPago('ios');

    await pagarConTarjeta();

    expect(await screen.findByRole('alert')).toHaveTextContent(MENSAJES.servidor);
    expect(screen).toHavePathname(RUTA_DEL_PAGO);
  });

  test('pago-E02 — "Regresar" vuelve al resumen; abierta sin pantalla anterior, lo pone en su lugar', async () => {
    await abrirElPago('android');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    // Sin pedido en curso, el resumen lleva al inicio: lo que importa es que el regreso no se queda en el pago.
    await waitFor(() => expect(screen).not.toHavePathname(RUTA_DEL_PAGO));
  });
});

describe('pago-E02: ninguna pantalla de la app tiene campos de tarjeta (SEG-4)', () => {
  test.each(PLATAFORMAS)(
    'pago-E02 — (%s) ni la pantalla de pago ni la espera tienen un solo campo para escribir',
    async (plataforma) => {
      navegador.__alTerminarLaSesion({ type: 'success', url: enlaceDeRegreso('exito') });
      await abrirElPago(plataforma);
      expect(screen.queryAllByDisplayValue(/.*/)).toHaveLength(0);

      await pagarConTarjeta();
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));

      expect(screen.queryAllByDisplayValue(/.*/)).toHaveLength(0);
    },
  );

  test('pago-E02 — ningún archivo de la app declara un campo de tarjeta: ni el autocompletado ni el contenido de tarjeta del sistema', () => {
    const raiz = path.resolve(__dirname, '..', '..');
    const archivos = [...recorrer(path.join(raiz, 'app')), ...recorrer(path.join(raiz, 'src'))];
    expect(archivos.length).toBeGreaterThan(0);

    // Lo que React Native usa para un campo de tarjeta: autoComplete "cc-…" y textContentType "creditCard…".
    const deTarjeta = /['"]cc-[a-z-]+['"]|creditCard[A-Z]\w*/;
    expect(archivos.filter((archivo) => deTarjeta.test(readFileSync(archivo, 'utf8')))).toEqual([]);
  });
});

describe('pago-E02: el regreso del checkout no abre otra pantalla', () => {
  test('pago-E02 — (android) con la app abierta, el enlace de regreso no navega: lo recibe la pantalla de pago', () => {
    expect(redirectSystemPath({ path: enlaceDeRegreso('exito'), initial: false })).toBeNull();
    expect(redirectSystemPath({ path: enlaceDeRegreso('error'), initial: false })).toBeNull();
  });

  test('pago-E02 — los demás enlaces de la app siguen igual', () => {
    for (const ruta of [
      '/pedido/confirmacion/01jd8x6m3v2c9q4w5e7r8t0yp1',
      '/pedidos',
      'mx.ejemplo.prueba://pedidos',
      'mx.ejemplo.prueba://pagos?pago=1',
      'exp+movil://expo-development-client/?url=http%3A%2F%2F10.0.2.2%3A8081',
    ]) {
      expect(redirectSystemPath({ path: ruta, initial: false })).toBe(ruta);
      expect(redirectSystemPath({ path: ruta, initial: true })).toBe(ruta);
    }
  });

  // renderRouter abre la app con una ruta, no con un enlace del sistema: aquí se toma la ruta a la que lleva el enlace,
  // como hace expo-router en el teléfono con app/+native-intent.tsx, y se abre la app en ella.
  test('pago-E02 y pago-E08 — (android) si el sistema abre la app con el enlace de regreso, abre la espera de ese cobro', async () => {
    const destino = redirectSystemPath({ path: enlaceDeRegreso('exito'), initial: true });
    expect(destino).toBe(RUTA_DE_LA_ESPERA);

    await abrir('android', destino ?? '/');

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
    expect(within(screen.getByTestId('contenido')).getByRole('header', { name: 'Procesando pago' })).toBeOnTheScreen();
  });

  test('pago-E02 — un enlace de regreso sin cobro abre el arranque de la app', () => {
    expect(redirectSystemPath({ path: `${REGRESO}?resultado=exito`, initial: true })).toBe('/');
    expect(redirectSystemPath({ path: `${REGRESO}?resultado=exito`, initial: false })).toBeNull();
  });
});

function recorrer(carpeta: string): string[] {
  return readdirSync(carpeta).flatMap((nombre) => {
    const ruta = path.join(carpeta, nombre);
    if (statSync(ruta).isDirectory()) {
      return recorrer(ruta);
    }
    return /\.(tsx?|json)$/.test(nombre) ? [ruta] : [];
  });
}
