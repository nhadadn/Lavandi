// Spec: openspec/changes/historial-y-calificacion/specs/historial/spec.md, historial-E06, historial-E07 e historial-E08
// (tarea 2.3). La calificación del servicio de un pedido (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #14):
// de 1 a 5 estrellas con un comentario opcional. "Mis pedidos" la ofrece solo donde el contrato de la API la permite, en
// la lectura provisional de BR-25 (design.md, decisión 6): un pedido Entregado, una sola vez. A quién se califica sigue
// en DD-052: una sola calificación, del servicio.
//
// La pantalla, /pedido/calificacion/[id], manda POST /api/pedidos/{id_publico}/calificacion con { estrellas, comentario }
// y, con 201, vuelve a "Mis pedidos", que muestra el pedido calificado (historial-E06). Una calificación fuera de la escala
// no se puede mandar y, si el servidor la rechaza (422), no se guarda (historial-E08).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado con el
// "Contrato de la API: la calificación" de openspec/changes/historial-y-calificacion/design.md y el de GET /api/pedidos,
// que trae "calificacion": null | { estrellas, comentario } en cada pedido.
import path from 'node:path';

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

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/historial-calificacion';
const TOKEN = 'token-historial-calificacion';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const CENTRO = { id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc1', nombre: 'Lavandería de prueba centro' };

const EN_PROCESO = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yd1',
  folio: '7KQ2M9XA',
  fecha: '2026-11-23',
  lavanderia: CENTRO,
  estado: { codigo: 'en_proceso', nombre: 'En proceso', visible_usuario: true },
};
const ENTREGADO = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yd2',
  folio: '3HT8W2QZ',
  fecha: '2026-11-06',
  lavanderia: CENTRO,
  estado: { codigo: 'entregado', nombre: 'Entregado', visible_usuario: true },
};
const YA_CALIFICADO = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yd3',
  folio: '9PL4X7RB',
  fecha: '2026-10-09',
  lavanderia: CENTRO,
  estado: { codigo: 'entregado', nombre: 'Entregado', visible_usuario: true },
};
const RUTA_DE_LA_CALIFICACION = `/pedido/calificacion/${ENTREGADO.id_publico}`;

type Calificacion = { estrellas: number; comentario: string | null } | null;

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// Lo que guarda el servidor falso: la calificación de cada pedido. Una prueba puede contestar otra cosa al calificar.
let calificaciones: Map<string, Calificacion>;
let contestarLaCalificacion: ((cuerpo: unknown) => unknown) | null;

/** Lo que valida el servidor (CalificarPedidoRequest): estrellas, un entero de 1 a 5; comentario, texto o null. */
function calificar(id: string, cuerpo: unknown) {
  const pedido = [EN_PROCESO, ENTREGADO, YA_CALIFICADO].find(({ id_publico }) => id_publico === id);
  if (!pedido) {
    return respuesta(404, { message: 'No existe el pedido.' });
  }
  const { estrellas, comentario } = (cuerpo ?? {}) as { estrellas?: unknown; comentario?: unknown };
  if (estrellas === undefined || estrellas === null) {
    return respuesta(422, { message: 'Elige de 1 a 5 estrellas.', errors: { estrellas: ['Elige de 1 a 5 estrellas.'] } });
  }
  if (typeof estrellas !== 'number' || !Number.isInteger(estrellas) || estrellas < 1 || estrellas > 5) {
    return respuesta(422, { message: 'Las estrellas van de 1 a 5.', errors: { estrellas: ['Las estrellas van de 1 a 5.'] } });
  }
  if (pedido.estado.codigo !== 'entregado') {
    return respuesta(409, { message: 'Este pedido todavía no se puede calificar.' });
  }
  if (calificaciones.get(id)) {
    return respuesta(409, { message: 'Este pedido ya está calificado.' });
  }
  const texto = typeof comentario === 'string' && comentario.trim() !== '' ? comentario.trim() : null;
  calificaciones.set(id, { estrellas, comentario: texto });
  return respuesta(201, { calificacion: { estrellas, comentario: texto } });
}

function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
    if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    if (metodo === 'GET' && ruta === '/pedidos') {
      const pedidos = [EN_PROCESO, ENTREGADO, YA_CALIFICADO].map((pedido) => ({
        ...pedido,
        calificacion: calificaciones.get(pedido.id_publico) ?? null,
      }));
      return respuesta(200, { pedidos });
    }
    const partes = /^\/pedidos\/([^/]+)\/calificacion$/.exec(ruta);
    if (metodo === 'POST' && partes) {
      const cuerpo = typeof opciones.body === 'string' ? JSON.parse(opciones.body) : undefined;
      const otra = contestarLaCalificacion?.(cuerpo);
      return otra !== undefined && otra !== null ? otra : calificar(partes[1], cuerpo);
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

/** Los cuerpos con que la app mandó una calificación, con su ruta. */
function calificacionesMandadas(): { ruta: string; cuerpo: unknown }[] {
  return fetchFalso.mock.calls
    .filter(([, opciones]: [string, RequestInit | undefined]) => opciones?.method === 'POST')
    .map(([url, opciones]: [string, RequestInit]) => ({
      ruta: url.slice(BASE.length),
      cuerpo: JSON.parse(opciones.body as string),
    }));
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

async function abrirMisPedidos(plataforma: Plataforma) {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: '/pedidos' });
  await screen.findByTestId(`pedido-${ENTREGADO.id_publico}`);
}

/** Desde "Mis pedidos", abre la calificación del pedido entregado. */
async function abrirLaCalificacion(plataforma: Plataforma) {
  await abrirMisPedidos(plataforma);
  await fireEvent.press(screen.getByRole('link', { name: 'Calificar el pedido 3HT8W2QZ' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_CALIFICACION));
}

function estrella(numero: number) {
  return within(screen.getByTestId('estrellas')).getByRole('radio', {
    name: numero === 1 ? '1 estrella' : `${numero} estrellas`,
  });
}

function botonDeEnviar(nombre = 'Enviar calificación') {
  return within(screen.getByTestId('acciones')).getByRole('button', { name: nombre });
}

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba historial-calificacion';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  calificaciones = new Map([[YA_CALIFICADO.id_publico, { estrellas: 4, comentario: null }]]);
  contestarLaCalificacion = null;
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

describe('historial-E06: califica sin comentario', () => {
  test.each(PLATAFORMAS)(
    'historial-E06 — (%s) con 4 estrellas y sin comentario, la calificación se guarda así y el historial muestra el pedido calificado',
    async (plataforma) => {
      await abrirLaCalificacion(plataforma);

      await fireEvent.press(estrella(4));
      await fireEvent.press(botonDeEnviar());

      await waitFor(() => expect(screen).toHavePathname('/pedidos'));
      expect(calificacionesMandadas()).toEqual([
        { ruta: `/pedidos/${ENTREGADO.id_publico}/calificacion`, cuerpo: { estrellas: 4, comentario: null } },
      ]);
      expect(calificaciones.get(ENTREGADO.id_publico)).toEqual({ estrellas: 4, comentario: null });
      const tarjeta = within(screen.getByTestId(`calificacion-${ENTREGADO.id_publico}`));
      expect(await tarjeta.findByText('Calificaste 4 de 5')).toBeOnTheScreen();
      expect(screen.queryByRole('link', { name: 'Calificar el pedido 3HT8W2QZ' })).not.toBeOnTheScreen();
    },
  );

  test('historial-E06 — la calificación va con la sesión, al pedido que se abrió', async () => {
    await abrirLaCalificacion('ios');

    await fireEvent.press(estrella(4));
    await fireEvent.press(botonDeEnviar());
    await waitFor(() => expect(calificacionesMandadas()).toHaveLength(1));

    expect(fetchFalso).toHaveBeenCalledWith(
      `${BASE}/pedidos/${ENTREGADO.id_publico}/calificacion`,
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ Authorization: `Bearer ${TOKEN}` }),
      }),
    );
  });

  test('historial-E06 — un comentario con solo espacios es una calificación sin comentario', async () => {
    await abrirLaCalificacion('android');

    await fireEvent.press(estrella(4));
    await fireEvent.changeText(screen.getByLabelText('Comentario (opcional)'), '   ');
    await fireEvent.press(botonDeEnviar());

    await waitFor(() => expect(calificacionesMandadas()).toHaveLength(1));
    expect(calificacionesMandadas()[0].cuerpo).toEqual({ estrellas: 4, comentario: null });
  });
});

describe('historial-E07: califica con comentario', () => {
  test.each(PLATAFORMAS)(
    'historial-E07 — (%s) con 5 estrellas y un comentario, la calificación se guarda con el comentario tal como lo escribió',
    async (plataforma) => {
      const comentario = 'La ropa llegó limpia y bien doblada. ¡Gracias!';
      await abrirLaCalificacion(plataforma);

      await fireEvent.press(estrella(5));
      await fireEvent.changeText(screen.getByLabelText('Comentario (opcional)'), comentario);
      await fireEvent.press(botonDeEnviar());

      await waitFor(() => expect(screen).toHavePathname('/pedidos'));
      expect(calificacionesMandadas()).toEqual([
        { ruta: `/pedidos/${ENTREGADO.id_publico}/calificacion`, cuerpo: { estrellas: 5, comentario } },
      ]);
      expect(calificaciones.get(ENTREGADO.id_publico)).toEqual({ estrellas: 5, comentario });
      const tarjeta = within(screen.getByTestId(`calificacion-${ENTREGADO.id_publico}`));
      expect(await tarjeta.findByText('Calificaste 5 de 5')).toBeOnTheScreen();
    },
  );
});

describe('historial-E08: una calificación fuera de la escala se rechaza', () => {
  test.each(PLATAFORMAS)(
    'historial-E08 — (%s) la pantalla ofrece solo de 1 a 5 estrellas, y sin estrellas no se puede mandar',
    async (plataforma) => {
      await abrirLaCalificacion(plataforma);

      const opciones = within(screen.getByTestId('estrellas')).getAllByRole('radio');
      expect(opciones.map((opcion) => opcion.props.accessibilityLabel)).toEqual([
        '1 estrella',
        '2 estrellas',
        '3 estrellas',
        '4 estrellas',
        '5 estrellas',
      ]);
      expect(screen.getByText('Toca una estrella.')).toBeOnTheScreen();
      const sinEstrellas = botonDeEnviar('Elige una estrella');
      expect(sinEstrellas).toBeDisabled();

      await fireEvent.press(sinEstrellas);

      expect(calificacionesMandadas()).toEqual([]);
      expect(screen).toHavePathname(RUTA_DE_LA_CALIFICACION);
    },
  );

  test('historial-E08 — si el servidor rechaza las estrellas (422), lo dice junto a ellas, no se guarda nada y la pantalla sigue', async () => {
    contestarLaCalificacion = () =>
      respuesta(422, { message: 'Las estrellas van de 1 a 5.', errors: { estrellas: ['Las estrellas van de 1 a 5.'] } });
    await abrirLaCalificacion('ios');

    await fireEvent.press(estrella(3));
    await fireEvent.press(botonDeEnviar());

    expect(
      await within(screen.getByTestId('campo-estrellas')).findByText('Las estrellas van de 1 a 5.'),
    ).toBeOnTheScreen();
    expect(calificaciones.get(ENTREGADO.id_publico)).toBeUndefined();
    expect(screen).toHavePathname(RUTA_DE_LA_CALIFICACION);
    expect(botonDeEnviar()).not.toBeBusy();
  });

  test('historial-E08 — el error del comentario (422) va junto al campo', async () => {
    contestarLaCalificacion = () =>
      respuesta(422, {
        message: 'El comentario no puede pasar de 1000 caracteres.',
        errors: { comentario: ['El comentario no puede pasar de 1000 caracteres.'] },
      });
    await abrirLaCalificacion('android');

    await fireEvent.press(estrella(5));
    await fireEvent.changeText(screen.getByLabelText('Comentario (opcional)'), 'x'.repeat(1001));
    await fireEvent.press(botonDeEnviar());

    expect(
      await within(screen.getByTestId('campo-comentario')).findByText('El comentario no puede pasar de 1000 caracteres.'),
    ).toBeOnTheScreen();
    expect(calificaciones.get(ENTREGADO.id_publico)).toBeUndefined();
  });
});

describe('historial-E06: dónde se ofrece la calificación (lectura provisional de BR-25)', () => {
  test.each(PLATAFORMAS)(
    'historial-E06 — (%s) "Mis pedidos" ofrece calificar solo el pedido Entregado sin calificar; el calificado dice sus estrellas',
    async (plataforma) => {
      await abrirMisPedidos(plataforma);

      expect(screen.getByRole('link', { name: 'Calificar el pedido 3HT8W2QZ' })).toBeOnTheScreen();
      // En proceso: todavía no se califica.
      expect(screen.queryByTestId(`calificacion-${EN_PROCESO.id_publico}`)).not.toBeOnTheScreen();
      expect(screen.queryByRole('link', { name: 'Calificar el pedido 7KQ2M9XA' })).not.toBeOnTheScreen();
      // Ya calificado: una sola vez.
      const calificado = within(screen.getByTestId(`calificacion-${YA_CALIFICADO.id_publico}`));
      expect(calificado.getByText('Calificaste 4 de 5')).toBeOnTheScreen();
      expect(calificado.getByText('Calificaste 4 de 5')).toHaveAccessibleName('Calificaste con 4 de 5 estrellas');
      expect(screen.queryByRole('link', { name: 'Calificar el pedido 9PL4X7RB' })).not.toBeOnTheScreen();
    },
  );

  test('historial-E06 — la tarjeta sigue abriendo el seguimiento del pedido', async () => {
    await abrirMisPedidos('android');

    await fireEvent.press(screen.getByTestId(`pedido-${ENTREGADO.id_publico}`));

    await waitFor(() => expect(screen).toHavePathname(`/pedido/seguimiento/${ENTREGADO.id_publico}`));
  });
});

describe('historial-E06 a E08: la pantalla de calificación', () => {
  test.each(PLATAFORMAS)(
    'la pantalla lleva "Califica tu servicio", el pedido que se califica, las 5 estrellas y el comentario opcional',
    async (plataforma) => {
      await abrirLaCalificacion(plataforma);

      expect(screen.getByRole('header', { name: 'Califica tu servicio' })).toBeOnTheScreen();
      expect(screen.getByText('Pedido 3HT8W2QZ · Lavandería de prueba centro')).toBeOnTheScreen();
      expect(screen.getByText('¿Cómo estuvo el servicio?')).toBeOnTheScreen();
      expect(screen.getByLabelText('Comentario (opcional)')).toBeOnTheScreen();
      // Una sola calificación, del servicio (DD-052): sin repartidor.
      expect(screen.queryByText('Quien recogió y entregó')).not.toBeOnTheScreen();
    },
  );

  test('al tocar una estrella, queda elegida con las anteriores llenas, y se puede cambiar antes de mandar', async () => {
    await abrirLaCalificacion('ios');

    await fireEvent.press(estrella(4));

    expect(estrella(4)).toBeChecked();
    expect(estrella(3)).not.toBeChecked();
    expect(screen.getByText('4 de 5')).toBeOnTheScreen();
    for (const numero of [1, 2, 3, 4]) {
      expect(within(estrella(numero)).getByText('★')).toHaveStyle({ color: color.estado.atencion });
    }
    expect(within(estrella(5)).getByText('☆')).toBeOnTheScreen();
    expect(botonDeEnviar()).toBeEnabled();

    await fireEvent.press(estrella(2));

    expect(estrella(2)).toBeChecked();
    expect(estrella(4)).not.toBeChecked();
    expect(screen.getByText('2 de 5')).toBeOnTheScreen();
  });

  test('cada estrella mide al menos el área táctil mínima', async () => {
    await abrirLaCalificacion('android');

    for (const numero of [1, 2, 3, 4, 5]) {
      expect(estrella(numero)).toHaveStyle({ minWidth: 48, minHeight: 48 });
    }
  });

  test.each([
    ['ya está calificado (409)', 'Este pedido ya está calificado.'],
    ['todavía no se puede calificar (409)', 'Este pedido todavía no se puede calificar.'],
  ])('si el servidor dice que el pedido %s, dice su mensaje y no vuelve a la lista', async (_caso, mensaje) => {
    contestarLaCalificacion = () => respuesta(409, { message: mensaje });
    await abrirLaCalificacion('ios');

    await fireEvent.press(estrella(5));
    await fireEvent.press(botonDeEnviar());

    expect(await screen.findByRole('alert')).toHaveTextContent(mensaje);
    expect(screen).toHavePathname(RUTA_DE_LA_CALIFICACION);
  });

  test('si el pedido no existe o es de otra cuenta (404), dice que no lo encontró', async () => {
    contestarLaCalificacion = () => respuesta(404, { message: 'No existe el pedido.' });
    await abrirLaCalificacion('android');

    await fireEvent.press(estrella(5));
    await fireEvent.press(botonDeEnviar());

    expect(await screen.findByRole('alert')).toHaveTextContent('No encontramos este pedido.');
  });

  // Sin escenario propio: los mensajes generales de la app, como las demás pantallas.
  test.each([
    ['sin conexión', MENSAJES.sinConexion, () => Promise.reject(new TypeError('Network request failed'))],
    ['una falla del servidor (500)', MENSAJES.servidor, () => respuesta(500, { message: 'Server Error' })],
  ])('con %s, lo dice con el mensaje de la app y la pantalla sigue', async (_caso, mensaje, contestar) => {
    contestarLaCalificacion = contestar;
    await abrirLaCalificacion('ios');

    await fireEvent.press(estrella(5));
    await fireEvent.press(botonDeEnviar());

    expect(await screen.findByRole('alert')).toHaveTextContent(mensaje);
    expect(screen).toHavePathname(RUTA_DE_LA_CALIFICACION);
  });

  test('mientras manda, el botón queda ocupado y un segundo toque no manda otra', async () => {
    let soltar: (valor: unknown) => void = () => {};
    contestarLaCalificacion = () => new Promise((resolver) => (soltar = resolver));
    await abrirLaCalificacion('android');

    await fireEvent.press(estrella(5));
    await fireEvent.press(botonDeEnviar());
    await waitFor(() => expect(calificacionesMandadas()).toHaveLength(1));
    expect(botonDeEnviar()).toBeBusy();
    await fireEvent.press(botonDeEnviar());

    contestarLaCalificacion = null;
    soltar(respuesta(201, { calificacion: { estrellas: 5, comentario: null } }));
    await waitFor(() => expect(screen).toHavePathname('/pedidos'));
    expect(calificacionesMandadas()).toHaveLength(1);
  });

  test('"Regresar" vuelve a "Mis pedidos" sin mandar nada', async () => {
    await abrirLaCalificacion('ios');

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedidos'));
    expect(calificacionesMandadas()).toEqual([]);
  });
});
