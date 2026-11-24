// El estado de la sesión en la app: ProveedorDeSesion y useSesion (ADR-0018). Es la base de registro-E11 y registro-E13
// (openspec/changes/registro-e-inicio-de-sesion/). No llama al servidor: eso lo agrega la tarea 2.1.
import { act, renderHook, waitFor } from '@testing-library/react-native';
import * as SecureStore from 'expo-secure-store';
import type { ReactNode } from 'react';

import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken, leerToken } from '../../src/sesion/almacen';
import { ProveedorDeSesion, useSesion } from '../../src/sesion/ProveedorDeSesion';

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

function conProveedor({ children }: { children: ReactNode }) {
  return <ProveedorDeSesion>{children}</ProveedorDeSesion>;
}

async function montar() {
  const resultado = await renderHook(() => useSesion(), { wrapper: conProveedor });
  await waitFor(() => expect(resultado.result.current.estado).not.toBe('cargando'));
  return resultado;
}

describe('ADR-0018: la sesión de la app sale del almacenamiento cifrado', () => {
  const fetchOriginal = global.fetch;

  beforeEach(async () => {
    almacen.__vaciar();
    // La app ya arrancó antes en esta instalación: la marca de instalación existe (registro-E32).
    await comprobarInstalacion();
  });

  afterEach(() => {
    global.fetch = fetchOriginal;
    jest.restoreAllMocks();
  });

  test('registro-E11 (en parte) — mientras lee el almacén, el estado es cargando', async () => {
    let soltar: (token: string | null) => void = () => {};
    jest.mocked(SecureStore.getItemAsync).mockImplementationOnce(
      () => new Promise<string | null>((resolver) => (soltar = resolver)),
    );

    const { result } = await renderHook(() => useSesion(), { wrapper: conProveedor });
    expect(result.current.estado).toBe('cargando');
    expect(result.current.token).toBeNull();

    await act(async () => soltar(null));
    expect(result.current.estado).toBe('sin-sesion');
  });

  test('registro-E11 (en parte) — si el almacén cifrado no se puede leer, la carga termina sin sesión', async () => {
    jest.mocked(SecureStore.getItemAsync).mockRejectedValueOnce(new Error('El almacén cifrado no respondió'));

    const { result } = await montar();

    expect(result.current.estado).toBe('sin-sesion');
    expect(result.current.token).toBeNull();
  });

  test('registro-E11 (en parte) — si el servidor rechaza el token y el teléfono no lo puede borrar, la carga termina sin sesión', async () => {
    const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
    process.env.EXPO_PUBLIC_API_URL = 'http://api.prueba.invalid/sesion-proveedor';
    try {
      global.fetch = jest.fn(async () => ({
        ok: false,
        status: 401,
        json: async () => ({ message: 'Unauthenticated.' }),
      })) as unknown as typeof fetch;
      await guardarToken('token-revocado');
      jest.mocked(SecureStore.deleteItemAsync).mockRejectedValueOnce(new Error('El almacén cifrado no respondió'));

      const { result } = await montar();

      expect(result.current.estado).toBe('sin-sesion');
      expect(result.current.token).toBeNull();
    } finally {
      if (direccionOriginal === undefined) {
        delete process.env.EXPO_PUBLIC_API_URL;
      } else {
        process.env.EXPO_PUBLIC_API_URL = direccionOriginal;
      }
    }
  });

  test('registro-E11 (en parte) — sin token guardado, no hay sesión', async () => {
    const { result } = await montar();

    expect(result.current.estado).toBe('sin-sesion');
    expect(result.current.token).toBeNull();
  });

  test('registro-E11 (en parte) — con un token guardado, al abrir la app sigue la sesión', async () => {
    await guardarToken('token-guardado');

    const { result } = await montar();

    expect(result.current.estado).toBe('con-sesion');
    expect(result.current.token).toBe('token-guardado');
  });

  test('registro-E04 y registro-E09 (en parte) — iniciar guarda el token y abre la sesión', async () => {
    const { result } = await montar();

    await act(() => result.current.iniciar('token-nuevo', 'usuario'));

    expect(result.current.estado).toBe('con-sesion');
    expect(result.current.token).toBe('token-nuevo');
    await expect(leerToken()).resolves.toBe('token-nuevo');
  });

  test('registro-E13 (en parte) — cerrar borra el token del teléfono y deja la app sin sesión', async () => {
    await guardarToken('token-guardado');
    const { result } = await montar();

    await act(() => result.current.cerrar());

    expect(result.current.estado).toBe('sin-sesion');
    expect(result.current.token).toBeNull();
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E13 (en parte) — si el teléfono no puede borrar el token, cerrar deja igual la app sin sesión', async () => {
    // Sin conexión al abrir: la sesión guardada se conserva (registro-E11).
    global.fetch = jest.fn(async () => {
      throw new TypeError('Network request failed');
    }) as unknown as typeof fetch;
    await guardarToken('token-guardado');
    const { result } = await montar();
    expect(result.current.estado).toBe('con-sesion');
    jest.mocked(SecureStore.deleteItemAsync).mockRejectedValueOnce(new Error('El almacén cifrado no respondió'));

    await act(() => result.current.cerrar().catch(() => {}));

    expect(result.current.estado).toBe('sin-sesion');
    expect(result.current.token).toBeNull();
  });

  test('ADR-0018 — useSesion fuera del proveedor avisa del error de programación', async () => {
    jest.spyOn(console, 'error').mockImplementation(() => {});

    await expect(renderHook(() => useSesion())).rejects.toThrow('ProveedorDeSesion');
  });
});
