// Spec: openspec/changes/lavanderias-cercanas/specs/cercanas/spec.md, cercanas-E01 y cercanas-E02 (tarea 2.1).
// El mapa nativo de cada plataforma con react-native-maps sin proveedor (ADR-0009): MapKit en iPhone y Google Maps en
// Android. La llave de Google Maps para Android sale del entorno, nunca del código (ADR-0012). El mapa es la simulación
// de __mocks__/react-native-maps.tsx: que en iPhone se vea el de Apple y en Android el de Google se prueba en un teléfono
// real.
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs';
import path from 'node:path';

import { render, screen } from '@testing-library/react-native';
import Constants from 'expo-constants';
import { Platform } from 'react-native';

import type { LavanderiaCercana } from '../../src/cercanas/api';
import { MapaDeLavanderias } from '../../src/cercanas/MapaDeLavanderias';

const RAIZ = path.resolve(__dirname, '..', '..');
const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');

// Valores de prueba que no pueden estar en el código: si aparecen en la configuración, salieron del entorno.
const ENTORNO_DE_PRUEBA = {
  EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba cercanas-E02',
  EXPO_PUBLIC_API_URL: 'http://api.prueba.invalid/cercanas-e02',
  APP_IDENTIFIER: 'mx.prueba.cercanase02',
};
const LLAVE_DE_PRUEBA = 'llave-de-prueba-cercanas-e02';
const VARIABLE_DE_LA_LLAVE = 'GOOGLE_MAPS_ANDROID_API_KEY';
const NOMBRE_DE_LA_LLAVE_EN_EL_MANIFIESTO = 'com.google.android.geo.API_KEY';

const CENTRO = { latitud: 24.0277, longitud: -104.6532 };
const LAVANDERIAS: LavanderiaCercana[] = [
  {
    id_publico: '01JD00000000000000000000A1',
    nombre: 'Lavandería de prueba uno',
    latitud: 24.03,
    longitud: -104.66,
    distancia_metros: 800,
    calificacion_promedio: 4.5,
    numero_de_calificaciones: 3,
  },
  {
    id_publico: '01JD00000000000000000000A2',
    nombre: 'Lavandería de prueba dos',
    latitud: 24.02,
    longitud: -104.64,
    distancia_metros: 1500,
    calificacion_promedio: null,
    numero_de_calificaciones: 0,
  },
];

// Lo que la prueba lee de la configuración resultante de Expo.
interface ElementoDelManifiesto {
  $?: Record<string, string>;
}
interface ConfiguracionLeida {
  extra?: Record<string, unknown>;
  ios?: { infoPlist?: Record<string, unknown> };
  _internal?: {
    modResults?: {
      ios?: { infoPlist?: Record<string, unknown> };
      android?: {
        manifest?: { manifest?: { application?: { 'meta-data'?: ElementoDelManifiesto[] }[] } };
      };
    };
  };
}

// Lee la configuración como la arma Expo al compilar, con los complementos aplicados (modo introspect), sin leer ningún
// .env de la máquina: solo cuenta el entorno que le da la prueba. Vive en cada archivo: aquí, todo lo que está en
// __tests__/ se corre como test.
function leerConfiguracionDeExpo(entorno: Record<string, string>): ConfiguracionLeida {
  const env: NodeJS.ProcessEnv = { ...process.env, EXPO_NO_DOTENV: '1', EXPO_NO_TELEMETRY: '1' };
  for (const variable of [...Object.keys(ENTORNO_DE_PRUEBA), VARIABLE_DE_LA_LLAVE]) {
    delete env[variable];
  }
  Object.assign(env, entorno);
  const leida = spawnSync(process.execPath, [CLI_DE_EXPO, 'config', '--type', 'introspect', '--json'], {
    cwd: RAIZ,
    env,
    encoding: 'utf8',
  });
  if (leida.status !== 0) {
    throw new Error(`Expo no pudo leer la configuración: ${leida.stderr}`);
  }
  return JSON.parse(leida.stdout) as ConfiguracionLeida;
}

function llaveDelManifiesto(configuracion: ConfiguracionLeida): string | undefined {
  const aplicacion = configuracion._internal?.modResults?.android?.manifest?.manifest?.application?.[0];
  const elemento = aplicacion?.['meta-data']?.find(
    (dato) => dato.$?.['android:name'] === NOMBRE_DE_LA_LLAVE_EN_EL_MANIFIESTO,
  );
  return elemento?.$?.['android:value'];
}

// La plataforma en la que corre la app (jest-expo corre como iPhone por omisión).
function enPlataforma(plataforma: 'ios' | 'android') {
  jest.replaceProperty(Platform, 'OS', plataforma);
}

// Lo que app.config.ts le dice a la app sobre la compilación: si trae la llave de Google Maps para Android. En Jest,
// Constants.expoConfig es un objeto vacío que se puede completar.
const configuracionDeLaApp = Constants.expoConfig as { extra?: Record<string, unknown> };
function compilacionConLlave(trae: boolean) {
  configuracionDeLaApp.extra = { mapaDeGoogleEnAndroid: trae };
}

async function pintarElMapa() {
  await render(<MapaDeLavanderias centro={CENTRO} lavanderias={LAVANDERIAS} alElegir={jest.fn()} />);
}

afterEach(() => {
  delete configuracionDeLaApp.extra;
  jest.restoreAllMocks();
});

describe('cercanas-E01: en iPhone, el mapa de Apple', () => {
  test('cercanas-E01 — el mapa no fija proveedor, así que en iPhone es el de Apple', async () => {
    enPlataforma('ios');
    compilacionConLlave(false);

    await pintarElMapa();

    const mapa = screen.getByTestId('mapa');
    expect(mapa.props).not.toHaveProperty('provider');
  });

  test('cercanas-E01 — en iPhone el mapa no depende de la llave de Google', async () => {
    enPlataforma('ios');

    await pintarElMapa();

    expect(screen.getByTestId('mapa')).toBeOnTheScreen();
    expect(screen.queryByTestId('mapa-no-disponible')).not.toBeOnTheScreen();
  });

  test('cercanas-E01 — ningún archivo de la app fija el proveedor del mapa', () => {
    const fijaProveedor = /\bprovider\s*[=:{]|PROVIDER_GOOGLE|PROVIDER_DEFAULT/;
    const archivos = archivosDeCodigo();
    expect(archivos.length).toBeGreaterThan(0);
    expect(archivos.filter((archivo) => fijaProveedor.test(readFileSync(archivo, 'utf8')))).toEqual([]);
  });
});

describe('cercanas-E02: en Android, el mapa de Google con la llave de la configuración', () => {
  let conLlave: ConfiguracionLeida;
  let sinLlave: ConfiguracionLeida;

  beforeAll(() => {
    conLlave = leerConfiguracionDeExpo({ ...ENTORNO_DE_PRUEBA, [VARIABLE_DE_LA_LLAVE]: LLAVE_DE_PRUEBA });
    sinLlave = leerConfiguracionDeExpo(ENTORNO_DE_PRUEBA);
  }, 180_000);

  test('cercanas-E02 — la llave de Google Maps del manifiesto de Android sale de la variable del entorno', () => {
    expect(llaveDelManifiesto(conLlave)).toBe(LLAVE_DE_PRUEBA);
    expect(conLlave.extra?.mapaDeGoogleEnAndroid).toBe(true);
  });

  test('cercanas-E02 — en iPhone no se configura Google Maps: el mapa es el de Apple', () => {
    const infoPlist = conLlave._internal?.modResults?.ios?.infoPlist ?? {};
    expect(infoPlist).not.toHaveProperty('GMSApiKey');
  });

  test('cercanas-E02 — sin la variable, el manifiesto no lleva llave y la app lo sabe', () => {
    expect(llaveDelManifiesto(sinLlave)).toBeUndefined();
    expect(sinLlave.extra?.mapaDeGoogleEnAndroid).toBe(false);
  });

  test('cercanas-E02 — con la llave en la compilación, en Android se monta el mapa sin proveedor, que es el de Google', async () => {
    enPlataforma('android');
    compilacionConLlave(true);

    await pintarElMapa();

    const mapa = screen.getByTestId('mapa');
    expect(mapa.props).not.toHaveProperty('provider');
  });

  // Sin la llave en el manifiesto, el SDK de Google Maps cierra la app al crear el mapa. La pantalla sigue sin él.
  test('cercanas-E02 — sin la llave en la compilación, en Android la app no monta el mapa y lo dice', async () => {
    enPlataforma('android');
    compilacionConLlave(false);

    await pintarElMapa();

    expect(screen.queryByTestId('mapa')).not.toBeOnTheScreen();
    expect(screen.getByTestId('mapa-no-disponible')).toHaveTextContent(/mapa no está disponible/);
  });

  test('cercanas-E02 — el código de la app no trae ninguna llave de Google escrita', () => {
    // El formato de las llaves de las API de Google: "AIza" y 35 caracteres más.
    const llaveDeGoogle = /AIza[0-9A-Za-z_-]{35}/;
    const archivos = [...archivosDeCodigo(), path.join(RAIZ, '.env.example')];
    expect(archivos.filter((archivo) => llaveDeGoogle.test(readFileSync(archivo, 'utf8')))).toEqual([]);
  });

  test('cercanas-E02 — la variable de la llave está documentada en .env.example, sin valor', () => {
    const ejemplo = readFileSync(path.join(RAIZ, '.env.example'), 'utf8');
    expect(ejemplo).toMatch(new RegExp(`^${VARIABLE_DE_LA_LLAVE}=$`, 'm'));
  });
});

// El código de la app: la configuración de Expo, las pantallas de app/ (expo-router, ADR-0017) y src/.
function archivosDeCodigo(): string[] {
  const raiz = ['app.config.ts'].map((archivo) => path.join(RAIZ, archivo)).filter((archivo) => existsSync(archivo));
  return [...raiz, ...recorrer(path.join(RAIZ, 'app')), ...recorrer(path.join(RAIZ, 'src'))];
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
    return /\.(ts|tsx|js|jsx|json)$/.test(nombre) ? [ruta] : [];
  });
}
