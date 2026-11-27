// El rango y la granularidad que elige la administración para sus reportes: los ingresos (administracion-app-E37) y,
// con la tarea 18.4, los indicadores (administracion-app-E45), con la misma tarjeta y la misma hoja (design.md, sección
// 4). El rango se escribe en dos campos de texto, DD/MM/AAAA, con el teclado de números y las diagonales que pone la
// app, como el filtro de "Mis pedidos" (src/historial/fechas.ts), sin librería de calendario. Al servidor va como
// AAAA-MM-DD.
//
// La app no ajusta el rango: lo hace App\Indicadores\Rango en el servidor, que responde el rango que usó, ya ajustado a
// periodos completos, y su descripción ("Del 26/10/2026 al 29/11/2026, por semana") (ADR-0022, decisión 9). Lo que sí
// revisa la app es lo que se escribió, como el filtro de "Mis pedidos" (src/historial/FiltroPorFecha.tsx): una fecha a
// medias o que no existe, como el 31/02, no se manda, y lo dice junto a su campo. El servidor no impone el formato: ese
// día lo tomaría como no dado y respondería otro rango sin decirlo.
import { conDiagonales, fechaDeTexto } from '../../historial/fechas';

export { conDiagonales, fechaDeTexto };

/** Cómo se agrupan las cifras en el tiempo, con el valor de App\Indicadores\Granularidad. */
export const GRANULARIDADES = ['dia', 'semana', 'mes'] as const;
export type Granularidad = (typeof GRANULARIDADES)[number];

/** Lo que eligió la administración en la hoja: los dos días como se escribieron, DD/MM/AAAA, y la granularidad. */
export interface RangoElegido {
  desde: string;
  hasta: string;
  granularidad: Granularidad;
}

/** El rango que usó el servidor, ya ajustado: los dos días AAAA-MM-DD, su granularidad y cómo se lee. */
export interface RangoAjustado {
  desde: string;
  hasta: string;
  granularidad: Granularidad;
  descripcion: string;
}

const DIA_DEL_SERVIDOR = /^(\d{4})-(\d{2})-(\d{2})$/;

/** Un día del servidor, AAAA-MM-DD, como se escribe en la app: DD/MM/AAAA. Otra cosa queda como llegó. */
export function diaALaVista(dia: string): string {
  const partes = DIA_DEL_SERVIDOR.exec(dia);
  return partes ? `${partes[3]}/${partes[2]}/${partes[1]}` : dia;
}

/**
 * Si un día escrito en la hoja del rango se puede aplicar: vacío, para que el servidor ponga el suyo, o una fecha
 * DD/MM/AAAA que existe.
 */
export function sePuedeAplicar(escrito: string): boolean {
  const limpio = escrito.trim();
  return limpio === '' || fechaDeTexto(limpio) !== null;
}

/**
 * Un día como lo escribió la administración, como va al servidor: AAAA-MM-DD, o vacío si no se escribió, y entonces no
 * va. La hoja del rango solo aplica días que se pueden aplicar (sePuedeAplicar): nunca manda uno a medias.
 */
export function diaParaElServidor(escrito: string): string {
  return fechaDeTexto(escrito.trim()) ?? '';
}

/** Lo que muestra la hoja con el rango que usó el servidor, cuando la administración todavía no eligió otro. */
export function elegidoDelServidor(ajustado: RangoAjustado): RangoElegido {
  return {
    desde: diaALaVista(ajustado.desde),
    hasta: diaALaVista(ajustado.hasta),
    granularidad: ajustado.granularidad,
  };
}

/** Si dos rangos elegidos dicen lo mismo. */
export function mismoRango(uno: RangoElegido, otro: RangoElegido): boolean {
  return uno.desde === otro.desde && uno.hasta === otro.hasta && uno.granularidad === otro.granularidad;
}
