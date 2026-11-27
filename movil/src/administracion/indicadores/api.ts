// Los indicadores del negocio en la app de la administración (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5;
// NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4; administracion-app-E45 a E53): una categoría del A.5 por consulta, la
// de la pestaña que se ve.
//
// El contrato es el del comentario de servidor/app/Http/Controllers/Api/Administracion/IndicadoresController.php,
// copiado aquí:
//
//     GET /api/administracion/indicadores/{categoria}?desde=AAAA-MM-DD&hasta=AAAA-MM-DD&granularidad=dia|semana|mes
//         categoria     operaciones, usuarios, socios, financiero o geografico; otra responde 404
//         desde, hasta  opcionales: sin ellos, el mes en curso hasta hoy (indicadores-E24). Un día que no se entiende
//                       se toma como no dado y un rango al revés se voltea: nunca responde 422
//         granularidad  opcional: dia (por omisión), semana o mes
//         agrupacion    solo en geografico, opcional: colonia (por omisión) o zona
//         200 { categoria,
//               rango: { desde, hasta, granularidad, descripcion },   el que se usó, ajustado a periodos completos
//               refresco: "30s" | "1500ms" | null,                    cada cuánto se consulta sola la pestaña visible;
//                                                                     null: no se consulta sola (indicadores-E22)
//               indicadores: { ... } }                                los de la categoría, abajo
//
//     operaciones  pedidos_totales: { periodos: [ { inicio, periodo, pedidos, periodo_anterior, pedidos_anterior } ] }
//                  pedidos_por_estado: { activos, completados }
//     usuarios     usuarios_activos: { periodos: [ { inicio, periodo, personas } ] }
//                  vuelven_a_pedir: { meses: [ { inicio, mes, con_primer_pedido, porcentaje: 33.3 | null } ] }
//                  monto_promedio: { promedio, promedio_texto, promedio_anterior, promedio_anterior_texto, diferencia,
//                                    tendencia: subio | bajo | igual | null, tendencia_texto }   cada uno puede ser null
//     socios       activas_frente_a_registradas: { en_espera: ["DD-083"] }
//                  calificacion_por_lavanderia: { semaforo_configurado: true | false,
//                                                 lavanderias: [ { id_publico, lugar, lavanderia, promedio, semaforo,
//                                                                  semaforo_texto } ] }
//                                     semaforo_configurado: si la configuración trae los dos cortes; sin ellos, ningún
//                                     semáforo, como el "Sin cortes configurados" del panel. Las cifras de los cortes no
//                                     llegan: las fija EL CLIENTE (DD-114) y la pantalla no las muestra (imágenes 46 y 47
//                                     de la Propuesta 06)
//                  entregas_por_repartidor: { repartidores: [ { id_publico, repartidor, plaza, entregas } ] }
//     financiero   ingresos_brutos: { periodos: [ { inicio, periodo, pagos, ingresos, ingresos_texto } ] }
//                  comisiones: { en_espera: ["DD-020", "DD-013"] }
//     geografico   mapa_de_calor: { agrupacion: "colonia", colonias: [ { colonia, municipio, plaza, pedidos,
//                                                                         intensidad, color, latitud, longitud } ] }
//                  mapa_de_calor: { agrupacion: "zona", zonas: [ { zona, plaza, pedidos, intensidad, color,
//                                                                  geometria: GeoJSON MultiPolygon } ] }
//
//     401 sin sesión o con el token vencido; 403 a una cuenta que no es de administración (administracion-app-E06 y E07)
//
// Cada cifra, el rango ajustado, los textos de cada periodo, de cada monto y del semáforo, el color de cada colonia y de
// cada zona y el intervalo los da el servidor, con las mismas clases que el panel (ADR-0022, decisión 9): la app no
// calcula ni ajusta ninguno. Un indicador que espera una respuesta llega con sus DD en "en_espera" y sin cifra
// (administracion-app-E53).
//
// Va sobre el cliente de la administración (src/administracion/api.ts), que pone el token y borra la sesión con un 401.
// Como las demás consultas, nunca lanza: devuelve un Resultado. Una respuesta fuera del contrato no se pinta a medias:
// cuenta como falla del servidor.
import { esObjeto, numero } from '../../configuracion/lectura';
import type { ColorDelSemaforo } from '../../piezas/RankingConSemaforo';
import type { SentidoDeLaTendencia } from '../../piezas/IndicadorDestacado';
import type { PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';
import { GRANULARIDADES, type Granularidad, type RangoAjustado } from '../rango/rango';

/** Las cinco categorías del A.5, en su orden, con el nombre que lleva cada una en la ruta. */
export const CATEGORIAS = ['operaciones', 'usuarios', 'socios', 'financiero', 'geografico'] as const;
export type Categoria = (typeof CATEGORIAS)[number];

/** El mapa de calor por colonia, el de omisión, o por zona de servicio. */
export const AGRUPACIONES = ['colonia', 'zona'] as const;
export type Agrupacion = (typeof AGRUPACIONES)[number];

/** La ruta y los campos de los indicadores, bajo /api/administracion, en un solo lugar para ajustarlos. */
export const CONTRATO_DE_LOS_INDICADORES = {
  indicadores: 'indicadores',
  desde: 'desde',
  hasta: 'hasta',
  granularidad: 'granularidad',
  agrupacion: 'agrupacion',
} as const;

/** Un indicador cuya cifra espera una respuesta: solo sus DD (administracion-app-E53). */
export interface EnEspera {
  en_espera: string[];
}

/** F-I01: un periodo del rango con sus pedidos, junto al periodo anterior y los suyos. */
export interface PeriodoDePedidos {
  inicio: string;
  periodo: string;
  pedidos: number;
  periodo_anterior: string;
  pedidos_anterior: number;
}

export interface IndicadoresDeOperaciones {
  pedidos_totales: { periodos: PeriodoDePedidos[] };
  pedidos_por_estado: { activos: number; completados: number };
}

/** F-I03: un periodo del rango con las personas distintas que tuvieron actividad. */
export interface PeriodoDeUsuarios {
  inicio: string;
  periodo: string;
  personas: number;
}

/** F-I04: un mes de registro, con las cuentas que pidieron y el porcentaje que volvió a pedir (null si ninguna pidió). */
export interface MesDeRegistro {
  inicio: string;
  mes: string;
  con_primer_pedido: number;
  porcentaje: number | null;
}

/** F-I05: el monto promedio frente al periodo anterior de la misma duración. Cada cifra puede faltar. */
export interface MontoPromedio {
  promedio: string | null;
  promedio_texto: string | null;
  promedio_anterior: string | null;
  promedio_anterior_texto: string | null;
  diferencia: string | null;
  tendencia: SentidoDeLaTendencia | null;
  tendencia_texto: string | null;
}

export interface IndicadoresDeUsuarios {
  usuarios_activos: { periodos: PeriodoDeUsuarios[] };
  vuelven_a_pedir: { meses: MesDeRegistro[] };
  monto_promedio: MontoPromedio;
}

/** F-I07: una lavandería del ranking. Sin calificaciones, sin lugar, sin promedio y sin semáforo. */
export interface LavanderiaDelRanking {
  id_publico: string;
  lugar: number | null;
  lavanderia: string;
  promedio: number | null;
  semaforo: ColorDelSemaforo | null;
  semaforo_texto: string | null;
}

/** F-I08: un repartidor con sus entregas completadas en el rango. */
export interface RepartidorConEntregas {
  id_publico: string;
  repartidor: string;
  plaza: string | null;
  entregas: number;
}

export interface IndicadoresDeSocios {
  activas_frente_a_registradas: EnEspera;
  calificacion_por_lavanderia: {
    /** Si la configuración trae los dos cortes del semáforo; sin ellos, ninguna lavandería lleva color. */
    semaforo_configurado: boolean;
    lavanderias: LavanderiaDelRanking[];
  };
  entregas_por_repartidor: { repartidores: RepartidorConEntregas[] };
}

/** F-I09: un periodo del rango con sus pagos confirmados y lo que suman, en pesos y como lo lee el panel. */
export interface PeriodoDeIngresos {
  inicio: string;
  periodo: string;
  pagos: number;
  ingresos: string;
  ingresos_texto: string;
}

export interface IndicadoresFinancieros {
  ingresos_brutos: { periodos: PeriodoDeIngresos[] };
  comisiones: EnEspera;
}

/** Un punto del mapa, como lo usa la app. */
export interface Punto {
  latitud: number;
  longitud: number;
}

/** Un polígono de una zona: su contorno y sus huecos, ya leídos del GeoJSON (que da cada punto como [longitud, latitud]). */
export interface Poligono {
  contorno: Punto[];
  huecos: Punto[][];
}

/** F-I11 por colonia: el centro de sus recolecciones, sus pedidos y el color que le da el servidor. */
export interface ColoniaDelMapa {
  colonia: string;
  municipio: string | null;
  plaza: string;
  pedidos: number;
  intensidad: number;
  color: string;
  latitud: number;
  longitud: number;
}

/** F-I11 por zona de servicio: sus polígonos, sus pedidos y el color que le da el servidor. */
export interface ZonaDelMapa {
  zona: string;
  plaza: string;
  pedidos: number;
  intensidad: number;
  color: string;
  poligonos: Poligono[];
}

export type MapaDeCalor = { agrupacion: 'colonia'; colonias: ColoniaDelMapa[] } | { agrupacion: 'zona'; zonas: ZonaDelMapa[] };

export interface IndicadoresGeograficos {
  mapa_de_calor: MapaDeCalor;
}

/** Los indicadores de cada categoría. */
export interface IndicadoresPorCategoria {
  operaciones: IndicadoresDeOperaciones;
  usuarios: IndicadoresDeUsuarios;
  socios: IndicadoresDeSocios;
  financiero: IndicadoresFinancieros;
  geografico: IndicadoresGeograficos;
}

/** El intervalo de la respuesta, como lo da el servidor ("30s" o "1500ms") y en milisegundos para la consulta. */
export interface Refresco {
  cantidad: number;
  unidad: 's' | 'ms';
  milisegundos: number;
}

/** La respuesta de una categoría, con su rango ajustado, su intervalo (null: no se consulta sola) y sus indicadores. */
export type IndicadoresDeLaCategoria = {
  [C in Categoria]: { categoria: C; rango: RangoAjustado; refresco: Refresco | null; indicadores: IndicadoresPorCategoria[C] };
}[Categoria];

/** Los filtros que eligió la administración, como los recibe el servidor (AAAA-MM-DD); sin ellos, los del servidor. */
export interface FiltrosDeLosIndicadores {
  desde: string;
  hasta: string;
  granularidad: Granularidad;
}

const FUERA_DEL_CONTRATO = { ok: false, motivo: 'servidor' } as const;

/**
 * GET indicadores/{categoria}: sin filtros, el rango que da el servidor; con ellos, el que se eligió. La agrupación va
 * solo en la geográfica (administracion-app-E45 y E50).
 */
export async function consultarIndicadores(
  pedir: PedirALaAdministracion,
  categoria: Categoria,
  filtros: FiltrosDeLosIndicadores | null,
  agrupacion: Agrupacion,
): Promise<ResultadoDeLaAdministracion<IndicadoresDeLaCategoria>> {
  const { indicadores, desde, hasta, granularidad } = CONTRATO_DE_LOS_INDICADORES;
  const campos: [string, string][] = filtros
    ? [
        [desde, filtros.desde],
        [hasta, filtros.hasta],
        [granularidad, filtros.granularidad],
      ]
    : [];
  if (categoria === 'geografico') {
    campos.push([CONTRATO_DE_LOS_INDICADORES.agrupacion, agrupacion]);
  }
  const consulta = campos
    .filter(([, valor]) => valor !== '')
    .map(([campo, valor]) => `${campo}=${encodeURIComponent(valor)}`)
    .join('&');
  const ruta = `${indicadores}/${categoria}`;
  const resultado = await pedir<unknown>(consulta ? `${ruta}?${consulta}` : ruta);
  if (!resultado.ok) {
    return resultado;
  }
  const leidos = leerRespuesta(resultado.datos, categoria);
  return leidos ? { ok: true, datos: leidos } : FUERA_DEL_CONTRATO;
}

/** "30s" o "1500ms", un entero mayor que cero, como App\Indicadores\Refresco; otra cosa no sirve y es null. */
export function leerRefresco(valor: unknown): Refresco | null {
  const partes = typeof valor === 'string' ? /^([1-9]\d*)(s|ms)$/.exec(valor) : null;
  if (!partes) {
    return null;
  }
  const cantidad = Number(partes[1]);
  const unidad = partes[2] as Refresco['unidad'];
  return { cantidad, unidad, milisegundos: unidad === 's' ? cantidad * 1000 : cantidad };
}

// Lo que se lee de cada campo. Un campo que no es del contrato deja fuera toda la respuesta.

function esTexto(valor: unknown): valor is string {
  return typeof valor === 'string';
}

function esTextoONulo(valor: unknown): valor is string | null {
  return valor === null || typeof valor === 'string';
}

function esCuenta(valor: unknown): valor is number {
  return typeof valor === 'number' && Number.isInteger(valor) && valor >= 0;
}

function esNumeroONulo(valor: unknown): valor is number | null {
  return valor === null || (typeof valor === 'number' && Number.isFinite(valor));
}

function esGranularidad(valor: unknown): valor is Granularidad {
  return GRANULARIDADES.includes(valor as Granularidad);
}

function esSentido(valor: unknown): valor is SentidoDeLaTendencia | null {
  return valor === null || valor === 'subio' || valor === 'bajo' || valor === 'igual';
}

function esColorDelSemaforo(valor: unknown): valor is ColorDelSemaforo | null {
  return valor === null || valor === 'verde' || valor === 'amarillo' || valor === 'rojo';
}

/** Cada elemento de la lista con su lector; null si la lista no es lista o alguno no se lee. */
function lista<T>(valor: unknown, leerUno: (uno: unknown) => T | null): T[] | null {
  if (!Array.isArray(valor)) {
    return null;
  }
  const leidos = valor.map(leerUno);
  return leidos.every((uno): uno is T => uno !== null) ? leidos : null;
}

/** El campo de un objeto que es un objeto con una lista: { periodos: [...] }. */
function listaDentro<T>(valor: unknown, campo: string, leerUno: (uno: unknown) => T | null): T[] | null {
  return esObjeto(valor) ? lista(valor[campo], leerUno) : null;
}

function leerEnEspera(valor: unknown): EnEspera | null {
  const dds = esObjeto(valor) ? lista(valor.en_espera, (dd) => (esTexto(dd) ? dd : null)) : null;
  return dds && dds.length > 0 ? { en_espera: dds } : null;
}

function leerPeriodoDePedidos(valor: unknown): PeriodoDePedidos | null {
  if (
    !esObjeto(valor) ||
    !esTexto(valor.inicio) ||
    !esTexto(valor.periodo) ||
    !esCuenta(valor.pedidos) ||
    !esTexto(valor.periodo_anterior) ||
    !esCuenta(valor.pedidos_anterior)
  ) {
    return null;
  }
  return {
    inicio: valor.inicio,
    periodo: valor.periodo,
    pedidos: valor.pedidos,
    periodo_anterior: valor.periodo_anterior,
    pedidos_anterior: valor.pedidos_anterior,
  };
}

function leerOperaciones(valor: Record<string, unknown>): IndicadoresDeOperaciones | null {
  const periodos = listaDentro(valor.pedidos_totales, 'periodos', leerPeriodoDePedidos);
  const porEstado = valor.pedidos_por_estado;
  if (!periodos || !esObjeto(porEstado) || !esCuenta(porEstado.activos) || !esCuenta(porEstado.completados)) {
    return null;
  }
  return {
    pedidos_totales: { periodos },
    pedidos_por_estado: { activos: porEstado.activos, completados: porEstado.completados },
  };
}

function leerPeriodoDeUsuarios(valor: unknown): PeriodoDeUsuarios | null {
  if (!esObjeto(valor) || !esTexto(valor.inicio) || !esTexto(valor.periodo) || !esCuenta(valor.personas)) {
    return null;
  }
  return { inicio: valor.inicio, periodo: valor.periodo, personas: valor.personas };
}

function leerMesDeRegistro(valor: unknown): MesDeRegistro | null {
  if (
    !esObjeto(valor) ||
    !esTexto(valor.inicio) ||
    !esTexto(valor.mes) ||
    !esCuenta(valor.con_primer_pedido) ||
    !esNumeroONulo(valor.porcentaje)
  ) {
    return null;
  }
  return { inicio: valor.inicio, mes: valor.mes, con_primer_pedido: valor.con_primer_pedido, porcentaje: valor.porcentaje };
}

function leerMontoPromedio(valor: unknown): MontoPromedio | null {
  if (
    !esObjeto(valor) ||
    !esTextoONulo(valor.promedio) ||
    !esTextoONulo(valor.promedio_texto) ||
    !esTextoONulo(valor.promedio_anterior) ||
    !esTextoONulo(valor.promedio_anterior_texto) ||
    !esTextoONulo(valor.diferencia) ||
    !esSentido(valor.tendencia) ||
    !esTextoONulo(valor.tendencia_texto)
  ) {
    return null;
  }
  return {
    promedio: valor.promedio,
    promedio_texto: valor.promedio_texto,
    promedio_anterior: valor.promedio_anterior,
    promedio_anterior_texto: valor.promedio_anterior_texto,
    diferencia: valor.diferencia,
    tendencia: valor.tendencia,
    tendencia_texto: valor.tendencia_texto,
  };
}

function leerUsuarios(valor: Record<string, unknown>): IndicadoresDeUsuarios | null {
  const periodos = listaDentro(valor.usuarios_activos, 'periodos', leerPeriodoDeUsuarios);
  const meses = listaDentro(valor.vuelven_a_pedir, 'meses', leerMesDeRegistro);
  const monto = leerMontoPromedio(valor.monto_promedio);
  if (!periodos || !meses || !monto) {
    return null;
  }
  return { usuarios_activos: { periodos }, vuelven_a_pedir: { meses }, monto_promedio: monto };
}

function leerLavanderiaDelRanking(valor: unknown): LavanderiaDelRanking | null {
  if (
    !esObjeto(valor) ||
    !esTexto(valor.id_publico) ||
    !(valor.lugar === null || esCuenta(valor.lugar)) ||
    !esTexto(valor.lavanderia) ||
    !esNumeroONulo(valor.promedio) ||
    !esColorDelSemaforo(valor.semaforo) ||
    !esTextoONulo(valor.semaforo_texto)
  ) {
    return null;
  }
  return {
    id_publico: valor.id_publico,
    lugar: valor.lugar,
    lavanderia: valor.lavanderia,
    promedio: valor.promedio,
    semaforo: valor.semaforo,
    semaforo_texto: valor.semaforo_texto,
  };
}

function leerRepartidor(valor: unknown): RepartidorConEntregas | null {
  if (
    !esObjeto(valor) ||
    !esTexto(valor.id_publico) ||
    !esTexto(valor.repartidor) ||
    !esTextoONulo(valor.plaza) ||
    !esCuenta(valor.entregas)
  ) {
    return null;
  }
  return { id_publico: valor.id_publico, repartidor: valor.repartidor, plaza: valor.plaza, entregas: valor.entregas };
}

function leerSocios(valor: Record<string, unknown>): IndicadoresDeSocios | null {
  const activas = leerEnEspera(valor.activas_frente_a_registradas);
  const calificacion = valor.calificacion_por_lavanderia;
  const configurado = esObjeto(calificacion) ? calificacion.semaforo_configurado : undefined;
  const lavanderias = listaDentro(calificacion, 'lavanderias', leerLavanderiaDelRanking);
  const repartidores = listaDentro(valor.entregas_por_repartidor, 'repartidores', leerRepartidor);
  if (!activas || typeof configurado !== 'boolean' || !lavanderias || !repartidores) {
    return null;
  }
  return {
    activas_frente_a_registradas: activas,
    calificacion_por_lavanderia: { semaforo_configurado: configurado, lavanderias },
    entregas_por_repartidor: { repartidores },
  };
}

function leerPeriodoDeIngresos(valor: unknown): PeriodoDeIngresos | null {
  if (
    !esObjeto(valor) ||
    !esTexto(valor.inicio) ||
    !esTexto(valor.periodo) ||
    !esCuenta(valor.pagos) ||
    !esTexto(valor.ingresos) ||
    numero(valor.ingresos) === null ||
    !esTexto(valor.ingresos_texto)
  ) {
    return null;
  }
  return {
    inicio: valor.inicio,
    periodo: valor.periodo,
    pagos: valor.pagos,
    ingresos: valor.ingresos,
    ingresos_texto: valor.ingresos_texto,
  };
}

function leerFinanciero(valor: Record<string, unknown>): IndicadoresFinancieros | null {
  const periodos = listaDentro(valor.ingresos_brutos, 'periodos', leerPeriodoDeIngresos);
  const comisiones = leerEnEspera(valor.comisiones);
  if (!periodos || !comisiones) {
    return null;
  }
  return { ingresos_brutos: { periodos }, comisiones };
}

/** Un color #RRGGBB, como los de App\Indicadores\Intensidad::RAMPA. */
function esColor(valor: unknown): valor is string {
  return typeof valor === 'string' && /^#[0-9a-fA-F]{6}$/.test(valor);
}

function leerColonia(valor: unknown): ColoniaDelMapa | null {
  if (!esObjeto(valor)) {
    return null;
  }
  const intensidad = numero(valor.intensidad);
  const latitud = numero(valor.latitud);
  const longitud = numero(valor.longitud);
  if (
    !esTexto(valor.colonia) ||
    !esTextoONulo(valor.municipio) ||
    !esTexto(valor.plaza) ||
    !esCuenta(valor.pedidos) ||
    intensidad === null ||
    !esColor(valor.color) ||
    latitud === null ||
    longitud === null
  ) {
    return null;
  }
  return {
    colonia: valor.colonia,
    municipio: valor.municipio,
    plaza: valor.plaza,
    pedidos: valor.pedidos,
    intensidad,
    color: valor.color,
    latitud,
    longitud,
  };
}

/** Un punto del GeoJSON, [longitud, latitud]. */
function leerPosicion(valor: unknown): Punto | null {
  if (!Array.isArray(valor) || valor.length < 2) {
    return null;
  }
  const [longitud, latitud] = valor.map(numero);
  return latitud === null || longitud === null || latitud === undefined || longitud === undefined
    ? null
    : { latitud, longitud };
}

/** Los anillos de un polígono del GeoJSON: el primero es el contorno y los demás, sus huecos. */
function leerAnillos(valor: unknown): Poligono | null {
  const anillos = lista(valor, (anillo) => lista(anillo, leerPosicion));
  if (!anillos || anillos.length === 0) {
    return null;
  }
  const [contorno, ...huecos] = anillos;
  return { contorno, huecos };
}

/** La geometría de una zona en GeoJSON, como al panel: Polygon o MultiPolygon (ST_Multi). */
function leerGeometria(valor: unknown): Poligono[] | null {
  if (!esObjeto(valor)) {
    return null;
  }
  if (valor.type === 'Polygon') {
    const poligono = leerAnillos(valor.coordinates);
    return poligono ? [poligono] : null;
  }
  if (valor.type === 'MultiPolygon') {
    return lista(valor.coordinates, leerAnillos);
  }
  return null;
}

function leerZona(valor: unknown): ZonaDelMapa | null {
  if (!esObjeto(valor)) {
    return null;
  }
  const intensidad = numero(valor.intensidad);
  const poligonos = leerGeometria(valor.geometria);
  if (
    !esTexto(valor.zona) ||
    !esTexto(valor.plaza) ||
    !esCuenta(valor.pedidos) ||
    intensidad === null ||
    !esColor(valor.color) ||
    !poligonos
  ) {
    return null;
  }
  return { zona: valor.zona, plaza: valor.plaza, pedidos: valor.pedidos, intensidad, color: valor.color, poligonos };
}

function leerGeografico(valor: Record<string, unknown>): IndicadoresGeograficos | null {
  const mapa = valor.mapa_de_calor;
  if (!esObjeto(mapa)) {
    return null;
  }
  if (mapa.agrupacion === 'colonia') {
    const colonias = lista(mapa.colonias, leerColonia);
    return colonias ? { mapa_de_calor: { agrupacion: 'colonia', colonias } } : null;
  }
  if (mapa.agrupacion === 'zona') {
    const zonas = lista(mapa.zonas, leerZona);
    return zonas ? { mapa_de_calor: { agrupacion: 'zona', zonas } } : null;
  }
  return null;
}

const LECTORES: { [C in Categoria]: (valor: Record<string, unknown>) => IndicadoresPorCategoria[C] | null } = {
  operaciones: leerOperaciones,
  usuarios: leerUsuarios,
  socios: leerSocios,
  financiero: leerFinanciero,
  geografico: leerGeografico,
};

function leerRango(valor: unknown): RangoAjustado | null {
  if (
    !esObjeto(valor) ||
    !esTexto(valor.desde) ||
    !esTexto(valor.hasta) ||
    !esGranularidad(valor.granularidad) ||
    !esTexto(valor.descripcion)
  ) {
    return null;
  }
  return { desde: valor.desde, hasta: valor.hasta, granularidad: valor.granularidad, descripcion: valor.descripcion };
}

/** La respuesta de la categoría que se pidió; null si es otra o si algo no es del contrato. */
function leerRespuesta<C extends Categoria>(valor: unknown, categoria: C): IndicadoresDeLaCategoria | null {
  if (!esObjeto(valor) || valor.categoria !== categoria || !esObjeto(valor.indicadores)) {
    return null;
  }
  const rango = leerRango(valor.rango);
  const indicadores = LECTORES[categoria](valor.indicadores);
  if (!rango || !indicadores || !(valor.refresco === null || esTexto(valor.refresco))) {
    return null;
  }
  return { categoria, rango, refresco: leerRefresco(valor.refresco), indicadores } as IndicadoresDeLaCategoria;
}
