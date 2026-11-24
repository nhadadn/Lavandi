// Spec: openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md, perfil-usuario-E01, E02 y E05 (tarea 2.1). La
// pantalla "Mi perfil" del Módulo A (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15): el nombre, que se
// cambia y se guarda con PATCH /api/perfil; el correo, solo para verlo; y las direcciones guardadas, que son las
// favoritas de la cuenta (GET /api/direcciones/favoritas, del cambio direcciones). La entrada es "Mi perfil" en la
// pantalla principal.
//
// Que la API rechace el nombre vacío y conserve el anterior, y que cada cuenta vea solo sus direcciones, se prueba en el
// servidor (tareas 1.1 y direcciones). Aquí el servidor va simulado y guarda el nombre como el real: la prueba
// comprueba lo que hace la app con lo que le contesta. Se abre la app real, con las rutas de movil/app/ (ADR-0017), en
// iPhone y en Android.
//
// El "Y" de perfil-usuario-E05, abrir la edición de cada dirección, no tiene pantalla todavía: ni el cambio direcciones
// ni el servidor tienen la edición de una dirección guardada. La prueba no la cubre.
import path from 'node:path';

import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.resolve(__dirname, '..', '..', 'app');
const BASE = 'http://api.prueba.invalid/perfil-usuario';
const TOKEN = 'token-perfil-usuario';
// La sesión de otra cuenta, con su propia dirección guardada.
const OTRO_TOKEN = 'token-perfil-de-otra-cuenta';
const USUARIO = { id_publico: '01JD0000000000000000000PU1', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PLATAFORMAS = ['ios', 'android'] as const;
type Plataforma = (typeof PLATAFORMAS)[number];

const NOMBRE = 'Nombre de prueba';

// Las favoritas de la cuenta, como las devuelve el servidor, y la de la otra cuenta.
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
  ...CASA,
  id_publico: '01jd00000000000000000000o1',
  alias: 'Oficina',
  calle: 'Avenida de prueba',
  numero_exterior: '456',
  numero_interior: '2',
  colonia: 'Otra colonia de prueba',
  referencias: null,
};
const AJENA = {
  ...CASA,
  id_publico: '01jd00000000000000000000a1',
  alias: 'Casa de otra cuenta',
  calle: 'Calle ajena',
};
const FAVORITAS_POR_TOKEN: Record<string, unknown[]> = {
  [TOKEN]: [CASA, OFICINA],
  [OTRO_TOKEN]: [AJENA],
};

// La simulación de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
// El nombre que tiene guardado el servidor falso.
let nombreGuardado: string | null;

function respuesta(status: number, cuerpo: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

function perfil() {
  return { id_publico: USUARIO.id_publico.toLowerCase(), nombre: nombreGuardado, correo: USUARIO.correo, foto: null };
}

function tokenDe(opciones: RequestInit): string {
  const encabezados = (opciones.headers ?? {}) as Record<string, string>;
  return (encabezados.Authorization ?? '').replace(/^Bearer /, '');
}

// El servidor falso: reconoce los dos tokens, da el perfil, cambia el nombre como la API (sin los espacios de los
// lados; vacío, lo rechaza y conserva el anterior) y da las favoritas de cada cuenta.
function servidor({ favoritas }: { favoritas?: () => ReturnType<typeof respuesta> } = {}) {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    if (ruta === '/perfil' && metodo === 'GET') {
      return respuesta(200, { perfil: perfil() });
    }
    if (ruta === '/perfil' && metodo === 'PATCH') {
      const { nombre } = JSON.parse(String(opciones.body)) as { nombre?: unknown };
      const limpio = typeof nombre === 'string' ? nombre.trim() : '';
      if (limpio === '') {
        return respuesta(422, { message: 'Escribe tu nombre.', errors: { nombre: ['Escribe tu nombre.'] } });
      }
      nombreGuardado = limpio;
      return respuesta(200, { perfil: perfil() });
    }
    if (ruta === '/direcciones/favoritas' && metodo === 'GET') {
      return favoritas ? favoritas() : respuesta(200, { direcciones: FAVORITAS_POR_TOKEN[tokenDe(opciones)] ?? [] });
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  });
}

// Las peticiones a una ruta con un método, con su token y su cuerpo leído.
function peticiones(ruta: string, metodo: string): { token: string; cuerpo: unknown }[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit | undefined]) => url === `${BASE}${ruta}` && (opciones?.method ?? 'GET') === metodo)
    .map(([, opciones]: [string, RequestInit]) => ({
      token: tokenDe(opciones),
      cuerpo: opciones.body === undefined ? undefined : JSON.parse(String(opciones.body)),
    }));
}

async function conSesion(plataforma: Plataforma, token = TOKEN) {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(token);
}

// La persona con sesión abre su perfil y espera a que lleguen el perfil y sus direcciones.
async function abrirElPerfil(plataforma: Plataforma, token = TOKEN) {
  await conSesion(plataforma, token);
  const pantalla = await renderRouter(APP, { initialUrl: '/perfil' });
  await screen.findByRole('header', { name: 'Mi perfil' });
  await waitFor(() => expect(screen.queryByText('Cargando tu perfil…')).not.toBeOnTheScreen());
  await waitFor(() => expect(screen.queryByText('Cargando tus direcciones…')).not.toBeOnTheScreen());
  return pantalla;
}

function campoDelNombre() {
  return screen.getByLabelText('Nombre');
}

async function guardarElNombre(nombre: string) {
  await fireEvent.changeText(campoDelNombre(), nombre);
  await fireEvent.press(screen.getByRole('button', { name: 'Guardar nombre' }));
}

beforeEach(() => {
  almacen.__vaciar();
  nombreGuardado = NOMBRE;
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba perfil-usuario';
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

describe('perfil-usuario-E01: el usuario cambia su nombre', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E01 — (%s) "Mi perfil" en la pantalla principal abre el perfil, con el nombre y el correo de la cuenta',
    async (plataforma) => {
      await conSesion(plataforma);
      await renderRouter(APP, { initialUrl: '/inicio' });

      await fireEvent.press(await screen.findByRole('link', { name: 'Mi perfil' }));

      await waitFor(() => expect(screen).toHavePathname('/perfil'));
      expect(await screen.findByRole('header', { name: 'Mi perfil' })).toBeOnTheScreen();
      await waitFor(() => expect(campoDelNombre()).toHaveDisplayValue(NOMBRE));
      expect(screen.getByText('persona@ejemplo.com')).toBeOnTheScreen();
      expect(peticiones('/perfil', 'GET')).toEqual([{ token: TOKEN, cuerpo: undefined }]);
    },
  );

  test.each(PLATAFORMAS)(
    'perfil-usuario-E01 — (%s) al guardar el nombre nuevo, el perfil lo muestra y la siguiente consulta lo devuelve',
    async (plataforma) => {
      const pantalla = await abrirElPerfil(plataforma);

      await guardarElNombre('  Nombre nuevo de prueba  ');

      // La app manda lo que se escribió; el servidor quita los espacios de los lados y el perfil muestra lo que guardó.
      expect(peticiones('/perfil', 'PATCH')).toEqual([{ token: TOKEN, cuerpo: { nombre: '  Nombre nuevo de prueba  ' } }]);
      expect(await screen.findByText('Guardamos tu nombre.')).toBeOnTheScreen();
      expect(campoDelNombre()).toHaveDisplayValue('Nombre nuevo de prueba');

      // Al volver a abrir el perfil, la consulta trae el nombre nuevo.
      pantalla.unmount();
      await abrirElPerfil(plataforma);
      await waitFor(() => expect(campoDelNombre()).toHaveDisplayValue('Nombre nuevo de prueba'));
    },
  );

  test('perfil-usuario-E01 — una cuenta sin nombre todavía (el registro no lo pide) abre el campo vacío', async () => {
    nombreGuardado = null;
    await abrirElPerfil('android');

    expect(campoDelNombre()).toHaveDisplayValue('');
    expect(screen.getByText('persona@ejemplo.com')).toBeOnTheScreen();
  });

  test('perfil-usuario-E01 — el correo solo se ve: no es un campo y no viaja al guardar', async () => {
    await abrirElPerfil('ios');

    expect(screen.queryByLabelText('Correo')).not.toBeOnTheScreen();
    await guardarElNombre('Otro nombre de prueba');

    await screen.findByText('Guardamos tu nombre.');
    expect(peticiones('/perfil', 'PATCH').map(({ cuerpo }) => cuerpo)).toEqual([{ nombre: 'Otro nombre de prueba' }]);
  });

  test('perfil-usuario-E01 — si el perfil no llega, lo dice con el mensaje general y no ofrece guardar', async () => {
    fetchFalso.mockImplementation(async (url: string) => {
      const ruta = url.slice(BASE.length);
      if (ruta === '/cuenta') {
        return respuesta(200, { usuario: USUARIO });
      }
      if (ruta === '/direcciones/favoritas') {
        return respuesta(200, { direcciones: [] });
      }
      throw new TypeError('Network request failed');
    });
    await conSesion('ios');
    await renderRouter(APP, { initialUrl: '/perfil' });

    expect(
      await screen.findByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Guardar nombre' })).not.toBeOnTheScreen();
  });
});

describe('perfil-usuario-E02: un nombre vacío se rechaza', () => {
  test.each(
    PLATAFORMAS.flatMap((plataforma) => [
      [plataforma, 'vacío', ''],
      [plataforma, 'solo con espacios', '   '],
    ]),
  )('perfil-usuario-E02 — (%s) %s: la API lo rechaza, el error va junto al campo y el nombre anterior se conserva', async (plataforma, _caso, vacio) => {
    const pantalla = await abrirElPerfil(plataforma as Plataforma);

    await guardarElNombre(vacio);

    // La app no lo filtra: lo manda y es la API la que lo rechaza.
    expect(peticiones('/perfil', 'PATCH').map(({ cuerpo }) => cuerpo)).toEqual([{ nombre: vacio }]);
    expect(await within(screen.getByTestId('campo-nombre')).findByRole('alert')).toHaveTextContent('Escribe tu nombre.');
    expect(screen.queryByText('Guardamos tu nombre.')).not.toBeOnTheScreen();

    // Al volver a abrir el perfil, sigue el nombre que había.
    pantalla.unmount();
    await abrirElPerfil(plataforma as Plataforma);
    await waitFor(() => expect(campoDelNombre()).toHaveDisplayValue(NOMBRE));
  });

  test('perfil-usuario-E02 — al escribir de nuevo después del error, se puede guardar', async () => {
    await abrirElPerfil('android');
    await guardarElNombre('');
    await within(screen.getByTestId('campo-nombre')).findByRole('alert');

    await guardarElNombre('Nombre corregido');

    expect(await screen.findByText('Guardamos tu nombre.')).toBeOnTheScreen();
    expect(within(screen.getByTestId('campo-nombre')).queryByRole('alert')).not.toBeOnTheScreen();
    expect(campoDelNombre()).toHaveDisplayValue('Nombre corregido');
  });
});

describe('perfil-usuario-E05: el perfil muestra sus direcciones guardadas', () => {
  test.each(PLATAFORMAS)(
    'perfil-usuario-E05 — (%s) el primero ve sus dos direcciones guardadas y no la del otro usuario',
    async (plataforma) => {
      await abrirElPerfil(plataforma);

      const guardadas = within(screen.getByTestId('direcciones-guardadas'));
      expect(guardadas.getByRole('header', { name: 'Direcciones guardadas' })).toBeOnTheScreen();
      expect(guardadas.getByText('Casa')).toBeOnTheScreen();
      expect(guardadas.getByText('Calle de prueba 123, Colonia de prueba')).toBeOnTheScreen();
      expect(guardadas.getByText('Oficina')).toBeOnTheScreen();
      expect(guardadas.getByText('Avenida de prueba 456, int. 2, Otra colonia de prueba')).toBeOnTheScreen();
      expect(guardadas.queryByText('Casa de otra cuenta')).not.toBeOnTheScreen();
      expect(guardadas.queryByText(/Calle ajena/)).not.toBeOnTheScreen();
      // Las pide con el token de su sesión: el servidor no recibe de quién son.
      expect(peticiones('/direcciones/favoritas', 'GET')).toEqual([{ token: TOKEN, cuerpo: undefined }]);
    },
  );

  test('perfil-usuario-E05 — el otro usuario, en su perfil, ve solo la suya', async () => {
    await abrirElPerfil('android', OTRO_TOKEN);

    const guardadas = within(screen.getByTestId('direcciones-guardadas'));
    expect(guardadas.getByText('Casa de otra cuenta')).toBeOnTheScreen();
    expect(guardadas.queryByText('Oficina')).not.toBeOnTheScreen();
    expect(guardadas.queryByText(/^Casa$/)).not.toBeOnTheScreen();
  });

  test('perfil-usuario-E05 — sin direcciones guardadas, lo dice', async () => {
    servidor({ favoritas: () => respuesta(200, { direcciones: [] }) });
    await abrirElPerfil('ios');

    expect(
      within(screen.getByTestId('direcciones-guardadas')).getByText('Todavía no tienes direcciones guardadas.'),
    ).toBeOnTheScreen();
  });

  test('perfil-usuario-E05 — si las direcciones no llegan, lo dice con el mensaje general y el nombre se sigue viendo', async () => {
    servidor({ favoritas: () => respuesta(500, { message: 'Server Error' }) });
    await abrirElPerfil('android');

    expect(
      within(screen.getByTestId('direcciones-guardadas')).getByText('Algo salió mal. Intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(campoDelNombre()).toHaveDisplayValue(NOMBRE);
  });
});
