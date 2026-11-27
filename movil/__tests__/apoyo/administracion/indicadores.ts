// Apoyo de las pruebas de los indicadores del A.5 en la app (administracion-app-E45 a E53). No es una prueba: Jest no lo
// corre como tal (testPathIgnorePatterns en package.json). Es el manejador que la prueba registra en el servidor simulado
// de la administración (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/IndicadoresController.php:
//
// - GET /administracion/indicadores/{categoria}, con desde, hasta y granularidad opcionales y, en geografico,
//   agrupacion: sin filtros, el mes en curso hasta hoy, por día; con los de octubre por semana, la respuesta de esos
//   filtros, con el rango ajustado de lunes a domingo; con otros, la que la prueba registró. Otra categoría, 404.
//   El rango, las cifras, los textos de cada periodo y de cada monto, el semáforo y el color de cada colonia y zona los
//   calcula el servidor real (App\Indicadores, App\Finanzas\Ingresos y App\Finanzas\Pesos); aquí van ya calculados;
// - refresco: el intervalo de indicadores.refresco, "30s" salvo que la prueba ponga otro, o null.
//
// Hoy, en el servidor simulado, es el miércoles 4 de noviembre de 2026. Los datos son de prueba, no del negocio: los
// nombres de los seeders (servidor/database/seeders/), los de las imágenes 41 a 50 y 69 de la Propuesta 06 y cifras que
// cuadran entre sí (los pedidos de cada día suman los de la dona; cada colonia, sus pedidos).
import { respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';

export const RUTA_DE_LOS_INDICADORES = '/administracion/indicadores';

/** Hoy en el servidor simulado y en el teléfono de la prueba: miércoles 4 de noviembre de 2026, a media mañana. */
export const HOY = new Date(2026, 10, 4, 10, 30);

type Categoria = 'operaciones' | 'usuarios' | 'socios' | 'financiero' | 'geografico';
type Granularidad = 'dia' | 'semana' | 'mes';

export interface RangoDeLaRespuesta {
  desde: string;
  hasta: string;
  granularidad: Granularidad;
  descripcion: string;
}

/** El rango sin filtros: el mes en curso hasta hoy, por día, como Rango::deLosFiltros(). */
export const RANGO_DEL_MES_EN_CURSO: RangoDeLaRespuesta = {
  desde: '2026-11-01',
  hasta: '2026-11-04',
  granularidad: 'dia',
  descripcion: 'Del 01/11/2026 al 04/11/2026, por día',
};

/** Los filtros de octubre por semana, como los manda la app al tocar "Mes anterior" y "Semana". */
export const OCTUBRE_POR_SEMANA = { desde: '2026-10-01', hasta: '2026-10-31', granularidad: 'semana' } as const;

/** Octubre por semana: el rango se ajusta de lunes a domingo, del 28 de septiembre al 1 de noviembre. */
export const RANGO_DE_OCTUBRE_POR_SEMANA: RangoDeLaRespuesta = {
  desde: '2026-09-28',
  hasta: '2026-11-01',
  granularidad: 'semana',
  descripcion: 'Del 28/09/2026 al 01/11/2026, por semana',
};

const DIAS = [
  { inicio: '2026-11-01', periodo: 'dom. 01/11/2026' },
  { inicio: '2026-11-02', periodo: 'lun. 02/11/2026' },
  { inicio: '2026-11-03', periodo: 'mar. 03/11/2026' },
  { inicio: '2026-11-04', periodo: 'mié. 04/11/2026' },
];

const SEMANAS = [
  { inicio: '2026-09-28', periodo: 'Semana del 28/09/2026' },
  { inicio: '2026-10-05', periodo: 'Semana del 05/10/2026' },
  { inicio: '2026-10-12', periodo: 'Semana del 12/10/2026' },
  { inicio: '2026-10-19', periodo: 'Semana del 19/10/2026' },
  { inicio: '2026-10-26', periodo: 'Semana del 26/10/2026' },
];

/** Los periodos con sus pedidos, cada uno junto al anterior: el primero, con el periodo de antes del rango. */
function conElAnterior(
  periodos: readonly { inicio: string; periodo: string }[],
  pedidos: readonly number[],
  anteriorAlRango: { periodo: string; pedidos: number },
) {
  return periodos.map((periodo, indice) => ({
    ...periodo,
    pedidos: pedidos[indice],
    periodo_anterior: indice === 0 ? anteriorAlRango.periodo : periodos[indice - 1].periodo,
    pedidos_anterior: indice === 0 ? anteriorAlRango.pedidos : pedidos[indice - 1],
  }));
}

/** Un monto en pesos con dos decimales, como lo da el servidor, y su texto, como lo lee el panel. */
function pesos(monto: string, texto: string) {
  return { ingresos: monto, ingresos_texto: texto };
}

/** Lo que una categoría responde dentro de "indicadores". */
export type IndicadoresDeLaRespuesta = Record<string, unknown>;

// --- Operaciones (F-I01 y F-I02) ------------------------------------------------------------------------------------

/** Los pedidos de cada día del mes en curso: 2, 0, 3 y 1. Suman los 6 de la dona: 2 activos y 4 completados. */
export function operacionesDelMesEnCurso(pedidosDeHoy = 1, activos = 2): IndicadoresDeLaRespuesta {
  return {
    pedidos_totales: {
      periodos: conElAnterior(DIAS, [2, 0, 3, pedidosDeHoy], { periodo: 'sáb. 31/10/2026', pedidos: 1 }),
    },
    pedidos_por_estado: { activos, completados: 4 },
  };
}

/** Octubre por semana, las cifras de la imagen 41: 3, 5, 4, 6 y 2, con 3 activos y 17 completados. */
export const OPERACIONES_DE_OCTUBRE: IndicadoresDeLaRespuesta = {
  pedidos_totales: {
    periodos: conElAnterior(SEMANAS, [3, 5, 4, 6, 2], { periodo: 'Semana del 21/09/2026', pedidos: 2 }),
  },
  pedidos_por_estado: { activos: 3, completados: 17 },
};

// --- Usuarios (F-I03, F-I04 y F-I05) ---------------------------------------------------------------------------------

export const USUARIOS_DEL_MES_EN_CURSO: IndicadoresDeLaRespuesta = {
  usuarios_activos: {
    periodos: DIAS.map((dia, indice) => ({ ...dia, personas: [1, 0, 2, 1][indice] })),
  },
  vuelven_a_pedir: {
    meses: [{ inicio: '2026-11-01', mes: 'noviembre 2026', con_primer_pedido: 3, porcentaje: 33.3 }],
  },
  monto_promedio: {
    promedio: '275.00',
    promedio_texto: '$275.00',
    promedio_anterior: '200.00',
    promedio_anterior_texto: '$200.00',
    diferencia: '75.00',
    tendencia: 'subio',
    tendencia_texto: 'Subió',
  },
};

export const USUARIOS_DE_OCTUBRE: IndicadoresDeLaRespuesta = {
  usuarios_activos: {
    periodos: SEMANAS.map((semana, indice) => ({ ...semana, personas: [8, 11, 9, 14, 6][indice] })),
  },
  vuelven_a_pedir: {
    meses: [
      { inicio: '2026-09-01', mes: 'septiembre 2026', con_primer_pedido: 4, porcentaje: 50.0 },
      { inicio: '2026-10-01', mes: 'octubre 2026', con_primer_pedido: 6, porcentaje: 33.3 },
      { inicio: '2026-11-01', mes: 'noviembre 2026', con_primer_pedido: 0, porcentaje: null },
    ],
  },
  monto_promedio: {
    promedio: '180.00',
    promedio_texto: '$180.00',
    promedio_anterior: '200.00',
    promedio_anterior_texto: '$200.00',
    diferencia: '-20.00',
    tendencia: 'bajo',
    tendencia_texto: 'Bajó',
  },
};

// --- Socios (F-I06, F-I07 y F-I08) -----------------------------------------------------------------------------------

export const LAVANDERIA_VERDE = '01jd8x6m3v2c9q4w5e7r8tind1';
export const LAVANDERIA_AMARILLA = '01jd8x6m3v2c9q4w5e7r8tind2';
export const LAVANDERIA_ROJA = '01jd8x6m3v2c9q4w5e7r8tind3';
export const LAVANDERIA_SIN_CALIFICACIONES = '01jd8x6m3v2c9q4w5e7r8tind4';
export const REPARTIDOR_CON_PLAZA = '01jd8x6m3v2c9q4w5e7r8tinr1';
export const REPARTIDOR_SIN_PLAZA = '01jd8x6m3v2c9q4w5e7r8tinr2';

/**
 * Las cuatro lavanderías del ranking, con los dos cortes en la configuración: el servidor dice que el semáforo está
 * configurado y no manda sus cifras (DD-114). No dependen del rango.
 */
const CALIFICACION_POR_LAVANDERIA = {
  semaforo_configurado: true,
  lavanderias: [
    {
      id_publico: LAVANDERIA_VERDE,
      lugar: 1,
      lavanderia: 'Lavandería de prueba Durango Centro',
      promedio: 4.8,
      semaforo: 'verde',
      semaforo_texto: 'Verde',
    },
    {
      id_publico: LAVANDERIA_AMARILLA,
      lugar: 2,
      lavanderia: 'Lavandería de prueba 1',
      promedio: 4.2,
      semaforo: 'amarillo',
      semaforo_texto: 'Amarillo',
    },
    {
      id_publico: LAVANDERIA_ROJA,
      lugar: 3,
      lavanderia: 'Lavandería de prueba suspendida',
      promedio: 3,
      semaforo: 'rojo',
      semaforo_texto: 'Rojo',
    },
    {
      id_publico: LAVANDERIA_SIN_CALIFICACIONES,
      lugar: null,
      lavanderia: 'Lavandería de prueba Durango Norte',
      promedio: null,
      semaforo: null,
      semaforo_texto: null,
    },
  ],
};

export function sociosCon(entregas: [number, number]): IndicadoresDeLaRespuesta {
  return {
    activas_frente_a_registradas: { en_espera: ['DD-083'] },
    calificacion_por_lavanderia: CALIFICACION_POR_LAVANDERIA,
    entregas_por_repartidor: {
      repartidores: [
        {
          id_publico: REPARTIDOR_CON_PLAZA,
          repartidor: 'Repartidor de prueba 1',
          plaza: 'Plaza de prueba Durango',
          entregas: entregas[0],
        },
        { id_publico: REPARTIDOR_SIN_PLAZA, repartidor: 'Repartidor de prueba 2', plaza: null, entregas: entregas[1] },
      ],
    },
  };
}

// --- Financiero (F-I09 y F-I10) --------------------------------------------------------------------------------------

/** Los ingresos de cada semana de octubre, la imagen 48: los mismos que da GET /administracion/ingresos con ese rango. */
export const INGRESOS_DE_OCTUBRE = SEMANAS.map((semana, indice) => ({
  ...semana,
  ...[
    { pagos: 2, ...pesos('500.00', '$500.00') },
    { pagos: 3, ...pesos('750.00', '$750.00') },
    { pagos: 4, ...pesos('1000.00', '$1,000.00') },
    { pagos: 2, ...pesos('500.00', '$500.00') },
    { pagos: 2, ...pesos('500.00', '$500.00') },
  ][indice],
}));

export const FINANCIERO_DEL_MES_EN_CURSO: IndicadoresDeLaRespuesta = {
  ingresos_brutos: {
    periodos: DIAS.map((dia, indice) => ({
      ...dia,
      ...[
        { pagos: 1, ...pesos('150.00', '$150.00') },
        { pagos: 0, ...pesos('0.00', '$0.00') },
        { pagos: 2, ...pesos('350.00', '$350.00') },
        { pagos: 0, ...pesos('0.00', '$0.00') },
      ][indice],
    })),
  },
  comisiones: { en_espera: ['DD-020', 'DD-013'] },
};

export const FINANCIERO_DE_OCTUBRE: IndicadoresDeLaRespuesta = {
  ingresos_brutos: { periodos: INGRESOS_DE_OCTUBRE },
  comisiones: { en_espera: ['DD-020', 'DD-013'] },
};

// --- Geográfico (F-I11) ----------------------------------------------------------------------------------------------

/** Por colonia, la imagen 49: cinco pedidos de la colonia A, dos de la B y uno de la C, cada una con su color. */
export const POR_COLONIA: IndicadoresDeLaRespuesta = {
  mapa_de_calor: {
    agrupacion: 'colonia',
    colonias: [
      {
        colonia: 'Colonia de prueba A',
        municipio: 'Durango',
        plaza: 'Plaza de prueba Durango',
        pedidos: 5,
        intensidad: 1.0,
        color: '#104281',
        latitud: 24.03,
        longitud: -104.66,
      },
      {
        colonia: 'Colonia de prueba B',
        municipio: 'Durango',
        plaza: 'Plaza de prueba Durango',
        pedidos: 2,
        intensidad: 0.4,
        color: '#3987e5',
        latitud: 24.02,
        longitud: -104.64,
      },
      {
        colonia: 'Colonia de prueba C',
        municipio: null,
        plaza: 'Plaza de prueba Durango',
        pedidos: 1,
        intensidad: 0.2,
        color: '#6da7ec',
        latitud: 24.01,
        longitud: -104.65,
      },
    ],
  },
};

/**
 * Por zona de servicio, la imagen 69: el Centro con seis pedidos y un hueco; el Norte con dos, en dos partes; y el Sur,
 * activa, sin pedidos. La geometría va en GeoJSON, cada punto como [longitud, latitud].
 */
export const POR_ZONA: IndicadoresDeLaRespuesta = {
  mapa_de_calor: {
    agrupacion: 'zona',
    zonas: [
      {
        zona: 'Zona de prueba Durango Centro',
        plaza: 'Plaza de prueba Durango',
        pedidos: 6,
        intensidad: 1.0,
        color: '#104281',
        geometria: {
          type: 'MultiPolygon',
          coordinates: [
            [
              [
                [-104.67, 24.02],
                [-104.64, 24.02],
                [-104.64, 24.0],
                [-104.67, 24.0],
                [-104.67, 24.02],
              ],
              [
                [-104.66, 24.015],
                [-104.65, 24.015],
                [-104.65, 24.005],
                [-104.66, 24.015],
              ],
            ],
          ],
        },
      },
      {
        zona: 'Zona de prueba Durango Norte',
        plaza: 'Plaza de prueba Durango',
        pedidos: 2,
        intensidad: 0.33,
        color: '#6da7ec',
        geometria: {
          type: 'MultiPolygon',
          coordinates: [
            [
              [
                [-104.67, 24.05],
                [-104.64, 24.05],
                [-104.64, 24.03],
                [-104.67, 24.05],
              ],
            ],
            [
              [
                [-104.63, 24.06],
                [-104.62, 24.06],
                [-104.62, 24.05],
                [-104.63, 24.06],
              ],
            ],
          ],
        },
      },
      {
        zona: 'Zona de prueba Durango Sur',
        plaza: 'Plaza de prueba Durango',
        pedidos: 0,
        intensidad: 0.0,
        color: '#b7d3f6',
        geometria: {
          type: 'MultiPolygon',
          coordinates: [
            [
              [
                [-104.67, 23.99],
                [-104.64, 23.99],
                [-104.64, 23.97],
                [-104.67, 23.99],
              ],
            ],
          ],
        },
      },
    ],
  },
};

/** Lo que pidió la app en una consulta: la categoría y su consulta, como {} sin filtros. */
export interface ConsultaDeLosIndicadores {
  categoria: string;
  filtros: Record<string, string>;
}

/** Los indicadores del servidor simulado, con lo que responde cada categoría. */
export class IndicadoresDelServidor {
  /** El intervalo de indicadores.refresco que trae cada respuesta. */
  refresco: string | null = '30s';
  /** Los pedidos de hoy y los activos: un pedido que se confirma cuenta en la siguiente consulta (E51). */
  private pedidosDeHoy = 1;
  private activos = 2;
  /** Las respuestas con filtros, por categoría y consulta ("desde=…&granularidad=…&hasta=…"). */
  private readonly conFiltros = new Map<string, { rango: RangoDeLaRespuesta; indicadores: IndicadoresDeLaRespuesta }>();
  /** Las respuestas sin filtros que la prueba cambió, por categoría y agrupación. */
  private readonly sinFiltros = new Map<string, IndicadoresDeLaRespuesta>();

  constructor() {
    for (const [categoria, indicadores] of [
      ['operaciones', OPERACIONES_DE_OCTUBRE],
      ['usuarios', USUARIOS_DE_OCTUBRE],
      ['socios', sociosCon([8, 5])],
      ['financiero', FINANCIERO_DE_OCTUBRE],
    ] as const) {
      this.responder(categoria, OCTUBRE_POR_SEMANA, RANGO_DE_OCTUBRE_POR_SEMANA, indicadores);
    }
    for (const agrupacion of ['colonia', 'zona'] as const) {
      this.responder(
        'geografico',
        { ...OCTUBRE_POR_SEMANA, agrupacion },
        RANGO_DE_OCTUBRE_POR_SEMANA,
        agrupacion === 'zona' ? POR_ZONA : POR_COLONIA,
      );
    }
  }

  /** Se confirma un pedido nuevo hoy: cuenta en los pedidos de hoy y en los activos (indicadores-E21). */
  confirmarUnPedido(): void {
    this.pedidosDeHoy += 1;
    this.activos += 1;
  }

  /** Lo que responde una categoría sin filtros, en lugar del mes en curso de este apoyo. */
  cambiarSinFiltros(categoria: Categoria, indicadores: IndicadoresDeLaRespuesta, agrupacion = 'colonia'): void {
    this.sinFiltros.set(this.llave(categoria, agrupacion), indicadores);
  }

  /** Registra la respuesta de una categoría para esos filtros (en geografico, con su agrupación). */
  responder(
    categoria: Categoria,
    filtros: Record<string, string>,
    rango: RangoDeLaRespuesta,
    indicadores: IndicadoresDeLaRespuesta,
  ): void {
    this.conFiltros.set(`${categoria}?${consultaDe(filtros)}`, { rango, indicadores });
  }

  /** Contesta lo de /administracion/indicadores; lo demás, undefined. */
  readonly manejador = ({ metodo, ruta }: Peticion): unknown => {
    const [camino, consulta = ''] = ruta.split('?');
    if (metodo !== 'GET' || !camino.startsWith(`${RUTA_DE_LOS_INDICADORES}/`)) {
      return undefined;
    }
    const categoria = camino.slice(RUTA_DE_LOS_INDICADORES.length + 1);
    if (!['operaciones', 'usuarios', 'socios', 'financiero', 'geografico'].includes(categoria)) {
      return respuesta(404, { message: 'No existe la categoría.' });
    }
    const filtros = Object.fromEntries(new URLSearchParams(consulta));
    const { agrupacion = 'colonia', ...delRango } = filtros;
    if (Object.keys(delRango).length === 0) {
      return this.contestar(categoria, RANGO_DEL_MES_EN_CURSO, this.delMesEnCurso(categoria as Categoria, agrupacion));
    }
    const registrada = this.conFiltros.get(`${categoria}?${consultaDe(filtros)}`);
    return registrada ? this.contestar(categoria, registrada.rango, registrada.indicadores) : undefined;
  };

  private contestar(categoria: string, rango: RangoDeLaRespuesta, indicadores: IndicadoresDeLaRespuesta) {
    return respuesta(200, { categoria, rango, refresco: this.refresco, indicadores });
  }

  private delMesEnCurso(categoria: Categoria, agrupacion: string): IndicadoresDeLaRespuesta {
    const cambiada = this.sinFiltros.get(this.llave(categoria, agrupacion));
    if (cambiada) {
      return cambiada;
    }
    switch (categoria) {
      case 'operaciones':
        return operacionesDelMesEnCurso(this.pedidosDeHoy, this.activos);
      case 'usuarios':
        return USUARIOS_DEL_MES_EN_CURSO;
      case 'socios':
        return sociosCon([3, 0]);
      case 'financiero':
        return FINANCIERO_DEL_MES_EN_CURSO;
      case 'geografico':
        return agrupacion === 'zona' ? POR_ZONA : POR_COLONIA;
    }
  }

  private llave(categoria: Categoria, agrupacion: string): string {
    return categoria === 'geografico' ? `${categoria}:${agrupacion}` : categoria;
  }
}

/** La consulta de unos filtros, con sus claves en orden, para compararla sin importar el orden en que llegaron. */
function consultaDe(filtros: Record<string, string>): string {
  return Object.keys(filtros)
    .sort()
    .map((clave) => `${clave}=${filtros[clave]}`)
    .join('&');
}

/**
 * Prepara cada prueba del archivo con los indicadores del servidor simulado de la administración, ya registrados en él.
 * Devuelve los de la prueba en curso. Va después de usarElServidorDeLaAdministracion().
 */
export function usarLosIndicadores(servidor: () => ServidorDeLaAdministracion): () => IndicadoresDelServidor {
  let indicadores: IndicadoresDelServidor;
  beforeEach(() => {
    indicadores = new IndicadoresDelServidor();
    servidor().agregar(indicadores.manejador);
  });
  return () => indicadores;
}

/** Cada GET de los indicadores que hizo la app, en orden, con su categoría y sus filtros. */
export function consultasDeLosIndicadores(servidor: ServidorDeLaAdministracion): ConsultaDeLosIndicadores[] {
  return servidor
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'GET' && ruta.startsWith(`${RUTA_DE_LOS_INDICADORES}/`))
    .map(({ ruta }) => {
      const [camino, consulta = ''] = ruta.split('?');
      return {
        categoria: camino.slice(RUTA_DE_LOS_INDICADORES.length + 1),
        filtros: Object.fromEntries(new URLSearchParams(consulta)),
      };
    });
}

/** Solo las categorías que consultó la app, en orden. */
export function categoriasConsultadas(servidor: ServidorDeLaAdministracion): string[] {
  return consultasDeLosIndicadores(servidor).map(({ categoria }) => categoria);
}
