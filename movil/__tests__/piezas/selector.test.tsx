// Spec: openspec/changes/direcciones/specs/direcciones/spec.md, direcciones-E17, E19 y E20 (tareas 5.1 y 5.2). La pieza
// con la que se elige el municipio de la lista del territorio y la colonia de un código postal: Selector (decisión 29
// del acta del 2026-11-20, en openspec/changes/direcciones/design.md, Open Questions). Es un campo que se ve como
// CampoDeTexto y al tocarlo abre una hoja (Modal de React Native) con un buscador y la lista, sin dependencias nuevas.
// Se anuncia como botón con su etiqueta, su valor y si está abierto; cada opción, como opción de radio con su estado; y
// la elección se anuncia al lector de pantalla. Cada color y cada medida sale de theme.ts (ADR-0013): las pruebas
// comparan contra el tema, no contra números. Se prueba en iPhone y en Android.
import { act, fireEvent, render, screen, within } from '@testing-library/react-native';
import { useState } from 'react';
import { AccessibilityInfo, Platform, StyleSheet } from 'react-native';

import { Selector, type OpcionDelSelector } from '../../src/piezas/Selector';
import { tema } from '../../src/tema/theme';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

const { accesibilidad, color } = tema;
const PLATAFORMAS = ['ios', 'android'] as const;

// Municipios de prueba, con su estado en el texto, como los arma la dirección nueva (direcciones-E17).
const MUNICIPIOS: OpcionDelSelector[] = [
  { clave: '05017', texto: 'Matamoros, Coahuila de Zaragoza' },
  { clave: '05035', texto: 'Torreón, Coahuila de Zaragoza' },
  { clave: '10005', texto: 'Durango, Durango' },
  { clave: '10007', texto: 'Gómez Palacio, Durango' },
];

// Colonias de prueba, con su tipo de asentamiento como detalle (direcciones-E19).
const COLONIAS: OpcionDelSelector[] = [
  { clave: '0', texto: 'Colonia de prueba uno', detalle: 'Colonia' },
  { clave: '1', texto: 'Fraccionamiento de prueba', detalle: 'Fraccionamiento' },
];

function estiloPlano(elemento: { props: { style?: unknown } }) {
  return StyleSheet.flatten(elemento.props.style as never) ?? {};
}

interface Prueba {
  opciones?: OpcionDelSelector[];
  alElegir?: (opcion: OpcionDelSelector) => void;
  error?: string;
  deshabilitado?: boolean;
  otraOpcion?: { texto: string; alElegir: (busqueda: string) => void };
}

// El selector como lo usa una pantalla: guarda la elegida y muestra su texto.
function SelectorDePrueba({ opciones = MUNICIPIOS, alElegir, error, deshabilitado, otraOpcion }: Prueba) {
  const [elegida, setElegida] = useState<OpcionDelSelector | null>(null);
  return (
    <Selector
      nombre="municipio"
      etiqueta="Municipio"
      valor={elegida?.texto ?? ''}
      marcador="Elige tu municipio"
      etiquetaDeLaBusqueda="Buscar municipio"
      opciones={opciones}
      elegida={elegida?.clave ?? null}
      alElegir={(opcion) => {
        setElegida(opcion);
        alElegir?.(opcion);
      }}
      error={error}
      deshabilitado={deshabilitado}
      otraOpcion={otraOpcion}
    />
  );
}

function campo() {
  return screen.getByRole('button', { name: 'Municipio' });
}

function hoja() {
  return within(screen.getByTestId('hoja-municipio'));
}

async function abrir() {
  await fireEvent.press(campo());
}

let anuncio: jest.SpyInstance;

beforeEach(() => {
  anuncio = jest.spyOn(AccessibilityInfo, 'announceForAccessibilityWithOptions').mockImplementation(() => {});
  // El preset de React Native ya la simula: spyOn devuelve la misma, con las llamadas de las pruebas anteriores.
  anuncio.mockClear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('direcciones-E17: la pieza Selector, un campo que abre la lista', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) sin elegir, se ve como un campo de texto con su etiqueta y el marcador, y se anuncia como botón cerrado con su valor',
    async (plataforma) => {
      jest.replaceProperty(Platform, 'OS', plataforma);
      await render(<SelectorDePrueba />);

      expect(within(screen.getByTestId('campo-municipio')).getByText('Municipio')).toBeOnTheScreen();
      expect(screen.getByText('Elige tu municipio')).toHaveStyle({ color: color.semantico.texto_suave });
      expect(campo()).toHaveAccessibilityValue({ text: 'Elige tu municipio' });
      expect(campo()).toBeCollapsed();
      expect(campo()).toBeEnabled();
      // La caja es la de CampoDeTexto: el área táctil mínima, el borde de control y la superficie.
      expect(estiloPlano(campo()).minHeight).toBeGreaterThanOrEqual(accesibilidad.area_tactil_minima_dp);
      expect(campo()).toHaveStyle({
        borderColor: color.semantico.borde_fuerte,
        borderWidth: tema.grosor.control,
        backgroundColor: color.semantico.superficie,
      });
      // La hoja no está abierta.
      expect(screen.queryByTestId('hoja-municipio')).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) al tocarlo abre la hoja con su título, el buscador y las opciones de radio, ninguna elegida',
    async (plataforma) => {
      jest.replaceProperty(Platform, 'OS', plataforma);
      await render(<SelectorDePrueba />);

      await abrir();

      expect(hoja().getByRole('header', { name: 'Municipio' })).toBeOnTheScreen();
      expect(hoja().getByLabelText('Buscar municipio')).toHaveDisplayValue('');
      const opciones = hoja().getAllByRole('radio');
      expect(opciones.map((opcion) => opcion.props.accessibilityLabel)).toEqual(MUNICIPIOS.map(({ texto }) => texto));
      for (const opcion of opciones) {
        expect(opcion).not.toBeChecked();
        expect(estiloPlano(opcion).minHeight).toBeGreaterThanOrEqual(accesibilidad.area_tactil_minima_dp);
      }
      expect(hoja().getByRole('button', { name: 'Cerrar' })).toBeOnTheScreen();
      expect(campo()).toBeExpanded();
    },
  );

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) al elegir una opción avisa con ella, cierra la hoja, el campo la muestra y el lector de pantalla lo anuncia',
    async (plataforma) => {
      jest.replaceProperty(Platform, 'OS', plataforma);
      const alElegir = jest.fn();
      await render(<SelectorDePrueba alElegir={alElegir} />);
      await abrir();

      await fireEvent.press(hoja().getByRole('radio', { name: 'Torreón, Coahuila de Zaragoza' }));

      expect(alElegir).toHaveBeenCalledTimes(1);
      expect(alElegir).toHaveBeenCalledWith(MUNICIPIOS[1]);
      expect(screen.queryByTestId('hoja-municipio')).not.toBeOnTheScreen();
      expect(campo()).toHaveAccessibilityValue({ text: 'Torreón, Coahuila de Zaragoza' });
      expect(campo()).toBeCollapsed();
      expect(screen.getByText('Torreón, Coahuila de Zaragoza')).toHaveStyle({ color: color.semantico.texto });
      // En cola: en iPhone no corta lo que el lector de pantalla dice al cerrarse la hoja.
      expect(anuncio).toHaveBeenCalledWith('Municipio: Torreón, Coahuila de Zaragoza', { queue: true });
    },
  );

  test('direcciones-E17 — al volver a abrirla, la elegida se anuncia elegida y las demás no', async () => {
    await render(<SelectorDePrueba />);
    await abrir();
    await fireEvent.press(hoja().getByRole('radio', { name: 'Durango, Durango' }));

    await abrir();

    expect(hoja().getByRole('radio', { name: 'Durango, Durango' })).toBeChecked();
    expect(hoja().getByRole('radio', { name: 'Torreón, Coahuila de Zaragoza' })).not.toBeChecked();
  });

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) el buscador filtra sin importar mayúsculas ni acentos, y sin coincidencias lo dice',
    async (plataforma) => {
      jest.replaceProperty(Platform, 'OS', plataforma);
      await render(<SelectorDePrueba />);
      await abrir();

      await fireEvent.changeText(hoja().getByLabelText('Buscar municipio'), 'TORREON');
      expect(hoja().getAllByRole('radio').map((opcion) => opcion.props.accessibilityLabel)).toEqual([
        'Torreón, Coahuila de Zaragoza',
      ]);

      await fireEvent.changeText(hoja().getByLabelText('Buscar municipio'), 'durango');
      expect(hoja().getAllByRole('radio').map((opcion) => opcion.props.accessibilityLabel)).toEqual([
        'Durango, Durango',
        'Gómez Palacio, Durango',
      ]);

      await fireEvent.changeText(hoja().getByLabelText('Buscar municipio'), 'Saltillo');
      expect(hoja().queryAllByRole('radio')).toHaveLength(0);
      expect(hoja().getByText('Sin resultados.')).toBeOnTheScreen();
    },
  );

  test('direcciones-E17 — el buscador también busca en el detalle, que la opción muestra y anuncia', async () => {
    await render(<SelectorDePrueba opciones={COLONIAS} />);
    await abrir();

    expect(hoja().getByRole('radio', { name: 'Colonia de prueba uno, Colonia' })).toBeOnTheScreen();
    expect(hoja().getByText('Fraccionamiento')).toHaveStyle({ color: color.semantico.texto_suave });
    await fireEvent.changeText(hoja().getByLabelText('Buscar municipio'), 'fraccionamiento');

    expect(hoja().getAllByRole('radio').map((opcion) => opcion.props.accessibilityLabel)).toEqual([
      'Fraccionamiento de prueba, Fraccionamiento',
    ]);
  });

  test.each(PLATAFORMAS)(
    'direcciones-E17 — (%s) "Cerrar" cierra la hoja sin elegir, y al volver a abrirla la búsqueda empieza vacía',
    async (plataforma) => {
      jest.replaceProperty(Platform, 'OS', plataforma);
      const alElegir = jest.fn();
      await render(<SelectorDePrueba alElegir={alElegir} />);
      await abrir();
      await fireEvent.changeText(hoja().getByLabelText('Buscar municipio'), 'torreón');

      await fireEvent.press(hoja().getByRole('button', { name: 'Cerrar' }));

      expect(screen.queryByTestId('hoja-municipio')).not.toBeOnTheScreen();
      expect(alElegir).not.toHaveBeenCalled();
      expect(campo()).toHaveAccessibilityValue({ text: 'Elige tu municipio' });
      await abrir();
      expect(hoja().getByLabelText('Buscar municipio')).toHaveDisplayValue('');
      expect(hoja().getAllByRole('radio')).toHaveLength(MUNICIPIOS.length);
    },
  );

  // El botón de regreso de Android y, en iPhone, deslizar la hoja hacia abajo: React Native llama a onRequestClose.
  test.each(PLATAFORMAS)('direcciones-E17 — (%s) regresar o deslizar la hoja la cierra sin elegir', async (plataforma) => {
    jest.replaceProperty(Platform, 'OS', plataforma);
    const alElegir = jest.fn();
    await render(<SelectorDePrueba alElegir={alElegir} />);
    await abrir();

    await act(() => screen.getByTestId('hoja-municipio').props.onRequestClose());

    expect(screen.queryByTestId('hoja-municipio')).not.toBeOnTheScreen();
    expect(alElegir).not.toHaveBeenCalled();
    expect(campo()).toBeCollapsed();
  });

  test('direcciones-E17 — deshabilitado no abre la hoja y se anuncia deshabilitado', async () => {
    await render(<SelectorDePrueba deshabilitado />);

    await abrir();

    expect(campo()).toBeDisabled();
    expect(screen.queryByTestId('hoja-municipio')).not.toBeOnTheScreen();
  });

  test('direcciones-E17 — el error va debajo del campo, se anuncia y pinta el borde del color de error', async () => {
    await render(<SelectorDePrueba error="Elige un municipio de la lista." />);

    expect(within(screen.getByTestId('campo-municipio')).getByRole('alert')).toHaveTextContent(
      'Elige un municipio de la lista.',
    );
    expect(campo()).toHaveStyle({ borderColor: color.estado.error });
  });
});

describe('direcciones-E19 y E20: la otra opción, para escribirla a mano', () => {
  test.each(PLATAFORMAS)(
    'direcciones-E20 — (%s) la otra opción va al final de la lista y, al tocarla, cierra la hoja y avisa con lo que se buscó',
    async (plataforma) => {
      jest.replaceProperty(Platform, 'OS', plataforma);
      const aMano = jest.fn();
      const alElegir = jest.fn();
      await render(
        <SelectorDePrueba
          opciones={COLONIAS}
          alElegir={alElegir}
          otraOpcion={{ texto: 'No aparece mi colonia', alElegir: aMano }}
        />,
      );
      await abrir();
      expect(hoja().getByRole('button', { name: 'No aparece mi colonia' })).toBeOnTheScreen();

      await fireEvent.changeText(hoja().getByLabelText('Buscar municipio'), '  Colonia que no está  ');
      // Sin coincidencias, la otra opción sigue ahí.
      expect(hoja().queryAllByRole('radio')).toHaveLength(0);
      await fireEvent.press(hoja().getByRole('button', { name: 'No aparece mi colonia' }));

      expect(aMano).toHaveBeenCalledWith('Colonia que no está');
      expect(alElegir).not.toHaveBeenCalled();
      expect(screen.queryByTestId('hoja-municipio')).not.toBeOnTheScreen();
    },
  );

  test('direcciones-E19 — sin otra opción, la hoja solo trae la lista', async () => {
    await render(<SelectorDePrueba opciones={COLONIAS} />);
    await abrir();

    expect(hoja().queryByRole('button', { name: 'No aparece mi colonia' })).not.toBeOnTheScreen();
    expect(hoja().getAllByRole('radio')).toHaveLength(COLONIAS.length);
  });
});
