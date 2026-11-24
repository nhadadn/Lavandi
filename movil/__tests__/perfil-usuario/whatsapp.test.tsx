// Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, perfil-usuario-E09 y E10 (tarea 2.4). Soporte
// por WhatsApp Business en la pantalla "Ayuda" del Módulo A (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A
// #16): el número viene de la configuración del servidor (GET /api/soporte, decisión 25 y 27 de
// acta-2026-11-20-modelo-y-pasarela) y nunca está escrito en la app. Con número, "Abrir WhatsApp" abre
// https://wa.me/<número>, que el teléfono abre en WhatsApp; sin número, la opción aparece como no disponible y la app no
// abre ningún enlace. La entrada es "Ayuda" en la pantalla principal.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado. Que el
// teléfono abra de verdad WhatsApp con el chat de ese número se revisa en un teléfono real.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Linking, Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-whatsapp';
const TOKEN = 'token-perfil-whatsapp';
const USUARIO = { id_publico: '01JD0000000000000000000PW1', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Un número de configuración de prueba, como lo entrega el servidor: solo sus dígitos.
const NUMERO = '5210000000001';

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
let abrirEnlace: jest.SpyInstance;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso: reconoce el token y da el número de soporte que se le diga.
function servidor(soporte: () => ReturnType<typeof respuesta>) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/soporte' && metodo === 'GET') {
      return soporte();
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

function consultasDelSoporte(): string[] {
  return fetchFalso.mock.calls
    .filter(([url]: [string]) => url === `${BASE}/soporte`)
    .map(([, opciones]: [string, RequestInit]) => (opciones.headers as Record<string, string>).Authorization);
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión abre Ayuda y espera a que llegue el número de soporte.
async function abrirAyuda(plataforma: Plataforma) {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: '/ayuda' });
  await screen.findByRole('header', { name: 'Ayuda' });
  await waitFor(() => expect(consultasDelSoporte()).toHaveLength(1));
  await waitFor(() => expect(screen.queryByText('Buscando el número de soporte…')).not.toBeOnTheScreen());
}

function whatsapp() {
  return within(screen.getByTestId('soporte-whatsapp'));
}

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-whatsapp';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  // En las pruebas, Linking.openURL ya es una simulación de react-native que guarda las llamadas de todo el archivo:
  // se vacía antes de cada prueba.
  abrirEnlace = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
  abrirEnlace.mockClear();
  servidor(() => respuesta(200, { whatsapp: NUMERO }));
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

describe('perfil-usuario-E09: el usuario abre el chat de soporte', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E09 — (%s) "Ayuda" en la pantalla principal abre Soporte, que pide el número con la sesión',
    async (plataforma) => {
      await conSesion(plataforma);
      await renderRouter(APP, { initialUrl: '/inicio' });

      await fireEvent.press(await screen.findByRole('link', { name: 'Ayuda' }));

      await waitFor(() => expect(screen).toHavePathname('/ayuda'));
      expect(await screen.findByRole('header', { name: 'Ayuda' })).toBeOnTheScreen();
      await waitFor(() => expect(consultasDelSoporte()).toEqual([`Bearer ${TOKEN}`]));
    },
  );

  test.each(PLATAFORMAS)(
    'perfil-usuario-E09 — (%s) "Abrir WhatsApp" abre el chat con el número de la configuración',
    async (plataforma) => {
      await abrirAyuda(plataforma);

      const boton = whatsapp().getByRole('button', { name: 'Abrir WhatsApp' });
      expect(boton).toBeEnabled();
      await fireEvent.press(boton);

      expect(abrirEnlace).toHaveBeenCalledTimes(1);
      expect(abrirEnlace).toHaveBeenCalledWith(`https://wa.me/${NUMERO}`);
    },
  );

  test('perfil-usuario-E09 — el número no está escrito en la app: con otro número de la configuración, abre ese', async () => {
    servidor(() => respuesta(200, { whatsapp: '5210000000002' }));
    await abrirAyuda('android');

    await fireEvent.press(whatsapp().getByRole('button', { name: 'Abrir WhatsApp' }));

    expect(abrirEnlace).toHaveBeenCalledWith('https://wa.me/5210000000002');
  });

  test('perfil-usuario-E09 — si el teléfono no puede abrir el enlace, la app no se cae y lo dice', async () => {
    abrirEnlace.mockRejectedValue(new Error('No se puede abrir'));
    await abrirAyuda('ios');

    await fireEvent.press(whatsapp().getByRole('button', { name: 'Abrir WhatsApp' }));

    expect(await whatsapp().findByText('No se pudo abrir WhatsApp. Intenta de nuevo.')).toBeOnTheScreen();
  });
});

describe('perfil-usuario-E10: sin número configurado', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E10 — (%s) sin número, la opción de WhatsApp aparece como no disponible y no abre un enlace vacío',
    async (plataforma) => {
      servidor(() => respuesta(200, { whatsapp: null }));
      await abrirAyuda(plataforma);

      const boton = whatsapp().getByRole('button', { name: 'WhatsApp no disponible' });
      expect(boton).toBeDisabled();
      expect(whatsapp().getByText('Por ahora no tenemos un número de WhatsApp de soporte.')).toBeOnTheScreen();
      expect(whatsapp().queryByRole('button', { name: 'Abrir WhatsApp' })).not.toBeOnTheScreen();

      await fireEvent.press(boton);

      expect(abrirEnlace).not.toHaveBeenCalled();
    },
  );

  test.each([
    ['vacío', ''],
    ['sin dígitos', '+ -'],
    ['con algo más que dígitos', '+52 1 000'],
    ['que no es texto', 5210000000001],
  ])('perfil-usuario-E10 — un número %s cuenta como sin número: no se arma un enlace con él', async (_caso, numero) => {
    servidor(() => respuesta(200, { whatsapp: numero }));
    await abrirAyuda('android');

    const boton = whatsapp().getByRole('button', { name: 'WhatsApp no disponible' });
    await fireEvent.press(boton);

    expect(abrirEnlace).not.toHaveBeenCalled();
  });

  test('perfil-usuario-E10 — si el número no llega, la opción no está disponible y lo dice con el mensaje general', async () => {
    servidor(() => {
      throw new TypeError('Network request failed');
    });
    await abrirAyuda('ios');

    expect(whatsapp().getByRole('button', { name: 'WhatsApp no disponible' })).toBeDisabled();
    expect(
      whatsapp().getByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(abrirEnlace).not.toHaveBeenCalled();
  });
});
