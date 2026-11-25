// Spec: openspec/changes/avisos/specs/avisos/spec.md, avisos-E03 y avisos-E17 (tarea 4.1), con la parte de la app de
// avisos-E05. La app recibe los avisos con @react-native-firebase/messaging en el development build (ADR-0008 y su
// adenda): manda a la api el token de Firebase del teléfono, con su plataforma, al iniciar sesión y cada vez que Firebase
// lo renueva (POST /api/dispositivos, design.md de avisos, "Contrato de la API para la app"); lo manda al cerrar la
// sesión para que la api lo borre (DELETE /api/sesion); y registra al arrancar el manejador de los mensajes que llegan
// con la app al fondo o cerrada.
//
// Mientras no existan las llaves de Firebase (DD-072), la app se compila y abre sin ellas: el archivo de Firebase entra a
// la configuración solo si el entorno lo trae, y los módulos nativos de Firebase solo se enlazan si la compilación lo
// lleva. En iPhone no entra todavía (avisos-E18, tarea 5.2: DD-069, DD-017 y DD-084). Expo Go, que no trae el módulo
// nativo, lo prueba base-E11 (__tests__/base/expo-go.test.tsx).
//
// La mensajería es la simulación de __mocks__/@react-native-firebase/messaging.ts. Que el aviso aparezca de verdad en
// un Android con la app cerrada (avisos-E17) se acredita con la captura de vault/evidencias/avisos/, que espera las
// llaves.
import { spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import * as Mensajeria from '@react-native-firebase/messaging';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import { userEvent } from '@testing-library/react-native';
import { fireEvent, renderRouter, screen, waitFor } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';
import {
  configuracionDeExpo,
  entornoMinimo,
  leerConfiguracion,
  nombresDeLosComplementos,
  permisosDelManifiesto,
  RAIZ,
  type ConfiguracionLeida,
  type Lectura,
} from '../apoyo/configuracion';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const APP = path.join(RAIZ, 'app');
const BASE = 'http://api.prueba.invalid/avisos-e03';
const TOKEN_GUARDADO = 'token-de-sesion-avisos-e03';
const TOKEN_NUEVO = 'token-de-sesion-nueva-avisos-e03';
const USUARIO = { id_publico: '01JD00000000000000000AV003', correo: 'persona@ejemplo.com', rol: 'usuario' };
const PERMISO_DE_AVISOS = 'android.permission.POST_NOTIFICATIONS';
const MODULOS_DE_FIREBASE = ['@react-native-firebase/app', '@react-native-firebase/messaging'];

// La simulación de __mocks__/, la misma instancia que importa la app.
const mensajeria = Mensajeria as unknown as typeof import('../../__mocks__/@react-native-firebase/messaging');
const almacen = SecureStore as unknown as { __vaciar(): void };

// Lo que app.config.ts le dice a la app sobre la compilación. En Jest, Constants.expoConfig es un objeto vacío que se
// puede completar.
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };

const entornoOriginal = {
  EXPO_PUBLIC_API_URL: process.env.EXPO_PUBLIC_API_URL,
  EXPO_PUBLIC_NOMBRE_APP: process.env.EXPO_PUBLIC_NOMBRE_APP,
};
const fetchOriginal = global.fetch;
let fetchFalso: jest.Mock;
let rolDeLaCuenta = USUARIO.rol;

function respuesta(status: number, cuerpo?: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

// El servidor falso: reconoce el token, abre la sesión con el correo y acepta el registro y el cierre.
function servidor() {
  fetchFalso.mockImplementation(async (url: string, opciones: RequestInit = {}) => {
    const ruta = url.slice(BASE.length);
    const metodo = opciones.method ?? 'GET';
    if (ruta === '/cuenta') {
      return respuesta(200, { usuario: { ...USUARIO, rol: rolDeLaCuenta } });
    }
    if (ruta === '/sesion' && metodo === 'POST') {
      return respuesta(200, { token: TOKEN_NUEVO, usuario: USUARIO });
    }
    if ((ruta === '/sesion' && metodo === 'DELETE') || (ruta === '/dispositivos' && metodo === 'POST')) {
      return respuesta(204);
    }
    if (ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    return respuesta(404, { message: 'No existe.' });
  });
}

interface Peticion {
  autorizacion: string | undefined;
  cuerpo: unknown;
}

function peticionesA(ruta: string, metodo: string): Peticion[] {
  return fetchFalso.mock.calls
    .filter(([url, opciones]: [string, RequestInit]) => url === `${BASE}${ruta}` && opciones?.method === metodo)
    .map(([, opciones]: [string, RequestInit]) => ({
      autorizacion: (opciones.headers as Record<string, string>).Authorization,
      cuerpo: opciones.body === undefined ? undefined : JSON.parse(opciones.body as string),
    }));
}

const registros = () => peticionesA('/dispositivos', 'POST');

function compilacionConFirebase(trae: boolean) {
  configuracionDeLaApp.extra = { firebaseEnAndroid: trae };
}

async function conSesionGuardada() {
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN_GUARDADO);
}

// Hasta que la app comprobó la sesión con el servidor y mostró la primera pantalla del módulo.
async function esperarLaSesion() {
  await waitFor(() => expect(peticionesA('/cuenta', 'GET').length).toBeGreaterThan(0));
  await waitFor(() => expect(screen.queryByTestId('leyendo-la-sesion')).not.toBeOnTheScreen());
}

beforeEach(() => {
  almacen.__vaciar();
  mensajeria.__reiniciar();
  rolDeLaCuenta = USUARIO.rol;
  process.env.EXPO_PUBLIC_API_URL = BASE;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba avisos-E03';
  fetchFalso = jest.fn();
  global.fetch = fetchFalso as unknown as typeof fetch;
  servidor();
  // Un development build de Android con el archivo de Firebase; Android 12, que no pide el permiso de avisos.
  jest.replaceProperty(Platform, 'OS', 'android');
  jest.spyOn(Platform, 'Version', 'get').mockReturnValue(31);
  jest.replaceProperty(Constants, 'executionEnvironment', ExecutionEnvironment.Bare);
  compilacionConFirebase(true);
});

afterEach(() => {
  for (const [variable, original] of Object.entries(entornoOriginal)) {
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
  delete configuracionDeLaApp.extra;
  jest.restoreAllMocks();
});

describe('avisos-E03: la app registra el token del teléfono', () => {
  test('avisos-E03 — al abrir la app con la sesión y el permiso concedido, manda el token a la api con la plataforma', async () => {
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });

    await waitFor(() =>
      expect(registros()).toEqual([
        { autorizacion: `Bearer ${TOKEN_GUARDADO}`, cuerpo: { token_fcm: 'token-fcm-de-prueba', plataforma: 'android' } },
      ]),
    );
  });

  test('avisos-E03 — al iniciar sesión con el correo, manda el token del teléfono con la sesión nueva', async () => {
    await comprobarInstalacion();
    await marcarBienvenidaVista();
    const usuario = userEvent.setup();
    await renderRouter(APP, { initialUrl: '/inicio-de-sesion' });

    await usuario.type(await screen.findByLabelText('Correo electrónico'), 'persona@ejemplo.com');
    await usuario.type(screen.getByLabelText('Contraseña'), 'contrasena-de-prueba');
    await usuario.press(screen.getByRole('button', { name: 'Iniciar sesión' }));

    await waitFor(() => expect(screen).toHavePathname('/inicio'));
    await waitFor(() =>
      expect(registros()).toEqual([
        { autorizacion: `Bearer ${TOKEN_NUEVO}`, cuerpo: { token_fcm: 'token-fcm-de-prueba', plataforma: 'android' } },
      ]),
    );
  });

  test('avisos-E03 — cuando Firebase renueva el token, la app manda el nuevo', async () => {
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await waitFor(() => expect(registros()).toHaveLength(1));

    mensajeria.__renovarToken('token-fcm-renovado');

    await waitFor(() => expect(registros()).toHaveLength(2));
    expect(registros()[1]).toEqual({
      autorizacion: `Bearer ${TOKEN_GUARDADO}`,
      cuerpo: { token_fcm: 'token-fcm-renovado', plataforma: 'android' },
    });
  });

  test('avisos-E03 — sin el permiso de avisos, la app no manda el token, ni al abrir ni al renovarse', async () => {
    mensajeria.__permiso(mensajeria.AuthorizationStatus.DENIED);
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await esperarLaSesion();
    await waitFor(() => expect(mensajeria.hasPermission).toHaveBeenCalled());

    mensajeria.__renovarToken('token-fcm-renovado');

    await waitFor(() => expect(mensajeria.hasPermission).toHaveBeenCalledTimes(2));
    expect(registros()).toEqual([]);
  });

  test('avisos-E03 — una cuenta de lavandería no registra el teléfono: sus avisos esperan DD-054', async () => {
    rolDeLaCuenta = 'lavanderia';
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/' });
    await esperarLaSesion();

    expect(mensajeria.getMessaging).not.toHaveBeenCalled();
    expect(registros()).toEqual([]);
  });

  test('avisos-E03 — en una compilación sin el archivo de Firebase, la app abre y no carga la mensajería', async () => {
    compilacionConFirebase(false);
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await esperarLaSesion();

    expect(await screen.findByRole('button', { name: 'Pedido nuevo' })).toBeOnTheScreen();
    expect(mensajeria.getMessaging).not.toHaveBeenCalled();
    expect(registros()).toEqual([]);
  });

  test('avisos-E03 — en iPhone la app todavía no carga la mensajería: su configuración es avisos-E18 (DD-069)', async () => {
    jest.replaceProperty(Platform, 'OS', 'ios');
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await esperarLaSesion();

    expect(mensajeria.getMessaging).not.toHaveBeenCalled();
    expect(registros()).toEqual([]);
  });

  test('avisos-E03 — si Firebase no da el token, la app sigue sin error y no lo manda', async () => {
    mensajeria.getToken.mockRejectedValueOnce(new Error('SERVICE_NOT_AVAILABLE'));
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await esperarLaSesion();
    await waitFor(() => expect(mensajeria.getToken).toHaveBeenCalled());

    expect(await screen.findByRole('button', { name: 'Pedido nuevo' })).toBeOnTheScreen();
    expect(registros()).toEqual([]);
  });
});

describe('avisos-E05: al cerrar la sesión, la app manda el token del teléfono para que la api lo borre', () => {
  test('avisos-E05 — "Cerrar sesión" manda a DELETE /sesion el token del teléfono que registró', async () => {
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await waitFor(() => expect(registros()).toHaveLength(1));

    await fireEvent.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() =>
      expect(peticionesA('/sesion', 'DELETE')).toEqual([
        { autorizacion: `Bearer ${TOKEN_GUARDADO}`, cuerpo: { token_fcm: 'token-fcm-de-prueba' } },
      ]),
    );
  });

  test('avisos-E05 — después de cerrar la sesión, la app deja de escuchar a Firebase y no registra otro token', async () => {
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await waitFor(() => expect(registros()).toHaveLength(1));

    await fireEvent.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    mensajeria.__renovarToken('token-fcm-renovado');

    expect(mensajeria.__escuchas().renovaciones).toBe(0);
    expect(registros()).toHaveLength(1);
  });

  test('avisos-E05 — sin un token registrado, "Cerrar sesión" cierra como antes, sin cuerpo', async () => {
    mensajeria.__permiso(mensajeria.AuthorizationStatus.DENIED);
    await conSesionGuardada();
    await renderRouter(APP, { initialUrl: '/inicio' });
    await waitFor(() => expect(mensajeria.hasPermission).toHaveBeenCalled());

    await fireEvent.press(await screen.findByRole('button', { name: 'Cerrar sesión' }));

    await waitFor(() =>
      expect(peticionesA('/sesion', 'DELETE')).toEqual([{ autorizacion: `Bearer ${TOKEN_GUARDADO}`, cuerpo: undefined }]),
    );
  });
});

describe('avisos-E17: el manejador de los mensajes con la app al fondo o cerrada se registra al arrancar', () => {
  // Carga la entrada de la app (index.ts) en un registro de módulos aparte, con la de expo-router simulada: anota el
  // orden en que se registró el manejador y se montaron las pantallas.
  function arrancar(conFirebase: boolean, plataforma: 'android' | 'ios' = 'android') {
    const orden: string[] = [];
    let aislada: typeof mensajeria | undefined;
    jest.isolateModules(() => {
      jest.doMock('expo-router/entry', () => {
        orden.push('pantallas');
        return {};
      });
      // Los módulos del registro aparte se piden con require: son los que va a cargar la app en él.
      /* eslint-disable @typescript-eslint/no-require-imports */
      const constantes = (require('expo-constants') as typeof import('expo-constants')).default;
      jest.replaceProperty(constantes, 'executionEnvironment', ExecutionEnvironment.Bare);
      (constantes.expoConfig as { extra?: Record<string, unknown> }).extra = { firebaseEnAndroid: conFirebase };
      jest.replaceProperty((require('react-native') as typeof import('react-native')).Platform, 'OS', plataforma);
      aislada = require('@react-native-firebase/messaging') as typeof mensajeria;
      aislada.setBackgroundMessageHandler.mockImplementation(() => {
        orden.push('manejador');
      });
      require('../../index');
      /* eslint-enable @typescript-eslint/no-require-imports */
    });
    return { orden, mensajeria: aislada! };
  }

  test('avisos-E17 — la app arranca por index.ts', () => {
    const paquete = JSON.parse(readFileSync(path.join(RAIZ, 'package.json'), 'utf8')) as { main?: string };
    expect(paquete.main).toBe('index.ts');
  });

  test('avisos-E17 — con Firebase en la compilación, registra el manejador antes de montar las pantallas', () => {
    const { orden } = arrancar(true);

    expect(orden).toEqual(['manejador', 'pantallas']);
  });

  test('avisos-E17 — el manejador recibe el mensaje sin fallar: el sistema muestra el aviso y la app consulta al volver', async () => {
    const { mensajeria: aislada } = arrancar(true);
    const manejador = aislada.setBackgroundMessageHandler.mock.calls[0][1];

    await expect(
      manejador({ data: { tipo: 'cambio_de_estado', pedido: '01JD00000000000000000AV017', estado: 'recolectado' } }),
    ).resolves.toBeUndefined();
  });

  test('avisos-E17 — sin Firebase en la compilación, arranca sin cargar la mensajería', () => {
    const { orden, mensajeria: aislada } = arrancar(false);

    expect(orden).toEqual(['pantallas']);
    expect(aislada.getMessaging).not.toHaveBeenCalled();
  });

  test('avisos-E17 — en iPhone, todavía sin la mensajería, arranca sin cargarla', () => {
    const { orden, mensajeria: aislada } = arrancar(true, 'ios');

    expect(orden).toEqual(['pantallas']);
    expect(aislada.getMessaging).not.toHaveBeenCalled();
  });
});

describe('avisos-E03: el módulo de Firebase entra a la compilación solo con el archivo del entorno', () => {
  const ENTORNO = entornoMinimo('avisos-E03');
  let carpeta: string;
  let archivo: string;
  let sinArchivo: Lectura;
  let conArchivo: ConfiguracionLeida;
  let conArchivoQueNoExiste: Lectura;

  beforeAll(() => {
    // Un google-services.json de prueba, fuera del repositorio: no es de ningún proyecto de Firebase.
    carpeta = mkdtempSync(path.join(os.tmpdir(), 'avisos-e03-'));
    archivo = path.join(carpeta, 'google-services.json');
    writeFileSync(archivo, JSON.stringify({ project_info: { project_id: 'proyecto-de-prueba-avisos-e03' } }));
    sinArchivo = leerConfiguracion(ENTORNO);
    conArchivo = configuracionDeExpo({ ...ENTORNO, GOOGLE_SERVICES_JSON: archivo });
    conArchivoQueNoExiste = leerConfiguracion({ ...ENTORNO, GOOGLE_SERVICES_JSON: path.join(carpeta, 'no-existe.json') });
  }, 240_000);

  afterAll(() => {
    rmSync(carpeta, { recursive: true, force: true });
  });

  test('avisos-E03 — sin el archivo, la configuración sale como antes, sin Firebase ni avisos de Expo', () => {
    expect(sinArchivo.status).toBe(0);
    expect(sinArchivo.stderr).toBe('');
    const configuracion = JSON.parse(sinArchivo.stdout) as ConfiguracionLeida;
    expect(configuracion.android?.googleServicesFile).toBeUndefined();
    expect(configuracion.android?.permissions ?? []).not.toContain(PERMISO_DE_AVISOS);
    expect(permisosDelManifiesto(configuracion)).not.toContain(PERMISO_DE_AVISOS);
    expect(nombresDeLosComplementos(configuracion).filter((nombre) => nombre.includes('firebase'))).toEqual([]);
    expect(configuracion.extra?.firebaseEnAndroid).toBe(false);
  });

  test('avisos-E03 — con el archivo, Android lo lleva con el permiso de avisos y la app sabe que tiene Firebase', () => {
    expect(conArchivo.android?.googleServicesFile).toBe(archivo);
    expect(permisosDelManifiesto(conArchivo)).toContain(PERMISO_DE_AVISOS);
    expect(conArchivo.extra?.firebaseEnAndroid).toBe(true);
  });

  test('avisos-E03 — con el archivo de Android, iPhone no lleva Firebase todavía (avisos-E18)', () => {
    expect(conArchivo.ios?.googleServicesFile).toBeUndefined();
  });

  test('avisos-E03 — si la variable apunta a un archivo que no existe, la configuración falla y lo dice', () => {
    expect(conArchivoQueNoExiste.status).not.toBe(0);
    expect(conArchivoQueNoExiste.stderr).toContain('GOOGLE_SERVICES_JSON');
  });

  test('avisos-E03 — la variable está documentada en .env.example, sin valor', () => {
    const ejemplo = readFileSync(path.join(RAIZ, '.env.example'), 'utf8');
    expect(ejemplo).toMatch(/^GOOGLE_SERVICES_JSON=$/m);
  });
});

describe('avisos-E03: los módulos nativos de Firebase se enlazan solo si la compilación trae el archivo', () => {
  // El enlace automático de los módulos nativos, como lo pide Gradle al compilar (settings.gradle de Expo).
  function modulosEnlazados(plataforma: 'android' | 'ios'): string[] {
    const salida = spawnSync(
      process.execPath,
      ['--no-warnings', '--eval', "require('expo/bin/autolinking')", 'expo-modules-autolinking', 'react-native-config', '--json', '--platform', plataforma],
      { cwd: RAIZ, encoding: 'utf8', env: { ...process.env, EXPO_NO_TELEMETRY: '1' } },
    );
    if (salida.status !== 0) {
      throw new Error(`El enlace automático falló: ${salida.stderr}`);
    }
    return Object.keys((JSON.parse(salida.stdout) as { dependencies: Record<string, unknown> }).dependencies);
  }

  // Lo que decide react-native.config.js para una carpeta de proyecto dada.
  function decision(raiz: string) {
    const { dependenciasNativasDeFirebase } = jest.requireActual<{
      dependenciasNativasDeFirebase(raiz: string): Record<string, { platforms: Record<string, null | undefined> }>;
    }>('../../firebase-nativo');
    return dependenciasNativasDeFirebase(raiz);
  }

  test('avisos-E03 — en esta copia, sin proyecto nativo con el archivo, ni Android ni iPhone enlazan Firebase', () => {
    expect(modulosEnlazados('android')).toEqual(expect.not.arrayContaining(MODULOS_DE_FIREBASE));
    expect(modulosEnlazados('ios')).toEqual(expect.not.arrayContaining(MODULOS_DE_FIREBASE));
    // Los demás módulos nativos siguen enlazados, como antes.
    expect(modulosEnlazados('android')).toEqual(expect.arrayContaining(['react-native-maps', 'react-native-nitro-modules']));
  }, 120_000);

  test('avisos-E03 — con el google-services.json en el proyecto de Android, Android enlaza Firebase y iPhone no', () => {
    const raiz = mkdtempSync(path.join(os.tmpdir(), 'avisos-e03-nativo-'));
    try {
      mkdirSync(path.join(raiz, 'android', 'app'), { recursive: true });
      writeFileSync(path.join(raiz, 'android', 'app', 'google-services.json'), '{}');

      const dependencias = decision(raiz);

      for (const modulo of MODULOS_DE_FIREBASE) {
        expect(dependencias[modulo].platforms).toEqual({ ios: null });
      }
    } finally {
      rmSync(raiz, { recursive: true, force: true });
    }
  });

  test('avisos-E03 — sin el archivo en el proyecto de Android, ninguna plataforma enlaza Firebase', () => {
    const raiz = mkdtempSync(path.join(os.tmpdir(), 'avisos-e03-nativo-'));
    try {
      const dependencias = decision(raiz);

      for (const modulo of MODULOS_DE_FIREBASE) {
        expect(dependencias[modulo].platforms).toEqual({ ios: null, android: null });
      }
    } finally {
      rmSync(raiz, { recursive: true, force: true });
    }
  });
});
