// Spec: openspec/changes/direcciones/specs/direcciones/spec.md, direcciones-E02, E19 y E20 (tarea 5.2). El formulario
// pide el código postal, opcional, de cinco números (decisión 29 del acta del 2026-11-20: se pide y no se exige). Con
// los cinco, la app pide sus colonias a GET /api/codigos-postales/{codigo_postal}/colonias y las ofrece en la pieza
// Selector; elegir una escribe su nombre en la colonia y, si la persona no había elegido municipio, elige el de esa
// colonia. El catálogo ayuda y no bloquea: sin colonias para ese código postal, o si la suya no aparece, la colonia se
// escribe a mano, y la colonia sigue siendo obligatoria aunque haya código postal. El contrato exacto está en
// openspec/changes/direcciones/design.md, "Contrato de la API". Se abre la app real, con las rutas de movil/app/
// (ADR-0017), en iPhone y en Android, con el servidor simulado.
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
const BASE = 'http://api.prueba.invalid/direcciones-codigo-postal';
const TOKEN = 'token-direcciones-codigo-postal';
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

// La lista del territorio, con códigos de prueba (design.md, grado E).
const MUNICIPIOS = [
  { codigo: '05035', nombre: 'Torreón', estado: { codigo: '05', nombre: 'Coahuila de Zaragoza' } },
  { codigo: '10005', nombre: 'Durango', estado: { codigo: '10', nombre: 'Durango' } },
  { codigo: '10007', nombre: 'Gómez Palacio', estado: { codigo: '10', nombre: 'Durango' } },
];

// Las colonias de dos códigos postales de prueba, por nombre, cada una con su municipio. Un tercero no está en el
// catálogo.
const DEL_PRIMERO = '34000';
const DEL_SEGUNDO = '35000';
const SIN_CATALOGO = '99999';
const COLONIAS: Record<string, { nombre: string; tipo: string; municipio: { codigo: string; nombre: string } }[]> = {
  [DEL_PRIMERO]: [
    { nombre: 'Colonia de prueba centro', tipo: 'Colonia', municipio: { codigo: '10005', nombre: 'Durango' } },
    { nombre: 'Fraccionamiento de prueba', tipo: 'Fraccionamiento', municipio: { codigo: '10005', nombre: 'Durango' } },
  ],
  [DEL_SEGUNDO]: [
    { nombre: 'Colonia de prueba norte', tipo: 'Colonia', municipio: { codigo: '10007', nombre: 'Gómez Palacio' } },
  ],
};

// La dirección como la devuelve el servidor al guardarla.
const GUARDADA = {
  id_publico: '01jd00000000000000000000p1',
  alias: null,
  es_favorita: false,
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: null,
  colonia: 'Colonia de prueba centro',
  codigo_postal: DEL_PRIMERO,
  municipio: 'Durango',
  estado: 'Durango',
  referencias: null,
  latitud: 24.0277,
  longitud: -104.6532,
};

const UBICACION = { latitude: 24.0277, longitude: -104.6532 };

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const gps = Location as unknown as typeof import('../../__mocks__/expo-location');
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
let anuncio: jest.SpyInstance;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

type Respuesta = ReturnType<typeof respuesta>;
interface Servidor {
  colonias?: (codigoPostal: string) => Respuesta | Promise<Respuesta>;
  alta?: () => Respuesta;
}

// El servidor falso: reconoce el token, da los municipios y las colonias de cada código postal, y guarda la dirección.
function servidor({
  colonias = (codigoPostal) => respuesta(200, { codigo_postal: codigoPostal, colonias: COLONIAS[codigoPostal] ?? [] }),
  alta = () => respuesta(201, { direccion: GUARDADA }),
}: Servidor = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/catalogos/municipios' && metodo === 'GET') {
      return respuesta(200, { municipios: MUNICIPIOS });
    }
    const deColonias = /^\/codigos-postales\/([^/]+)\/colonias$/.exec(ruta);
    if (deColonias && metodo === 'GET') {
      return colonias(decodeURIComponent(deColonias[1]));
    }
    if (ruta === '/direcciones' && metodo === 'POST') {
      return alta();
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Las peticiones de colonias, con el código postal que pidieron y sus cabeceras.
function pedidasDeColonias(): { codigo: string; opciones: RequestInit }[] {
  return fetchFalso.mock.calls.flatMap(([url, opciones]: [string, RequestInit]) => {
    const encontrado = new RegExp(`^${BASE}/codigos-postales/([^/]+)/colonias$`).exec(url);
    return encontrado ? [{ codigo: encontrado[1], opciones }] : [];
  });
}

function altas(): Record<string, unknown>[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/direcciones` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => JSON.parse(String(opciones.body)));
}

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
  await act(() => router.push('/pedido/direccion-nueva'));
  await waitFor(() => expect(screen).toHavePathname('/pedido/direccion-nueva'));
  await screen.findByText('Nueva dirección');
  await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

async function escribirElCodigoPostal(codigo: string) {
  await escribir('Código postal (opcional)', codigo);
}

function campoDeLaColonia() {
  return screen.getByRole('button', { name: 'Colonia' });
}

// Espera a que lleguen las colonias del código postal, con el campo de la colonia como lista.
async function esperarLaListaDeColonias() {
  await waitFor(() => expect(campoDeLaColonia()).toBeOnTheScreen());
}

function hojaDeColonias() {
  return within(screen.getByTestId('hoja-colonia'));
}

async function elegirColonia(nombre: string) {
  await fireEvent.press(campoDeLaColonia());
  await fireEvent.press(hojaDeColonias().getByRole('radio', { name: nombre }));
}

async function elegirMunicipio(texto: string, etiqueta = 'Municipio') {
  await fireEvent.press(screen.getByRole('button', { name: etiqueta }));
  await fireEvent.press(within(screen.getByTestId('hoja-municipio')).getByRole('radio', { name: texto }));
}

function municipioElegido(etiqueta = 'Municipio') {
  return screen.getByRole('button', { name: etiqueta });
}

async function escribirCalleYNumero() {
  await escribir('Calle', GUARDADA.calle);
  await escribir('Número', GUARDADA.numero_exterior);
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
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba direcciones-codigo-postal';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
  anuncio = jest.spyOn(AccessibilityInfo, 'announceForAccessibilityWithOptions').mockImplementation(() => {});
  // El preset de React Native ya la simula: spyOn devuelve la misma, con las llamadas de las pruebas anteriores.
  anuncio.mockClear();
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

describe('direcciones-E20: el código postal se pide, es opcional y son cinco números', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E20 — (%s) el formulario pide el código postal, opcional, antes de la colonia, con el teclado de números y hasta cinco',
    async (plataforma) => {
      await abrirElFormulario(plataforma);

      const codigo = screen.getByLabelText('Código postal (opcional)');
      expect(codigo).toHaveDisplayValue('');
      expect(codigo.props.keyboardType).toBe('number-pad');
      expect(codigo.props.maxLength).toBe(5);
      const campos = screen.getAllByTestId(/^campo-/).map((campo) => campo.props.testID);
      expect(campos.indexOf('campo-codigo_postal')).toBe(campos.indexOf('campo-colonia') - 1);
    },
  );

  test('direcciones-E20 — en la dirección marcada en el mapa también se pide', async () => {
    await abrirElFormulario('android');

    await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));

    expect(await screen.findByTestId('mapa-para-marcar')).toBeOnTheScreen();
    expect(screen.getByLabelText('Código postal (opcional)')).toHaveDisplayValue('');
  });

  test('direcciones-E20 — el campo solo guarda números, hasta cinco', async () => {
    await abrirElFormulario('ios');

    await escribirElCodigoPostal('34-0a0');
    expect(screen.getByLabelText('Código postal (opcional)')).toHaveDisplayValue('3400');
    await escribirElCodigoPostal('3500012');
    expect(screen.getByLabelText('Código postal (opcional)')).toHaveDisplayValue('35000');
  });

  test('direcciones-E20 — antes de los cinco números no se piden colonias', async () => {
    await abrirElFormulario('android');

    await escribirElCodigoPostal('3400');

    expect(pedidasDeColonias()).toHaveLength(0);
    expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('');
  });

  test.each(PLATAFORMAS)(
    'direcciones-E20 — (%s) un código postal que el servidor rechaza muestra el error junto a su campo y no se guarda nada',
    async (plataforma) => {
      const mensaje = 'El código postal son cinco números.';
      servidor({ alta: () => respuesta(422, { message: mensaje, errors: { codigo_postal: [mensaje] } }) });
      await abrirElFormulario(plataforma);
      await escribirCalleYNumero();
      await escribirElCodigoPostal('340');
      await escribir('Colonia', GUARDADA.colonia);
      await elegirMunicipio('Durango, Durango');

      await guardar();

      expect(await within(screen.getByTestId('campo-codigo_postal')).findByRole('alert')).toHaveTextContent(mensaje);
      expect(screen.getAllByText(mensaje)).toHaveLength(1);
      expect(altas()[0]).toMatchObject({ codigo_postal: '340' });
      expect(screen).toHavePathname('/pedido/direccion-nueva');
      expect(leerPedidoEnCurso()?.agregadas).toBeUndefined();
    },
  );
});

describe('direcciones-E19: las colonias de un código postal', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E19 — (%s) al escribir los cinco números, la app pide sus colonias con la sesión y las ofrece en una lista, por nombre',
    async (plataforma) => {
      await abrirElFormulario(plataforma);

      await escribirElCodigoPostal(DEL_PRIMERO);

      await esperarLaListaDeColonias();
      expect(pedidasDeColonias()).toHaveLength(1);
      const [{ codigo, opciones }] = pedidasDeColonias();
      expect(codigo).toBe(DEL_PRIMERO);
      expect(opciones.method).toBe('GET');
      expect((opciones.headers as Record<string, string>).Authorization).toBe(`Bearer ${TOKEN}`);
      expect(campoDeLaColonia()).toHaveAccessibilityValue({ text: 'Elige tu colonia' });
      await fireEvent.press(campoDeLaColonia());
      expect(hojaDeColonias().getAllByRole('radio').map((opcion) => opcion.props.accessibilityLabel)).toEqual([
        'Colonia de prueba centro, Colonia',
        'Fraccionamiento de prueba, Fraccionamiento',
      ]);
      expect(hojaDeColonias().getByLabelText('Buscar colonia')).toBeOnTheScreen();
      expect(hojaDeColonias().getByRole('button', { name: 'No aparece mi colonia' })).toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E19 — (%s) al elegir una, la colonia queda con el nombre del catálogo y, sin municipio elegido, queda elegido el suyo; al guardar viajan los tres',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirCalleYNumero();
      await escribirElCodigoPostal(DEL_PRIMERO);
      await esperarLaListaDeColonias();

      await elegirColonia('Colonia de prueba centro, Colonia');

      expect(campoDeLaColonia()).toHaveAccessibilityValue({ text: 'Colonia de prueba centro' });
      expect(municipioElegido()).toHaveAccessibilityValue({ text: 'Durango, Durango' });
      // El lector de pantalla oye la colonia y después el municipio que se eligió con ella.
      expect(anuncio.mock.calls).toEqual([
        ['Colonia: Colonia de prueba centro', { queue: true }],
        ['Municipio: Durango, Durango', { queue: true }],
      ]);
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toEqual({
        origen: 'escrita',
        calle: GUARDADA.calle,
        numero_exterior: GUARDADA.numero_exterior,
        codigo_postal: DEL_PRIMERO,
        colonia: 'Colonia de prueba centro',
        municipio: '10005',
        es_favorita: false,
      });
      await waitFor(() => expect(screen).toHavePathname('/pedido/servicio'));
      expect(leerPedidoEnCurso()?.agregadas).toEqual([{ direccion: GUARDADA, para: 'recogida' }]);
    },
  );

  test('direcciones-E19 — si la persona ya había elegido el municipio, elegir la colonia no lo cambia', async () => {
    await abrirElFormulario('ios');
    await elegirMunicipio('Torreón, Coahuila de Zaragoza');
    await escribirElCodigoPostal(DEL_PRIMERO);
    await esperarLaListaDeColonias();

    await elegirColonia('Fraccionamiento de prueba, Fraccionamiento');

    expect(campoDeLaColonia()).toHaveAccessibilityValue({ text: 'Fraccionamiento de prueba' });
    expect(municipioElegido()).toHaveAccessibilityValue({ text: 'Torreón, Coahuila de Zaragoza' });
    expect(anuncio).not.toHaveBeenCalledWith('Municipio: Durango, Durango', { queue: true });
  });

  test('direcciones-E19 — al cambiar el código postal, la colonia elegida de la lista se quita, y otra colonia cambia el municipio que la app eligió', async () => {
    await abrirElFormulario('android');
    await escribirElCodigoPostal(DEL_PRIMERO);
    await esperarLaListaDeColonias();
    await elegirColonia('Colonia de prueba centro, Colonia');

    await escribirElCodigoPostal(DEL_SEGUNDO);

    // La colonia era de otro código postal: se quita, y la lista nueva se ofrece sin elegir.
    await waitFor(() => expect(pedidasDeColonias().map(({ codigo }) => codigo)).toEqual([DEL_PRIMERO, DEL_SEGUNDO]));
    await waitFor(() => expect(campoDeLaColonia()).toHaveAccessibilityValue({ text: 'Elige tu colonia' }));
    // El municipio lo eligió la app con la colonia, no la persona: la colonia nueva lo cambia.
    await elegirColonia('Colonia de prueba norte, Colonia');
    expect(municipioElegido()).toHaveAccessibilityValue({ text: 'Gómez Palacio, Durango' });
  });

  test.each(PLATAFORMAS)(
    'direcciones-E20 — (%s) si su colonia no aparece, "No aparece mi colonia" deja escribirla con lo que buscó, y se guarda tal como la escribió',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirCalleYNumero();
      await escribirElCodigoPostal(DEL_PRIMERO);
      await esperarLaListaDeColonias();
      await elegirMunicipio('Durango, Durango');
      await fireEvent.press(campoDeLaColonia());
      await fireEvent.changeText(hojaDeColonias().getByLabelText('Buscar colonia'), 'Colonia nueva de prueba');

      await fireEvent.press(hojaDeColonias().getByRole('button', { name: 'No aparece mi colonia' }));

      expect(screen.queryByTestId('hoja-colonia')).not.toBeOnTheScreen();
      expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('Colonia nueva de prueba');
      await escribir('Colonia', 'Colonia escrita de prueba');
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toMatchObject({ codigo_postal: DEL_PRIMERO, colonia: 'Colonia escrita de prueba' });
    },
  );

  test('direcciones-E20 — desde la colonia escrita a mano, "Elegir de la lista de colonias" vuelve a la lista', async () => {
    await abrirElFormulario('ios');
    await escribirElCodigoPostal(DEL_PRIMERO);
    await esperarLaListaDeColonias();
    await fireEvent.press(campoDeLaColonia());
    await fireEvent.press(hojaDeColonias().getByRole('button', { name: 'No aparece mi colonia' }));

    await fireEvent.press(screen.getByRole('link', { name: 'Elegir de la lista de colonias' }));

    await elegirColonia('Fraccionamiento de prueba, Fraccionamiento');
    expect(campoDeLaColonia()).toHaveAccessibilityValue({ text: 'Fraccionamiento de prueba' });
    expect(screen.queryByRole('link', { name: 'Elegir de la lista de colonias' })).not.toBeOnTheScreen();
  });

  test('direcciones-E20 — una colonia escrita antes del código postal se queda escrita, y la lista se ofrece aparte', async () => {
    await abrirElFormulario('android');
    await escribir('Colonia', 'Colonia escrita de prueba');

    await escribirElCodigoPostal(DEL_PRIMERO);

    expect(await screen.findByRole('link', { name: 'Elegir de la lista de colonias' })).toBeOnTheScreen();
    expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('Colonia escrita de prueba');
  });

  test.each(PLATAFORMAS)(
    'direcciones-E19 — (%s) en la dirección marcada en el mapa también se elige la colonia de la lista, con su municipio',
    async (plataforma) => {
      servidor({ alta: () => respuesta(201, { direccion: { ...GUARDADA, calle: null, numero_exterior: null } }) });
      await abrirElFormulario(plataforma);
      await fireEvent.press(screen.getByRole('tab', { name: 'En el mapa' }));
      await screen.findByTestId('mapa-para-marcar');
      await escribirElCodigoPostal(DEL_PRIMERO);
      await esperarLaListaDeColonias();

      await elegirColonia('Colonia de prueba centro, Colonia');
      expect(municipioElegido('Municipio (opcional)')).toHaveAccessibilityValue({ text: 'Durango, Durango' });
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toMatchObject({
        origen: 'mapa',
        codigo_postal: DEL_PRIMERO,
        colonia: 'Colonia de prueba centro',
        municipio: '10005',
      });
    },
  );
});

describe('direcciones-E20: un código postal sin catálogo se escribe a mano', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E20 — (%s) sin colonias para ese código postal, lo dice junto al campo, deja escribirla y se guarda tal como vino',
    async (plataforma) => {
      await abrirElFormulario(plataforma);
      await escribirCalleYNumero();
      await elegirMunicipio('Durango, Durango');

      await escribirElCodigoPostal(SIN_CATALOGO);

      expect(
        await screen.findByText('No encontramos colonias con ese código postal. Escríbela.'),
      ).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Colonia' })).not.toBeOnTheScreen();
      await escribir('Colonia', 'Colonia escrita de prueba');
      await guardar();

      await waitFor(() => expect(altas()).toHaveLength(1));
      expect(altas()[0]).toEqual({
        origen: 'escrita',
        calle: GUARDADA.calle,
        numero_exterior: GUARDADA.numero_exterior,
        codigo_postal: SIN_CATALOGO,
        colonia: 'Colonia escrita de prueba',
        municipio: '10005',
        es_favorita: false,
      });
    },
  );

  // Sin escenario propio: el catálogo ayuda y no bloquea (requisito "La colonia, por código postal").
  test('direcciones-E20 — si las colonias no llegan, lo dice y deja escribir la colonia', async () => {
    servidor({ colonias: () => Promise.reject(new TypeError('Network request failed')) });
    await abrirElFormulario('ios');

    await escribirElCodigoPostal(DEL_PRIMERO);

    expect(
      await screen.findByText('No pudimos buscar las colonias de ese código postal. Escríbela.'),
    ).toBeOnTheScreen();
    await escribir('Colonia', 'Colonia escrita de prueba');
    expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('Colonia escrita de prueba');
  });

  test('direcciones-E20 — mientras llegan las colonias, la colonia se puede escribir y dice que las está buscando', async () => {
    let responder: (valor: Respuesta) => void = () => {};
    servidor({ colonias: () => new Promise<Respuesta>((resolver) => (responder = resolver)) });
    await abrirElFormulario('android');

    await escribirElCodigoPostal(DEL_PRIMERO);

    expect(await screen.findByText('Buscando las colonias…')).toBeOnTheScreen();
    expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('');
    await act(async () => responder(respuesta(200, { codigo_postal: DEL_PRIMERO, colonias: COLONIAS[DEL_PRIMERO] })));
    await esperarLaListaDeColonias();
    expect(screen.queryByText('Buscando las colonias…')).not.toBeOnTheScreen();
  });
});

describe('direcciones-E02: la colonia sigue obligatoria aunque haya código postal', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E02 — (%s) con un código postal del catálogo y sin elegir ni escribir la colonia, la app no la llena y el error va junto a la colonia',
    async (plataforma) => {
      servidor({ alta: () => respuesta(422, { message: 'Falta la colonia.', errors: { colonia: ['Falta la colonia.'] } }) });
      await abrirElFormulario(plataforma);
      await escribirCalleYNumero();
      await elegirMunicipio('Durango, Durango');
      await escribirElCodigoPostal(DEL_PRIMERO);
      await esperarLaListaDeColonias();

      await guardar();

      expect(await within(screen.getByTestId('campo-colonia')).findByRole('alert')).toHaveTextContent(
        'Falta la colonia.',
      );
      expect(altas()).toHaveLength(1);
      expect(altas()[0]).not.toHaveProperty('colonia');
      expect(altas()[0]).toMatchObject({ codigo_postal: DEL_PRIMERO, municipio: '10005' });
      expect(campoDeLaColonia()).toHaveAccessibilityValue({ text: 'Elige tu colonia' });
      expect(screen).toHavePathname('/pedido/direccion-nueva');
    },
  );
});
