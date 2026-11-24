// Spec: openspec/changes/resumen-y-confirmacion/specs/confirmacion/spec.md, confirmacion-E01 y E07, con lo que la app
// hace en confirmacion-E08 y E15 (tarea 2.1). El resumen del pedido nuevo ("Pedido nuevo · 4 de 4") con el componente 6
// del design system (vault/design-system/componentes.md): la lavandería, el servicio, la fecha con su ventana en la hora
// de la plaza, la recogida y la entrega, y los renglones de precio estimado y política de cancelación reservados sin
// cifra (confirmacion-E02 y E04 esperan DD-012, DD-010 y DD-016). El envío no lleva renglón: el Módulo A #9 enumera
// servicio, fecha, dirección, precio estimado y política de cancelación, y si el envío es un cargo aparte lo dicen DD-019
// y DD-025 (confirmacion-E03).
//
// El botón dice que lleva al pago y manda POST /api/pedidos con lo del pedido en curso, como lo pide
// servidor/app/Http/Requests/CrearPedidoRequest.php. Con 201, el pedido nació antes del pago (C-188): la app guarda su
// id_publico y abre el pago, provisional mientras pago-E02 espera DD-055 y DD-079. Ninguna petición confirma: la
// confirmación la hace el cobro (confirmacion-E07). Si el servidor rechaza la ventana, la app regresa a fecha y hora; si
// rechaza la recogida o la entrega, al paso de la dirección, con el aviso, y con los demás datos intactos
// (confirmacion-E08). Si la dirección quedó con su plaza anterior, ofrece guardarla de nuevo (confirmacion-E15).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado con el
// contrato de servidor/app/Http/Controllers/Api/PedidosController.php.
import path from 'node:path';

import { act, fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import type { Ventana } from '../../src/agenda/api';
import * as fechas from '../../src/agenda/fechas';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import type { Direccion } from '../../src/direcciones/api';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import {
  elegirDirecciones,
  elegirServicio,
  elegirVentana,
  empezarPedido,
  leerPedidoEnCurso,
  vaciarPedidoEnCurso,
  type LavanderiaDelPedido,
} from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/confirmacion-resumen';
const TOKEN = 'token-confirmacion-resumen';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const LAVANDERIA: LavanderiaDelPedido = {
  id_publico: '01jd00000000000000000000l1',
  nombre: 'Lavandería de prueba resumen',
  servicios: [
    { codigo: 'estandar', nombre: 'Lavado estándar', precio: '45.00', unidad: { codigo: 'kg', nombre: 'kilo' } },
    { codigo: 'planchado', nombre: 'Planchado', precio: '30.00', unidad: { codigo: 'kg', nombre: 'kilo' } },
  ],
};

// La agenda de prueba va en una zona con otro desfase que el de México: la app muestra la hora de la plaza que da el
// servidor y no la convierte. Hoy en la plaza es el jueves 3 de diciembre de 2026.
const DESFASE = '+09:00';
const HOY = '2026-12-03';

function ventana(hora: number): Ventana {
  const dos = (numero: number) => String(numero).padStart(2, '0');
  return {
    inicio: `${HOY}T${dos(hora)}:00:00${DESFASE}`,
    fin: `${HOY}T${dos(hora + 1)}:00:00${DESFASE}`,
    fecha: HOY,
    hora_inicio: `${dos(hora)}:00`,
    hora_fin: `${dos(hora + 1)}:00`,
  };
}

const ONCE = ventana(11);
const DOCE = ventana(12);

const CASA: Direccion = {
  id_publico: '01jd00000000000000000000c1',
  alias: 'Casa',
  es_favorita: true,
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: null,
  colonia: 'Colonia de prueba',
  codigo_postal: null,
  municipio: 'Municipio de prueba',
  estado: 'Estado de prueba',
  referencias: 'Portón gris',
  latitud: 24.0277,
  longitud: -104.6532,
};
const OFICINA: Direccion = {
  id_publico: '01jd00000000000000000000o1',
  alias: 'Oficina',
  es_favorita: true,
  calle: 'Avenida de prueba',
  numero_exterior: '456',
  numero_interior: '2',
  colonia: 'Otra colonia de prueba',
  codigo_postal: null,
  municipio: 'Municipio de prueba',
  estado: 'Estado de prueba',
  referencias: null,
  latitud: 24.0311,
  longitud: -104.6612,
};
// La que se guarda de nuevo cuando la anterior quedó con la plaza de antes (confirmacion-E15).
const CASA_DE_NUEVO: Direccion = { ...CASA, id_publico: '01jd00000000000000000000c2', alias: 'Casa de nuevo' };
const TODAS = [CASA, OFICINA, CASA_DE_NUEVO];

const ID_DEL_PEDIDO = '01jd00000000000000000000p1';

// El pedido como lo devuelve POST /api/pedidos con 201: en el estado previo al pago, sin folio ni confirmado_en.
function pedidoCreado(cuerpo: Cuerpo) {
  const copia = (id: string) => {
    const { id_publico: _id, es_favorita: _favorita, ...senas } = TODAS.find((una) => una.id_publico === id) ?? CASA;
    return senas;
  };
  const laVentana = [ONCE, DOCE].find((una) => una.inicio === cuerpo.ventana.inicio) ?? ONCE;
  return {
    pedido: {
      id_publico: ID_DEL_PEDIDO,
      folio: null,
      confirmado_en: null,
      estado: { codigo: 'por_pagar', nombre: 'Por pagar', visible_usuario: false },
      lavanderia: { id_publico: LAVANDERIA.id_publico, nombre: LAVANDERIA.nombre },
      servicios: [
        {
          codigo: 'estandar',
          nombre: 'Lavado estándar',
          unidad: { codigo: 'kg', nombre: 'kilo' },
          precio_unitario: '45.00',
          cantidad_estimada: null,
          monto_estimado: null,
        },
      ],
      zona_horaria: 'Asia/Tokyo',
      ventana: laVentana,
      recogida: copia(cuerpo.recogida),
      entrega: copia(cuerpo.entrega ?? cuerpo.recogida),
      monto_estimado: null,
      envio: null,
    },
  };
}

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
interface Cuerpo {
  lavanderia: string;
  servicio: string;
  ventana: { inicio: string; fin: string };
  recogida: string;
  entrega?: string | null;
}

interface Servidor {
  /** Lo que contesta POST /api/pedidos, con el cuerpo de la petición. Por omisión, 201 con el pedido. */
  crear?: (cuerpo: Cuerpo) => Respuesta | Promise<Respuesta>;
  /** Las ventanas del día que da GET /api/lavanderias/{id_publico}/ventanas. Por omisión, las de las 11 y las 12. */
  ventanas?: () => Ventana[];
  /** La dirección que guarda POST /api/direcciones. */
  alta?: Direccion;
}

// El servidor falso: reconoce el token, crea el pedido y contesta los pasos anteriores (las ventanas, las favoritas, la
// recogida y la entrega, y la dirección nueva) para los regresos de confirmacion-E08 y E15.
function servidor({
  crear = (cuerpo) => respuesta(201, pedidoCreado(cuerpo)),
  ventanas = () => [ONCE, DOCE],
  alta = CASA_DE_NUEVO,
}: Servidor = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const [ruta] = url.slice(BASE.length).split('?');
    const metodo = opciones.method ?? 'GET';
    const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
    if (cabeceras.Authorization !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/pedidos' && metodo === 'POST') {
      return crear(JSON.parse(String(opciones.body)));
    }
    if (ruta === `/lavanderias/${LAVANDERIA.id_publico}/ventanas`) {
      return respuesta(200, {
        fecha: HOY,
        zona_horaria: 'Asia/Tokyo',
        primer_dia: HOY,
        ultimo_dia: '2026-12-07',
        dia_sin_servicio: false,
        ventanas: ventanas(),
      });
    }
    if (ruta === '/direcciones/favoritas') {
      return respuesta(200, { direcciones: [CASA, OFICINA] });
    }
    // La lista del territorio, que la dirección nueva pide al abrirse (direcciones-E17).
    if (ruta === '/catalogos/municipios') {
      return respuesta(200, {
        municipios: [{ codigo: '00001', nombre: 'Municipio de prueba', estado: { codigo: '00', nombre: 'Estado de prueba' } }],
      });
    }
    if (ruta === '/direcciones/recogida-y-entrega' && metodo === 'POST') {
      const { recogida, entrega } = JSON.parse(String(opciones.body));
      const buscar = (id: string) => TODAS.find((direccion) => direccion.id_publico === id);
      return respuesta(200, { recogida: buscar(recogida), entrega: buscar(entrega ?? recogida) });
    }
    if (ruta === '/direcciones' && metodo === 'POST') {
      return respuesta(201, { direccion: alta });
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Los cuerpos de las peticiones POST a una ruta, leídos.
function peticionesA(ruta: string): Record<string, unknown>[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}${ruta}` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => JSON.parse(String(opciones.body)));
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  jest.spyOn(fechas, 'fechaDelTelefono').mockReturnValue(HOY);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// El pedido en curso completo, como lo dejan los pasos anteriores: lavandería, servicio, ventana, recogida y entrega.
function pedidoEnCursoCompleto({ entrega = CASA }: { entrega?: Direccion } = {}) {
  empezarPedido(LAVANDERIA);
  elegirServicio('estandar');
  elegirVentana(ONCE);
  elegirDirecciones({ recogida: CASA, entrega });
}

// La persona con sesión y el pedido en curso completo llega al resumen.
async function abrirElResumen(plataforma: Plataforma, opciones: { entrega?: Direccion } = {}) {
  await conSesion(plataforma);
  pedidoEnCursoCompleto(opciones);
  await renderRouter(APP, { initialUrl: '/pedido/resumen' });
  await screen.findByRole('header', { name: 'Revisa tu pedido' });
}

function resumen() {
  return within(screen.getByTestId('resumen-del-pedido'));
}

function botonDelPago() {
  return within(screen.getByTestId('acciones')).getByRole('button', { name: 'Confirmar y pagar' });
}

async function aceptarElResumen() {
  await fireEvent.press(botonDelPago());
}

async function continuar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Continuar' }));
}

// Desde el paso de fecha y hora, con la ventana ya elegida, la persona sigue a la dirección y de ahí al resumen: así la
// pila lleva las pantallas anteriores montadas, como en el teléfono.
async function deFechaYHoraAlResumen(plataforma: Plataforma) {
  await conSesion(plataforma);
  pedidoEnCursoCompleto();
  await renderRouter(APP, { initialUrl: '/pedido/fecha-y-hora' });
  await screen.findByRole('header', { name: '¿Cuándo pasamos por tu ropa?' });
  expect(await screen.findByRole('radio', { name: '11:00 – 12:00' })).toBeChecked();
  await continuar();
  await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
  expect(await screen.findByRole('radio', { name: /^Casa,/ })).toBeChecked();
  await continuar();
  await screen.findByRole('header', { name: 'Revisa tu pedido' });
}

beforeEach(() => {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba confirmacion-resumen';
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

describe('confirmacion-E01: el resumen muestra servicio, fecha y direcciones', () => {
  test.each(PLATAFORMAS)(
    'confirmacion-E01 — (%s) el paso 4 de 4 muestra la lavandería, el servicio, la fecha con su ventana en la hora de la plaza, la recogida y la entrega, y el botón para seguir al pago',
    async (plataforma) => {
      await abrirElResumen(plataforma);

      expect(screen.getByText('Pedido nuevo · 4 de 4')).toBeOnTheScreen();
      expect(resumen().getByText('Lavandería de prueba resumen')).toBeOnTheScreen();
      expect(resumen().getByText('Lavado estándar')).toBeOnTheScreen();
      expect(resumen().queryByText('Planchado')).not.toBeOnTheScreen();
      // La hora de la plaza, tal como la dio el servidor, sin pasarla a la del teléfono.
      expect(resumen().getByText('Jueves 3 de diciembre · 11:00 – 12:00')).toBeOnTheScreen();
      const recogida = within(resumen().getByTestId('dato-recogida'));
      expect(recogida.getByText('Recogida')).toBeOnTheScreen();
      expect(recogida.getByText('Casa')).toBeOnTheScreen();
      expect(recogida.getByText('Calle de prueba 123, Colonia de prueba')).toBeOnTheScreen();
      const entrega = within(resumen().getByTestId('dato-entrega'));
      expect(entrega.getByText('Entrega')).toBeOnTheScreen();
      expect(entrega.getByText('La misma de la recogida')).toBeOnTheScreen();
      expect(botonDelPago()).toBeEnabled();
    },
  );

  test('confirmacion-E01 — con la entrega en otra dirección, muestra las dos', async () => {
    await abrirElResumen('android', { entrega: OFICINA });

    const recogida = within(resumen().getByTestId('dato-recogida'));
    expect(recogida.getByText('Casa')).toBeOnTheScreen();
    const entrega = within(resumen().getByTestId('dato-entrega'));
    expect(entrega.getByText('Oficina')).toBeOnTheScreen();
    expect(entrega.getByText('Avenida de prueba 456, int. 2, Otra colonia de prueba')).toBeOnTheScreen();
    expect(entrega.queryByText('La misma de la recogida')).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'confirmacion-E01 — (%s) los renglones de precio estimado y política de cancelación van reservados, sin cifra ni texto inventado (DD-012, DD-016)',
    async (plataforma) => {
      await abrirElResumen(plataforma);

      for (const [testID, etiqueta] of [
        ['renglon-precio-estimado', 'Precio estimado'],
        ['renglon-politica-de-cancelacion', 'Política de cancelación'],
      ] as const) {
        const renglon = within(resumen().getByTestId(testID));
        expect(renglon.getByText(etiqueta)).toBeOnTheScreen();
        expect(renglon.getByText('Por confirmar')).toBeOnTheScreen();
        expect(renglon.queryByText(/\d/)).not.toBeOnTheScreen();
      }
      // Ningún monto en todo el resumen: ni el precio por kilo del perfil ni un envío.
      expect(screen.queryByText(/\$/)).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'confirmacion-E03 — (%s) el resumen no lleva renglón de envío: el Módulo A #9 no lo enumera, y si es un cargo aparte lo dicen DD-019 y DD-025',
    async (plataforma) => {
      await abrirElResumen(plataforma);

      expect(resumen().queryByTestId('renglon-envio')).not.toBeOnTheScreen();
      expect(resumen().queryByText(/env[ií]o/i)).not.toBeOnTheScreen();
      // Los dos renglones reservados son los únicos que dicen "Por confirmar".
      expect(resumen().getAllByText('Por confirmar')).toHaveLength(2);
    },
  );

  test('confirmacion-E01 — sin pedido en curso, el resumen lleva a la pantalla principal', async () => {
    await conSesion('ios');

    await renderRouter(APP, { initialUrl: '/pedido/resumen' });

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test('confirmacion-E01 — sin ventana elegida, el resumen lleva a fecha y hora', async () => {
    await conSesion('android');
    empezarPedido(LAVANDERIA);
    elegirServicio('estandar');

    await renderRouter(APP, { initialUrl: '/pedido/resumen' });

    await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
  });

  test('confirmacion-E01 — sin la recogida aceptada, el resumen lleva al paso de la dirección', async () => {
    await conSesion('ios');
    empezarPedido(LAVANDERIA);
    elegirServicio('estandar');
    elegirVentana(ONCE);

    await renderRouter(APP, { initialUrl: '/pedido/resumen' });

    await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
  });
});

describe('confirmacion-E07: el botón del resumen lleva al pago y no confirma el pedido', () => {
  test.each(PLATAFORMAS)(
    'confirmacion-E07 — (%s) el botón dice que lleva al pago; al tocarlo manda POST /api/pedidos solo con lo del pedido en curso y, con 201, guarda el pedido y abre el pago',
    async (plataforma) => {
      await abrirElResumen(plataforma);

      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
      const peticiones = peticionesA('/pedidos');
      expect(peticiones).toEqual([
        {
          lavanderia: LAVANDERIA.id_publico,
          servicio: 'estandar',
          ventana: { inicio: ONCE.inicio, fin: ONCE.fin },
          recogida: CASA.id_publico,
          entrega: null,
        },
      ]);
      // Ni folio, ni estado, ni que ya pagó: la app no tiene cómo confirmar el pedido.
      expect(Object.keys(peticiones[0]).sort()).toEqual(['entrega', 'lavanderia', 'recogida', 'servicio', 'ventana']);
      expect(leerPedidoEnCurso()?.creado).toBe(ID_DEL_PEDIDO);
      expect(await screen.findByRole('header', { name: 'Pago' })).toBeOnTheScreen();
      expect(screen.queryByText(/confirmado/i)).not.toBeOnTheScreen();
    },
  );

  test('confirmacion-E07 — con la entrega en otra dirección, la manda por su id_publico', async () => {
    await abrirElResumen('ios', { entrega: OFICINA });

    await aceptarElResumen();

    await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
    expect(peticionesA('/pedidos')[0]).toMatchObject({ recogida: CASA.id_publico, entrega: OFICINA.id_publico });
  });

  test.each(PLATAFORMAS)(
    'confirmacion-E07 — (%s) nunca dos pedidos por doble toque: mientras el servidor contesta, el botón está ocupado y otro toque no manda nada',
    async (plataforma) => {
      let contestar: (valor: Respuesta) => void = () => undefined;
      servidor({ crear: () => new Promise<Respuesta>((resolver) => (contestar = resolver)) });
      await abrirElResumen(plataforma);

      await aceptarElResumen();
      expect(botonDelPago()).toBeDisabled();
      expect(botonDelPago()).toBeBusy();
      await aceptarElResumen();
      await aceptarElResumen();
      expect(peticionesA('/pedidos')).toHaveLength(1);
      await act(async () => contestar(respuesta(201, pedidoCreado(peticionesA('/pedidos')[0] as unknown as Cuerpo))));

      await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
      expect(peticionesA('/pedidos')).toHaveLength(1);
    },
  );

  test('confirmacion-E07 — al regresar del pago y tocar otra vez, no crea otro pedido: abre el pago del mismo', async () => {
    await abrirElResumen('android');
    await aceptarElResumen();
    await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
    await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));
    await aceptarElResumen();

    await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
    expect(peticionesA('/pedidos')).toHaveLength(1);
  });

  test('confirmacion-E07 — si cambia la ventana después de crear el pedido, el resumen ya no usa ese pedido', async () => {
    await abrirElResumen('ios');
    await aceptarElResumen();
    await waitFor(() => expect(leerPedidoEnCurso()?.creado).toBe(ID_DEL_PEDIDO));

    await act(async () => elegirVentana(DOCE));

    expect(leerPedidoEnCurso()?.creado).toBeUndefined();
  });

  test.each([
    [503, 'Todavía no se pueden crear pedidos.'],
    [503, 'La agenda de la plaza no está configurada.'],
    [404, 'No existe la dirección.'],
  ])('confirmacion-E07 — con %s, dice el mensaje del servidor, no crea nada y deja intentar otra vez', async (status, mensaje) => {
    servidor({ crear: () => respuesta(status, { message: mensaje }) });
    await abrirElResumen('android');

    await aceptarElResumen();

    expect(await within(screen.getByTestId('acciones')).findByRole('alert')).toHaveTextContent(mensaje);
    expect(screen).toHavePathname('/pedido/resumen');
    expect(leerPedidoEnCurso()?.creado).toBeUndefined();
    expect(botonDelPago()).toBeEnabled();
  });

  test('confirmacion-E07 — con el servicio que ya no ofrece la lavandería, dice el motivo del servidor', async () => {
    servidor({
      crear: () =>
        respuesta(422, {
          message: 'La lavandería no ofrece ese servicio.',
          errors: { servicio: ['La lavandería no ofrece ese servicio.'] },
        }),
    });
    await abrirElResumen('ios');

    await aceptarElResumen();

    expect(await within(screen.getByTestId('acciones')).findByRole('alert')).toHaveTextContent(
      'La lavandería no ofrece ese servicio.',
    );
    expect(screen).toHavePathname('/pedido/resumen');
  });

  test.each(PLATAFORMAS)(
    'confirmacion-E07 — (%s) sin conexión con el servidor, lo dice con el mensaje de la app y no avanza',
    async (plataforma) => {
      servidor({ crear: () => Promise.reject(new TypeError('Network request failed')) });
      await abrirElResumen(plataforma);

      await aceptarElResumen();

      expect(await within(screen.getByTestId('acciones')).findByRole('alert')).toHaveTextContent(MENSAJES.sinConexion);
      expect(screen).toHavePathname('/pedido/resumen');
    },
  );

  test('confirmacion-E07 — con un error del servidor sin mensaje para la persona, el mensaje general', async () => {
    servidor({ crear: () => respuesta(500, { message: 'Server Error' }) });
    await abrirElResumen('ios');

    await aceptarElResumen();

    expect(await within(screen.getByTestId('acciones')).findByRole('alert')).toHaveTextContent(MENSAJES.servidor);
  });
});

describe('confirmacion-E08: la ventana dejó de estar disponible', () => {
  const YA_EMPEZO = 'La ventana ya empezó.';

  function rechazarLaVentanaDeLasOnce(cuerpo: Cuerpo): Respuesta {
    return cuerpo.ventana.inicio === ONCE.inicio
      ? respuesta(422, { message: YA_EMPEZO, errors: { ventana: [YA_EMPEZO] } })
      : respuesta(201, pedidoCreado(cuerpo));
  }

  test.each(PLATAFORMAS)(
    'confirmacion-E08 — (%s) con 422 de la ventana, regresa a fecha y hora con el motivo, sin ventana elegida y con los demás datos intactos; con otra ventana, el pedido se crea',
    async (plataforma) => {
      let ventanasDelDia = [ONCE, DOCE];
      servidor({ crear: rechazarLaVentanaDeLasOnce, ventanas: () => ventanasDelDia });
      await deFechaYHoraAlResumen(plataforma);
      // Mientras tanto la ventana de las 11 empezó: la agenda ya no la ofrece.
      ventanasDelDia = [DOCE];

      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
      expect(await screen.findByText(YA_EMPEZO)).toBeOnTheScreen();
      expect(await screen.findByRole('radio', { name: '12:00 – 13:00' })).not.toBeChecked();
      expect(screen.queryByRole('radio', { name: '11:00 – 12:00' })).not.toBeOnTheScreen();
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Elige una ventana' })).toBeDisabled();
      expect(peticionesA('/pedidos')).toHaveLength(1);
      const pedido = leerPedidoEnCurso();
      expect(pedido?.lavanderia.id_publico).toBe(LAVANDERIA.id_publico);
      expect(pedido?.servicio).toBe('estandar');
      expect(pedido?.recogida).toEqual(CASA);
      expect(pedido?.entrega).toEqual(CASA);
      expect(pedido?.creado).toBeUndefined();

      await fireEvent.press(screen.getByRole('radio', { name: '12:00 – 13:00' }));
      expect(screen.queryByText(YA_EMPEZO)).not.toBeOnTheScreen();
      await continuar();
      await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
      expect(await screen.findByRole('radio', { name: /^Casa,/ })).toBeChecked();
      await continuar();
      await screen.findByRole('header', { name: 'Revisa tu pedido' });
      expect(resumen().getByText('Jueves 3 de diciembre · 12:00 – 13:00')).toBeOnTheScreen();
      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
      expect(peticionesA('/pedidos').at(-1)).toEqual({
        lavanderia: LAVANDERIA.id_publico,
        servicio: 'estandar',
        ventana: { inicio: DOCE.inicio, fin: DOCE.fin },
        recogida: CASA.id_publico,
        entrega: null,
      });
    },
  );

  test('confirmacion-E08 — con el resumen abierto directo, el 422 de la ventana también lleva a fecha y hora', async () => {
    servidor({ crear: rechazarLaVentanaDeLasOnce, ventanas: () => [DOCE] });
    await abrirElResumen('android');

    await aceptarElResumen();

    await waitFor(() => expect(screen).toHavePathname('/pedido/fecha-y-hora'));
    expect(await screen.findByText(YA_EMPEZO)).toBeOnTheScreen();
    expect(await screen.findByRole('radio', { name: '12:00 – 13:00' })).not.toBeChecked();
  });
});

describe('confirmacion-E08: la recogida o la entrega dejaron de servir', () => {
  const FUERA_DE_ZONA = 'La dirección está fuera de la zona de servicio.';

  test.each(PLATAFORMAS)(
    'confirmacion-E08 — (%s) con 422 de la recogida con su motivo, regresa al paso de la dirección con el aviso bajo la recogida y los demás datos intactos; con otra, el pedido se crea',
    async (plataforma) => {
      servidor({
        crear: (cuerpo) =>
          cuerpo.recogida === CASA.id_publico
            ? respuesta(422, {
                message: FUERA_DE_ZONA,
                errors: { recogida: [FUERA_DE_ZONA] },
                motivos: { recogida: 'fuera_de_la_zona_de_servicio' },
              })
            : respuesta(201, pedidoCreado(cuerpo)),
      });
      await deFechaYHoraAlResumen(plataforma);

      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
      const recogida = within(screen.getByTestId('direcciones-de-recogida'));
      expect(await recogida.findByRole('alert')).toHaveTextContent(
        'Esta dirección está fuera de la zona de servicio. Elige otra.',
      );
      expect(recogida.getByRole('radio', { name: /^Casa,/ })).toBeChecked();
      const pedido = leerPedidoEnCurso();
      expect(pedido?.servicio).toBe('estandar');
      expect(pedido?.ventana).toEqual(ONCE);

      await fireEvent.press(recogida.getByRole('radio', { name: /^Oficina,/ }));
      expect(recogida.queryByRole('alert')).not.toBeOnTheScreen();
      await continuar();
      await screen.findByRole('header', { name: 'Revisa tu pedido' });
      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
      expect(peticionesA('/pedidos').at(-1)).toMatchObject({
        ventana: { inicio: ONCE.inicio, fin: ONCE.fin },
        recogida: OFICINA.id_publico,
      });
    },
  );

  test('confirmacion-E08 — con 422 de la entrega, el aviso va en la entrega y la recogida no lo lleva', async () => {
    servidor({
      crear: () =>
        respuesta(422, {
          message: 'Esta lavandería no da servicio en esa dirección.',
          errors: { entrega: ['Esta lavandería no da servicio en esa dirección.'] },
          motivos: { entrega: 'fuera_de_la_plaza_de_la_lavanderia' },
        }),
    });
    await abrirElResumen('ios', { entrega: OFICINA });

    await aceptarElResumen();

    await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
    const entrega = within(await screen.findByTestId('direcciones-de-entrega'));
    expect(await entrega.findByRole('alert')).toHaveTextContent('Esta lavandería no da servicio en esa dirección. Elige otra.');
    expect(entrega.getByRole('radio', { name: /^Oficina,/ })).toBeChecked();
    expect(within(screen.getByTestId('direcciones-de-recogida')).queryByRole('alert')).not.toBeOnTheScreen();
  });
});

describe('confirmacion-E15: la dirección ya se usó con su plaza anterior', () => {
  const DESACTUALIZADA = 'Esta dirección cambió de zona de servicio. Guárdala de nuevo para usarla.';

  test.each(PLATAFORMAS)(
    'confirmacion-E15 — (%s) con plaza_de_la_direccion_desactualizada, regresa a la dirección con el mensaje y ofrece guardarla de nuevo; la guardada de nuevo es la que lleva el pedido',
    async (plataforma) => {
      servidor({
        crear: (cuerpo) =>
          cuerpo.recogida === CASA.id_publico
            ? respuesta(422, {
                message: DESACTUALIZADA,
                errors: { recogida: [DESACTUALIZADA] },
                motivos: { recogida: 'plaza_de_la_direccion_desactualizada' },
              })
            : respuesta(201, pedidoCreado(cuerpo)),
      });
      await deFechaYHoraAlResumen(plataforma);

      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
      const recogida = within(screen.getByTestId('direcciones-de-recogida'));
      expect(await recogida.findByRole('alert')).toHaveTextContent(DESACTUALIZADA);
      // La oferta de guardarla de nuevo: la dirección nueva del paso de la dirección, con POST /api/direcciones.
      await fireEvent.press(recogida.getByRole('link', { name: 'Agregar otra dirección' }));
      await screen.findByText('Nueva dirección');
      await fireEvent.changeText(screen.getByLabelText('Calle'), 'Calle de prueba');
      await fireEvent.changeText(screen.getByLabelText('Número'), '123');
      await fireEvent.changeText(screen.getByLabelText('Colonia'), 'Colonia de prueba');
      // El municipio, de la lista del territorio (direcciones-E17).
      await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
      await fireEvent.press(screen.getByRole('button', { name: 'Municipio' }));
      await fireEvent.press(
        within(screen.getByTestId('hoja-municipio')).getByRole('radio', {
          name: 'Municipio de prueba, Estado de prueba',
        }),
      );
      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar dirección' }));
      await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
      expect(peticionesA('/direcciones')).toHaveLength(1);

      const otraVez = within(screen.getByTestId('direcciones-de-recogida'));
      expect(await otraVez.findByRole('radio', { name: /^Casa de nuevo,/ })).toBeChecked();
      expect(otraVez.queryByRole('alert')).not.toBeOnTheScreen();
      await continuar();
      await screen.findByRole('header', { name: 'Revisa tu pedido' });
      await aceptarElResumen();

      await waitFor(() => expect(screen).toHavePathname(`/pedido/pago/${ID_DEL_PEDIDO}`));
      expect(peticionesA('/pedidos').at(-1)).toMatchObject({ recogida: CASA_DE_NUEVO.id_publico });
    },
  );
});
