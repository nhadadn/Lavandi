// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E26.
// La pantalla "¿Olvidaste tu contraseña?". El enlace que llega por correo abre una página web del servidor
// (ADR-0018): la app solo lo pide.
import * as SecureStore from 'expo-secure-store';
import { userEvent } from '@testing-library/react-native';
import { renderRouter, screen, within } from 'expo-router/testing-library';

import InicioDeSesion from '../../app/inicio-de-sesion';
import RecuperarContrasena from '../../app/recuperar-contrasena';
import { ProveedorDeSesion } from '../../src/sesion/ProveedorDeSesion';

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app: aquí no hay sesión.
const almacen = SecureStore as unknown as { __vaciar(): void };

// La primera escritura con userEvent tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

const BASE = 'http://api.prueba.invalid/registro-e26';

interface RespuestaFalsa {
  ok: boolean;
  status: number;
  json: () => Promise<unknown>;
}

function respuesta(status: number, cuerpo: unknown): RespuestaFalsa {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
// El nombre de la app es obligatorio en la configuración: el inicio de sesión lo muestra (tarea 2.3, C-230).
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function restaurar(nombre: string, valor: string | undefined) {
  if (valor === undefined) {
    delete process.env[nombre];
  } else {
    process.env[nombre] = valor;
  }
}

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba registro-E26';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
});

afterEach(() => {
  restaurar('EXPO_PUBLIC_API_URL', direccionOriginal);
  restaurar('EXPO_PUBLIC_NOMBRE_APP', nombreOriginal);
  global.fetch = fetchOriginal;
});

async function abrir(ruta: string) {
  await renderRouter(
    { 'inicio-de-sesion': InicioDeSesion, 'recuperar-contrasena': RecuperarContrasena },
    { initialUrl: ruta, wrapper: ProveedorDeSesion },
  );
}

describe('registro-E26: la pantalla de recuperación en la app', () => {
  test('registro-E26 — desde el inicio de sesión, "¿Olvidaste tu contraseña?" lleva a la recuperación', async () => {
    const usuario = userEvent.setup();
    await abrir('/inicio-de-sesion');

    await usuario.press(await screen.findByRole('link', { name: '¿Olvidaste tu contraseña?' }));

    expect(screen).toHavePathname('/recuperar-contrasena');
    expect(await screen.findByLabelText('Correo electrónico')).toBeOnTheScreen();
  });

  test('registro-E26 — manda el correo al servidor y le dice que revise su correo', async () => {
    fetchFalso.mockResolvedValue(respuesta(200, { message: 'Respuesta igual exista o no la cuenta.' }));
    const usuario = userEvent.setup();
    await abrir('/recuperar-contrasena');

    await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona@ejemplo.com');
    await usuario.press(screen.getByRole('button', { name: 'Enviar enlace' }));

    expect(await screen.findByText('Revisa tu correo')).toBeOnTheScreen();
    expect(screen.getByText(/persona@ejemplo\.com/)).toBeOnTheScreen();
    const [url, opciones] = fetchFalso.mock.calls[0] as [string, RequestInit];
    expect(url).toBe(`${BASE}/contrasena/olvido`);
    expect(JSON.parse(opciones.body as string)).toEqual({ correo: 'persona@ejemplo.com' });
  });

  test('registro-E26 — con el correo mal escrito, muestra el error junto al campo y no dice que revise', async () => {
    fetchFalso.mockResolvedValue(
      respuesta(422, { message: 'El correo no es válido.', errors: { correo: ['El correo no es válido.'] } }),
    );
    const usuario = userEvent.setup();
    await abrir('/recuperar-contrasena');

    await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona');
    await usuario.press(screen.getByRole('button', { name: 'Enviar enlace' }));

    expect(await within(screen.getByTestId('campo-correo')).findByText('El correo no es válido.')).toBeOnTheScreen();
    expect(screen.queryByText('Revisa tu correo')).not.toBeOnTheScreen();
  });

  test('registro-E26 — con demasiadas peticiones, lo dice y no dice que revise su correo', async () => {
    fetchFalso.mockResolvedValue(respuesta(429, { message: 'Too Many Attempts.' }));
    const usuario = userEvent.setup();
    await abrir('/recuperar-contrasena');

    await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona@ejemplo.com');
    await usuario.press(screen.getByRole('button', { name: 'Enviar enlace' }));

    expect(await screen.findByText('Demasiados intentos. Espera un minuto e intenta de nuevo.')).toBeOnTheScreen();
    expect(screen.queryByText('Revisa tu correo')).not.toBeOnTheScreen();
  });

  // Con el aspecto de la Propuesta 04 (tarea 2.3, C-230), "Volver a iniciar sesión" es el botón principal y no un enlace.
  test('registro-E26 — después de enviarlo, "Volver a iniciar sesión" regresa al inicio de sesión', async () => {
    fetchFalso.mockResolvedValue(respuesta(200, { message: 'Respuesta igual exista o no la cuenta.' }));
    const usuario = userEvent.setup();
    await abrir('/recuperar-contrasena');

    await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona@ejemplo.com');
    await usuario.press(screen.getByRole('button', { name: 'Enviar enlace' }));
    await usuario.press(await screen.findByRole('button', { name: 'Volver a iniciar sesión' }));

    expect(screen).toHavePathname('/inicio-de-sesion');
  });
});
