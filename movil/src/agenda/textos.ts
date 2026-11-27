// Los textos del selector de fecha y hora (agenda-E04 y E08), juntos para revisarlos en un solo lugar. El título y el
// subtítulo son los del mock A07 y del tablero "Fecha y hora (2 de 4)" de la Propuesta 5 corregida (C-233).
import { MESES } from '../perfil/textos';
import type { Ventana } from './api';
import { diaDeLaSemana, diaDelMes, diaSiguiente, mesDeLaFecha } from './fechas';

export const TEXTOS_DE_LA_AGENDA = {
  titulo: '¿Cuándo pasamos por tu ropa?',
  subtitulo: 'Elige una ventana de una hora para la recolección.',
  cargando: 'Cargando las ventanas…',
  // El día sin servicio (agenda-E04), con el texto del mock A07: un mensaje en lugar de la rejilla.
  diaSinServicio: 'Este día la lavandería no da servicio.',
  // Un día con horario al que ya no le quedan ventanas que ofrecer: ya pasaron o no caben en la anticipación.
  sinVentanas: 'Este día ya no quedan ventanas.',
  verElSiguienteDia: 'Ver el siguiente día',
  // "Continuar" sin ventana elegida (agenda-E08), como en el mock A07.
  eligeUnaVentana: 'Elige una ventana',
  // Para el lector de pantalla.
  dias: 'Días',
  ventanas: 'Ventanas de una hora',
} as const;

const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'] as const;
const DIAS_CORTOS = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'] as const;

function conMayuscula(texto: string): string {
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** "jueves 3 de diciembre": el nombre de cada día del selector para el lector de pantalla. */
export function fechaEnTexto(fecha: string): string {
  return `${DIAS[diaDeLaSemana(fecha)]} ${diaDelMes(fecha)} de ${MESES[mesDeLaFecha(fecha)]}`;
}

/** "jue": el día de la semana en el botón de cada día. */
export function diaCorto(fecha: string): string {
  return DIAS_CORTOS[diaDeLaSemana(fecha)];
}

/**
 * El día elegido, como en el mock A07: "Hoy, jueves 3 de diciembre", "Mañana, viernes 4 de diciembre" o "Sábado 5 de
 * diciembre". Hoy es el primer día de la agenda, que el servidor da como hoy en la plaza; sin él, no hay "Hoy" ni
 * "Mañana".
 */
export function fechaDelSelector(fecha: string, primerDia: string | null): string {
  if (fecha === primerDia) {
    return `Hoy, ${fechaEnTexto(fecha)}`;
  }
  if (primerDia !== null && fecha === diaSiguiente(primerDia)) {
    return `Mañana, ${fechaEnTexto(fecha)}`;
  }
  return conMayuscula(fechaEnTexto(fecha));
}

/** "10:00 – 11:00": la hora de la plaza, tal como la da el servidor. */
export function ventanaEnTexto({ hora_inicio, hora_fin }: Pick<Ventana, 'hora_inicio' | 'hora_fin'>): string {
  return `${hora_inicio} – ${hora_fin}`;
}

/** La elección sobre "Continuar", con la fecha de la propia ventana: "Hoy, jueves 3 de diciembre · 10:00 – 11:00". */
export function eleccionEnTexto(ventana: Ventana, primerDia: string | null): string {
  return `${fechaDelSelector(ventana.fecha, primerDia)} · ${ventanaEnTexto(ventana)}`;
}
