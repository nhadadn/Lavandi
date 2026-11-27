// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, administracion-app-E45
// (tarea 18.3). Las cinco categorías del A.5 (Operaciones, Usuarios, Socios, Financiero y Geográfico) van en la pieza
// Pestanas (ADR-0022, decisión 14), que hasta ahora era una fila sin desplazamiento: en un teléfono pequeño no caben.
// Aquí se desplaza de lado cuando sus pestañas no caben en el ancho, y cuando caben ocupan el ancho como antes. Como en
// la maqueta de la Propuesta 06 de la administración (41, 46 y 48; NOTAS.md, puntos 5 y 7 de lo abierto y de la
// verificación final), un desvanecido de 64 puntos marca la orilla por donde sigue la fila, y la fila se queda al
// principio o al final, lo que deje a la elegida entera y fuera del desvanecido. La pestaña se sigue anunciando como
// pestaña y la elegida como seleccionada.
//
// Este escenario lo cumple del todo la pantalla de indicadores (tarea 18.4): aquí se prueba la pieza. La dirección nueva
// también la usa (direcciones-E01 y E04), y sus pruebas siguen sin cambios. Jest no mide: las pruebas le dan a la fila
// las medidas que daría el teléfono, con los eventos de layout, de tamaño del contenido y de desplazamiento. Cada color
// y cada medida sale de theme.ts (ADR-0013): las pruebas comparan contra el tema, no contra números.
import { act, fireEvent, render, screen } from '@testing-library/react-native';
import { Profiler, useState } from 'react';
import { ScrollView, StyleSheet } from 'react-native';

import { Pestanas } from '../../src/piezas/Pestanas';
import { tema } from '../../src/tema/theme';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

// El que lee el degradado de backgroundImage antes de dibujarlo en el teléfono. React Native no publica sus tipos.
const { default: processBackgroundImage } = jest.requireActual<{
  default: (valor: string) => { type: string; colorStops: { color: unknown }[] }[];
}>('react-native/Libraries/StyleSheet/processBackgroundImage');

const { accesibilidad, color, espaciado } = tema;

// Las cinco categorías del A.5, como las nombra la pantalla de indicadores (ADR-0022, decisión 14).
const CATEGORIAS = [
  { clave: 'operaciones', texto: 'Operaciones' },
  { clave: 'usuarios', texto: 'Usuarios' },
  { clave: 'socios', texto: 'Socios' },
  { clave: 'financiero', texto: 'Financiero' },
  { clave: 'geografico', texto: 'Geográfico' },
] as const;
type Categoria = (typeof CATEGORIAS)[number]['clave'];

// Dónde queda cada pestaña dentro de la fila y cuánto mide, como lo mediría un teléfono: 520 puntos en total.
const LUGARES: Record<Categoria, { x: number; ancho: number }> = {
  operaciones: { x: 0, ancho: 130 },
  usuarios: { x: 130, ancho: 100 },
  socios: { x: 230, ancho: 90 },
  financiero: { x: 320, ancho: 100 },
  geografico: { x: 420, ancho: 100 },
};
const ANCHO_DE_LAS_CINCO = 520;
const ALTO = accesibilidad.area_tactil_minima_dp;
// El texto de cada pestaña mide lo que la pestaña menos su relleno de los dos lados.
const RELLENO = espaciado[3];
// La más ancha, Operaciones, mide 130: las cinco caben en partes iguales desde 5 × 130 más el borde de los dos lados.
const ANCHO_PAREJO = 5 * 130 + 2 * tema.grosor.control;

function estiloPlano(elemento: { props: { style?: unknown } }) {
  return StyleSheet.flatten(elemento.props.style as never) ?? {};
}

// Las pestañas como las usa una pantalla: guarda la elegida.
function PestanasDePrueba({
  inicial = 'operaciones',
  sangrado,
  conSeparadores,
}: {
  inicial?: Categoria;
  sangrado?: number;
  conSeparadores?: boolean;
}) {
  const [elegida, setElegida] = useState<Categoria>(inicial);
  return (
    <Pestanas
      pestanas={CATEGORIAS}
      elegida={elegida}
      alElegir={setElegida}
      sangrado={sangrado}
      conSeparadores={conSeparadores}
    />
  );
}

function fila() {
  return screen.getByTestId('pestanas-desplazable');
}

// El desvanecido de una orilla, si está. Es adorno escondido del lector de pantalla: se busca también entre lo escondido.
function desvanecido(orilla: 'izquierdo' | 'derecho') {
  return screen.queryByTestId(`desvanecido-${orilla}`, { includeHiddenElements: true });
}

function pestana(texto: string) {
  return screen.getByRole('tab', { name: texto });
}

// Le da a la fila las medidas del teléfono: el ancho que se ve, el de su contenido, dónde queda cada pestaña y cuánto
// mide su texto.
async function medir(anchoVisible: number, anchoDelContenido: number) {
  for (const { clave, texto } of CATEGORIAS) {
    const { x, ancho } = LUGARES[clave];
    await fireEvent(pestana(texto), 'layout', { nativeEvent: { layout: { x, y: 0, width: ancho, height: ALTO } } });
    await fireEvent(screen.getByText(texto), 'layout', {
      nativeEvent: { layout: { x: RELLENO, y: 0, width: ancho - 2 * RELLENO, height: ALTO } },
    });
  }
  await fireEvent(fila(), 'layout', { nativeEvent: { layout: { x: 0, y: 0, width: anchoVisible, height: ALTO } } });
  await fireEvent(fila(), 'contentSizeChange', anchoDelContenido, ALTO);
}

async function desplazarA(x: number) {
  await fireEvent.scroll(fila(), {
    nativeEvent: {
      contentOffset: { x, y: 0 },
      contentSize: { width: ANCHO_DE_LAS_CINCO, height: ALTO },
      layoutMeasurement: { width: 300, height: ALTO },
    },
  });
}

describe('administracion-app-E45 (tarea 18.3): las pestañas se desplazan de lado cuando no caben', () => {
  let llevarA: jest.SpyInstance;

  beforeEach(() => {
    llevarA = jest.spyOn(ScrollView.prototype, 'scrollTo');
  });

  afterEach(() => {
    llevarA.mockRestore();
  });

  test('administracion-app-E45 — cuando cada una cabe en una parte igual, se reparten el ancho por igual, como antes, sin desplazarse ni desvanecido', async () => {
    await render(<PestanasDePrueba />);
    // La fila crece hasta el ancho que se ve: su contenido mide lo mismo.
    await medir(ANCHO_PAREJO, ANCHO_PAREJO);

    expect(fila().props.horizontal).toBe(true);
    expect(fila().props.scrollEnabled).toBe(false);
    expect(fila().props.contentContainerStyle).toMatchObject({ flexGrow: 1 });
    expect(screen.getByTestId('pestanas').props.accessibilityRole).toBe('tablist');
    expect(screen.getByTestId('pestanas')).toHaveStyle({ flexGrow: 1, flexDirection: 'row' });
    // Las cinco con la misma parte del ancho de adentro del borde, sin encogerse.
    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto))).toMatchObject({ minWidth: 130, flexGrow: 1, flexShrink: 0 });
    }
    expect(desvanecido('izquierdo')).not.toBeOnTheScreen();
    expect(desvanecido('derecho')).not.toBeOnTheScreen();
  });

  test('administracion-app-E45 — si la elegida, en negritas, ya no cabe en su parte igual, cada una vuelve a medir lo que su texto', async () => {
    await render(<PestanasDePrueba />);
    await medir(ANCHO_PAREJO, ANCHO_PAREJO);
    expect(estiloPlano(pestana('Socios')).minWidth).toBe(130);

    // Socios elegida mide más que la parte igual: su texto se mide en un renglón, porque la pestaña no se encoge.
    await fireEvent.press(pestana('Socios'));
    await fireEvent(screen.getByText('Socios'), 'layout', {
      nativeEvent: { layout: { x: RELLENO, y: 0, width: 140 - 2 * RELLENO, height: ALTO } },
    });

    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto)).minWidth).toBeUndefined();
      expect(estiloPlano(pestana(texto))).toMatchObject({ flexGrow: 1, flexShrink: 0 });
    }
  });

  test('administracion-app-E45 — cuando caben juntas pero no en partes iguales, cada una mide lo que su texto y crecen hasta llenar la fila, sin desplazarse', async () => {
    await render(<PestanasDePrueba />);
    // 560 da para las cinco a su medida (520), no para cinco de 130.
    await medir(560, 560);

    expect(fila().props.scrollEnabled).toBe(false);
    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto))).toMatchObject({ flexGrow: 1, flexShrink: 0 });
      expect(estiloPlano(pestana(texto)).minWidth).toBeUndefined();
    }
    expect(desvanecido('izquierdo')).not.toBeOnTheScreen();
    expect(desvanecido('derecho')).not.toBeOnTheScreen();
  });

  test('administracion-app-E45 — hasta medir los textos, cada una mide lo que su texto: nunca se parte una palabra', async () => {
    await render(<PestanasDePrueba />);

    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto))).toMatchObject({ flexGrow: 1, flexShrink: 0 });
      expect(estiloPlano(pestana(texto)).minWidth).toBeUndefined();
    }
  });

  test('administracion-app-E45 — cuando no caben, la fila se desplaza de lado y un desvanecido marca la orilla por donde sigue', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    expect(fila().props.scrollEnabled).toBe(true);
    // Al principio, la fila sigue a la derecha.
    expect(desvanecido('izquierdo')).not.toBeOnTheScreen();
    expect(desvanecido('derecho')).toHaveStyle({ width: espaciado[8], right: 0 });
    expect(desvanecido('derecho')?.props.pointerEvents).toBe('none');

    // A la mitad, sigue por los dos lados.
    await desplazarA(100);
    expect(desvanecido('izquierdo')).toHaveStyle({ width: espaciado[8], left: 0 });
    expect(desvanecido('derecho')).toBeOnTheScreen();

    // Al final, solo a la izquierda.
    await desplazarA(ANCHO_DE_LAS_CINCO - 300);
    expect(desvanecido('izquierdo')).toBeOnTheScreen();
    expect(desvanecido('derecho')).not.toBeOnTheScreen();
  });

  test('administracion-app-E45 — el desvanecido va del fondo transparente al color del fondo, sin anunciarse', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    const derecho = desvanecido('derecho')!;
    const degradado = String(estiloPlano(derecho).backgroundImage);
    // Del color del fondo transparente, casi entero a la mitad y entero a tres cuartos (maqueta 41), con el color del
    // tema y su transparencia en #RRGGBBAA: ningún color escrito a mano (ADR-0013).
    const fondo = color.semantico.fondo;
    // tokens.json no trae el fondo transparente: la pieza le pega la transparencia al fondo, y eso solo sirve si el
    // fondo es #RRGGBB.
    expect(fondo).toMatch(/^#[0-9a-f]{6}$/i);
    expect(degradado).toBe(`linear-gradient(to right, ${fondo}00, ${fondo}eb 55%, ${fondo} 75%)`);
    // React Native lo entiende: un degradado lineal con sus tres pasos, ninguno descartado.
    const [procesado] = processBackgroundImage(degradado);
    expect(procesado).toMatchObject({ type: 'linear-gradient' });
    expect(procesado.colorStops).toHaveLength(3);
    expect(procesado.colorStops.every(({ color: paso }) => paso != null)).toBe(true);
    expect(derecho.props.accessibilityElementsHidden).toBe(true);
    expect(derecho.props.importantForAccessibility).toBe('no-hide-descendants');
  });

  test('administracion-app-E45 — la fila se queda al principio o al final, lo que deje a la elegida entera y fuera del desvanecido', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    // Operaciones cabe entera al principio, antes del desvanecido derecho. Al abrir, la fila se coloca sin deslizarse.
    expect(llevarA).toHaveBeenLastCalledWith({ x: 0, animated: false });

    // Financiero no cabe al principio: la fila va al final, y queda después del desvanecido izquierdo.
    await fireEvent.press(pestana('Financiero'));
    expect(llevarA).toHaveBeenLastCalledWith({ x: ANCHO_DE_LAS_CINCO - 300, animated: true });

    // Usuarios cabe al principio: la fila vuelve al principio.
    await fireEvent.press(pestana('Usuarios'));
    expect(llevarA).toHaveBeenLastCalledWith({ x: 0, animated: true });
  });

  test('administracion-app-E45 — si la elegida no queda entera ni al principio ni al final, la fila la deja junto al desvanecido izquierdo, entera', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    // Socios (de 230 a 320) cae en el desvanecido derecho al principio y en el izquierdo al final (220).
    await fireEvent.press(pestana('Socios'));

    expect(llevarA).toHaveBeenLastCalledWith({ x: LUGARES.socios.x - espaciado[8], animated: true });
  });

  test('administracion-app-E45 — al abrir con una pestaña del final elegida, la fila ya está al final, sin deslizarse; al elegir otra, se desliza', async () => {
    await render(<PestanasDePrueba inicial="geografico" />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    expect(llevarA).toHaveBeenCalledTimes(1);
    expect(llevarA).toHaveBeenLastCalledWith({ x: ANCHO_DE_LAS_CINCO - 300, animated: false });

    await fireEvent.press(pestana('Operaciones'));
    expect(llevarA).toHaveBeenLastCalledWith({ x: 0, animated: true });
  });

  test('administracion-app-E45 — al desplazarla, la fila solo se vuelve a dibujar cuando llega a una orilla o sale de ella', async () => {
    const dibujos = jest.fn();
    await render(
      <Profiler id="pestanas" onRender={dibujos}>
        <PestanasDePrueba />
      </Profiler>,
    );
    await medir(300, ANCHO_DE_LAS_CINCO);

    // Sale de la orilla izquierda: aparece su desvanecido.
    await desplazarA(100);
    expect(desvanecido('izquierdo')).toBeOnTheScreen();
    const alSalir = dibujos.mock.calls.length;

    // A la mitad, los desvanecidos no cambian: no se vuelve a dibujar.
    await desplazarA(120);
    await desplazarA(140);
    expect(dibujos).toHaveBeenCalledTimes(alSalir);

    // Llega a la orilla derecha: su desvanecido se va.
    await desplazarA(ANCHO_DE_LAS_CINCO - 300);
    expect(dibujos.mock.calls.length).toBeGreaterThan(alSalir);
    expect(desvanecido('derecho')).not.toBeOnTheScreen();
  });

  test('administracion-app-E45 — con el sangrado de la pantalla, la fila se sale hasta la orilla y la elegida queda entera fuera del desvanecido', async () => {
    // La pantalla de indicadores le da su relleno, como .desliza en las maquetas 41, 46 y 48: la fila mide 32 más y su
    // contenido también.
    const sangrado = espaciado[4];
    await render(<PestanasDePrueba sangrado={sangrado} />);
    await medir(290, ANCHO_DE_LAS_CINCO + 2 * sangrado);

    expect(screen.getByTestId('pestanas-marco')).toHaveStyle({ marginHorizontal: -sangrado });
    expect(StyleSheet.flatten(fila().props.contentContainerStyle)).toMatchObject({
      flexGrow: 1,
      paddingHorizontal: sangrado,
    });
    expect(fila().props.scrollEnabled).toBe(true);
    expect(desvanecido('derecho')).toHaveStyle({ right: 0 });
    expect(llevarA).toHaveBeenLastCalledWith({ x: 0, animated: false });

    // Financiero empieza 16 después de donde empieza en la fila: al final queda 74 adentro, fuera del desvanecido.
    await fireEvent.press(pestana('Financiero'));
    expect(llevarA).toHaveBeenLastCalledWith({ x: ANCHO_DE_LAS_CINCO + 2 * sangrado - 290, animated: true });
  });

  test('administracion-app-E45 — con el sangrado, cuando cada una cabe en una parte igual, se reparten el ancho que quedaría sin él', async () => {
    const sangrado = espaciado[4];
    await render(<PestanasDePrueba sangrado={sangrado} />);
    await medir(ANCHO_PAREJO + 2 * sangrado, ANCHO_PAREJO + 2 * sangrado);

    expect(fila().props.scrollEnabled).toBe(false);
    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto))).toMatchObject({ minWidth: 130, flexGrow: 1, flexShrink: 0 });
    }
  });

  test('administracion-app-E45 — con separadores, una línea del color del borde entre cada pestaña y la anterior, que cuenta en la parte igual', async () => {
    await render(<PestanasDePrueba conSeparadores />);
    // Con su línea, cada una pide 131: a 652 ya no caben en partes iguales.
    await medir(ANCHO_PAREJO, ANCHO_PAREJO);

    expect(estiloPlano(pestana('Operaciones')).borderLeftWidth).toBeUndefined();
    for (const { texto } of CATEGORIAS.slice(1)) {
      expect(pestana(texto)).toHaveStyle({
        borderLeftWidth: tema.grosor.control,
        borderLeftColor: color.semantico.borde,
      });
    }
    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto)).minWidth).toBeUndefined();
    }

    // Con 5 más, sí: cada una mide 131.
    await fireEvent(fila(), 'layout', {
      nativeEvent: { layout: { x: 0, y: 0, width: ANCHO_PAREJO + 5, height: ALTO } },
    });
    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto)).minWidth).toBe(131);
    }
  });

  test('administracion-app-E45 — sin sangrado ni separadores, como la usa la dirección nueva, la fila se queda dentro del relleno y sin líneas', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    expect(estiloPlano(screen.getByTestId('pestanas-marco')).marginHorizontal).toBeUndefined();
    expect(fila().props.contentContainerStyle).toEqual({ flexGrow: 1 });
    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto)).borderLeftWidth).toBeUndefined();
    }
  });

  test('administracion-app-E45 — cuando caben, la fila no se mueve al elegir', async () => {
    await render(<PestanasDePrueba />);
    await medir(560, 560);

    await fireEvent.press(pestana('Geográfico'));

    expect(llevarA).not.toHaveBeenCalled();
  });

  test('administracion-app-E45 — cada pestaña se anuncia como pestaña de la lista, y la elegida como seleccionada, también al desplazarse', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);

    expect(screen.getAllByRole('tab')).toHaveLength(CATEGORIAS.length);
    expect(pestana('Operaciones')).toBeSelected();
    expect(pestana('Socios')).not.toBeSelected();

    await desplazarA(ANCHO_DE_LAS_CINCO - 300);
    await fireEvent.press(pestana('Geográfico'));

    expect(pestana('Geográfico')).toBeSelected();
    expect(pestana('Operaciones')).not.toBeSelected();
  });

  test('administracion-app-E45 — cada pestaña guarda el área táctil mínima y la elegida va en el color de acción', async () => {
    await render(<PestanasDePrueba inicial="socios" />);

    for (const { texto } of CATEGORIAS) {
      expect(estiloPlano(pestana(texto)).minHeight).toBeGreaterThanOrEqual(accesibilidad.area_tactil_minima_dp);
    }
    expect(pestana('Socios')).toHaveStyle({ backgroundColor: color.semantico.accion });
    expect(screen.getByText('Socios')).toHaveStyle({ color: color.semantico.accion_texto });
    expect(screen.getByText('Usuarios')).toHaveStyle({ color: color.semantico.texto });
  });

  test('administracion-app-E45 — si cambian las medidas y ya caben, el desvanecido se va y la fila deja de desplazarse', async () => {
    await render(<PestanasDePrueba />);
    await medir(300, ANCHO_DE_LAS_CINCO);
    expect(fila().props.scrollEnabled).toBe(true);

    // El teléfono se gira: ahora sobra ancho, y el contenido crece hasta él.
    await act(async () => {
      await fireEvent(fila(), 'layout', { nativeEvent: { layout: { x: 0, y: 0, width: 700, height: ALTO } } });
      await fireEvent(fila(), 'contentSizeChange', 700, ALTO);
    });

    expect(fila().props.scrollEnabled).toBe(false);
    expect(desvanecido('derecho')).not.toBeOnTheScreen();
  });
});
