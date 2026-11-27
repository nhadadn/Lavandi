// Apoyo de las pruebas de la configuración de Expo (app.config.ts): la lee como la arma Expo al compilar, con los
// complementos aplicados (modo introspect), sin leer ningún .env de la máquina. Solo cuenta el entorno que le da cada
// prueba: las variables de la app que traiga la máquina se quitan antes. Lo usan las pruebas de avisos y de publicacion.
import { spawnSync } from 'node:child_process';
import path from 'node:path';

export const RAIZ = path.resolve(__dirname, '..', '..');
const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');

/** Todas las variables que lee app.config.ts: ninguna llega de la máquina a la prueba. */
export const VARIABLES_DE_LA_CONFIGURACION = [
  'EXPO_PUBLIC_NOMBRE_APP',
  'EXPO_PUBLIC_API_URL',
  'APP_IDENTIFIER',
  'EXPO_PUBLIC_GOOGLE_IOS_CLIENT_ID',
  'GOOGLE_MAPS_ANDROID_API_KEY',
  'GOOGLE_SERVICES_JSON',
  'PERFIL_DE_COMPILACION',
] as const;

/** Lo que pide la configuración para leerse, con valores de prueba que no pueden estar escritos en el código. */
export function entornoMinimo(escenario: string): Record<string, string> {
  const sufijo = escenario.toLowerCase().replace(/[^a-z0-9]/g, '');
  return {
    EXPO_PUBLIC_NOMBRE_APP: `Nombre de prueba ${escenario}`,
    EXPO_PUBLIC_API_URL: `http://api.prueba.invalid/${sufijo}`,
    APP_IDENTIFIER: `mx.prueba.${sufijo}`,
  };
}

export interface ElementoDelManifiesto {
  $?: Record<string, string>;
}

/** Lo que las pruebas leen de la configuración resultante de Expo. */
export interface ConfiguracionLeida {
  plugins?: (string | [string, unknown])[];
  extra?: Record<string, unknown>;
  ios?: { bundleIdentifier?: string; googleServicesFile?: string; infoPlist?: Record<string, unknown> };
  android?: { package?: string; googleServicesFile?: string; permissions?: string[] };
  _internal?: {
    modResults?: {
      ios?: { infoPlist?: Record<string, unknown> };
      android?: {
        manifest?: {
          manifest?: {
            'uses-permission'?: ElementoDelManifiesto[];
            application?: ({ 'meta-data'?: ElementoDelManifiesto[] } & ElementoDelManifiesto)[];
          };
        };
      };
    };
  };
}

export interface Lectura {
  status: number | null;
  stdout: string;
  stderr: string;
}

/** Corre `expo config --type introspect` con ese entorno y devuelve lo que salió, aunque falle. */
export function leerConfiguracion(entorno: Record<string, string>): Lectura {
  const env: NodeJS.ProcessEnv = { ...process.env, EXPO_NO_DOTENV: '1', EXPO_NO_TELEMETRY: '1' };
  for (const variable of VARIABLES_DE_LA_CONFIGURACION) {
    delete env[variable];
  }
  Object.assign(env, entorno);
  const { status, stdout, stderr } = spawnSync(
    process.execPath,
    [CLI_DE_EXPO, 'config', '--type', 'introspect', '--json'],
    { cwd: RAIZ, env, encoding: 'utf8' },
  );
  return { status, stdout, stderr };
}

/** La configuración con ese entorno. Si Expo no la pudo leer, la prueba falla con lo que dijo. */
export function configuracionDeExpo(entorno: Record<string, string>): ConfiguracionLeida {
  const lectura = leerConfiguracion(entorno);
  if (lectura.status !== 0) {
    throw new Error(`Expo no pudo leer la configuración: ${lectura.stderr}`);
  }
  return JSON.parse(lectura.stdout) as ConfiguracionLeida;
}

/** Los nombres de los complementos de la configuración. */
export function nombresDeLosComplementos(configuracion: ConfiguracionLeida): string[] {
  return (configuracion.plugins ?? []).map((complemento) =>
    Array.isArray(complemento) ? complemento[0] : complemento,
  );
}

/** Los permisos que declara el manifiesto de Android, sin los que la configuración quita. */
export function permisosDelManifiesto(configuracion: ConfiguracionLeida): string[] {
  const permisos = configuracion._internal?.modResults?.android?.manifest?.manifest?.['uses-permission'] ?? [];
  return permisos
    .filter((permiso) => permiso.$?.['tools:node'] !== 'remove')
    .map((permiso) => permiso.$?.['android:name'] ?? '');
}

/** Los atributos de <application> del manifiesto de Android. */
export function aplicacionDelManifiesto(configuracion: ConfiguracionLeida): Record<string, string> {
  return configuracion._internal?.modResults?.android?.manifest?.manifest?.application?.[0]?.$ ?? {};
}
