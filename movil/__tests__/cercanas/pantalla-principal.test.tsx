// Spec: openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md, cercanas-E12, cercanas-E14 y cercanas-E15
// (tarea 3.1). La pantalla principal: un pin por lavandería, su tarjeta (componente 1 de vault/design-system/
// componentes.md) y el paso al perfil; el estado vacío; y "Pedido nuevo", que abre la lista para elegir lavandería.
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android. El mapa, el permiso y el GPS son
// las simulaciones de __mocks__/, y el servidor va simulado. El estado de disponibilidad de la tarjeta es cercanas-E13,
// que espera a DD-083 y DD-078 (tarea 3.2).
import path from 'node:path';

import Constants from 'expo-constants';
import * as Location from 'expo-location';
import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/cercanas-pantalla';
const TOKEN = 'token-cercanas-pantalla';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const UBICACION = { latitude: 24.0277, longitude: -104.6532 };
const CONSULTA = `${BASE}/lavanderias/cercanas?latitud=24.0277&longitud=-104.6532`;

// Tres lavanderías de prueba, como las que devuelve la consulta.
const UNO = {
  id_publico: '01JD00000000000000000000C1',
  nombre: 'Lavandería de prueba uno',
  latitud: 24.031,
  longitud: -104.661,
  distancia_metros: 800,
  calificacion_promedio: 4.5,
  numero_de_calificaciones: 3,
};
const DOS = {
  id_publico: '01JD00000000000000000000C2',
  nombre: 'Lavandería de prueba dos',
  latitud: 24.021,
  longitud: -104.641,
  distancia_metros: 1200,
  calificacion_promedio: 4.8,
  numero_de_calificaciones: 12,
};
const TRES = {
  id_publico: '01JD00000000000000000000C3',
  nombre: 'Lavandería de prueba tres',
  latitud: 24.041,
  longitud: -104.671,
  distancia_metros: 2700,
  calificacion_promedio: null,
  numero_de_calificaciones: 0,
};
const TRES_LAVANDERIAS = [UNO, DOS, TRES];
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const gps = Location as unknown as typeof import('../../__mocks__/expo-location');
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso: reconoce el token, revoca la sesión al cerrarla y contesta la consulta con la lista que se le dé.
function servidor(lavanderias: unknown[]) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/sesion' && opciones.method === 'DELETE') {
      return respuesta(204, undefined);
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

function consultasDeCercanas(): string[] {
  return fetchFalso.mock.calls.map(([url]: [string]) => url).filter((url: string) => url.includes('/lavanderias/'));
}

// La persona que ya inició sesión y dio el permiso de ubicación abre la app en la pantalla principal, en un iPhone o en
// un Android. Por omisión, el Android es una compilación con la llave de Google Maps (cercanas-E02).
async function abrirLaPantallaPrincipal(
  plataforma: Plataforma,
  lavanderias: unknown[] = TRES_LAVANDERIAS,
  { conLlaveDeMapas = true } = {},
) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: conLlaveDeMapas };
  gps.__permiso(gps.PermissionStatus.GRANTED);
  gps.__ubicacion(UBICACION);
  servidor(lavanderias);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: '/inicio' });
  await waitFor(() => expect(consultasDeCercanas()).toHaveLength(1));
}

beforeEach(() => {
  almacen.__vaciar();
  gps.__reiniciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba cercanas';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
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
  delete configuracionDeLaApp.extra;
  jest.restoreAllMocks();
});

describe('cercanas-E12: del pin al perfil', () => {
  test.each(PLATAFORMAS)('cercanas-E12 — (%s) hay un pin por lavandería, cada uno en su ubicación', async (plataforma) => {
    await abrirLaPantallaPrincipal(plataforma);

    const mapa = await screen.findByTestId('mapa');
    for (const lavanderia of TRES_LAVANDERIAS) {
      const pin = within(mapa).getByTestId(`pin-${lavanderia.id_publico}`);
      expect(pin.props.coordinate).toEqual({ latitude: lavanderia.latitud, longitude: lavanderia.longitud });
    }
    expect(within(mapa).getAllByTestId(/^pin-/)).toHaveLength(3);
    // Hasta tocar un pin no hay tarjeta: la pantalla dice cuántas hay.
    expect(screen.queryByTestId(/^tarjeta-/)).not.toBeOnTheScreen();
    expect(screen.getByText('3 lavanderías cerca de ti. Toca un pin.')).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'cercanas-E12 — (%s) al tocar un pin se ve la tarjeta de esa lavandería con su nombre, distancia y calificación',
    async (plataforma) => {
      await abrirLaPantallaPrincipal(plataforma);

      await fireEvent.press(await screen.findByTestId(`pin-${DOS.id_publico}`));

      const tarjeta = await screen.findByTestId(`tarjeta-${DOS.id_publico}`);
      expect(within(tarjeta).getByText(DOS.nombre)).toBeOnTheScreen();
      expect(within(tarjeta).getByText('A 1.2 km · Calificación 4.8')).toBeOnTheScreen();
    },
  );

  test('cercanas-E12 — al tocar otro pin, la tarjeta pasa a esa lavandería', async () => {
    await abrirLaPantallaPrincipal('ios');

    await fireEvent.press(await screen.findByTestId(`pin-${DOS.id_publico}`));
    await fireEvent.press(screen.getByTestId(`pin-${UNO.id_publico}`));

    const tarjeta = await screen.findByTestId(`tarjeta-${UNO.id_publico}`);
    expect(within(tarjeta).getByText(UNO.nombre)).toBeOnTheScreen();
    expect(screen.queryByTestId(`tarjeta-${DOS.id_publico}`)).not.toBeOnTheScreen();
  });

  test('cercanas-E12 — la distancia de menos de un kilómetro va en metros', async () => {
    await abrirLaPantallaPrincipal('ios');

    await fireEvent.press(await screen.findByTestId(`pin-${UNO.id_publico}`));

    expect(await screen.findByText('A 800 m · Calificación 4.5')).toBeOnTheScreen();
  });

  test('cercanas-E12 — una lavandería sin calificaciones lo dice, sin promedio', async () => {
    await abrirLaPantallaPrincipal('ios');

    await fireEvent.press(await screen.findByTestId(`pin-${TRES.id_publico}`));

    const tarjeta = await screen.findByTestId(`tarjeta-${TRES.id_publico}`);
    expect(within(tarjeta).getByText('A 2.7 km · Aún sin calificaciones')).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)('cercanas-E12 — (%s) al tocar la tarjeta se abre el perfil de esa lavandería', async (plataforma) => {
    await abrirLaPantallaPrincipal(plataforma);
    await fireEvent.press(await screen.findByTestId(`pin-${DOS.id_publico}`));

    await fireEvent.press(await screen.findByRole('button', { name: new RegExp(DOS.nombre) }));

    await waitFor(() => expect(screen).toHavePathname(`/lavanderias/${DOS.id_publico}`));
  });

  test('cercanas-E12 — con una sola lavandería, la pantalla lo dice en singular', async () => {
    await abrirLaPantallaPrincipal('ios', [UNO]);

    expect(await screen.findByText('1 lavandería cerca de ti. Toca el pin.')).toBeOnTheScreen();
  });
});

describe('cercanas-E14: no hay lavanderías cercanas', () => {
  test.each(PLATAFORMAS)(
    'cercanas-E14 — (%s) con la lista vacía, la app dice que no hay lavanderías cercanas, con el mapa a la vista',
    async (plataforma) => {
      await abrirLaPantallaPrincipal(plataforma, []);

      expect(await screen.findByText('No hay lavanderías cerca de ti.')).toBeOnTheScreen();
      const mapa = screen.getByTestId('mapa');
      expect(within(mapa).queryAllByTestId(/^pin-/)).toHaveLength(0);
    },
  );

  test('cercanas-E14 — la lista de "Pedido nuevo" vacía también lo dice', async () => {
    await abrirLaPantallaPrincipal('ios', []);

    await fireEvent.press(await screen.findByRole('button', { name: 'Pedido nuevo' }));

    await waitFor(() => expect(screen).toHavePathname('/lavanderias'));
    expect(await screen.findByText('No hay lavanderías cerca de ti.')).toBeOnTheScreen();
  });
});

describe('cercanas-E15: "Pedido nuevo" lleva a elegir lavandería', () => {
  test.each(PLATAFORMAS)(
    'cercanas-E15 — (%s) "Pedido nuevo" abre la lista de lavanderías cercanas para elegir una',
    async (plataforma) => {
      await abrirLaPantallaPrincipal(plataforma);

      await fireEvent.press(await screen.findByRole('button', { name: 'Pedido nuevo' }));

      await waitFor(() => expect(screen).toHavePathname('/lavanderias'));
      expect(await screen.findByRole('header', { name: 'Elige tu lavandería' })).toBeOnTheScreen();
      for (const lavanderia of TRES_LAVANDERIAS) {
        expect(await screen.findByTestId(`tarjeta-${lavanderia.id_publico}`)).toBeOnTheScreen();
      }
      // La lista consulta con el mismo punto de la pantalla principal, sin volver a leer la ubicación.
      expect(consultasDeCercanas()).toEqual([CONSULTA, CONSULTA]);
      expect(gps.getCurrentPositionAsync).toHaveBeenCalledTimes(1);
    },
  );

  test.each(PLATAFORMAS)('cercanas-E15 — (%s) en la lista, tocar una lavandería abre su perfil', async (plataforma) => {
    await abrirLaPantallaPrincipal(plataforma);
    await fireEvent.press(await screen.findByRole('button', { name: 'Pedido nuevo' }));

    await fireEvent.press(await screen.findByTestId(`tarjeta-${TRES.id_publico}`));

    await waitFor(() => expect(screen).toHavePathname(`/lavanderias/${TRES.id_publico}`));
  });

  test('cercanas-E15 — "Regresar" en la lista vuelve a la pantalla principal', async () => {
    await abrirLaPantallaPrincipal('ios');
    await fireEvent.press(await screen.findByRole('button', { name: 'Pedido nuevo' }));
    await waitFor(() => expect(screen).toHavePathname('/lavanderias'));

    await fireEvent.press(await screen.findByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });
});

describe('cercanas-E02: en Android sin la llave de Google Maps, la pantalla sigue', () => {
  test('cercanas-E02 — sin el mapa, la pantalla dice cuántas hay y "Pedido nuevo" abre la lista', async () => {
    await abrirLaPantallaPrincipal('android', TRES_LAVANDERIAS, { conLlaveDeMapas: false });

    expect(await screen.findByTestId('mapa-no-disponible')).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa')).not.toBeOnTheScreen();
    expect(screen.getByText('3 lavanderías cerca de ti.')).toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('button', { name: 'Pedido nuevo' }));

    expect(await screen.findByTestId(`tarjeta-${UNO.id_publico}`)).toBeOnTheScreen();
  });
});

describe('registro-E13 y registro-E11 con la pantalla principal', () => {
  test('registro-E13 — "Cerrar sesión" sigue en la pantalla principal y lleva al inicio de sesión', async () => {
    await abrirLaPantallaPrincipal('ios');

    await fireEvent.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
  });

  test.each(['/lavanderias', `/lavanderias/${UNO.id_publico}`])(
    'registro-E11 — sin sesión, abrir %s lleva al inicio de sesión sin consultar lavanderías',
    async (ruta) => {
      servidor(TRES_LAVANDERIAS);
      await comprobarInstalacion();
      await marcarBienvenidaVista();

      await renderRouter(APP, { initialUrl: ruta });

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
      expect(consultasDeCercanas()).toEqual([]);
    },
  );
});
