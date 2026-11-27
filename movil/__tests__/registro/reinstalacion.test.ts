// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E32 (C-229): al reinstalar la
// app, la sesión no sobrevive, en iPhone igual que en Android.
//
// En iPhone, el almacén cifrado (expo-secure-store, el Keychain) sobrevive a desinstalar la app; en Android se borra.
// Por eso la app guarda una marca de instalación en sus documentos, que el sistema borra al desinstalar en los dos
// teléfonos. Si al arrancar no la encuentra, borra del almacén cifrado el token, su rol y la marca de la bienvenida, y
// luego la escribe.
//
// El almacén cifrado es la simulación en memoria de __mocks__/expo-secure-store.ts. Los documentos son la simulación en
// memoria que expo-file-system trae para Jest y que jest-expo conecta sola. Lo que de verdad borra cada sistema al
// desinstalar se prueba en un teléfono real.
import path from 'node:path';

import * as SecureStore from 'expo-secure-store';
import { File, Paths } from 'expo-file-system';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

import { leerBienvenidaVista, marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarRol, guardarToken, leerRol, leerToken } from '../../src/sesion/almacen';

// El primer render carga expo-router y sus pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/registro-reinstalacion';

// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

// Lo que el sistema borra al desinstalar la app, en iPhone y en Android: los documentos de la app.
function borrarLosDocumentosDeLaApp() {
  for (const entrada of Paths.document.list()) {
    entrada.delete();
  }
}

// La persona que ya usó la app en esta instalación: la abrió, vio la bienvenida e inició sesión.
async function personaQueYaUsoLaApp() {
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarRol('usuario');
  await guardarToken('token-de-antes');
}

// Desinstalar en iPhone: el sistema borra los documentos de la app, pero el Keychain conserva el token y la marca de
// la bienvenida.
function desinstalarEnIPhone() {
  borrarLosDocumentosDeLaApp();
}

// Desinstalar en Android: el sistema borra los documentos y también el almacén cifrado.
function desinstalarEnAndroid() {
  borrarLosDocumentosDeLaApp();
  almacen.__vaciar();
}

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

beforeEach(() => {
  almacen.__vaciar();
  borrarLosDocumentosDeLaApp();
  jest.clearAllMocks();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // La bienvenida muestra el nombre de la app, que sale de la configuración (base-E09).
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba registro-E32';
  // Si la app usara la sesión anterior, se la preguntaría al servidor (registro-E11). Aquí el servidor no contesta:
  // la sesión se conservaría y la app iría al inicio.
  fetchFalso = jest.fn(async () => {
    throw new TypeError('Network request failed');
  });
  global.fetch = fetchFalso as unknown as typeof fetch;
});

afterEach(() => {
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
  jest.restoreAllMocks();
});

describe('registro-E32: al reinstalar la app, la sesión no sobrevive', () => {
  describe('la marca de instalación', () => {
    test('registro-E32 — sin la marca, borra del almacén cifrado el token, su rol y la marca de la bienvenida', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnIPhone();

      await comprobarInstalacion();

      await expect(leerToken()).resolves.toBeNull();
      await expect(leerRol()).resolves.toBeNull();
      await expect(leerBienvenidaVista()).resolves.toBe(false);
    });

    test('registro-E32 — sin la marca, la escribe en los documentos de la app y no en el almacén cifrado', async () => {
      await guardarToken('token-de-antes');
      jest.clearAllMocks();

      await comprobarInstalacion();

      const documentos = Paths.document.list();
      expect(documentos).toHaveLength(1);
      expect(documentos[0]).toBeInstanceOf(File);
      expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
    });

    test('registro-E32 — con la marca presente, no borra nada', async () => {
      await personaQueYaUsoLaApp();
      jest.clearAllMocks();

      await comprobarInstalacion();

      expect(SecureStore.deleteItemAsync).not.toHaveBeenCalled();
      await expect(leerToken()).resolves.toBe('token-de-antes');
      await expect(leerBienvenidaVista()).resolves.toBe(true);
    });

    // Al arrancar la piden a la vez la sesión (ProveedorDeSesion) y la pantalla de arranque (leerBienvenidaVista).
    test('registro-E32 — si la piden dos a la vez, borra una sola vez y escribe una sola marca', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnIPhone();
      jest.clearAllMocks();
      const crear = jest.spyOn(File.prototype, 'create');

      await Promise.all([comprobarInstalacion(), comprobarInstalacion()]);

      // El token, su rol (registro-E33) y la marca de la bienvenida.
      expect(SecureStore.deleteItemAsync).toHaveBeenCalledTimes(3);
      expect(crear).toHaveBeenCalledTimes(1);
    });

    test('registro-E32 — si no se puede leer la marca, no borra nada y la app sigue', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnIPhone();
      jest.clearAllMocks();
      jest.spyOn(File.prototype, 'exists', 'get').mockImplementation(() => {
        throw new Error('Sin acceso a los documentos de la app');
      });

      await expect(comprobarInstalacion()).resolves.toBeUndefined();

      expect(SecureStore.deleteItemAsync).not.toHaveBeenCalled();
      await expect(leerToken()).resolves.toBe('token-de-antes');
      await expect(leerBienvenidaVista()).resolves.toBe(true);
    });

    // El orden es el acordado en C-229: primero se borra y luego se escribe la marca. Si la escritura falla, lo borrado
    // ya no vuelve, y como la marca no quedó, el siguiente arranque vuelve a borrar: la sesión anterior nunca se usa.
    test('registro-E32 — si no se puede escribir la marca, la sesión anterior ya se borró y la app sigue', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnIPhone();
      jest.spyOn(File.prototype, 'create').mockImplementation(() => {
        throw new Error('No hay espacio en el teléfono');
      });

      await expect(comprobarInstalacion()).resolves.toBeUndefined();

      await expect(leerToken()).resolves.toBeNull();
      expect(Paths.document.list()).toHaveLength(0);
    });

    test('registro-E32 — si no se puede borrar del almacén cifrado, no escribe la marca y la app sigue', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnIPhone();
      jest.mocked(SecureStore.deleteItemAsync).mockRejectedValueOnce(new Error('Keychain no disponible'));

      await expect(comprobarInstalacion()).resolves.toBeUndefined();

      // Sin la marca, el siguiente arranque lo vuelve a intentar.
      expect(Paths.document.list()).toHaveLength(0);
    });
  });

  describe('el arranque después de reinstalar', () => {
    test('registro-E32 — en iPhone empieza en la bienvenida y sin sesión, aunque el Keychain conserve la anterior', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnIPhone();

      await renderRouter(APP, { initialUrl: '/' });

      await waitFor(() => expect(screen).toHavePathname('/bienvenida'));
      await expect(leerToken()).resolves.toBeNull();
      // La sesión anterior no se usa: ni siquiera se le pregunta al servidor por ella.
      expect(fetchFalso).not.toHaveBeenCalled();
    });

    test('registro-E32 — en Android empieza igual, en la bienvenida y sin sesión', async () => {
      await personaQueYaUsoLaApp();
      desinstalarEnAndroid();

      await renderRouter(APP, { initialUrl: '/' });

      await waitFor(() => expect(screen).toHavePathname('/bienvenida'));
      await expect(leerToken()).resolves.toBeNull();
      expect(fetchFalso).not.toHaveBeenCalled();
    });
  });
});
