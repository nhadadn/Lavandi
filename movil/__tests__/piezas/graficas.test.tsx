// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, administracion-app-E46, E47,
// E48 y E49 (tareas 18.1 y 18.2). Las piezas con las que la pantalla de indicadores dibuja la visualización que el A.5
// nombra para cada indicador (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5): barras, tabla comparativa, dona, línea de
// tendencia, indicador destacado con tendencia y ranking con semáforo. Se arman con las vistas de React Native y los
// tokens, sin librería (ADR-0022, alternativa 5; ADR-0013), con la forma de la maqueta de la Propuesta 06 de la
// administración (41 y 43 a 48):
// - la línea es un punto por periodo y, entre cada par, un segmento: una vista del largo de la distancia entre los
//   puntos, girada al ángulo entre ellos;
// - la dona es un anillo de 144 marcas cortas alrededor de un centro, cada una girada a su ángulo y del color de la
//   porción en que cae;
// - cada pieza trae sus cifras también en texto, para leerse sin el dibujo y sin depender del color, y se anuncia al
//   lector de pantalla.
//
// Estos escenarios los cumple del todo la pantalla de indicadores (tarea 18.5), con las cifras del servidor: aquí se
// prueban las piezas, con datos de la prueba. Ninguna pieza trae cifras propias. Jest no mide: la línea recibe el ancho
// que daría el teléfono con el evento de layout. Cada color y cada medida sale de theme.ts (ADR-0013): las pruebas
// comparan contra el tema, no contra números.
import { fireEvent, render, screen } from '@testing-library/react-native';
import { StyleSheet } from 'react-native';

import { Dona, type PorcionDeLaDona } from '../../src/piezas/Dona';
import { GraficaDeBarras, type Barra } from '../../src/piezas/GraficaDeBarras';
import { IndicadorDestacado } from '../../src/piezas/IndicadorDestacado';
import { LineaDeTendencia, type PuntoDeLaLinea } from '../../src/piezas/LineaDeTendencia';
import { RankingConSemaforo, type LugarDelRanking } from '../../src/piezas/RankingConSemaforo';
import { ANCHO_DE_COLUMNA, TablaComparativa } from '../../src/piezas/TablaComparativa';
import { letra } from '../../src/tema/letra';
import { tema } from '../../src/tema/theme';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

const { color, espaciado, grosor, tipografia } = tema;

function estiloPlano(elemento: { props: { style?: unknown } }) {
  return StyleSheet.flatten(elemento.props.style as never) ?? {};
}

function numero(valor: unknown): number {
  expect(typeof valor).toBe('number');
  return valor as number;
}

// El giro de una vista, en grados: el de su transform: [{ rotate: '…deg' }].
function giro(elemento: { props: { style?: unknown } }): number {
  const transform = estiloPlano(elemento).transform as { rotate?: string }[] | undefined;
  const rotate = transform?.find((paso) => paso.rotate !== undefined)?.rotate;
  expect(rotate).toMatch(/^-?[\d.e-]+deg$/);
  return parseFloat(rotate as string);
}

// El centro de una vista puesta con left y top, y su ancho y alto.
function centro(elemento: { props: { style?: unknown } }) {
  const estilo = estiloPlano(elemento);
  return {
    x: numero(estilo.left) + numero(estilo.width) / 2,
    y: numero(estilo.top) + numero(estilo.height) / 2,
  };
}

// Un ángulo llevado a [0, 360).
function vuelta(grados: number): number {
  return ((grados % 360) + 360) % 360;
}

// Pedidos totales por semana, como los daría el servidor para un rango de cinco semanas (indicadores-E01 y E02).
const PEDIDOS_POR_SEMANA: Barra[] = [
  { clave: '2026-10-26', etiqueta: '26/10', etiquetaCompleta: 'Semana del 26/10/2026', valor: 3 },
  { clave: '2026-11-02', etiqueta: '02/11', etiquetaCompleta: 'Semana del 02/11/2026', valor: 5 },
  { clave: '2026-11-09', etiqueta: '09/11', etiquetaCompleta: 'Semana del 09/11/2026', valor: 4 },
  { clave: '2026-11-16', etiqueta: '16/11', etiquetaCompleta: 'Semana del 16/11/2026', valor: 6 },
  { clave: '2026-11-23', etiqueta: '23/11', etiquetaCompleta: 'Semana del 23/11/2026', valor: 2 },
];

// Pedidos por estado del rango (indicadores-E04): activos y completados, cada uno con el color que le da la pantalla.
const PEDIDOS_POR_ESTADO: PorcionDeLaDona[] = [
  { clave: 'activos', etiqueta: 'Activos', valor: 3, color: color.semantico.accion },
  { clave: 'completados', etiqueta: 'Completados', valor: 17, color: color.estado.exito },
];

describe('administracion-app-E46 (tarea 18.1): las barras de los pedidos totales', () => {
  test('administracion-app-E46 — una barra por periodo, del alto de su cifra frente a la mayor, en el color de acción', async () => {
    await render(<GraficaDeBarras titulo="Pedidos totales" barras={PEDIDOS_POR_SEMANA} />);

    const mayor = numero(estiloPlano(screen.getByTestId('barra-2026-11-16')).height);
    expect(mayor).toBeGreaterThan(0);
    for (const { clave, valor } of PEDIDOS_POR_SEMANA) {
      const barra = screen.getByTestId(`barra-${clave}`);
      expect(numero(estiloPlano(barra).height) / mayor).toBeCloseTo(valor / 6);
      expect(barra).toHaveStyle({ backgroundColor: color.semantico.accion });
    }
  });

  test('administracion-app-E46 — cada barra trae su cifra encima y su periodo debajo, en texto', async () => {
    await render(<GraficaDeBarras titulo="Pedidos totales" barras={PEDIDOS_POR_SEMANA} />);

    for (const { valor, etiqueta } of PEDIDOS_POR_SEMANA) {
      // La cifra en la letra de la maqueta 41: menor y semifuerte.
      expect(screen.getByText(String(valor))).toHaveStyle({
        color: color.semantico.texto,
        fontSize: tipografia.escala.menor,
        ...letra('cuerpo', 'semifuerte'),
      });
      expect(screen.getByText(etiqueta)).toHaveStyle({ color: color.semantico.texto_suave });
    }
  });

  test('administracion-app-E46 — la línea base es una sola: el área de cada columna crece hasta el alto de la más alta', async () => {
    await render(<GraficaDeBarras titulo="Pedidos totales" barras={PEDIDOS_POR_SEMANA} />);

    // Las columnas miden lo que la más alta; cada área crece hasta llenar la suya, así su orilla de abajo, que es la
    // línea base, queda a la misma altura aunque una cifra mida más con la letra agrandada.
    for (const { clave } of PEDIDOS_POR_SEMANA) {
      expect(screen.getByTestId(`area-${clave}`)).toHaveStyle({
        flexGrow: 1,
        borderBottomWidth: grosor.control,
        borderBottomColor: color.semantico.borde_fuerte,
      });
    }
  });

  test('administracion-app-E46 — se anuncia al lector de pantalla como una imagen con cada periodo y su cifra', async () => {
    await render(<GraficaDeBarras titulo="Pedidos totales" barras={PEDIDOS_POR_SEMANA} />);

    expect(
      screen.getByRole('image', {
        name:
          'Pedidos totales. Semana del 26/10/2026: 3. Semana del 02/11/2026: 5. Semana del 09/11/2026: 4. ' +
          'Semana del 16/11/2026: 6. Semana del 23/11/2026: 2',
      }),
    ).toBeOnTheScreen();
  });

  test('administracion-app-E46 — cada columna guarda su ancho mínimo y crece hasta el ancho; con muchos periodos, la gráfica se desplaza de lado', async () => {
    await render(<GraficaDeBarras titulo="Pedidos totales" barras={PEDIDOS_POR_SEMANA} />);

    const desplazable = screen.getByTestId('barras-desplazable');
    expect(desplazable.props.horizontal).toBe(true);
    expect(desplazable.props.contentContainerStyle).toMatchObject({ flexGrow: 1 });
    for (const { clave } of PEDIDOS_POR_SEMANA) {
      expect(screen.getByTestId(`columna-${clave}`)).toHaveStyle({ flexGrow: 1, minWidth: espaciado[8] });
    }
  });

  test('administracion-app-E46 — un periodo sin pedidos lleva su cero escrito y no dibuja barra; todo en cero no rompe el dibujo', async () => {
    await render(
      <GraficaDeBarras
        titulo="Pedidos totales"
        barras={[
          { clave: 'a', etiqueta: '01/11', valor: 0 },
          { clave: 'b', etiqueta: '02/11', valor: 0 },
        ]}
      />,
    );

    expect(screen.getAllByText('0')).toHaveLength(2);
    expect(screen.getByTestId('barra-a')).toHaveStyle({ height: 0 });
    expect(screen.getByTestId('barra-b')).toHaveStyle({ height: 0 });
    expect(screen.getByRole('image', { name: 'Pedidos totales. 01/11: 0. 02/11: 0' })).toBeOnTheScreen();
  });
});

describe('administracion-app-E46 (tarea 18.1): la tabla que pone cada periodo junto al anterior', () => {
  const COLUMNAS = [
    { clave: 'pedidos', titulo: 'Pedidos' },
    { clave: 'anterior', titulo: 'Anterior' },
  ];
  const FILAS = [
    { clave: '2026-10-26', nombre: 'Semana del 26/10/2026', cifras: { pedidos: '3', anterior: '2' } },
    { clave: '2026-11-02', nombre: 'Semana del 02/11/2026', cifras: { pedidos: '5', anterior: '3' } },
  ];

  test('administracion-app-E46 — cada periodo con sus cifras en texto, bajo los títulos de las columnas, en números tabulares', async () => {
    await render(<TablaComparativa primeraColumna="Periodo" columnas={COLUMNAS} filas={FILAS} />);

    expect(screen.getByText('Periodo', { includeHiddenElements: true })).toBeOnTheScreen();
    expect(screen.getByText('Pedidos', { includeHiddenElements: true })).toBeOnTheScreen();
    expect(screen.getByText('Anterior', { includeHiddenElements: true })).toBeOnTheScreen();
    for (const fila of FILAS) {
      expect(screen.getByText(fila.nombre)).toBeOnTheScreen();
      expect(screen.getByTestId(`cifra-${fila.clave}-pedidos`)).toHaveTextContent(fila.cifras.pedidos);
      expect(screen.getByTestId(`cifra-${fila.clave}-anterior`)).toHaveTextContent(fila.cifras.anterior);
      expect(screen.getByTestId(`cifra-${fila.clave}-pedidos`)).toHaveStyle({ fontVariant: ['tabular-nums'] });
    }
  });

  test('administracion-app-E46 — cada cifra queda bajo su título: la columna tiene un solo ancho, al menos el de la maqueta, y crece con su cifra más ancha', async () => {
    await render(<TablaComparativa primeraColumna="Periodo" columnas={COLUMNAS} filas={FILAS} />);

    for (const { clave } of COLUMNAS) {
      expect(screen.getByTestId(`titulo-${clave}`, { includeHiddenElements: true })).toHaveStyle({
        width: ANCHO_DE_COLUMNA,
        textAlign: 'right',
      });
      for (const fila of FILAS) {
        expect(screen.getByTestId(`cifra-${fila.clave}-${clave}`)).toHaveStyle({
          minWidth: ANCHO_DE_COLUMNA,
          textAlign: 'right',
        });
      }
    }

    // Una cifra más ancha que la columna, como la mide el teléfono: su título y las demás cifras toman su ancho.
    const ancha = ANCHO_DE_COLUMNA + 20;
    await fireEvent(screen.getByTestId('cifra-2026-11-02-anterior'), 'layout', {
      nativeEvent: { layout: { x: 0, y: 0, width: ancha, height: 24 } },
    });

    expect(screen.getByTestId('titulo-anterior', { includeHiddenElements: true })).toHaveStyle({ width: ancha });
    expect(screen.getByTestId('cifra-2026-10-26-anterior')).toHaveStyle({ minWidth: ancha });
    // La otra columna no cambia.
    expect(screen.getByTestId('titulo-pedidos', { includeHiddenElements: true })).toHaveStyle({
      width: ANCHO_DE_COLUMNA,
    });

    // Al ponerse al día con una cifra más angosta, la columna no se encoge: las cifras no bailan.
    await fireEvent(screen.getByTestId('cifra-2026-11-02-anterior'), 'layout', {
      nativeEvent: { layout: { x: 0, y: 0, width: ANCHO_DE_COLUMNA, height: 24 } },
    });
    expect(screen.getByTestId('titulo-anterior', { includeHiddenElements: true })).toHaveStyle({ width: ancha });
  });

  test('administracion-app-E46 — cada fila se anuncia con su periodo y cada cifra con el título de su columna; los títulos no se anuncian solos', async () => {
    await render(<TablaComparativa primeraColumna="Periodo" columnas={COLUMNAS} filas={FILAS} />);

    expect(screen.getByLabelText('Semana del 26/10/2026. Pedidos: 3. Anterior: 2')).toBeOnTheScreen();
    expect(screen.getByLabelText('Semana del 02/11/2026. Pedidos: 5. Anterior: 3')).toBeOnTheScreen();
    expect(screen.queryByText('Periodo')).not.toBeOnTheScreen();
  });
});

describe('administracion-app-E46 (tarea 18.2): la dona de los pedidos por estado', () => {
  const CENTRO = { cifra: '20', texto: 'pedidos' };

  test('administracion-app-E46 — un anillo de 144 marcas alrededor del centro, cada una girada a su ángulo', async () => {
    await render(<Dona titulo="Pedidos por estado" porciones={PEDIDOS_POR_ESTADO} centro={CENTRO} />);

    const anillo = screen.getByTestId('dona-anillo');
    const medio = numero(estiloPlano(anillo).width) / 2;
    expect(estiloPlano(anillo).height).toBe(estiloPlano(anillo).width);
    const marcas = screen.getAllByTestId(/^marca-\d+$/);
    expect(marcas).toHaveLength(144);

    const radios = marcas.map((marca, indice) => {
      const angulo = giro(marca);
      expect(angulo).toBeCloseTo((indice * 360) / 144);
      // Cada marca apunta al centro: su centro está sobre el radio que sale a su ángulo, desde las doce.
      const { x, y } = centro(marca);
      expect(vuelta((Math.atan2(x - medio, medio - y) * 180) / Math.PI)).toBeCloseTo(vuelta(angulo), 5);
      // Larga en el sentido del radio: más alta que ancha antes de girarla.
      expect(numero(estiloPlano(marca).height)).toBeGreaterThan(numero(estiloPlano(marca).width));
      return Math.hypot(x - medio, y - medio);
    });
    // Todas a la misma distancia del centro, dentro del anillo.
    for (const radio of radios) {
      expect(radio).toBeCloseTo(radios[0], 5);
      expect(radio).toBeLessThan(medio);
    }
  });

  test('administracion-app-E46 — cada marca va del color de la porción en que cae, en el orden de las porciones', async () => {
    await render(<Dona titulo="Pedidos por estado" porciones={PEDIDOS_POR_ESTADO} centro={CENTRO} />);

    const marcas = screen.getAllByTestId(/^marca-\d+$/);
    marcas.forEach((marca, indice) => {
      // Los activos son 3 de 20: las marcas de las doce hasta antes de 3/20 de la vuelta.
      const esperado = indice / 144 < 3 / 20 ? color.semantico.accion : color.estado.exito;
      expect(marca).toHaveStyle({ backgroundColor: esperado });
    });
    expect(marcas.filter((marca) => estiloPlano(marca).backgroundColor === color.semantico.accion)).toHaveLength(22);
  });

  test('administracion-app-E46 — trae sus cifras en texto: el total al centro y cada porción con su color, su nombre y su cifra', async () => {
    await render(<Dona titulo="Pedidos por estado" porciones={PEDIDOS_POR_ESTADO} centro={CENTRO} />);

    expect(screen.getByText('20')).toBeOnTheScreen();
    expect(screen.getByText('pedidos')).toBeOnTheScreen();
    for (const porcion of PEDIDOS_POR_ESTADO) {
      expect(screen.getByText(porcion.etiqueta)).toBeOnTheScreen();
      expect(screen.getByText(String(porcion.valor))).toBeOnTheScreen();
      expect(screen.getByTestId(`muestra-${porcion.clave}`)).toHaveStyle({ backgroundColor: porcion.color });
    }
  });

  test('administracion-app-E46 — se anuncia al lector de pantalla como una imagen con el total y cada porción con su cifra', async () => {
    await render(<Dona titulo="Pedidos por estado" porciones={PEDIDOS_POR_ESTADO} centro={CENTRO} />);

    expect(
      screen.getByRole('image', { name: 'Pedidos por estado. 20 pedidos. Activos: 3. Completados: 17' }),
    ).toBeOnTheScreen();
  });

  test('administracion-app-E46 — sin ninguna cifra, el anillo queda entero en el color del borde, con sus ceros escritos', async () => {
    await render(
      <Dona
        titulo="Pedidos por estado"
        porciones={PEDIDOS_POR_ESTADO.map((porcion) => ({ ...porcion, valor: 0 }))}
        centro={{ cifra: '0', texto: 'pedidos' }}
      />,
    );

    for (const marca of screen.getAllByTestId(/^marca-\d+$/)) {
      expect(marca).toHaveStyle({ backgroundColor: color.semantico.borde });
    }
    expect(screen.getAllByText('0')).toHaveLength(3);
  });
});

describe('administracion-app-E47 (tarea 18.2): la línea de tendencia de los usuarios activos', () => {
  // Tres periodos: los dos primeros iguales y el tercero el doble. Con 300 puntos de ancho, cada uno ocupa 100.
  const TRES: PuntoDeLaLinea[] = [
    { clave: 'a', etiqueta: '26/10', etiquetaCompleta: 'Semana del 26/10/2026', valor: 4 },
    { clave: 'b', etiqueta: '02/11', etiquetaCompleta: 'Semana del 02/11/2026', valor: 4 },
    { clave: 'c', etiqueta: '09/11', etiquetaCompleta: 'Semana del 09/11/2026', valor: 8 },
  ];

  async function dibujar(puntos: PuntoDeLaLinea[], ancho: number) {
    await render(<LineaDeTendencia titulo="Usuarios activos" puntos={puntos} />);
    await fireEvent(screen.getByTestId('linea-desplazable'), 'layout', {
      nativeEvent: { layout: { x: 0, y: 0, width: ancho, height: 0 } },
    });
  }

  test('administracion-app-E47 — un punto por periodo, al centro de su columna, más arriba el de más cifra', async () => {
    await dibujar(TRES, 300);

    const [a, b, c] = TRES.map(({ clave }) => centro(screen.getByTestId(`punto-${clave}`)));
    expect([a.x, b.x, c.x]).toEqual([50, 150, 250]);
    expect(a.y).toBeCloseTo(b.y);
    expect(c.y).toBeLessThan(a.y);
    for (const { clave } of TRES) {
      expect(screen.getByTestId(`punto-${clave}`)).toHaveStyle({ backgroundColor: color.semantico.accion });
    }
  });

  test('administracion-app-E47 — entre cada par de puntos, un segmento del largo de la distancia entre ellos, girado al ángulo entre ellos', async () => {
    await dibujar(TRES, 300);

    const puntos = TRES.map(({ clave }) => centro(screen.getByTestId(`punto-${clave}`)));
    const segmentos = screen.getAllByTestId(/^segmento-\d+$/);
    expect(segmentos).toHaveLength(TRES.length - 1);

    segmentos.forEach((segmento, indice) => {
      const desde = puntos[indice];
      const hasta = puntos[indice + 1];
      const largo = Math.hypot(hasta.x - desde.x, hasta.y - desde.y);
      expect(numero(estiloPlano(segmento).width)).toBeCloseTo(largo, 5);
      expect(giro(segmento)).toBeCloseTo((Math.atan2(hasta.y - desde.y, hasta.x - desde.x) * 180) / Math.PI, 5);
      // Girado sobre su centro, que es el punto medio entre los dos.
      const medio = centro(segmento);
      expect(medio.x).toBeCloseTo((desde.x + hasta.x) / 2, 5);
      expect(medio.y).toBeCloseTo((desde.y + hasta.y) / 2, 5);
      expect(segmento).toHaveStyle({ backgroundColor: color.semantico.accion });
    });

    // Entre dos cifras iguales, el segmento va derecho y mide lo que la columna; hacia una cifra mayor, sube.
    expect(giro(segmentos[0])).toBeCloseTo(0, 5);
    expect(numero(estiloPlano(segmentos[0]).width)).toBeCloseTo(100, 5);
    expect(giro(segmentos[1])).toBeLessThan(0);
  });

  test('administracion-app-E47 — cada punto trae su cifra encima y su periodo debajo, en texto, y se anuncia como una imagen', async () => {
    await dibujar(TRES, 300);

    expect(screen.getAllByText('4')).toHaveLength(2);
    // La cifra en la letra de la maqueta 44: menor y semifuerte.
    expect(screen.getByText('8')).toHaveStyle({ fontSize: tipografia.escala.menor, ...letra('cuerpo', 'semifuerte') });
    for (const { etiqueta } of TRES) {
      expect(screen.getByText(etiqueta)).toHaveStyle({ color: color.semantico.texto_suave });
    }
    expect(
      screen.getByRole('image', {
        name: 'Usuarios activos. Semana del 26/10/2026: 4. Semana del 02/11/2026: 4. Semana del 09/11/2026: 8',
      }),
    ).toBeOnTheScreen();
  });

  test('administracion-app-E47 — con la letra del teléfono agrandada, cada cifra queda apoyada sobre su punto, sin encimarse, y la del más alto cabe en el dibujo', async () => {
    await dibujar(TRES, 300);
    const altoAntes = numero(estiloPlano(screen.getByTestId('linea-dibujo')).height);

    // El teléfono mide las cifras más altas que de costumbre.
    const ALTO_DE_LA_CIFRA = 40;
    for (const { clave } of TRES) {
      await fireEvent(screen.getByTestId(`cifra-${clave}`), 'layout', {
        nativeEvent: { layout: { x: 0, y: 0, width: 100, height: ALTO_DE_LA_CIFRA } },
      });
    }

    const alto = numero(estiloPlano(screen.getByTestId('linea-dibujo')).height);
    expect(alto).toBeGreaterThan(altoAntes);
    for (const { clave } of TRES) {
      // La cifra se pone desde la línea base, por dentro del borde: su orilla de abajo, contada desde arriba.
      const abajoDeLaCifra = alto - grosor.control - numero(estiloPlano(screen.getByTestId(`cifra-${clave}`)).bottom);
      const arribaDelPunto = numero(estiloPlano(screen.getByTestId(`punto-${clave}`)).top);
      expect(abajoDeLaCifra).toBeCloseTo(arribaDelPunto, 5);
      expect(abajoDeLaCifra - ALTO_DE_LA_CIFRA).toBeGreaterThanOrEqual(-1e-9);
    }
  });

  test('administracion-app-E47 — con muchos periodos, cada columna guarda su ancho mínimo y la línea se desplaza de lado', async () => {
    const DIEZ = Array.from({ length: 10 }, (_, indice) => ({
      clave: `d${indice}`,
      etiqueta: `${indice + 1}/11`,
      valor: indice,
    }));
    await dibujar(DIEZ, 300);

    expect(screen.getByTestId('linea-desplazable').props.horizontal).toBe(true);
    expect(screen.getByTestId('linea-dibujo')).toHaveStyle({ width: 10 * espaciado[8] });
    expect(centro(screen.getByTestId('punto-d0')).x).toBeCloseTo(espaciado[8] / 2);
    expect(centro(screen.getByTestId('punto-d9')).x).toBeCloseTo(9.5 * espaciado[8]);
  });
});

describe('administracion-app-E47 (tarea 18.1): el monto promedio, destacado con su tendencia', () => {
  test('administracion-app-E47 — la cifra destacada y, debajo, la tendencia con su flecha y su texto: subió en el color de éxito', async () => {
    await render(
      <IndicadorDestacado
        titulo="Monto promedio por pedido"
        valor="$250.00"
        sentido="subio"
        detalle="Subió $50.00 frente al periodo anterior ($200.00)"
      />,
    );

    // Del tamaño de la maqueta 45.
    expect(screen.getByText('$250.00')).toHaveStyle({
      color: color.semantico.texto,
      fontSize: tipografia.escala.titulo_mayor,
      fontVariant: ['tabular-nums'],
    });
    expect(screen.getByText('Subió $50.00 frente al periodo anterior ($200.00)')).toBeOnTheScreen();
    expect(screen.getByTestId('tendencia-flecha')).toHaveTextContent('↑');
    expect(screen.getByTestId('tendencia-flecha')).toHaveStyle({ color: color.estado.exito });
    expect(
      screen.getByLabelText('Monto promedio por pedido: $250.00. Subió $50.00 frente al periodo anterior ($200.00)'),
    ).toBeOnTheScreen();
  });

  test('administracion-app-E47 — bajó, en el color de error: el texto lo dice, no solo el color', async () => {
    await render(
      <IndicadorDestacado
        titulo="Monto promedio por pedido"
        valor="$150.00"
        sentido="bajo"
        detalle="Bajó $50.00 frente al periodo anterior ($200.00)"
      />,
    );

    expect(screen.getByTestId('tendencia-flecha')).toHaveTextContent('↓');
    expect(screen.getByTestId('tendencia-flecha')).toHaveStyle({ color: color.estado.error });
    expect(screen.getByText('Bajó $50.00 frente al periodo anterior ($200.00)')).toBeOnTheScreen();
  });

  test('administracion-app-E47 — igual, en el texto suave: el texto lo dice, no solo el color', async () => {
    await render(
      <IndicadorDestacado
        titulo="Monto promedio por pedido"
        valor="$200.00"
        sentido="igual"
        detalle="Igual que en el periodo anterior ($200.00)"
      />,
    );

    expect(screen.getByTestId('tendencia-flecha')).toHaveTextContent('→');
    expect(screen.getByTestId('tendencia-flecha')).toHaveStyle({ color: color.semantico.texto_suave });
    expect(screen.getByText('Igual que en el periodo anterior ($200.00)')).toBeOnTheScreen();
  });

  test('administracion-app-E47 — sin tendencia, la cifra con su nota y sin flecha', async () => {
    await render(
      <IndicadorDestacado
        titulo="Monto promedio por pedido"
        valor="$250.00"
        detalle="Sin pedidos con monto en el periodo anterior para comparar."
      />,
    );

    expect(screen.queryByTestId('tendencia-flecha')).not.toBeOnTheScreen();
    expect(screen.getByText('Sin pedidos con monto en el periodo anterior para comparar.')).toHaveStyle({
      color: color.semantico.texto_suave,
    });
    expect(
      screen.getByLabelText(
        'Monto promedio por pedido: $250.00. Sin pedidos con monto en el periodo anterior para comparar.',
      ),
    ).toBeOnTheScreen();
  });
});

describe('administracion-app-E47 (tarea 18.1): quiénes vuelven a pedir, en una tabla', () => {
  test('administracion-app-E47 — cada mes de registro con sus cifras, en texto, y su anuncio', async () => {
    await render(
      <TablaComparativa
        primeraColumna="Mes de registro"
        columnas={[
          { clave: 'con_primer_pedido', titulo: 'Con primer pedido' },
          { clave: 'volvieron', titulo: 'Volvieron' },
        ]}
        filas={[{ clave: '2026-09', nombre: 'septiembre 2026', cifras: { con_primer_pedido: '4', volvieron: '50 %' } }]}
      />,
    );

    expect(screen.getByText('septiembre 2026')).toBeOnTheScreen();
    expect(screen.getByText('50 %')).toBeOnTheScreen();
    expect(screen.getByLabelText('septiembre 2026. Con primer pedido: 4. Volvieron: 50 %')).toBeOnTheScreen();
  });
});

describe('administracion-app-E48 (tarea 18.1): el ranking con semáforo y las entregas por repartidor', () => {
  const RANKING: LugarDelRanking[] = [
    {
      clave: 'l1',
      lugar: 1,
      nombre: 'Lavandería de prueba Centro',
      calificacion: '4.8 de 5',
      semaforo: { color: 'verde', texto: 'Verde' },
    },
    {
      clave: 'l2',
      lugar: 2,
      nombre: 'Lavandería de prueba 1',
      calificacion: '4.2 de 5',
      semaforo: { color: 'amarillo', texto: 'Amarillo' },
    },
    {
      clave: 'l3',
      lugar: 3,
      nombre: 'Lavandería de prueba suspendida',
      calificacion: '3.9 de 5',
      semaforo: { color: 'rojo', texto: 'Rojo' },
    },
    { clave: 'l4', lugar: null, nombre: 'Lavandería de prueba Norte', calificacion: null, semaforo: null },
  ];

  test('administracion-app-E48 — cada lavandería con su lugar, su calificación y su semáforo: cada color con su texto', async () => {
    await render(<RankingConSemaforo lugares={RANKING} />);

    expect(screen.getByText('1. Lavandería de prueba Centro')).toBeOnTheScreen();
    expect(screen.getByText('4.8 de 5')).toBeOnTheScreen();
    const esperados = [
      { clave: 'l1', texto: 'Verde', tono: color.estado.exito },
      { clave: 'l2', texto: 'Amarillo', tono: color.estado.atencion },
      { clave: 'l3', texto: 'Rojo', tono: color.estado.error },
    ];
    for (const { clave, texto, tono } of esperados) {
      expect(screen.getByTestId(`semaforo-${clave}`)).toHaveStyle({ borderColor: tono });
      expect(screen.getByText(texto)).toHaveStyle({ color: tono });
    }
  });

  test('administracion-app-E48 — cada lavandería en su fila, con una línea arriba, también la primera, y su calificación en números tabulares', async () => {
    await render(<RankingConSemaforo lugares={RANKING} />);

    for (const { clave } of RANKING) {
      expect(screen.getByTestId(`lugar-${clave}`)).toHaveStyle({
        borderTopWidth: tema.grosor.control,
        borderTopColor: color.semantico.borde,
      });
    }
    expect(screen.getByText('4.8 de 5')).toHaveStyle({ fontVariant: ['tabular-nums'] });
  });

  test('administracion-app-E48 — la lavandería sin calificaciones va sin lugar ni semáforo, y lo dice', async () => {
    await render(<RankingConSemaforo lugares={RANKING} />);

    expect(screen.getByText('Lavandería de prueba Norte')).toBeOnTheScreen();
    expect(screen.getByText('Sin calificaciones')).toBeOnTheScreen();
    expect(screen.queryByTestId('semaforo-l4')).not.toBeOnTheScreen();
  });

  test('administracion-app-E48 — cada lavandería se anuncia con su lugar, su calificación y el texto de su semáforo', async () => {
    await render(<RankingConSemaforo lugares={RANKING} />);

    expect(screen.getByLabelText('1. Lavandería de prueba Centro. 4.8 de 5. Semáforo: Verde')).toBeOnTheScreen();
    expect(screen.getByLabelText('2. Lavandería de prueba 1. 4.2 de 5. Semáforo: Amarillo')).toBeOnTheScreen();
    expect(screen.getByLabelText('3. Lavandería de prueba suspendida. 3.9 de 5. Semáforo: Rojo')).toBeOnTheScreen();
    expect(screen.getByLabelText('Lavandería de prueba Norte. Sin calificaciones')).toBeOnTheScreen();
  });

  test('administracion-app-E48 — sin cortes del semáforo, la calificación va sin color', async () => {
    await render(
      <RankingConSemaforo
        lugares={[{ clave: 'l1', lugar: 1, nombre: 'Lavandería de prueba Centro', calificacion: '4.8 de 5', semaforo: null }]}
      />,
    );

    expect(screen.queryByTestId('semaforo-l1')).not.toBeOnTheScreen();
    expect(screen.getByLabelText('1. Lavandería de prueba Centro. 4.8 de 5')).toBeOnTheScreen();
  });

  test('administracion-app-E48 — las entregas por repartidor: cada uno con su plaza y su cifra, en texto, y su anuncio', async () => {
    await render(
      <TablaComparativa
        primeraColumna="Repartidor"
        columnas={[{ clave: 'entregas', titulo: 'Entregas' }]}
        filas={[
          { clave: 'r1', nombre: 'Repartidor de prueba 1', detalle: 'Plaza de prueba Durango', cifras: { entregas: '8' } },
          { clave: 'r2', nombre: 'Repartidor de prueba 2', detalle: 'Plaza de prueba Torreón', cifras: { entregas: '5' } },
        ]}
      />,
    );

    expect(screen.getByText('Plaza de prueba Durango')).toHaveStyle({ color: color.semantico.texto_suave });
    expect(screen.getByTestId('cifra-r1-entregas')).toHaveTextContent('8');
    expect(screen.getByLabelText('Repartidor de prueba 1, Plaza de prueba Durango. Entregas: 8')).toBeOnTheScreen();
    expect(screen.getByLabelText('Repartidor de prueba 2, Plaza de prueba Torreón. Entregas: 5')).toBeOnTheScreen();
  });
});

describe('administracion-app-E49 (tarea 18.1): los ingresos brutos en barras', () => {
  // Los ingresos de enero y febrero, en centavos para el alto y con el texto del servidor encima (indicadores-E16).
  const INGRESOS: Barra[] = [
    { clave: '2026-01', etiqueta: 'ene', etiquetaCompleta: 'enero 2026', valor: 100000, texto: '$1,000.00' },
    { clave: '2026-02', etiqueta: 'feb', etiquetaCompleta: 'febrero 2026', valor: 75000, texto: '$750.00' },
  ];

  test('administracion-app-E49 — cada mes con su monto escrito encima tal como llega, y la barra del alto de su monto', async () => {
    await render(<GraficaDeBarras titulo="Ingresos brutos" barras={INGRESOS} />);

    expect(screen.getByText('$1,000.00')).toBeOnTheScreen();
    expect(screen.getByText('$750.00')).toBeOnTheScreen();
    expect(screen.getByText('ene')).toBeOnTheScreen();
    expect(screen.getByText('feb')).toBeOnTheScreen();
    const enero = numero(estiloPlano(screen.getByTestId('barra-2026-01')).height);
    const febrero = numero(estiloPlano(screen.getByTestId('barra-2026-02')).height);
    expect(febrero / enero).toBeCloseTo(0.75);
  });

  test('administracion-app-E49 — con pocos periodos, cada barra mide el 70 % de su columna, hasta 40, como en las maquetas 41 y 48', async () => {
    await render(<GraficaDeBarras titulo="Ingresos brutos" barras={INGRESOS} />);

    for (const { clave } of INGRESOS) {
      expect(screen.getByTestId(`barra-${clave}`)).toHaveStyle({ width: '70%', maxWidth: espaciado[6] + espaciado[2] });
      expect(estiloPlano(screen.getByTestId(`barra-${clave}`)).alignSelf).toBeUndefined();
    }
  });

  test('administracion-app-E49 — el monto encima de la barra va en micro y semifuerte, para que quepa', async () => {
    await render(<GraficaDeBarras titulo="Ingresos brutos" barras={INGRESOS} />);

    // La maqueta 48 lo pone a 13, que no está en tokens.json: el tamaño de abajo que sí está.
    for (const { texto } of INGRESOS) {
      expect(screen.getByText(texto!)).toHaveStyle({
        fontSize: tipografia.escala.micro,
        fontVariant: ['tabular-nums'],
        ...letra('cuerpo', 'semifuerte'),
      });
    }
  });

  test('administracion-app-E49 — se anuncia con cada mes y su monto', async () => {
    await render(<GraficaDeBarras titulo="Ingresos brutos" barras={INGRESOS} />);

    expect(
      screen.getByRole('image', { name: 'Ingresos brutos. enero 2026: $1,000.00. febrero 2026: $750.00' }),
    ).toBeOnTheScreen();
  });
});
