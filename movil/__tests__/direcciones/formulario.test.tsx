// Spec: openspec/changes/direcciones/specs/direcciones/spec.md, direcciones-E01, E02 y E03 (tareas 2.1, 5.1 y 5.2). La
// dirección nueva, escrita: calle, número, colonia y municipio, con el número interior, el código postal y las
// referencias opcionales (DD-135, punto 6; C-238). La colonia es obligatoria y el municipio se elige de la lista del
// territorio (direcciones-E17, que prueba municipio.test.tsx; el código postal y la colonia de su lista, en
// codigo-postal-y-colonia.test.tsx). El servidor convierte la dirección en un punto una sola vez, al guardarla
// (ADR-0009, decisión 4); si no la encuentra, la app ofrece marcarla en el mapa. Se abre la app real, con las rutas de
// movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado: la geocodificación vive en el servidor, así
// que aquí ninguna prueba la consulta.
//
// La dirección nueva se abre desde el paso de la dirección del pedido (tarea 2.3). Aquí se abre sobre otra pantalla
// del pedido, para comprobar que al guardar vuelve a la anterior con la dirección en el pedido en curso.
import path from 'node:path';

import { router } from 'expo-router';
import { act, fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { olvidarMunicipios } from '../../src/direcciones/useMunicipios';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { elegirServicio, empezarPedido, leerPedidoEnCurso, vaciarPedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/direcciones-formulario';
const TOKEN = 'token-direcciones-formulario';
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

// La lista del territorio de prueba: un solo municipio, con su estado (direcciones-E17).
const MUNICIPIO = { codigo: '00001', nombre: 'Municipio de prueba', estado: { codigo: '00', nombre: 'Estado de prueba' } };

// La dirección como la devuelve el servidor al guardarla: con el punto que le dio la geocodificación (direcciones-E01).
const GUARDADA = {
  id_publico: '01jd00000000000000000000d1',
  alias: null,
  es_favorita: false,
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: null,
  colonia: 'Colonia de prueba',
  codigo_postal: null,
  municipio: 'Municipio de prueba',
  estado: 'Estado de prueba',
  referencias: null,
  latitud: 24.0277,
  longitud: -104.6532,
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

// El servidor falso: reconoce el token, da la lista de municipios y contesta el alta de la dirección con lo que se le
// dé.
function servidor(alGuardar: () => ReturnType<typeof respuesta> | Promise<never>) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/catalogos/municipios') {
      return respuesta(200, { municipios: [MUNICIPIO] });
    }
    if (ruta === '/direcciones' && opciones.method === 'POST') {
      return alGuardar();
    }
    throw new Error(`El servidor falso no esperaba ${opciones.method ?? 'GET'} ${ruta}`);
  });
}

// Las peticiones que guardan una dirección, con su cuerpo leído.
function altas(): { opciones: RequestInit; cuerpo: Record<string, unknown> }[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/direcciones` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => ({ opciones, cuerpo: JSON.parse(String(opciones.body)) }));
}

async function conSesion(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

// La persona con sesión y un pedido en curso, con lavandería y servicio, abre la dirección nueva desde otro paso del
// pedido.
async function abrirElFormulario(plataforma: Plataforma) {
  await conSesion(plataforma);
  empezarPedido(LAVANDERIA);
  elegirServicio('estandar');
  await renderRouter(APP, { initialUrl: '/pedido/servicio' });
  await screen.findByRole('header', { name: '¿Qué servicio necesitas?' });
  await act(() => router.push('/pedido/direccion-nueva'));
  await waitFor(() => expect(screen).toHavePathname('/pedido/direccion-nueva'));
  await screen.findByText('Nueva dirección');
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

// El municipio de la lista, cuando llega (direcciones-E17).
async function elegirElMunicipio() {
  const campo = () => screen.getByRole('button', { name: 'Municipio' });
  await waitFor(() => expect(campo()).toBeEnabled());
  await fireEvent.press(campo());
  await fireEvent.press(
    within(screen.getByTestId('hoja-municipio')).getByRole('radio', { name: 'Municipio de prueba, Estado de prueba' }),
  );
}

// La dirección de prueba, escrita completa salvo lo que se pida dejar vacío.
async function escribirLaDireccion({ sinColonia = false } = {}) {
  await escribir('Calle', GUARDADA.calle);
  await escribir('Número', GUARDADA.numero_exterior);
  if (!sinColonia) {
    await escribir('Colonia', GUARDADA.colonia);
  }
  await elegirElMunicipio();
}

async function guardar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar dirección' }));
}

beforeEach(() => {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  olvidarMunicipios();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El inicio de sesión lleva el nombre de la app en su logotipo.
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba direcciones-formulario';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor(() => respuesta(201, { direccion: GUARDADA }));
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

describe('direcciones-E01: una dirección escrita se guarda con su punto', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E01 — (%s) la dirección nueva se escribe por omisión, con calle, número, número interior, código postal, colonia, municipio y referencias',
    async (plataforma) => {
      await abrirElFormulario(plataforma);

      expect(screen.getByRole('tab', { name: 'Escribirla' })).toBeSelected();
      expect(screen.getByRole('tab', { name: 'En el mapa' })).not.toBeSelected();
      // El código postal se pide y no se exige (C-238; decisión 29 del acta del 2026-11-20): lo prueba
      // codigo-postal-y-colonia.test.tsx.
      for (const etiqueta of [
        'Calle',
        'Número',
        'Número interior (opcional)',
        'Código postal (opcional)',
        'Colonia',
        'Municipio',
        'Referencias (opcional)',
      ]) {
        expect(screen.getByLabelText(etiqueta)).toBeOnTheScreen();
      }
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E01 — (%s) al escribir calle y número y colonia, elegir el municipio de la lista y guardar, la app manda la dirección escrita al servidor una sola vez, con la sesión y sin punto',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();

      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      const [{ opciones, cuerpo }] = altas();
      expect((opciones.headers as Record<string, string>).Authorization).toBe(`Bearer ${TOKEN}`);
      expect(cuerpo).toEqual({
        origen: 'escrita',
        calle: GUARDADA.calle,
        numero_exterior: GUARDADA.numero_exterior,
        colonia: GUARDADA.colonia,
        // El código del municipio elegido, no su nombre (direcciones-E17).
        municipio: MUNICIPIO.codigo,
        es_favorita: false,
      });
      // El punto lo pone el servidor con la geocodificación: la app no manda ninguno (ADR-0009, decisión 4).
      expect(cuerpo).not.toHaveProperty('latitud');
      expect(cuerpo).not.toHaveProperty('longitud');
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E01 — (%s) la dirección guardada, con el punto que devolvió el servidor, queda en el pedido en curso para la recogida y la app vuelve al paso anterior',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();

      await guardar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
      expect(leerPedidoEnCurso()?.agregadas).toEqual([{ direccion: GUARDADA, para: 'recogida' }]);
      expect(fetchFalso.mock.calls.filter(([url]: [string]) => url.startsWith(`${BASE}/direcciones`))).toHaveLength(1);
    },
  );

  test('direcciones-E01 — el número interior y las referencias viajan si se escriben, sin espacios de sobra', async () => {
    await abrirElFormulario('ios');
    await escribirLaDireccion();
    await escribir('Número interior (opcional)', ' 4 ');
    await escribir('Referencias (opcional)', '  Portón gris  ');

    await guardar();

    await waitFor(() => expect(altas()).toHaveLength(1));
    expect(altas()[0].cuerpo).toMatchObject({ numero_interior: '4', referencias: 'Portón gris' });
  });
});

describe('direcciones-E02: la colonia es obligatoria', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E02 — (%s) sin colonia, el servidor la rechaza nombrando el campo y la app lo señala junto a la colonia',
    async (plataforma) => {
      servidor(() =>
        respuesta(422, { message: 'Falta la colonia.', errors: { colonia: ['Falta la colonia.'] } }),
      );
      await abrirElFormulario(plataforma);
      await escribirLaDireccion({ sinColonia: true });

      await guardar();

      expect(await within(screen.getByTestId('campo-colonia')).findByRole('alert')).toHaveTextContent(
        'Falta la colonia.',
      );
      expect(altas()).toHaveLength(1);
      expect(altas()[0].cuerpo).not.toHaveProperty('colonia');
      // No se guardó nada y la persona sigue en el formulario.
      expect(screen).toHavePathname('/pedido/direccion-nueva');
      expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
      expect(screen.queryByRole('button', { name: 'Marcarla en el mapa' })).not.toBeOnTheScreen();
    },
  );

  test('direcciones-E02 — al escribir la colonia y volver a guardar, la dirección se guarda', async () => {
    servidor(() => respuesta(422, { message: 'Falta la colonia.', errors: { colonia: ['Falta la colonia.'] } }));
    await abrirElFormulario('android');
    await escribirLaDireccion({ sinColonia: true });
    await guardar();
    await within(screen.getByTestId('campo-colonia')).findByRole('alert');

    servidor(() => respuesta(201, { direccion: GUARDADA }));
    await escribir('Colonia', GUARDADA.colonia);
    await guardar();

    await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
    expect(altas()[1].cuerpo).toMatchObject({ colonia: GUARDADA.colonia });
  });
});

describe('direcciones-E03: la dirección escrita no se encuentra', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E03 — (%s) si el servidor no la encuentra, la app lo dice, no guarda nada y ofrece marcarla en el mapa',
    async (plataforma) => {
      servidor(() =>
        respuesta(422, {
          message: 'No encontramos la dirección. Márcala en el mapa.',
          motivo: 'direccion_no_encontrada',
        }),
      );
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();

      await guardar();

      expect(await screen.findByText('No encontramos la dirección. Márcala en el mapa.')).toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Marcarla en el mapa' })).toBeOnTheScreen();
      expect(screen).toHavePathname('/pedido/direccion-nueva');
      expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E03 — (%s) "Marcarla en el mapa" pasa a marcarla en el mapa sin perder lo que escribió',
    async (plataforma) => {
      servidor(() => respuesta(422, { message: 'No encontramos la dirección.', motivo: 'direccion_no_encontrada' }));
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();
      await guardar();

      await fireEvent.press(await screen.findByRole('button', { name: 'Marcarla en el mapa' }));

      expect(screen.getByRole('tab', { name: 'En el mapa' })).toBeSelected();
      expect(screen.getByRole('tab', { name: 'Escribirla' })).not.toBeSelected();
      expect(screen.getByLabelText('Colonia')).toHaveDisplayValue(GUARDADA.colonia);
      expect(screen.queryByRole('button', { name: 'Marcarla en el mapa' })).not.toBeOnTheScreen();
    },
  );

  test('direcciones-E03 — si el servicio de búsqueda no responde, la app lo dice y también ofrece el mapa', async () => {
    servidor(() =>
      respuesta(503, {
        message: 'No pudimos buscar la dirección. Márcala en el mapa o inténtalo más tarde.',
        motivo: 'geocodificacion_no_disponible',
      }),
    );
    await abrirElFormulario('android');
    await escribirLaDireccion();

    await guardar();

    expect(
      await screen.findByText('No pudimos buscar la dirección. Márcala en el mapa o inténtalo más tarde.'),
    ).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Marcarla en el mapa' })).toBeOnTheScreen();
    expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
  });

  test('direcciones-E03 — sin conexión con el servidor, lo dice con el mensaje general y no ofrece el mapa', async () => {
    servidor(() => Promise.reject(new TypeError('Network request failed')));
    await abrirElFormulario('ios');
    await escribirLaDireccion();

    await guardar();

    expect(await screen.findByText(/No se pudo conectar con el servidor/)).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Marcarla en el mapa' })).not.toBeOnTheScreen();
    expect(screen).toHavePathname('/pedido/direccion-nueva');
  });
});

describe('direcciones-E01 a E03: la dirección nueva sin sesión o sin pedido', () => {
  test('sin pedido en curso, la dirección nueva lleva a la pantalla principal', async () => {
    await conSesion('ios');

    await renderRouter(APP, { initialUrl: '/pedido/direccion-nueva' });

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
  });

  test('registro-E11 — sin sesión, abrir la dirección nueva lleva al inicio de sesión', async () => {
    await comprobarInstalacion();
    await marcarBienvenidaVista();

    await renderRouter(APP, { initialUrl: '/pedido/direccion-nueva' });

    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
  });
});
