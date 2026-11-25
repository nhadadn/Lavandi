// Spec: openspec/changes/avisos/specs/avisos/spec.md, avisos-E15 (tarea 4.3). Con el seguimiento de un pedido abierto,
// el mensaje de Firebase del cambio de estado de ese pedido hace que la app consulte el estado de inmediato, sin esperar
// los 30 segundos (ADR-0007 y ADR-0008). El mensaje trae en data el pedido y su estado nuevo (design.md de avisos, "Lo
// que recibe la app por FCM"): { tipo: "cambio_de_estado", pedido: <id_publico>, estado: <código> }.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en Android con el archivo de Firebase, con el servidor
// simulado con el contrato de GET /api/pedidos/{id_publico} y los temporizadores falsos de Jest. El mensaje llega por la
// simulación de __mocks__/@react-native-firebase/messaging.ts.
import path from 'node:path';

import * as Mensajeria from '@react-native-firebase/messaging';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import { act, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/avisos-e15';
const TOKEN = 'token-de-sesion-avisos-e15';
const USUARIO = { id_publico: '01JD00000000000000000AV015', correo: 'persona@ejemplo.com', rol: 'usuario' };
const ID = '01jd8x6m3v2c9q4w5e7r8t0a15';
const OTRO = '01jd8x6m3v2c9q4w5e7r8t0b15';
const RUTA_DEL_PEDIDO = `/pedidos/${ID}`;

const CINCO = [
  { codigo: 'confirmado', nombre: 'Confirmado', hora: '09:30' },
  { codigo: 'recolectado', nombre: 'Recolectado', hora: '10:05' },
  { codigo: 'en_proceso', nombre: 'En proceso', hora: '12:40' },
  { codigo: 'listo', nombre: 'Listo', hora: '17:15' },
  { codigo: 'entregado', nombre: 'Entregado', hora: '18:50' },
] as const;
type Codigo = (typeof CINCO)[number]['codigo'];

/** El pedido como lo devuelve el servidor en ese estado. */
function pedidoEn(codigo: Codigo) {
  const actual = CINCO.findIndex((paso) => paso.codigo === codigo);
  return {
    id_publico: ID,
    folio: '6PB3X8WQ',
    confirmado_en: '2026-12-03T15:30:00Z',
    estado: { codigo, nombre: CINCO[actual].nombre, visible_usuario: true },
    seguimiento: CINCO.map((paso, indice) => ({
      codigo: paso.codigo,
      nombre: paso.nombre,
      ocurrido_en: indice <= actual ? `2026-12-03T${paso.hora}:00-06:00` : null,
    })),
    lavanderia: { id_publico: '01jd8x6m3v2c9q4w5e7r8t0y1u', nombre: 'Lavandería de prueba avisos' },
  };
}

/** El mensaje que manda la api al cambiar el estado (avisos-E14). */
function cambioDeEstado(pedido: string, estado: Codigo) {
  return { data: { tipo: 'cambio_de_estado', pedido, estado } };
}

// La simulación de __mocks__/, la misma instancia que importa la app.
const mensajeria = Mensajeria as unknown as typeof import('../../__mocks__/@react-native-firebase/messaging');
const almacen = SecureStore as unknown as { __vaciar(): void };
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

const entornoOriginal = {
  EXPO_PUBLIC_API_URL: process.env.EXPO_PUBLIC_API_URL,
  EXPO_PUBLIC_NOMBRE_APP: process.env.EXPO_PUBLIC_NOMBRE_APP,
};
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
// El estado en que la lavandería dejó el pedido.
let enElServidor: Codigo = 'confirmado';

function respuesta(status: number, cuerpo?: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/dispositivos' && metodo === 'POST') {
      return respuesta(204);
    }
    if (ruta === RUTA_DEL_PEDIDO) {
      return respuesta(200, { pedido: pedidoEn(enElServidor) });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    return respuesta(404, { message: 'No existe.' });
  });
}

/** Cuántas veces la app pidió el pedido al servidor. */
function consultasDelPedido(): number {
  return fetchFalso.mock.calls.filter(([url]: [string]) => url === `${BASE}${RUTA_DEL_PEDIDO}`).length;
}

/** Llega un mensaje con la app al frente, y se deja que lleguen las respuestas, sin avanzar el reloj. */
async function llega(mensaje: Parameters<typeof mensajeria.__mensaje>[0]) {
  await act(async () => {
    mensajeria.__mensaje(mensaje);
  });
}

async function estadoActual(nombre: string) {
  return within(await screen.findByTestId('estado-actual')).findByText(nombre);
}

async function abrir(ruta: string) {
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: ruta });
  // La app ya escucha los mensajes de Firebase cuando el teléfono quedó registrado.
  await waitFor(() => expect(mensajeria.__escuchas().mensajes).toBe(1));
}

async function abrirElSeguimiento() {
  await abrir(`/pedido/seguimiento/${ID}`);
  await estadoActual('Confirmado');
}

beforeEach(() => {
  jest.useFakeTimers();
  almacen.__vaciar();
  mensajeria.__reiniciar();
  enElServidor = 'confirmado';
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba avisos-E15';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
  jest.replaceProperty(Platform, 'OS', 'android');
  jest.spyOn(Platform, 'Version', 'get').mockReturnValue(31);
  jest.replaceProperty(Constants, 'executionEnvironment', ExecutionEnvironment.Bare);
  configuracionDeLaApp.extra = { firebaseEnAndroid: true };
});

afterEach(() => {
  for (const [variable, original] of Object.entries(entornoOriginal)) {
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
  delete configuracionDeLaApp.extra;
  jest.useRealTimers();
  jest.restoreAllMocks();
});

describe('avisos-E15: con la app abierta, el mensaje adelanta la consulta', () => {
  test('avisos-E15 — con el seguimiento abierto, el mensaje del cambio de ese pedido consulta de inmediato y muestra el estado nuevo', async () => {
    await abrirElSeguimiento();
    const antes = consultasDelPedido();
    enElServidor = 'recolectado';

    await llega(cambioDeEstado(ID, 'recolectado'));

    expect(consultasDelPedido()).toBe(antes + 1);
    expect(await estadoActual('Recolectado')).toBeOnTheScreen();
  });

  test('avisos-E15 — el aviso visible de un momento también trae el pedido y adelanta la consulta', async () => {
    await abrirElSeguimiento();
    const antes = consultasDelPedido();
    enElServidor = 'listo';

    await llega({ ...cambioDeEstado(ID, 'listo'), notification: { title: 'Aviso de prueba', body: 'Texto de prueba' } });

    expect(consultasDelPedido()).toBe(antes + 1);
    expect(await estadoActual('Listo')).toBeOnTheScreen();
  });

  test('avisos-E15 — después del mensaje, la siguiente consulta vuelve a contar 30 segundos desde ahí', async () => {
    await abrirElSeguimiento();
    await act(() => jest.advanceTimersByTimeAsync(20_000));
    const antes = consultasDelPedido();

    await llega(cambioDeEstado(ID, 'recolectado'));
    await act(() => jest.advanceTimersByTimeAsync(20_000));
    expect(consultasDelPedido()).toBe(antes + 1);

    await act(() => jest.advanceTimersByTimeAsync(10_000));
    expect(consultasDelPedido()).toBe(antes + 2);
  });

  test('avisos-E15 — el mensaje del cambio de otro pedido no adelanta la consulta', async () => {
    await abrirElSeguimiento();
    const antes = consultasDelPedido();

    await llega(cambioDeEstado(OTRO, 'recolectado'));

    expect(consultasDelPedido()).toBe(antes);
  });

  test('avisos-E15 — un mensaje que no es de cambio de estado no adelanta la consulta', async () => {
    await abrirElSeguimiento();
    const antes = consultasDelPedido();

    await llega({ data: { tipo: 'otra_cosa', pedido: ID } });
    await llega({ notification: { title: 'Sin datos' } });

    expect(consultasDelPedido()).toBe(antes);
  });

  test('avisos-E15 — sin el seguimiento abierto, el mensaje no consulta el pedido', async () => {
    await abrir('/inicio');

    await llega(cambioDeEstado(ID, 'recolectado'));

    expect(consultasDelPedido()).toBe(0);
  });
});
