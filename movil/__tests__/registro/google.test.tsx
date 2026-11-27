// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E19 y registro-E20.
// Google con react-native-nitro-google-signin (ADR-0005). La librería corre su JavaScript real; lo nativo lo simula
// __mocks__/react-native-nitro-modules.tsx.
import { spawnSync } from 'node:child_process';
import path from 'node:path';

import * as SecureStore from 'expo-secure-store';
import { act, renderHook, userEvent, waitFor } from '@testing-library/react-native';
import { renderRouter, screen } from 'expo-router/testing-library';
import { Platform } from 'react-native';
import { NitroModules } from 'react-native-nitro-modules';

import Inicio from '../../app/inicio';
import InicioDeSesion from '../../app/inicio-de-sesion';
import Registro from '../../app/registro';
import { cerrarSesionDeGoogle, pedirIdTokenDeGoogle } from '../../src/acceso/google';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken, leerToken } from '../../src/sesion/almacen';
import { ProveedorDeSesion, useSesion } from '../../src/sesion/ProveedorDeSesion';

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app: el token queda en
// memoria y se lee con leerToken.
const almacen = SecureStore as unknown as { __vaciar(): void };

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

// eslint-disable-next-line @typescript-eslint/no-require-imports -- la simulación trae su propia utilidad de limpieza
const { reiniciarObjetosHibridos } = require('react-native-nitro-modules') as {
  reiniciarObjetosHibridos: () => void;
};

const google = NitroModules.createHybridObject('NitroGoogleSignin') as unknown as Record<string, jest.Mock>;

// Identificadores de prueba que no pueden estar en el código: si llegan a la librería, salieron del entorno.
const CLIENTE_WEB = 'cliente-web-de-prueba.apps.googleusercontent.com';
const CLIENTE_IOS = 'cliente-ios-de-prueba.apps.googleusercontent.com';

const EXITO = {
  type: 'success',
  data: {
    user: { id: '1', email: 'persona@ejemplo.com', name: null, givenName: null, familyName: null, photo: null },
    scopes: [],
    idToken: 'id-token-de-google',
    serverAuthCode: null,
  },
};
const CANCELADO = { type: 'cancelled', data: null };
const SIN_CUENTA_GUARDADA = { type: 'noSavedCredentialFound', data: null };

const entornoOriginal = {
  web: process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID,
  ios: process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID,
};

function restaurar(nombre: string, valor: string | undefined) {
  if (valor === undefined) {
    delete process.env[nombre];
  } else {
    process.env[nombre] = valor;
  }
}

beforeEach(() => {
  reiniciarObjetosHibridos();
  process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID = CLIENTE_WEB;
  process.env.EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID = CLIENTE_IOS;
  google.checkPlayServices.mockResolvedValue(undefined);
});

afterEach(() => {
  restaurar('EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID', entornoOriginal.web);
  restaurar('EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID', entornoOriginal.ios);
});

describe('registro-E19: Google devuelve el idToken a la app', () => {
  test('registro-E19 — configura la librería con los clientes OAuth del entorno (ADR-0012)', async () => {
    google.signIn.mockResolvedValue(EXITO);

    await pedirIdTokenDeGoogle();

    expect(google.configure).toHaveBeenCalledWith(
      expect.objectContaining({ webClientId: CLIENTE_WEB, iosClientId: CLIENTE_IOS }),
    );
  });

  test('registro-E19 — con la cuenta elegida, entrega el idToken', async () => {
    google.signIn.mockResolvedValue(EXITO);

    await expect(pedirIdTokenDeGoogle()).resolves.toEqual({ tipo: 'exito', idToken: 'id-token-de-google' });
  });

  test('registro-E19 — sin una cuenta ya autorizada, ofrece elegir entre las cuentas del teléfono', async () => {
    google.signIn.mockResolvedValue(SIN_CUENTA_GUARDADA);
    google.createAccount.mockResolvedValue(EXITO);

    await expect(pedirIdTokenDeGoogle()).resolves.toEqual({ tipo: 'exito', idToken: 'id-token-de-google' });
  });

  test('registro-E19 — si la persona cierra la ventana de Google, el resultado es "cancelado", sin error', async () => {
    google.signIn.mockResolvedValue(SIN_CUENTA_GUARDADA);
    google.createAccount.mockResolvedValue(CANCELADO);

    await expect(pedirIdTokenDeGoogle()).resolves.toEqual({ tipo: 'cancelado' });
    expect(google.presentExplicitSignIn).not.toHaveBeenCalled();
  });

  test('registro-E19 — si la cancelación llega como error de la librería, también es "cancelado"', async () => {
    google.signIn.mockRejectedValue(new Error('[SIGN_IN_CANCELLED] La persona cerró la ventana'));

    await expect(pedirIdTokenDeGoogle()).resolves.toEqual({ tipo: 'cancelado' });
  });

  test('registro-E19 — cualquier otro error de la librería se entrega como error, sin lanzarlo', async () => {
    google.checkPlayServices.mockRejectedValue(new Error('[PLAY_SERVICES_NOT_AVAILABLE] Sin Play Services'));

    await expect(pedirIdTokenDeGoogle()).resolves.toEqual({ tipo: 'error', codigo: 'PLAY_SERVICES_NOT_AVAILABLE' });
  });
});

describe('registro-E19 y registro-E20: el botón de Google en las pantallas de entrada', () => {
  const BASE = 'http://api.prueba.invalid/registro-e19';
  const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
  const PANTALLAS = ['/registro', '/inicio-de-sesion'];
  const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
  // El nombre de la app es obligatorio en la configuración: el inicio de sesión lo muestra (tarea 2.3, C-230).
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
  const fetchOriginal = global.fetch;
  let fetchFalso: jest.Mock;

  beforeEach(() => {
    process.env.EXPO_PUBLIC_API_URL = BASE;
    process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba registro-E19';
    fetchFalso = jest.fn();
    global.fetch = fetchFalso as unknown as typeof fetch;
    almacen.__vaciar();
  });

  afterEach(() => {
    restaurar('EXPO_PUBLIC_API_URL', direccionOriginal);
    restaurar('EXPO_PUBLIC_NOMBRE_APP', nombreOriginal);
    global.fetch = fetchOriginal;
    jest.restoreAllMocks();
  });

  function respuesta(status: number, cuerpo: unknown) {
    return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
  }

  async function abrir(ruta: string) {
    await renderRouter(
      { registro: Registro, 'inicio-de-sesion': InicioDeSesion, inicio: Inicio },
      { initialUrl: ruta, wrapper: ProveedorDeSesion },
    );
  }

  test.each(PANTALLAS)(
    'registro-E19 — en %s, con el idToken de Google, la app lo manda al servidor, guarda la sesión y entra',
    async (ruta) => {
      google.signIn.mockResolvedValue(EXITO);
      fetchFalso.mockResolvedValue(respuesta(200, { token: 'token-google', usuario: USUARIO }));
      const usuario = userEvent.setup();
      await abrir(ruta);

      await usuario.press(await screen.findByRole('button', { name: 'Continuar con Google' }));

      expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
      expect(screen).toHavePathname('/inicio');
      await expect(leerToken()).resolves.toBe('token-google');
      const [url, opciones] = fetchFalso.mock.calls[0] as [string, RequestInit];
      expect(url).toBe(`${BASE}/sesion/google`);
      expect(JSON.parse(opciones.body as string)).toEqual({ id_token: 'id-token-de-google' });
    },
  );

  test.each(PANTALLAS)(
    'registro-E19 — en %s, si la persona cierra la ventana de Google, se queda en la pantalla sin mensaje de error',
    async (ruta) => {
      google.signIn.mockResolvedValue(CANCELADO);
      const usuario = userEvent.setup();
      await abrir(ruta);

      await usuario.press(await screen.findByRole('button', { name: 'Continuar con Google' }));

      expect(screen).toHavePathname(ruta);
      expect(screen.queryByRole('alert')).not.toBeOnTheScreen();
      expect(fetchFalso).not.toHaveBeenCalled();
      await expect(leerToken()).resolves.toBeNull();
    },
  );

  test('registro-E19 — si el servidor rechaza el idToken (registro-E17), muestra el error y no entra', async () => {
    google.signIn.mockResolvedValue(EXITO);
    fetchFalso.mockResolvedValue(
      respuesta(422, { message: 'No se pudo validar la cuenta de Google.', errors: { id_token: ['No válido.'] } }),
    );
    const usuario = userEvent.setup();
    await abrir('/inicio-de-sesion');

    await usuario.press(await screen.findByRole('button', { name: 'Continuar con Google' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('No válido.');
    expect(screen).toHavePathname('/inicio-de-sesion');
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E19 — sin el cliente web de Google en la configuración, no ofrece Google y la pantalla abre igual', async () => {
    delete process.env.EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID;
    await abrir('/registro');

    expect(await screen.findByRole('button', { name: 'Crear cuenta' })).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Continuar con Google' })).not.toBeOnTheScreen();
  });

  test.each(PANTALLAS)(
    'registro-E20 — en Android, %s ofrece el correo con la contraseña y Google, y no ofrece Apple',
    async (ruta) => {
      jest.replaceProperty(Platform, 'OS', 'android');
      await abrir(ruta);

      expect(await screen.findByLabelText('Correo electrónico')).toBeOnTheScreen();
      expect(screen.getByLabelText('Contraseña')).toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Continuar con Google' })).toBeOnTheScreen();
      expect(screen.queryByText(/Apple/)).not.toBeOnTheScreen();
    },
  );

  // En iPhone, Google se ofrece igual. Apple en iPhone es la tarea 4.3, bloqueada por DD-069: esta prueba no lo pide.
  test.each(PANTALLAS)('registro-E20 — en iPhone, %s también ofrece Google', async (ruta) => {
    jest.replaceProperty(Platform, 'OS', 'ios');
    await abrir(ruta);

    expect(await screen.findByRole('button', { name: 'Continuar con Google' })).toBeOnTheScreen();
  });
});

// En iPhone, signIn() de la librería recupera sin preguntar la cuenta de Google que quedó abierta. Si cerrar la sesión de
// la app no cierra también la de Google, la siguiente entrada con Google vuelve a la misma cuenta sin dejar elegir otra.
describe('registro-E13 y registro-E19: cerrar la sesión de la app cierra también la de Google', () => {
  const fetchOriginal = global.fetch;

  beforeEach(async () => {
    almacen.__vaciar();
    // La sesión es de esta misma instalación: la marca de instalación ya existe (registro-E32).
    await comprobarInstalacion();
    // Sin conexión al abrir: la sesión guardada se conserva (registro-E11) y el cierre no espera al servidor.
    global.fetch = jest.fn(async () => {
      throw new TypeError('Network request failed');
    }) as unknown as typeof fetch;
  });

  afterEach(() => {
    global.fetch = fetchOriginal;
  });

  async function conSesionAbierta() {
    await guardarToken('token-google');
    const sesion = await renderHook(() => useSesion(), { wrapper: ProveedorDeSesion });
    await waitFor(() => expect(sesion.result.current.estado).toBe('con-sesion'));
    return sesion;
  }

  test('registro-E13 — cerrarSesionDeGoogle pide a la librería cerrar la sesión de Google del teléfono', async () => {
    google.signOut.mockResolvedValue(undefined);

    await cerrarSesionDeGoogle();

    expect(google.signOut).toHaveBeenCalledTimes(1);
  });

  test('registro-E13 — si la librería falla al cerrar la sesión de Google, cerrarSesionDeGoogle no falla', async () => {
    google.signOut.mockRejectedValue(new Error('[SIGN_OUT_FAILED] No se pudo cerrar la sesión'));

    await expect(cerrarSesionDeGoogle()).resolves.toBeUndefined();
  });

  test('registro-E13 — si la librería lanza en vez de rechazar, cerrarSesionDeGoogle tampoco falla', async () => {
    google.signOut.mockImplementation(() => {
      throw new Error('Google no está disponible');
    });

    await expect(cerrarSesionDeGoogle()).resolves.toBeUndefined();
  });

  test('registro-E13 y registro-E19 — al cerrar la sesión de la app se llama a signOut de Google', async () => {
    google.signOut.mockResolvedValue(undefined);
    const { result } = await conSesionAbierta();

    await act(() => result.current.cerrar());

    expect(google.signOut).toHaveBeenCalledTimes(1);
    expect(result.current.estado).toBe('sin-sesion');
  });

  test('registro-E13 — si Google falla al cerrar, la sesión de la app se cierra igual y el token se borra', async () => {
    google.signOut.mockRejectedValue(new Error('[SIGN_OUT_FAILED] No se pudo cerrar la sesión'));
    const { result } = await conSesionAbierta();

    await act(() => result.current.cerrar());

    expect(result.current.estado).toBe('sin-sesion');
    await expect(leerToken()).resolves.toBeNull();
  });
});

describe('registro-E19: la configuración de Expo lleva el complemento de Google', () => {
  const RAIZ = path.resolve(__dirname, '..', '..');
  const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');
  const ENTORNO_BASE = {
    EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba registro-E19',
    EXPO_PUBLIC_API_URL: 'http://api.prueba.invalid/registro-e19',
    APP_IDENTIFIER: 'mx.prueba.registroe19',
  };

  interface ConfiguracionLeida {
    plugins?: (string | [string, unknown])[];
    _internal?: { modResults?: { ios?: { infoPlist?: { CFBundleURLTypes?: { CFBundleURLSchemes: string[] }[] } } } };
  }

  // Lee la configuración como la arma Expo al compilar, solo con el entorno que le da la prueba.
  function leerConfiguracion(extra: Record<string, string>): ConfiguracionLeida {
    const env: NodeJS.ProcessEnv = { ...process.env, EXPO_NO_DOTENV: '1', EXPO_NO_TELEMETRY: '1' };
    for (const nombre of Object.keys(env).filter((clave) => clave.startsWith('EXPO_PUBLIC_'))) {
      delete env[nombre];
    }
    Object.assign(env, ENTORNO_BASE, extra);
    const leida = spawnSync(process.execPath, [CLI_DE_EXPO, 'config', '--type', 'introspect', '--json'], {
      cwd: RAIZ,
      env,
      encoding: 'utf8',
    });
    if (leida.status !== 0) {
      throw new Error(`Expo no pudo leer la configuración: ${leida.stderr}`);
    }
    return JSON.parse(leida.stdout) as ConfiguracionLeida;
  }

  function esquemasDeIphone(configuracion: ConfiguracionLeida): string[] {
    const tipos = configuracion._internal?.modResults?.ios?.infoPlist?.CFBundleURLTypes ?? [];
    return tipos.flatMap((tipo) => tipo.CFBundleURLSchemes);
  }

  function nombresDeComplementos(configuracion: ConfiguracionLeida): string[] {
    return (configuracion.plugins ?? []).map((complemento) =>
      Array.isArray(complemento) ? complemento[0] : complemento,
    );
  }

  test(
    'registro-E19 — con el cliente de iPhone en el entorno, el complemento registra su esquema invertido',
    () => {
      const configuracion = leerConfiguracion({
        EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID: '123-prueba.apps.googleusercontent.com',
      });

      expect(nombresDeComplementos(configuracion)).toContain('react-native-nitro-google-signin');
      expect(esquemasDeIphone(configuracion)).toContain('com.googleusercontent.apps.123-prueba');
    },
    120_000,
  );

  test(
    'registro-E19 — sin el cliente de iPhone, la configuración se arma igual y no inventa un esquema',
    () => {
      const configuracion = leerConfiguracion({});

      expect(esquemasDeIphone(configuracion).some((esquema) => esquema.startsWith('com.googleusercontent'))).toBe(
        false,
      );
    },
    120_000,
  );
});
