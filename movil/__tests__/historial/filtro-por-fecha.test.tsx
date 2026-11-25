// Spec: openspec/changes/historial-y-calificacion/specs/historial/spec.md, historial-E03 e historial-E04 (tarea 2.2). El
// filtro por fecha de "Mis pedidos" (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #13, "filtro por fecha"): el
// usuario escribe un rango, "Desde" y "Hasta", y la lista muestra los pedidos de esos días, los dos incluidos; con un
// rango sin pedidos, la app lo dice; y el filtro se quita para volver a la lista completa. La fecha que se filtra es la
// del resumen del pedido, en la hora de la plaza, y la cuenta el servidor (design.md, decisión 1): la app manda los dos
// días como AAAA-MM-DD en GET /api/pedidos?desde=…&hasta=…, y un 422 trae errors.desde o errors.hasta.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado: filtra
// como el de verdad, por la fecha de cada pedido, con los dos días incluidos. Las fechas se escriben en dos campos de
// texto, DD/MM/AAAA, con el teclado de números y las diagonales que pone la app: sin dependencia nativa nueva (design.md,
// Open Questions).
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { conDiagonales, fechaDeTexto } from '../../src/historial/fechas';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/historial-filtro';
const TOKEN = 'token-historial-filtro';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const LAVANDERIA = { id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc1', nombre: 'Lavandería de prueba filtro' };
const ENTREGADO = { codigo: 'entregado', nombre: 'Entregado', visible_usuario: true };

function pedidoDel(fecha: string, folio: string, sufijo: string) {
  return { id_publico: `01jd8x6m3v2c9q4w5e7r8t0yf${sufijo}`, folio, fecha, lavanderia: LAVANDERIA, estado: ENTREGADO };
}

// historial-E03: pedidos del 3, del 10 y del 20 de marzo, del más reciente al más antiguo. Ninguno en abril (E04).
const DEL_20 = pedidoDel('2026-03-20', '2MZ0VX20', '3');
const DEL_10 = pedidoDel('2026-03-10', '2MZ0VX10', '2');
const DEL_3 = pedidoDel('2026-03-03', '2MZ0VX03', '1');
const PEDIDOS = [DEL_20, DEL_10, DEL_3];

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

/** El valor de un parámetro de la consulta, o null si no viene. */
function parametro(consulta: string, nombre: string): string | null {
  const encontrado = new RegExp(`(?:^|&)${nombre}=([^&]*)`).exec(consulta);
  return encontrado ? decodeURIComponent(encontrado[1]) : null;
}

// Lo que el servidor falso contesta a una consulta con fechas que rechaza (422), si se le pide.
let rechazo: { campo: 'desde' | 'hasta'; mensaje: string } | null = null;

// El servidor falso: reconoce el token y filtra los pedidos por su fecha, con los dos días incluidos, como el de verdad.
function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const [ruta, consulta = ''] = url.slice(BASE.length).split('?');
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
    if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/pedidos') {
      if (rechazo && consulta !== '') {
        return respuesta(422, { message: rechazo.mensaje, errors: { [rechazo.campo]: [rechazo.mensaje] } });
      }
      const desde = parametro(consulta, 'desde');
      const hasta = parametro(consulta, 'hasta');
      const pedidos = PEDIDOS.filter(
        ({ fecha }) => (desde === null || fecha >= desde) && (hasta === null || fecha <= hasta),
      );
      return respuesta(200, { pedidos });
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

/** Las direcciones con que la app pidió la lista, en orden. */
function consultasDeLaLista(): string[] {
  const lista = `${BASE}/pedidos`;
  return fetchFalso.mock.calls
    .map(([url]: [string]) => url)
    .filter((url: string) => url === lista || url.startsWith(`${lista}?`));
}

// La persona con sesión abre "Mis pedidos" y espera a que lleguen sus pedidos.
async function abrirMisPedidos(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: '/pedidos' });
  await screen.findByTestId(`pedido-${DEL_20.id_publico}`);
}

// Abre el filtro, escribe las dos fechas como en el teclado de números, sin diagonales, y lo aplica.
async function filtrar(desde: string, hasta: string) {
  if (screen.queryByLabelText('Desde') === null) {
    await fireEvent.press(screen.getByRole('button', { name: 'Elegir fechas' }));
  }
  await fireEvent.changeText(screen.getByLabelText('Desde'), desde);
  await fireEvent.changeText(screen.getByLabelText('Hasta'), hasta);
  await fireEvent.press(screen.getByRole('button', { name: 'Aplicar' }));
}

/** Los pedidos que se ven en la lista, por su id_publico. */
function pedidosALaVista(): string[] {
  return screen.queryAllByTestId(/^pedido-/).map((elemento) => String(elemento.props.testID).slice('pedido-'.length));
}

beforeEach(() => {
  almacen.__vaciar();
  rechazo = null;
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión lleva el nombre de la app en su logotipo.
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba historial-filtro';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
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

describe('historial-E03: el filtro deja solo los pedidos del rango', () => {
  test.each(PLATAFORMAS)(
    'historial-E03 — (%s) con pedidos del 3, del 10 y del 20 de marzo, filtra del 10 al 20 de marzo: ve los del 10 y del 20 y no el del 3',
    async (plataforma) => {
      await abrirMisPedidos(plataforma);
      expect(pedidosALaVista()).toEqual([DEL_20.id_publico, DEL_10.id_publico, DEL_3.id_publico]);

      await filtrar('10032026', '20032026');

      await waitFor(() => expect(pedidosALaVista()).toEqual([DEL_20.id_publico, DEL_10.id_publico]));
      expect(screen.getByText('Viernes 20 de marzo')).toBeOnTheScreen();
      expect(screen.getByText('Martes 10 de marzo')).toBeOnTheScreen();
      expect(screen.queryByText('Martes 3 de marzo')).not.toBeOnTheScreen();
    },
  );

  test('historial-E03 — la consulta lleva el rango con los dos días, AAAA-MM-DD, y la sesión', async () => {
    await abrirMisPedidos('ios');

    await filtrar('10032026', '20032026');

    await waitFor(() => expect(consultasDeLaLista()).toHaveLength(2));
    expect(fetchFalso).toHaveBeenLastCalledWith(
      `${BASE}/pedidos?desde=2026-03-10&hasta=2026-03-20`,
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({ Authorization: `Bearer ${TOKEN}` }),
      }),
    );
  });

  test.each(PLATAFORMAS)(
    'historial-E03 — (%s) las fechas se escriben con el teclado de números y la app pone las diagonales: DD/MM/AAAA',
    async (plataforma) => {
      await abrirMisPedidos(plataforma);
      await fireEvent.press(screen.getByRole('button', { name: 'Elegir fechas' }));

      const desde = screen.getByLabelText('Desde');
      await fireEvent.changeText(desde, '10032026');

      expect(desde).toHaveDisplayValue('10/03/2026');
      expect(desde.props.keyboardType).toBe('number-pad');
      expect(screen.getByLabelText('Hasta').props.keyboardType).toBe('number-pad');
    },
  );

  test.each([
    ['una fecha que no existe', '31022026'],
    ['una fecha a medias', '1003'],
    ['un campo vacío', ''],
  ])('historial-E03 — con %s, el filtro no se manda: lo dice junto al campo', async (_caso, escrita) => {
    await abrirMisPedidos('android');

    await filtrar(escrita, '20032026');

    expect(within(screen.getByTestId('campo-desde')).getByText('Escribe la fecha como DD/MM/AAAA.')).toBeOnTheScreen();
    expect(consultasDeLaLista()).toHaveLength(1);
    expect(pedidosALaVista()).toHaveLength(3);
  });

  test('historial-E03 — con "Hasta" antes de "Desde", el filtro no se manda: lo dice junto a "Hasta"', async () => {
    await abrirMisPedidos('ios');

    await filtrar('20032026', '10032026');

    expect(
      within(screen.getByTestId('campo-hasta')).getByText('No puede ser anterior a la fecha de inicio.'),
    ).toBeOnTheScreen();
    expect(consultasDeLaLista()).toHaveLength(1);
  });

  test('historial-E03 — si el servidor rechaza una fecha (422), lo dice junto a su campo y no pinta la lista', async () => {
    await abrirMisPedidos('android');
    rechazo = { campo: 'hasta', mensaje: 'La fecha final no es válida.' };

    await filtrar('10032026', '20032026');

    expect(
      await within(screen.getByTestId('campo-hasta')).findByText('La fecha final no es válida.'),
    ).toBeOnTheScreen();
    expect(pedidosALaVista()).toEqual([]);
    // Ni el mensaje general ni el de la lista vacía: lo que falló es la fecha.
    expect(screen.queryByText(MENSAJES.servidor)).not.toBeOnTheScreen();
    expect(screen.queryByText('No hay pedidos en esas fechas.')).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'historial-E03 — (%s) "Quitar filtro" vuelve a la lista completa, sin fechas en la consulta',
    async (plataforma) => {
      await abrirMisPedidos(plataforma);
      await filtrar('10032026', '20032026');
      await waitFor(() => expect(pedidosALaVista()).toHaveLength(2));

      await fireEvent.press(screen.getByRole('button', { name: 'Quitar filtro' }));

      await waitFor(() => expect(pedidosALaVista()).toHaveLength(3));
      expect(consultasDeLaLista().at(-1)).toBe(`${BASE}/pedidos`);
      // El filtro se cierra y queda listo para abrirse de nuevo, vacío.
      expect(screen.queryByLabelText('Desde')).not.toBeOnTheScreen();
      await fireEvent.press(screen.getByRole('button', { name: 'Elegir fechas' }));
      expect(screen.getByLabelText('Desde')).toHaveDisplayValue('');
      expect(screen.getByLabelText('Hasta')).toHaveDisplayValue('');
    },
  );
});

describe('historial-E04: un rango sin pedidos', () => {
  test.each(PLATAFORMAS)(
    'historial-E04 — (%s) sin pedidos en abril, filtra del 1 al 30 de abril: la lista queda vacía y la app dice que no hay pedidos en esas fechas',
    async (plataforma) => {
      await abrirMisPedidos(plataforma);

      await filtrar('01042026', '30042026');

      expect(await screen.findByText('No hay pedidos en esas fechas.')).toBeOnTheScreen();
      expect(pedidosALaVista()).toEqual([]);
      expect(consultasDeLaLista().at(-1)).toBe(`${BASE}/pedidos?desde=2026-04-01&hasta=2026-04-30`);
      // Sin filtro, la lista vacía dice otra cosa: aquí no.
      expect(screen.queryByText('Todavía no tienes pedidos.')).not.toBeOnTheScreen();
    },
  );

  test('historial-E04 — al quitar el filtro, vuelven los pedidos y el mensaje se va', async () => {
    await abrirMisPedidos('ios');
    await filtrar('01042026', '30042026');
    await screen.findByText('No hay pedidos en esas fechas.');

    await fireEvent.press(screen.getByRole('button', { name: 'Quitar filtro' }));

    await waitFor(() => expect(pedidosALaVista()).toHaveLength(3));
    expect(screen.queryByText('No hay pedidos en esas fechas.')).not.toBeOnTheScreen();
  });
});

describe('historial-E03: cómo se leen las fechas del filtro', () => {
  test.each([
    ['', ''],
    ['1', '1'],
    ['10', '10'],
    ['100', '10/0'],
    ['1003', '10/03'],
    ['10032', '10/03/2'],
    ['10032026', '10/03/2026'],
    ['100320269', '10/03/2026'],
    ['10/03/2026', '10/03/2026'],
    ['10-03-2026', '10/03/2026'],
  ])('historial-E03 — lo escrito "%s" se ve como "%s"', (escrito, visto) => {
    expect(conDiagonales(escrito)).toBe(visto);
  });

  test.each([
    ['10/03/2026', '2026-03-10'],
    ['01/04/2026', '2026-04-01'],
    ['29/02/2028', '2028-02-29'],
    ['31/12/2026', '2026-12-31'],
  ])('historial-E03 — "%s" es el día %s', (escrito, dia) => {
    expect(fechaDeTexto(escrito)).toBe(dia);
  });

  test.each(['', '10/03', '10/03/26', '31/02/2026', '29/02/2026', '00/03/2026', '10/13/2026', '32/01/2026'])(
    'historial-E03 — "%s" no es una fecha',
    (escrito) => {
      expect(fechaDeTexto(escrito)).toBeNull();
    },
  );
});
