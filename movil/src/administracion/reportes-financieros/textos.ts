// Los textos de los reportes financieros de la administración (F-D06; administracion-app-E37 y E38), juntos para
// revisarlos en un solo lugar. Son los de las imágenes 31 a 35 de la Propuesta 06 de los mocks; los de las cifras, de la
// tabla, de la confirmación y de la pasarela que no responde, los del panel de respaldo, para que la app y el panel digan
// lo mismo (servidor/app/Filament/Widgets/Finanzas/ y servidor/app/Filament/Pages/Conciliacion.php). Los textos de cada
// monto, de cada periodo y de cada resultado, y los mensajes de cada error 422, los da el servidor; el de una fecha
// mal escrita es el de "Mis pedidos" (src/historial/textos.ts). Ninguno trae el nombre del producto ni el dominio
// (CLAUDE.md §5; administracion-app-E03).
import { mensajeGeneral } from '../../acceso/mensajes';
import { pedidoConFolio } from '../../estados/textos';
import { TEXTOS_DEL_HISTORIAL } from '../../historial/textos';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';
import type { FilaDeLaConciliacion } from './api';

export const TEXTOS_DE_LOS_INGRESOS = {
  // La pantalla (imagen 31).
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.ingresos,
  cargando: 'Cargando los ingresos…',
  intentarDeNuevo: 'Intentar de nuevo',
  // Las cifras del periodo, como el panel (IngresosDelPeriodo e IngresosPorPeriodoTabla).
  ingresosDelPeriodo: 'Ingresos del periodo',
  pagosConfirmados: 'Pagos confirmados',
  // Lo que espera una DD: su lugar, sin cifra (administracion-app-E39 y E40).
  reembolsosYAjustes: 'Reembolsos y ajustes',
  comisionesDelPeriodo: 'Comisiones del periodo',
  // La tabla de cada periodo.
  periodo: 'Periodo',
  ingresos: 'Ingresos',
} as const;

export const TEXTOS_DE_LA_CONCILIACION = {
  // La pantalla (imágenes 32 y 35), con el texto del panel antes de conciliar.
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.conciliacion,
  instrucciones: 'Elige un periodo y concilia para leer los pagos de la pasarela.',
  desde: 'Desde',
  hasta: 'Hasta',
  // Junto al campo vacío, a medias o con una fecha que no existe, el de "Mis pedidos": no se concilia.
  fechaInvalida: TEXTOS_DEL_HISTORIAL.fechaInvalida,
  conciliarUnPeriodo: 'Conciliar un periodo',
  // La confirmación, con el diálogo del sistema y los textos del panel.
  confirmarTitulo: 'Conciliar con la pasarela',
  confirmarTexto:
    'Se leen de la pasarela los pagos que acreditó en el periodo, con sus dos fechas, y se comparan con los que registró la plataforma.',
  confirmarAccion: 'Conciliar',
  // La pasarela que no responde (imagen 35), con el aviso del panel: no se guarda nada.
  sinPasarela: 'No se pudo leer la pasarela',
  pasarelaNoDisponible: 'La pasarela no está disponible por ahora. Intenta conciliar otra vez más tarde.',
  // El reporte (imágenes 33 y 34).
  comisionDeLaPasarela: 'Comisión de la pasarela',
  otroPeriodo: 'Otro periodo',
  diferencias: 'Diferencias',
  conciliados: 'Conciliados',
  sinPagos: 'No hay pagos ni movimientos de la pasarela en el periodo.',
  // Cada fila, con las columnas del panel.
  sinPedido: 'Sin pedido',
  enLaPlataforma: 'En la plataforma',
  enLaPasarela: 'En la pasarela',
  // El lado que no tiene monto, como el panel.
  sinMonto: '—',
} as const;

/** Las DD que esperan los lugares reservados de los reportes financieros. */
export const DD_DE_LOS_REPORTES = {
  reembolsosYAjustes: ['DD-012', 'DD-016'],
  comisionesDelPeriodo: ['DD-020'],
  comisionDeLaPasarela: ['DD-013'],
} as const;

/** "Pedido F2G7H4JS · Plaza de prueba Durango", o "Sin pedido" en un movimiento sin pedido, como el panel. */
export function pedidoDeLaFila({ folio, plaza }: Pick<FilaDeLaConciliacion, 'folio' | 'plaza'>): string {
  if (folio === null) {
    return TEXTOS_DE_LA_CONCILIACION.sinPedido;
  }
  return plaza ? `${pedidoConFolio(folio)} · ${plaza}` : pedidoConFolio(folio);
}

/** "Orden en la pasarela: ord_2x9… · Pago del 05/03/2027 10:00", con la fecha del servidor tal cual. */
export function ordenYFecha({ orden, fecha }: Pick<FilaDeLaConciliacion, 'orden' | 'fecha'>): string {
  return `Orden en la pasarela: ${orden} · Pago del ${fecha}`;
}

/**
 * El mensaje de una consulta o una acción que no salió, sin los errores que van junto a su campo: con un 422, el de los
 * campos que la pantalla no muestra; sin conexión o con otro error, los mensajes generales de la app. Con el 401 no hay
 * nada que decir: la app ya volvió al inicio de sesión.
 */
export function mensajeDeLosReportes(
  falla: FallaDeLaAdministracion,
  camposVisibles: readonly string[] = [],
): string | undefined {
  switch (falla.motivo) {
    case 'sin-sesion':
      return undefined;
    case 'conflicto':
    case 'no-existe':
      return falla.mensaje || mensajeGeneral({ ok: false, motivo: 'servidor' });
    default:
      return mensajeGeneral(falla, camposVisibles);
  }
}
