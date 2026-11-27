// La letra de la marca en la app: adenda del 2026-11-21 de vault/arquitectura/adr/ADR-0013-adaptador-de-tokens.md
// (DD-131, punto 1; decisión 22 del acta del 2026-11-20) y tarea 3.8 de registro-e-inicio-de-sesion. Nunito para
// titulares y Source Sans 3 para cuerpo, las familias de tokens.json, se cargan con expo-font; la app no muestra su
// primera pantalla hasta que cargan y, si fallan, sigue con la letra del sistema. La monoespaciada del token se sustituye
// por la del sistema.
//
// expo-font se simula aquí: useFonts dice si las fuentes cargaron o fallaron, e isLoaded, si una letra está cargada. Los
// archivos de los paquetes valen, en Jest, 1 todos (el transformador de assets de jest-expo): cada uno se simula con su
// nombre para saber cuál se carga con qué nombre.
import path from 'node:path';

import * as SecureStore from 'expo-secure-store';
import { isLoaded, useFonts } from 'expo-font';
import { render, screen, waitFor } from '@testing-library/react-native';
import { renderRouter, screen as pantallaDelRouter } from 'expo-router/testing-library';
import { Platform, StyleSheet } from 'react-native';

import { Boton } from '../../src/piezas/Boton';
import { CampoDeTexto } from '../../src/piezas/CampoDeTexto';
import { Encabezado } from '../../src/piezas/Encabezado';
import { Enlace } from '../../src/piezas/Enlace';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Parrafo } from '../../src/piezas/Parrafo';
import { Separador } from '../../src/piezas/Separador';
import { Titulo } from '../../src/piezas/Titulo';
import { LETRAS_DE_LA_MARCA, letra, nombreDeLaLetra } from '../../src/tema/letra';
import { tema } from '../../src/tema/theme';

jest.mock('expo-font', () => ({
  ...jest.requireActual('expo-font'),
  useFonts: jest.fn(),
  isLoaded: jest.fn(),
}));
jest.mock('@expo-google-fonts/nunito/400Regular', () => ({ Nunito_400Regular: 'Nunito_400Regular.ttf' }));
jest.mock('@expo-google-fonts/nunito/500Medium', () => ({ Nunito_500Medium: 'Nunito_500Medium.ttf' }));
jest.mock('@expo-google-fonts/nunito/600SemiBold', () => ({ Nunito_600SemiBold: 'Nunito_600SemiBold.ttf' }));
jest.mock('@expo-google-fonts/nunito/700Bold', () => ({ Nunito_700Bold: 'Nunito_700Bold.ttf' }));
jest.mock('@expo-google-fonts/source-sans-3/400Regular', () => ({ SourceSans3_400Regular: 'SourceSans3_400Regular.ttf' }));
jest.mock('@expo-google-fonts/source-sans-3/500Medium', () => ({ SourceSans3_500Medium: 'SourceSans3_500Medium.ttf' }));
jest.mock('@expo-google-fonts/source-sans-3/600SemiBold', () => ({
  SourceSans3_600SemiBold: 'SourceSans3_600SemiBold.ttf',
}));
jest.mock('@expo-google-fonts/source-sans-3/700Bold', () => ({ SourceSans3_700Bold: 'SourceSans3_700Bold.ttf' }));

const APP = path.resolve(__dirname, '..', '..', 'app');

// El primer render carga expo-router y sus pantallas: con la caché de Babel vacía pasa de los 5 s de Jest.
jest.setTimeout(60_000);

const useFontsFalso = jest.mocked(useFonts);
const isLoadedFalso = jest.mocked(isLoaded);
// La simulación de __mocks__/expo-secure-store.ts, la misma instancia que importa la app: aquí no hay sesión.
const almacen = SecureStore as unknown as { __vaciar(): void };

const { color, tipografia } = tema;
const { familia, peso } = tipografia;

// Un nombre que no puede estar en el código: si aparece en pantalla, salió de la configuración.
const NOMBRE = 'Nombre de prueba letra';
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;

beforeEach(() => {
  almacen.__vaciar();
  process.env.EXPO_PUBLIC_NOMBRE_APP = NOMBRE;
  useFontsFalso.mockReturnValue([true, null]);
  isLoadedFalso.mockReturnValue(true);
});

afterEach(() => {
  if (nombreOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_NOMBRE_APP;
  } else {
    process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
  }
  jest.restoreAllMocks();
});

function estiloPlano(elemento: { props: { style?: unknown } }) {
  return StyleSheet.flatten(elemento.props.style as never) ?? {};
}

// Con la letra de la marca, el estilo nombra la letra del peso y no pide además el peso: el archivo ya es el de ese peso,
// y pedirlo otra vez hace que Android lo engrose de más.
function conLaLetra(uso: 'titulares' | 'cuerpo', nombreDelPeso: keyof typeof peso) {
  return { fontFamily: nombreDeLaLetra(familia[uso], peso[nombreDelPeso]), fontWeight: undefined };
}

// Sin ella, la letra del sistema con el peso de tokens.json.
function conLaDelSistema(nombreDelPeso: keyof typeof peso) {
  return { fontFamily: undefined, fontWeight: peso[nombreDelPeso] };
}

function letraDe(elemento: { props: { style?: unknown } }) {
  const { fontFamily, fontWeight } = estiloPlano(elemento) as { fontFamily?: string; fontWeight?: number };
  return { fontFamily, fontWeight };
}

describe('ADR-0013 (adenda del 2026-11-21): qué letra carga la app', () => {
  test('ADR-0013 — el nombre de cada letra es su familia de tokens.json, sin espacios, y su peso', () => {
    expect(nombreDeLaLetra(familia.titulares, peso.fuerte)).toBe('Nunito_700');
    expect(nombreDeLaLetra(familia.cuerpo, peso.regular)).toBe('SourceSans3_400');
  });

  test('ADR-0013 — carga cada peso de tokens.json de las familias de titulares y de cuerpo, nada más', () => {
    const esperadas = [familia.titulares, familia.cuerpo].flatMap((nombre) =>
      Object.values(peso).map((valor) => nombreDeLaLetra(nombre, valor)),
    );

    expect(Object.keys(LETRAS_DE_LA_MARCA).sort()).toEqual([...esperadas].sort());
  });

  test('ADR-0013 — cada letra es el archivo de su familia y de su peso, de los paquetes de @expo-google-fonts', () => {
    expect(LETRAS_DE_LA_MARCA).toEqual({
      Nunito_400: 'Nunito_400Regular.ttf',
      Nunito_500: 'Nunito_500Medium.ttf',
      Nunito_600: 'Nunito_600SemiBold.ttf',
      Nunito_700: 'Nunito_700Bold.ttf',
      SourceSans3_400: 'SourceSans3_400Regular.ttf',
      SourceSans3_500: 'SourceSans3_500Medium.ttf',
      SourceSans3_600: 'SourceSans3_600SemiBold.ttf',
      SourceSans3_700: 'SourceSans3_700Bold.ttf',
    });
  });

  test('ADR-0013 — la raíz de la app las carga con expo-font', async () => {
    await renderRouter(APP, { initialUrl: '/' });

    await waitFor(() => expect(pantallaDelRouter.getByTestId('pantalla-inicial')).toBeOnTheScreen());
    expect(useFontsFalso).toHaveBeenCalledWith(LETRAS_DE_LA_MARCA);
  });
});

describe('ADR-0013 (adenda del 2026-11-21): la primera pantalla espera a la letra', () => {
  test('ADR-0013 — mientras cargan las fuentes, la app no muestra su primera pantalla: solo el fondo', async () => {
    useFontsFalso.mockReturnValue([false, null]);

    await renderRouter(APP, { initialUrl: '/' });

    await waitFor(() => expect(pantallaDelRouter.getByTestId('cargando-la-letra')).toBeOnTheScreen());
    expect(pantallaDelRouter.getByTestId('cargando-la-letra')).toHaveStyle({
      flex: 1,
      backgroundColor: color.semantico.fondo,
    });
    expect(pantallaDelRouter.queryByTestId('pantalla-inicial')).not.toBeOnTheScreen();
    expect(pantallaDelRouter.queryByText(NOMBRE)).not.toBeOnTheScreen();
  });

  test('ADR-0013 — con las fuentes cargadas, la primera pantalla aparece con la letra de la marca', async () => {
    await renderRouter(APP, { initialUrl: '/' });

    const nombre = await pantallaDelRouter.findByRole('header', { name: NOMBRE });
    expect(letraDe(nombre)).toEqual(conLaLetra('titulares', 'fuerte'));
  });

  test('ADR-0013 — si las fuentes fallan, la primera pantalla aparece igual, con la letra del sistema', async () => {
    useFontsFalso.mockReturnValue([false, new Error('No cargó la letra.')]);
    isLoadedFalso.mockReturnValue(false);

    await renderRouter(APP, { initialUrl: '/' });

    expect(await pantallaDelRouter.findByTestId('pantalla-inicial')).toBeOnTheScreen();
    expect(letraDe(pantallaDelRouter.getByRole('header', { name: NOMBRE }))).toEqual(conLaDelSistema('fuerte'));
  });
});

describe('ADR-0013 (adenda del 2026-11-21): las piezas usan la letra de la marca', () => {
  test('ADR-0013 — los títulos y el del encabezado van en la letra de titulares, en su peso fuerte', async () => {
    await render(
      <>
        <Titulo>Hola de nuevo</Titulo>
        <Encabezado titulo="Crear cuenta" alRegresar={() => {}} />
      </>,
    );

    expect(letraDe(screen.getByRole('header', { name: 'Hola de nuevo' }))).toEqual(conLaLetra('titulares', 'fuerte'));
    expect(letraDe(screen.getByRole('header', { name: 'Crear cuenta' }))).toEqual(conLaLetra('titulares', 'fuerte'));
  });

  test('ADR-0013 — el párrafo, el separador, los errores y lo que se escribe van en la letra de cuerpo, regular', async () => {
    await render(
      <>
        <Parrafo>Con tu correo.</Parrafo>
        <Separador texto="o con tu correo" />
        <MensajeDeError texto="No se pudo." />
        <CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} error="Inválido." />
      </>,
    );

    const regular = conLaLetra('cuerpo', 'regular');
    expect(letraDe(screen.getByText('Con tu correo.'))).toEqual(regular);
    expect(letraDe(screen.getByText('o con tu correo', { includeHiddenElements: true }))).toEqual(regular);
    expect(letraDe(screen.getByText('No se pudo.'))).toEqual(regular);
    expect(letraDe(screen.getByText('Inválido.'))).toEqual(regular);
    expect(letraDe(screen.getByLabelText('Correo electrónico'))).toEqual(regular);
  });

  test('ADR-0013 — la etiqueta, el enlace y el botón secundario van en cuerpo semifuerte; el principal, en cuerpo fuerte', async () => {
    await render(
      <>
        <CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} />
        <Enlace pregunta="¿Ya tienes cuenta?" texto="Inicia sesión" alTocar={() => {}} />
        <Boton variante="secundario" onPress={() => {}}>
          Cerrar sesión
        </Boton>
        <Boton onPress={() => {}}>Crear cuenta</Boton>
      </>,
    );

    expect(letraDe(screen.getByText('Correo electrónico'))).toEqual(conLaLetra('cuerpo', 'semifuerte'));
    expect(letraDe(screen.getByText('Inicia sesión'))).toEqual(conLaLetra('cuerpo', 'semifuerte'));
    expect(letraDe(screen.getByText(/^¿Ya tienes cuenta\?/))).toEqual(conLaLetra('cuerpo', 'regular'));
    expect(letraDe(screen.getByText('Cerrar sesión'))).toEqual(conLaLetra('cuerpo', 'semifuerte'));
    expect(letraDe(screen.getByText('Crear cuenta'))).toEqual(conLaLetra('cuerpo', 'fuerte'));
  });

  test('ADR-0013 — sin la letra de la marca, cada pieza sigue con la del sistema y el peso de tokens.json', async () => {
    isLoadedFalso.mockReturnValue(false);

    await render(
      <>
        <Titulo>Hola de nuevo</Titulo>
        <Parrafo>Con tu correo.</Parrafo>
        <CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} />
        <Boton onPress={() => {}}>Crear cuenta</Boton>
      </>,
    );

    expect(letraDe(screen.getByRole('header', { name: 'Hola de nuevo' }))).toEqual(conLaDelSistema('fuerte'));
    expect(letraDe(screen.getByText('Con tu correo.'))).toEqual(conLaDelSistema('regular'));
    expect(letraDe(screen.getByText('Correo electrónico'))).toEqual(conLaDelSistema('semifuerte'));
    expect(letraDe(screen.getByLabelText('Correo electrónico'))).toEqual(conLaDelSistema('regular'));
    expect(letraDe(screen.getByText('Crear cuenta'))).toEqual(conLaDelSistema('fuerte'));
  });

  test('ADR-0013 — solo usa la letra de la marca si esa letra cargó: pregunta por su nombre', async () => {
    isLoadedFalso.mockImplementation((nombre) => nombre === nombreDeLaLetra(familia.titulares, peso.fuerte));

    await render(
      <>
        <Titulo>Hola de nuevo</Titulo>
        <Parrafo>Con tu correo.</Parrafo>
      </>,
    );

    expect(letraDe(screen.getByRole('header', { name: 'Hola de nuevo' }))).toEqual(conLaLetra('titulares', 'fuerte'));
    expect(letraDe(screen.getByText('Con tu correo.'))).toEqual(conLaDelSistema('regular'));
  });
});

// La primera familia de la pila de tokens.json es ui-monospace, que React Native no conoce (DD-131, punto 1).
describe('ADR-0013 (adenda del 2026-11-21): la monoespaciada es la del sistema', () => {
  test('ADR-0013 — tokens.json la empieza con una familia que React Native no conoce', () => {
    expect(familia.monoespaciada).toBe('ui-monospace');
  });

  test('ADR-0013 — en iPhone, la monoespaciada es Menlo, la del sistema, con el peso de tokens.json', () => {
    jest.replaceProperty(Platform, 'OS', 'ios');

    expect(letra('monoespaciada', 'regular')).toEqual({ fontFamily: 'Menlo', fontWeight: peso.regular });
  });

  test('ADR-0013 — en Android, la monoespaciada es monospace, la del sistema, con el peso de tokens.json', () => {
    jest.replaceProperty(Platform, 'OS', 'android');

    expect(letra('monoespaciada', 'fuerte')).toEqual({ fontFamily: 'monospace', fontWeight: peso.fuerte });
  });
});
