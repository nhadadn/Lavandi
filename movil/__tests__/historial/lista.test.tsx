// Spec: openspec/changes/historial-y-calificacion/specs/historial/spec.md, historial-E01 (tarea 2.1). La pantalla "Mis
// pedidos" del Módulo A (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #13): la lista de los pedidos del
// usuario, cada uno con su folio, su fecha, su lavandería y la insignia de su estado, la del componente 3 del design
// system (vault/design-system/componentes.md). Al tocar un pedido se abre su seguimiento. Se abre la app real, con las
// rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado con el contrato de GET /api/pedidos:
// { "pedidos": [ { id_publico, folio, fecha, lavanderia: { id_publico, nombre }, estado: { codigo, nombre,
// visible_usuario } } ] }, del más reciente al más antiguo, con la fecha de la recolección en la hora de la plaza.
//
// No entran: el filtro por fecha (tarea 2.2, filtro-por-fecha.test.tsx), repetir un pedido (historial-E05, bloqueado por
// BR-25) ni calificarlo (tarea 2.3, calificacion.test.tsx, y el grupo 3).
import path from 'node:path';

import * as Location from 'expo-location';
import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';
import { color } from '../../src/tema/theme';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

/** Un elemento de la pantalla, como lo devuelven las consultas de la biblioteca de pruebas. */
type Elemento = ReturnType<typeof screen.getByTestId>;

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/historial-lista';
const TOKEN = 'token-historial-lista';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const CENTRO = { id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc1', nombre: 'Lavandería de prueba centro' };
const JARDINES = { id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc2', nombre: 'Lavandería de prueba jardines' };

const ESTADOS = {
  confirmado: { codigo: 'confirmado', nombre: 'Confirmado', visible_usuario: true },
  recolectado: { codigo: 'recolectado', nombre: 'Recolectado', visible_usuario: true },
  en_proceso: { codigo: 'en_proceso', nombre: 'En proceso', visible_usuario: true },
  entregado: { codigo: 'entregado', nombre: 'Entregado', visible_usuario: true },
} as const;

// historial-E01: tres pedidos, uno en proceso y dos entregados, del más reciente al más antiguo, como los da el servidor.
const EN_PROCESO = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ya1',
  folio: '7KQ2M9XA',
  fecha: '2026-11-23',
  lavanderia: CENTRO,
  estado: ESTADOS.en_proceso,
};
const ENTREGADO_RECIENTE = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ya2',
  folio: '3HT8W2QZ',
  fecha: '2026-11-06',
  lavanderia: CENTRO,
  estado: ESTADOS.entregado,
};
const ENTREGADO_ANTIGUO = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ya3',
  folio: '9PL4X7RB',
  fecha: '2026-10-09',
  lavanderia: JARDINES,
  estado: ESTADOS.entregado,
};
const TRES_PEDIDOS = [EN_PROCESO, ENTREGADO_RECIENTE, ENTREGADO_ANTIGUO];

// Lo que se ve de cada uno: la fecha como la escribe la app ("Lunes 23 de noviembre"), sin pasarla a otra zona.
const A_LA_VISTA = [
  { pedido: EN_PROCESO, folio: 'Pedido 7KQ2M9XA', fecha: 'Lunes 23 de noviembre', estado: 'En proceso' },
  { pedido: ENTREGADO_RECIENTE, folio: 'Pedido 3HT8W2QZ', fecha: 'Viernes 6 de noviembre', estado: 'Entregado' },
  { pedido: ENTREGADO_ANTIGUO, folio: 'Pedido 9PL4X7RB', fecha: 'Viernes 9 de octubre', estado: 'Entregado' },
] as const;

/** El pedido como lo devuelve GET /api/pedidos/{id_publico}, para el seguimiento que abre la lista. */
function pedidoEnSeguimiento(pedido: { id_publico: string; folio: string; lavanderia: unknown; estado: unknown }) {
  return {
    id_publico: pedido.id_publico,
    folio: pedido.folio,
    estado: pedido.estado,
    seguimiento: Object.values(ESTADOS).map(({ codigo, nombre }) => ({ codigo, nombre, ocurrido_en: null })),
    lavanderia: pedido.lavanderia,
  };
}

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const gps = Location as unknown as typeof import('../../__mocks__/expo-location');

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// Lo que el servidor falso contesta a la lista; por omisión, los tres pedidos.
let contestarLaLista: () => Promise<unknown>;

function conLosPedidos(pedidos: unknown[]) {
  contestarLaLista = async () => respuesta(200, { pedidos });
}

// El servidor falso: reconoce el token, contesta la lista solo con la sesión y el seguimiento del pedido que se abra.
function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
    if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/pedidos') {
      return contestarLaLista();
    }
    const abierto = TRES_PEDIDOS.find(({ id_publico }) => ruta === `/pedidos/${id_publico}`);
    if (abierto) {
      return respuesta(200, { pedido: pedidoEnSeguimiento(abierto) });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

/** Las veces que la app pidió la lista al servidor, con filtro o sin él; no cuenta el seguimiento de un pedido. */
function consultasDeLaLista(): number {
  const lista = `${BASE}/pedidos`;
  return fetchFalso.mock.calls.filter(([url]: [string]) => url === lista || url.startsWith(`${lista}?`)).length;
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión abre "Mis pedidos" y espera a que llegue la lista.
async function abrirMisPedidos(plataforma: Plataforma) {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: '/pedidos' });
  await waitFor(() => expect(consultasDeLaLista()).toBe(1));
}

/** La tarjeta de un pedido en la lista. */
function tarjeta(idPublico: string): Elemento {
  return screen.getByTestId(`pedido-${idPublico}`);
}

beforeEach(() => {
  almacen.__vaciar();
  gps.__reiniciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión lleva el nombre de la app en su logotipo.
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba historial-lista';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  conLosPedidos(TRES_PEDIDOS);
  servidor();
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
  jest.restoreAllMocks();
});

describe('historial-E01: el usuario ve la lista de sus pedidos', () => {
  test.each(PLATAFORMAS)(
    'historial-E01 — (%s) con tres pedidos, uno en proceso y dos entregados, ve los tres, cada uno con su folio, su fecha, su lavandería y su estado',
    async (plataforma) => {
      await abrirMisPedidos(plataforma);

      await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`);
      // Los tres, en el orden en que los da el servidor: del más reciente al más antiguo.
      expect(screen.getAllByTestId(/^pedido-/).map((elemento) => elemento.props.testID)).toEqual(
        TRES_PEDIDOS.map(({ id_publico }) => `pedido-${id_publico}`),
      );
      for (const { pedido, folio, fecha, estado } of A_LA_VISTA) {
        const suTarjeta = within(tarjeta(pedido.id_publico));
        expect(suTarjeta.getByText(folio)).toBeOnTheScreen();
        expect(suTarjeta.getByText(fecha)).toBeOnTheScreen();
        expect(suTarjeta.getByText(pedido.lavanderia.nombre)).toBeOnTheScreen();
        expect(within(suTarjeta.getByTestId('estado-del-pedido')).getByText(estado)).toBeOnTheScreen();
      }
    },
  );

  test('historial-E01 — la insignia del estado es la del seguimiento (componente 3): el nombre escrito y el color propio de cada estado', async () => {
    await abrirMisPedidos('android');

    const enProceso = within(await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`)).getByTestId('estado-del-pedido');
    const entregado = within(tarjeta(ENTREGADO_RECIENTE.id_publico)).getByTestId('estado-del-pedido');
    expect(enProceso).toHaveStyle({ borderColor: color.pedido.en_proceso });
    expect(within(enProceso).getByText('En proceso')).toHaveStyle({ color: color.pedido.en_proceso });
    expect(entregado).toHaveStyle({ borderColor: color.pedido.entregado });
    expect(within(entregado).getByText('Entregado')).toHaveStyle({ color: color.pedido.entregado });
  });

  test('historial-E01 — el lector de pantalla lee cada pedido completo: folio, lavandería, fecha y estado', async () => {
    await abrirMisPedidos('ios');

    expect(await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`)).toHaveAccessibleName(
      'Pedido 7KQ2M9XA, Lavandería de prueba centro, Lunes 23 de noviembre, En proceso',
    );
  });

  test('historial-E01 — un pedido cuyo estado el usuario no ve (visible_usuario en false) va sin insignia y sin nombrar su estado', async () => {
    const oculto = { ...EN_PROCESO, estado: { codigo: 'por_pagar', nombre: 'Por pagar', visible_usuario: false } };
    conLosPedidos([oculto]);

    await abrirMisPedidos('ios');

    const suTarjeta = await screen.findByTestId(`pedido-${oculto.id_publico}`);
    expect(within(suTarjeta).queryByTestId('estado-del-pedido')).not.toBeOnTheScreen();
    expect(screen.queryByText('Por pagar')).not.toBeOnTheScreen();
    expect(within(suTarjeta).getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)('historial-E01 — (%s) al tocar un pedido se abre su seguimiento', async (plataforma) => {
    await abrirMisPedidos(plataforma);

    await fireEvent.press(await screen.findByTestId(`pedido-${ENTREGADO_RECIENTE.id_publico}`));

    await waitFor(() => expect(screen).toHavePathname(`/pedido/seguimiento/${ENTREGADO_RECIENTE.id_publico}`));
    expect(await screen.findByTestId('seguimiento')).toBeOnTheScreen();
  });

  test('historial-E01 — al volver del seguimiento, la lista se pide de nuevo y muestra el estado al día', async () => {
    conLosPedidos([{ ...EN_PROCESO, estado: ESTADOS.confirmado }]);
    await abrirMisPedidos('android');
    await fireEvent.press(await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`));
    await screen.findByTestId('seguimiento');

    // Mientras se veía el seguimiento, la lavandería recolectó el pedido.
    conLosPedidos([{ ...EN_PROCESO, estado: ESTADOS.recolectado }]);
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedidos'));
    await waitFor(() => expect(consultasDeLaLista()).toBe(2));
    expect(await within(tarjeta(EN_PROCESO.id_publico)).findByText('Recolectado')).toBeOnTheScreen();
  });

  test('historial-E01 — la consulta lleva la sesión y, sin filtro, no lleva fechas', async () => {
    await abrirMisPedidos('ios');

    expect(fetchFalso).toHaveBeenCalledWith(
      `${BASE}/pedidos`,
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({ Authorization: `Bearer ${TOKEN}` }),
      }),
    );
  });
});

describe('historial-E01: la pantalla, sin pedidos y cuando algo falla', () => {
  test('la pantalla lleva el encabezado "Mis pedidos" con "Regresar"; abierta sin pantalla anterior, regresa al inicio', async () => {
    await abrirMisPedidos('android');

    expect(screen.getByRole('header', { name: 'Mis pedidos' })).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test.each(PLATAFORMAS)('(%s) el inicio lleva a "Mis pedidos"', async (plataforma) => {
    gps.__permiso(gps.PermissionStatus.GRANTED);
    await conSesion(plataforma);
    await renderRouter(APP, { initialUrl: '/inicio' });

    await fireEvent.press(await screen.findByRole('link', { name: 'Mis pedidos' }));

    await waitFor(() => expect(screen).toHavePathname('/pedidos'));
    expect(await screen.findByTestId(`pedido-${EN_PROCESO.id_publico}`)).toBeOnTheScreen();
  });

  test('sin ningún pedido, un mensaje neutro y ninguna tarjeta', async () => {
    conLosPedidos([]);

    await abrirMisPedidos('ios');

    expect(await screen.findByText('Todavía no tienes pedidos.')).toBeOnTheScreen();
    expect(screen.queryAllByTestId(/^pedido-/)).toHaveLength(0);
  });

  // Sin escenario propio: si la lista no llega, la pantalla usa los mensajes generales de la app, como el seguimiento,
  // la agenda y el perfil (src/cercanas/textos.ts).
  test.each([
    ['sin conexión', MENSAJES.sinConexion, () => Promise.reject(new TypeError('Network request failed'))],
    ['una falla del servidor (500)', MENSAJES.servidor, async () => respuesta(500, { message: 'Server Error' })],
    [
      'una respuesta fuera del contrato',
      MENSAJES.servidor,
      async () => respuesta(200, { pedidos: [{ id_publico: EN_PROCESO.id_publico, folio: EN_PROCESO.folio }] }),
    ],
  ])('si la lista no llega (%s), lo dice y no pinta ningún pedido', async (_caso, mensaje, contestar) => {
    contestarLaLista = contestar;

    await abrirMisPedidos('android');

    expect(await screen.findByText(mensaje)).toBeOnTheScreen();
    expect(screen.queryAllByTestId(/^pedido-/)).toHaveLength(0);
    expect(screen.queryByText('Todavía no tienes pedidos.')).not.toBeOnTheScreen();
    expect(screen.getByRole('header', { name: 'Mis pedidos' })).toBeOnTheScreen();
  });

  test('registro-E11 — sin sesión, abrir "Mis pedidos" lleva al inicio de sesión sin consultar la lista', async () => {
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    await renderRouter(APP, { initialUrl: '/pedidos' });

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(consultasDeLaLista()).toBe(0);
  });
});
