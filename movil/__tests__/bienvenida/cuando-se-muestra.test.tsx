// Spec: openspec/changes/bienvenida/specs/bienvenida/spec.md, escenarios bienvenida-E01 (a dónde llega la primera
// apertura) y bienvenida-E02 (las aperturas siguientes y con la sesión abierta; C-225). Navegación: ADR-0017.
import path from 'node:path';
import * as SecureStore from 'expo-secure-store';
import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { leerBienvenidaVista, marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { contenidoDeBienvenida } from '../../src/contenido/bienvenida';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

const APP = path.resolve(__dirname, '..', '..', 'app');

// El primer render carga expo-router y sus pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);
// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

// Abre la app como al tocar su ícono y espera a que decida a dónde llevar a la persona.
async function abrirLaApp(destino: string) {
  await renderRouter(APP, { initialUrl: '/' });
  await waitFor(() => expect(screen).toHavePathname(destino));
}

describe('bienvenida: a dónde llega la persona al abrir la app', () => {
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;

  beforeEach(async () => {
    almacen.__vaciar();
    // Las aperturas de este archivo son de la misma instalación: la marca de instalación ya existe. La reinstalación
    // se prueba en registro/reinstalacion.test.ts (registro-E32).
    await comprobarInstalacion();
    process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba';
  });

  afterEach(() => {
    if (nombreOriginal === undefined) {
      delete process.env.EXPO_PUBLIC_NOMBRE_APP;
    } else {
      process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
    }
  });

  test('bienvenida-E01 — la primera apertura, sin sesión, empieza en la bienvenida', async () => {
    await abrirLaApp('/bienvenida');
  });

  describe('si el almacén cifrado no deja leer la marca de la bienvenida', () => {
    const leer = jest.mocked(SecureStore.getItemAsync);
    let original: ReturnType<typeof leer.getMockImplementation>;

    beforeEach(() => {
      original = leer.getMockImplementation();
      leer.mockImplementation(async (clave: string) => {
        if (clave === 'bienvenida.vista') {
          throw new Error('El almacén cifrado no respondió');
        }
        return original!(clave);
      });
    });

    afterEach(() => {
      leer.mockImplementation(original!);
    });

    test('bienvenida-E01 — la marca cuenta como no vista', async () => {
      await expect(leerBienvenidaVista()).resolves.toBe(false);
    });

    test('bienvenida-E01 — sin sesión, la app no se queda en blanco: muestra la bienvenida', async () => {
      await abrirLaApp('/bienvenida');

      expect(await screen.findByTestId('pantalla-inicial')).toBeOnTheScreen();
    });
  });

  test('bienvenida-E02 — si ya vio la bienvenida y no tiene sesión, llega directo al inicio de sesión', async () => {
    await marcarBienvenidaVista();

    await abrirLaApp('/inicio-de-sesion');
    expect(await screen.findByRole('link', { name: '¿No tienes cuenta? Regístrate' })).toBeOnTheScreen();
  });

  test('bienvenida-E02 — con la sesión abierta entra directo, sin la bienvenida ni el inicio de sesión', async () => {
    await marcarBienvenidaVista();
    await guardarToken('token-guardado');

    await abrirLaApp('/inicio');
  });

  test('bienvenida-E02 — con la sesión abierta entra directo aunque no haya visto la bienvenida', async () => {
    await guardarToken('token-guardado');

    await abrirLaApp('/inicio');
  });

  test('bienvenida-E02 — que ya la vio se recuerda en el almacenamiento del teléfono', async () => {
    await expect(leerBienvenidaVista()).resolves.toBe(false);

    await marcarBienvenidaVista();

    await expect(leerBienvenidaVista()).resolves.toBe(true);
  });

  // La siguiente apertura no se vuelve a montar dentro del mismo test: el router de pruebas conserva la última ruta.
  // A dónde lleva cada valor de la marca lo prueban los tests de arriba.
  test('bienvenida-E02 — al terminar la tercera informativa, la app recuerda que ya la vio', async () => {
    const { comenzar, siguiente } = contenidoDeBienvenida.botones;
    await abrirLaApp('/bienvenida');

    await fireEvent.press(screen.getByRole('button', { name: comenzar }));
    await waitFor(() => expect(screen).toHavePathname('/informativa/1'));
    await fireEvent.press(screen.getByRole('button', { name: siguiente }));
    await waitFor(() => expect(screen).toHavePathname('/informativa/2'));
    await fireEvent.press(screen.getByRole('button', { name: siguiente }));
    await waitFor(() => expect(screen).toHavePathname('/informativa/3'));
    await expect(leerBienvenidaVista()).resolves.toBe(false);

    await fireEvent.press(screen.getByRole('button', { name: siguiente }));
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));

    await expect(leerBienvenidaVista()).resolves.toBe(true);
  });

  test('bienvenida-E02 — si deja las informativas a medias, no cuenta como vista y la siguiente vez empieza en la bienvenida', async () => {
    await abrirLaApp('/bienvenida');

    await fireEvent.press(screen.getByRole('button', { name: contenidoDeBienvenida.botones.comenzar }));
    await waitFor(() => expect(screen).toHavePathname('/informativa/1'));

    await expect(leerBienvenidaVista()).resolves.toBe(false);
  });
});
