// La consulta de las ventanas de una lavandería para un día (openspec/changes/agenda-de-recoleccion, agenda-E04 y
// E08): por su id_publico y una fecha de la plaza, con el token de la sesión. Las ventanas las calcula el servidor
// (design.md, decisión 1); la app las pinta como llegan. Como las demás consultas, nunca lanza: devuelve un Resultado.
import { pedir, type Resultado } from '../configuracion/api';
import { esObjeto } from '../configuracion/lectura';

/**
 * El contrato de la consulta con el servidor (tareas 1.1 y 1.2 de agenda-de-recoleccion), en un solo lugar para
 * ajustarlo: GET <ruta>/<id_publico>/<tramo>?<fecha>=AAAA-MM-DD, con la sesión, que contesta un DiaDeAgenda
 * (servidor/app/Http/Controllers/Api/VentanasDeLavanderiaController.php).
 */
export const CONTRATO_DE_LA_AGENDA = {
  ruta: 'lavanderias',
  tramo: 'ventanas',
  fecha: 'fecha',
} as const;

/**
 * Una ventana de una hora, con los nombres de campo del servidor. inicio y fin son instantes ISO 8601 con el desfase de
 * la plaza: el pedido los manda de vuelta tal como llegaron, porque el servidor los valida igual (agenda-E09).
 * hora_inicio y hora_fin ya son la hora de la plaza, y se muestran tal cual, sin pasarlas a la hora del teléfono.
 */
export interface Ventana {
  inicio: string;
  fin: string;
  /** AAAA-MM-DD, en la hora de la plaza. */
  fecha: string;
  /** "09:00", en la hora de la plaza. */
  hora_inicio: string;
  hora_fin: string;
}

/** Un día de la agenda de una lavandería, como lo devuelve el servidor. */
export interface DiaDeAgenda {
  /** El día pedido, AAAA-MM-DD en la hora de la plaza. */
  fecha: string;
  zona_horaria: string;
  /** Hoy en la plaza: la app no deja elegir antes. */
  primer_dia: string;
  /** Hoy más los días hacia adelante de la plaza: la app no deja elegir después. */
  ultimo_dia: string;
  /** Ese día de la semana la lavandería no tiene horario (agenda-E04). */
  dia_sin_servicio: boolean;
  /** De la más temprana a la más tarde. Vacía con dia_sin_servicio en false: al día ya no le quedan ventanas. */
  ventanas: Ventana[];
}

const FECHA = /^\d{4}-\d{2}-\d{2}$/;
const HORA = /^\d{2}:\d{2}$/;

/** GET de las ventanas de una lavandería para una fecha de la plaza, con la sesión (agenda-E04 y E08). */
export async function consultarVentanas(
  token: string,
  idPublico: string,
  fecha: string,
): Promise<Resultado<DiaDeAgenda>> {
  const contrato = CONTRATO_DE_LA_AGENDA;
  const consulta = `${contrato.fecha}=${encodeURIComponent(fecha)}`;
  const ruta = `${contrato.ruta}/${encodeURIComponent(idPublico)}/${contrato.tramo}?${consulta}`;
  const resultado = await pedir<unknown>(ruta, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const dia = leerDia(resultado.datos);
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return dia ? { ok: true, datos: dia } : { ok: false, motivo: 'servidor' };
}

function leerDia(datos: unknown): DiaDeAgenda | null {
  if (
    !esObjeto(datos) ||
    !esFecha(datos.fecha) ||
    typeof datos.zona_horaria !== 'string' ||
    !esFecha(datos.primer_dia) ||
    !esFecha(datos.ultimo_dia) ||
    datos.primer_dia > datos.ultimo_dia ||
    typeof datos.dia_sin_servicio !== 'boolean' ||
    !Array.isArray(datos.ventanas)
  ) {
    return null;
  }
  const ventanas = datos.ventanas.map(leerVentana);
  if (!ventanas.every((ventana) => ventana !== null)) {
    return null;
  }
  return {
    fecha: datos.fecha,
    zona_horaria: datos.zona_horaria,
    primer_dia: datos.primer_dia,
    ultimo_dia: datos.ultimo_dia,
    dia_sin_servicio: datos.dia_sin_servicio,
    ventanas: ventanas as Ventana[],
  };
}

/** Una ventana leída campo por campo, como la da la agenda; null si no es del contrato. También la usa el pedido. */
export function leerVentana(fila: unknown): Ventana | null {
  if (
    !esObjeto(fila) ||
    !esInstante(fila.inicio) ||
    !esInstante(fila.fin) ||
    !esFecha(fila.fecha) ||
    typeof fila.hora_inicio !== 'string' ||
    !HORA.test(fila.hora_inicio) ||
    typeof fila.hora_fin !== 'string' ||
    !HORA.test(fila.hora_fin)
  ) {
    return null;
  }
  const { inicio, fin, fecha, hora_inicio, hora_fin } = fila;
  return { inicio, fin, fecha, hora_inicio, hora_fin };
}

function esFecha(valor: unknown): valor is string {
  return typeof valor === 'string' && FECHA.test(valor);
}

function esInstante(valor: unknown): valor is string {
  return typeof valor === 'string' && !Number.isNaN(Date.parse(valor));
}
