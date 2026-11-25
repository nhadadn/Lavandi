// Spec: openspec/changes/estados-del-pedido/specs/estados/spec.md, estados-E02 y estados-E24 (tarea 4.1). El
// seguimiento del pedido en la app del usuario, con el componente 3 del design system (vault/design-system/
// componentes.md): los cinco estados del contrato (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #12), el
// actual con su nombre escrito, los anteriores cumplidos y los siguientes pendientes, y la hora en que el pedido llegó a
// cada estado cumplido y al actual (decisión 25 del acta del 2026-11-20, DD-135 punto 4). Se abre la app real, con las
// rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado con el contrato de
// GET /api/pedidos/{id_publico} (openspec/changes/estados-del-pedido/design.md, "Contrato de la API", y
// servidor/app/Http/Resources/PedidoResource.php).
//
// No entran: lo que ve el usuario con "En proceso de entrega" (estados-E12), el pedido rechazado o cancelado
// (estados-E19 y E20), todos bloqueados, ni la consulta cada 30 segundos (tarea 4.2, consulta-periodica.test.tsx).
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

/** Un elemento de la pantalla, como lo devuelven las consultas de la biblioteca de pruebas. */
type Elemento = ReturnType<typeof screen.getByTestId>;

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/estados-seguimiento';
const TOKEN = 'token-estados-seguimiento';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const ID = '01jd8x6m3v2c9q4w5e7r8t0y1z';
const RUTA_DEL_PEDIDO = `/pedidos/${ID}`;
const FOLIO = '7KQ2M9XA';
const LAVANDERIA = 'Lavandería de prueba estados';
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Los cinco del contrato, como los da el servidor (estados-E01), con la hora de prueba en que el pedido llega a cada uno.
const CINCO = [
  { codigo: 'confirmado', nombre: 'Confirmado', hora: '09:30' },
  { codigo: 'recolectado', nombre: 'Recolectado', hora: '10:05' },
  { codigo: 'en_proceso', nombre: 'En proceso', hora: '12:40' },
  { codigo: 'listo', nombre: 'Listo', hora: '17:15' },
  { codigo: 'entregado', nombre: 'Entregado', hora: '18:50' },
] as const;

// Un desfase de prueba distinto del de México: la app muestra la hora de la plaza como viene en el texto, sin pasarla a
// la hora del teléfono (estados-E24).
const DESFASE = '+09:00';

/**
 * El pedido como lo devuelve el servidor en ese estado. Los estados hasta el actual traen la hora en que el pedido llegó
 * a ellos; los demás, null (estados-E24). Fuera de los cinco (por_pagar), sin folio y ninguno con hora.
 */
function pedidoEn(codigo: string, { nombre, visible = true }: { nombre?: string; visible?: boolean } = {}) {
  const actual = CINCO.findIndex((paso) => paso.codigo === codigo);
  return {
    id_publico: ID,
    folio: actual === -1 ? null : FOLIO,
    confirmado_en: actual === -1 ? null : '2026-12-03T00:30:00Z',
    estado: { codigo, nombre: nombre ?? CINCO[actual].nombre, visible_usuario: visible },
    seguimiento: CINCO.map((paso, indice) => ({
      codigo: paso.codigo,
      nombre: paso.nombre,
      ocurrido_en: actual !== -1 && indice <= actual ? `2026-12-03T${paso.hora}:00${DESFASE}` : null,
    })),
    lavanderia: { id_publico: '01jd8x6m3v2c9q4w5e7r8t0y1u', nombre: LAVANDERIA },
    zona_horaria: 'Asia/Tokyo',
    monto_estimado: null,
    envio: null,
  };
}

// El pedido que todavía espera su pago: en por_pagar, que el usuario no ve (ADR-0021; C-188).
const POR_PAGAR = pedidoEn('por_pagar', { nombre: 'Por pagar', visible: false });

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso: reconoce el token y contesta el pedido de prueba, solo con la sesión.
function servidor(pedido: () => Promise<unknown>) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === RUTA_DEL_PEDIDO) {
      const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
      if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
        return respuesta(401, { message: 'Unauthenticated.' });
      }
      return pedido();
    }
    throw new Error(`El servidor falso no esperaba ${ruta}`);
  });
}

function conElPedido(cuerpo: unknown) {
  servidor(async () => respuesta(200, { pedido: cuerpo }));
}

/** Cuántas veces la app pidió el pedido al servidor. */
function consultasDelPedido(): number {
  return fetchFalso.mock.calls.filter(([url]: [string]) => url === `${BASE}${RUTA_DEL_PEDIDO}`).length;
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión abre el seguimiento del pedido y espera a que lleguen sus cinco estados.
async function abrirElSeguimiento(plataforma: Plataforma) {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: `/pedido/seguimiento/${ID}` });
  return screen.findByTestId('seguimiento');
}

/** El paso de un estado en el seguimiento. */
function paso(codigo: string): Elemento {
  return screen.getByTestId(`paso-${codigo}`);
}

/** Los textos que se leen en un elemento, cada uno como lo pinta su Text. */
function textosDe(elemento: Elemento): string[] {
  return within(elemento)
    .queryAllByText(/.+/)
    .map((texto) => [texto.props.children].flat().join(''));
}

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión lleva el nombre de la app en su logotipo.
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba estados-seguimiento';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  conElPedido(pedidoEn('confirmado'));
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

// Cada estado de los cinco, en cada plataforma.
const CASOS = PLATAFORMAS.flatMap((plataforma) =>
  CINCO.map((estado, actual) => [plataforma, estado.nombre, actual] as const),
);

describe('estados-E02: la pantalla de seguimiento muestra los cinco estados', () => {
  test.each(CASOS)(
    'estados-E02 — (%s) con el pedido en %s, ve el estado actual con su nombre escrito, los anteriores como cumplidos y los siguientes como pendientes',
    async (plataforma, _nombre, actual) => {
      conElPedido(pedidoEn(CINCO[actual].codigo));

      await abrirElSeguimiento(plataforma);

      expect(within(screen.getByTestId('estado-actual')).getByText(CINCO[actual].nombre)).toBeOnTheScreen();
      CINCO.forEach(({ codigo, nombre, hora }, indice) => {
        if (indice < actual) {
          expect(paso(codigo)).toHaveAccessibleName(`${nombre}, cumplido, ${hora}`);
        } else if (indice === actual) {
          expect(paso(codigo)).toHaveAccessibleName(`${nombre}, estado actual, ${hora}`);
        } else {
          expect(paso(codigo)).toHaveAccessibleName(`${nombre}, pendiente`);
        }
      });
    },
  );

  test.each(PLATAFORMAS)('estados-E02 — (%s) los cinco van en el orden del contrato, con los nombres que da el servidor', async (plataforma) => {
    conElPedido(pedidoEn('recolectado'));

    const seguimiento = await abrirElSeguimiento(plataforma);

    const pasos = within(seguimiento).getAllByTestId(/^paso-/);
    expect(pasos.map((elemento) => elemento.props.testID)).toEqual(CINCO.map(({ codigo }) => `paso-${codigo}`));
    CINCO.forEach(({ codigo, nombre }) => expect(within(paso(codigo)).getByText(nombre)).toBeOnTheScreen());
  });

  test.each(PLATAFORMAS)(
    'estados-E02 — (%s) no ve ningún nombre de estado fuera de los cinco: cada texto del seguimiento es uno de los cinco, su hora, "Pendiente", el folio o la lavandería',
    async (plataforma) => {
      conElPedido(pedidoEn('en_proceso'));

      const seguimiento = await abrirElSeguimiento(plataforma);

      const permitidos = new Set<string>([
        ...CINCO.map(({ nombre }) => nombre),
        ...CINCO.map(({ hora }) => hora),
        'Pendiente',
        `Pedido ${FOLIO}`,
        LAVANDERIA,
      ]);
      const textos = textosDe(seguimiento);
      expect(textos.length).toBeGreaterThan(0);
      expect(textos.filter((texto) => !permitidos.has(texto))).toEqual([]);
    },
  );

  test.each(PLATAFORMAS)(
    'estados-E02 — (%s) con el pedido que todavía espera su pago (visible_usuario en false), ningún paso es el actual: los cinco pendientes, sin nombrar su estado',
    async (plataforma) => {
      conElPedido(POR_PAGAR);

      const seguimiento = await abrirElSeguimiento(plataforma);

      CINCO.forEach(({ codigo, nombre }) => expect(paso(codigo)).toHaveAccessibleName(`${nombre}, pendiente`));
      expect(screen.queryByTestId('estado-actual')).not.toBeOnTheScreen();
      expect(screen.queryByText('Por pagar')).not.toBeOnTheScreen();
      // Sin folio todavía: no hay "Pedido …".
      expect(textosDe(seguimiento).filter((texto) => texto.startsWith('Pedido'))).toEqual([]);
    },
  );

  test('estados-E02 — el seguimiento lleva el folio y la lavandería del pedido', async () => {
    conElPedido(pedidoEn('listo'));

    const seguimiento = await abrirElSeguimiento('android');

    expect(within(seguimiento).getByText(`Pedido ${FOLIO}`)).toBeOnTheScreen();
    expect(within(seguimiento).getByText(LAVANDERIA)).toBeOnTheScreen();
  });

  test('estados-E02 — la consulta lleva la sesión y pide el pedido por su id_publico', async () => {
    await abrirElSeguimiento('ios');

    expect(fetchFalso).toHaveBeenCalledWith(
      `${BASE}${RUTA_DEL_PEDIDO}`,
      expect.objectContaining({
        method: 'GET',
        headers: expect.objectContaining({ Authorization: `Bearer ${TOKEN}` }),
      }),
    );
    expect(consultasDelPedido()).toBe(1);
  });
});

describe('estados-E24: cada estado cumplido lleva su hora', () => {
  test.each(PLATAFORMAS)(
    'estados-E24 — (%s) los cumplidos y el actual llevan la hora en que el pedido llegó a ellos, en la hora de la plaza tal como la da el servidor; los pendientes no llevan hora',
    async (plataforma) => {
      conElPedido(pedidoEn('en_proceso'));

      await abrirElSeguimiento(plataforma);

      expect(within(paso('confirmado')).getByText('09:30')).toBeOnTheScreen();
      expect(within(paso('recolectado')).getByText('10:05')).toBeOnTheScreen();
      expect(within(paso('en_proceso')).getByText('12:40')).toBeOnTheScreen();
      for (const pendiente of ['listo', 'entregado']) {
        expect(within(paso(pendiente)).getByText('Pendiente')).toBeOnTheScreen();
        expect(within(paso(pendiente)).queryByText(/\d{2}:\d{2}/)).not.toBeOnTheScreen();
      }
    },
  );

  test('estados-E24 — con el pedido que todavía espera su pago, ningún estado lleva hora', async () => {
    conElPedido(POR_PAGAR);

    const seguimiento = await abrirElSeguimiento('ios');

    expect(within(seguimiento).queryByText(/\d{2}:\d{2}/)).not.toBeOnTheScreen();
    expect(within(seguimiento).getAllByText('Pendiente')).toHaveLength(5);
  });
});

describe('estados-E02: el encabezado y lo que no llega', () => {
  test('el seguimiento lleva el encabezado "Tu pedido" con "Regresar"; abierto sin pantalla anterior, regresa al inicio', async () => {
    await abrirElSeguimiento('android');

    expect(screen.getByRole('header', { name: 'Tu pedido' })).toBeOnTheScreen();
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  // Sin escenario propio: si el pedido no llega, la pantalla usa los mensajes generales de la app, como la agenda y el
  // perfil de la lavandería (src/cercanas/textos.ts). Un pedido de otra cuenta responde 404 (estados-E10).
  test.each([
    ['sin conexión', MENSAJES.sinConexion, () => Promise.reject(new TypeError('Network request failed'))],
    ['el pedido no es de la cuenta (404)', MENSAJES.servidor, async () => respuesta(404, { message: 'No existe el pedido.' })],
    [
      'una respuesta fuera del contrato',
      MENSAJES.servidor,
      async () => respuesta(200, { pedido: { id_publico: ID, estado: { codigo: 'confirmado' } } }),
    ],
  ])('si el pedido no llega (%s), lo dice y no pinta el seguimiento', async (_caso, mensaje, contestar) => {
    servidor(contestar);
    await conSesion('ios');

    await renderRouter(APP, { initialUrl: `/pedido/seguimiento/${ID}` });

    expect(await screen.findByText(mensaje)).toBeOnTheScreen();
    expect(screen.queryByTestId('seguimiento')).not.toBeOnTheScreen();
    expect(screen.getByRole('header', { name: 'Tu pedido' })).toBeOnTheScreen();
  });

  test('registro-E11 — sin sesión, abrir el seguimiento lleva al inicio de sesión sin consultar el pedido', async () => {
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    await renderRouter(APP, { initialUrl: `/pedido/seguimiento/${ID}` });

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(consultasDelPedido()).toBe(0);
  });
});
