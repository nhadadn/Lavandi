// Spec: openspec/changes/publicacion/specs/publicacion/spec.md, publicacion-E07 (tarea 3.1) y publicacion-E09 (tarea 3.3).
// La app se compila en la nube con EAS Build (ADR-0002), con un perfil por entorno (ADR-0012): `preview` para el entorno
// de prueba y `production` para las tiendas. Cada perfil toma del entorno de EAS que le toca su API, su proyecto de
// Firebase y su llave de mapas, que no se versionan: eas.json solo dice qué entorno usa cada perfil.
//
// Las variables de cada entorno de EAS, con su tipo y su visibilidad, están en el design.md del cambio ("Las variables de
// cada entorno de EAS"). Aquí se simulan con valores de prueba.
import { existsSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { configuracionDeExpo, entornoMinimo, RAIZ, type ConfiguracionLeida } from '../apoyo/configuracion';

interface PerfilDeEas {
  environment?: string;
  distribution?: string;
  env?: Record<string, string>;
  android?: { buildType?: string };
}

const PERFILES = ['preview', 'production'] as const;
type Perfil = (typeof PERFILES)[number];

// Las variables que viven en el entorno de EAS y nunca en el repositorio.
const DEL_ENTORNO_DE_EAS = ['EXPO_PUBLIC_API_URL', 'APP_IDENTIFIER', 'GOOGLE_SERVICES_JSON', 'GOOGLE_MAPS_ANDROID_API_KEY'];
const NOMBRE_DE_LA_LLAVE_EN_EL_MANIFIESTO = 'com.google.android.geo.API_KEY';

function textoDeEas(): string {
  return readFileSync(path.join(RAIZ, 'eas.json'), 'utf8');
}

function perfilesDeEas(): Record<string, PerfilDeEas> {
  return (JSON.parse(textoDeEas()) as { build?: Record<string, PerfilDeEas> }).build ?? {};
}

function llaveDelManifiesto(configuracion: ConfiguracionLeida): string | undefined {
  const aplicacion = configuracion._internal?.modResults?.android?.manifest?.manifest?.application?.[0];
  return aplicacion?.['meta-data']?.find((dato) => dato.$?.['android:name'] === NOMBRE_DE_LA_LLAVE_EN_EL_MANIFIESTO)?.$?.[
    'android:value'
  ];
}

describe('publicacion-E07: un perfil por entorno', () => {
  test('publicacion-E07 — preview toma las variables del entorno de prueba de EAS y production las de producción', () => {
    const perfiles = perfilesDeEas();
    expect(perfiles.preview?.environment).toBe('preview');
    expect(perfiles.production?.environment).toBe('production');
  });

  test('publicacion-E07 — preview es la compilación interna del entorno de prueba: un APK que se instala sin tienda', () => {
    const { preview } = perfilesDeEas();
    expect(preview?.distribution).toBe('internal');
    expect(preview?.android?.buildType).toBe('apk');
  });

  test.each(PERFILES)(
    'publicacion-E07 — (%s) eas.json no trae la API, el identificador, el archivo de Firebase ni la llave de mapas',
    (perfil) => {
      const env = perfilesDeEas()[perfil]?.env ?? {};
      for (const variable of DEL_ENTORNO_DE_EAS) {
        expect(env).not.toHaveProperty(variable);
      }
    },
  );

  test('publicacion-E07 — eas.json no trae ninguna dirección ni llave escrita', () => {
    const texto = textoDeEas();
    expect(texto).not.toMatch(/https?:\/\//);
    // El formato de las llaves de las API de Google: "AIza" y 35 caracteres más.
    expect(texto).not.toMatch(/AIza[0-9A-Za-z_-]{35}/);
    expect(texto).not.toMatch(/google-services|GoogleService-Info/);
  });

  describe('publicacion-E07: con las variables de cada entorno, cada perfil usa las suyas', () => {
    let carpeta: string;
    const porPerfil = {} as Record<Perfil, { configuracion: ConfiguracionLeida; archivo: string; llave: string }>;

    beforeAll(() => {
      // Los archivos de Firebase de prueba de cada entorno, fuera del repositorio: no son de ningún proyecto.
      carpeta = mkdtempSync(path.join(os.tmpdir(), 'publicacion-e07-'));
      for (const perfil of PERFILES) {
        const archivo = path.join(carpeta, `firebase-${perfil}.json`);
        writeFileSync(archivo, JSON.stringify({ project_info: { project_id: `proyecto-de-prueba-${perfil}` } }));
        const llave = `llave-de-mapas-de-prueba-${perfil}`;
        const configuracion = configuracionDeExpo({
          ...entornoMinimo(`publicacion-E07-${perfil}`),
          EXPO_PUBLIC_API_URL: `https://api-${perfil}.prueba.invalid/api`,
          GOOGLE_SERVICES_JSON: archivo,
          GOOGLE_MAPS_ANDROID_API_KEY: llave,
          ...(perfilesDeEas()[perfil]?.env ?? {}),
        });
        porPerfil[perfil] = { configuracion, archivo, llave };
      }
    }, 240_000);

    afterAll(() => {
      rmSync(carpeta, { recursive: true, force: true });
    });

    test.each(PERFILES)('publicacion-E07 — (%s) usa el proyecto de Firebase de su entorno', (perfil) => {
      const { configuracion, archivo } = porPerfil[perfil];
      expect(configuracion.android?.googleServicesFile).toBe(archivo);
      expect(configuracion.extra?.firebaseEnAndroid).toBe(true);
    });

    test.each(PERFILES)('publicacion-E07 — (%s) usa la llave de mapas de su entorno', (perfil) => {
      const { configuracion, llave } = porPerfil[perfil];
      expect(llaveDelManifiesto(configuracion)).toBe(llave);
      expect(configuracion.extra?.mapaDeGoogleEnAndroid).toBe(true);
    });

    test('publicacion-E07 — los dos perfiles no se mezclan', () => {
      expect(porPerfil.preview.configuracion.android?.googleServicesFile).not.toBe(
        porPerfil.production.configuracion.android?.googleServicesFile,
      );
      expect(llaveDelManifiesto(porPerfil.preview.configuracion)).not.toBe(
        llaveDelManifiesto(porPerfil.production.configuracion),
      );
    });
  });
});

describe('publicacion-E09: la compilación de producción firmada de Android', () => {
  interface Eas {
    cli?: { appVersionSource?: string };
    build?: Record<string, PerfilDeEas & { autoIncrement?: boolean | string; credentialsSource?: string }>;
  }
  interface PropiedadDeGradle {
    key?: string;
    value?: string;
  }
  const eas = () => JSON.parse(textoDeEas()) as Eas;
  let produccion: ConfiguracionLeida & { android?: { versionCode?: number }; ios?: { buildNumber?: string } };

  beforeAll(() => {
    produccion = configuracionDeExpo({
      ...entornoMinimo('publicacion-E09'),
      EXPO_PUBLIC_API_URL: 'https://api.prueba.invalid/publicacion-e09',
      ...(perfilesDeEas().production?.env ?? {}),
    });
  }, 120_000);

  test('publicacion-E09 — production compila para Google Play: un paquete de aplicación, no un APK', () => {
    expect(eas().build?.production?.android?.buildType).toBe('app-bundle');
  });

  test('publicacion-E09 — la firma de Android vive en EAS: eas.json no la busca en el repositorio, que no la tiene', () => {
    expect(eas().build?.production?.credentialsSource).toBe('remote');
    expect(existsSync(path.join(RAIZ, 'credentials.json'))).toBe(false);
  });

  test('publicacion-E09 — el número de versión lo lleva EAS y production lo sube en cada compilación', () => {
    expect(eas().cli?.appVersionSource).toBe('remote');
    expect(eas().build?.production?.autoIncrement).toBe(true);
  });

  test('publicacion-E09 — la configuración no fija el número de la compilación, que es de EAS', () => {
    expect(produccion.android?.versionCode).toBeUndefined();
    expect(produccion.ios?.buildNumber).toBeUndefined();
  });

  test('publicacion-E09 — la compilación de las tiendas no se minifica, como el APK local que sí abre (C-236)', () => {
    const modResults = produccion._internal?.modResults as { android?: { gradleProperties?: PropiedadDeGradle[] } };
    const minificar = modResults?.android?.gradleProperties?.find(
      (propiedad) => propiedad.key === 'android.enableMinifyInReleaseBuilds',
    );
    expect(minificar?.value).toBe('false');
  });
});
