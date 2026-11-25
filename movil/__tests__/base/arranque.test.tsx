// Spec: openspec/changes/base-tecnica/specs/base/spec.md, escenarios base-E04 y base-E09.
// Desde ADR-0017 la app arranca con expo-router: el componente raíz es app/_layout.tsx, y la primera pantalla de una
// app recién instalada y sin sesión es la bienvenida (bienvenida-E01).
import { readFileSync } from 'node:fs';
import path from 'node:path';
import * as SecureStore from 'expo-secure-store';
import { renderRouter, screen, waitFor } from 'expo-router/testing-library';

const APP = path.resolve(__dirname, '..', '..', 'app');

// El primer render carga expo-router y sus pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);
// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

// Un nombre que no puede estar escrito en el código: si aparece en pantalla, salió de la configuración.
const NOMBRE_DE_PRUEBA = 'Nombre de prueba base-E09';

describe('base: arranque de la app', () => {
  // Se cambia la variable sin reemplazar process.env: en desarrollo y en pruebas, Expo lee las EXPO_PUBLIC_*
  // de una referencia a process.env que toma al cargar el módulo.
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;

  beforeEach(() => {
    almacen.__vaciar();
    process.env.EXPO_PUBLIC_NOMBRE_APP = NOMBRE_DE_PRUEBA;
  });

  afterEach(() => {
    if (nombreOriginal === undefined) {
      delete process.env.EXPO_PUBLIC_NOMBRE_APP;
    } else {
      process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
    }
    jest.restoreAllMocks();
  });

  test('base-E04 — la app arranca y muestra su primera pantalla sin errores', async () => {
    const errores = jest.spyOn(console, 'error').mockImplementation(() => {});

    await renderRouter(APP, { initialUrl: '/' });

    await waitFor(() => expect(screen.getByTestId('pantalla-inicial')).toBeOnTheScreen());
    expect(errores).not.toHaveBeenCalled();
  });

  test('base-E09 — la primera pantalla muestra el nombre que dice la configuración', async () => {
    await renderRouter(APP, { initialUrl: '/' });

    expect(await screen.findByText(NOMBRE_DE_PRUEBA)).toBeOnTheScreen();
  });

  // Desde avisos-E17, index.ts registra primero el manejador de los mensajes de Firebase con la app al fondo, y luego
  // carga la entrada de expo-router, que monta las pantallas.
  test('ADR-0017 — la entrada de la app es la de expo-router, después de lo que corre al arrancar', () => {
    const paquete = jest.requireActual<{ main?: string }>('../../package.json');
    const entrada = readFileSync(path.resolve(__dirname, '..', '..', 'index.ts'), 'utf8');
    const importados = [...entrada.matchAll(/^import '([^']+)';$/gm)].map(([, modulo]) => modulo);

    expect(paquete.main).toBe('index.ts');
    expect(importados.at(-1)).toBe('expo-router/entry');
  });
});
