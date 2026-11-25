// Las piezas con las que se arman las pantallas de H1 con el aspecto de las imágenes de la Propuesta 04 de los mocks,
// módulo del usuario (C-230): tareas 2.3 de registro-e-inicio-de-sesion y 1.3 de bienvenida. Cada color y cada medida
// sale de theme.ts (ADR-0013): las pruebas comparan contra el tema, no contra números.
import { render, screen, userEvent, within } from '@testing-library/react-native';
import { StyleSheet, Text } from 'react-native';

import { Encabezado } from '../../src/piezas/Encabezado';
import { Enlace } from '../../src/piezas/Enlace';
import { Logotipo } from '../../src/piezas/Logotipo';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Presentacion } from '../../src/piezas/Presentacion';
import { Separador } from '../../src/piezas/Separador';
import { Titulo } from '../../src/piezas/Titulo';
import { tema } from '../../src/tema/theme';

// La primera pulsación con userEvent tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

const { accesibilidad, color, espaciado, tipografia } = tema;

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

describe('registro-E04 y registro-E09 (tarea 2.3): el encabezado con regreso y título', () => {
  test('registro-E04 — muestra el título de la pantalla como encabezado, en la superficie', async () => {
    await render(<Encabezado titulo="Crear cuenta" alRegresar={() => {}} />);

    expect(screen.getByRole('header', { name: 'Crear cuenta' })).toHaveStyle({
      color: color.semantico.texto,
      fontSize: tipografia.escala.cuerpo_grande,
      fontWeight: tipografia.peso.fuerte,
    });
    expect(screen.getByTestId('encabezado')).toHaveStyle({ backgroundColor: color.semantico.superficie });
  });

  test('registro-E04 — "Regresar" es un botón con el área táctil mínima y avisa al tocarlo', async () => {
    const alRegresar = jest.fn();
    const usuario = userEvent.setup();
    await render(<Encabezado titulo="Crear cuenta" alRegresar={alRegresar} />);

    const regresar = screen.getByRole('button', { name: 'Regresar' });
    await usuario.press(regresar);

    expect(alRegresar).toHaveBeenCalledTimes(1);
    expect(estiloPlano(regresar).minWidth).toBeGreaterThanOrEqual(accesibilidad.area_tactil_minima_dp);
    expect(estiloPlano(regresar).minHeight).toBeGreaterThanOrEqual(accesibilidad.area_tactil_minima_dp);
  });

  // El inicio de sesión abierto como primera pantalla es la raíz sin sesión (bienvenida-E04, C-237): no regresa a ningún
  // lado. El título queda donde está en las demás pantallas.
  test('registro-E09 — sin a dónde regresar, no hay "Regresar" y el lado izquierdo queda vacío, del mismo ancho', async () => {
    await render(<Encabezado titulo="Iniciar sesión" />);

    expect(screen.queryByRole('button', { name: 'Regresar' })).not.toBeOnTheScreen();
    expect(screen.getByRole('header', { name: 'Iniciar sesión' })).toBeOnTheScreen();
    expect(screen.getByTestId('sin-regreso')).toHaveStyle({ minWidth: accesibilidad.area_tactil_minima_dp });
  });

  test('registro-E09 — la pantalla con encabezado sin regreso lo pone arriba del contenido', async () => {
    await render(
      <Pantalla encabezado={{ titulo: 'Iniciar sesión' }}>
        <Text testID="contenido-de-prueba">Contenido</Text>
      </Pantalla>,
    );

    expect(enOrden(['#encabezado', 'Iniciar sesión', '#contenido-de-prueba'])).toEqual([
      '#encabezado',
      'Iniciar sesión',
      '#contenido-de-prueba',
    ]);
    expect(screen.queryByRole('button', { name: 'Regresar' })).not.toBeOnTheScreen();
  });

  test('registro-E04 — la pantalla con encabezado lo pone arriba del contenido, que lleva el margen del mock', async () => {
    await render(
      <Pantalla encabezado={{ titulo: 'Crear cuenta', alRegresar: () => {} }}>
        <Text testID="contenido-de-prueba">Contenido</Text>
      </Pantalla>,
    );

    expect(enOrden(['#encabezado', 'Crear cuenta', '#contenido-de-prueba'])).toEqual([
      '#encabezado',
      'Crear cuenta',
      '#contenido-de-prueba',
    ]);
    expect(screen.getByTestId('contenido')).toHaveStyle({ paddingHorizontal: espaciado[4], gap: espaciado[4] });
  });
});

// El logotipo en un archivo de nombre neutro (decisión 20 del acta del 2026-11-20, C-237): la marca no se escribe en el
// código (CLAUDE.md §5). En el repositorio, el archivo es el marcador neutro de la plantilla.
describe('registro-E09 (C-237): el logotipo', () => {
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;

  beforeEach(() => {
    process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba logotipo';
  });

  afterEach(() => {
    if (nombreOriginal === undefined) {
      delete process.env.EXPO_PUBLIC_NOMBRE_APP;
    } else {
      process.env.EXPO_PUBLIC_NOMBRE_APP = nombreOriginal;
    }
  });

  test('registro-E09 — es la imagen de assets/marca/logotipo.png y su texto alternativo es el nombre de la configuración', async () => {
    await render(<Logotipo />);

    const logotipo = screen.getByRole('image', { name: 'Nombre de prueba logotipo' });
    expect(logotipo.props.source).toEqual(require('../../assets/marca/logotipo.png'));
  });

  test('registro-E09 — va al centro, del tamaño del mock, sin deformarse', async () => {
    await render(<Logotipo />);

    const logotipo = screen.getByTestId('logotipo');
    expect(logotipo).toHaveStyle({ alignSelf: 'center', width: espaciado[8], height: espaciado[8] });
    expect(logotipo.props.resizeMode).toBe('contain');
  });
});

describe('registro-E04 (tarea 2.3): el separador "o con tu correo"', () => {
  test('registro-E04 — el texto va entre dos líneas y el lector de pantalla no lo anuncia', async () => {
    await render(<Separador texto="o con tu correo" />);

    expect(screen.queryByText('o con tu correo')).not.toBeOnTheScreen();
    const separador = screen.getByTestId('separador', { includeHiddenElements: true });
    expect(enOrden(['#separador', '#separador-linea', 'o con tu correo', '#separador-linea'])).toEqual([
      '#separador',
      '#separador-linea',
      'o con tu correo',
      '#separador-linea',
    ]);
    expect(estiloPlano(separador)).toMatchObject({ flexDirection: 'row', alignItems: 'center', gap: espaciado[3] });
  });
});

describe('registro-E09 y registro-E26 (tarea 2.3): los enlaces', () => {
  test('registro-E26 — "¿Olvidaste tu contraseña?" puede ir a la derecha sin perder el área táctil ni el color de acción', async () => {
    await render(<Enlace texto="¿Olvidaste tu contraseña?" alinear="fin" alTocar={() => {}} />);

    const enlace = screen.getByRole('link', { name: '¿Olvidaste tu contraseña?' });
    expect(estiloPlano(enlace).alignSelf).toBe('flex-end');
    expect(estiloPlano(enlace).minHeight).toBeGreaterThanOrEqual(accesibilidad.area_tactil_minima_dp);
    expect(screen.getByText('¿Olvidaste tu contraseña?')).toHaveStyle({ color: color.semantico.accion });
  });

  test('registro-E09 — con pregunta, es un solo enlace con las dos partes en su nombre, y avisa al tocarlo', async () => {
    const alTocar = jest.fn();
    const usuario = userEvent.setup();
    await render(<Enlace pregunta="¿No tienes cuenta?" texto="Regístrate" alinear="centro" alTocar={alTocar} />);

    await usuario.press(screen.getByRole('link', { name: '¿No tienes cuenta? Regístrate' }));

    expect(alTocar).toHaveBeenCalledTimes(1);
    expect(screen.getAllByRole('link')).toHaveLength(1);
    expect(screen.getByText('Regístrate')).toHaveStyle({ color: color.semantico.accion, fontSize: tipografia.escala.menor });
  });
});

describe('registro-E04, registro-E09 y bienvenida-E03 (tareas 2.3 y 1.3): el título y su texto', () => {
  test('registro-E04 — el título de pantalla usa la escala de título y el espaciado de letra de los titulares', async () => {
    await render(<Titulo alineacion="inicio">Crea tu cuenta</Titulo>);

    expect(screen.getByRole('header', { name: 'Crea tu cuenta' })).toHaveStyle({
      fontSize: tipografia.escala.titulo,
      lineHeight: tipografia.escala.titulo * tipografia.interlineado.apretado,
      letterSpacing: tipografia.escala.titulo * tipografia.espaciado_letra.titular,
      textAlign: 'left',
    });
  });

  test('bienvenida-E01 — el título mayor, el del nombre del producto, usa la escala mayor y va al centro', async () => {
    await render(<Titulo tamano="mayor">Nombre de prueba</Titulo>);

    expect(screen.getByRole('header', { name: 'Nombre de prueba' })).toHaveStyle({
      fontSize: tipografia.escala.titulo_mayor,
      textAlign: 'center',
    });
  });

  test('registro-E04 — la presentación junta el título y su texto, con el texto en el tono suave del cuerpo', async () => {
    await render(<Presentacion titulo="Crea tu cuenta" texto="Con tu correo." alineacion="inicio" />);

    const grupo = screen.getByTestId('presentacion');
    expect(grupo).toHaveStyle({ gap: espaciado[2] });
    expect(within(grupo).getByRole('header', { name: 'Crea tu cuenta' })).toBeOnTheScreen();
    expect(within(grupo).getByText('Con tu correo.')).toHaveStyle({
      color: color.semantico.texto_suave,
      fontSize: tipografia.escala.cuerpo,
      lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
      textAlign: 'left',
    });
  });
});
