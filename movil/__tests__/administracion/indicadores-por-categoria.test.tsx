// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Indicadores del
// A.5 en la app": administracion-app-E46 (operaciones), E47 (usuarios), E48 (socios), E49 (los ingresos brutos), E52
// (cifras de la base, las mismas del panel) y E53 (un indicador que espera una respuesta). Tarea 18.5 de
// openspec/changes/administracion-en-la-app/tasks.md. La pantalla, el rango y la consulta periódica están en
// indicadores.test.tsx, y el mapa de calor, en mapa-de-calor.test.tsx.
//
// El A.5 nombra la visualización de cada indicador (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5): barras y tabla
// comparativa, dona, línea de tendencia, tabla comparativa, indicador destacado con tendencia, ranking con semáforo y
// barras agrupadas. Cómo se dibujan lo decide ADR-0022 (alternativa 5): con las vistas de React Native y los tokens, cada
// gráfica con sus cifras en texto. Las cifras las calcula el servidor con las mismas clases que el panel (ADR-0022,
// decisión 9); la app solo las pone en su pieza. La composición y los textos son los de las imágenes 41 a 48 de la
// Propuesta 6 (C-248).
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y los indicadores del contrato de IndicadoresController
// (__tests__/apoyo/administracion/indicadores.ts). Ninguna cifra de lo que se comprueba aquí sale de la app: todas son
// las que responde el servidor simulado.
import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';

import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { StyleSheet } from 'react-native';

import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  RUTA_DEL_INICIO,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  consultasDeLosIndicadores,
  HOY,
  LAVANDERIA_AMARILLA,
  LAVANDERIA_ROJA,
  LAVANDERIA_SIN_CALIFICACIONES,
  LAVANDERIA_VERDE,
  RANGO_DE_OCTUBRE_POR_SEMANA,
  RANGO_DEL_MES_EN_CURSO,
  REPARTIDOR_CON_PLAZA,
  REPARTIDOR_SIN_PLAZA,
  sociosCon,
  usarLosIndicadores,
  USUARIOS_DEL_MES_EN_CURSO,
  type IndicadoresDeLaRespuesta,
} from '../apoyo/administracion/indicadores';
import { usarLosReportesFinancieros } from '../apoyo/administracion/reportes-financieros';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-indicadores-por-categoria');
const indicadores = usarLosIndicadores(servidor);
const reportes = usarLosReportesFinancieros(servidor);

const RUTA_DE_LA_PANTALLA = '/administracion/indicadores';

// El reloj del teléfono en el hoy del servidor simulado: los atajos del mes dan días fijos.
beforeEach(() => {
  jest.useFakeTimers({ now: HOY });
});

afterEach(() => {
  jest.useRealTimers();
});

async function abrirLosIndicadores(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_PANTALLA);
  await screen.findByText(RANGO_DEL_MES_EN_CURSO.descripcion);
  await screen.findByTestId('indicador-pedidos-totales');
}

/** "Mes anterior", Semana y "Aplicar": los indicadores de octubre por semana, como la administración los pide. */
async function verOctubrePorSemana() {
  await fireEvent.press(screen.getByRole('link', { name: 'Cambiar' }));
  const hoja = within(await screen.findByTestId('hoja-del-rango'));
  await fireEvent.press(hoja.getByRole('link', { name: 'Mes anterior' }));
  await fireEvent.press(hoja.getByRole('tab', { name: 'Semana' }));
  await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
  await screen.findByText(RANGO_DE_OCTUBRE_POR_SEMANA.descripcion);
}

/** La pestaña de esa categoría, de la primera fila de pestañas de la pantalla, y espera a que llegue su indicador. */
async function elegirLaPestana(nombre: string, indicador: string) {
  await fireEvent.press(within(screen.getAllByTestId('pestanas')[0]).getByRole('tab', { name: nombre }));
  return within(await screen.findByTestId(indicador));
}

/** Lo que el lector de pantalla dice de cada fila de una tabla, en orden. */
function filasDe(tabla: ReturnType<typeof within>): string[] {
  return tabla.getAllByTestId(/^fila-/).map((fila) => fila.props.accessibilityLabel as string);
}

/** Los textos que se ven dentro de un elemento, en orden. */
function textosDe(elemento: ReturnType<typeof within>): string[] {
  return elemento.getAllByText(/.+/).map((texto) => String(texto.props.children));
}

/** El alto de una barra de la gráfica. */
function altoDe(elemento: ReturnType<typeof within>, testID: string): number {
  return StyleSheet.flatten(elemento.getByTestId(testID).props.style).height as number;
}

describe('administracion-app-E46: los indicadores de operaciones', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E46 — (%s) los pedidos totales en barras, cada barra con su cifra y su periodo, y la tabla que pone cada periodo junto al anterior',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();

      const barras = within(screen.getByTestId('indicador-pedidos-totales'));
      expect(barras.getByRole('header', { name: 'Pedidos totales' })).toBeOnTheScreen();
      expect(barras.getByTestId('grafica-de-barras').props.accessibilityLabel).toBe(
        'Pedidos totales. Semana del 28/09/2026: 3. Semana del 05/10/2026: 5. Semana del 12/10/2026: 4. ' +
          'Semana del 19/10/2026: 6. Semana del 26/10/2026: 2',
      );
      // Cada barra con su cifra encima y su periodo corto debajo, en texto: se lee sin el dibujo.
      for (const [inicio, cifra, periodo] of [
        ['2026-09-28', '3', '28/09'],
        ['2026-10-05', '5', '05/10'],
        ['2026-10-12', '4', '12/10'],
        ['2026-10-19', '6', '19/10'],
        ['2026-10-26', '2', '26/10'],
      ]) {
        const columna = within(barras.getByTestId(`columna-${inicio}`));
        expect(textosDe(columna)).toEqual([cifra, periodo]);
      }
      // El alto de cada barra, frente a la de más pedidos.
      const mayor = altoDe(barras, 'barra-2026-10-19');
      expect(altoDe(barras, 'barra-2026-10-05')).toBeCloseTo((mayor * 5) / 6);
      expect(altoDe(barras, 'barra-2026-10-26')).toBeCloseTo((mayor * 2) / 6);

      const tabla = within(screen.getByTestId('indicador-pedidos-totales-comparados'));
      expect(tabla.getByRole('header', { name: 'Pedidos totales, cada periodo junto al anterior' })).toBeOnTheScreen();
      expect(filasDe(tabla)).toEqual([
        'Semana del 28/09/2026. Pedidos: 3. Anterior: 2',
        'Semana del 05/10/2026. Pedidos: 5. Anterior: 3',
        'Semana del 12/10/2026. Pedidos: 4. Anterior: 5',
        'Semana del 19/10/2026. Pedidos: 6. Anterior: 4',
        'Semana del 26/10/2026. Pedidos: 2. Anterior: 6',
      ]);
      expect(textosDe(within(tabla.getByTestId('fila-2026-09-28')))).toEqual(['Semana del 28/09/2026', '3', '2']);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E46 — (%s) los pedidos por estado en una dona, con el total al centro y cada estado con su nombre y su cifra en texto',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();

      const tarjeta = within(screen.getByTestId('indicador-pedidos-por-estado'));
      expect(tarjeta.getByRole('header', { name: 'Pedidos por estado' })).toBeOnTheScreen();
      const dona = tarjeta.getByTestId('dona');
      expect(dona.props.accessibilityLabel).toBe('Pedidos por estado. 20 pedidos. Activos: 3. Completados: 17');
      // El total al centro y la leyenda, en texto: no depende del color.
      expect(textosDe(within(dona))).toEqual(['20', 'pedidos', 'Activos', '3', 'Completados', '17']);
      // Los cancelados esperan DD-016 (administracion-app-E55): su lugar, sin cifra.
      expect(tarjeta.getByTestId('espera-cancelados').props.accessibilityLabel).toBe(
        'Cancelados: Por confirmar · DD-016',
      );
    },
  );
});

describe('administracion-app-E47: los indicadores de usuarios', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E47 — (%s) los usuarios activos en una línea de tendencia, cada punto con su cifra y su periodo',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();

      const tarjeta = await elegirLaPestana('Usuarios', 'indicador-usuarios-activos');
      expect(tarjeta.getByRole('header', { name: 'Usuarios activos' })).toBeOnTheScreen();
      expect(tarjeta.getByText('Personas distintas.')).toBeOnTheScreen();
      const linea = tarjeta.getByTestId('linea-de-tendencia');
      expect(linea.props.accessibilityLabel).toBe(
        'Usuarios activos. Semana del 28/09/2026: 8. Semana del 05/10/2026: 11. Semana del 12/10/2026: 9. ' +
          'Semana del 19/10/2026: 14. Semana del 26/10/2026: 6',
      );
      for (const [inicio, cifra] of [
        ['2026-09-28', '8'],
        ['2026-10-05', '11'],
        ['2026-10-12', '9'],
        ['2026-10-19', '14'],
        ['2026-10-26', '6'],
      ]) {
        expect(within(linea).getByTestId(`cifra-${inicio}`)).toHaveTextContent(cifra);
      }
      for (const periodo of ['28/09', '05/10', '12/10', '19/10', '26/10']) {
        expect(within(linea).getByText(periodo)).toBeOnTheScreen();
      }
      // Un segmento entre cada par de puntos.
      expect(within(linea).getAllByTestId(/^segmento-/)).toHaveLength(4);
      // Qué actividad cuenta espera DD-111 (administracion-app-E55).
      expect(tarjeta.getByTestId('espera-que-actividad-cuenta').props.accessibilityLabel).toBe(
        'Qué actividad cuenta: Por confirmar · DD-111',
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E47 — (%s) los que vuelven a pedir por mes de registro en una tabla, y el monto promedio destacado con su tendencia',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();
      await elegirLaPestana('Usuarios', 'indicador-usuarios-activos');

      const tabla = within(screen.getByTestId('indicador-vuelven-a-pedir'));
      expect(tabla.getByRole('header', { name: 'Vuelven a pedir, por mes de registro' })).toBeOnTheScreen();
      // El mes como lo lee el servidor; el porcentaje con un decimal solo si lo tiene, como el panel; sin primeros
      // pedidos, la raya.
      expect(filasDe(tabla)).toEqual([
        'septiembre 2026. Con primer pedido: 4. Volvieron: 50 %',
        'octubre 2026. Con primer pedido: 6. Volvieron: 33.3 %',
        'noviembre 2026. Con primer pedido: 0. Volvieron: —',
      ]);

      const monto = within(screen.getByTestId('indicador-monto-promedio'));
      expect(monto.getByRole('header', { name: 'Monto promedio por pedido' })).toBeOnTheScreen();
      expect(monto.getByTestId('indicador-destacado').props.accessibilityLabel).toBe(
        'Monto promedio por pedido: $180.00. Bajó frente al periodo anterior ($200.00).',
      );
      expect(monto.getByText('$180.00')).toBeOnTheScreen();
      expect(monto.getByTestId('tendencia-flecha')).toHaveTextContent('↓');
      // Qué monto cuenta espera DD-012 (administracion-app-E55).
      expect(monto.getByTestId('espera-que-monto-cuenta').props.accessibilityLabel).toBe(
        'Qué monto cuenta: Por confirmar · DD-012',
      );
    },
  );

  test.each([
    [
      'subió',
      {},
      'Monto promedio por pedido: $275.00. Subió frente al periodo anterior ($200.00).',
      '↑',
    ],
    [
      'igual',
      {
        promedio: '200.00',
        promedio_texto: '$200.00',
        diferencia: '0.00',
        tendencia: 'igual',
        tendencia_texto: 'Igual',
      },
      'Monto promedio por pedido: $200.00. Igual que en el periodo anterior ($200.00).',
      '→',
    ],
    [
      'sin monto en el periodo anterior',
      {
        promedio_anterior: null,
        promedio_anterior_texto: null,
        diferencia: null,
        tendencia: null,
        tendencia_texto: null,
      },
      'Monto promedio por pedido: $275.00. Sin pedidos con monto en el periodo anterior para comparar.',
      null,
    ],
    [
      'sin monto en el periodo',
      {
        promedio: null,
        promedio_texto: null,
        diferencia: null,
        tendencia: null,
        tendencia_texto: null,
      },
      'Monto promedio por pedido: Sin monto. No hay pedidos con monto en el periodo.',
      null,
    ],
  ])(
    'administracion-app-E47 — el monto promedio %s, con el texto de la tendencia que da el servidor',
    async (_caso, cambio, anuncio, flecha) => {
      indicadores().cambiarSinFiltros('usuarios', {
        ...USUARIOS_DEL_MES_EN_CURSO,
        monto_promedio: { ...(USUARIOS_DEL_MES_EN_CURSO.monto_promedio as object), ...cambio },
      });
      await abrirLosIndicadores('ios');
      await elegirLaPestana('Usuarios', 'indicador-usuarios-activos');

      const monto = within(screen.getByTestId('indicador-monto-promedio'));
      expect(monto.getByTestId('indicador-destacado').props.accessibilityLabel).toBe(anuncio);
      if (flecha === null) {
        expect(monto.queryByTestId('tendencia-flecha')).not.toBeOnTheScreen();
      } else {
        expect(monto.getByTestId('tendencia-flecha')).toHaveTextContent(flecha);
      }
    },
  );
});

describe('administracion-app-E48: los indicadores de socios', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E48 — (%s) la calificación por lavandería en un ranking con semáforo, cada color con su texto, y la que no tiene calificaciones al final',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();

      const ranking = await elegirLaPestana('Socios', 'indicador-calificacion-por-lavanderia');
      expect(ranking.getByRole('header', { name: 'Calificación por lavandería' })).toBeOnTheScreen();
      expect(ranking.getAllByTestId(/^lugar-/).map((lugar) => lugar.props.accessibilityLabel)).toEqual([
        '1. Lavandería de prueba Durango Centro. 4.8 de 5. Semáforo: Verde',
        '2. Lavandería de prueba 1. 4.2 de 5. Semáforo: Amarillo',
        '3. Lavandería de prueba suspendida. 3.0 de 5. Semáforo: Rojo',
        'Lavandería de prueba Durango Norte. Sin calificaciones',
      ]);
      // Cada color con su texto: el semáforo no depende del color.
      expect(ranking.getByTestId(`semaforo-${LAVANDERIA_VERDE}`)).toHaveTextContent('Verde');
      expect(ranking.getByTestId(`semaforo-${LAVANDERIA_AMARILLA}`)).toHaveTextContent('Amarillo');
      expect(ranking.getByTestId(`semaforo-${LAVANDERIA_ROJA}`)).toHaveTextContent('Rojo');
      expect(ranking.queryByTestId(`semaforo-${LAVANDERIA_SIN_CALIFICACIONES}`)).not.toBeOnTheScreen();
      // Los cortes de EL CLIENTE esperan DD-114 (administracion-app-E55).
      expect(ranking.getByTestId('espera-cortes-del-semaforo').props.accessibilityLabel).toBe(
        'Cortes del semáforo: Por confirmar · DD-114',
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E48 — (%s) las entregas completadas por repartidor en una tabla, con su plaza, en el orden del servidor',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();
      await elegirLaPestana('Socios', 'indicador-calificacion-por-lavanderia');

      const tabla = within(screen.getByTestId('indicador-entregas-por-repartidor'));
      expect(tabla.getByRole('header', { name: 'Entregas por repartidor' })).toBeOnTheScreen();
      expect(filasDe(tabla)).toEqual([
        'Repartidor de prueba 1, Plaza de prueba Durango. Entregas: 8',
        'Repartidor de prueba 2, Sin plaza. Entregas: 5',
      ]);
      expect(textosDe(within(tabla.getByTestId(`fila-${REPARTIDOR_CON_PLAZA}`)))).toEqual([
        'Repartidor de prueba 1',
        'Plaza de prueba Durango',
        '8',
      ]);
      expect(textosDe(within(tabla.getByTestId(`fila-${REPARTIDOR_SIN_PLAZA}`)))).toEqual([
        'Repartidor de prueba 2',
        'Sin plaza',
        '5',
      ]);
    },
  );

  test('administracion-app-E48 — sin cortes del semáforo en la configuración, el ranking va sin colores; sin repartidores, lo dice como el panel', async () => {
    const socios = sociosCon([0, 0]) as {
      calificacion_por_lavanderia: { lavanderias: Record<string, unknown>[] };
    } & IndicadoresDeLaRespuesta;
    indicadores().cambiarSinFiltros('socios', {
      ...socios,
      calificacion_por_lavanderia: {
        semaforo_configurado: false,
        lavanderias: socios.calificacion_por_lavanderia.lavanderias.map((lavanderia) => ({
          ...lavanderia,
          semaforo: null,
          semaforo_texto: null,
        })),
      },
      entregas_por_repartidor: { repartidores: [] },
    });
    await abrirLosIndicadores('android');

    const ranking = await elegirLaPestana('Socios', 'indicador-calificacion-por-lavanderia');
    expect(ranking.getAllByTestId(/^lugar-/).map((lugar) => lugar.props.accessibilityLabel)).toEqual([
      '1. Lavandería de prueba Durango Centro. 4.8 de 5',
      '2. Lavandería de prueba 1. 4.2 de 5',
      '3. Lavandería de prueba suspendida. 3.0 de 5',
      'Lavandería de prueba Durango Norte. Sin calificaciones',
    ]);
    expect(ranking.queryAllByTestId(/^semaforo-/)).toHaveLength(0);
    const entregas = within(screen.getByTestId('indicador-entregas-por-repartidor'));
    expect(entregas.getByText('No hay repartidores dados de alta.')).toBeOnTheScreen();
    expect(entregas.queryAllByTestId(/^fila-/)).toHaveLength(0);
  });
});

describe('administracion-app-E49: los ingresos brutos', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E49 — (%s) los ingresos brutos de cada periodo en barras, cada una con el monto como lo lee el panel',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);
      await verOctubrePorSemana();

      const tarjeta = await elegirLaPestana('Financiero', 'indicador-ingresos-brutos');
      expect(tarjeta.getByRole('header', { name: 'Ingresos brutos' })).toBeOnTheScreen();
      expect(
        tarjeta.getByText(
          'Lo cobrado con pago confirmado por la pasarela, en pesos. Cada cifra es la misma de la pantalla Ingresos para el mismo rango.',
        ),
      ).toBeOnTheScreen();
      expect(tarjeta.getByTestId('grafica-de-barras').props.accessibilityLabel).toBe(
        'Ingresos brutos. Semana del 28/09/2026: $500.00. Semana del 05/10/2026: $750.00. ' +
          'Semana del 12/10/2026: $1,000.00. Semana del 19/10/2026: $500.00. Semana del 26/10/2026: $500.00',
      );
      expect(textosDe(within(tarjeta.getByTestId('columna-2026-10-12')))).toEqual(['$1,000.00', '12/10']);
      // El alto, del monto en pesos: la de $750.00 mide tres cuartos de la de $1,000.00.
      expect(altoDe(tarjeta, 'barra-2026-10-05')).toBeCloseTo((altoDe(tarjeta, 'barra-2026-10-12') * 3) / 4);
      // Cómo se agrupan las barras espera DD-115 (administracion-app-E55).
      expect(tarjeta.getByTestId('espera-como-se-agrupan-las-barras').props.accessibilityLabel).toBe(
        'Cómo se agrupan las barras: Por confirmar · DD-115',
      );
    },
  );

  // Enero y febrero de 2027 por mes, como el escenario. El servidor real saca las dos respuestas de la misma clase,
  // App\Finanzas\Ingresos::porPeriodo(); el simulado, de los mismos periodos.
  const ENERO_Y_FEBRERO_POR_MES = { desde: '2027-01-01', hasta: '2027-02-28', granularidad: 'mes' };
  const RANGO_DE_ENERO_Y_FEBRERO = {
    desde: '2027-01-01',
    hasta: '2027-02-28',
    granularidad: 'mes' as const,
    descripcion: 'Del 01/01/2027 al 28/02/2027, por mes',
  };
  const PAGOS_DE_ENERO_Y_FEBRERO = [
    { inicio: '2027-01-01', periodo: 'enero 2027', pagos: 2, ingresos: '350.00', ingresos_texto: '$350.00' },
    { inicio: '2027-02-01', periodo: 'febrero 2027', pagos: 5, ingresos: '1250.50', ingresos_texto: '$1,250.50' },
  ];

  /** Escribe el rango de enero y febrero, por mes, en la hoja que esté abierta, y lo aplica. */
  async function aplicarEneroYFebreroPorMes() {
    await fireEvent.press(screen.getByRole('link', { name: 'Cambiar' }));
    const hoja = within(await screen.findByTestId('hoja-del-rango'));
    await fireEvent.changeText(hoja.getByLabelText('Desde'), '01012027');
    await fireEvent.changeText(hoja.getByLabelText('Hasta'), '28022027');
    await fireEvent.press(hoja.getByRole('tab', { name: 'Mes' }));
    await fireEvent.press(hoja.getByRole('button', { name: 'Aplicar' }));
    await screen.findByText(RANGO_DE_ENERO_Y_FEBRERO.descripcion);
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E49 — (%s) con pagos en enero y en febrero, por mes, cada barra trae la misma cifra que la pantalla Ingresos para ese mes',
    async (plataforma) => {
      indicadores().responder('financiero', ENERO_Y_FEBRERO_POR_MES, RANGO_DE_ENERO_Y_FEBRERO, {
        ingresos_brutos: { periodos: PAGOS_DE_ENERO_Y_FEBRERO },
        comisiones: { en_espera: ['DD-020', 'DD-013'] },
      });
      reportes().responderIngresos(ENERO_Y_FEBRERO_POR_MES, {
        rango: RANGO_DE_ENERO_Y_FEBRERO,
        total: { pagos: 7, ingresos: '1600.50', ingresos_texto: '$1,600.50' },
        periodos: PAGOS_DE_ENERO_Y_FEBRERO,
      });
      await abrirLosIndicadores(plataforma);
      await elegirLaPestana('Financiero', 'indicador-ingresos-brutos');
      await aplicarEneroYFebreroPorMes();
      await waitFor(() =>
        expect(consultasDeLosIndicadores(servidor()).at(-1)).toEqual({
          categoria: 'financiero',
          filtros: ENERO_Y_FEBRERO_POR_MES,
        }),
      );

      const barras = within(screen.getByTestId('indicador-ingresos-brutos'));
      await waitFor(() =>
        expect(barras.getByTestId('grafica-de-barras').props.accessibilityLabel).toBe(
          'Ingresos brutos. enero 2027: $350.00. febrero 2027: $1,250.50',
        ),
      );
      // Por mes, el periodo corto bajo cada barra es su mes y su año.
      const enLasBarras = PAGOS_DE_ENERO_Y_FEBRERO.map(({ inicio }) =>
        textosDe(within(barras.getByTestId(`columna-${inicio}`))),
      );
      expect(enLasBarras).toEqual([
        ['$350.00', '01/2027'],
        ['$1,250.50', '02/2027'],
      ]);

      // La pantalla Ingresos, con el mismo rango.
      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
      await fireEvent.press(await screen.findByRole('link', { name: 'Ingresos' }));
      await screen.findByTestId('tabla-de-los-periodos');
      await aplicarEneroYFebreroPorMes();

      const ingresos = within(screen.getByTestId('tabla-de-los-periodos'));
      await waitFor(() =>
        expect(ingresos.getByTestId('periodo-2027-02-01').props.accessibilityLabel).toBe('febrero 2027: $1,250.50'),
      );
      PAGOS_DE_ENERO_Y_FEBRERO.forEach(({ inicio, periodo }, indice) => {
        const [enLaBarra] = enLasBarras[indice];
        expect(ingresos.getByTestId(`periodo-${inicio}`).props.accessibilityLabel).toBe(`${periodo}: ${enLaBarra}`);
      });
    },
  );
});

describe('administracion-app-E53: un indicador que espera una respuesta', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E53 — (%s) las lavanderías activas frente a registradas, en su lugar en Socios, con su nombre y que espera una respuesta, sin cifra ni descarga',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      const tarjeta = await elegirLaPestana('Socios', 'indicador-activas-frente-a-registradas');
      expect(tarjeta.getByRole('header', { name: 'Lavanderías activas frente a registradas' })).toBeOnTheScreen();
      // Solo su nombre, que espera una respuesta y la marca con las DD que da el servidor: ninguna cifra.
      expect(textosDe(tarjeta)).toEqual([
        'Lavanderías activas frente a registradas',
        'Sin cifra mientras espera una respuesta.',
        'Por confirmar · DD-083',
      ]);
      expect(tarjeta.queryByText('Descargar')).not.toBeOnTheScreen();
      // Va primero en Socios, como en la imagen 46.
      expect(screen.getAllByTestId(/^indicador-/).map((indicador) => indicador.props.testID)).toEqual([
        'indicador-activas-frente-a-registradas',
        'indicador-calificacion-por-lavanderia',
        'indicador-entregas-por-repartidor',
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E53 — (%s) las comisiones por socio y en total, en su lugar en Financiero, con su nombre y que espera una respuesta, sin cifra ni descarga',
    async (plataforma) => {
      await abrirLosIndicadores(plataforma);

      const tarjeta = await elegirLaPestana('Financiero', 'indicador-comisiones');
      expect(textosDe(tarjeta)).toEqual([
        'Comisiones por socio y en total',
        'Sin cifra mientras espera una respuesta.',
        'Por confirmar · DD-020, DD-013',
      ]);
      expect(tarjeta.queryByText('Descargar')).not.toBeOnTheScreen();
      expect(screen.getAllByTestId(/^indicador-/).map((indicador) => indicador.props.testID)).toEqual([
        'indicador-ingresos-brutos',
        'indicador-comisiones',
      ]);
    },
  );
});

describe('administracion-app-E52: cifras de la base, las mismas del panel', () => {
  // Otra base sembrada: cada categoría responde las cifras que el servidor sacó de ella, sin filtros.
  const OTRA_BASE: Record<'operaciones' | 'usuarios' | 'financiero', IndicadoresDeLaRespuesta> = {
    operaciones: {
      pedidos_totales: {
        periodos: [
          {
            inicio: '2026-11-01',
            periodo: 'dom. 01/11/2026',
            pedidos: 7,
            periodo_anterior: 'sáb. 31/10/2026',
            pedidos_anterior: 9,
          },
        ],
      },
      pedidos_por_estado: { activos: 4, completados: 3 },
    },
    usuarios: {
      usuarios_activos: { periodos: [{ inicio: '2026-11-01', periodo: 'dom. 01/11/2026', personas: 12 }] },
      vuelven_a_pedir: {
        meses: [{ inicio: '2026-11-01', mes: 'noviembre 2026', con_primer_pedido: 8, porcentaje: 12.5 }],
      },
      monto_promedio: {
        promedio: '310.40',
        promedio_texto: '$310.40',
        promedio_anterior: '310.40',
        promedio_anterior_texto: '$310.40',
        diferencia: '0.00',
        tendencia: 'igual',
        tendencia_texto: 'Igual',
      },
    },
    financiero: {
      ingresos_brutos: {
        periodos: [
          { inicio: '2026-11-01', periodo: 'dom. 01/11/2026', pagos: 3, ingresos: '931.20', ingresos_texto: '$931.20' },
        ],
      },
      comisiones: { en_espera: ['DD-020', 'DD-013'] },
    },
  };

  test.each(PLATAFORMAS)(
    'administracion-app-E52 — (%s) con otra base, cada pestaña da las cifras que el servidor sacó de ella',
    async (plataforma) => {
      indicadores().cambiarSinFiltros('operaciones', OTRA_BASE.operaciones);
      indicadores().cambiarSinFiltros('usuarios', OTRA_BASE.usuarios);
      indicadores().cambiarSinFiltros('financiero', OTRA_BASE.financiero);
      indicadores().cambiarSinFiltros('socios', sociosCon([21, 13]));
      await abrirLosIndicadores(plataforma);

      expect(screen.getByTestId('grafica-de-barras').props.accessibilityLabel).toBe(
        'Pedidos totales. dom. 01/11/2026: 7',
      );
      expect(filasDe(within(screen.getByTestId('indicador-pedidos-totales-comparados')))).toEqual([
        'dom. 01/11/2026. Pedidos: 7. Anterior: 9',
      ]);
      expect(screen.getByTestId('dona').props.accessibilityLabel).toBe(
        'Pedidos por estado. 7 pedidos. Activos: 4. Completados: 3',
      );

      await elegirLaPestana('Usuarios', 'indicador-usuarios-activos');
      expect(screen.getByTestId('linea-de-tendencia').props.accessibilityLabel).toBe(
        'Usuarios activos. dom. 01/11/2026: 12',
      );
      expect(filasDe(within(screen.getByTestId('indicador-vuelven-a-pedir')))).toEqual([
        'noviembre 2026. Con primer pedido: 8. Volvieron: 12.5 %',
      ]);
      expect(screen.getByTestId('indicador-destacado').props.accessibilityLabel).toBe(
        'Monto promedio por pedido: $310.40. Igual que en el periodo anterior ($310.40).',
      );

      await elegirLaPestana('Socios', 'indicador-entregas-por-repartidor');
      expect(filasDe(within(screen.getByTestId('indicador-entregas-por-repartidor')))).toEqual([
        'Repartidor de prueba 1, Plaza de prueba Durango. Entregas: 21',
        'Repartidor de prueba 2, Sin plaza. Entregas: 13',
      ]);

      await elegirLaPestana('Financiero', 'indicador-ingresos-brutos');
      expect(screen.getByTestId('grafica-de-barras').props.accessibilityLabel).toBe(
        'Ingresos brutos. dom. 01/11/2026: $931.20',
      );
    },
  );

  // Los archivos de la pantalla de los indicadores: ninguno trae una cifra, un nombre, un monto, un periodo ni un color
  // de ejemplo. Lo que se ve sale de la respuesta; los números del código son medidas del dibujo. Los comentarios sí
  // citan los ejemplos de las maquetas, así que se quitan antes de buscar.
  const RAIZ = path.resolve(__dirname, '..', '..');

  /** El código sin sus comentarios: los de bloque y los de línea (un "//" que no es de una dirección, "https://"). */
  function sinComentarios(codigo: string): string {
    return codigo.replace(/\/\*[\s\S]*?\*\//g, '').replace(/(^|[^:])\/\/.*$/gm, '$1');
  }
  const ARCHIVOS = [
    path.join(RAIZ, 'app', 'administracion', 'indicadores.tsx'),
    ...readdirSync(path.join(RAIZ, 'src', 'administracion', 'indicadores')).map((archivo) =>
      path.join(RAIZ, 'src', 'administracion', 'indicadores', archivo),
    ),
  ];

  test.each(ARCHIVOS.map((archivo) => [path.relative(RAIZ, archivo), archivo]))(
    'administracion-app-E52 — %s no trae cifras de ejemplo',
    (_nombre, archivo) => {
      const codigo = sinComentarios(readFileSync(archivo, 'utf8'));
      // Ningún nombre de los datos de prueba.
      expect(codigo).not.toMatch(/de prueba/i);
      // Ningún monto escrito: los montos y su texto los da el servidor.
      expect(codigo).not.toMatch(/\$\d/);
      // Ningún periodo, mes ni día escrito.
      expect(codigo).not.toMatch(/Semana del \d|\d{2}\/\d{2}\/\d{4}|\b(enero|febrero|marzo|abril|mayo|junio|julio|agosto|septiembre|octubre|noviembre|diciembre) \d{4}/i);
      // Ningún color de una figura del mapa: los da el servidor (Intensidad::color()); los demás, el tema.
      expect(codigo).not.toMatch(/['"`]#[0-9a-f]{6}['"`]/i);
      // Ninguna lista de cifras escrita en el código.
      expect(codigo).not.toMatch(/(pedidos|personas|entregas|pagos|activos|completados|promedio)\s*:\s*\d/);
    },
  );
});
