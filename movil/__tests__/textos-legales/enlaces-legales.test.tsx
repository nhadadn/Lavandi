// Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md, textos-legales-E05 (tarea 2.1). La app abre el
// aviso de privacidad y los términos y condiciones vigentes en las mismas direcciones públicas del sitio (/aviso-de-privacidad
// y /terminos, que sirve servidor/), tomadas de la configuración (EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL y
// EXPO_PUBLIC_TERMINOS_URL) y no escritas en el código (NSV-2026-001-v8.pdf:31:ANEXO B, Sección B.7).
//
// Dónde y cómo, por delegación de Arthur (C-236; design.md de textos-legales, preguntas abiertas 2 y 3): en el registro,
// junto al consentimiento (registro-E28), y en "Mi perfil", en la sección "Legal" del mock A15, para que la persona los
// encuentre también con su sesión abierta; los dos se abren en la vista de navegador del sistema dentro de la app, con
// openBrowserAsync de expo-web-browser, como el checkout del pago (ADR-0010 y su adenda del 2026-11-22). El documento
// vigente es el que sirve esa dirección: una versión nueva no cambia la app (textos-legales-E04).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado del
// perfil. La vista real de cada sistema se prueba en un teléfono.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import * as WebBrowser from 'expo-web-browser';
import { Linking, Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/textos-legales';
const TOKEN = 'token-textos-legales';
const USUARIO = { id_publico: '01JD0000000000000000000TL1', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Las direcciones públicas del sitio de prueba, con las rutas que sirve servidor/ (design.md de textos-legales).
const AVISO = 'https://sitio.prueba.invalid/aviso-de-privacidad';
const TERMINOS = 'https://sitio.prueba.invalid/terminos';

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const navegador = WebBrowser as unknown as typeof import('../../__mocks__/expo-web-browser');

const VARIABLES = [
  'EXPO_PUBLIC_API_URL',
  'EXPO_PUBLIC_NOMBRE_APP',
  'EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL',
  'EXPO_PUBLIC_TERMINOS_URL',
] as const;
const originales = Object.fromEntries(VARIABLES.map((variable) => [variable, process.env[variable]]));
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso: la cuenta y el perfil del usuario, para abrir "Mi perfil".
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
    if (ruta === '/direcciones/favoritas' && metodo === 'GET') {
      return respuesta(200, { direcciones: [] });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

async function abrirElRegistro(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await renderRouter(APP, { initialUrl: '/registro' });
  await screen.findByRole('header', { name: 'Crear cuenta' });
}

async function abrirMiPerfil(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: '/perfil' });
  await screen.findByRole('header', { name: 'Mi perfil' });
}

beforeEach(() => {
  almacen.__vaciar();
  navegador.__reiniciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba textos-legales';
  process.env.EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL = AVISO;
  process.env.EXPO_PUBLIC_TERMINOS_URL = TERMINOS;
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
});

afterEach(() => {
  for (const variable of VARIABLES) {
    const original = originales[variable];
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
  jest.restoreAllMocks();
});

describe('textos-legales-E05: abrir el aviso y los términos', () => {
  test.each(PLATAFORMAS)(
    'textos-legales-E05 — (%s) en el registro, el enlace al aviso de privacidad abre su dirección de la configuración dentro de la app, y el de los términos la suya',
    async (plataforma) => {
      const abrirFuera = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
      await abrirElRegistro(plataforma);

      await fireEvent.press(screen.getByRole('link', { name: 'Aviso de privacidad' }));
      await waitFor(() => expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(1));
      expect(navegador.openBrowserAsync).toHaveBeenLastCalledWith(AVISO);

      await fireEvent.press(screen.getByRole('link', { name: 'Términos y condiciones' }));
      await waitFor(() => expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(2));
      expect(navegador.openBrowserAsync).toHaveBeenLastCalledWith(TERMINOS);
      // Dentro de la app: ni Safari ni Chrome.
      expect(abrirFuera).not.toHaveBeenCalled();
    },
  );

  test.each(PLATAFORMAS)(
    'textos-legales-E05 — (%s) en "Mi perfil", la sección "Legal" lleva los dos enlaces y cada uno abre su dirección de la configuración dentro de la app',
    async (plataforma) => {
      const abrirFuera = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
      await abrirMiPerfil(plataforma);

      const legal = within(await screen.findByTestId('enlaces-legales'));
      expect(legal.getByRole('header', { name: 'Legal' })).toBeOnTheScreen();

      await fireEvent.press(legal.getByRole('link', { name: 'Aviso de privacidad' }));
      await waitFor(() => expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(1));
      expect(navegador.openBrowserAsync).toHaveBeenLastCalledWith(AVISO);

      await fireEvent.press(legal.getByRole('link', { name: 'Términos y condiciones' }));
      await waitFor(() => expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(2));
      expect(navegador.openBrowserAsync).toHaveBeenLastCalledWith(TERMINOS);
      expect(abrirFuera).not.toHaveBeenCalled();
    },
  );

  test('textos-legales-E05 — con otras direcciones en la configuración, abre esas: la app no trae ninguna escrita', async () => {
    process.env.EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL = 'https://otro-sitio.prueba.invalid/aviso-de-privacidad';
    process.env.EXPO_PUBLIC_TERMINOS_URL = 'https://otro-sitio.prueba.invalid/terminos';
    await abrirMiPerfil('android');

    const legal = within(await screen.findByTestId('enlaces-legales'));
    await fireEvent.press(legal.getByRole('link', { name: 'Aviso de privacidad' }));
    await fireEvent.press(legal.getByRole('link', { name: 'Términos y condiciones' }));

    await waitFor(() => expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(2));
    expect(navegador.openBrowserAsync.mock.calls.map(([direccion]) => direccion)).toEqual([
      'https://otro-sitio.prueba.invalid/aviso-de-privacidad',
      'https://otro-sitio.prueba.invalid/terminos',
    ]);
  });

  test('textos-legales-E05 — sin la dirección de un documento en la configuración, su enlace no aparece y el otro sí', async () => {
    delete process.env.EXPO_PUBLIC_TERMINOS_URL;
    await abrirMiPerfil('ios');

    const legal = within(await screen.findByTestId('enlaces-legales'));
    expect(legal.getByRole('link', { name: 'Aviso de privacidad' })).toBeOnTheScreen();
    expect(screen.queryByRole('link', { name: 'Términos y condiciones' })).not.toBeOnTheScreen();
  });

  test('textos-legales-E05 — sin ninguna de las dos direcciones, "Mi perfil" no muestra la sección "Legal"', async () => {
    delete process.env.EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL;
    delete process.env.EXPO_PUBLIC_TERMINOS_URL;
    await abrirMiPerfil('android');

    expect(screen.queryByTestId('enlaces-legales')).not.toBeOnTheScreen();
    expect(screen.queryByRole('header', { name: 'Legal' })).not.toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Eliminar mi cuenta' })).toBeOnTheScreen();
  });

  test('textos-legales-E05 — si la vista del navegador no se puede abrir, la pantalla sigue como estaba', async () => {
    navegador.openBrowserAsync.mockRejectedValueOnce(new Error('No se pudo abrir la vista.'));
    await abrirMiPerfil('ios');

    const legal = within(await screen.findByTestId('enlaces-legales'));
    await fireEvent.press(legal.getByRole('link', { name: 'Aviso de privacidad' }));

    await waitFor(() => expect(navegador.openBrowserAsync).toHaveBeenCalledTimes(1));
    expect(screen.getByRole('header', { name: 'Mi perfil' })).toBeOnTheScreen();
    expect(legal.getByRole('link', { name: 'Términos y condiciones' })).toBeOnTheScreen();
  });
});
