// Spec: openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md, cercanas-E03 y cercanas-E04 (tarea 2.2).
// La ubicación del teléfono solo mientras la app está abierta, con el texto que dice para qué se usa, y la consulta de
// las lavanderías con esa ubicación. Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en
// Android. El permiso y el GPS son la simulación de __mocks__/expo-location.ts y el servidor va simulado: el diálogo
// de cada sistema se prueba en un teléfono real. Lo que pasa si la persona niega el permiso es cercanas-E05, que
// espera a DD-083 (tarea 2.3).
import { spawnSync } from 'node:child_process';
import path from 'node:path';

import { userEvent } from '@testing-library/react-native';
import Constants from 'expo-constants';
import * as Location from 'expo-location';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { consultarCercanas } from '../../src/cercanas/api';
import permisoDeUbicacion from '../../src/cercanas/permiso-de-ubicacion.json';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const RAIZ = path.resolve(__dirname, '..', '..');
const APP = path.join(RAIZ, 'app');
const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');
const BASE = 'http://api.prueba.invalid/cercanas-e04';
const TOKEN = 'token-cercanas-e04';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
// Un punto de prueba, en la ciudad de Durango.
const UBICACION = { latitude: 24.0277, longitude: -104.6532 };
const LAVANDERIA = {
  id_publico: '01JD00000000000000000000B1',
  nombre: 'Lavandería de prueba E04',
  latitud: 24.03,
  longitud: -104.66,
  distancia_metros: 700,
  calificacion_promedio: 4.6,
  numero_de_calificaciones: 5,
};
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const gps = Location as unknown as typeof import('../../__mocks__/expo-location');
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

interface RespuestaFalsa {
  ok: boolean;
  status: number;
  json: () => Promise<unknown>;
}

function respuesta(status: number, cuerpo: unknown): RespuestaFalsa {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

// El servidor falso: reconoce el token y contesta la consulta de cercanas con la lista que se le dé.
function servidor(lavanderias: unknown[] = [LAVANDERIA]) {
  fetchFalso.mockImplementation(async (url: string) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

function consultasDeCercanas(): { url: string; opciones: RequestInit }[] {
  return fetchFalso.mock.calls
    .filter(([url]: [string]) => url.includes('/lavanderias/cercanas'))
    .map(([url, opciones]: [string, RequestInit]) => ({ url, opciones }));
}

// La persona que ya inició sesión en esta instalación abre la app en la pantalla principal, en un iPhone o en un
// Android. El Android es una compilación con la llave de Google Maps (cercanas-E02).
async function abrirLaPantallaPrincipal(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: true };
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: '/inicio' });
}

beforeEach(() => {
  almacen.__vaciar();
  gps.__reiniciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
});

afterEach(() => {
  if (direccionOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_API_URL;
  } else {
    process.env.EXPO_PUBLIC_API_URL = direccionOriginal;
  }
  global.fetch = fetchOriginal;
  delete configuracionDeLaApp.extra;
  jest.restoreAllMocks();
});

describe('cercanas-E03: el permiso es solo mientras se usa', () => {
  describe('en la configuración de la app', () => {
    interface ElementoDelManifiesto {
      $: Record<string, string>;
    }
    interface ConfiguracionLeida {
      _internal?: {
        modResults?: {
          ios?: { infoPlist?: Record<string, unknown> };
          android?: { manifest?: { manifest?: { 'uses-permission'?: ElementoDelManifiesto[] } } };
        };
      };
    }

    let infoPlist: Record<string, unknown>;
    let permisosDeAndroid: ElementoDelManifiesto[];

    // La configuración como la arma Expo al compilar, con los complementos aplicados (modo introspect), sin leer
    // ningún .env de la máquina. Vive en cada archivo: aquí, todo lo que está en __tests__/ se corre como test.
    beforeAll(() => {
      const env: NodeJS.ProcessEnv = {
        ...process.env,
        EXPO_NO_DOTENV: '1',
        EXPO_NO_TELEMETRY: '1',
        EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba cercanas-E03',
        EXPO_PUBLIC_API_URL: 'http://api.prueba.invalid/cercanas-e03',
        APP_IDENTIFIER: 'mx.prueba.cercanase03',
      };
      const leida = spawnSync(process.execPath, [CLI_DE_EXPO, 'config', '--type', 'introspect', '--json'], {
        cwd: RAIZ,
        env,
        encoding: 'utf8',
      });
      if (leida.status !== 0) {
        throw new Error(`Expo no pudo leer la configuración: ${leida.stderr}`);
      }
      const configuracion = JSON.parse(leida.stdout) as ConfiguracionLeida;
      infoPlist = configuracion._internal?.modResults?.ios?.infoPlist ?? {};
      permisosDeAndroid = configuracion._internal?.modResults?.android?.manifest?.manifest?.['uses-permission'] ?? [];
    }, 180_000);

    // Los permisos que la app declara, sin los que la configuración quita del manifiesto final.
    function declarados(): string[] {
      return permisosDeAndroid
        .filter((permiso) => permiso.$['tools:node'] !== 'remove')
        .map((permiso) => permiso.$['android:name']);
    }

    test('cercanas-E03 — en iPhone, el permiso de uso mientras la app está abierta lleva el texto de para qué se usa', () => {
      expect(permisoDeUbicacion.texto).toMatch(/lavanderías cercanas/);
      expect(infoPlist.NSLocationWhenInUseUsageDescription).toBe(permisoDeUbicacion.texto);
    });

    test('cercanas-E03 — en iPhone, la configuración no declara ubicación en segundo plano', () => {
      expect(infoPlist).not.toHaveProperty('NSLocationAlwaysAndWhenInUseUsageDescription');
      expect(infoPlist).not.toHaveProperty('NSLocationAlwaysUsageDescription');
      expect((infoPlist.UIBackgroundModes as string[] | undefined) ?? []).not.toContain('location');
    });

    test('cercanas-E03 — en Android, la app declara la ubicación de primer plano', () => {
      expect(declarados()).toEqual(
        expect.arrayContaining(['android.permission.ACCESS_COARSE_LOCATION', 'android.permission.ACCESS_FINE_LOCATION']),
      );
    });

    test('cercanas-E03 — en Android, la configuración no declara ubicación en segundo plano', () => {
      expect(declarados()).not.toContain('android.permission.ACCESS_BACKGROUND_LOCATION');
      expect(declarados()).not.toContain('android.permission.FOREGROUND_SERVICE_LOCATION');
    });

    test('cercanas-E03 — en Android, el manifiesto final quita la ubicación en segundo plano aunque una librería la traiga', () => {
      const quitados = permisosDeAndroid
        .filter((permiso) => permiso.$['tools:node'] === 'remove')
        .map((permiso) => permiso.$['android:name']);
      expect(quitados).toContain('android.permission.ACCESS_BACKGROUND_LOCATION');
    });
  });

  describe('en la pantalla principal', () => {
    test.each(PLATAFORMAS)(
      'cercanas-E03 — (%s) la primera vez, la app dice para qué usa la ubicación antes de pedirla',
      async (plataforma) => {
        await abrirLaPantallaPrincipal(plataforma);

        expect(await screen.findByText(permisoDeUbicacion.texto)).toBeOnTheScreen();
        expect(screen.getByRole('button', { name: 'Continuar' })).toBeOnTheScreen();
        expect(gps.requestForegroundPermissionsAsync).not.toHaveBeenCalled();
        expect(consultasDeCercanas()).toEqual([]);
      },
    );

    test.each(PLATAFORMAS)(
      'cercanas-E03 — (%s) "Continuar" pide el permiso de uso mientras la app está abierta, nunca el de segundo plano',
      async (plataforma) => {
        const usuario = userEvent.setup();
        await abrirLaPantallaPrincipal(plataforma);

        await usuario.press(await screen.findByRole('button', { name: 'Continuar' }));

        await waitFor(() => expect(gps.requestForegroundPermissionsAsync).toHaveBeenCalledTimes(1));
        expect(gps.requestBackgroundPermissionsAsync).not.toHaveBeenCalled();
        expect(gps.getBackgroundPermissionsAsync).not.toHaveBeenCalled();
      },
    );

    test.each(PLATAFORMAS)(
      'cercanas-E03 — (%s) si la persona no da el permiso, la app no consulta ni pide el de segundo plano',
      async (plataforma) => {
        gps.__respuestaAlPedir(gps.PermissionStatus.DENIED);
        const usuario = userEvent.setup();
        await abrirLaPantallaPrincipal(plataforma);

        await usuario.press(await screen.findByRole('button', { name: 'Continuar' }));

        await waitFor(() => expect(screen.queryByRole('button', { name: 'Continuar' })).not.toBeOnTheScreen());
        expect(gps.getCurrentPositionAsync).not.toHaveBeenCalled();
        expect(gps.requestBackgroundPermissionsAsync).not.toHaveBeenCalled();
        expect(consultasDeCercanas()).toEqual([]);
        // Lo que muestra en su lugar espera a cercanas-E05 (DD-083); mientras, la sesión se puede cerrar.
        expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();
      },
    );
  });
});

describe('cercanas-E04: con permiso, la consulta usa la ubicación del teléfono', () => {
  const URL_ESPERADA = `${BASE}/lavanderias/cercanas?latitud=24.0277&longitud=-104.6532`;

  test.each(PLATAFORMAS)(
    'cercanas-E04 — (%s) con el permiso dado, consulta las lavanderías con la ubicación actual y la sesión',
    async (plataforma) => {
      gps.__permiso(gps.PermissionStatus.GRANTED);
      gps.__ubicacion(UBICACION);

      await abrirLaPantallaPrincipal(plataforma);

      await waitFor(() => expect(consultasDeCercanas()).toHaveLength(1));
      const [{ url, opciones }] = consultasDeCercanas();
      expect(url).toBe(URL_ESPERADA);
      expect(opciones.method).toBe('GET');
      expect((opciones.headers as Record<string, string>).Authorization).toBe(`Bearer ${TOKEN}`);
      expect(gps.requestForegroundPermissionsAsync).not.toHaveBeenCalled();
      expect(screen.queryByText(permisoDeUbicacion.texto)).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)('cercanas-E04 — (%s) y centra el mapa en la ubicación del teléfono', async (plataforma) => {
    gps.__permiso(gps.PermissionStatus.GRANTED);
    gps.__ubicacion(UBICACION);

    await abrirLaPantallaPrincipal(plataforma);

    const mapa = await screen.findByTestId('mapa');
    expect(mapa.props.initialRegion).toMatchObject(UBICACION);
  });

  test.each(PLATAFORMAS)(
    'cercanas-E04 — (%s) al dar el permiso con "Continuar", consulta con la ubicación actual',
    async (plataforma) => {
      gps.__ubicacion(UBICACION);
      const usuario = userEvent.setup();
      await abrirLaPantallaPrincipal(plataforma);

      await usuario.press(await screen.findByRole('button', { name: 'Continuar' }));

      await waitFor(() => expect(consultasDeCercanas()).toHaveLength(1));
      expect(consultasDeCercanas()[0].url).toBe(URL_ESPERADA);
      expect(await screen.findByTestId('mapa')).toBeOnTheScreen();
    },
  );

  // En Android, la precisión equilibrada sale de la red; sin red (o en el emulador, que solo simula el GPS) no llega.
  test('cercanas-E04 — si la ubicación con precisión equilibrada no llega, la pide con precisión alta', async () => {
    gps.__permiso(gps.PermissionStatus.GRANTED);
    gps.__ubicacion(UBICACION);
    gps.__fallaConPrecision([gps.Accuracy.Balanced]);

    await abrirLaPantallaPrincipal('android');

    await waitFor(() => expect(consultasDeCercanas()).toHaveLength(1));
    expect(consultasDeCercanas()[0].url).toBe(URL_ESPERADA);
  });

  test('cercanas-E04 — si tampoco llega con precisión alta, usa la última ubicación que el teléfono recuerda', async () => {
    gps.__permiso(gps.PermissionStatus.GRANTED);
    gps.__fallaConPrecision([gps.Accuracy.Balanced, gps.Accuracy.High]);
    gps.__ultimaConocida(UBICACION);

    await abrirLaPantallaPrincipal('android');

    await waitFor(() => expect(consultasDeCercanas()).toHaveLength(1));
    expect(consultasDeCercanas()[0].url).toBe(URL_ESPERADA);
  });

  test('cercanas-E04 — sin ninguna ubicación, lo dice y no consulta', async () => {
    gps.__permiso(gps.PermissionStatus.GRANTED);
    gps.__fallaConPrecision([gps.Accuracy.Balanced, gps.Accuracy.High]);

    await abrirLaPantallaPrincipal('android');

    expect(await screen.findByText('No pudimos obtener tu ubicación. Revisa que esté activada.')).toBeOnTheScreen();
    expect(consultasDeCercanas()).toEqual([]);
  });

  // La ubicación viaja en la consulta y la app no la conserva (design.md, decisión 6).
  test('cercanas-E04 — la ubicación no se guarda en el teléfono', async () => {
    gps.__permiso(gps.PermissionStatus.GRANTED);
    gps.__ubicacion(UBICACION);

    await abrirLaPantallaPrincipal('ios');

    await waitFor(() => expect(consultasDeCercanas()).toHaveLength(1));
    const guardado = jest.mocked(SecureStore.setItemAsync).mock.calls.map(([, valor]) => valor);
    expect(guardado.filter((valor) => valor.includes(String(UBICACION.latitude)))).toEqual([]);
  });

  describe('el cliente de la consulta', () => {
    const PUNTO = { latitud: 24.0277, longitud: -104.6532 };

    test('cercanas-E04 — va a la ruta del contrato con el punto y el token, y lee la lista', async () => {
      await expect(consultarCercanas(TOKEN, PUNTO)).resolves.toEqual({ ok: true, datos: [LAVANDERIA] });
      expect(fetchFalso).toHaveBeenCalledTimes(1);
      const [url, opciones] = fetchFalso.mock.calls[0] as [string, RequestInit];
      expect(url).toBe(URL_ESPERADA);
      expect(opciones.headers).toMatchObject({ Accept: 'application/json', Authorization: `Bearer ${TOKEN}` });
    });

    test('cercanas-E04 — si el servidor manda los números como texto, la app los lee como números', async () => {
      servidor([{ ...LAVANDERIA, latitud: '24.03', longitud: '-104.66', distancia_metros: '700', calificacion_promedio: '4.60' }]);

      await expect(consultarCercanas(TOKEN, PUNTO)).resolves.toEqual({ ok: true, datos: [LAVANDERIA] });
    });

    test('cercanas-E04 — una lavandería sin calificaciones llega con la calificación vacía', async () => {
      servidor([{ ...LAVANDERIA, calificacion_promedio: null, numero_de_calificaciones: 0 }]);

      await expect(consultarCercanas(TOKEN, PUNTO)).resolves.toEqual({
        ok: true,
        datos: [{ ...LAVANDERIA, calificacion_promedio: null, numero_de_calificaciones: 0 }],
      });
    });

    test('cercanas-E04 — una respuesta que no trae la lista del contrato cuenta como falla del servidor', async () => {
      fetchFalso.mockResolvedValue(respuesta(200, { datos: [] }));

      await expect(consultarCercanas(TOKEN, PUNTO)).resolves.toMatchObject({ ok: false, motivo: 'servidor' });
    });

    test('cercanas-E04 — una lavandería sin ubicación válida cuenta como falla del servidor', async () => {
      servidor([{ ...LAVANDERIA, latitud: null }]);

      await expect(consultarCercanas(TOKEN, PUNTO)).resolves.toMatchObject({ ok: false, motivo: 'servidor' });
    });

    test('cercanas-E04 — sin conexión, la consulta lo dice en lugar de fallar', async () => {
      fetchFalso.mockRejectedValue(new TypeError('Network request failed'));

      await expect(consultarCercanas(TOKEN, PUNTO)).resolves.toEqual({ ok: false, motivo: 'sin-conexion' });
    });
  });
});
