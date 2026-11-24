// Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, perfil-usuario-E08 (tarea 2.3). La sección
// "Notificaciones" de "Mi perfil" (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15, "notificaciones"): dice
// si los avisos de la app están activos en el teléfono y, si no lo están, ofrece pedir el permiso o abrir los ajustes del
// sistema (ADR-0008; design.md de perfil-y-soporte, decisión 4, lectura mínima del numeral 15).
//
// El permiso es el de avisos (openspec/changes/avisos, avisos-E07; src/avisos/permiso.ts): la petición del sistema si la
// persona nunca respondió, y los ajustes si ya la negó. Al activarlos, el teléfono queda registrado (avisos-E03). Sin
// avisos en la compilación (Expo Go, iPhone hasta avisos-E18 o un APK sin el archivo de Firebase), la sección no aparece,
// como Google en Expo Go (base-E11).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en Android con el archivo de Firebase y el servidor
// simulado. El diálogo es la simulación de PermissionsAndroid; los ajustes, la de Linking.openSettings.
import path from 'node:path';

import * as Mensajeria from '@react-native-firebase/messaging';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import { act, fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import {
  AppState,
  Linking,
  PermissionsAndroid,
  Platform,
  type AppStateStatus,
  type NativeEventSubscription,
  type PermissionStatus,
} from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-notificaciones';
const TOKEN = 'token-perfil-notificaciones';
const USUARIO = { id_publico: '01JD0000000000000000000PN1', correo: 'persona@ejemplo.com', rol: 'usuario' };

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
let abrirAjustes: jest.SpyInstance;

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
    if (ruta === '/perfil' && metodo === 'GET') {
      return respuesta(200, {
        perfil: { id_publico: USUARIO.id_publico.toLowerCase(), nombre: 'Nombre de prueba', correo: USUARIO.correo, foto: null },
      });
    }
    if (ruta === '/direcciones/favoritas') {
      return respuesta(200, { direcciones: [] });
    }
    if (ruta === '/dispositivos' && metodo === 'POST') {
      return respuesta(204);
    }
    return respuesta(404, { message: 'No existe.' });
  });
}

const registros = () =>
  fetchFalso.mock.calls.filter(
    ([url, opciones]: [string, RequestInit]) => url === `${BASE}/dispositivos` && opciones?.method === 'POST',
  );

/** Lo que la persona contesta en el diálogo de Android; si lo acepta, los avisos quedan permitidos. */
function enElDialogoDeAndroid(...respuestas: (PermissionStatus | Error)[]) {
  for (const contesta of respuestas) {
    pedirAlSistema.mockImplementationOnce(async () => {
      if (contesta instanceof Error) {
        throw contesta;
      }
      if (contesta === PermissionsAndroid.RESULTS.GRANTED) {
        mensajeria.__permiso(mensajeria.AuthorizationStatus.AUTHORIZED);
      }
      return contesta;
    });
  }
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

function notificaciones() {
  return within(screen.getByTestId('notificaciones'));
}

async function abrirElPerfil() {
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: '/perfil' });
  await screen.findByRole('header', { name: 'Mi perfil' });
  await waitFor(() => expect(screen.queryByText('Cargando tu perfil…')).not.toBeOnTheScreen());
}

/** Espera a que la sección diga en qué están los avisos. */
async function seccionLista() {
  const seccion = await screen.findByTestId('notificaciones');
  await waitFor(() => expect(within(seccion).queryByText(/Los avisos de tus pedidos están/)).toBeOnTheScreen());
  return within(seccion);
}

beforeEach(() => {
  almacen.__vaciar();
  mensajeria.__reiniciar();
  // Android 14, recién instalada: los avisos no están permitidos hasta que la persona responde.
  mensajeria.__permiso(mensajeria.AuthorizationStatus.DENIED);
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-notificaciones';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
  jest.replaceProperty(Platform, 'OS', 'android');
  jest.spyOn(Platform, 'Version', 'get').mockReturnValue(34);
  jest.replaceProperty(Constants, 'executionEnvironment', ExecutionEnvironment.Bare);
  configuracionDeLaApp.extra = { firebaseEnAndroid: true };
  pedirAlSistema = jest.spyOn(PermissionsAndroid, 'request');
  // En las pruebas, Linking.openSettings ya es una simulación de react-native que guarda las llamadas de todo el
  // archivo: se vacía antes de cada prueba.
  abrirAjustes = jest.spyOn(Linking, 'openSettings').mockResolvedValue(undefined);
  abrirAjustes.mockClear();
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
  for (const [variable, original] of Object.entries(entornoOriginal)) {
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
  delete configuracionDeLaApp.extra;
  jest.restoreAllMocks();
});

describe('perfil-usuario-E08: avisos apagados en el teléfono', () => {
  test('perfil-usuario-E08 — si negó el permiso, la sección dice que los avisos están apagados y ofrece abrir los ajustes', async () => {
    enElDialogoDeAndroid(PermissionsAndroid.RESULTS.DENIED);
    await abrirElPerfil();
    await waitFor(() => expect(pedirAlSistema).toHaveBeenCalledTimes(1));

    const seccion = await seccionLista();
    expect(screen.getByRole('header', { name: 'Notificaciones' })).toBeOnTheScreen();
    expect(seccion.getByText('Los avisos de tus pedidos están apagados.')).toBeOnTheScreen();
    await fireEvent.press(seccion.getByRole('button', { name: 'Abrir ajustes' }));

    expect(abrirAjustes).toHaveBeenCalledTimes(1);
    expect(pedirAlSistema).toHaveBeenCalledTimes(1);
  });

  test('perfil-usuario-E08 — al volver de los ajustes con los avisos activados, dice que están activos y registra el teléfono', async () => {
    enElDialogoDeAndroid(PermissionsAndroid.RESULTS.DENIED);
    await abrirElPerfil();
    const seccion = await seccionLista();
    await fireEvent.press(seccion.getByRole('button', { name: 'Abrir ajustes' }));
    await mandarLaAppA('background');

    mensajeria.__permiso(mensajeria.AuthorizationStatus.AUTHORIZED);
    await mandarLaAppA('active');

    expect(await seccion.findByText('Los avisos de tus pedidos están activos.')).toBeOnTheScreen();
    expect(seccion.queryByRole('button')).not.toBeOnTheScreen();
    await waitFor(() => expect(registros()).toHaveLength(1));
  });

  test('perfil-usuario-E08 — si nunca respondió, ofrece pedir el permiso; al concederlo, dice que están activos y registra el teléfono', async () => {
    // La petición al abrirse la sesión no llegó a mostrarse: la persona nunca respondió.
    enElDialogoDeAndroid(new Error('Tried to use permissions API while not attached to an Activity.'), PermissionsAndroid.RESULTS.GRANTED);
    await abrirElPerfil();
    await waitFor(() => expect(pedirAlSistema).toHaveBeenCalledTimes(1));
    const seccion = await seccionLista();
    expect(seccion.getByText('Los avisos de tus pedidos están apagados.')).toBeOnTheScreen();

    await fireEvent.press(seccion.getByRole('button', { name: 'Activar avisos' }));

    expect(await seccion.findByText('Los avisos de tus pedidos están activos.')).toBeOnTheScreen();
    expect(pedirAlSistema).toHaveBeenCalledTimes(2);
    expect(abrirAjustes).not.toHaveBeenCalled();
    await waitFor(() => expect(registros()).toHaveLength(1));
  });

  test('perfil-usuario-E08 — si al pedirlo lo niega, sigue apagado y ofrece abrir los ajustes', async () => {
    enElDialogoDeAndroid(new Error('Tried to use permissions API while not attached to an Activity.'), PermissionsAndroid.RESULTS.DENIED);
    await abrirElPerfil();
    const seccion = await seccionLista();

    await fireEvent.press(await seccion.findByRole('button', { name: 'Activar avisos' }));

    expect(await seccion.findByRole('button', { name: 'Abrir ajustes' })).toBeOnTheScreen();
    expect(seccion.getByText('Los avisos de tus pedidos están apagados.')).toBeOnTheScreen();
    expect(registros()).toEqual([]);
  });

  test('perfil-usuario-E08 — con el permiso concedido, la sección dice que los avisos están activos, sin botones', async () => {
    mensajeria.__permiso(mensajeria.AuthorizationStatus.AUTHORIZED);
    await abrirElPerfil();

    const seccion = await seccionLista();
    expect(seccion.getByText('Los avisos de tus pedidos están activos.')).toBeOnTheScreen();
    expect(seccion.queryByRole('button')).not.toBeOnTheScreen();
  });

  test('perfil-usuario-E08 — en Android 12 o anterior con los avisos apagados, ofrece abrir los ajustes', async () => {
    jest.spyOn(Platform, 'Version', 'get').mockReturnValue(31);
    await abrirElPerfil();

    const seccion = await seccionLista();
    expect(seccion.getByText('Los avisos de tus pedidos están apagados.')).toBeOnTheScreen();
    expect(seccion.getByRole('button', { name: 'Abrir ajustes' })).toBeOnTheScreen();
    expect(pedirAlSistema).not.toHaveBeenCalled();
  });

  test('perfil-usuario-E08 — sin avisos en la compilación, la sección no aparece', async () => {
    configuracionDeLaApp.extra = { firebaseEnAndroid: false };
    await abrirElPerfil();

    expect(screen.getByRole('button', { name: 'Guardar nombre' })).toBeOnTheScreen();
    expect(screen.queryByTestId('notificaciones')).not.toBeOnTheScreen();
    expect(mensajeria.getMessaging).not.toHaveBeenCalled();
  });

  test('perfil-usuario-E08 — en iPhone, hasta avisos-E18, la sección no aparece', async () => {
    jest.replaceProperty(Platform, 'OS', 'ios');
    await abrirElPerfil();

    expect(screen.getByRole('button', { name: 'Guardar nombre' })).toBeOnTheScreen();
    expect(screen.queryByTestId('notificaciones')).not.toBeOnTheScreen();
  });

  test('perfil-usuario-E08 — la sección de notificaciones no trae interruptores por tipo de aviso, que no tienen cita', async () => {
    enElDialogoDeAndroid(PermissionsAndroid.RESULTS.DENIED);
    await abrirElPerfil();

    const seccion = await seccionLista();
    expect(seccion.queryByRole('switch')).not.toBeOnTheScreen();
    expect(notificaciones().getAllByRole('button')).toHaveLength(1);
  });
});
