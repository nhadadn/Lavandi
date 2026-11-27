// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E11 y registro-E13.
// La sesión que se conserva al volver a abrir la app y que se borra al cerrarla (ADR-0018), y las pantallas que se ven
// con ella y sin ella. Se abre la app real, con las rutas de movil/app/ (ADR-0017); el almacenamiento cifrado es la
// simulación en memoria de __mocks__/expo-secure-store.ts y el servidor va simulado.
import path from 'node:path';

import * as SecureStore from 'expo-secure-store';
import { act, userEvent } from '@testing-library/react-native';
import { router } from 'expo-router';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken, leerToken } from '../../src/sesion/almacen';
import { color } from '../../src/tema/theme';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/registro-sesion';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };

const almacen = SecureStore as unknown as { __vaciar(): void };

type Ruta = `${'GET' | 'POST' | 'DELETE'} ${string}`;

function respuesta(status: number, cuerpo?: unknown) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => {
      if (cuerpo === undefined) {
        throw new SyntaxError('Respuesta sin cuerpo');
      }
      return cuerpo;
    },
  };
}

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const plazoOriginal = process.env.EXPO_PUBLIC_API_PLAZO_MS;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

// El servidor acepta la conexión y no contesta: la petición solo termina si la app la cancela con su señal.
const NO_CONTESTA = 'no-contesta';
// Las peticiones que siguen esperando al terminar cada prueba: se cortan en afterEach, como si el servidor cerrara la
// conexión, para que ningún plazo quede corriendo.
let sinContestar: (() => void)[] = [];

function errorDeCorte() {
  return Object.assign(new Error('Aborted'), { name: 'AbortError' });
}

// Programa el servidor falso: cada "MÉTODO /ruta" con su respuesta, con un error de red o sin contestar.
function servidor(respuestas: Partial<Record<Ruta, ReturnType<typeof respuesta> | Error | typeof NO_CONTESTA>>) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const clave = `${opciones.method ?? 'GET'} ${url.slice(BASE.length)}` as Ruta;
    const programada = respuestas[clave];
    if (programada instanceof Error) {
      throw programada;
    }
    if (programada === NO_CONTESTA) {
      return new Promise((_resolver, rechazar) => {
        sinContestar.push(() => rechazar(errorDeCorte()));
        opciones.signal?.addEventListener('abort', () => rechazar(errorDeCorte()));
      });
    }
    if (!programada) {
      throw new Error(`El servidor falso no esperaba ${clave}`);
    }
    return programada;
  });
}

function peticiones(): string[] {
  return fetchFalso.mock.calls.map(([url, opciones]: [string, RequestInit | undefined]) => {
    const encabezados = (opciones?.headers ?? {}) as Record<string, string>;
    return `${opciones?.method ?? 'GET'} ${url.slice(BASE.length)} ${encabezados.Authorization ?? 'sin token'}`;
  });
}

// La persona que ya inició sesión antes, en esta misma instalación (registro-E32): vio la bienvenida y tiene su token
// en el teléfono.
async function conSesionGuardada(token: string) {
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(token);
}

// Abre la app como al tocar su ícono.
async function abrirLaApp() {
  await renderRouter(APP, { initialUrl: '/' });
}

// Sin sesión, la persona llega al inicio de sesión, con su enlace a registrarse (bienvenida-E04, C-237).
async function esperarElInicioDeSesion() {
  await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
  expect(await screen.findByRole('link', { name: '¿No tienes cuenta? Regístrate' })).toBeOnTheScreen();
}

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión muestra el nombre de la app, que sale de la configuración (base-E09).
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba registro-E11';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
});

afterEach(() => {
  sinContestar.forEach((cortar) => cortar());
  sinContestar = [];
  if (plazoOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_API_PLAZO_MS;
  } else {
    process.env.EXPO_PUBLIC_API_PLAZO_MS = plazoOriginal;
  }
  if (direccionOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_API_URL;
  } else {
    process.env.EXPO_PUBLIC_API_URL = direccionOriginal;
  }
  if (nombreOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_NOMBRE_APP;
  } else {
    process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
  }
  global.fetch = fetchOriginal;
});

describe('registro-E11: al volver a abrir la app, sigue dentro', () => {
  test('registro-E11 — con un token que el servidor reconoce, entra sin pedir el correo ni la contraseña', async () => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': respuesta(200, { usuario: USUARIO }) });

    await abrirLaApp();

    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio');
    expect(screen.queryByLabelText('Contraseña')).not.toBeOnTheScreen();
    expect(peticiones()).toEqual(['GET /cuenta Bearer token-guardado']);
  });

  test('registro-E11 — si el servidor ya no reconoce el token, lo borra del teléfono y lleva al inicio de sesión', async () => {
    await conSesionGuardada('token-revocado');
    servidor({ 'GET /cuenta': respuesta(401, { message: 'Unauthenticated.' }) });

    await abrirLaApp();

    await esperarElInicioDeSesion();
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E11 — sin conexión al abrir, no borra la sesión: solo el servidor dice que ya no sirve', async () => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': new TypeError('Network request failed') });

    await abrirLaApp();

    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio');
    await expect(leerToken()).resolves.toBe('token-guardado');
  });

  test('registro-E11 — si el servidor acepta y no contesta, al vencer el plazo entra con su sesión, como sin conexión', async () => {
    // Un plazo corto para la prueba; en la app es de 15 s si la configuración no dice otro.
    process.env.EXPO_PUBLIC_API_PLAZO_MS = '50';
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': NO_CONTESTA });

    await abrirLaApp();

    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio');
    await expect(leerToken()).resolves.toBe('token-guardado');
  });
});

describe('registro-E13: cerrar la sesión la borra del teléfono', () => {
  test('registro-E13 — pide al servidor revocar el token, lo borra del teléfono y lleva al inicio de sesión', async () => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': respuesta(200, { usuario: USUARIO }), 'DELETE /sesion': respuesta(204) });
    const usuario = userEvent.setup();
    await abrirLaApp();

    await usuario.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await esperarElInicioDeSesion();
    expect(peticiones()).toContain('DELETE /sesion Bearer token-guardado');
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E13 — si el servidor no responde, igual la borra del teléfono y lleva al inicio de sesión', async () => {
    await conSesionGuardada('token-guardado');
    servidor({
      'GET /cuenta': respuesta(200, { usuario: USUARIO }),
      'DELETE /sesion': new TypeError('Network request failed'),
    });
    const usuario = userEvent.setup();
    await abrirLaApp();

    await usuario.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await esperarElInicioDeSesion();
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E13 — borra el token del teléfono antes de pedir al servidor que lo revoque', async () => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': respuesta(200, { usuario: USUARIO }), 'DELETE /sesion': respuesta(204) });
    const programado = fetchFalso.getMockImplementation()!;
    let tokenEnElTelefonoAlRevocar: string | null | undefined;
    fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
      if (opciones.method === 'DELETE') {
        tokenEnElTelefonoAlRevocar = await leerToken();
      }
      return programado(url, opciones);
    });
    const usuario = userEvent.setup();
    await abrirLaApp();

    await usuario.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await esperarElInicioDeSesion();
    expect(peticiones()).toContain('DELETE /sesion Bearer token-guardado');
    expect(tokenEnElTelefonoAlRevocar).toBeNull();
  });

  test('registro-E13 — si el servidor acepta y no contesta, no lo espera: la borra del teléfono y lleva al inicio de sesión', async () => {
    // El plazo es el de 15 s por omisión: la app no debe esperarlo para salir.
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': respuesta(200, { usuario: USUARIO }), 'DELETE /sesion': NO_CONTESTA });
    const usuario = userEvent.setup();
    await abrirLaApp();

    await usuario.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await esperarElInicioDeSesion();
    await expect(leerToken()).resolves.toBeNull();
    expect(peticiones()).toContain('DELETE /sesion Bearer token-guardado');
  });

  test('registro-E13 — si el teléfono no puede borrar el token, igual cierra la sesión en la app y lleva al inicio de sesión', async () => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': respuesta(200, { usuario: USUARIO }), 'DELETE /sesion': respuesta(204) });
    const usuario = userEvent.setup();
    await abrirLaApp();
    const cerrar = await screen.findByRole('button', { name: 'Cerrar sesión' });
    jest.mocked(SecureStore.deleteItemAsync).mockRejectedValueOnce(new Error('El almacén cifrado no respondió'));

    await usuario.press(cerrar);

    await esperarElInicioDeSesion();
    // El servidor revoca el token aunque el teléfono no lo haya podido borrar: si vuelve a abrir, el servidor ya no
    // lo reconoce (registro-E11).
    expect(peticiones()).toContain('DELETE /sesion Bearer token-guardado');
  });
});

// Las pantallas se ven según la sesión: con sesión no se ven el registro, el inicio de sesión ni la recuperación; sin
// sesión no se ve el inicio; mientras se comprueba, no se ve ninguna. Sin esto, un enlace directo abría el inicio sin
// sesión, y "atrás" después de entrar regresaba al formulario con la sesión abierta: volver a entrar sobrescribía el
// token sin revocar el anterior, que seguía vivo en el servidor.
describe('registro-E11, registro-E09 y registro-E13: las pantallas según la sesión', () => {
  const PANTALLAS_SIN_SESION = ['/registro', '/inicio-de-sesion', '/recuperar-contrasena'];

  // La persona que ya abrió la app en esta instalación y vio la bienvenida, sin sesión (bienvenida-E02).
  async function sinSesionYaVioLaBienvenida() {
    await comprobarInstalacion();
    await marcarBienvenidaVista();
  }

  async function abrirEn(ruta: string) {
    await renderRouter(APP, { initialUrl: ruta });
  }

  async function iniciarSesion() {
    const usuario = userEvent.setup();
    await esperarElInicioDeSesion();
    await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona@ejemplo.com');
    await usuario.type(screen.getByLabelText('Contraseña'), 'secreta123');
    await usuario.press(screen.getByRole('button', { name: 'Iniciar sesión' }));
    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    return usuario;
  }

  test('registro-E11 — sin sesión, abrir el inicio directo (con un enlace) lleva al inicio de sesión', async () => {
    await sinSesionYaVioLaBienvenida();
    servidor({});

    await abrirEn('/inicio');

    await esperarElInicioDeSesion();
    expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).not.toBeOnTheScreen();
  });

  test('registro-E11 — si el servidor ya no reconoce el token, abrir el inicio directo lleva al inicio de sesión y lo borra', async () => {
    await conSesionGuardada('token-revocado');
    servidor({ 'GET /cuenta': respuesta(401, { message: 'Unauthenticated.' }) });

    await abrirEn('/inicio');

    await esperarElInicioDeSesion();
    expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).not.toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E11 — mientras la app comprueba la sesión con el servidor, no muestra el inicio', async () => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': NO_CONTESTA });

    await abrirEn('/inicio');
    // renderRouter usa el reloj falso de Jest: se adelanta un momento, menos que el plazo de la petición.
    await act(() => jest.advanceTimersByTimeAsync(100));

    expect(screen.queryByRole('button', { name: 'Cerrar sesión' })).not.toBeOnTheScreen();
    expect(screen.queryByRole('header', { name: 'Lavanderías cerca de ti' })).not.toBeOnTheScreen();
    // Lo único que se ve es el fondo de la app, como en las pantallas: sin el blanco de la vista nativa (ADR-0013).
    expect(screen.getByTestId('leyendo-la-sesion')).toHaveStyle({ flex: 1, backgroundColor: color.semantico.fondo });
  });

  test.each(PANTALLAS_SIN_SESION)('registro-E11 — con la sesión abierta, abrir %s lleva al inicio', async (ruta) => {
    await conSesionGuardada('token-guardado');
    servidor({ 'GET /cuenta': respuesta(200, { usuario: USUARIO }) });

    await abrirEn(ruta);

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
  });

  test('registro-E09 — después de entrar, "atrás" no regresa al inicio de sesión', async () => {
    await sinSesionYaVioLaBienvenida();
    servidor({ 'POST /sesion': respuesta(200, { token: 'token-nuevo', usuario: USUARIO }) });
    await abrirLaApp();

    await iniciarSesion();

    await waitFor(() => expect(router.canGoBack()).toBe(false));
    expect(screen).toHavePathname('/inicio');
    await expect(leerToken()).resolves.toBe('token-nuevo');
  });

  test('registro-E13 — después de entrar y cerrar la sesión, el inicio de sesión queda como primera pantalla: sin "Regresar" y "atrás" no regresa al inicio', async () => {
    await sinSesionYaVioLaBienvenida();
    servidor({
      'POST /sesion': respuesta(200, { token: 'token-nuevo', usuario: USUARIO }),
      'DELETE /sesion': respuesta(204),
    });
    await abrirLaApp();
    const usuario = await iniciarSesion();

    await usuario.press(screen.getByRole('button', { name: 'Cerrar sesión' }));

    await esperarElInicioDeSesion();
    await waitFor(() => expect(router.canGoBack()).toBe(false));
    expect(screen).toHavePathname('/inicio-de-sesion');
    expect(screen.queryByRole('button', { name: 'Regresar' })).not.toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  });
});
