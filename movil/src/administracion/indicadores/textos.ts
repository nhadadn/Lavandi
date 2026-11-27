// Los textos de los indicadores de la administración (A.5; administracion-app-E45 a E53), juntos para revisarlos en un
// solo lugar. Son los de las imágenes 41 a 50 y 69 de la Propuesta 06 de los mocks; los que las imágenes no traen (el
// monto promedio sin tendencia, la tabla de entregas vacía, el mapa sin pedidos y el formato del porcentaje), los del
// panel de respaldo, para que la app y el panel digan lo mismo (servidor/app/Filament/Widgets/Indicadores/). Los textos
// de cada periodo, de cada mes, de cada monto, del semáforo y del rango ajustado los da el servidor; las DD que espera un
// indicador sin cifra, también. Ninguno trae el nombre del producto ni el dominio (CLAUDE.md §5; administracion-app-E03).
import { mensajeGeneral } from '../../acceso/mensajes';
import { TEXTOS as TEXTOS_DE_LAS_CERCANAS } from '../../cercanas/textos';
import type { SentidoDeLaTendencia } from '../../piezas/IndicadorDestacado';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LAS_DESCARGAS } from '../descargas/textos';
import type { Granularidad } from '../rango/rango';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';
import type { Agrupacion, Categoria, ColoniaDelMapa, MontoPromedio, Refresco } from './api';

export const TEXTOS_DE_LOS_INDICADORES = {
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.indicadores,
  cargando: 'Cargando los indicadores…',
  intentarDeNuevo: 'Intentar de nuevo',
  // Las pestañas, con los nombres de las categorías del A.5 (imagen 41).
  categorias: {
    operaciones: 'Operaciones',
    usuarios: 'Usuarios',
    socios: 'Socios',
    financiero: 'Financiero',
    geografico: 'Geográfico',
  } satisfies Record<Categoria, string>,
  // La hoja del rango (imagen 42): lo que la de los ingresos no trae.
  valeParaLasCinco: 'Vale para las cinco categorías.',
  esteMes: 'Este mes',
  mesAnterior: 'Mes anterior',
  // Lo que espera una respuesta, sin cifra (administracion-app-E53; imágenes 46 y 48).
  sinCifra: 'Sin cifra mientras espera una respuesta.',
  // Operaciones (imágenes 41 y 43).
  pedidosTotales: 'Pedidos totales',
  pedidosTotalesJuntoAlAnterior: 'Pedidos totales, cada periodo junto al anterior',
  periodo: 'Periodo',
  pedidos: 'Pedidos',
  anterior: 'Anterior',
  pedidosPorEstado: 'Pedidos por estado',
  activos: 'Activos',
  completados: 'Completados',
  cancelados: 'Cancelados',
  // Usuarios (imágenes 44 y 45).
  usuariosActivos: 'Usuarios activos',
  personasDistintas: 'Personas distintas.',
  queActividadCuenta: 'Qué actividad cuenta',
  vuelvenAPedir: 'Vuelven a pedir, por mes de registro',
  mesDeRegistro: 'Mes de registro',
  conPrimerPedido: 'Con primer pedido',
  volvieron: 'Volvieron',
  // El porcentaje de un mes sin primeros pedidos, como el panel (RetencionTabla).
  sinPorcentaje: '—',
  montoPromedio: 'Monto promedio por pedido',
  queMontoCuenta: 'Qué monto cuenta',
  // Sin monto en el periodo o en el anterior, como el panel (MontoPromedioIndicador).
  sinMonto: 'Sin monto',
  sinMontoEnElPeriodo: 'No hay pedidos con monto en el periodo.',
  sinMontoParaComparar: 'Sin pedidos con monto en el periodo anterior para comparar.',
  // Socios (imágenes 46 y 47).
  activasFrenteARegistradas: 'Lavanderías activas frente a registradas',
  calificacionPorLavanderia: 'Calificación por lavandería',
  cortesDelSemaforo: 'Cortes del semáforo',
  entregasPorRepartidor: 'Entregas por repartidor',
  repartidor: 'Repartidor',
  entregas: 'Entregas',
  // Como el panel (EntregasPorRepartidorTabla).
  sinPlaza: 'Sin plaza',
  sinRepartidores: 'No hay repartidores dados de alta.',
  // Financiero (imagen 48).
  ingresosBrutos: 'Ingresos brutos',
  loQueCuentanLosIngresos:
    'Lo cobrado con pago confirmado por la pasarela, en pesos. Cada cifra es la misma de la pantalla Ingresos para el mismo rango.',
  comoSeAgrupanLasBarras: 'Cómo se agrupan las barras',
  comisiones: 'Comisiones por socio y en total',
  // Geográfico (imágenes 49, 50 y 69).
  verElMapaPor: 'Ver el mapa por',
  agrupaciones: { colonia: 'Colonia', zona: 'Zona de servicio' } satisfies Record<Agrupacion, string>,
  pedidosPor: { colonia: 'Pedidos por colonia', zona: 'Pedidos por zona' } satisfies Record<Agrupacion, string>,
  // El título de la primera columna de la tabla bajo el mapa.
  columnaDelMapa: { colonia: 'Colonia', zona: 'Zona de servicio' } satisfies Record<Agrupacion, string>,
  // Para el lector de pantalla, lo que dibuja el mapa, como la descripción del mapa del panel (MapaDeCalor).
  elMapa: {
    colonia: 'Mapa de calor de pedidos por colonia: cada punto es una colonia de recolección; más grande y más oscuro, más pedidos',
    zona: 'Mapa de calor de pedidos por zona de servicio: cada zona, pintada por su número de pedidos; más oscuro, más pedidos',
  } satisfies Record<Agrupacion, string>,
  // Como el panel (mapa-de-calor.blade.php).
  sinPedidosEnElRango: 'No hay pedidos en el rango.',
  // Solo en una compilación de Android sin la llave del mapa (imagen 50; src/cercanas/mapa.ts), como en todos los mapas
  // de la app.
  mapaNoDisponible: TEXTOS_DE_LAS_CERCANAS.mapaNoDisponible,
} as const;

/**
 * Las DD que esperan las reglas de los indicadores que ya tienen cifra (administracion-app-E55): su lugar va en ámbar
 * bajo la gráfica, sin cambiar la cifra (imágenes 43 a 48). Las de los indicadores sin cifra (F-I06 y F-I10) las da el
 * servidor en "en_espera".
 */
export const DD_DE_LOS_INDICADORES = {
  cancelados: ['DD-016'],
  queActividadCuenta: ['DD-111'],
  queMontoCuenta: ['DD-012'],
  cortesDelSemaforo: ['DD-114'],
  comoSeAgrupanLasBarras: ['DD-115'],
} as const;

/**
 * Lo que lee el lector de pantalla en el "Descargar" de un indicador: "Descargar Pedidos totales". Cada indicador con
 * cifra trae el suyo, y el texto solo no dice cuál reporte baja.
 */
export function descargarElIndicador(titulo: string): string {
  return `${TEXTOS_DE_LAS_DESCARGAS.descargar} ${titulo}`;
}

/**
 * Bajo el rango, cada cuánto se ponen al día las cifras, con el intervalo de la respuesta, con las mismas palabras del
 * panel (App\Indicadores\Refresco::descripcion()): "Las cifras se actualizan solas cada 30 segundos.". Como el panel,
 * la unidad va siempre en plural, también con "1s". Sin intervalo, nada: la pantalla no se consulta sola, y la frase del
 * panel para ese caso habla de recargar la página.
 */
export function cadaCuantoSeActualizan(refresco: Refresco | null): string | undefined {
  if (refresco === null) {
    return undefined;
  }
  const { cantidad, unidad } = refresco;
  return `Las cifras se actualizan solas cada ${cantidad} ${unidad === 's' ? 'segundos' : 'milisegundos'}.`;
}

/**
 * El periodo corto bajo una barra o un punto, del primer día del periodo que da el servidor (AAAA-MM-DD): por día y por
 * semana, "26/10" (imágenes 41, 44 y 48); por mes, "10/2026". El periodo completo lo lee el lector de pantalla.
 */
export function periodoCorto(inicio: string, granularidad: Granularidad): string {
  const [anio, mes, dia] = inicio.split('-');
  return granularidad === 'mes' ? `${mes}/${anio}` : `${dia}/${mes}`;
}

/** El porcentaje de los que volvieron a pedir, con un decimal solo si lo tiene, como el panel: "50 %", "33.3 %". */
export function porcentajeEnTexto(porcentaje: number | null): string {
  if (porcentaje === null) {
    return TEXTOS_DE_LOS_INDICADORES.sinPorcentaje;
  }
  return `${Number(porcentaje.toFixed(1))} %`;
}

/** "4.8 de 5": la calificación promedio de una lavandería, con un decimal (imagen 46). */
export function calificacionEnTexto(promedio: number): string {
  return `${promedio.toFixed(1)} de 5`;
}

/** El centro de la dona: cuántos pedidos suman sus porciones y lo que cuenta (imagen 43). */
export function centroDeLaDona(total: number): { cifra: string; texto: string } {
  return { cifra: String(total), texto: total === 1 ? 'pedido' : 'pedidos' };
}

/** La cifra destacada del monto promedio y lo que dice debajo: su tendencia, o por qué no la hay. */
export interface MontoALaVista {
  valor: string;
  detalle: string;
  sentido?: SentidoDeLaTendencia;
}

/**
 * El monto promedio con su tendencia frente al periodo anterior (imagen 45): "Subió frente al periodo anterior
 * ($200.00).", con el texto de la tendencia que da el servidor; igual, como el panel, "Igual que en el periodo anterior
 * ($250.00).". Sin monto en el periodo o en el anterior, lo dice como el panel.
 */
export function montoALaVista(monto: MontoPromedio): MontoALaVista {
  const textos = TEXTOS_DE_LOS_INDICADORES;
  if (monto.promedio_texto === null) {
    return { valor: textos.sinMonto, detalle: textos.sinMontoEnElPeriodo };
  }
  if (monto.tendencia === null || monto.tendencia_texto === null || monto.promedio_anterior_texto === null) {
    return { valor: monto.promedio_texto, detalle: textos.sinMontoParaComparar };
  }
  const anterior = `(${monto.promedio_anterior_texto})`;
  const detalle =
    monto.tendencia === 'igual'
      ? `Igual que en el periodo anterior ${anterior}.`
      : `${monto.tendencia_texto} frente al periodo anterior ${anterior}.`;
  return { valor: monto.promedio_texto, detalle, sentido: monto.tendencia };
}

/**
 * La letra de la colonia en el mapa y en la tabla, por su lugar en la lista, para ligar cada punto con su fila (imagen
 * 49): A, B, … Z, y después AA, AB…
 */
export function letraDeLaColonia(indice: number): string {
  const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
  let letra = '';
  for (let resto = indice; resto >= 0; resto = Math.floor(resto / letras.length) - 1) {
    letra = letras[resto % letras.length] + letra;
  }
  return letra;
}

/** "Colonia de prueba A, Durango": la colonia y su municipio, si lo tiene (imagen 49). */
export function coloniaEnTexto({ colonia, municipio }: Pick<ColoniaDelMapa, 'colonia' | 'municipio'>): string {
  return municipio ? `${colonia}, ${municipio}` : colonia;
}

/** "1 pedido", "5 pedidos": lo que dice cada figura del mapa, como el panel. */
export function pedidosEnTexto(pedidos: number): string {
  return pedidos === 1 ? '1 pedido' : `${pedidos} pedidos`;
}

/**
 * El mensaje de una consulta que no salió, sin escenario propio: los mensajes generales de la app, como las demás
 * pantallas del módulo. Con el 401 no hay nada que decir: la app ya volvió al inicio de sesión.
 */
export function mensajeDeLosIndicadores(falla: FallaDeLaAdministracion): string | undefined {
  switch (falla.motivo) {
    case 'sin-sesion':
      return undefined;
    case 'conflicto':
    case 'no-existe':
      return falla.mensaje || mensajeGeneral({ ok: false, motivo: 'servidor' });
    default:
      return mensajeGeneral(falla);
  }
}
