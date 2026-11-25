// Spec: openspec/changes/base-tecnica/specs/base/spec.md, escenarios base-E05, base-E06 y base-E07.
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

const RAIZ = path.resolve(__dirname, '..', '..');
const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');
const VARIABLES_DE_LA_APP = ['EXPO_PUBLIC_NOMBRE_APP', 'EXPO_PUBLIC_API_URL', 'APP_IDENTIFIER'];

// Valores de prueba que no pueden estar escritos en el código: si salen en la configuración, salieron del entorno.
const ENTORNO_DE_PRUEBA = {
  EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba base-E05',
  EXPO_PUBLIC_API_URL: 'http://api.prueba.invalid/base-e05',
  APP_IDENTIFIER: 'mx.prueba.basee05',
};

interface PropiedadDeGradle {
  type: string;
  key?: string;
  value?: string;
}

// Lo que la prueba lee de la configuración resultante de Expo.
interface ConfiguracionLeida {
  platforms?: string[];
  ios?: { bundleIdentifier?: string; supportsTablet?: boolean };
  android?: { package?: string };
  _internal?: {
    modResults?: {
      ios?: { podfileProperties?: Record<string, string> };
      android?: { gradleProperties?: PropiedadDeGradle[] };
    };
  };
}

// Lee la configuración como la arma Expo al compilar, con los complementos aplicados (modo introspect),
// sin leer ningún .env de la máquina: solo cuenta el entorno que le da la prueba.
function leerConfiguracionDeExpo(entorno: Record<string, string>) {
  const env: NodeJS.ProcessEnv = { ...process.env, EXPO_NO_DOTENV: '1', EXPO_NO_TELEMETRY: '1' };
  for (const variable of VARIABLES_DE_LA_APP) {
    delete env[variable];
  }
  Object.assign(env, entorno);
  return spawnSync(process.execPath, [CLI_DE_EXPO, 'config', '--type', 'introspect', '--json'], {
    cwd: RAIZ,
    env,
    encoding: 'utf8',
  });
}

describe('base: configuración de la app', () => {
  let configuracion: ConfiguracionLeida;
  let sinEntorno: ReturnType<typeof leerConfiguracionDeExpo>;

  beforeAll(() => {
    const leida = leerConfiguracionDeExpo(ENTORNO_DE_PRUEBA);
    if (leida.status !== 0) {
      throw new Error(`Expo no pudo leer la configuración: ${leida.stderr}`);
    }
    configuracion = JSON.parse(leida.stdout) as ConfiguracionLeida;
    sinEntorno = leerConfiguracionDeExpo({});
  }, 120_000);

  describe('base-E05: una sola configuración para las dos plataformas', () => {
    test('base-E05 — la configuración declara iPhone y Android desde el mismo proyecto', () => {
      expect(configuracion.platforms).toEqual(['ios', 'android']);
    });

    // El contrato pide la app para iPhone y Android (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3), no para iPad.
    test('base-E05 — en iPhone la app no se declara para iPad, que el contrato no pide', () => {
      expect(configuracion.ios?.supportsTablet).toBe(false);
    });

    test('base-E05 — el identificador de la app en iPhone y en Android sale del entorno', () => {
      expect(configuracion.ios?.bundleIdentifier).toBe(ENTORNO_DE_PRUEBA.APP_IDENTIFIER);
      expect(configuracion.android?.package).toBe(ENTORNO_DE_PRUEBA.APP_IDENTIFIER);
    });

    test('base-E05 — sin las variables del entorno, la configuración no inventa valores', () => {
      expect(sinEntorno.status).not.toBe(0);
      for (const variable of VARIABLES_DE_LA_APP) {
        expect(sinEntorno.stderr).toContain(variable);
      }
    });

    test('base-E05 — la app se prueba con development builds y no depende de Expo Go', () => {
      const paquete = JSON.parse(readFileSync(path.join(RAIZ, 'package.json'), 'utf8')) as {
        dependencies?: Record<string, string>;
      };
      expect(paquete.dependencies).toHaveProperty('expo-dev-client');
    });
  });

  describe('base-E06: la configuración pide iOS 18 y Android 11', () => {
    test('base-E06 — la versión mínima para iPhone es iOS 18.0', () => {
      const podfile = configuracion._internal?.modResults?.ios?.podfileProperties ?? {};
      expect(podfile['ios.deploymentTarget']).toBe('18.0');
    });

    test('base-E06 — la versión mínima para Android es la API 30 (Android 11)', () => {
      const gradle = configuracion._internal?.modResults?.android?.gradleProperties ?? [];
      const minima = gradle.find((propiedad) => propiedad.key === 'android.minSdkVersion');
      expect(minima?.value).toBe('30');
    });
  });
});

describe('base-E07: la app toma la dirección de la API de su entorno', () => {
  // Se cambia la variable sin reemplazar process.env: en desarrollo y en pruebas, Expo lee las EXPO_PUBLIC_*
  // de una referencia a process.env que toma al cargar el módulo.
  const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
  const fetchOriginal = global.fetch;

  afterEach(() => {
    if (direccionOriginal === undefined) {
      delete process.env.EXPO_PUBLIC_API_URL;
    } else {
      process.env.EXPO_PUBLIC_API_URL = direccionOriginal;
    }
    global.fetch = fetchOriginal;
  });

  test.each([
    ['http://api.prueba.invalid/base-e07', 'salud', 'http://api.prueba.invalid/base-e07/salud'],
    ['https://otra.prueba.invalid/v2/', '/salud', 'https://otra.prueba.invalid/v2/salud'],
  ])('base-E07 — con la dirección %s, la consulta a %s va a %s', async (direccion, ruta, esperada) => {
    process.env.EXPO_PUBLIC_API_URL = direccion;
    const fetchFalso = jest.fn().mockResolvedValue({ ok: true, status: 200 });
    global.fetch = fetchFalso as unknown as typeof fetch;

    // Se carga dentro de la prueba para que, si el módulo falta, fallen solo las pruebas de base-E07.
    const { consultarApi } = jest.requireActual<typeof import('../../src/configuracion/api')>(
      '../../src/configuracion/api',
    );
    await consultarApi(ruta);

    expect(fetchFalso).toHaveBeenCalledTimes(1);
    expect(fetchFalso.mock.calls[0][0]).toBe(esperada);
  });

  test('base-E07 — el código de la app no trae ninguna dirección de la API escrita a mano', () => {
    const archivos = archivosDeCodigo();
    expect(archivos.length).toBeGreaterThan(0);

    const direcciones = /https?:\/\/|\blocalhost\b|\b\d{1,3}(?:\.\d{1,3}){3}\b/i;
    const hallazgos = archivos.filter((archivo) => direcciones.test(textoSinElChatDeWhatsApp(archivo)));
    expect(hallazgos).toEqual([]);
  });
});

// El código de la app: la configuración de Expo, las pantallas de app/ (expo-router, ADR-0017) y src/.
function archivosDeCodigo(): string[] {
  const raiz = ['index.ts', 'App.tsx', 'app.config.ts']
    .map((archivo) => path.join(RAIZ, archivo))
    .filter((archivo) => existsSync(archivo));
  return [...raiz, ...recorrer(path.join(RAIZ, 'app')), ...recorrer(path.join(RAIZ, 'src'))];
}

// La única dirección escrita en la app: la del chat de WhatsApp de soporte, https://wa.me/<número>, en
// src/perfil-usuario/whatsapp.ts (perfil-usuario-E09; openspec/changes/perfil-y-soporte/design.md, "Contrato de la
// API"). Es la dirección pública de WhatsApp para abrir un chat, no la de la API, la de la marca ni la de un documento
// legal; el número sí sale de la configuración del servidor. Solo en ese archivo y solo esa dirección.
const CHAT_DE_WHATSAPP = path.join('src', 'perfil-usuario', 'whatsapp.ts');

function textoSinElChatDeWhatsApp(archivo: string): string {
  const texto = readFileSync(archivo, 'utf8');
  return archivo.endsWith(CHAT_DE_WHATSAPP) ? texto.replace(/https:\/\/wa\.me\//g, '') : texto;
}

function recorrer(carpeta: string): string[] {
  if (!existsSync(carpeta)) {
    return [];
  }
  return readdirSync(carpeta).flatMap((nombre) => {
    const ruta = path.join(carpeta, nombre);
    if (statSync(ruta).isDirectory()) {
      return recorrer(ruta);
    }
    return /\.(ts|tsx|js|jsx)$/.test(nombre) ? [ruta] : [];
  });
}
