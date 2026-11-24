// Spec: openspec/changes/direcciones/specs/direcciones/spec.md, direcciones-E01, E17 y E18 (tarea 5.1). El municipio de
// la dirección nueva se elige de la lista de GET /api/catalogos/municipios, que trae solo los municipios del territorio
// del contrato, cada uno con su estado (NSV-2026-001-v8.pdf:4:Cláusula I; C-238). La app la pide una vez por sesión de
// la app, la ofrece en la pieza Selector (decisión 29 del acta del 2026-11-20) y manda el código del elegido. No hay
// campo de texto libre para el municipio: el servidor rechazaría uno que no está en el catálogo, y ese error va junto al
// campo. Sin lista, la app lo dice y deja intentarlo de nuevo, y la forma del mapa sigue sirviendo, porque ahí el
// municipio es opcional. El contrato exacto de las rutas está en openspec/changes/direcciones/design.md, "Contrato de
// la API". Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor
// simulado.
import path from 'node:path';

import Constants from 'expo-constants';
import * as Location from 'expo-location';
import { router } from 'expo-router';
import { act, fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { AccessibilityInfo, Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { olvidarMunicipios } from '../../src/direcciones/useMunicipios';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { elegirServicio, empezarPedido, leerPedidoEnCurso, vaciarPedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/direcciones-municipio';
const TOKEN = 'token-direcciones-municipio';
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

// La lista del catálogo como la da el servidor: ordenada por el nombre del estado y luego por el del municipio. Los
// códigos son de prueba: los reales los da el archivo de Correos de México (design.md, grado E).
const MUNICIPIOS = [
  { codigo: '05017', nombre: 'Matamoros', estado: { codigo: '05', nombre: 'Coahuila de Zaragoza' } },
  { codigo: '05035', nombre: 'Torreón', estado: { codigo: '05', nombre: 'Coahuila de Zaragoza' } },
  { codigo: '10005', nombre: 'Durango', estado: { codigo: '10', nombre: 'Durango' } },
  { codigo: '10007', nombre: 'Gómez Palacio', estado: { codigo: '10', nombre: 'Durango' } },
];

// La dirección como la devuelve el servidor al guardarla: con el nombre del municipio del catálogo y el de su estado
// (direcciones-E17).
const GUARDADA = {
  id_publico: '01jd00000000000000000000m1',
  alias: null,
  es_favorita: false,
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: null,
  colonia: 'Colonia de prueba',
  codigo_postal: null,
  municipio: 'Torreón',
  estado: 'Coahuila de Zaragoza',
  referencias: null,
  latitud: 25.5428,
  longitud: -103.4068,
};

// Dónde está el teléfono, para la forma del mapa.
const UBICACION = { latitude: 25.5428, longitude: -103.4068 };

const FUERA_DEL_TERRITORIO = 'Elige un municipio de la lista.';

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

type Respuesta = ReturnType<typeof respuesta>;
interface Servidor {
  municipios?: () => Respuesta | Promise<Respuesta>;
  alta?: () => Respuesta;
}

// El servidor falso: reconoce el token, da la lista de municipios y contesta el alta de la dirección.
function servidor({
  municipios = () => respuesta(200, { municipios: MUNICIPIOS }),
  alta = () => respuesta(201, { direccion: GUARDADA }),
}: Servidor = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/catalogos/municipios' && metodo === 'GET') {
      return municipios();
    }
    if (ruta === '/direcciones' && metodo === 'POST') {
      return alta();
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Las peticiones de la lista de municipios, con sus cabeceras.
function pedidasDeLaLista(): RequestInit[] {
  return fetchFalso.mock.calls
    .filter(([url]: [string]) => url === `${BASE}/catalogos/municipios`)
    .map(([, opciones]: [string, RequestInit]) => opciones);
}

// Las peticiones que guardan una dirección, con su cuerpo leído.
function altas(): Record<string, unknown>[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/direcciones` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => JSON.parse(String(opciones.body)));
}

// La persona con sesión y un pedido en curso abre la dirección nueva desde otro paso del pedido. Ya dio el permiso de
// ubicación, y el Android es una compilación con la llave de Google Maps, para la forma del mapa.
async function abrirElFormulario(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: true };
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  empezarPedido(LAVANDERIA);
  elegirServicio('estandar');
  await renderRouter(APP, { initialUrl: '/pedido/servicio' });
  await screen.findByRole('header', { name: '¿Qué servicio necesitas?' });
  await abrirLaDireccionNueva();
}

async function abrirLaDireccionNueva() {
  await act(() => router.push('/pedido/direccion-nueva'));
  await waitFor(() => expect(screen).toHavePathname('/pedido/direccion-nueva'));
  await screen.findByText('Nueva dirección');
}

function campoDelMunicipio(etiqueta = 'Municipio') {
  return screen.getByRole('button', { name: etiqueta });
}

// Espera a que llegue la lista y elige un municipio de ella.
async function elegirMunicipio(texto: string, etiqueta = 'Municipio') {
  await waitFor(() => expect(campoDelMunicipio(etiqueta)).toBeEnabled());
  await fireEvent.press(campoDelMunicipio(etiqueta));
  await fireEvent.press(within(screen.getByTestId('hoja-municipio')).getByRole('radio', { name: texto }));
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

async function escribirLaDireccion() {
  await escribir('Calle', GUARDADA.calle);
  await escribir('Número', GUARDADA.numero_exterior);
  await escribir('Colonia', GUARDADA.colonia);
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
  olvidarMunicipios();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba direcciones-municipio';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
  jest.spyOn(AccessibilityInfo, 'announceForAccessibilityWithOptions').mockImplementation(() => {});
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

describe('direcciones-E17: la lista de municipios es la del territorio', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) al abrir el formulario, la app pide la lista con la sesión y ofrece el municipio para elegirlo, sin campo de texto libre',
    async (plataforma) => {
      await abrirElFormulario(plataforma);

      await waitFor(() => expect(pedidasDeLaLista()).toHaveLength(1));
      const [peticion] = pedidasDeLaLista();
      expect(peticion.method).toBe('GET');
      expect((peticion.headers as Record<string, string>).Authorization).toBe(`Bearer ${TOKEN}`);
      await waitFor(() => expect(campoDelMunicipio()).toBeEnabled());
      expect(campoDelMunicipio()).toHaveAccessibilityValue({ text: 'Elige tu municipio' });
      // El único elemento con esa etiqueta es el botón que abre la lista: ninguno deja escribir.
      expect(screen.getAllByLabelText('Municipio')).toEqual([campoDelMunicipio()]);
      expect(within(screen.getByTestId('campo-municipio')).queryByDisplayValue(/.*/)).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) la lista trae solo los municipios del catálogo, cada uno con su estado, en el orden del servidor',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await waitFor(() => expect(campoDelMunicipio()).toBeEnabled());

      await fireEvent.press(campoDelMunicipio());

      const hoja = within(screen.getByTestId('hoja-municipio'));
      expect(hoja.getAllByRole('radio').map((opcion) => opcion.props.accessibilityLabel)).toEqual([
        'Matamoros, Coahuila de Zaragoza',
        'Torreón, Coahuila de Zaragoza',
        'Durango, Durango',
        'Gómez Palacio, Durango',
      ]);
      expect(hoja.getByLabelText('Buscar municipio')).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) al elegir uno, el campo lo muestra con su estado y al guardar la app manda su código',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();

      await elegirMunicipio('Torreón, Coahuila de Zaragoza');
      expect(campoDelMunicipio()).toHaveAccessibilityValue({ text: 'Torreón, Coahuila de Zaragoza' });
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toEqual({
        origen: 'escrita',
        calle: GUARDADA.calle,
        numero_exterior: GUARDADA.numero_exterior,
        colonia: GUARDADA.colonia,
        municipio: '05035',
        es_favorita: false,
      });
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E01 y E17 — (%s) la dirección guardada sale con el nombre del municipio y el de su estado, y queda en el pedido en curso',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();
      await elegirMunicipio('Torreón, Coahuila de Zaragoza');

      await guardar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
      expect(leerPedidoEnCurso()?.agregadas).toEqual([{ direccion: GUARDADA, para: 'recogida' }]);
    },
  );

  test('direcciones-E17 — la lista se pide una vez por sesión de la app: al abrir otra dirección nueva ya está', async () => {
    await abrirElFormulario('ios');
    await escribirLaDireccion();
    await elegirMunicipio('Torreón, Coahuila de Zaragoza');
    await guardar();
    await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));

    await abrirLaDireccionNueva();

    expect(campoDelMunicipio()).toBeEnabled();
    expect(campoDelMunicipio()).toHaveAccessibilityValue({ text: 'Elige tu municipio' });
    expect(pedidasDeLaLista()).toHaveLength(1);
  });

  test('direcciones-E17 — mientras llega la lista, el campo no se abre y dice que la está cargando', async () => {
    let responder: (valor: Respuesta) => void = () => {};
    servidor({ municipios: () => new Promise<Respuesta>((resolver) => (responder = resolver)) });
    await abrirElFormulario('android');

    await waitFor(() => expect(pedidasDeLaLista()).toHaveLength(1));
    expect(campoDelMunicipio()).toBeDisabled();
    expect(campoDelMunicipio()).toHaveAccessibilityValue({ text: 'Cargando los municipios…' });

    await act(async () => responder(respuesta(200, { municipios: MUNICIPIOS })));
    await waitFor(() => expect(campoDelMunicipio()).toBeEnabled());
  });

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) sin catálogo cargado, lo dice junto al campo, no deja escribir el municipio y deja intentarlo de nuevo',
    async (plataforma) => {
      servidor({ municipios: () => respuesta(200, { municipios: [] }) });
      await abrirElFormulario(plataforma);

      expect(await within(screen.getByTestId('campo-municipio')).findByRole('alert')).toHaveTextContent(
        'No pudimos cargar la lista de municipios. Intenta de nuevo o marca la dirección en el mapa.',
      );
      expect(campoDelMunicipio()).toBeDisabled();
      expect(screen.getAllByLabelText('Municipio')).toEqual([campoDelMunicipio()]);

      servidor();
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      await waitFor(() => expect(campoDelMunicipio()).toBeEnabled());
      expect(pedidasDeLaLista()).toHaveLength(2);
      expect(within(screen.getByTestId('campo-municipio')).queryByRole('alert')).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Intentar de nuevo' })).not.toBeOnTheScreen();
      await elegirMunicipio('Durango, Durango');
      expect(campoDelMunicipio()).toHaveAccessibilityValue({ text: 'Durango, Durango' });
    },
  );

  test('direcciones-E17 — sin conexión con el servidor, lo dice igual y deja intentarlo de nuevo', async () => {
    servidor({ municipios: () => Promise.reject(new TypeError('Network request failed')) });
    await abrirElFormulario('ios');

    expect(await within(screen.getByTestId('campo-municipio')).findByRole('alert')).toHaveTextContent(
      /^No pudimos cargar la lista de municipios\./,
    );
    expect(screen.getByRole('button', { name: 'Intentar de nuevo' })).toBeOnTheScreen();
  });

  test('direcciones-E17 — una lista que no llegó no se queda para la sesión: la siguiente dirección nueva la pide otra vez', async () => {
    servidor({ municipios: () => respuesta(200, { municipios: [] }) });
    await abrirElFormulario('android');
    await within(screen.getByTestId('campo-municipio')).findByRole('alert');
    await act(() => router.back());
    await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));

    servidor();
    await abrirLaDireccionNueva();

    await waitFor(() => expect(campoDelMunicipio()).toBeEnabled());
    expect(pedidasDeLaLista()).toHaveLength(2);
  });

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) sin lista, la forma del mapa sigue sirviendo: lo dice y guarda la dirección sin municipio',
    async (plataforma) => {
      servidor({
        municipios: () => respuesta(200, { municipios: [] }),
        alta: () => respuesta(201, { direccion: { ...GUARDADA, municipio: null, estado: null } }),
      });
      await abrirElFormulario(plataforma);
      await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));
      await screen.findByTestId('mapa-para-marcar');

      expect(await within(screen.getByTestId('campo-municipio')).findByRole('alert')).toHaveTextContent(
        'No pudimos cargar la lista de municipios. Puedes guardar la dirección sin él.',
      );
      await escribir('Colonia', GUARDADA.colonia);
      await guardar();

      await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
      expect(altas()).toHaveLength(1);
      expect(altas()[0]).not.toHaveProperty('municipio');
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) en la forma del mapa el municipio es opcional y, si se elige, viaja con su código',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));
      await screen.findByTestId('mapa-para-marcar');
      await escribir('Colonia', GUARDADA.colonia);

      await elegirMunicipio('Gómez Palacio, Durango', 'Municipio (opcional)');
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toMatchObject({ origen: 'mapa', colonia: GUARDADA.colonia, municipio: '10007' });
    },
  );
});

describe('direcciones-E18: un municipio fuera del territorio se rechaza', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E18 — (%s) en la dirección escrita, el error del servidor va junto al campo del municipio y no se guarda nada',
    async (plataforma) => {
      servidor({
        alta: () => respuesta(422, { message: FUERA_DEL_TERRITORIO, errors: { municipio: [FUERA_DEL_TERRITORIO] } }),
      });
      await abrirElFormulario(plataforma);
      await escribirLaDireccion();
      await elegirMunicipio('Torreón, Coahuila de Zaragoza');

      await guardar();

      expect(await within(screen.getByTestId('campo-municipio')).findByRole('alert')).toHaveTextContent(
        FUERA_DEL_TERRITORIO,
      );
      // El error va solo junto al campo, no además arriba del botón.
      expect(screen.getAllByText(FUERA_DEL_TERRITORIO)).toHaveLength(1);
      expect(screen).toHavePathname('/pedido/direccion-nueva');
      expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
      // No se ofrece el mapa: el servidor no buscó la dirección.
      expect(screen.queryByRole('button', { name: 'Marcarla en el mapa' })).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E18 — (%s) en la dirección marcada en el mapa, el error también va junto al municipio',
    async (plataforma) => {
      servidor({
        alta: () => respuesta(422, { message: FUERA_DEL_TERRITORIO, errors: { municipio: [FUERA_DEL_TERRITORIO] } }),
      });
      await abrirElFormulario(plataforma);
      await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));
      await screen.findByTestId('mapa-para-marcar');
      await escribir('Colonia', GUARDADA.colonia);
      await elegirMunicipio('Matamoros, Coahuila de Zaragoza', 'Municipio (opcional)');

      await guardar();

      expect(await within(screen.getByTestId('campo-municipio')).findByRole('alert')).toHaveTextContent(
        FUERA_DEL_TERRITORIO,
      );
      expect(screen).toHavePathname('/pedido/direccion-nueva');
      expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
    },
  );

  test('direcciones-E18 — al elegir otro municipio y volver a guardar, la dirección se guarda', async () => {
    servidor({
      alta: () => respuesta(422, { message: FUERA_DEL_TERRITORIO, errors: { municipio: [FUERA_DEL_TERRITORIO] } }),
    });
    await abrirElFormulario('android');
    await escribirLaDireccion();
    await elegirMunicipio('Durango, Durango');
    await guardar();
    await within(screen.getByTestId('campo-municipio')).findByRole('alert');

    servidor();
    await elegirMunicipio('Torreón, Coahuila de Zaragoza');
    await guardar();

    await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
    expect(altas()[1]).toMatchObject({ municipio: '05035' });
  });
});
