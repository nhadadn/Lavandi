// Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, perfil-usuario-E03 y E04 (tarea 2.2). La foto
// de perfil del Módulo A (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15, "foto"): en "Mi perfil", la
// persona toma una foto con la cámara o elige una de su galería; la app la comprime en el teléfono con los parámetros de
// configuración (ADR-0006; src/configuracion/fotos.ts) y la sube en multipart a POST /api/perfil/foto, con su sesión. El
// perfil muestra la foto que guardó el servidor, desde su URL firmada. Si la API la rechaza, el error va junto a la foto
// y la anterior se conserva.
//
// Que la foto quede en el almacenamiento privado, ligada a la cuenta, que la URL esté firmada y venza, y que la API
// rechace un archivo que no es imagen o que pasa el peso máximo, se prueba en el servidor (tarea 1.2,
// servidor/tests/Feature/PerfilUsuario/FotoDePerfilTest.php). Aquí el servidor va simulado con el contrato de design.md
// ("La foto de perfil") y guarda la foto como el real: la prueba comprueba lo que hace la app con lo que le contesta. Se
// abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android. La cámara, la galería, sus diálogos
// y el peso real con que sale la foto se prueban en un teléfono real.
import path from 'node:path';

import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
import permisosDeLaFoto from '../../src/alta-lavanderias/permisos-de-la-foto.json';
import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { COMPRESION_DE_FOTOS } from '../../src/configuracion/fotos';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';
import {
  contenidoDelArchivo,
  esFormulario,
  FormDataDelTelefono,
  leerFormulario,
  partesLeidas,
  type ParteDelFormulario,
} from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-foto';
const TOKEN = 'token-perfil-foto';
const USUARIO = { id_publico: '01JD0000000000000000000PF1', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

/** Una foto de la cámara de un teléfono, horizontal, más grande que el lado máximo de la configuración. */
const FOTO_DE_LA_CAMARA = { uri: 'file:///camara/perfil-1.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };
/** Una foto vertical de la galería, en el formato de iPhone, que el servidor no acepta tal cual. */
const FOTO_DE_LA_GALERIA = { uri: 'file:///galeria/perfil-2.heic', width: 3024, height: 4032, mimeType: 'image/heic' };
/** La foto que ya tiene la cuenta, como la firma el servidor. */
const FOTO_ANTERIOR = {
  url: 'https://fotos.prueba.invalid/usuarios/01jd0000000000000000000pf1/anterior.jpg?firma=1',
  vence_en: '2026-11-22T18:00:00Z',
};

// Los mensajes de la API para un archivo inválido (design.md, "La foto de perfil").
const NO_ES_IMAGEN = 'La foto debe ser una imagen: jpg, jpeg, png, webp.';
const PESA_DE_MAS = 'La foto pesa más de 5120 KB.';

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
const formDataOriginal = global.FormData;
let fetchFalso: jest.Mock;
// La foto que tiene guardada el servidor falso, y cuántas ha firmado.
let fotoGuardada: { url: string; vence_en: string } | null;
let firmas: number;
// Lo que una prueba pone en lugar de la respuesta de la subida: si devuelve algo, el servidor contesta eso.
let alSubir: (() => unknown) | null;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

function perfil() {
  return { id_publico: USUARIO.id_publico.toLowerCase(), nombre: 'Nombre de prueba', correo: USUARIO.correo, foto: fotoGuardada };
}

function tokenDe(opciones: RequestInit): string {
  const encabezados = (opciones.headers ?? {}) as Record<string, string>;
  return (encabezados.Authorization ?? '').replace(/^Bearer /, '');
}

// Las partes del formulario como las leyó el servidor, ya mandadas como en el teléfono (__tests__/apoyo/formulario.ts);
// null si el cuerpo no es un formulario.
function partesDe(cuerpo: unknown): ParteDelFormulario[] | null {
  return esFormulario(cuerpo) ? partesLeidas(cuerpo) : null;
}

// El servidor falso: reconoce el token, da el perfil con la foto que tiene y guarda la foto que llega como la API: la
// reemplaza y la devuelve con otra URL firmada.
function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    // Como en el teléfono: el formulario lo manda expo/fetch, y si no sabe mandarlo, la petición no sale.
    const partes = esFormulario(opciones.body) ? await leerFormulario(opciones.body) : [];
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (tokenDe(opciones) !== TOKEN) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/perfil' && metodo === 'GET') {
      return respuesta(200, { perfil: perfil() });
    }
    if (ruta === '/perfil/foto' && metodo === 'POST') {
      const otra = alSubir?.();
      if (otra !== undefined) {
        return otra;
      }
      const foto = partes.find((parte) => parte.campo === 'foto' && 'archivo' in parte);
      if (!foto || !('archivo' in foto)) {
        return respuesta(422, { message: 'Elige una foto.', errors: { foto: ['Elige una foto.'] } });
      }
      firmas += 1;
      fotoGuardada = {
        url: `https://fotos.prueba.invalid/usuarios/${encodeURIComponent(foto.archivo.contenido)}?firma=${firmas + 1}`,
        vence_en: '2026-11-22T19:00:00Z',
      };
      return respuesta(200, { perfil: perfil() });
    }
    if (ruta === '/direcciones/favoritas' && metodo === 'GET') {
      return respuesta(200, { direcciones: [] });
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Las subidas de la foto que mandó la app, con su token, si fueron multipart y sus partes.
function subidas(): { token: string; multipart: boolean; partes: ParteDelFormulario[] | null }[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/perfil/foto` && opciones?.method === 'POST')
    .map(([, opciones]: [string, RequestInit]) => ({
      token: tokenDe(opciones),
      multipart: esFormulario(opciones.body),
      partes: partesDe(opciones.body),
    }));
}

function consultasDelPerfil(): number {
  return fetchFalso.mock.calls.filter(
    ([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}/perfil` && (opciones?.method ?? 'GET') === 'GET',
  ).length;
}

// La persona con sesión abre su perfil y espera a que llegue.
async function abrirElPerfil(plataforma: Plataforma) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  const pantalla = await renderRouter(APP, { initialUrl: '/perfil' });
  await screen.findByRole('header', { name: 'Mi perfil' });
  await waitFor(() => expect(screen.queryByText('Cargando tu perfil…')).not.toBeOnTheScreen());
  return pantalla;
}

function fotoDePerfil() {
  return screen.queryByRole('image', { name: 'Tu foto de perfil' });
}

function campoDeLaFoto() {
  return within(screen.getByTestId('campo-foto'));
}

async function tomarLaFoto() {
  camara.__fotoDeLaCamara(FOTO_DE_LA_CAMARA);
  await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));
}

async function elegirLaFoto() {
  camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);
  await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
}

beforeEach(() => {
  almacen.__vaciar();
  camara.__reiniciar();
  manipulador.__reiniciar();
  fotoGuardada = null;
  firmas = 0;
  alSubir = null;
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-foto';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  // El FormData del teléfono: en Jest, el global es el de Node.
  global.FormData = FormDataDelTelefono;
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
  global.FormData = formDataOriginal;
  jest.restoreAllMocks();
});

describe('perfil-usuario-E03: el usuario pone su foto', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E03 — (%s) sin foto, toma una con la cámara: la app la comprime con la configuración, la sube en multipart con su sesión y muestra la que guardó el servidor, por su URL firmada',
    async (plataforma) => {
      await abrirElPerfil(plataforma);
      expect(fotoDePerfil()).not.toBeOnTheScreen();

      await tomarLaFoto();

      expect(await campoDeLaFoto().findByText('Guardamos tu foto.')).toBeOnTheScreen();
      // La cámara pide su permiso antes de abrirse, y solo toma fotos.
      expect(camara.requestCameraPermissionsAsync).toHaveBeenCalledTimes(1);
      expect(camara.launchCameraAsync).toHaveBeenCalledWith(expect.objectContaining({ mediaTypes: ['images'] }));
      // Se comprimió una vez, con los parámetros de configuración: el lado mayor al máximo y en JPEG.
      expect(manipulador.__preparaciones()).toEqual([
        {
          fuente: FOTO_DE_LA_CAMARA.uri,
          redimension: { width: COMPRESION_DE_FOTOS.ladoMaximoPx },
          guardado: { compress: COMPRESION_DE_FOTOS.calidad, format: 'jpeg' },
          resultado: manipulador.__preparada(FOTO_DE_LA_CAMARA.uri),
        },
      ]);
      // Sube solo la foto comprimida, en el campo "foto", con la sesión: la cuenta es la de la sesión.
      expect(subidas()).toEqual([
        {
          token: TOKEN,
          multipart: true,
          partes: [
            {
              campo: 'foto',
              archivo: {
                nombre: 'foto.jpg',
                tipo: 'image/jpeg',
                contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_CAMARA.uri)),
              },
            },
          ],
        },
      ]);
      // El perfil muestra la foto desde la URL firmada que devolvió el servidor, no desde el teléfono.
      expect(fotoDePerfil()).toHaveProp('source', { uri: fotoGuardada?.url });
    },
  );

  test.each(PLATAFORMAS)(
    'perfil-usuario-E03 — (%s) elige una de la galería: no pide el permiso de la cámara y la sube comprimida en JPEG',
    async (plataforma) => {
      await abrirElPerfil(plataforma);

      await elegirLaFoto();

      expect(await campoDeLaFoto().findByText('Guardamos tu foto.')).toBeOnTheScreen();
      expect(camara.requestCameraPermissionsAsync).not.toHaveBeenCalled();
      expect(camara.launchCameraAsync).not.toHaveBeenCalled();
      expect(camara.launchImageLibraryAsync).toHaveBeenCalledWith(expect.objectContaining({ mediaTypes: ['images'] }));
      // Vertical: el lado mayor es el alto. Del HEIC del teléfono sale un JPEG.
      expect(manipulador.__preparaciones()).toEqual([
        {
          fuente: FOTO_DE_LA_GALERIA.uri,
          redimension: { height: COMPRESION_DE_FOTOS.ladoMaximoPx },
          guardado: { compress: COMPRESION_DE_FOTOS.calidad, format: 'jpeg' },
          resultado: manipulador.__preparada(FOTO_DE_LA_GALERIA.uri),
        },
      ]);
      expect(subidas().map(({ partes }) => partes)).toEqual([
        [
          {
            campo: 'foto',
            archivo: {
              nombre: 'foto.jpg',
              tipo: 'image/jpeg',
              contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_GALERIA.uri)),
            },
          },
        ],
      ]);
      expect(fotoDePerfil()).toHaveProp('source', { uri: fotoGuardada?.url });
    },
  );

  test.each(PLATAFORMAS)(
    'perfil-usuario-E03 — (%s) con su foto puesta, el perfil la muestra desde su URL firmada; al volver a abrirlo, la pide otra vez y muestra la URL nueva',
    async (plataforma) => {
      fotoGuardada = FOTO_ANTERIOR;
      const pantalla = await abrirElPerfil(plataforma);
      expect(fotoDePerfil()).toHaveProp('source', { uri: FOTO_ANTERIOR.url });

      // Mientras tanto, la URL venció: el servidor firma otra para la misma foto.
      fotoGuardada = { url: FOTO_ANTERIOR.url.replace('firma=1', 'firma=renovada'), vence_en: '2026-11-22T19:30:00Z' };
      pantalla.unmount();
      await abrirElPerfil(plataforma);

      expect(consultasDelPerfil()).toBe(2);
      expect(fotoDePerfil()).toHaveProp('source', { uri: fotoGuardada.url });
    },
  );

  test('perfil-usuario-E03 — con una foto puesta, la nueva la reemplaza en el perfil', async () => {
    fotoGuardada = FOTO_ANTERIOR;
    await abrirElPerfil('android');

    await elegirLaFoto();

    await campoDeLaFoto().findByText('Guardamos tu foto.');
    expect(fotoDePerfil()).toHaveProp('source', { uri: fotoGuardada?.url });
    expect(fotoDePerfil()).not.toHaveProp('source', { uri: FOTO_ANTERIOR.url });
  });

  test('perfil-usuario-E03 — si la persona cierra la cámara o la galería sin foto, no sube nada', async () => {
    await abrirElPerfil('ios');

    await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));
    await waitFor(() => expect(camara.launchCameraAsync).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Elegir de la galería' })).toBeEnabled());
    await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
    await waitFor(() => expect(camara.launchImageLibraryAsync).toHaveBeenCalledTimes(1));

    await waitFor(() => expect(screen.getByRole('button', { name: 'Elegir de la galería' })).toBeEnabled());
    expect(manipulador.__preparaciones()).toEqual([]);
    expect(subidas()).toEqual([]);
    expect(campoDeLaFoto().queryByRole('alert')).not.toBeOnTheScreen();
    expect(fotoDePerfil()).not.toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'perfil-usuario-E03 — (%s) sin permiso de la cámara, lo dice junto a la foto, no sube nada y deja elegirla de la galería',
    async (plataforma) => {
      await abrirElPerfil(plataforma);
      camara.__respuestaAlPedirLaCamara(camara.PermissionStatus.DENIED);

      await tomarLaFoto();

      expect(await campoDeLaFoto().findByRole('alert')).toHaveTextContent(
        'Sin permiso de la cámara no podemos tomar la foto. Puedes elegirla de tu galería.',
      );
      expect(camara.launchCameraAsync).not.toHaveBeenCalled();
      expect(subidas()).toEqual([]);

      await elegirLaFoto();

      expect(await campoDeLaFoto().findByText('Guardamos tu foto.')).toBeOnTheScreen();
      expect(campoDeLaFoto().queryByRole('alert')).not.toBeOnTheScreen();
    },
  );

  test('perfil-usuario-E03 — mientras la foto se prepara y sube, no deja subir otra', async () => {
    let soltar: (valor: unknown) => void = () => {};
    alSubir = () => {
      alSubir = null;
      return new Promise((resolver) => {
        soltar = resolver;
      });
    };
    await abrirElPerfil('android');

    await tomarLaFoto();
    await waitFor(() => expect(subidas()).toHaveLength(1));

    expect(screen.getByRole('button', { name: 'Tomar foto' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Elegir de la galería' })).toBeDisabled();
    await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
    expect(camara.launchImageLibraryAsync).not.toHaveBeenCalled();

    fotoGuardada = { url: 'https://fotos.prueba.invalid/usuarios/nueva.jpg?firma=9', vence_en: '2026-11-22T19:00:00Z' };
    soltar(respuesta(200, { perfil: perfil() }));

    expect(await campoDeLaFoto().findByText('Guardamos tu foto.')).toBeOnTheScreen();
    expect(screen.getByRole('button', { name: 'Elegir de la galería' })).toBeEnabled();
    expect(subidas()).toHaveLength(1);
  });

  // Sin escenario propio: si la subida no llega al servidor, los mensajes generales de la app, y nada cambia.
  test('perfil-usuario-E03 — sin conexión, lo dice junto a la foto y la que había sigue', async () => {
    fotoGuardada = FOTO_ANTERIOR;
    alSubir = () => Promise.reject(new TypeError('Network request failed'));
    await abrirElPerfil('ios');

    await elegirLaFoto();

    expect(await campoDeLaFoto().findByRole('alert')).toHaveTextContent(MENSAJES.sinConexion);
    expect(fotoDePerfil()).toHaveProp('source', { uri: FOTO_ANTERIOR.url });
    expect(campoDeLaFoto().queryByText('Guardamos tu foto.')).not.toBeOnTheScreen();
  });

  // ADR-0006: la cámara y la galería piden su permiso con el texto de la app. El mismo texto sale en el diálogo de
  // iPhone (alta-lavanderias-E03 comprueba que la configuración de Expo lo pone en el Info.plist); como la cuenta de
  // usuario también toma su foto, el texto dice que es para las dos.
  test('perfil-usuario-E03 — la cámara y las fotos piden permiso con un texto que también nombra la foto de tu perfil', () => {
    expect(permisosDeLaFoto.camara).toMatch(/foto de tu perfil/);
    expect(permisosDeLaFoto.fotos).toMatch(/foto de tu perfil/);
  });
});

describe('perfil-usuario-E04: un archivo inválido se rechaza', () => {
  test.each(PLATAFORMAS.flatMap((plataforma) => [NO_ES_IMAGEN, PESA_DE_MAS].map((mensaje) => [plataforma, mensaje])))(
    'perfil-usuario-E04 — (%s) la API rechaza el archivo ("%s"): el error va junto a la foto y la anterior se conserva',
    async (plataforma, mensaje) => {
      fotoGuardada = FOTO_ANTERIOR;
      alSubir = () => respuesta(422, { message: mensaje, errors: { foto: [mensaje] } });
      const pantalla = await abrirElPerfil(plataforma as Plataforma);

      await elegirLaFoto();

      expect(await campoDeLaFoto().findByRole('alert')).toHaveTextContent(mensaje);
      expect(subidas()).toHaveLength(1);
      expect(fotoDePerfil()).toHaveProp('source', { uri: FOTO_ANTERIOR.url });
      expect(campoDeLaFoto().queryByText('Guardamos tu foto.')).not.toBeOnTheScreen();

      // Al volver a abrir el perfil, sigue la foto que había.
      pantalla.unmount();
      await abrirElPerfil(plataforma as Plataforma);
      expect(fotoDePerfil()).toHaveProp('source', { uri: FOTO_ANTERIOR.url });
    },
  );

  test.each(PLATAFORMAS)(
    'perfil-usuario-E04 — (%s) una foto que el teléfono no puede preparar, como una imagen dañada, no se sube: lo dice y la anterior se conserva',
    async (plataforma) => {
      fotoGuardada = FOTO_ANTERIOR;
      manipulador.__fallar(new Error('No se pudo leer la imagen.'));
      await abrirElPerfil(plataforma);

      await elegirLaFoto();

      expect(await campoDeLaFoto().findByRole('alert')).toHaveTextContent('No pudimos preparar la foto. Intenta con otra.');
      expect(subidas()).toEqual([]);
      expect(fotoDePerfil()).toHaveProp('source', { uri: FOTO_ANTERIOR.url });
    },
  );

  test('perfil-usuario-E04 — después del rechazo, otra foto se sube y el error se quita', async () => {
    fotoGuardada = FOTO_ANTERIOR;
    alSubir = () => {
      alSubir = null;
      return respuesta(422, { message: PESA_DE_MAS, errors: { foto: [PESA_DE_MAS] } });
    };
    await abrirElPerfil('android');
    await elegirLaFoto();
    await campoDeLaFoto().findByRole('alert');

    await tomarLaFoto();

    expect(await campoDeLaFoto().findByText('Guardamos tu foto.')).toBeOnTheScreen();
    expect(campoDeLaFoto().queryByRole('alert')).not.toBeOnTheScreen();
    expect(fotoDePerfil()).toHaveProp('source', { uri: fotoGuardada?.url });
  });
});
