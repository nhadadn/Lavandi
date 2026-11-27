// Spec: openspec/changes/direcciones/specs/direcciones/spec.md, direcciones-E04 y E05 (tarea 2.2). La dirección nueva
// marcada en el mapa: la persona mueve el mapa bajo un pin fijo, escribe la colonia y las demás señas, y la dirección se
// guarda con el punto del pin, sin geocodificar ni de ida ni de vuelta (ADR-0009; presupuesto-de-mapas.md, decisiones 3
// y 4). El mapa es react-native-maps sin proveedor: el de Apple en iPhone y el de Google en Android (ADR-0009,
// decisión 1). Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android. El mapa, el permiso
// y el GPS son las simulaciones de __mocks__/, y el servidor va simulado. Que en cada teléfono se vea el mapa de su
// plataforma y que el pin quede sobre la puerta se prueba en un teléfono real.
import path from 'node:path';

import Constants from 'expo-constants';
import * as Location from 'expo-location';
import { router } from 'expo-router';
import { act, fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { elegirServicio, empezarPedido, leerPedidoEnCurso, vaciarPedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/direcciones-mapa';
const TOKEN = 'token-direcciones-mapa';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

// Dónde está el teléfono, y a dónde mueve la persona el mapa para dejar el pin sobre su puerta.
const UBICACION = { latitude: 24.0277, longitude: -104.6532 };
const PUERTA = { latitude: 24.0301, longitude: -104.6587 };

const LAVANDERIA = {
  id_publico: '01jd00000000000000000000l1',
  nombre: 'Lavandería de prueba direcciones',
  servicios: [
    { codigo: 'estandar' as const, nombre: 'Lavado estándar', precio: '45.00', unidad: { codigo: 'kg', nombre: 'kilo' } },
  ],
};

// La dirección como la devuelve el servidor al guardarla: con el punto del pin (direcciones-E04).
const GUARDADA = {
  id_publico: '01jd00000000000000000000d4',
  alias: null,
  es_favorita: false,
  calle: null,
  numero_exterior: null,
  numero_interior: null,
  colonia: 'Colonia de prueba',
  codigo_postal: null,
  municipio: null,
  estado: null,
  referencias: 'Portón de prueba',
  latitud: PUERTA.latitude,
  longitud: PUERTA.longitude,
};

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

// El servidor falso: reconoce el token y contesta el alta de la dirección con lo que se le dé.
function servidor(alGuardar: () => ReturnType<typeof respuesta>) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    // La lista del territorio, que la dirección nueva pide al abrirse (direcciones-E17).
    if (ruta === '/catalogos/municipios') {
      return respuesta(200, {
        municipios: [{ codigo: '00001', nombre: 'Municipio de prueba', estado: { codigo: '00', nombre: 'Estado de prueba' } }],
      });
    }
    if (ruta === '/direcciones' && opciones.method === 'POST') {
      return alGuardar();
    }
    throw new Error(`El servidor falso no esperaba ${opciones.method ?? 'GET'} ${ruta}`);
  });
}

// Las peticiones que guardan una dirección, con su cuerpo leído.
function altas(): Record<string, unknown>[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/direcciones` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => JSON.parse(String(opciones.body)));
}

// La persona con sesión y un pedido en curso abre la dirección nueva desde otro paso del pedido y elige "En el mapa".
// Por omisión ya dio el permiso de ubicación, al buscar lavanderías (cercanas-E03), y el Android es una compilación con
// la llave de Google Maps (cercanas-E02).
async function abrirElMapa(plataforma: Plataforma, { conLlaveDeMapas = true } = {}) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: conLlaveDeMapas };
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  empezarPedido(LAVANDERIA);
  elegirServicio('estandar');
  await renderRouter(APP, { initialUrl: '/pedido/servicio' });
  await screen.findByRole('header', { name: '¿Qué servicio necesitas?' });
  await act(() => router.push('/pedido/direccion-nueva'));
  await screen.findByText('Nueva dirección');
  await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

// La persona mueve el mapa: al soltarlo, el mapa avisa la región que quedó a la vista, con el pin en su centro.
async function moverElMapaA(punto: { latitude: number; longitude: number }) {
  await fireEvent(screen.getByTestId('mapa-para-marcar'), 'regionChangeComplete', {
    ...punto,
    latitudeDelta: 0.005,
    longitudeDelta: 0.005,
  });
}

async function guardar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar dirección' }));
}

beforeEach(() => {
  almacen.__vaciar();
  gps.__reiniciar();
  gps.__permiso(gps.PermissionStatus.GRANTED);
  gps.__ubicacion(UBICACION);
  vaciarPedidoEnCurso();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba direcciones-mapa';
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
  delete configuracionDeLaApp.extra;
  global.fetch = fetchOriginal;
  jest.restoreAllMocks();
});

describe('direcciones-E04: marcar la dirección en el mapa', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E04 — (%s) "En el mapa" abre el mapa centrado en el teléfono, con el pin fijo en el centro y la colonia por escribir',
    async (plataforma) => {
      await abrirElMapa(plataforma);

      const mapa = await screen.findByTestId('mapa-para-marcar');
      expect(mapa.props.initialRegion).toMatchObject(UBICACION);
      // El pin es adorno: el lector de pantalla anuncia el mapa, no el pin.
      expect(screen.getByTestId('pin-de-la-direccion', { includeHiddenElements: true })).toBeOnTheScreen();
      expect(screen.getByText('Mueve el mapa para dejar el pin sobre tu puerta.')).toBeOnTheScreen();
      // Sin geocodificación inversa, nadie sugiere las señas: la colonia se escribe, y las demás son opcionales.
      for (const etiqueta of [
        'Calle (opcional)',
        'Número (opcional)',
        'Número interior (opcional)',
        'Colonia',
        'Referencias (opcional)',
      ]) {
        expect(screen.getByLabelText(etiqueta)).toHaveDisplayValue('');
      }
      // El municipio, opcional, se elige de la lista del territorio (direcciones-E17).
      const municipio = screen.getByRole('button', { name: 'Municipio (opcional)' });
      await waitFor(() => expect(municipio).toBeEnabled());
      expect(municipio).toHaveAccessibilityValue({ text: 'Elige tu municipio' });
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E04 — (%s) al dejar el pin sobre su puerta, escribir la colonia y las señas y guardar, la dirección se manda con el punto del pin',
    async (plataforma) => {
      await abrirElMapa(plataforma);
      await screen.findByTestId('mapa-para-marcar');

      await moverElMapaA(PUERTA);
      await escribir('Colonia', GUARDADA.colonia);
      await escribir('Referencias (opcional)', GUARDADA.referencias);
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toEqual({
        origen: 'mapa',
        latitud: PUERTA.latitude,
        longitud: PUERTA.longitude,
        colonia: GUARDADA.colonia,
        referencias: GUARDADA.referencias,
        es_favorita: false,
      });
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E04 — (%s) la dirección guardada con el punto del pin queda en el pedido en curso y la app vuelve al paso anterior',
    async (plataforma) => {
      await abrirElMapa(plataforma);
      await screen.findByTestId('mapa-para-marcar');
      await moverElMapaA(PUERTA);
      await escribir('Colonia', GUARDADA.colonia);

      await guardar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
      expect(leerPedidoEnCurso()?.agregadas).toEqual([{ direccion: GUARDADA, para: 'recogida' }]);
    },
  );

  test('direcciones-E04 — sin mover el mapa, el punto del pin es el del teléfono', async () => {
    await abrirElMapa('ios');
    await screen.findByTestId('mapa-para-marcar');
    await escribir('Colonia', GUARDADA.colonia);

    await guardar();

    await waitFor(() => expect(altas()).toHaveLength(1));
    expect(altas()[0]).toMatchObject({ latitud: UBICACION.latitude, longitud: UBICACION.longitude });
  });

  test('direcciones-E04 — mover el mapa no consulta nada: la calle no se sugiere desde el pin', async () => {
    await abrirElMapa('android');
    await screen.findByTestId('mapa-para-marcar');
    // Ya llegó la lista de municipios, que se pide al abrir la dirección nueva (direcciones-E17).
    await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio (opcional)' })).toBeEnabled());
    const antes = fetchFalso.mock.calls.length;

    await moverElMapaA(PUERTA);
    await moverElMapaA(UBICACION);

    expect(fetchFalso.mock.calls).toHaveLength(antes);
    expect(screen.getByLabelText('Calle (opcional)')).toHaveDisplayValue('');
  });

  test('direcciones-E04 — en el mapa la colonia sigue siendo obligatoria: el error del servidor va junto a ella', async () => {
    servidor(() => respuesta(422, { message: 'Falta la colonia.', errors: { colonia: ['Falta la colonia.'] } }));
    await abrirElMapa('ios');
    await screen.findByTestId('mapa-para-marcar');

    await guardar();

    expect(await within(screen.getByTestId('campo-colonia')).findByRole('alert')).toHaveTextContent(
      'Falta la colonia.',
    );
    expect(screen).toHavePathname('/pedido/direccion-nueva');
    expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
  });

  test('direcciones-E04 — lo escrito antes de pasar al mapa sigue ahí', async () => {
    await abrirElMapa('ios');
    await fireEvent.press(screen.getByRole('tab', { name: 'Escribirla' }));
    await escribir('Colonia', GUARDADA.colonia);

    await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));

    expect(await screen.findByTestId('mapa-para-marcar')).toBeOnTheScreen();
    expect(screen.getByLabelText('Colonia')).toHaveDisplayValue(GUARDADA.colonia);
  });

  // Sin escenario propio: el permiso de ubicación es de la búsqueda de lavanderías (cercanas-E03) y el mapa de la
  // dirección no lo pide. Sin él, el mapa no tiene dónde abrirse: lo dice y deja escribir la dirección.
  test('direcciones-E04 — sin permiso de ubicación, el mapa no se abre, no se pide el permiso y se puede escribir la dirección', async () => {
    gps.__permiso(gps.PermissionStatus.UNDETERMINED);
    await abrirElMapa('android');

    expect(await screen.findByText(/Sin tu ubicación no podemos abrir el mapa/)).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa-para-marcar')).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Guardar dirección' })).not.toBeOnTheScreen();
    expect(gps.requestForegroundPermissionsAsync).not.toHaveBeenCalled();

    await fireEvent.press(screen.getByRole('tab', { name: 'Escribirla' }));
    expect(screen.getByRole('button', { name: 'Guardar dirección' })).toBeOnTheScreen();
  });

  test('direcciones-E04 — si el teléfono no da su ubicación, el mapa no se abre y lo dice', async () => {
    gps.__ubicacion(new Error('Sin ubicación'));
    await abrirElMapa('ios');

    expect(await screen.findByText(/Sin tu ubicación no podemos abrir el mapa/)).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa-para-marcar')).not.toBeOnTheScreen();
  });

  test('direcciones-E04 — la ubicación se lee al abrir el mapa, no al escribir la dirección', async () => {
    jest.replaceProperty(Platform, 'OS', 'ios');
    await comprobarInstalacion();
    await marcarBienvenidaVista();
    await guardarToken(TOKEN);
    empezarPedido(LAVANDERIA);
    await renderRouter(APP, { initialUrl: '/pedido/direccion-nueva' });
    await screen.findByText('Nueva dirección');

    expect(gps.getCurrentPositionAsync).not.toHaveBeenCalled();
    await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));
    await screen.findByTestId('mapa-para-marcar');
    expect(gps.getCurrentPositionAsync).toHaveBeenCalled();
  });
});

describe('direcciones-E05: el mapa es el de cada plataforma', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E05 — (%s) el mapa para marcar la dirección no fija proveedor: Apple en iPhone y Google en Android',
    async (plataforma) => {
      await abrirElMapa(plataforma);

      const mapa = await screen.findByTestId('mapa-para-marcar');
      expect(mapa.props).not.toHaveProperty('provider');
    },
  );

  test('direcciones-E05 — en iPhone el mapa no depende de la llave de Google', async () => {
    await abrirElMapa('ios', { conLlaveDeMapas: false });

    expect(await screen.findByTestId('mapa-para-marcar')).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa-no-disponible')).not.toBeOnTheScreen();
  });

  // Sin la llave en el manifiesto, el SDK de Google Maps cierra la app al crear el mapa (src/cercanas/mapa.ts).
  test('direcciones-E05 — en un Android sin la llave de Google Maps, el mapa no se monta, lo dice y se puede escribir la dirección', async () => {
    await abrirElMapa('android', { conLlaveDeMapas: false });

    expect(await screen.findByTestId('mapa-no-disponible')).toHaveTextContent(/mapa no está disponible/);
    expect(screen.queryByTestId('mapa-para-marcar')).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Guardar dirección' })).not.toBeOnTheScreen();

    await fireEvent.press(screen.getByRole('tab', { name: 'Escribirla' }));
    expect(screen.getByRole('button', { name: 'Guardar dirección' })).toBeOnTheScreen();
  });
});
