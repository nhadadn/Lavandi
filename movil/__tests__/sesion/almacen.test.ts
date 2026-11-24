// Dónde guarda la app el token de la sesión: ADR-0018, decisión 1 (expo-secure-store). Es la base de registro-E11 y
// registro-E13 (openspec/changes/registro-e-inicio-de-sesion/), que completa la tarea 2.1 con las llamadas al servidor.
import * as SecureStore from 'expo-secure-store';

import { borrarToken, guardarToken, leerToken } from '../../src/sesion/almacen';

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

describe('ADR-0018: el token de la sesión vive en el almacenamiento cifrado del teléfono', () => {
  beforeEach(() => {
    almacen.__vaciar();
    jest.clearAllMocks();
  });

  test('registro-E11 (en parte) — sin sesión guardada, no hay token', async () => {
    await expect(leerToken()).resolves.toBeNull();
  });

  test('registro-E11 (en parte) — el token guardado se lee al volver a abrir la app', async () => {
    await guardarToken('token-de-prueba');

    await expect(leerToken()).resolves.toBe('token-de-prueba');
  });

  test('ADR-0018 — el token se guarda con expo-secure-store y no en otro almacenamiento', async () => {
    await guardarToken('token-de-prueba');

    expect(SecureStore.setItemAsync).toHaveBeenCalledTimes(1);
    expect(jest.mocked(SecureStore.setItemAsync).mock.calls[0][1]).toBe('token-de-prueba');
  });

  test('registro-E13 (en parte) — borrar el token lo quita del teléfono', async () => {
    await guardarToken('token-de-prueba');

    await borrarToken();

    expect(SecureStore.deleteItemAsync).toHaveBeenCalledTimes(1);
    await expect(leerToken()).resolves.toBeNull();
  });
});
