// Spec: openspec/changes/base-tecnica/specs/base/spec.md, escenario base-E11 (C-240): la app abre en Expo Go para
// probarla en un iPhone sin cuenta de Apple, y ahí no ofrece Google. Con avisos-E03 (openspec/changes/avisos), tampoco
// pide la mensajería de Firebase.
//
// Expo Go no trae react-native-nitro-modules ni react-native-nitro-google-signin. En Expo Go, cargar la librería de Google
// cierra la app: al importarse crea su objeto nativo (GoogleOneTapSignIn.ts) y Nitro lanza "NitroModules are not
// supported in Expo Go!" (ModuleNotFoundError.ts). Tampoco trae el módulo nativo de @react-native-firebase/messaging.
// Aquí los tres módulos fallan igual si alguien los pide, y cada petición queda anotada para comprobar que la app no los
// pidió.
import path from 'node:path';

import Constants, { ExecutionEnvironment } from 'expo-constants';
import * as SecureStore from 'expo-secure-store';
import { act, renderHook, userEvent, waitFor } from '@testing-library/react-native';
import { renderRouter, screen } from 'expo-router/testing-library';
import { Platform } from 'react-native';

import { cerrarSesionDeGoogle, googleDisponible, pedirIdTokenDeGoogle } from '../../src/acceso/google';
import { registrarManejadorDeSegundoPlano } from '../../src/avisos/segundoPlano';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { enExpoGo } from '../../src/configuracion/entorno';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken, leerToken } from '../../src/sesion/almacen';
import { ProveedorDeSesion, useSesion } from '../../src/sesion/ProveedorDeSesion';

// Los módulos que pidió la app. Son funciones declaradas, que se suben completas: jest.mock se sube arriba de los
// import, y si la app pidiera un módulo al importarse, una constante de este archivo todavía no existiría.
function mockModulosPedidos(): string[] {
  const global = globalThis as typeof globalThis & { modulosQueExpoGoNoTrae?: string[] };
  global.modulosQueExpoGoNoTrae ??= [];
  return global.modulosQueExpoGoNoTrae;
}

function mockFallarComoEnExpoGo(modulo: string): never {
  mockModulosPedidos().push(modulo);
  throw new Error(`NitroModules are not supported in Expo Go! La app pidió ${modulo}.`);
}

jest.mock('react-native-nitro-modules', () => mockFallarComoEnExpoGo('react-native-nitro-modules'));
jest.mock('react-native-nitro-google-signin', () => mockFallarComoEnExpoGo('react-native-nitro-google-signin'));
jest.mock('@react-native-firebase/messaging', () => mockFallarComoEnExpoGo('@react-native-firebase/messaging'));

const APP = path.resolve(__dirname, '..', '..', 'app');

// El primer render carga expo-router y todas las pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const PLATAFORMAS = [
  ['iPhone', 'ios'],
  ['Android', 'android'],
] as const;

const PANTALLAS = [
  // [ruta, botón que manda el correo y la contraseña, ruta de la API]
  ['/inicio-de-sesion', 'Iniciar sesión', 'sesion'],
  ['/registro', 'Crear cuenta', 'registro'],
] as const;

const BASE = 'http://api.prueba.invalid/base-e11';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };

// Con el cliente de Google en la configuración: si Google no aparece en Expo Go, es por Expo Go y no porque falte.
const ENTORNO = {
  EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba base-E11',
  EXPO_PUBLIC_API_URL: BASE,
  EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID: 'cliente-web-de-prueba-base-e11.apps.googleusercontent.com',
};
const entornoOriginal = Object.fromEntries(Object.keys(ENTORNO).map((nombre) => [nombre, process.env[nombre]]));

const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

// Lo que app.config.ts le dice a la app sobre la compilación. En Jest, Constants.expoConfig es un objeto vacío que se
// puede completar.
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

beforeEach(() => {
  mockModulosPedidos().length = 0;
  almacen.__vaciar();
  Object.assign(process.env, ENTORNO);
  // El registro y el inicio de sesión abren la sesión; lo demás, como las lavanderías del inicio, no se prueba aquí.
  fetchFalso = jest.fn(async (url: string) =>
    url === `${BASE}/sesion` || url === `${BASE}/registro`
      ? respuesta(200, { token: 'token-de-correo', usuario: USUARIO })
      : respuesta(503, {}),
  );
  global.fetch = fetchFalso as unknown as typeof fetch;
});

afterEach(() => {
  for (const [nombre, valor] of Object.entries(entornoOriginal)) {
    if (valor === undefined) {
      delete process.env[nombre];
    } else {
      process.env[nombre] = valor;
    }
  }
  global.fetch = fetchOriginal;
  delete configuracionDeLaApp.extra;
  jest.restoreAllMocks();
});

async function conSesionAbierta() {
  // La sesión es de esta misma instalación: la marca de instalación ya existe (registro-E32).
  await comprobarInstalacion();
  await guardarToken('token-guardado');
  const sesion = await renderHook(() => useSesion(), { wrapper: ProveedorDeSesion });
  await waitFor(() => expect(sesion.result.current.estado).toBe('con-sesion'));
  return sesion;
}

describe.each(PLATAFORMAS)('base-E11: en Expo Go, en %s, la app abre y no ofrece Google', (_nombre, plataforma) => {
  beforeEach(() => {
    jest.replaceProperty(Platform, 'OS', plataforma);
    jest.replaceProperty(Constants, 'executionEnvironment', ExecutionEnvironment.StoreClient);
    // Con el archivo de Firebase en la configuración de Metro: si la app no pide la mensajería, es por Expo Go.
    configuracionDeLaApp.extra = { firebaseEnAndroid: true };
  });

  test('base-E11 — la app reconoce que corre en Expo Go', () => {
    expect(enExpoGo()).toBe(true);
  });

  test('base-E11 — la app abre en su primera pantalla sin pedir el módulo de Google', async () => {
    await renderRouter(APP, { initialUrl: '/' });

    expect(await screen.findByTestId('pantalla-inicial')).toBeOnTheScreen();
    expect(mockModulosPedidos()).toEqual([]);
  });

  test.each(PANTALLAS)(
    'base-E11 — %s se muestra con el correo y la contraseña, sin el botón de Google',
    async (ruta, boton) => {
      await comprobarInstalacion();
      await marcarBienvenidaVista();

      await renderRouter(APP, { initialUrl: ruta });

      expect(await screen.findByRole('button', { name: boton })).toBeOnTheScreen();
      expect(screen.getByLabelText('Correo electrónico')).toBeOnTheScreen();
      expect(screen.getByLabelText('Contraseña')).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Continuar con Google' })).not.toBeOnTheScreen();
      expect(screen.queryByText(/Google/)).not.toBeOnTheScreen();
      expect(mockModulosPedidos()).toEqual([]);
    },
  );

  test.each(PANTALLAS)(
    'base-E11 — en %s, el correo y la contraseña siguen funcionando: abre la sesión y entra al inicio',
    async (ruta, boton, rutaDeLaApi) => {
      await comprobarInstalacion();
      await marcarBienvenidaVista();
      const usuario = userEvent.setup();
      await renderRouter(APP, { initialUrl: ruta });

      await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona@ejemplo.com');
      await usuario.type(screen.getByLabelText('Contraseña'), 'contrasena-de-prueba');
      await usuario.press(screen.getByRole('button', { name: boton }));

      await waitFor(() => expect(screen).toHavePathname('/inicio'));
      await expect(leerToken()).resolves.toBe('token-de-correo');
      const [url, opciones] = fetchFalso.mock.calls[0] as [string, RequestInit];
      expect(url).toBe(`${BASE}/${rutaDeLaApi}`);
      expect(JSON.parse(opciones.body as string)).toEqual({
        correo: 'persona@ejemplo.com',
        password: 'contrasena-de-prueba',
      });
      expect(mockModulosPedidos()).toEqual([]);
    },
  );

  test('base-E11 — con el cliente de Google en la configuración, Google no está disponible', () => {
    expect(googleDisponible()).toBe(false);
    expect(mockModulosPedidos()).toEqual([]);
  });

  test('avisos-E03 — al arrancar, la app no registra el manejador de los avisos ni pide la mensajería', () => {
    registrarManejadorDeSegundoPlano();

    expect(mockModulosPedidos()).toEqual([]);
  });

  test('base-E11 — pedir el idToken de Google no pide el módulo: devuelve un error sin lanzar', async () => {
    await expect(pedirIdTokenDeGoogle()).resolves.toEqual({ tipo: 'error' });
    expect(mockModulosPedidos()).toEqual([]);
  });

  test('base-E11 — cerrar la sesión de la app la cierra sin pedir el módulo de Google', async () => {
    const { result } = await conSesionAbierta();

    await act(() => result.current.cerrar());

    expect(result.current.estado).toBe('sin-sesion');
    await expect(leerToken()).resolves.toBeNull();
    await expect(cerrarSesionDeGoogle()).resolves.toBeUndefined();
    expect(mockModulosPedidos()).toEqual([]);
  });
});

// Fuera de Expo Go todo sigue igual: el development build dice "bare" y la app de las tiendas, "standalone". La entrada
// completa con Google, con la librería real, la prueban registro-E16 a E19 (__tests__/registro/google.test.tsx).
describe.each(PLATAFORMAS)('base-E11: fuera de Expo Go, en %s, Google sigue', (_nombre, plataforma) => {
  const COMPILACIONES = [
    ['el development build', ExecutionEnvironment.Bare],
    ['la app de las tiendas', ExecutionEnvironment.Standalone],
  ] as const;

  beforeEach(() => {
    jest.replaceProperty(Platform, 'OS', plataforma);
  });

  test.each(COMPILACIONES)('base-E11 — en %s, la app no se cree en Expo Go y ofrece Google', async (_c, entorno) => {
    jest.replaceProperty(Constants, 'executionEnvironment', entorno);
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    expect(enExpoGo()).toBe(false);
    expect(googleDisponible()).toBe(true);
    await renderRouter(APP, { initialUrl: '/inicio-de-sesion' });
    expect(await screen.findByRole('button', { name: 'Continuar con Google' })).toBeOnTheScreen();
  });

  test.each(COMPILACIONES)('base-E11 — en %s, cerrar la sesión de Google sí pide el módulo', async (_c, entorno) => {
    jest.replaceProperty(Constants, 'executionEnvironment', entorno);

    // Aquí el módulo falla al pedirse; el cierre de la sesión de Google nunca falla (registro-E13).
    await expect(cerrarSesionDeGoogle()).resolves.toBeUndefined();
    expect(mockModulosPedidos()).toContain('react-native-nitro-google-signin');
  });
});
