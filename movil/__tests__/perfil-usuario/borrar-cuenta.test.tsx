// Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, perfil-usuario-E15 (tarea 2.6). Borrar la
// cuenta desde "Mi perfil" (ADR-0005: "Borrar la cuenta se puede desde la app, como piden las dos tiendas"). La app pide
// confirmación con el diálogo del sistema antes de llamar a DELETE /api/cuenta; con el 204, borra la sesión del teléfono
// como "Cerrar sesión" (registro-E13) y vuelve a la bienvenida como primera pantalla.
//
// Que la cuenta ya no pueda iniciar sesión, ni con correo ni con Google, y que sus tokens queden revocados en todos sus
// teléfonos se prueba en el servidor (tarea 1.5). Aquí el servidor va simulado: la prueba comprueba que la app no borra
// nada sin la confirmación, que manda el borrado con su sesión y que después queda sin sesión. Qué pasa con los datos
// de la cuenta borrada es perfil-usuario-E16, bloqueado por DD-109. Se abre la app real, con las rutas de movil/app/
// (ADR-0017), en iPhone y en Android. El diálogo real de cada sistema se revisa en un teléfono real.
import path from 'node:path';

import { act, fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import { router } from 'expo-router';
import * as SecureStore from 'expo-secure-store';
import { Alert, Platform, type AlertButton } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken, leerToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-borrar-cuenta';
const TOKEN = 'token-perfil-borrar-cuenta';
const USUARIO = { id_publico: '01JD0000000000000000000PB1', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
let dialogo: jest.SpyInstance;
// Si el servidor falso ya borró la cuenta: desde entonces, su token responde 401.
let borrada: boolean;

function respuesta(status: number, cuerpo?: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso: reconoce el token mientras la cuenta exista, da el perfil y borra la cuenta como la API: 204 y,
// desde ahí, el token ya no sirve, también para revocar la sesión.
function servidor({ borrar }: { borrar?: () => ReturnType<typeof respuesta> } = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (borrada) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/cuenta' && metodo === 'GET') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/perfil' && metodo === 'GET') {
      return respuesta(200, {
        perfil: { id_publico: USUARIO.id_publico, nombre: 'Nombre de prueba', correo: USUARIO.correo, foto: null },
      });
    }
    if (ruta === '/direcciones/favoritas' && metodo === 'GET') {
      return respuesta(200, { direcciones: [] });
    }
    if (ruta === '/cuenta' && metodo === 'DELETE') {
      if (borrar) {
        return borrar();
      }
      borrada = true;
      return respuesta(204);
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

function borradosMandados(): string[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/cuenta` && opciones?.method === 'DELETE')
    .map(([, opciones]: [string, RequestInit]) => (opciones.headers as Record<string, string>).Authorization);
}

async function abrirElPerfil(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: '/perfil' });
  await screen.findByRole('header', { name: 'Mi perfil' });
  await waitFor(() => expect(screen.getByLabelText('Nombre')).toHaveDisplayValue('Nombre de prueba'));
}

// Toca "Eliminar mi cuenta" y devuelve los botones del diálogo de confirmación que abrió.
async function pedirBorrarLaCuenta(): Promise<AlertButton[]> {
  await fireEvent.press(screen.getByRole('button', { name: 'Eliminar mi cuenta' }));
  expect(dialogo).toHaveBeenCalledTimes(1);
  return dialogo.mock.calls[0][2] as AlertButton[];
}

async function tocar(botones: AlertButton[], texto: string) {
  const boton = botones.find((opcion) => opcion.text === texto);
  expect(boton).toBeDefined();
  await act(async () => {
    boton?.onPress?.();
  });
}

beforeEach(() => {
  almacen.__vaciar();
  borrada = false;
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-borrar-cuenta';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo: se vacía antes
  // de cada prueba.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
  servidor();
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
});

describe('perfil-usuario-E15: el usuario borra su cuenta', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E15 — (%s) "Eliminar mi cuenta" pide confirmación antes de borrar nada',
    async (plataforma) => {
      await abrirElPerfil(plataforma);

      const botones = await pedirBorrarLaCuenta();

      const [titulo, mensaje] = dialogo.mock.calls[0];
      expect(titulo).toBe('¿Eliminar tu cuenta?');
      expect(mensaje).toBe('No se puede deshacer.');
      expect(botones.map(({ text, style }) => ({ text, style }))).toEqual([
        { text: 'Cancelar', style: 'cancel' },
        { text: 'Eliminar', style: 'destructive' },
      ]);
      expect(borradosMandados()).toEqual([]);
    },
  );

  test.each(PLATAFORMAS)(
    'perfil-usuario-E15 — (%s) al confirmar, borra la cuenta con su sesión, la quita del teléfono y vuelve a la bienvenida',
    async (plataforma) => {
      await abrirElPerfil(plataforma);

      await tocar(await pedirBorrarLaCuenta(), 'Eliminar');

      expect(borradosMandados()).toEqual([`Bearer ${TOKEN}`]);
      await waitFor(() => expect(screen).toHavePathname('/bienvenida'));
      expect(await screen.findByTestId('pantalla-inicial')).toBeOnTheScreen();
      expect(await leerToken()).toBeNull();
      // La bienvenida queda como primera pantalla: "atrás" ya no regresa al perfil de la cuenta borrada.
      expect(router.canGoBack()).toBe(false);
    },
  );

  test('perfil-usuario-E15 — después de borrarla, las pantallas de la sesión ya no se abren', async () => {
    await abrirElPerfil('android');
    await tocar(await pedirBorrarLaCuenta(), 'Eliminar');
    await waitFor(() => expect(screen).toHavePathname('/bienvenida'));

    await act(async () => {
      router.push('/perfil');
    });

    await waitFor(() => expect(screen).not.toHavePathname('/perfil'));
    expect(screen.queryByRole('header', { name: 'Mi perfil' })).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)('perfil-usuario-E15 — (%s) "Cancelar" no borra nada y deja el perfil como estaba', async (plataforma) => {
    await abrirElPerfil(plataforma);

    await tocar(await pedirBorrarLaCuenta(), 'Cancelar');

    expect(borradosMandados()).toEqual([]);
    expect(screen).toHavePathname('/perfil');
    expect(await leerToken()).toBe(TOKEN);
  });

  test('perfil-usuario-E15 — si el borrado no llega al servidor, lo dice con el mensaje general y conserva la sesión', async () => {
    servidor({
      borrar: () => {
        throw new TypeError('Network request failed');
      },
    });
    await abrirElPerfil('ios');

    await tocar(await pedirBorrarLaCuenta(), 'Eliminar');

    expect(
      await screen.findByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(screen).toHavePathname('/perfil');
    expect(await leerToken()).toBe(TOKEN);
  });

  test('perfil-usuario-E15 — si el servidor falla al borrarla, lo dice con el mensaje general y conserva la sesión', async () => {
    servidor({ borrar: () => respuesta(500, { message: 'Server Error' }) });
    await abrirElPerfil('android');

    await tocar(await pedirBorrarLaCuenta(), 'Eliminar');

    expect(await screen.findByText('Algo salió mal. Intenta de nuevo.')).toBeOnTheScreen();
    expect(screen).toHavePathname('/perfil');
    expect(await leerToken()).toBe(TOKEN);
  });
});
