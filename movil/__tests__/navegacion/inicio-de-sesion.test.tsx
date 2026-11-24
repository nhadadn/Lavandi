// Sin sesión, la oferta de registrarse o iniciar sesión es la pantalla de inicio de sesión del mock, con su enlace a
// registrarse (bienvenida-E04, openspec/changes/bienvenida/). Ya no hay una entrada aparte con el nombre al centro
// (C-237). Abierto como primera pantalla, el inicio de sesión es la raíz sin sesión y no tiene "Regresar".
// Navegación con expo-router (ADR-0017).
import path from 'node:path';
import * as SecureStore from 'expo-secure-store';
import { router } from 'expo-router';
import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

const APP = path.resolve(__dirname, '..', '..', 'app');

// El primer render carga expo-router y sus pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);
// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

describe('bienvenida-E04: el inicio de sesión ofrece registrarse o iniciar sesión', () => {
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;

  beforeEach(() => {
    almacen.__vaciar();
    process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba';
  });

  afterEach(() => {
    if (nombreOriginal === undefined) {
      delete process.env.EXPO_PUBLIC_NOMBRE_APP;
    } else {
      process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
    }
  });

  test('bienvenida-E04 — ofrece iniciar sesión y, abajo, registrarse', async () => {
    await renderRouter(APP, { initialUrl: '/inicio-de-sesion' });

    expect(await screen.findByRole('button', { name: 'Iniciar sesión' })).toBeOnTheScreen();
    expect(screen.getByRole('link', { name: '¿No tienes cuenta? Regístrate' })).toBeOnTheScreen();
  });

  test('bienvenida-E04 — "Regístrate" lleva al registro', async () => {
    await renderRouter(APP, { initialUrl: '/inicio-de-sesion' });

    await fireEvent.press(await screen.findByRole('link', { name: '¿No tienes cuenta? Regístrate' }));

    await waitFor(() => expect(screen).toHavePathname('/registro'));
    expect(screen.getByRole('header', { name: 'Crear cuenta' })).toBeOnTheScreen();
  });

  test('bienvenida-E04 — abierto como primera pantalla, no tiene "Regresar": es la raíz sin sesión', async () => {
    await renderRouter(APP, { initialUrl: '/inicio-de-sesion' });

    expect(await screen.findByRole('header', { name: 'Iniciar sesión' })).toBeOnTheScreen();
    expect(router.canGoBack()).toBe(false);
    expect(screen.queryByRole('button', { name: 'Regresar' })).not.toBeOnTheScreen();
  });

  test('bienvenida-E04 — un enlace viejo a la entrada no deja la pantalla en blanco: lleva al inicio de sesión', async () => {
    // Ya vio la bienvenida en esta misma instalación (bienvenida-E02 y registro-E32).
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    await renderRouter(APP, { initialUrl: '/entrada' });

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByRole('button', { name: 'Iniciar sesión' })).toBeOnTheScreen();
  });

  test('registro-E11 (en parte) — con la sesión abierta, el inicio muestra su título', async () => {
    // La sesión es de esta misma instalación: la marca de instalación ya existe (registro-E32).
    await comprobarInstalacion();
    await guardarToken('token-guardado');

    await renderRouter(APP, { initialUrl: '/inicio' });

    // El inicio es la pantalla principal de lavanderias-cercanas, que reemplazó al provisional.
    expect(await screen.findByRole('header', { name: 'Lavanderías cerca de ti' })).toBeOnTheScreen();
  });
});
