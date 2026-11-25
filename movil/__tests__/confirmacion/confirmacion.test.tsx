// Spec: openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md, confirmacion-E09 (tarea 2.2). La pantalla
// de confirmación, /pedido/confirmacion/[id], consulta GET /api/pedidos/{id_publico} con la sesión. Cuando el servidor
// dice que el pedido ya está Confirmado (estado.codigo "confirmado", con folio y confirmado_en), muestra el folio y el
// resumen con la lavandería, el servicio, la fecha con su ventana y la dirección de recogida, con el componente 6 del
// design system. Mientras no lo está, no muestra folio ni nombra el estado previo al pago (DD-123, ADR-0021). Un pedido
// que no existe o es de otra cuenta responde 404 (confirmacion-E12): la app dice que no lo encontró. Al salir, la app
// deja de tener pedido en curso y vuelve al inicio.
//
// Quien abre esta pantalla es la espera del pago cuando el cobro queda pagado (pago-E08, __tests__/pago/): aquí se abre
// por su ruta, con la API simulada con el contrato de servidor/app/Http/Controllers/Api/PedidosController.php, en iPhone
// y en Android.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import {
  elegirServicio,
  empezarPedido,
  leerPedidoEnCurso,
  vaciarPedidoEnCurso,
} from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/confirmacion-pantalla';
const TOKEN = 'token-confirmacion-pantalla';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const ID_DEL_PEDIDO = '01jd00000000000000000000p1';
// Ocho caracteres del alfabeto de Crockford, como los da ADR-0020. Es de prueba.
const FOLIO = 'K7M2Q9XA';

const COPIA_DE_CASA = {
  alias: 'Casa',
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: null,
  colonia: 'Colonia de prueba',
  codigo_postal: null,
  municipio: 'Municipio de prueba',
  referencias: 'Portón gris',
  latitud: 24.0277,
  longitud: -104.6532,
};

// El pedido como lo devuelve GET /api/pedidos/{id_publico}, ya Confirmado después de su pago. La ventana va con el
// desfase de una zona de prueba: la app muestra la hora de la plaza que da el servidor y no la convierte.
const CONFIRMADO = {
  id_publico: ID_DEL_PEDIDO,
  folio: FOLIO,
  confirmado_en: '2026-12-02T15:30:00Z',
  estado: { codigo: 'confirmado', nombre: 'Confirmado', visible_usuario: true },
  lavanderia: { id_publico: '01jd00000000000000000000l1', nombre: 'Lavandería de prueba confirmación' },
  servicios: [
    {
      codigo: 'planchado',
      nombre: 'Planchado',
      unidad: { codigo: 'kg', nombre: 'kilo' },
      precio_unitario: '30.00',
      cantidad_estimada: null,
      monto_estimado: null,
    },
  ],
  zona_horaria: 'Asia/Tokyo',
  ventana: {
    inicio: '2026-12-03T11:00:00+09:00',
    fin: '2026-12-03T12:00:00+09:00',
    fecha: '2026-12-03',
    hora_inicio: '11:00',
    hora_fin: '12:00',
  },
  recogida: COPIA_DE_CASA,
  entrega: COPIA_DE_CASA,
  monto_estimado: null,
  envio: null,
};

// El mismo pedido antes de su pago: en el estado previo al pago, que el usuario no ve (ADR-0021).
const POR_PAGAR = {
  ...CONFIRMADO,
  folio: null,
  confirmado_en: null,
  estado: { codigo: 'por_pagar', nombre: 'Por pagar', visible_usuario: false },
};

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo?: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

type Respuesta = ReturnType<typeof respuesta>;

// El servidor falso: reconoce el token y contesta el pedido. Lo demás que pida la pantalla principal al volver al
// inicio no es de esta prueba: contesta como sin conexión.
function servidor(pedido: () => Respuesta | Promise<Respuesta> = () => respuesta(200, { pedido: CONFIRMADO })) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const [ruta] = url.slice(BASE.length).split('?');
    const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
    if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === `/pedidos/${ID_DEL_PEDIDO}` && (opciones.method ?? 'GET') === 'GET') {
      return pedido();
    }
    throw new TypeError(`El servidor falso no esperaba ${opciones.method ?? 'GET'} ${ruta}`);
  });
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

async function abrirLaConfirmacion(plataforma: Plataforma) {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: `/pedido/confirmacion/${ID_DEL_PEDIDO}` });
  // Hasta que llega el pedido.
  await waitFor(() => expect(screen.queryByText('Cargando tu pedido…')).not.toBeOnTheScreen());
}

function botonDeSalir() {
  return within(screen.getByTestId('acciones')).getByRole('button', { name: 'Volver al inicio' });
}

beforeEach(() => {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba confirmacion-pantalla';
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

describe('confirmacion-E09: la pantalla muestra el folio y el resumen', () => {
  test.each(PLATAFORMAS)(
    'confirmacion-E09 — (%s) con el pedido Confirmado, muestra el folio y el resumen con la lavandería, el servicio, la fecha con su ventana y la recogida',
    async (plataforma) => {
      await abrirLaConfirmacion(plataforma);

      expect(screen.getByRole('header', { name: '¡Pedido confirmado!' })).toBeOnTheScreen();
      expect(screen.getByText('Tu folio es')).toBeOnTheScreen();
      expect(screen.getByText(FOLIO)).toBeOnTheScreen();
      const resumen = within(screen.getByTestId('resumen-del-pedido'));
      expect(resumen.getByText('Lavandería de prueba confirmación')).toBeOnTheScreen();
      expect(resumen.getByText('Planchado')).toBeOnTheScreen();
      expect(resumen.getByText('Jueves 3 de diciembre · 11:00 – 12:00')).toBeOnTheScreen();
      const recogida = within(resumen.getByTestId('dato-recogida'));
      expect(recogida.getByText('Casa')).toBeOnTheScreen();
      expect(recogida.getByText('Calle de prueba 123, Colonia de prueba')).toBeOnTheScreen();
      // El pedido se pide por su id_publico, con la sesión.
      expect(fetchFalso).toHaveBeenCalledWith(
        `${BASE}/pedidos/${ID_DEL_PEDIDO}`,
        expect.objectContaining({ method: 'GET', headers: expect.objectContaining({ Authorization: `Bearer ${TOKEN}` }) }),
      );
    },
  );

  test('confirmacion-E09 — el resumen es el que guardó el pedido en el servidor, no el pedido en curso del teléfono', async () => {
    empezarPedido({
      id_publico: '01jd00000000000000000000l9',
      nombre: 'Otra lavandería del teléfono',
      servicios: [
        { codigo: 'estandar', nombre: 'Lavado estándar', precio: '45.00', unidad: { codigo: 'kg', nombre: 'kilo' } },
      ],
    });
    elegirServicio('estandar');

    await abrirLaConfirmacion('android');

    const resumen = within(screen.getByTestId('resumen-del-pedido'));
    expect(resumen.getByText('Lavandería de prueba confirmación')).toBeOnTheScreen();
    expect(resumen.getByText('Planchado')).toBeOnTheScreen();
    expect(screen.queryByText('Otra lavandería del teléfono')).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'confirmacion-E09 — (%s) si el pedido todavía no está Confirmado, no muestra folio ni nombra el estado previo al pago',
    async (plataforma) => {
      servidor(() => respuesta(200, { pedido: POR_PAGAR }));

      await abrirLaConfirmacion(plataforma);

      expect(await screen.findByText('Todavía no está confirmado.')).toBeOnTheScreen();
      expect(screen.queryByRole('header', { name: '¡Pedido confirmado!' })).not.toBeOnTheScreen();
      expect(screen.queryByText('Tu folio es')).not.toBeOnTheScreen();
      expect(screen.queryByText('Por pagar')).not.toBeOnTheScreen();
      expect(screen.queryByTestId('resumen-del-pedido')).not.toBeOnTheScreen();
    },
  );

  test('confirmacion-E09 — con el estado Confirmado pero sin folio, no lo da por confirmado', async () => {
    servidor(() => respuesta(200, { pedido: { ...CONFIRMADO, folio: null } }));

    await abrirLaConfirmacion('ios');

    expect(await screen.findByText('Todavía no está confirmado.')).toBeOnTheScreen();
    expect(screen.queryByText('Tu folio es')).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)('confirmacion-E09 — (%s) con 404, dice que no encontró el pedido', async (plataforma) => {
    servidor(() => respuesta(404, { message: 'No existe el pedido.' }));

    await abrirLaConfirmacion(plataforma);

    expect(await screen.findByRole('alert')).toHaveTextContent('No encontramos este pedido.');
    expect(screen.queryByText('Tu folio es')).not.toBeOnTheScreen();
    expect(screen.queryByTestId('resumen-del-pedido')).not.toBeOnTheScreen();
  });

  test('confirmacion-E09 — sin conexión con el servidor, lo dice con el mensaje de la app', async () => {
    servidor(() => Promise.reject(new TypeError('Network request failed')));

    await abrirLaConfirmacion('android');

    expect(await screen.findByRole('alert')).toHaveTextContent(MENSAJES.sinConexion);
  });

  test('confirmacion-E09 — una respuesta fuera del contrato no se pinta a medias: mensaje general', async () => {
    servidor(() => respuesta(200, { pedido: { ...CONFIRMADO, ventana: null } }));

    await abrirLaConfirmacion('ios');

    expect(await screen.findByRole('alert')).toHaveTextContent(MENSAJES.servidor);
    expect(screen.queryByText(FOLIO)).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'confirmacion-E09 — (%s) al salir, la app deja de tener pedido en curso y vuelve al inicio',
    async (plataforma) => {
      empezarPedido({ id_publico: '01jd00000000000000000000l1', nombre: 'Lavandería de prueba confirmación', servicios: [] });
      await abrirLaConfirmacion(plataforma);
      expect(leerPedidoEnCurso()).not.toBeNull();

      await fireEvent.press(botonDeSalir());

      await waitFor(() => expect(screen).toHavePathname('/inicio'));
      expect(leerPedidoEnCurso()).toBeNull();
    },
  );

  test('confirmacion-E09 — también se sale al inicio cuando no encontró el pedido', async () => {
    servidor(() => respuesta(404, { message: 'No existe el pedido.' }));
    await abrirLaConfirmacion('ios');

    await fireEvent.press(botonDeSalir());

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test('registro-E11 — sin sesión, abrir la confirmación lleva al inicio de sesión', async () => {
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    await renderRouter(APP, { initialUrl: `/pedido/confirmacion/${ID_DEL_PEDIDO}` });

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(fetchFalso).not.toHaveBeenCalledWith(`${BASE}/pedidos/${ID_DEL_PEDIDO}`, expect.anything());
  });
});
