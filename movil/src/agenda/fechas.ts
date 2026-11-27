// Las fechas de la agenda (openspec/changes/agenda-de-recoleccion): días del calendario de la plaza, como texto
// AAAA-MM-DD, igual que en GET /api/lavanderias/{id_publico}/ventanas. Un día de la agenda no tiene hora ni zona: para
// contar días y saber qué día de la semana es, se usa el calendario en UTC, que no depende de la zona del teléfono.

const UN_DIA_EN_MILISEGUNDOS = 24 * 60 * 60 * 1000;

function aTexto(anio: number, mes: number, dia: number): string {
  return `${String(anio).padStart(4, '0')}-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
}

// El día a la medianoche en UTC.
function enUtc(fecha: string): Date {
  const [anio, mes, dia] = fecha.split('-').map(Number);
  return new Date(Date.UTC(anio, mes - 1, dia));
}

/** Hoy en el teléfono, AAAA-MM-DD. Con ella se pide el primer día; el servidor responde cuál es hoy en la plaza. */
export function fechaDelTelefono(ahora: Date = new Date()): string {
  return aTexto(ahora.getFullYear(), ahora.getMonth() + 1, ahora.getDate());
}

/** El día siguiente, AAAA-MM-DD. */
export function diaSiguiente(fecha: string): string {
  const siguiente = new Date(enUtc(fecha).getTime() + UN_DIA_EN_MILISEGUNDOS);
  return aTexto(siguiente.getUTCFullYear(), siguiente.getUTCMonth() + 1, siguiente.getUTCDate());
}

/** Los días de primero a último, los dos incluidos. Vacía si el último es anterior al primero. */
export function diasEntre(primero: string, ultimo: string): string[] {
  const dias: string[] = [];
  for (let dia = primero; dia <= ultimo; dia = diaSiguiente(dia)) {
    dias.push(dia);
  }
  return dias;
}

/** El día de la semana, de 0 (domingo) a 6 (sábado). */
export function diaDeLaSemana(fecha: string): number {
  return enUtc(fecha).getUTCDay();
}

/** El día del mes, de 1 a 31. */
export function diaDelMes(fecha: string): number {
  return enUtc(fecha).getUTCDate();
}

/** El mes, de 0 (enero) a 11 (diciembre). */
export function mesDeLaFecha(fecha: string): number {
  return enUtc(fecha).getUTCMonth();
}
