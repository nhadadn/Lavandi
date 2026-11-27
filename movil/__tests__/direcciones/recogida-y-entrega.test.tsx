// Spec: openspec/changes/direcciones/specs/direcciones/spec.md, direcciones-E06, E08, E09, E10 y E11 (tarea 2.3). El
// paso de la dirección del pedido ("Pedido nuevo · 3 de 4"): las favoritas de la cuenta como opciones, "Agregar otra
// dirección" con su opción de guardarla como favorita con un nombre, la recogida y la entrega, que puede ser la misma o
// otra, y el aviso cuando el servidor rechaza una por estar fuera de la zona de servicio. La recogida y la entrega que
// acepta el servidor quedan en el pedido en curso y la app pasa al resumen (resumen-y-confirmacion): la prueba comprueba
// que llega a él con las dos direcciones. El resumen pide el pedido completo, así que el de la prueba lleva la ventana
// que deja el paso de fecha y hora.
//
// Elegir una favorita dentro de los 30 días no geocodifica: el servidor usa el punto guardado (direcciones-E07, tarea
// 1.3; adenda del 2026-11-24 de ADR-0009). Que la dirección quede copiada en el pedido se prueba en
// resumen-y-confirmacion (design.md, "Lo que no escribe"). Se abre la app real, con las rutas de movil/app/ (ADR-0017),
// en iPhone y en Android, con el servidor simulado.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import {
  elegirServicio,
  elegirVentana,
  empezarPedido,
  leerPedidoEnCurso,
  vaciarPedidoEnCurso,
} from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/direcciones-recogida';
const TOKEN = 'token-direcciones-recogida';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const LAVANDERIA = {
  id_publico: '01jd00000000000000000000l1',
  nombre: 'Lavandería de prueba direcciones',
  servicios: [
    { codigo: 'estandar' as const, nombre: 'Lavado estándar', precio: '45.00', unidad: { codigo: 'kg', nombre: 'kilo' } },
  ],
};

// La ventana que dejó el paso de fecha y hora (agenda-E08).
const VENTANA = {
  inicio: '2026-12-03T11:00:00-06:00',
  fin: '2026-12-03T12:00:00-06:00',
  fecha: '2026-12-03',
  hora_inicio: '11:00',
  hora_fin: '12:00',
};

// Dos favoritas de la cuenta, como las devuelve el servidor, con su punto guardado.
const CASA = {
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
const OFICINA = {
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
// Una que se agrega en el pedido: la favorita "Casa nueva" y otra que no es favorita.
const NUEVA_FAVORITA = {
  ...CASA,
  id_publico: '01jd00000000000000000000n1',
  alias: 'Casa nueva',
  calle: 'Calle nueva de prueba',
};
const SIN_FAVORITA = {
  ...CASA,
  id_publico: '01jd00000000000000000000n2',
  alias: null,
  es_favorita: false,
  calle: 'Calle de una vez',
  numero_exterior: '789',
  referencias: null,
};

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

type Respuesta = ReturnType<typeof respuesta>;
interface Servidor {
  favoritas?: () => Respuesta;
  alta?: () => Respuesta;
  /** Con el cuerpo de la petición: la recogida y la entrega elegidas. */
  validar?: (cuerpo: { lavanderia: string; recogida: string; entrega: string | null }) => Respuesta;
}

const TODAS = [CASA, OFICINA, NUEVA_FAVORITA, SIN_FAVORITA];

// Por omisión, el servidor acepta la recogida y la entrega y las devuelve, la misma si no hay entrega.
function aceptarLasDos({ recogida, entrega }: { recogida: string; entrega: string | null }): Respuesta {
  const buscar = (id: string) => TODAS.find((direccion) => direccion.id_publico === id);
  return respuesta(200, { recogida: buscar(recogida), entrega: buscar(entrega ?? recogida) });
}

// El servidor falso: reconoce el token, da las favoritas, guarda la dirección nueva y valida la recogida y la entrega.
function servidor({
  favoritas = () => respuesta(200, { direcciones: [CASA, OFICINA] }),
  alta = () => respuesta(201, { direccion: NUEVA_FAVORITA }),
  validar = aceptarLasDos,
}: Servidor = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    // La lista del territorio, que la dirección nueva pide al abrirse (direcciones-E17).
    if (ruta === '/catalogos/municipios' && metodo === 'GET') {
      return respuesta(200, {
        municipios: [{ codigo: '00001', nombre: 'Municipio de prueba', estado: { codigo: '00', nombre: 'Estado de prueba' } }],
      });
    }
    if (ruta === '/direcciones/favoritas' && metodo === 'GET') {
      return favoritas();
    }
    if (ruta === '/direcciones' && metodo === 'POST') {
      return alta();
    }
    if (ruta === '/direcciones/recogida-y-entrega' && metodo === 'POST') {
      return validar(JSON.parse(String(opciones.body)));
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Las peticiones a una ruta, con su cuerpo leído.
function peticionesA(ruta: string): Record<string, unknown>[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}${ruta}` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => JSON.parse(String(opciones.body)));
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión y un pedido en curso, con lavandería, servicio y ventana, llega al paso de la dirección.
async function abrirLaDireccion(plataforma: Plataforma) {
  await conSesion(plataforma);
  empezarPedido(LAVANDERIA);
  elegirServicio('estandar');
  elegirVentana(VENTANA);
  const pantalla = await renderRouter(APP, { initialUrl: '/pedido/direccion' });
  await screen.findByRole('header', { name: '¿Dónde recogemos?' });
  // Hasta que llegan las favoritas.
  await waitFor(() => expect(screen.queryByText('Cargando tus direcciones…')).not.toBeOnTheScreen());
  return pantalla;
}

function recogida() {
  return within(screen.getByTestId('direcciones-de-recogida'));
}

function entrega() {
  return within(screen.getByTestId('direcciones-de-entrega'));
}

async function continuar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Continuar' }));
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

// En la dirección nueva: la escribe completa y, si se pide, la guarda como favorita con su nombre.
async function agregarDireccion(seccion: () => ReturnType<typeof within>, { favoritaConNombre = '' } = {}) {
  await fireEvent.press(seccion().getByRole('link', { name: /Agregar (otra|una) dirección/ }));
  await screen.findByText('Nueva dirección');
  await escribir('Calle', 'Calle nueva de prueba');
  await escribir('Número', '123');
  await escribir('Colonia', 'Colonia de prueba');
  // El municipio, de la lista del territorio (direcciones-E17).
  await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
  await fireEvent.press(screen.getByRole('button', { name: 'Municipio' }));
  await fireEvent.press(
    within(screen.getByTestId('hoja-municipio')).getByRole('radio', { name: 'Municipio de prueba, Estado de prueba' }),
  );
  if (favoritaConNombre) {
    await fireEvent(screen.getByRole('switch', { name: 'Guardar como favorita' }), 'valueChange', true);
    await escribir('Nombre', favoritaConNombre);
  }
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar dirección' }));
  await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
}

beforeEach(() => {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba direcciones-recogida';
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

describe('direcciones-E06: guardar una dirección como favorita', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E06 — (%s) el paso de la dirección muestra las favoritas de la cuenta como opciones, sin ninguna elegida',
    async (plataforma) => {
      await abrirLaDireccion(plataforma);

      expect(screen.getByText('Pedido nuevo · 3 de 4')).toBeOnTheScreen();
      const favoritas = within(recogida().getByTestId('favoritas-de-recogida'));
      expect(favoritas.getByText('Tus direcciones favoritas')).toBeOnTheScreen();
      const casa = favoritas.getByRole('radio', { name: /^Casa, Calle de prueba 123, Colonia de prueba/ });
      const oficina = favoritas.getByRole('radio', { name: /^Oficina, Avenida de prueba 456, int\. 2/ });
      expect(casa).not.toBeChecked();
      expect(oficina).not.toBeChecked();
      expect(favoritas.getAllByRole('radio')).toHaveLength(2);
      expect(recogida().getByRole('link', { name: 'Agregar otra dirección' })).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E06 — (%s) al agregar una dirección como favorita con el nombre "Casa nueva", se manda con su nombre y aparece entre las favoritas, elegida para la recogida',
    async (plataforma) => {
      await abrirLaDireccion(plataforma);

      await agregarDireccion(recogida, { favoritaConNombre: 'Casa nueva' });

      expect(peticionesA('/direcciones')).toEqual([
        {
          origen: 'escrita',
          calle: 'Calle nueva de prueba',
          numero_exterior: '123',
          colonia: 'Colonia de prueba',
          municipio: '00001',
          es_favorita: true,
          alias: 'Casa nueva',
        },
      ]);
      const favoritas = within(recogida().getByTestId('favoritas-de-recogida'));
      expect(await favoritas.findByRole('radio', { name: /^Casa nueva,/ })).toBeChecked();
      expect(favoritas.getByRole('radio', { name: /^Casa,/ })).not.toBeChecked();
    },
  );

  test('direcciones-E06 — el nombre solo se pide si se guarda como favorita, y su error va junto a él', async () => {
    servidor({
      alta: () =>
        respuesta(422, {
          message: 'Falta el nombre de la favorita.',
          errors: { alias: ['Falta el nombre de la favorita.'] },
        }),
    });
    await abrirLaDireccion('ios');
    await fireEvent.press(recogida().getByRole('link', { name: 'Agregar otra dirección' }));
    await screen.findByText('Nueva dirección');
    expect(screen.getByRole('switch', { name: 'Guardar como favorita' })).not.toBeChecked();
    expect(screen.queryByLabelText('Nombre')).not.toBeOnTheScreen();

    await fireEvent(screen.getByRole('switch', { name: 'Guardar como favorita' }), 'valueChange', true);
    await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar dirección' }));

    expect(await within(screen.getByTestId('campo-alias')).findByRole('alert')).toHaveTextContent(
      'Falta el nombre de la favorita.',
    );
    expect(screen).toHavePathname('/pedido/direccion-nueva');
  });

  test('direcciones-E06 — sin favoritas todavía, lo dice y deja agregar una dirección', async () => {
    servidor({ favoritas: () => respuesta(200, { direcciones: [] }) });
    await abrirLaDireccion('android');

    expect(recogida().getByText('Todavía no tienes direcciones favoritas.')).toBeOnTheScreen();
    expect(recogida().queryAllByRole('radio')).toHaveLength(0);
    expect(recogida().getByRole('link', { name: 'Agregar una dirección' })).toBeOnTheScreen();
  });

  test('direcciones-E06 — si las favoritas no llegan, lo dice con el mensaje general y deja agregar una dirección', async () => {
    servidor({ favoritas: () => respuesta(500, { message: 'Server Error' }) });
    await conSesion('ios');
    empezarPedido(LAVANDERIA);
    await renderRouter(APP, { initialUrl: '/pedido/direccion' });

    expect(await screen.findByText('Algo salió mal. Intenta de nuevo.')).toBeOnTheScreen();
    expect(recogida().getByRole('link', { name: /Agregar (otra|una) dirección/ })).toBeOnTheScreen();
  });
});

describe('direcciones-E08: una dirección que no es favorita no entra a la lista', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E08 — (%s) la dirección agregada sin marcarla como favorita queda elegida para este pedido, fuera de las favoritas',
    async (plataforma) => {
      servidor({ alta: () => respuesta(201, { direccion: SIN_FAVORITA }) });
      await abrirLaDireccion(plataforma);

      await agregarDireccion(recogida);

      expect(peticionesA('/direcciones')[0]).toMatchObject({ es_favorita: false });
      expect(peticionesA('/direcciones')[0]).not.toHaveProperty('alias');
      const soloEste = within(recogida().getByTestId('solo-este-pedido-de-recogida'));
      expect(soloEste.getByText('Solo para este pedido')).toBeOnTheScreen();
      expect(await soloEste.findByRole('radio', { name: /^Calle de una vez 789/ })).toBeChecked();
      const favoritas = within(recogida().getByTestId('favoritas-de-recogida'));
      expect(favoritas.queryByText(/Calle de una vez/)).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E08 — (%s) al usarla en el pedido, la recogida es esa dirección; en un pedido nuevo ya no aparece',
    async (plataforma) => {
      servidor({ alta: () => respuesta(201, { direccion: SIN_FAVORITA }) });
      const pantalla = await abrirLaDireccion(plataforma);
      await agregarDireccion(recogida);

      await continuar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));
      expect(peticionesA('/direcciones/recogida-y-entrega')).toEqual([
        { lavanderia: LAVANDERIA.id_publico, recogida: SIN_FAVORITA.id_publico, entrega: null },
      ]);
      expect(leerPedidoEnCurso()?.recogida).toEqual(SIN_FAVORITA);

      // Un pedido nuevo: el servidor da solo las favoritas, y la app no guarda la otra.
      await pantalla.unmount();
      empezarPedido(LAVANDERIA);
      await renderRouter(APP, { initialUrl: '/pedido/direccion' });
      await screen.findByTestId('favoritas-de-recogida');
      expect(screen.queryByText(/Calle de una vez/)).not.toBeOnTheScreen();
      expect(screen.queryByTestId('solo-este-pedido-de-recogida')).not.toBeOnTheScreen();
    },
  );
});

describe('direcciones-E09: entrega en la misma dirección', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E09 — (%s) con "Casa" para la recogida y la entrega en la misma dirección, el pedido lleva "Casa" en las dos y llegan al resumen',
    async (plataforma) => {
      await abrirLaDireccion(plataforma);
      const misma = screen.getByRole('switch', { name: 'Entregar en la misma dirección' });
      expect(misma).toBeChecked();
      expect(screen.queryByTestId('direcciones-de-entrega')).not.toBeOnTheScreen();

      await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));
      await continuar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));
      expect(peticionesA('/direcciones/recogida-y-entrega')).toEqual([
        { lavanderia: LAVANDERIA.id_publico, recogida: CASA.id_publico, entrega: null },
      ]);
      const pedido = leerPedidoEnCurso();
      expect(pedido?.recogida).toEqual(CASA);
      expect(pedido?.entrega).toEqual(CASA);
      expect(pedido?.servicio).toBe('estandar');
      expect(await screen.findByText('Pedido nuevo · 4 de 4')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E09 — (%s) sin elegir la recogida, "Continuar" no avanza y dice que falta',
    async (plataforma) => {
      await abrirLaDireccion(plataforma);

      await continuar();

      expect(await screen.findByText('Elige la dirección de recogida para continuar.')).toBeOnTheScreen();
      expect(screen).toHavePathname('/pedido/direccion');
      expect(peticionesA('/direcciones/recogida-y-entrega')).toHaveLength(0);
    },
  );

  test('direcciones-E09 — se elige una sola dirección de recogida a la vez', async () => {
    await abrirLaDireccion('ios');

    await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));
    await fireEvent.press(recogida().getByRole('radio', { name: /^Oficina,/ }));

    expect(recogida().getByRole('radio', { name: /^Oficina,/ })).toBeChecked();
    expect(recogida().getByRole('radio', { name: /^Casa,/ })).not.toBeChecked();
  });

  test('direcciones-E09 — desde el resumen, "Regresar" vuelve a la dirección con la elección', async () => {
    await abrirLaDireccion('android');
    await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));
    await continuar();
    await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));

    await fireEvent.press(await screen.findByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname('/pedido/direccion'));
    expect(await recogida().findByRole('radio', { name: /^Casa,/ })).toBeChecked();
  });
});

describe('direcciones-E10: entrega en otra dirección', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E10 — (%s) con "Casa" para la recogida y "Oficina" para la entrega, el pedido lleva las dos',
    async (plataforma) => {
      await abrirLaDireccion(plataforma);
      await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));

      await fireEvent(screen.getByRole('switch', { name: 'Entregar en la misma dirección' }), 'valueChange', false);
      expect(screen.getByText('¿Dónde la entregamos?')).toBeOnTheScreen();
      await fireEvent.press(entrega().getByRole('radio', { name: /^Oficina,/ }));
      await continuar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));
      expect(peticionesA('/direcciones/recogida-y-entrega')).toEqual([
        { lavanderia: LAVANDERIA.id_publico, recogida: CASA.id_publico, entrega: OFICINA.id_publico },
      ]);
      expect(leerPedidoEnCurso()?.recogida).toEqual(CASA);
      expect(leerPedidoEnCurso()?.entrega).toEqual(OFICINA);
    },
  );

  test('direcciones-E10 — sin elegir la entrega, "Continuar" no avanza y dice que falta', async () => {
    await abrirLaDireccion('ios');
    await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));
    await fireEvent(screen.getByRole('switch', { name: 'Entregar en la misma dirección' }), 'valueChange', false);

    await continuar();

    expect(await screen.findByText('Elige la dirección de entrega para continuar.')).toBeOnTheScreen();
    expect(peticionesA('/direcciones/recogida-y-entrega')).toHaveLength(0);
  });

  test('direcciones-E10 — una dirección nueva agregada para la entrega queda elegida para la entrega', async () => {
    servidor({ alta: () => respuesta(201, { direccion: SIN_FAVORITA }) });
    await abrirLaDireccion('android');
    await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));
    await fireEvent(screen.getByRole('switch', { name: 'Entregar en la misma dirección' }), 'valueChange', false);

    await agregarDireccion(entrega);

    expect(await entrega().findByRole('radio', { name: /^Calle de una vez 789/ })).toBeChecked();
    expect(recogida().getByRole('radio', { name: /^Casa,/ })).toBeChecked();
    await continuar();
    await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));
    expect(leerPedidoEnCurso()?.entrega).toEqual(SIN_FAVORITA);
  });
});

describe('direcciones-E11: dirección fuera de toda zona de servicio', () => {
  const FUERA_DE_ZONA = 'La dirección está fuera de la zona de servicio.';

  test.each(PLATAFORMAS)(
    'direcciones-E11 — (%s) si el servidor rechaza la recogida por estar fuera de la zona de servicio, la app lo dice y deja elegir otra',
    async (plataforma) => {
      servidor({
        validar: (cuerpo) =>
          cuerpo.recogida === CASA.id_publico
            ? respuesta(422, {
                message: FUERA_DE_ZONA,
                errors: { recogida: [FUERA_DE_ZONA] },
                motivos: { recogida: 'fuera_de_la_zona_de_servicio' },
              })
            : aceptarLasDos(cuerpo),
      });
      await abrirLaDireccion(plataforma);
      await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));

      await continuar();

      expect(await recogida().findByRole('alert')).toHaveTextContent(
        'Esta dirección está fuera de la zona de servicio. Elige otra.',
      );
      expect(screen).toHavePathname('/pedido/direccion');
      expect(leerPedidoEnCurso()?.recogida).toBeUndefined();

      await fireEvent.press(recogida().getByRole('radio', { name: /^Oficina,/ }));
      expect(recogida().queryByRole('alert')).not.toBeOnTheScreen();
      await continuar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/resumen'));
      expect(leerPedidoEnCurso()?.recogida).toEqual(OFICINA);
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E11 — (%s) si rechaza la entrega, el aviso va en la entrega y la recogida no lo lleva',
    async (plataforma) => {
      servidor({
        validar: () =>
          respuesta(422, {
            message: FUERA_DE_ZONA,
            errors: { entrega: [FUERA_DE_ZONA] },
            motivos: { entrega: 'fuera_de_la_zona_de_servicio' },
          }),
      });
      await abrirLaDireccion(plataforma);
      await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));
      await fireEvent(screen.getByRole('switch', { name: 'Entregar en la misma dirección' }), 'valueChange', false);
      await fireEvent.press(entrega().getByRole('radio', { name: /^Oficina,/ }));

      await continuar();

      expect(await entrega().findByRole('alert')).toHaveTextContent(
        'Esta dirección está fuera de la zona de servicio. Elige otra.',
      );
      expect(recogida().queryByRole('alert')).not.toBeOnTheScreen();
      expect(leerPedidoEnCurso()?.entrega).toBeUndefined();
    },
  );

  test('direcciones-E11 — con el motivo de otra plaza (direcciones-E12), dice que esa lavandería no da servicio ahí', async () => {
    servidor({
      validar: () =>
        respuesta(422, {
          message: 'Esta lavandería no da servicio en esa dirección.',
          errors: { recogida: ['Esta lavandería no da servicio en esa dirección.'] },
          motivos: { recogida: 'fuera_de_la_plaza_de_la_lavanderia' },
        }),
    });
    await abrirLaDireccion('ios');
    await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));

    await continuar();

    expect(await recogida().findByRole('alert')).toHaveTextContent(
      'Esta lavandería no da servicio en esa dirección. Elige otra.',
    );
  });

  test('direcciones-E11 — sin conexión con el servidor, lo dice con el mensaje general y no avanza', async () => {
    servidor({ validar: () => Promise.reject(new TypeError('Network request failed')) as never });
    await abrirLaDireccion('android');
    await fireEvent.press(recogida().getByRole('radio', { name: /^Casa,/ }));

    await continuar();

    expect(await screen.findByText(/No se pudo conectar con el servidor/)).toBeOnTheScreen();
    expect(screen).toHavePathname('/pedido/direccion');
  });
});

describe('direcciones-E06 a E11: el paso de la dirección sin sesión o sin pedido', () => {
  test('sin pedido en curso, el paso de la dirección lleva a la pantalla principal', async () => {
    await conSesion('ios');

    await renderRouter(APP, { initialUrl: '/pedido/direccion' });

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test.each(['/pedido/direccion', '/pedido/resumen'])(
    'registro-E11 — sin sesión, abrir %s lleva al inicio de sesión',
    async (ruta) => {
      await comprobarInstalacion();
      await marcarBienvenidaVista();

      await renderRouter(APP, { initialUrl: ruta });

      await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    },
  );
});
