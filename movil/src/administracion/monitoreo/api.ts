// El monitoreo de pedidos de la administración en la app (F-D04; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
// #4, "Vista en tiempo real de todos los pedidos activos con mapa"; administracion-app-E28 a E30): los pedidos activos de
// todas las plazas y sus marcadores, en una sola respuesta, así que la lista y el mapa nunca difieren (ADR-0022,
// decisión 12).
//
// El contrato es el del comentario de servidor/app/Http/Controllers/Api/Administracion/MonitoreoController.php, copiado
// aquí. Qué pedidos están activos, en qué orden van, cómo se nombra su estado y qué marcadores lleva cada uno lo decide el
// servidor, con las mismas clases que el monitoreo del panel (ADR-0022, decisión 9): la app no decide ninguno. No trae
// nada de ningún repartidor (DD-077).
//
//     GET /api/administracion/monitoreo       Sin parámetros. La app la consulta cada 30 s mientras la pantalla está
//                                             visible y la app al frente (ADR-0007; administracion-app-E29)
//
//     200  {
//            "pedidos": [
//              {
//                "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",    el ULID del pedido
//                "folio": "7KQ2M9XA",                           siempre lleno: el pedido activo está pagado
//                "estado": { "codigo": "en_proceso", "nombre": "En proceso" },
//                                                              el estado operativo y su nombre, el mismo del panel y
//                                                              de los marcadores
//                "plaza": "Plaza de prueba",                   el nombre de la plaza del pedido
//                "ventana": {                                  la recolección: instantes con el desfase de la plaza,
//                  "inicio": "2026-12-03T11:00:00-06:00",      y la fecha y las horas en su hora
//                  "fin": "2026-12-03T12:00:00-06:00",
//                  "fecha": "2026-12-03",
//                  "hora_inicio": "11:00",
//                  "hora_fin": "12:00"
//                }
//              }
//            ],
//            "marcadores": [
//              {
//                "pedido": "01jd8x6m3v2c9q4w5e7r8t0y1z",       el id_publico de su pedido en "pedidos"
//                "folio": "7KQ2M9XA",
//                "estado": "En proceso",                       el mismo nombre que "estado.nombre" de su pedido
//                "plaza": "Plaza de prueba",                   el mismo nombre que "plaza" de su pedido
//                "tipo": "recoleccion",                        "recoleccion" (R en el mapa) o "entrega" (E)
//                "etiqueta": "Recolección",                    "Recolección", "Entrega" o "Recolección y entrega"
//                "latitud": 10.5,                              el punto que se guardó con la dirección, en grados
//                "longitud": -119.5
//              }
//            ]
//          }
//
//          "pedidos": cada uno con su folio, su estado operativo, su plaza y su recolección, y nada más (design.md,
//          F-D04): sin la zona horaria de la plaza, porque la recolección ya llega en su hora. De la recolección más
//          próxima a la más lejana, sin paginación. Sin pedidos activos, "pedidos" y "marcadores" llegan vacías.
//          "marcadores": solo de los pedidos de "pedidos" y en su orden, uno en la recolección de cada pedido y,
//          después, otro en la entrega cuando es en otra dirección; si se entrega en la misma, un solo marcador, el de la
//          recolección, con la etiqueta "Recolección y entrega". Ningún marcador es de un repartidor.
//
//     401  sin sesión, o con el token vencido o revocado (administracion-app-E05): lo atiende el cliente de la
//          administración, que borra la sesión y abre el inicio de sesión.
//     403  la cuenta no es de administración, o su token no lleva la habilidad (administracion-app-E06 y E07).
//
// Va sobre el cliente de la administración (src/administracion/api.ts). Como las demás consultas, nunca lanza: devuelve
// un Resultado. Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
import { esObjeto, numero } from '../../configuracion/lectura';
import type { PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';

/** La ruta del monitoreo, bajo /api/administracion. */
export const RUTA_DEL_MONITOREO = 'monitoreo';

/** Los dos tipos de marcador: en la recolección (R) y en la entrega en otra dirección (E). */
export const TIPOS_DE_MARCADOR = ['recoleccion', 'entrega'] as const;
export type TipoDeMarcador = (typeof TIPOS_DE_MARCADOR)[number];

/** La recolección del pedido, en la hora de su plaza. */
export interface VentanaDelMonitoreo {
  inicio: string;
  fin: string;
  /** AAAA-MM-DD, el día de la recolección en la hora de la plaza. */
  fecha: string;
  /** HH:MM, en la hora de la plaza. */
  hora_inicio: string;
  hora_fin: string;
}

/** Un pedido activo, como sale en "pedidos". */
export interface PedidoDelMonitoreo {
  id_publico: string;
  folio: string;
  estado: { codigo: string; nombre: string };
  plaza: string;
  ventana: VentanaDelMonitoreo;
}

/** Un marcador del mapa, como sale en "marcadores". */
export interface MarcadorDelMonitoreo {
  /** El id_publico de su pedido. */
  pedido: string;
  folio: string;
  /** El nombre operativo del estado, el mismo de su pedido en la lista. */
  estado: string;
  plaza: string;
  tipo: TipoDeMarcador;
  /** "Recolección", "Entrega" o "Recolección y entrega", como lo dice el servidor. */
  etiqueta: string;
  latitud: number;
  longitud: number;
}

export interface Monitoreo {
  pedidos: PedidoDelMonitoreo[];
  marcadores: MarcadorDelMonitoreo[];
}

const FUERA_DEL_CONTRATO = { ok: false, motivo: 'servidor' } as const;

/** GET monitoreo: los pedidos activos de todas las plazas y sus marcadores (administracion-app-E28). */
export async function consultarElMonitoreo(
  pedir: PedirALaAdministracion,
): Promise<ResultadoDeLaAdministracion<Monitoreo>> {
  const resultado = await pedir<unknown>(RUTA_DEL_MONITOREO);
  if (!resultado.ok) {
    return resultado;
  }
  const monitoreo = leerElMonitoreo(resultado.datos);
  return monitoreo ? { ok: true, datos: monitoreo } : FUERA_DEL_CONTRATO;
}

/** La respuesta leída campo por campo; null si algo no es del contrato. */
function leerElMonitoreo(datos: unknown): Monitoreo | null {
  if (!esObjeto(datos) || !Array.isArray(datos.pedidos) || !Array.isArray(datos.marcadores)) {
    return null;
  }
  const pedidos = datos.pedidos.map(leerPedido);
  const marcadores = datos.marcadores.map(leerMarcador);
  if (!pedidos.every((pedido) => pedido !== null) || !marcadores.every((marcador) => marcador !== null)) {
    return null;
  }
  return { pedidos, marcadores };
}

function esTexto(valor: unknown): valor is string {
  return typeof valor === 'string';
}

function leerPedido(valor: unknown): PedidoDelMonitoreo | null {
  if (!esObjeto(valor) || !esObjeto(valor.estado) || !esObjeto(valor.ventana)) {
    return null;
  }
  const { id_publico: idPublico, folio, estado, plaza, ventana } = valor;
  const textos = [idPublico, folio, estado.codigo, estado.nombre, plaza];
  const horas = [ventana.inicio, ventana.fin, ventana.fecha, ventana.hora_inicio, ventana.hora_fin];
  if (![...textos, ...horas].every(esTexto)) {
    return null;
  }
  return {
    id_publico: idPublico as string,
    folio: folio as string,
    estado: { codigo: estado.codigo as string, nombre: estado.nombre as string },
    plaza: plaza as string,
    ventana: {
      inicio: ventana.inicio as string,
      fin: ventana.fin as string,
      fecha: ventana.fecha as string,
      hora_inicio: ventana.hora_inicio as string,
      hora_fin: ventana.hora_fin as string,
    },
  };
}

function esTipoDeMarcador(valor: unknown): valor is TipoDeMarcador {
  return TIPOS_DE_MARCADOR.includes(valor as TipoDeMarcador);
}

function leerMarcador(valor: unknown): MarcadorDelMonitoreo | null {
  if (!esObjeto(valor)) {
    return null;
  }
  const { pedido, folio, estado, plaza, tipo, etiqueta } = valor;
  // Las coordenadas, aunque lleguen como texto: PostgreSQL entrega los decimales así si el servidor no los convierte.
  const latitud = numero(valor.latitud);
  const longitud = numero(valor.longitud);
  if (![pedido, folio, estado, plaza, etiqueta].every(esTexto) || !esTipoDeMarcador(tipo)) {
    return null;
  }
  if (latitud === null || longitud === null) {
    return null;
  }
  return {
    pedido: pedido as string,
    folio: folio as string,
    estado: estado as string,
    plaza: plaza as string,
    tipo,
    etiqueta: etiqueta as string,
    latitud,
    longitud,
  };
}
