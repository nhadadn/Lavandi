// Spec: openspec/changes/pago/specs/pago/spec.md, pago-E08 (tarea 4.2). La espera "Procesando pago" del cobro,
// /pedido/pago/procesando/[cobro], a la que pasa la app cuando la persona vuelve del checkout de la pasarela: consulta el
// cobro en servidor/ (GET /api/pagos/{id_publico}) cada 5 segundos mientras siga pendiente y muestra el resultado cuando
// el servidor lo da por pagado, con la confirmación del pedido y su folio, o informa que el intento se rechazó, en la
// pantalla de pago, con el botón para volver a intentar con el mismo cobro (pago-E04). A los 3 minutos sin respuesta deja
// de esperar, dice que el pago sigue en revisión y ofrece volver a consultar (decisión 32; adenda del 2026-11-22 de
// ADR-0010). Regresar del checkout no confirma nada (pago-E07): la espera solo lee.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado con el
// "Contrato de la API" de openspec/changes/pago/design.md (__tests__/apoyo/pago.ts) y los temporizadores falsos de Jest.
// El estado del cobro en el servidor simulado es el que dejaría el aviso de la pasarela (pago-E03 y pago-E04).
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrir,
  enlaceDeRegreso,
  FOLIO,
  limpiarElServidor,
  navegador,
  PEDIDO,
  PLATAFORMAS,
  prepararElServidor,
  respuesta,
  RUTA_DE_LA_ESPERA,
  RUTA_DEL_PAGO,
  type Plataforma,
  type ServidorDelPago,
} from '../apoyo/pago';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const BASE = 'http://api.prueba.invalid/pago-espera';
const RUTA_DE_LA_CONFIRMACION = `/pedido/confirmacion/${PEDIDO}`;

// Decisión 32: cada 5 segundos, hasta 3 minutos.
const CINCO_SEGUNDOS = 5_000;
const TRES_MINUTOS = 3 * 60_000;

let servidor: ServidorDelPago;

beforeEach(() => {
  jest.useFakeTimers();
  servidor = prepararElServidor(BASE);
});

afterEach(() => {
  limpiarElServidor();
  jest.restoreAllMocks();
  jest.useRealTimers();
});

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

async function abrirLaEspera(plataforma: Plataforma) {
  await abrir(plataforma, RUTA_DE_LA_ESPERA);
  await waitFor(() => expect(servidor.consultasDelCobro()).toBe(1));
}

/** El título de la espera, en el contenido de la pantalla (el encabezado dice "Pago"). */
function tituloDelContenido(nombre: string) {
  return within(screen.getByTestId('contenido')).queryByRole('header', { name: nombre });
}

describe('pago-E08: la app espera la confirmación del servidor', () => {
  test.each(PLATAFORMAS)(
    'pago-E08 — (%s) con el cobro pendiente, muestra "Procesando pago" y sigue consultando cada 5 segundos',
    async (plataforma) => {
      await abrirLaEspera(plataforma);

      expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();
      expect(screen.getByText('Esperamos la confirmación de tu pago.')).toBeOnTheScreen();

      await pasan(CINCO_SEGUNDOS - 1);
      expect(servidor.consultasDelCobro()).toBe(1);
      await pasan(1);
      expect(servidor.consultasDelCobro()).toBe(2);
      await pasan(CINCO_SEGUNDOS);
      expect(servidor.consultasDelCobro()).toBe(3);
      expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'pago-E08 — (%s) cuando el servidor lo da por pagado, deja de consultar y muestra la confirmación del pedido con su folio',
    async (plataforma) => {
      await abrirLaEspera(plataforma);

      // Llega el aviso de la pasarela al servidor: el cobro queda pagado y el pedido, Confirmado.
      servidor.cobro = 'pagado';
      await pasan(CINCO_SEGUNDOS);

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_CONFIRMACION));
      expect(await screen.findByRole('header', { name: '¡Pedido confirmado!' })).toBeOnTheScreen();
      expect(screen.getByText(FOLIO)).toBeOnTheScreen();
      const consultas = servidor.consultasDelCobro();
      await pasan(CINCO_SEGUNDOS * 4);
      expect(servidor.consultasDelCobro()).toBe(consultas);
    },
  );

  test.each(PLATAFORMAS)(
    'pago-E08 y pago-E04 — (%s) cuando el servidor informa que el intento se rechazó, deja de consultar y lo dice en la pantalla de pago, que vuelve a intentar con el mismo cobro',
    async (plataforma) => {
      await abrirLaEspera(plataforma);

      servidor.cobro = 'fallido';
      await pasan(CINCO_SEGUNDOS);

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_PAGO));
      expect(await screen.findByRole('alert')).toHaveTextContent('El pago no se completó. Intenta de nuevo.');
      const consultas = servidor.consultasDelCobro();
      await pasan(CINCO_SEGUNDOS * 4);
      expect(servidor.consultasDelCobro()).toBe(consultas);

      // Volver a intentar pide otra vez el checkout del mismo pedido: el servidor reabre el mismo cobro.
      await fireEvent.press(screen.getByRole('button', { name: 'Pagar con tarjeta' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      expect(servidor.pedidosDeCheckout()).toEqual([{ pedido: PEDIDO, metodo: 'tarjeta' }]);
      expect(screen.queryByText('El pago no se completó. Intenta de nuevo.')).not.toBeOnTheScreen();
      expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'pago-E08 — (%s) desde la pantalla de pago: vuelve del checkout, espera y, con el pago acreditado, muestra la confirmación',
    async (plataforma) => {
      navegador.__alTerminarLaSesion({ type: 'success', url: enlaceDeRegreso('exito') });
      await abrir(plataforma, RUTA_DEL_PAGO);
      await fireEvent.press(await screen.findByRole('button', { name: 'Pagar con tarjeta' }));

      // Volvió con el regreso de éxito, pero el aviso de la pasarela todavía no llega: sigue pendiente.
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ESPERA));
      await waitFor(() => expect(servidor.consultasDelCobro()).toBe(1));
      expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();

      servidor.cobro = 'pagado';
      await pasan(CINCO_SEGUNDOS);

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_CONFIRMACION));
      expect(await screen.findByText(FOLIO)).toBeOnTheScreen();
    },
  );

  test('pago-E07 y pago-E08 — la espera solo lee: consulta el cobro con la sesión, sin cuerpo, y no le manda nada más al servidor', async () => {
    await abrirLaEspera('ios');
    await pasan(CINCO_SEGUNDOS * 2);

    const peticiones = servidor.peticiones();
    expect(peticiones.every(({ metodo }) => metodo === 'GET')).toBe(true);
    const delCobro = peticiones.filter(({ ruta }) => ruta.startsWith('/pagos/'));
    expect(delCobro).toHaveLength(3);
    for (const peticion of delCobro) {
      expect(peticion.ruta).toBe(RUTA_DE_LA_ESPERA.replace('/pedido/pago/procesando/', '/pagos/'));
      expect(peticion.cuerpo).toBeUndefined();
      expect(peticion.autorizacion).toBe('Bearer token-del-pago');
    }
  });
});

describe('pago-E08: a los 3 minutos deja de esperar y ofrece volver a consultar', () => {
  test.each(PLATAFORMAS)(
    'pago-E08 — (%s) a los 3 minutos con el cobro pendiente, deja de consultar, dice que el pago sigue en revisión y ofrece volver a consultar',
    async (plataforma) => {
      await abrirLaEspera(plataforma);

      await pasan(TRES_MINUTOS - CINCO_SEGUNDOS);
      expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Volver a consultar' })).not.toBeOnTheScreen();

      await pasan(CINCO_SEGUNDOS);

      expect(await screen.findByRole('button', { name: 'Volver a consultar' })).toBeOnTheScreen();
      expect(tituloDelContenido('Tu pago sigue en revisión')).toBeOnTheScreen();
      expect(
        screen.getByText('Todavía no recibimos la confirmación de tu pago. Puedes volver a consultar en un momento.'),
      ).toBeOnTheScreen();
      expect(tituloDelContenido('Procesando pago')).not.toBeOnTheScreen();
      const consultas = servidor.consultasDelCobro();
      // Cada 5 segundos durante 3 minutos: la primera al abrir y una más cada 5 segundos.
      expect(consultas).toBeGreaterThanOrEqual(TRES_MINUTOS / CINCO_SEGUNDOS);
      expect(consultas).toBeLessThanOrEqual(TRES_MINUTOS / CINCO_SEGUNDOS + 1);

      await pasan(TRES_MINUTOS);
      expect(servidor.consultasDelCobro()).toBe(consultas);
    },
  );

  test.each(PLATAFORMAS)(
    'pago-E08 — (%s) "Volver a consultar" consulta de inmediato, vuelve a esperar cada 5 segundos y muestra el resultado',
    async (plataforma) => {
      await abrirLaEspera(plataforma);
      await pasan(TRES_MINUTOS);
      const consultas = servidor.consultasDelCobro();

      await fireEvent.press(await screen.findByRole('button', { name: 'Volver a consultar' }));

      await waitFor(() => expect(servidor.consultasDelCobro()).toBe(consultas + 1));
      expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS);
      expect(servidor.consultasDelCobro()).toBe(consultas + 2);

      servidor.cobro = 'pagado';
      await pasan(CINCO_SEGUNDOS);
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_CONFIRMACION));
    },
  );

  test('pago-E08 — después de volver a consultar, espera otros 3 minutos antes de dejar de consultar', async () => {
    await abrirLaEspera('android');
    await pasan(TRES_MINUTOS);
    await fireEvent.press(await screen.findByRole('button', { name: 'Volver a consultar' }));

    await pasan(TRES_MINUTOS - CINCO_SEGUNDOS);
    expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();
    await pasan(CINCO_SEGUNDOS);

    expect(await screen.findByRole('button', { name: 'Volver a consultar' })).toBeOnTheScreen();
  });
});

describe('pago-E08: cuando la consulta no dice pagado, rechazado ni pendiente', () => {
  test('pago-E08 — si el cobro no existe o es de otro pedido (404), lo dice y deja de consultar', async () => {
    servidor.cobro = null;
    await abrirLaEspera('ios');

    expect(await screen.findByRole('alert')).toHaveTextContent('No encontramos este pago.');
    expect(tituloDelContenido('Procesando pago')).not.toBeOnTheScreen();
    await pasan(CINCO_SEGUNDOS * 4);
    expect(servidor.consultasDelCobro()).toBe(1);
  });

  test.each(['vencido', 'cancelado'] as const)(
    'pago-E08 — si el cobro queda %s, lo dice y deja de consultar',
    async (estado) => {
      await abrirLaEspera('android');

      servidor.cobro = estado;
      await pasan(CINCO_SEGUNDOS);

      expect(await screen.findByRole('alert')).toHaveTextContent('Este cobro ya no se puede pagar.');
      expect(tituloDelContenido('Procesando pago')).not.toBeOnTheScreen();
      await pasan(CINCO_SEGUNDOS * 4);
      expect(servidor.consultasDelCobro()).toBe(2);
    },
  );

  // Sin escenario propio: como el seguimiento del pedido, la espera dice el mensaje general y sigue consultando.
  test.each([
    ['sin conexión', MENSAJES.sinConexion, () => Promise.reject(new TypeError('Network request failed'))],
    ['una falla del servidor (500)', MENSAJES.servidor, () => respuesta(500, { message: 'Server Error' })],
  ])('pago-E08 — con %s, lo dice y sigue consultando; cuando contesta, sigue la espera', async (_caso, mensaje, contestar) => {
    servidor.interceptar = ({ ruta }) => (ruta.startsWith('/pagos/') ? contestar() : undefined);
    await abrirLaEspera('ios');

    expect(await screen.findByRole('alert')).toHaveTextContent(mensaje);
    expect(tituloDelContenido('Procesando pago')).toBeOnTheScreen();

    servidor.interceptar = null;
    await pasan(CINCO_SEGUNDOS);
    expect(servidor.consultasDelCobro()).toBe(2);
    expect(screen.queryByRole('alert')).not.toBeOnTheScreen();

    servidor.cobro = 'pagado';
    await pasan(CINCO_SEGUNDOS);
    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_CONFIRMACION));
  });
});
