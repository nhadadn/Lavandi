// Apoyo de las pruebas del monitoreo de pedidos en la app (F-D04; administracion-app-E28 a E30). No es una prueba: Jest
// no lo corre como tal (testPathIgnorePatterns en package.json). Es el manejador que la prueba registra en el servidor
// simulado de la administración (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/MonitoreoController.php:
//
// - GET /administracion/monitoreo: los pedidos activos y sus marcadores, en una sola respuesta. Activos, con la regla de
//   administracion-E06 (Pedido::activosDelMonitoreo()): los de "confirmado" a "en_entrega", nunca uno entregado. Los
//   marcadores, con la de administracion-E09 y E10 (App\Pedidos\MarcadoresDelMonitoreo): uno en la recolección de cada
//   pedido y, después, otro en la entrega cuando es en otra dirección; si se entrega en la misma, uno solo, "Recolección
//   y entrega". Ninguno de un repartidor. El estado se nombra como App\Pedidos\NombresDelEstado.
//
// El servidor simulado no ordena: devuelve los pedidos en el orden en que la prueba los guardó, como si fuera el de la
// recolección más próxima. Los datos son de prueba, no del negocio: las plazas y los folios de la imagen 25 de la
// Propuesta 06 (la Plaza de prueba Durango es la de servidor/database/seeders/PlazasDePruebaSeeder.php; la de Torreón
// solo existe en la maqueta), y los puntos, cerca del centro de cada ciudad.
import { copia, respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';

export const RUTA_DEL_MONITOREO = '/administracion/monitoreo';

export type EstadoOperativo =
  | 'confirmado'
  | 'aceptado'
  | 'rechazado'
  | 'recolectado'
  | 'en_proceso'
  | 'listo'
  | 'en_entrega'
  | 'entregado';

/** Cómo nombra la administración cada estado operativo (servidor/app/Pedidos/NombresDelEstado.php). */
export const NOMBRES_DEL_ESTADO: Record<EstadoOperativo, string> = {
  confirmado: 'Confirmado',
  aceptado: 'Aceptado',
  rechazado: 'Rechazado',
  recolectado: 'Recolectado',
  en_proceso: 'En proceso',
  listo: 'Listo',
  en_entrega: 'En proceso de entrega',
  entregado: 'Entregado',
};

/** Los que el monitoreo trae (administracion-E06): todos menos el entregado. */
const ACTIVOS: readonly EstadoOperativo[] = [
  'confirmado',
  'aceptado',
  'rechazado',
  'recolectado',
  'en_proceso',
  'listo',
  'en_entrega',
];

export interface Punto {
  latitud: number;
  longitud: number;
}

/** El pedido como lo guarda el servidor simulado. */
export interface PedidoGuardado {
  id_publico: string;
  folio: string;
  estado: EstadoOperativo;
  plaza: string;
  /** AAAA-MM-DD, el día de la recolección en la hora de la plaza. */
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  recoleccion: Punto;
  /** La entrega en otra dirección; null si se entrega en la misma de la recolección. */
  entrega: Punto | null;
}

/** En proceso en Durango, con la entrega en otra dirección: dos marcadores, R y E. */
export const EN_PROCESO: PedidoGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0mo1',
  folio: '7K2M9QXA',
  estado: 'en_proceso',
  plaza: 'Plaza de prueba Durango',
  fecha: '2026-11-24',
  hora_inicio: '09:00',
  hora_fin: '10:00',
  recoleccion: { latitud: 24.03, longitud: -104.66 },
  entrega: { latitud: 24.01, longitud: -104.64 },
};

/** Confirmado en otra plaza, con la entrega en la misma dirección: un solo marcador, "Recolección y entrega". */
export const CONFIRMADO: PedidoGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0mo2',
  folio: 'R6T3N8VB',
  estado: 'confirmado',
  plaza: 'Plaza de prueba Torreón',
  fecha: '2026-11-24',
  hora_inicio: '11:00',
  hora_fin: '12:00',
  recoleccion: { latitud: 25.55, longitud: -103.42 },
  entrega: null,
};

/** Recolectado en Torreón, con la entrega en la misma dirección (administracion-app-E29). */
export const RECOLECTADO: PedidoGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0mo3',
  folio: 'W9C4D7PL',
  estado: 'recolectado',
  plaza: 'Plaza de prueba Torreón',
  fecha: '2026-11-24',
  hora_inicio: '12:00',
  hora_fin: '13:00',
  recoleccion: { latitud: 25.54, longitud: -103.41 },
  entrega: null,
};

/** Entregado: el monitoreo no lo trae (administracion-E06). */
export const ENTREGADO: PedidoGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0mo4',
  folio: 'M4P7R2KD',
  estado: 'entregado',
  plaza: 'Plaza de prueba Durango',
  fecha: '2026-11-23',
  hora_inicio: '10:00',
  hora_fin: '11:00',
  recoleccion: { latitud: 24.04, longitud: -104.65 },
  entrega: null,
};

/** El pedido activo como sale en "pedidos": su folio, su estado, su plaza y su recolección, sin la zona horaria. */
export function pedidoComoSale(pedido: PedidoGuardado) {
  return {
    id_publico: pedido.id_publico,
    folio: pedido.folio,
    estado: { codigo: pedido.estado, nombre: NOMBRES_DEL_ESTADO[pedido.estado] },
    plaza: pedido.plaza,
    ventana: {
      inicio: `${pedido.fecha}T${pedido.hora_inicio}:00-06:00`,
      fin: `${pedido.fecha}T${pedido.hora_fin}:00-06:00`,
      fecha: pedido.fecha,
      hora_inicio: pedido.hora_inicio,
      hora_fin: pedido.hora_fin,
    },
  };
}

/** Los marcadores del pedido, como salen en "marcadores": la recolección y, si es en otra dirección, la entrega. */
export function marcadoresComoSalen(pedido: PedidoGuardado) {
  const delPedido = {
    pedido: pedido.id_publico,
    folio: pedido.folio,
    estado: NOMBRES_DEL_ESTADO[pedido.estado],
    plaza: pedido.plaza,
  };
  const recoleccion = {
    ...delPedido,
    tipo: 'recoleccion',
    etiqueta: pedido.entrega ? 'Recolección' : 'Recolección y entrega',
    ...pedido.recoleccion,
  };
  return pedido.entrega
    ? [recoleccion, { ...delPedido, tipo: 'entrega', etiqueta: 'Entrega', ...pedido.entrega }]
    : [recoleccion];
}

/** Los pedidos del servidor simulado, con lo que la app le pidió al monitoreo. */
export class PedidosDelMonitoreo {
  pedidos: PedidoGuardado[];

  constructor(iniciales: PedidoGuardado[]) {
    this.pedidos = copia(iniciales);
  }

  /** Cambia el estado del pedido en el servidor, como si la lavandería lo hubiera movido. */
  cambiar(idPublico: string, estado: EstadoOperativo): void {
    const pedido = this.pedidos.find((guardado) => guardado.id_publico === idPublico);
    if (!pedido) {
      throw new Error(`El servidor simulado no tiene el pedido ${idPublico}`);
    }
    pedido.estado = estado;
  }

  /** Pone los pedidos del servidor, en su orden. */
  poner(pedidos: PedidoGuardado[]): void {
    this.pedidos = copia(pedidos);
  }

  /** Contesta GET /administracion/monitoreo; lo demás, undefined. */
  readonly manejador = ({ metodo, ruta }: Peticion): unknown => {
    if (metodo !== 'GET' || ruta !== RUTA_DEL_MONITOREO) {
      return undefined;
    }
    const activos = this.pedidos.filter(({ estado }) => ACTIVOS.includes(estado));
    return respuesta(200, {
      pedidos: activos.map(pedidoComoSale),
      marcadores: activos.flatMap(marcadoresComoSalen),
    });
  };
}

/**
 * Prepara cada prueba del archivo con esos pedidos en el servidor simulado de la administración, ya registrados en él.
 * Devuelve los de la prueba en curso. Va después de usarElServidorDeLaAdministracion().
 */
export function usarElMonitoreo(
  servidor: () => ServidorDeLaAdministracion,
  iniciales: PedidoGuardado[] = [EN_PROCESO, CONFIRMADO, ENTREGADO],
): () => PedidosDelMonitoreo {
  let pedidos: PedidosDelMonitoreo;
  beforeEach(() => {
    pedidos = new PedidosDelMonitoreo(iniciales);
    servidor().agregar(pedidos.manejador);
  });
  return () => pedidos;
}

/** Cuántas veces la app pidió el monitoreo. */
export function consultasDelMonitoreo(servidor: ServidorDeLaAdministracion): number {
  return servidor.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === RUTA_DEL_MONITOREO).length;
}
