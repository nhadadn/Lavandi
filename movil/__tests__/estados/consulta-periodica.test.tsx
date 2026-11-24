// Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md, estados-E15 y estados-E16 (tarea 4.2). Con el
// seguimiento abierto, la app consulta el pedido cada 30 segundos, para que un cambio de la lavandería se vea en 60 o
// menos (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3; ADR-0007). La consulta corre solo con la pantalla visible y la
// app al frente, y al volver a ella consulta de inmediato. Se abre la app real, con las rutas de movil/app/ (ADR-0017),
// en iPhone y en Android, con el servidor simulado con el contrato de GET /api/pedidos/{id_publico}
// (openspec/changes/estados-del-pedido/design.md, "Contrato de la API") y los temporizadores falsos de Jest.
//
// El aviso de FCM que adelanta la consulta al cambiar el estado es del cambio `avisos` (ADR-0007, ADR-0008), y el
// cronómetro en un teléfono, estados-E17, es la tarea 4.3 (Maestro).
import path from 'node:path';

import { router } from 'expo-router';
import { act, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { AppState, Platform, type AppStateStatus, type NativeEventSubscription } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/estados-consulta';
const TOKEN = 'token-estados-consulta';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const ID = '01jd8x6m3v2c9q4w5e7r8t0y2a';
const RUTA_DEL_PEDIDO = `/pedidos/${ID}`;
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// ADR-0007: cada 30 segundos.
const TREINTA_SEGUNDOS = 30_000;

const CINCO = [
  { codigo: 'confirmado', nombre: 'Confirmado', hora: '09:30' },
  { codigo: 'recolectado', nombre: 'Recolectado', hora: '10:05' },
  { codigo: 'en_proceso', nombre: 'En proceso', hora: '12:40' },
  { codigo: 'listo', nombre: 'Listo', hora: '17:15' },
  { codigo: 'entregado', nombre: 'Entregado', hora: '18:50' },
] as const;
type Codigo = (typeof CINCO)[number]['codigo'];

/** El pedido como lo devuelve el servidor en ese estado, con la hora de los estados a los que ya llegó. */
function pedidoEn(codigo: Codigo) {
  const actual = CINCO.findIndex((paso) => paso.codigo === codigo);
  return {
    id_publico: ID,
    folio: '3HT8W2QZ',
    confirmado_en: '2026-12-03T15:30:00Z',
    estado: { codigo, nombre: CINCO[actual].nombre, visible_usuario: true },
    seguimiento: CINCO.map((paso, indice) => ({
      codigo: paso.codigo,
      nombre: paso.nombre,
      ocurrido_en: indice <= actual ? `2026-12-03T${paso.hora}:00-06:00` : null,
    })),
    lavanderia: { id_publico: '01jd8x6m3v2c9q4w5e7r8t0y1u', nombre: 'Lavandería de prueba consulta' },
  };
}

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// Lo que el servidor falso contesta al pedido: el estado en que lo dejó la lavandería, o sin conexión.
let enElServidor: Codigo | 'sin-conexion' = 'confirmado';

function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === RUTA_DEL_PEDIDO) {
      const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
      if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
        return respuesta(401, { message: 'Unauthenticated.' });
      }
      if (enElServidor === 'sin-conexion') {
        throw new TypeError('Network request failed');
      }
      return respuesta(200, { pedido: pedidoEn(enElServidor) });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

/** Cuántas veces la app pidió el pedido al servidor. */
function consultasDelPedido(): number {
  return fetchFalso.mock.calls.filter(([url]: [string]) => url === `${BASE}${RUTA_DEL_PEDIDO}`).length;
}

// Los oyentes de AppState que registró la app, para mandarla al fondo y traerla al frente.
let oyentesDeLaApp: ((estado: AppStateStatus) => void)[] = [];

async function mandarLaAppA(estado: AppStateStatus) {
  await act(async () => {
    for (const oyente of [...oyentesDeLaApp]) {
      oyente(estado);
    }
  });
}

/** Avanza el reloj falso y deja que lleguen las respuestas. */
async function pasan(milisegundos: number) {
  await act(() => jest.advanceTimersByTimeAsync(milisegundos));
}

/** El nombre del estado actual en la pantalla. */
async function estadoActual(nombre: string) {
  return within(await screen.findByTestId('estado-actual')).findByText(nombre);
}

async function abrirElSeguimiento(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: `/pedido/seguimiento/${ID}` });
  await estadoActual('Confirmado');
}

beforeEach(() => {
  jest.useFakeTimers();
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba estados-consulta';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  enElServidor = 'confirmado';
  servidor();
  oyentesDeLaApp = [];
  jest.spyOn(AppState, 'addEventListener').mockImplementation((_tipo, oyente) => {
    oyentesDeLaApp.push(oyente as (estado: AppStateStatus) => void);
    return {
      remove: () => {
        oyentesDeLaApp = oyentesDeLaApp.filter((otro) => otro !== oyente);
      },
    } as NativeEventSubscription;
  });
});

afterEach(() => {
  for (const [variable, original] of [
    ['EXPO_PUBLIC_API_URL', direccionOriginal],
    ['EXPO_PUBLIC_NOMBRE_APP', nombreOriginal],
  ] as const) {
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
  jest.restoreAllMocks();
  jest.useRealTimers();
});

describe('estados-E15: la pantalla consulta cada 30 segundos', () => {
  test.each(PLATAFORMAS)(
    'estados-E15 — (%s) con el pedido en Confirmado, a los 30 segundos vuelve a consultar y muestra "Recolectado" sin que el usuario toque nada',
    async (plataforma) => {
      await abrirElSeguimiento(plataforma);
      expect(consultasDelPedido()).toBe(1);

      // La lavandería marca Recolectado.
      enElServidor = 'recolectado';
      await pasan(TREINTA_SEGUNDOS - 1);
      expect(consultasDelPedido()).toBe(1);
      expect(within(screen.getByTestId('estado-actual')).getByText('Confirmado')).toBeOnTheScreen();

      await pasan(1);

      expect(consultasDelPedido()).toBe(2);
      expect(await estadoActual('Recolectado')).toBeOnTheScreen();
      expect(screen.getByTestId('paso-confirmado')).toHaveAccessibleName('Confirmado, cumplido, 09:30');
      expect(screen.getByTestId('paso-recolectado')).toHaveAccessibleName('Recolectado, estado actual, 10:05');
    },
  );

  test('estados-E15 — sigue consultando cada 30 segundos mientras la pantalla está visible', async () => {
    await abrirElSeguimiento('android');

    enElServidor = 'recolectado';
    await pasan(TREINTA_SEGUNDOS);
    expect(await estadoActual('Recolectado')).toBeOnTheScreen();
    enElServidor = 'en_proceso';
    await pasan(TREINTA_SEGUNDOS);
    expect(await estadoActual('En proceso')).toBeOnTheScreen();
    await pasan(TREINTA_SEGUNDOS);

    expect(consultasDelPedido()).toBe(4);
  });

  // Sin escenario propio: si una consulta no llega, la pantalla conserva lo último que supo y usa el mensaje general de
  // la app; la siguiente que llega lo quita.
  test('estados-E15 — si una consulta no llega, conserva el último estado, lo dice y la siguiente lo pone al día', async () => {
    await abrirElSeguimiento('ios');

    enElServidor = 'sin-conexion';
    await pasan(TREINTA_SEGUNDOS);
    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(within(screen.getByTestId('estado-actual')).getByText('Confirmado')).toBeOnTheScreen();

    enElServidor = 'recolectado';
    await pasan(TREINTA_SEGUNDOS);

    expect(await estadoActual('Recolectado')).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });
});

describe('estados-E16: la consulta corre solo con la pantalla visible', () => {
  test.each(PLATAFORMAS)(
    'estados-E16 — (%s) al salir del seguimiento deja de consultar, y al volver consulta de inmediato',
    async (plataforma) => {
      await abrirElSeguimiento(plataforma);
      expect(consultasDelPedido()).toBe(1);

      await act(() => router.push('/informativa/1'));
      await waitFor(() => expect(screen).toHavePathname('/informativa/1'));
      enElServidor = 'recolectado';
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultasDelPedido()).toBe(1);

      await act(() => router.back());
      await waitFor(() => expect(screen).toHavePathname(`/pedido/seguimiento/${ID}`));

      expect(consultasDelPedido()).toBe(2);
      expect(await estadoActual('Recolectado')).toBeOnTheScreen();

      // De vuelta en el seguimiento, la consulta sigue cada 30 segundos.
      await pasan(TREINTA_SEGUNDOS);
      expect(consultasDelPedido()).toBe(3);
    },
  );

  test.each(PLATAFORMAS)(
    'estados-E16 — (%s) con la app al fondo deja de consultar, y al traerla al frente consulta de inmediato',
    async (plataforma) => {
      await abrirElSeguimiento(plataforma);
      expect(consultasDelPedido()).toBe(1);

      await mandarLaAppA('background');
      enElServidor = 'recolectado';
      await pasan(3 * TREINTA_SEGUNDOS);
      expect(consultasDelPedido()).toBe(1);

      await mandarLaAppA('active');

      expect(consultasDelPedido()).toBe(2);
      expect(await estadoActual('Recolectado')).toBeOnTheScreen();

      // Al frente otra vez, la consulta sigue cada 30 segundos.
      await pasan(TREINTA_SEGUNDOS);
      expect(consultasDelPedido()).toBe(3);
    },
  );

  test('estados-E16 — al cerrar el seguimiento deja de consultar', async () => {
    await abrirElSeguimiento('ios');

    await act(() => router.replace('/informativa/1'));
    await waitFor(() => expect(screen).toHavePathname('/informativa/1'));
    await pasan(3 * TREINTA_SEGUNDOS);

    expect(consultasDelPedido()).toBe(1);
  });
});
