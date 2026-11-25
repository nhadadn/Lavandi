// Spec: openspec/changes/bienvenida/, tarea 1.3 (C-230): el aspecto de la bienvenida y las tres informativas, ajustado a
// las imágenes de la Propuesta 04 de los mocks. No cambia ningún escenario (bienvenida-E01 a bienvenida-E05): prueba la
// composición y el orden, los botones fijos abajo y a todo lo ancho, y los puntos de avance, con los colores y las
// medidas de theme.ts (ADR-0013). Sin "Saltar" ni "Ya tengo cuenta" (C-225) y sin la cinta ni las marcas del mock. El
// contenido es el de un archivo de prueba, como en bienvenida.test.tsx (bienvenida-E05). Ya no hay una entrada aparte:
// la oferta de bienvenida-E04 es el inicio de sesión (C-237), cuyo aspecto prueba registro/aspecto.test.tsx.
import * as SecureStore from 'expo-secure-store';
import { Stack } from 'expo-router';
import { renderRouter, screen, waitFor, within } from 'expo-router/testing-library';
import { StyleSheet } from 'react-native';

import Bienvenida from '../../app/bienvenida';
import Informativa from '../../app/informativa/[numero]';
import type { ContenidoDeBienvenida } from '../../src/contenido/bienvenida';
import { ProveedorDeSesion } from '../../src/sesion/ProveedorDeSesion';
import { tema } from '../../src/tema/theme';

// El primer render carga expo-router y sus pantallas; con la caché de Babel vacía, como en la integración continua,
// pasa de los 5 s que Jest da por omisión.
jest.setTimeout(60_000);
// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };

const { color, espaciado, tipografia } = tema;
const NOMBRE = 'Nombre de prueba aspecto';

const mockContenido: ContenidoDeBienvenida = {
  bienvenida: { lema: 'Lema de prueba aspecto', ilustracion: { uri: 'prueba://bienvenida' } },
  informativas: [
    { titulo: 'Título de prueba 1', texto: 'Texto de prueba 1', ilustracion: { uri: 'prueba://informativa-1' } },
    { titulo: 'Título de prueba 2', texto: 'Texto de prueba 2', ilustracion: { uri: 'prueba://informativa-2' } },
    { titulo: 'Título de prueba 3', texto: 'Texto de prueba 3', ilustracion: { uri: 'prueba://informativa-3' } },
  ],
  botones: { comenzar: 'Comenzar de prueba', siguiente: 'Siguiente de prueba' },
};

// Con un getter: las pantallas se importan arriba, antes de que exista mockContenido, y lo leen al dibujarse.
jest.mock('../../src/contenido/bienvenida', () => ({
  get contenidoDeBienvenida() {
    return mockContenido;
  },
}));

// Una pila como la de la app, sin el resto de su raíz.
function Pila() {
  return <Stack screenOptions={{ headerShown: false }} />;
}

async function abrir(ruta: string) {
  await renderRouter(
    { _layout: Pila, bienvenida: Bienvenida, 'informativa/[numero]': Informativa },
    { initialUrl: ruta, wrapper: ProveedorDeSesion },
  );
  await waitFor(() => expect(screen).toHavePathname(ruta));
}

function estiloPlano(elemento: { props: { style?: unknown } }) {
  return StyleSheet.flatten(elemento.props.style as never) ?? {};
}

// El orden de las partes sin depender de medidas: recorre el árbol de arriba abajo, junta los textos y los testID (como
// "#testID") y devuelve las marcas que aparecen en ese orden, hasta la primera que falta o que llega antes de tiempo.
// Vive en cada archivo: aquí, todo lo que está en __tests__/ se corre como test.
interface Nodo {
  props: Record<string, unknown>;
  children: readonly (Nodo | string)[];
}

function enOrden(marcas: readonly string[]): string[] {
  const partes: string[] = [];
  const visitar = (nodo: Nodo | string) => {
    if (typeof nodo === 'string') {
      partes.push(nodo);
      return;
    }
    if (typeof nodo.props.testID === 'string') {
      partes.push(`#${nodo.props.testID}`);
    }
    nodo.children.forEach(visitar);
  };
  visitar(screen.container as unknown as Nodo);

  const encontradas: string[] = [];
  let desde = 0;
  for (const marca of marcas) {
    const indice = partes.indexOf(marca, desde);
    if (indice < 0) {
      break;
    }
    encontradas.push(marca);
    desde = indice + 1;
  }
  return encontradas;
}

const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_NOMBRE_APP = NOMBRE;
});

afterEach(() => {
  if (nombreOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_NOMBRE_APP;
  } else {
    process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
  }
});

describe('bienvenida-E01 (tarea 1.3): la bienvenida con el aspecto de la Propuesta 04', () => {
  test('bienvenida-E01 — el nombre, el lema y la ilustración, en ese orden, y "Comenzar" fijo abajo', async () => {
    await abrir('/bienvenida');

    const marcas = [
      '#pantalla-inicial',
      '#contenido',
      NOMBRE,
      'Lema de prueba aspecto',
      '#ilustracion',
      '#acciones',
      'Comenzar de prueba',
    ];
    expect(enOrden(marcas)).toEqual(marcas);
  });

  test('bienvenida-E01 — el nombre va al centro con la escala mayor y el lema en el tono suave', async () => {
    await abrir('/bienvenida');

    expect(screen.getByRole('header', { name: NOMBRE })).toHaveStyle({
      color: color.semantico.texto,
      fontSize: tipografia.escala.titulo_mayor,
      textAlign: 'center',
    });
    expect(screen.getByText('Lema de prueba aspecto')).toHaveStyle({
      color: color.semantico.texto_suave,
      fontSize: tipografia.escala.cuerpo,
      textAlign: 'center',
    });
  });

  test('bienvenida-E01 — "Comenzar" es el botón principal, a todo lo ancho', async () => {
    await abrir('/bienvenida');

    const comenzar = within(screen.getByTestId('acciones')).getByRole('button', { name: 'Comenzar de prueba' });
    expect(estiloPlano(comenzar)).toMatchObject({ alignSelf: 'stretch', backgroundColor: color.semantico.accion });
  });
});

describe('bienvenida-E03 (tarea 1.3): las informativas con el aspecto de la Propuesta 04', () => {
  test.each([1, 2, 3])(
    'bienvenida-E03 — la informativa %i: la ilustración, el título y el texto, y abajo los puntos de avance y "Siguiente"',
    async (numero) => {
      await abrir(`/informativa/${numero}`);

      const marcas = [
        `#informativa-${numero}`,
        '#ilustracion',
        `Título de prueba ${numero}`,
        `Texto de prueba ${numero}`,
        '#acciones',
        '#puntos-de-avance',
        'Siguiente de prueba',
      ];
      expect(enOrden(marcas)).toEqual(marcas);
      expect(screen.getByRole('header', { name: `Título de prueba ${numero}` })).toHaveStyle({
        fontSize: tipografia.escala.titulo,
        textAlign: 'center',
      });
      expect(estiloPlano(screen.getByRole('button', { name: 'Siguiente de prueba' }))).toMatchObject({
        alignSelf: 'stretch',
        backgroundColor: color.semantico.accion,
      });
    },
  );

  test.each([1, 2, 3])(
    'bienvenida-E03 — en la informativa %i, su punto es el de la pantalla actual: más ancho y en el color de acción',
    async (numero) => {
      await abrir(`/informativa/${numero}`);

      for (const punto of [1, 2, 3]) {
        const elemento = screen.getByTestId(`punto-${punto}`, { includeHiddenElements: true });
        if (punto === numero) {
          expect(elemento).toHaveStyle({ width: espaciado[5], backgroundColor: color.semantico.accion });
        } else {
          expect(elemento).toHaveStyle({ width: espaciado[2], backgroundColor: color.semantico.borde_fuerte });
        }
      }
    },
  );

  test('bienvenida-E03 — los puntos de avance son adorno: el lector de pantalla no los anuncia', async () => {
    await abrir('/informativa/2');

    expect(screen.queryByTestId('puntos-de-avance')).not.toBeOnTheScreen();
    expect(screen.getByTestId('puntos-de-avance', { includeHiddenElements: true })).toBeTruthy();
  });
});

describe('bienvenida-E05 (tarea 1.3): lo que el mock dibuja y la app no trae', () => {
  test.each([['/bienvenida'], ['/informativa/1'], ['/informativa/3']])(
    'bienvenida-E05 — %s no lleva encabezado, ni la cinta del mock, ni "Texto provisional", ni "Saltar" o "Ya tengo cuenta"',
    async (ruta) => {
      await abrir(ruta);

      expect(screen.queryByTestId('encabezado', { includeHiddenElements: true })).not.toBeOnTheScreen();
      expect(
        screen.queryByText(/Ejemplo para ilustrar|Texto provisional|Saltar|Ya tengo cuenta/, {
          includeHiddenElements: true,
        }),
      ).not.toBeOnTheScreen();
    },
  );
});
