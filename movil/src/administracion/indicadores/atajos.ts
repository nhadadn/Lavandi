// Los atajos del rango de los indicadores, "Este mes" y "Mes anterior" (administracion-app-E45; design.md, sección 4;
// imagen 42 de la Propuesta 06): llenan las dos fechas de la hoja del rango con los días de ese mes, DD/MM/AAAA, como si
// se escribieran; "Aplicar" los manda. "Este mes" va del día 1 a hoy, como el rango sin filtros del servidor
// (Rango::deLosFiltros()); "Mes anterior", del día 1 al último día del mes de antes.
//
// Hoy es el día del calendario del teléfono, en su hora. El servidor no ajusta los días que recibe más que a periodos
// completos; si el teléfono estuviera en otro día que la zona horaria de referencia (indicadores-E24), el rango que
// manda es el que la persona ve en los campos, y la tarjeta dice el que usó el servidor.
import { diaALaVista } from '../rango/rango';

/** Los dos días de un atajo, como se ven en los campos: DD/MM/AAAA. */
export interface DiasDelAtajo {
  desde: string;
  hasta: string;
}

/** Un día del calendario, sin hora, como AAAA-MM-DD; el mes cuenta desde 0, como en Date. */
function dia(anio: number, mes: number, diaDelMes: number): string {
  return [String(anio).padStart(4, '0'), String(mes + 1).padStart(2, '0'), String(diaDelMes).padStart(2, '0')].join('-');
}

/** Del día 1 del mes de hoy a hoy. */
export function diasDeEsteMes(hoy: Date): DiasDelAtajo {
  const anio = hoy.getFullYear();
  const mes = hoy.getMonth();
  return { desde: diaALaVista(dia(anio, mes, 1)), hasta: diaALaVista(dia(anio, mes, hoy.getDate())) };
}

/** Del día 1 al último día del mes anterior al de hoy; en enero, el diciembre del año anterior. */
export function diasDelMesAnterior(hoy: Date): DiasDelAtajo {
  // El día 0 del mes de hoy es el último del mes anterior, también al cambiar de año.
  const ultimo = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
  const anio = ultimo.getFullYear();
  const mes = ultimo.getMonth();
  return { desde: diaALaVista(dia(anio, mes, 1)), hasta: diaALaVista(dia(anio, mes, ultimo.getDate())) };
}
