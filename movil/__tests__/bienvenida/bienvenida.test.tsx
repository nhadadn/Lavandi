// Spec: openspec/changes/bienvenida/specs/bienvenida/spec.md, escenarios bienvenida-E01, bienvenida-E03, bienvenida-E04
// y bienvenida-E05 (tarea 1.1). Las pantallas se prueban con un archivo de contenido de prueba: si muestran sus textos
// y sus imágenes, los toman del archivo y no de su código. Navegación con expo-router (ADR-0017).
import path from 'node:path';
import * as SecureStore from 'expo-secure-store';
import { router } from 'expo-router';
import { fireEvent, renderRouter, screen, waitFor, within } from 'expo-router/testing-library';

import type { ContenidoDeBienvenida } from '../../src/contenido/bienvenida';

const APP = path.resolve(__dirname, '..', '..', 'app');

// El primer render carga expo-router y sus pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);
// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const NOMBRE_DE_PRUEBA = 'Nombre de prueba bienvenida';

// El archivo de contenido de prueba (bienvenida-E05): otros textos y otras imágenes que los provisionales.
const mockContenido: ContenidoDeBienvenida = {
  bienvenida: { lema: 'Lema de prueba E05', ilustracion: { uri: 'prueba://bienvenida' } },
  informativas: [
    { titulo: 'Título de prueba 1', texto: 'Texto de prueba 1', ilustracion: { uri: 'prueba://informativa-1' } },
    { titulo: 'Título de prueba 2', texto: 'Texto de prueba 2', ilustracion: { uri: 'prueba://informativa-2' } },
    { titulo: 'Título de prueba 3', texto: 'Texto de prueba 3', ilustracion: { uri: 'prueba://informativa-3' } },
  ],
  botones: { comenzar: 'Comenzar de prueba', siguiente: 'Siguiente de prueba' },
};

jest.mock('../../src/contenido/bienvenida', () => ({ contenidoDeBienvenida: mockContenido }));

// Los textos que puede mostrar cada pantalla: los del archivo de contenido y el nombre de la configuración.
const TEXTOS_PERMITIDOS = new Set([
  NOMBRE_DE_PRUEBA,
  mockContenido.bienvenida.lema,
  ...mockContenido.informativas.flatMap((i) => [i.titulo, i.texto]),
  mockContenido.botones.comenzar,
  mockContenido.botones.siguiente,
]);

function textosEnPantalla(): string[] {
  return screen.queryAllByText(/./, { includeHiddenElements: true }).map((elemento) =>
    ([] as unknown[])
      .concat(elemento.props.children)
      .filter((parte) => typeof parte === 'string' || typeof parte === 'number')
      .join(''),
  );
}

// La ilustración de una pantalla: la pila deja montadas las anteriores, así que se busca dentro de la que se pide.
function ilustracion(pantalla: string) {
  return within(screen.getByTestId(pantalla)).getByTestId('ilustracion', { includeHiddenElements: true }).props.source;
}

async function avanzar(boton: string, destino: string) {
  await fireEvent.press(screen.getByRole('button', { name: boton }));
  await waitFor(() => expect(screen).toHavePathname(destino));
}

describe('bienvenida: la bienvenida y las tres informativas', () => {
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
  });

  test('bienvenida-E01 — la primera apertura muestra la bienvenida con el nombre de la configuración y su contenido', async () => {
    await renderRouter(APP, { initialUrl: '/' });
    await waitFor(() => expect(screen).toHavePathname('/bienvenida'));

    expect(screen.getByText(NOMBRE_DE_PRUEBA)).toBeOnTheScreen();
    expect(screen.getByText('Lema de prueba E05')).toBeOnTheScreen();
    expect(ilustracion('pantalla-inicial')).toEqual({ uri: 'prueba://bienvenida' });
  });

  test('bienvenida-E03 — al avanzar se ven las tres informativas en orden, cada una con su contenido', async () => {
    await renderRouter(APP, { initialUrl: '/bienvenida' });

    await avanzar('Comenzar de prueba', '/informativa/1');
    expect(screen.getByRole('header', { name: 'Título de prueba 1' })).toBeOnTheScreen();
    expect(screen.getByText('Texto de prueba 1')).toBeOnTheScreen();
    expect(ilustracion('informativa-1')).toEqual({ uri: 'prueba://informativa-1' });

    await avanzar('Siguiente de prueba', '/informativa/2');
    expect(screen.getByRole('header', { name: 'Título de prueba 2' })).toBeOnTheScreen();
    expect(screen.getByText('Texto de prueba 2')).toBeOnTheScreen();
    expect(ilustracion('informativa-2')).toEqual({ uri: 'prueba://informativa-2' });

    await avanzar('Siguiente de prueba', '/informativa/3');
    expect(screen.getByRole('header', { name: 'Título de prueba 3' })).toBeOnTheScreen();
    expect(screen.getByText('Texto de prueba 3')).toBeOnTheScreen();
    expect(ilustracion('informativa-3')).toEqual({ uri: 'prueba://informativa-3' });
  });

  test('bienvenida-E04 — al avanzar desde la tercera, la app abre el inicio de sesión, que ofrece registrarse o iniciar sesión', async () => {
    await renderRouter(APP, { initialUrl: '/informativa/3' });

    await avanzar('Siguiente de prueba', '/inicio-de-sesion');

    expect(await screen.findByRole('button', { name: 'Iniciar sesión' })).toBeOnTheScreen();
    expect(screen.getByRole('link', { name: '¿No tienes cuenta? Regístrate' })).toBeOnTheScreen();
  });

  // DD-131, punto 3, por delegación (C-236): el inicio de sesión queda como primera pantalla.
  test('bienvenida-E04 — al llegar al inicio de sesión desde la tercera, "atrás" ya no vuelve a las informativas', async () => {
    await renderRouter(APP, { initialUrl: '/bienvenida' });
    await avanzar('Comenzar de prueba', '/informativa/1');
    await avanzar('Siguiente de prueba', '/informativa/2');
    await avanzar('Siguiente de prueba', '/informativa/3');
    expect(router.canGoBack()).toBe(true);

    await avanzar('Siguiente de prueba', '/inicio-de-sesion');

    await waitFor(() => expect(router.canGoBack()).toBe(false));
    expect(screen.queryByTestId('informativa-3', { includeHiddenElements: true })).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Regresar' })).not.toBeOnTheScreen();
  });

  test.each([['/bienvenida'], ['/informativa/1'], ['/informativa/2'], ['/informativa/3']])(
    'bienvenida-E05 — %s solo muestra textos del archivo de contenido y el nombre de la configuración',
    async (ruta) => {
      await renderRouter(APP, { initialUrl: ruta });
      await waitFor(() => expect(screen).toHavePathname(ruta));

      const textos = textosEnPantalla();
      expect(textos.length).toBeGreaterThan(0);
      expect(textos.filter((texto) => !TEXTOS_PERMITIDOS.has(texto))).toEqual([]);
    },
  );

  test.each([['/bienvenida'], ['/informativa/1'], ['/informativa/2'], ['/informativa/3']])(
    'bienvenida-E05 — %s tiene un solo botón, el de avanzar: sin "Saltar" ni "Ya tengo cuenta" (C-225)',
    async (ruta) => {
      await renderRouter(APP, { initialUrl: ruta });
      await waitFor(() => expect(screen).toHavePathname(ruta));

      expect(screen.getAllByRole('button')).toHaveLength(1);
    },
  );
});

describe('bienvenida-E05: el contenido provisional vive en su propio archivo', () => {
  const { contenidoDeBienvenida: provisional } = jest.requireActual<typeof import('../../src/contenido/bienvenida')>(
    '../../src/contenido/bienvenida',
  );

  test('bienvenida-E05 — trae el lema y la ilustración de la bienvenida', () => {
    expect(provisional.bienvenida.lema.trim()).not.toBe('');
    expect(provisional.bienvenida.ilustracion).toBeTruthy();
  });

  // Decisión 20 del acta del 2026-11-20 (C-237): la ilustración provisional de la bienvenida es el logotipo, en su
  // archivo de nombre neutro; la compilación local lo reemplaza por el de EL CLIENTE.
  test('bienvenida-E05 — la ilustración provisional de la bienvenida es el logotipo', () => {
    expect(provisional.bienvenida.ilustracion).toEqual(require('../../assets/marca/logotipo.png'));
  });

  test('bienvenida-E05 — trae tres informativas, cada una con título, texto e ilustración (C-225)', () => {
    expect(provisional.informativas).toHaveLength(3);
    for (const informativa of provisional.informativas) {
      expect(informativa.titulo.trim()).not.toBe('');
      expect(informativa.texto.trim()).not.toBe('');
      expect(informativa.ilustracion).toBeTruthy();
    }
  });
});
