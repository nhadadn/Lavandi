// Spec: openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md, cercanas-E21 (tarea 4.1). La búsqueda ofrece los
// filtros de distancia máxima y de calificación mínima, combinables, con los cortes de la configuración y no escritos en
// la pantalla; la lista muestra lo que el servidor devuelve con esos filtros, y si no queda ninguna lavandería lo dice y
// deja quitar los filtros. Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android; el
// servidor va simulado con el contrato de GET /api/lavanderias/cercanas (distancia_maxima_metros y calificacion_minima,
// servidor/app/Http/Controllers/Api/LavanderiasCercanasController.php).
//
// "Dado cortes de distancia y de calificación en la configuración de prueba": esta prueba reemplaza la configuración de
// los cortes (src/configuracion/filtros.ts) por unos de prueba, distintos de los de la app, para comprobar que la
// pantalla los toma de ahí. Los valores reales los fija DD-083. El filtro de disponibilidad es cercanas-E19, que espera
// a DD-083 y DD-078.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

jest.mock('../../src/configuracion/filtros', () => ({
  FILTROS: {
    distanciasMaximasMetros: [500, 2000],
    calificacionesMinimas: [4, 4.5],
  },
}));

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/cercanas-filtros';
const TOKEN = 'token-cercanas-filtros';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const LISTA = '/lavanderias?latitud=24.0277&longitud=-104.6532';
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

function lavanderia(sufijo: string, distancia: number, calificacion: number | null) {
  return {
    id_publico: `01JD00000000000000000000F${sufijo}`,
    nombre: `Lavandería de prueba ${sufijo}`,
    latitud: 24.03,
    longitud: -104.66,
    distancia_metros: distancia,
    calificacion_promedio: calificacion,
    numero_de_calificaciones: calificacion === null ? 0 : 3,
  };
}

// Cumple las dos, solo la distancia, solo la calificación y ninguna (con "Hasta 2 km" y "4 o más").
const LAS_DOS = lavanderia('1', 400, 4.8);
const SOLO_DISTANCIA = lavanderia('2', 1500, 3.8);
const SOLO_CALIFICACION = lavanderia('3', 6000, 4.9);
const NINGUNA = lavanderia('4', 9000, 3.1);
const TODAS = [LAS_DOS, SOLO_DISTANCIA, SOLO_CALIFICACION, NINGUNA];

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso aplica los filtros de la consulta como el de verdad (cercanas-E16 a E18): distancia máxima en
// metros y calificación mínima, que una lavandería sin calificaciones no pasa.
function servidor(lavanderias: ReturnType<typeof lavanderia>[] = TODAS) {
  fetchFalso.mockImplementation(async (url: string) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      const { distancia, calificacion } = filtrosDe(url);
      return respuesta(200, {
        lavanderias: lavanderias.filter(
          (una) =>
            (distancia === null || una.distancia_metros <= distancia) &&
            (calificacion === null || (una.calificacion_promedio !== null && una.calificacion_promedio >= calificacion)),
        ),
      });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

function filtrosDe(url: string): { distancia: number | null; calificacion: number | null } {
  const parametros = new URL(url).searchParams;
  const distancia = parametros.get('distancia_maxima_metros');
  const calificacion = parametros.get('calificacion_minima');
  return {
    distancia: distancia === null ? null : Number(distancia),
    calificacion: calificacion === null ? null : Number(calificacion),
  };
}

function consultas(): string[] {
  return fetchFalso.mock.calls.map(([url]: [string]) => url).filter((url: string) => url.includes('/lavanderias/cercanas'));
}

function ultimaConsulta(): URLSearchParams {
  const todas = consultas();
  return new URL(todas[todas.length - 1]).searchParams;
}

function tarjetasALaVista(): string[] {
  return screen.queryAllByTestId(/^tarjeta-/).map((tarjeta) => String(tarjeta.props.testID).replace('tarjeta-', ''));
}

// La persona con sesión abre "Elige tu lavandería" con el punto de la pantalla principal (cercanas-E15), y espera la
// tarjeta de la primera lavandería de la lista.
async function abrirLaBusqueda(plataforma: Plataforma, primera = LAS_DOS) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: LISTA });
  await screen.findByTestId(`tarjeta-${primera.id_publico}`);
}

async function tocar(nombre: string) {
  const antes = consultas().length;
  await fireEvent.press(screen.getByRole('button', { name: nombre }));
  await waitFor(() => expect(consultas().length).toBeGreaterThan(antes));
  await waitFor(() => expect(screen.queryByText('Buscando lavanderías cercanas…')).not.toBeOnTheScreen());
}

beforeEach(() => {
  almacen.__vaciar();
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
  jest.restoreAllMocks();
});

describe('cercanas-E21: elegir filtros y quitarlos', () => {
  test.each(PLATAFORMAS)(
    'cercanas-E21 — (%s) la búsqueda ofrece los cortes de distancia y de calificación de la configuración, sin ninguno elegido',
    async (plataforma) => {
      await abrirLaBusqueda(plataforma);

      const filtros = screen.getByTestId('filtros');
      expect(within(filtros).getByText('Distancia')).toBeOnTheScreen();
      expect(within(filtros).getByText('Calificación')).toBeOnTheScreen();
      const cortes = within(filtros).getAllByRole('button');
      expect(cortes.map((corte) => within(corte).getByText(/.+/).props.children)).toEqual([
        'Hasta 500 m',
        'Hasta 2 km',
        '4 o más',
        '4.5 o más',
      ]);
      for (const corte of cortes) {
        expect(corte).not.toBeSelected();
      }
      // Sin filtros elegidos, la consulta no lleva ninguno.
      expect(ultimaConsulta().has('distancia_maxima_metros')).toBe(false);
      expect(ultimaConsulta().has('calificacion_minima')).toBe(false);
      expect(tarjetasALaVista()).toEqual(TODAS.map((una) => una.id_publico));
    },
  );

  test.each(PLATAFORMAS)(
    'cercanas-E21 — (%s) con un corte de cada uno, la lista muestra solo las lavanderías que el servidor devuelve con los dos filtros',
    async (plataforma) => {
      await abrirLaBusqueda(plataforma);

      await tocar('Hasta 2 km');
      await tocar('4 o más');

      expect(ultimaConsulta().get('distancia_maxima_metros')).toBe('2000');
      expect(ultimaConsulta().get('calificacion_minima')).toBe('4');
      expect(ultimaConsulta().get('latitud')).toBe('24.0277');
      expect(ultimaConsulta().get('longitud')).toBe('-104.6532');
      expect(tarjetasALaVista()).toEqual([LAS_DOS.id_publico]);
      expect(screen.getByRole('button', { name: 'Hasta 2 km' })).toBeSelected();
      expect(screen.getByRole('button', { name: '4 o más' })).toBeSelected();
      expect(screen.getByRole('button', { name: 'Hasta 500 m' })).not.toBeSelected();
    },
  );

  test.each(PLATAFORMAS)(
    'cercanas-E21 — (%s) si no queda ninguna, la app lo dice y "Quitar filtros" vuelve a la lista sin filtros',
    async (plataforma) => {
      servidor([SOLO_DISTANCIA, SOLO_CALIFICACION, NINGUNA]);
      await abrirLaBusqueda(plataforma, SOLO_DISTANCIA);

      await tocar('Hasta 500 m');
      await tocar('4.5 o más');

      expect(tarjetasALaVista()).toEqual([]);
      expect(screen.getByText('Ninguna lavandería coincide con estos filtros.')).toBeOnTheScreen();
      expect(screen.queryByText('No hay lavanderías cerca de ti.')).not.toBeOnTheScreen();

      await tocar('Quitar filtros');

      expect(ultimaConsulta().has('distancia_maxima_metros')).toBe(false);
      expect(ultimaConsulta().has('calificacion_minima')).toBe(false);
      expect(tarjetasALaVista()).toEqual([SOLO_DISTANCIA, SOLO_CALIFICACION, NINGUNA].map((una) => una.id_publico));
      expect(screen.queryByText('Ninguna lavandería coincide con estos filtros.')).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Quitar filtros' })).not.toBeOnTheScreen();
      for (const corte of within(screen.getByTestId('filtros')).getAllByRole('button')) {
        expect(corte).not.toBeSelected();
      }
    },
  );

  test('cercanas-E21 — un solo corte de distancia a la vez: elegir otro cambia el filtro', async () => {
    await abrirLaBusqueda('ios');

    await tocar('Hasta 500 m');
    await tocar('Hasta 2 km');

    expect(ultimaConsulta().get('distancia_maxima_metros')).toBe('2000');
    expect(screen.getByRole('button', { name: 'Hasta 2 km' })).toBeSelected();
    expect(screen.getByRole('button', { name: 'Hasta 500 m' })).not.toBeSelected();
    expect(tarjetasALaVista()).toEqual([LAS_DOS.id_publico, SOLO_DISTANCIA.id_publico]);
  });

  test('cercanas-E21 — volver a tocar el corte elegido lo quita', async () => {
    await abrirLaBusqueda('ios');
    await tocar('4.5 o más');
    expect(ultimaConsulta().get('calificacion_minima')).toBe('4.5');

    await tocar('4.5 o más');

    expect(ultimaConsulta().has('calificacion_minima')).toBe(false);
    expect(screen.getByRole('button', { name: '4.5 o más' })).not.toBeSelected();
    expect(tarjetasALaVista()).toEqual(TODAS.map((una) => una.id_publico));
  });

  test('cercanas-E14 y cercanas-E21 — sin filtros y sin lavanderías, el aviso es el de que no hay cercanas, sin "Quitar filtros"', async () => {
    servidor([]);
    jest.replaceProperty(Platform, 'OS', 'ios');
    await comprobarInstalacion();
    await marcarBienvenidaVista();
    await guardarToken(TOKEN);

    await renderRouter(APP, { initialUrl: LISTA });

    expect(await screen.findByText('No hay lavanderías cerca de ti.')).toBeOnTheScreen();
    expect(screen.queryByText('Ninguna lavandería coincide con estos filtros.')).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Quitar filtros' })).not.toBeOnTheScreen();
  });
});

describe('cercanas-E21: los cortes viven en la configuración', () => {
  test('cercanas-E21 — la configuración de la app trae cortes de distancia en metros y de calificación de 1 a 5', () => {
    const { FILTROS } = jest.requireActual<typeof import('../../src/configuracion/filtros')>(
      '../../src/configuracion/filtros',
    );

    expect(FILTROS.distanciasMaximasMetros.length).toBeGreaterThan(0);
    expect(FILTROS.calificacionesMinimas.length).toBeGreaterThan(0);
    for (const metros of FILTROS.distanciasMaximasMetros) {
      expect(Number.isInteger(metros) && metros > 0).toBe(true);
    }
    for (const estrellas of FILTROS.calificacionesMinimas) {
      expect(estrellas >= 1 && estrellas <= 5).toBe(true);
    }
  });
});
