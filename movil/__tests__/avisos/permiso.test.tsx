// Spec: openspec/changes/avisos/specs/avisos/spec.md, avisos-E07 y avisos-E08 (tarea 4.2). La app pide el permiso de
// avisos que exigen Android 13 o superior y iPhone (ADR-0008), y si el usuario lo niega sigue sin errores: el
// seguimiento se pone al día con la consulta cada 30 segundos (ADR-0007, estados-E15).
//
// El punto donde se pide (design.md de avisos, "Decisiones de la implementación de la app"): al abrirse la sesión de una
// cuenta de usuario que nunca respondió, con la petición del sistema y sin pantalla propia. Una sola vez: si la niega, la
// app no la vuelve a mostrar sola, y la sección de notificaciones del perfil ofrece activarlos (perfil-usuario-E08).
//
// El diálogo es la simulación de PermissionsAndroid en Android y la de __mocks__/@react-native-firebase/messaging.ts en
// iPhone. El diálogo real de cada sistema se ve en un teléfono.
import path from 'node:path';

import * as Mensajeria from '@react-native-firebase/messaging';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import { act, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { PermissionsAndroid, Platform, type Permission, type PermissionStatus } from 'react-native';

import { leerPermisoDeAvisos, pedirPermisoDeAvisos } from '../../src/avisos/permiso';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/avisos-e07';
const TOKEN = 'token-de-sesion-avisos-e07';
const USUARIO = { id_publico: '01JD00000000000000000AV007', correo: 'persona@ejemplo.com', rol: 'usuario' };
const ID = '01jd8x6m3v2c9q4w5e7r8t0av8';
const PERMISO_DE_AVISOS = 'android.permission.POST_NOTIFICATIONS';

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
let pedirAlSistema: jest.SpyInstance;

function respuesta(status: number, cuerpo?: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El pedido de prueba en Confirmado, con los cinco estados del contrato.
const CINCO = ['confirmado', 'recolectado', 'en_proceso', 'listo', 'entregado'];
const NOMBRES = ['Confirmado', 'Recolectado', 'En proceso', 'Listo', 'Entregado'];
const PEDIDO = {
  id_publico: ID,
  folio: '4MW7Q2ZK',
  confirmado_en: '2026-12-03T15:30:00Z',
  estado: { codigo: 'confirmado', nombre: 'Confirmado', visible_usuario: true },
  seguimiento: CINCO.map((codigo, indice) => ({
    codigo,
    nombre: NOMBRES[indice],
    ocurrido_en: indice === 0 ? '2026-12-03T09:30:00-06:00' : null,
  })),
  lavanderia: { id_publico: '01jd8x6m3v2c9q4w5e7r8t0y1u', nombre: 'Lavandería de prueba avisos' },
};

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
    if (ruta === `/pedidos/${ID}`) {
      return respuesta(200, { pedido: PEDIDO });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    return respuesta(404, { message: 'No existe.' });
  });
}

const registros = () =>
  fetchFalso.mock.calls.filter(
    ([url, opciones]: [string, RequestInit]) => url === `${BASE}/dispositivos` && opciones?.method === 'POST',
  );
const consultasDelPedido = () =>
  fetchFalso.mock.calls.filter(([url]: [string]) => url === `${BASE}/pedidos/${ID}`).length;

/** Lo que la persona contesta en el diálogo de Android; si lo acepta, los avisos quedan permitidos. */
function enElDialogoDeAndroid(contesta: PermissionStatus) {
  pedirAlSistema.mockImplementation(async (_permiso: Permission) => {
    if (contesta === PermissionsAndroid.RESULTS.GRANTED) {
      mensajeria.__permiso(mensajeria.AuthorizationStatus.AUTHORIZED);
    }
    return contesta;
  });
}

function enAndroid(version: number) {
  jest.replaceProperty(Platform, 'OS', 'android');
  jest.spyOn(Platform, 'Version', 'get').mockReturnValue(version);
}

async function conSesionGuardada() {
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

async function abrirLaApp(ruta = '/inicio') {
  const app = await renderRouter(APP, { initialUrl: ruta });
  await waitFor(() => expect(screen.queryByTestId('leyendo-la-sesion')).not.toBeOnTheScreen());
  return app;
}

beforeEach(() => {
  almacen.__vaciar();
  mensajeria.__reiniciar();
  // Recién instalada en Android 13 o superior: los avisos no están permitidos hasta que la persona responde.
  mensajeria.__permiso(mensajeria.AuthorizationStatus.DENIED);
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba avisos-E07';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
  enAndroid(34);
  jest.replaceProperty(Constants, 'executionEnvironment', ExecutionEnvironment.Bare);
  configuracionDeLaApp.extra = { firebaseEnAndroid: true };
  pedirAlSistema = jest.spyOn(PermissionsAndroid, 'request');
  enElDialogoDeAndroid(PermissionsAndroid.RESULTS.GRANTED);
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

describe('avisos-E07: la app pide el permiso de avisos', () => {
  test('avisos-E07 — en Android 13 o superior, al abrirse la sesión de quien nunca respondió, se muestra la petición del sistema', async () => {
    await conSesionGuardada();
    await abrirLaApp();

    await waitFor(() => expect(pedirAlSistema).toHaveBeenCalledTimes(1));
    expect(pedirAlSistema).toHaveBeenCalledWith(PERMISO_DE_AVISOS);
  });

  test('avisos-E07 — si la acepta, la app registra el token del teléfono (avisos-E03)', async () => {
    await conSesionGuardada();
    await abrirLaApp();

    await waitFor(() => expect(registros()).toHaveLength(1));
    expect(JSON.parse(registros()[0][1].body as string)).toEqual({
      token_fcm: 'token-fcm-de-prueba',
      plataforma: 'android',
    });
  });

  test('avisos-E07 — si la niega, la app no registra el token y queda sin avisos', async () => {
    enElDialogoDeAndroid(PermissionsAndroid.RESULTS.DENIED);
    await conSesionGuardada();
    await abrirLaApp();
    await waitFor(() => expect(pedirAlSistema).toHaveBeenCalledTimes(1));
    await act(async () => undefined);

    expect(registros()).toEqual([]);
    await expect(leerPermisoDeAvisos()).resolves.toBe('negado');
  });

  test('avisos-E07 — si ya la negó antes, al abrir la app no se la vuelve a mostrar', async () => {
    // Una apertura anterior de la app le mostró la petición y la negó.
    enElDialogoDeAndroid(PermissionsAndroid.RESULTS.DENIED);
    await expect(pedirPermisoDeAvisos()).resolves.toBe('negado');
    pedirAlSistema.mockClear();
    await conSesionGuardada();
    await abrirLaApp();
    await waitFor(() => expect(mensajeria.hasPermission).toHaveBeenCalled());
    await act(async () => undefined);

    expect(pedirAlSistema).not.toHaveBeenCalled();
    expect(registros()).toEqual([]);
  });

  test('avisos-E07 — con los avisos ya permitidos, no la vuelve a pedir y registra el token', async () => {
    mensajeria.__permiso(mensajeria.AuthorizationStatus.AUTHORIZED);
    await conSesionGuardada();
    await abrirLaApp();

    await waitFor(() => expect(registros()).toHaveLength(1));
    expect(pedirAlSistema).not.toHaveBeenCalled();
  });

  test('avisos-E07 — en Android 12 o anterior no hay petición: los avisos vienen permitidos y registra el token', async () => {
    enAndroid(31);
    mensajeria.__permiso(mensajeria.AuthorizationStatus.AUTHORIZED);
    await conSesionGuardada();
    await abrirLaApp();

    await waitFor(() => expect(registros()).toHaveLength(1));
    expect(pedirAlSistema).not.toHaveBeenCalled();
  });

  test('avisos-E07 — en Android 12 o anterior, con los avisos apagados en los ajustes, no pide nada: quedan negados', async () => {
    enAndroid(31);
    await conSesionGuardada();
    await abrirLaApp();
    await waitFor(() => expect(mensajeria.hasPermission).toHaveBeenCalled());
    await act(async () => undefined);

    expect(pedirAlSistema).not.toHaveBeenCalled();
    expect(registros()).toEqual([]);
    await expect(leerPermisoDeAvisos()).resolves.toBe('negado');
  });

  test('avisos-E07 — en iPhone, la app lee el estado y pide el permiso con la librería de Firebase', async () => {
    jest.replaceProperty(Platform, 'OS', 'ios');
    mensajeria.__permiso(mensajeria.AuthorizationStatus.NOT_DETERMINED);

    await expect(leerPermisoDeAvisos()).resolves.toBe('sin-responder');
    await expect(pedirPermisoDeAvisos()).resolves.toBe('concedido');
    expect(mensajeria.requestPermission).toHaveBeenCalledTimes(1);

    mensajeria.__permiso(mensajeria.AuthorizationStatus.DENIED);
    mensajeria.__respuestaAlPedir(mensajeria.AuthorizationStatus.DENIED);
    await expect(leerPermisoDeAvisos()).resolves.toBe('negado');
    await expect(pedirPermisoDeAvisos()).resolves.toBe('negado');
  });
});

describe('avisos-E08: sin permiso, la app sigue y el seguimiento se pone al día', () => {
  test('avisos-E08 — si el usuario negó el permiso, el seguimiento abre sin error y consulta cada 30 segundos', async () => {
    enElDialogoDeAndroid(PermissionsAndroid.RESULTS.DENIED);
    await conSesionGuardada();
    jest.useFakeTimers();
    await abrirLaApp(`/pedido/seguimiento/${ID}`);
    expect(await screen.findByTestId('seguimiento')).toBeOnTheScreen();
    await waitFor(() => expect(pedirAlSistema).toHaveBeenCalledTimes(1));
    const antes = consultasDelPedido();

    await act(() => jest.advanceTimersByTimeAsync(30_000));

    expect(consultasDelPedido()).toBe(antes + 1);
    expect(screen.queryByRole('alert')).not.toBeOnTheScreen();
    expect(registros()).toEqual([]);
  });

  test('avisos-E08 — si la petición del permiso falla, la app sigue sin error y sin registrar el teléfono', async () => {
    pedirAlSistema.mockRejectedValue(new Error('Tried to use permissions API while not attached to an Activity.'));
    await conSesionGuardada();
    await abrirLaApp();
    await waitFor(() => expect(pedirAlSistema).toHaveBeenCalled());
    await act(async () => undefined);

    expect(await screen.findByRole('button', { name: 'Pedido nuevo' })).toBeOnTheScreen();
    expect(registros()).toEqual([]);
  });
});
