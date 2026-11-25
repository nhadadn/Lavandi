// La configuración de Expo que piden expo-router (ADR-0017) y expo-secure-store (ADR-0018).
// Sin `scheme`, expo-linking lanza un error en una compilación de producción; el esquema sale del entorno, como el
// identificador de la app, para no escribir la marca a mano (CLAUDE.md §5).
import { spawnSync } from 'node:child_process';
import path from 'node:path';

const RAIZ = path.resolve(__dirname, '..', '..');
const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');

const ENTORNO_DE_PRUEBA = {
  EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba ADR-0017',
  EXPO_PUBLIC_API_URL: 'http://api.prueba.invalid/adr-0017',
  APP_IDENTIFIER: 'mx.prueba.adr0017',
};

type Complemento = string | [string, Record<string, unknown>?];

describe('ADR-0017 y ADR-0018: la configuración de la navegación y del almacenamiento cifrado', () => {
  let configuracion: { scheme?: string | string[]; plugins?: Complemento[] };

  beforeAll(() => {
    const env: NodeJS.ProcessEnv = { ...process.env, ...ENTORNO_DE_PRUEBA, EXPO_NO_DOTENV: '1', EXPO_NO_TELEMETRY: '1' };
    const leida = spawnSync(process.execPath, [CLI_DE_EXPO, 'config', '--type', 'public', '--json'], {
      cwd: RAIZ,
      env,
      encoding: 'utf8',
    });
    if (leida.status !== 0) {
      throw new Error(`Expo no pudo leer la configuración: ${leida.stderr}`);
    }
    configuracion = JSON.parse(leida.stdout);
  }, 120_000);

  const complemento = (nombre: string) =>
    configuracion.plugins?.find((c) => (Array.isArray(c) ? c[0] : c) === nombre);

  test('ADR-0017 — el esquema de los enlaces de la app sale del entorno', () => {
    expect(configuracion.scheme).toBe(ENTORNO_DE_PRUEBA.APP_IDENTIFIER);
  });

  test('ADR-0017 — la configuración lleva el complemento de expo-router', () => {
    expect(complemento('expo-router')).toBeDefined();
  });

  test('ADR-0018 — la configuración lleva expo-secure-store sin pedir Face ID, que la app no usa', () => {
    const encontrado = complemento('expo-secure-store');

    expect(Array.isArray(encontrado) ? encontrado[1] : undefined).toEqual({ faceIDPermission: false });
  });
});
