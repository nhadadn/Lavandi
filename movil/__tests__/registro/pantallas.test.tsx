// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E04, registro-E09 y registro-E28.
// Las pantallas de registro y de inicio de sesión contra la API (fetch simulado), con expo-router (ADR-0017).
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

import * as SecureStore from 'expo-secure-store';
import * as WebBrowser from 'expo-web-browser';
import { userEvent } from '@testing-library/react-native';
import { renderRouter, screen, within } from 'expo-router/testing-library';

import Inicio from '../../app/inicio';
import InicioDeSesion from '../../app/inicio-de-sesion';
import Registro from '../../app/registro';
import { leerToken } from '../../src/sesion/almacen';
import { ProveedorDeSesion } from '../../src/sesion/ProveedorDeSesion';

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app: el token queda en
// memoria y se lee con leerToken.
const almacen = SecureStore as unknown as { __vaciar(): void };

// La primera escritura con userEvent tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

// Valores de prueba que no pueden estar en el código: si aparecen, salieron del entorno.
const BASE = 'http://api.prueba.invalid/registro-pantallas';
const AVISO = 'https://documentos.prueba.invalid/aviso-de-privacidad-e28';
const TERMINOS = 'https://documentos.prueba.invalid/terminos-e28';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };

interface RespuestaFalsa {
  ok: boolean;
  status: number;
  json: () => Promise<unknown>;
}

function respuesta(status: number, cuerpo: unknown): RespuestaFalsa {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El nombre de la app es obligatorio en la configuración: el inicio de sesión lo muestra (tarea 2.3, C-230).
const VARIABLES = [
  'EXPO_PUBLIC_API_URL',
  'EXPO_PUBLIC_NOMBRE_APP',
  'EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL',
  'EXPO_PUBLIC_TERMINOS_URL',
] as const;
const originales = Object.fromEntries(VARIABLES.map((nombre) => [nombre, process.env[nombre]]));
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

beforeEach(() => {
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba registro';
  process.env.EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL = AVISO;
  process.env.EXPO_PUBLIC_TERMINOS_URL = TERMINOS;
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  almacen.__vaciar();
});

afterEach(() => {
  for (const nombre of VARIABLES) {
    const original = originales[nombre];
    if (original === undefined) {
      delete process.env[nombre];
    } else {
      process.env[nombre] = original;
    }
  }
  global.fetch = fetchOriginal;
  jest.restoreAllMocks();
});

async function abrir(ruta: string) {
  await renderRouter(
    { registro: Registro, 'inicio-de-sesion': InicioDeSesion, inicio: Inicio },
    { initialUrl: ruta, wrapper: ProveedorDeSesion },
  );
}

async function llenar(correo: string, password: string) {
  const usuario = userEvent.setup();
  await usuario.type(await screen.findByLabelText('Correo electrónico'), correo);
  await usuario.type(screen.getByLabelText('Contraseña'), password);
  return usuario;
}

function llamada(indice = 0): { url: string; cuerpo: unknown } {
  const [url, opciones] = fetchFalso.mock.calls[indice] as [string, RequestInit];
  return { url, cuerpo: JSON.parse(opciones.body as string) };
}

describe('registro-E04: la pantalla de registro crea la cuenta y muestra los errores', () => {
  test('registro-E04 — pide solo el correo y la contraseña (C-225)', async () => {
    await abrir('/registro');

    expect(await screen.findByLabelText('Correo electrónico')).toBeOnTheScreen();
    expect(screen.getByLabelText('Contraseña')).toBeOnTheScreen();
    expect(screen.queryByLabelText('Nombre')).not.toBeOnTheScreen();
  });

  test('registro-E04 — con la cuenta creada, guarda la sesión y entra al inicio', async () => {
    fetchFalso.mockResolvedValue(respuesta(201, { token: 'token-registro', usuario: USUARIO }));
    await abrir('/registro');

    const usuario = await llenar('persona@ejemplo.com', 'secreta123');
    await usuario.press(screen.getByRole('button', { name: 'Crear cuenta' }));

    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio');
    await expect(leerToken()).resolves.toBe('token-registro');
    expect(llamada()).toEqual({
      url: `${BASE}/registro`,
      cuerpo: { correo: 'persona@ejemplo.com', password: 'secreta123' },
    });
  });

  test('registro-E04 — si el servidor rechaza el registro, muestra cada error junto a su campo y no entra', async () => {
    fetchFalso.mockResolvedValue(
      respuesta(422, {
        message: 'El correo ya tiene cuenta. (y 1 error más)',
        errors: { correo: ['El correo ya tiene cuenta.'], password: ['Debe tener al menos 8 caracteres.'] },
      }),
    );
    await abrir('/registro');

    const usuario = await llenar('persona@ejemplo.com', 'corta');
    await usuario.press(screen.getByRole('button', { name: 'Crear cuenta' }));

    expect(await within(screen.getByTestId('campo-correo')).findByText('El correo ya tiene cuenta.')).toBeOnTheScreen();
    expect(within(screen.getByTestId('campo-password')).getByText('Debe tener al menos 8 caracteres.')).toBeOnTheScreen();
    expect(screen).toHavePathname('/registro');
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E04 — sin conexión con el servidor, lo dice y no entra', async () => {
    fetchFalso.mockRejectedValue(new TypeError('Network request failed'));
    await abrir('/registro');

    const usuario = await llenar('persona@ejemplo.com', 'secreta123');
    await usuario.press(screen.getByRole('button', { name: 'Crear cuenta' }));

    expect(
      await screen.findByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(screen).toHavePathname('/registro');
  });

  test('registro-E04 — "¿Ya tienes cuenta? Inicia sesión" lleva al inicio de sesión', async () => {
    const usuario = userEvent.setup();
    await abrir('/registro');

    await usuario.press(await screen.findByRole('link', { name: '¿Ya tienes cuenta? Inicia sesión' }));

    expect(screen).toHavePathname('/inicio-de-sesion');
  });
});

describe('registro-E09: la pantalla de inicio de sesión', () => {
  test('registro-E09 — con el correo y la contraseña aceptados, guarda la sesión y entra al inicio', async () => {
    fetchFalso.mockResolvedValue(respuesta(200, { token: 'token-sesion', usuario: USUARIO }));
    await abrir('/inicio-de-sesion');

    const usuario = await llenar('persona@ejemplo.com', 'secreta123');
    await usuario.press(screen.getByRole('button', { name: 'Iniciar sesión' }));

    expect(await screen.findByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio');
    await expect(leerToken()).resolves.toBe('token-sesion');
    expect(llamada()).toEqual({
      url: `${BASE}/sesion`,
      cuerpo: { correo: 'persona@ejemplo.com', password: 'secreta123' },
    });
  });

  test('registro-E09 — si el servidor las rechaza, muestra el error y no entra', async () => {
    fetchFalso.mockResolvedValue(
      respuesta(422, {
        message: 'Las credenciales no son válidas.',
        errors: { correo: ['Las credenciales no son válidas.'] },
      }),
    );
    await abrir('/inicio-de-sesion');

    const usuario = await llenar('persona@ejemplo.com', 'otra');
    await usuario.press(screen.getByRole('button', { name: 'Iniciar sesión' }));

    expect(await screen.findByText('Las credenciales no son válidas.')).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio-de-sesion');
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E09 — con demasiados intentos (registro-E30), lo dice y no entra', async () => {
    fetchFalso.mockResolvedValue(respuesta(429, { message: 'Too Many Attempts.' }));
    await abrir('/inicio-de-sesion');

    const usuario = await llenar('persona@ejemplo.com', 'otra');
    await usuario.press(screen.getByRole('button', { name: 'Iniciar sesión' }));

    expect(await screen.findByText('Demasiados intentos. Espera un minuto e intenta de nuevo.')).toBeOnTheScreen();
    expect(screen).toHavePathname('/inicio-de-sesion');
  });

  test('registro-E09 — "¿No tienes cuenta? Regístrate" lleva al registro', async () => {
    const usuario = userEvent.setup();
    await abrir('/inicio-de-sesion');

    await usuario.press(await screen.findByRole('link', { name: '¿No tienes cuenta? Regístrate' }));

    expect(screen).toHavePathname('/registro');
  });
});

describe('registro-E28: los enlaces legales salen de la configuración', () => {
  // Se abren en la vista de navegador de la app (textos-legales-E05, __tests__/textos-legales/enlaces-legales.test.tsx).
  test('registro-E28 — el registro muestra los dos enlaces y abren las direcciones de la configuración', async () => {
    const abrirDireccion = (WebBrowser as unknown as typeof import('../../__mocks__/expo-web-browser')).openBrowserAsync;
    abrirDireccion.mockClear();
    const usuario = userEvent.setup();
    await abrir('/registro');

    await usuario.press(await screen.findByRole('link', { name: 'Aviso de privacidad' }));
    await usuario.press(screen.getByRole('link', { name: 'Términos y condiciones' }));

    expect(abrirDireccion).toHaveBeenNthCalledWith(1, AVISO);
    expect(abrirDireccion).toHaveBeenNthCalledWith(2, TERMINOS);
  });

  test('registro-E28 — sin una dirección en la configuración, ese enlace no aparece y la pantalla abre igual', async () => {
    delete process.env.EXPO_PUBLIC_AVISO_DE_PRIVACIDAD_URL;
    await abrir('/registro');

    expect(await screen.findByRole('link', { name: 'Términos y condiciones' })).toBeOnTheScreen();
    expect(screen.queryByRole('link', { name: 'Aviso de privacidad' })).not.toBeOnTheScreen();
  });

  test('registro-E28 — las pantallas no traen ninguna dirección escrita a mano', () => {
    const raiz = path.resolve(__dirname, '..', '..');
    const archivos = [...recorrer(path.join(raiz, 'app')), ...recorrer(path.join(raiz, 'src'))];
    expect(archivos.length).toBeGreaterThan(0);

    const direcciones = /https?:\/\/|\bwww\./i;
    expect(archivos.filter((archivo) => direcciones.test(textoSinElChatDeWhatsApp(archivo)))).toEqual([]);
  });
});

// La única dirección escrita en la app: la del chat de WhatsApp de soporte, https://wa.me/<número>, en
// src/perfil-usuario/whatsapp.ts (perfil-usuario-E09; openspec/changes/perfil-y-soporte/design.md, "Contrato de la
// API"). Es la dirección pública de WhatsApp para abrir un chat, no la de la API, la de la marca ni la de un documento
// legal; el número sí sale de la configuración del servidor. Solo en ese archivo y solo esa dirección.
const CHAT_DE_WHATSAPP = path.join('src', 'perfil-usuario', 'whatsapp.ts');

function textoSinElChatDeWhatsApp(archivo: string): string {
  const texto = readFileSync(archivo, 'utf8');
  return archivo.endsWith(CHAT_DE_WHATSAPP) ? texto.replace(/https:\/\/wa\.me\//g, '') : texto;
}

function recorrer(carpeta: string): string[] {
  if (!existsSync(carpeta)) {
    return [];
  }
  return readdirSync(carpeta).flatMap((nombre) => {
    const ruta = path.join(carpeta, nombre);
    if (statSync(ruta).isDirectory()) {
      return recorrer(ruta);
    }
    return /\.(ts|tsx|js|jsx)$/.test(nombre) ? [ruta] : [];
  });
}
