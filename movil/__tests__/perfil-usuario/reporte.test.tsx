// Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, perfil-usuario-E11 y E12 (tarea 2.5). El
// formulario de reporte en la pantalla "Ayuda" del Módulo A (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A
// #16): una descripción y, si la persona llega desde uno de sus pedidos, ese pedido (POST /api/soporte/reportes). La app
// confirma que se recibió sin prometer un tiempo de respuesta. A quién le llega es perfil-usuario-E14, bloqueado por
// DD-108.
//
// Que el reporte quede guardado con el usuario, el pedido, la descripción y la fecha, que la API rechace la descripción
// vacía sin guardar nada (E12) y el pedido de otra cuenta (E13) se prueba en el servidor (tarea 1.4). Aquí el servidor
// va simulado: la prueba comprueba lo que manda la app y lo que hace con cada respuesta. Una cuenta sin pedidos ni
// direcciones con plaza manda su reporte como cualquier otra: el servidor lo guarda sin plaza (perfil-usuario-E17).
//
// No hay todavía una lista de pedidos en la app ni en la API: el pedido llega en la ruta, "/ayuda?pedido=<id_publico>",
// para que el seguimiento o el historial abran el reporte de ese pedido. Se abre la app real, con las rutas de
// movil/app/ (ADR-0017), en iPhone y en Android.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Linking, Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-reporte';
const TOKEN = 'token-perfil-reporte';
const USUARIO = { id_publico: '01JD0000000000000000000PR1', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const NUMERO = '5210000000001';
const PEDIDO = '01jd00000000000000000000p1';
const FOLIO = 'DGO-000123';
const DESCRIPCION = 'La camisa azul llegó con una mancha.';

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
let abrirEnlace: jest.SpyInstance;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

type Respuesta = ReturnType<typeof respuesta>;
interface CuerpoDelReporte {
  descripcion?: unknown;
  pedido?: unknown;
}

// Como la API: la descripción vacía o solo con espacios se rechaza; si no, se guarda y se devuelve.
function recibirComoLaApi({ descripcion, pedido }: CuerpoDelReporte): Respuesta {
  const limpia = typeof descripcion === 'string' ? descripcion.trim() : '';
  if (limpia === '') {
    return respuesta(422, { message: 'Cuéntanos qué pasó.', errors: { descripcion: ['Cuéntanos qué pasó.'] } });
  }
  return respuesta(201, {
    reporte: { descripcion: limpia, pedido: pedido ?? null, estado: 'recibido', recibido_en: '2026-12-03T18:30:00Z' },
  });
}

// El servidor falso: reconoce el token, da el número de soporte y recibe el reporte.
function servidor(reporte: (cuerpo: CuerpoDelReporte) => Respuesta = recibirComoLaApi) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/soporte' && metodo === 'GET') {
      return respuesta(200, { whatsapp: NUMERO });
    }
    if (ruta === '/soporte/reportes' && metodo === 'POST') {
      return reporte(JSON.parse(String(opciones.body)));
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Los reportes que mandó la app, con su token y su cuerpo.
function reportesMandados(): { token: string; cuerpo: unknown }[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/soporte/reportes` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => ({
      token: (opciones.headers as Record<string, string>).Authorization,
      cuerpo: JSON.parse(String(opciones.body)),
    }));
}

async function abrirAyuda(plataforma: Plataforma, ruta = '/ayuda') {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: ruta });
  await screen.findByRole('header', { name: 'Ayuda' });
  await waitFor(() => expect(screen.queryByText('Buscando el número de soporte…')).not.toBeOnTheScreen());
}

function reporte() {
  return within(screen.getByTestId('soporte-reporte'));
}

async function enviar(descripcion: string) {
  await fireEvent.changeText(reporte().getByLabelText('Cuéntanos qué pasó'), descripcion);
  await fireEvent.press(reporte().getByRole('button', { name: 'Enviar reporte' }));
}

// Palabras con las que una confirmación prometería un tiempo de respuesta.
const PROMESA_DE_TIEMPO = /\b(hora|horas|minuto|minutos|día|días|semana|pronto|breve|enseguida|en cuanto|\d+\s*h)\b/i;

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-reporte';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  // En las pruebas, Linking.openURL ya es una simulación de react-native que guarda las llamadas de todo el archivo:
  // se vacía antes de cada prueba.
  abrirEnlace = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
  abrirEnlace.mockClear();
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

describe('perfil-usuario-E11: el usuario envía un reporte', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E11 — (%s) con un pedido, manda la descripción y ese pedido, y confirma que se recibió sin prometer un tiempo de respuesta',
    async (plataforma) => {
      await abrirAyuda(plataforma, `/ayuda?pedido=${PEDIDO}&folio=${FOLIO}`);
      expect(reporte().getByText(`Sobre tu pedido ${FOLIO}`)).toBeOnTheScreen();

      await enviar(DESCRIPCION);

      expect(reportesMandados()).toEqual([{ token: `Bearer ${TOKEN}`, cuerpo: { descripcion: DESCRIPCION, pedido: PEDIDO } }]);
      const confirmacion = await reporte().findByText('Recibimos tu reporte. Gracias por avisarnos.');
      expect(confirmacion).toBeOnTheScreen();
      expect(screen.queryByText(PROMESA_DE_TIEMPO)).not.toBeOnTheScreen();
      // El campo queda vacío para otro reporte.
      expect(reporte().getByLabelText('Cuéntanos qué pasó')).toHaveDisplayValue('');
    },
  );

  test.each(PLATAFORMAS)('perfil-usuario-E11 — (%s) sin pedido, el reporte va sin pedido', async (plataforma) => {
    await abrirAyuda(plataforma);
    expect(reporte().queryByText(/Sobre tu pedido/)).not.toBeOnTheScreen();

    await enviar(DESCRIPCION);

    expect(reportesMandados().map(({ cuerpo }) => cuerpo)).toEqual([{ descripcion: DESCRIPCION, pedido: null }]);
    expect(await reporte().findByText('Recibimos tu reporte. Gracias por avisarnos.')).toBeOnTheScreen();
  });

  test('perfil-usuario-E11 — con el pedido y sin folio en la ruta, lo dice sin folio', async () => {
    await abrirAyuda('android', `/ayuda?pedido=${PEDIDO}`);

    expect(reporte().getByText('Sobre tu pedido')).toBeOnTheScreen();
    await enviar(DESCRIPCION);

    expect(reportesMandados().map(({ cuerpo }) => cuerpo)).toEqual([{ descripcion: DESCRIPCION, pedido: PEDIDO }]);
  });

  test('perfil-usuario-E11 — sin conexión, lo dice con el mensaje general y conserva lo que se escribió', async () => {
    servidor(() => {
      throw new TypeError('Network request failed');
    });
    await abrirAyuda('android');

    await enviar(DESCRIPCION);

    expect(
      await reporte().findByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(reporte().queryByText('Recibimos tu reporte. Gracias por avisarnos.')).not.toBeOnTheScreen();
    expect(reporte().getByLabelText('Cuéntanos qué pasó')).toHaveDisplayValue(DESCRIPCION);
  });

  test('perfil-usuario-E13 (en la app) — un pedido que no es de la cuenta (404): no confirma y lo dice con el mensaje general', async () => {
    servidor(() => respuesta(404, { message: 'No existe el pedido.' }));
    await abrirAyuda('ios', `/ayuda?pedido=${PEDIDO}`);

    await enviar(DESCRIPCION);

    expect(await reporte().findByText('Algo salió mal. Intenta de nuevo.')).toBeOnTheScreen();
    expect(reporte().queryByText('Recibimos tu reporte. Gracias por avisarnos.')).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'perfil-usuario-E17 — (%s) una cuenta sin pedidos ni direcciones con plaza manda su reporte y la app confirma que se recibió, sin mandarla al WhatsApp',
    async (plataforma) => {
      await abrirAyuda(plataforma);

      await enviar(DESCRIPCION);

      expect(reportesMandados().map(({ cuerpo }) => cuerpo)).toEqual([{ descripcion: DESCRIPCION, pedido: null }]);
      expect(await reporte().findByText('Recibimos tu reporte. Gracias por avisarnos.')).toBeOnTheScreen();
      // El formulario no tiene su propio botón de WhatsApp: el chat vive en su sección (perfil-usuario-E09).
      expect(reporte().queryByRole('button', { name: 'Escríbenos por WhatsApp' })).not.toBeOnTheScreen();
      expect(abrirEnlace).not.toHaveBeenCalled();
    },
  );
});

describe('perfil-usuario-E12: un reporte sin descripción se rechaza', () => {
  test.each(
    PLATAFORMAS.flatMap((plataforma) => [
      [plataforma, 'vacía', ''],
      [plataforma, 'solo con espacios', '   '],
    ]),
  )(
    'perfil-usuario-E12 — (%s) con la descripción %s, la API lo rechaza, el error va junto al campo y no confirma nada',
    async (plataforma, _caso, vacia) => {
      await abrirAyuda(plataforma as Plataforma, `/ayuda?pedido=${PEDIDO}`);

      await enviar(vacia);

      // La app no lo filtra: lo manda y es la API la que lo rechaza.
      expect(reportesMandados().map(({ cuerpo }) => cuerpo)).toEqual([{ descripcion: vacia, pedido: PEDIDO }]);
      expect(await within(reporte().getByTestId('campo-descripcion')).findByRole('alert')).toHaveTextContent(
        'Cuéntanos qué pasó.',
      );
      expect(reporte().queryByText('Recibimos tu reporte. Gracias por avisarnos.')).not.toBeOnTheScreen();
    },
  );

  test('perfil-usuario-E12 — después del error, con la descripción escrita, el reporte se envía', async () => {
    await abrirAyuda('android');
    await enviar('');
    await within(reporte().getByTestId('campo-descripcion')).findByRole('alert');

    await enviar(DESCRIPCION);

    expect(await reporte().findByText('Recibimos tu reporte. Gracias por avisarnos.')).toBeOnTheScreen();
    expect(within(reporte().getByTestId('campo-descripcion')).queryByRole('alert')).not.toBeOnTheScreen();
  });
});
