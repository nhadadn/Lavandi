// Los cortes de los filtros de la búsqueda de lavanderías (openspec/changes/lavanderias-cercanas, cercanas-E21): viven
// en la configuración de la app, no en la pantalla (design.md, decisión 5). Si DD-083 los hace por plaza, pasan a la
// respuesta del servidor.
//
// PROVISIONALES. Los valores los fija DD-083 (pregunta 3), que sigue abierta: estos son los de ejemplo del mock A04
// (mocks/usuario/a04-busqueda.html, "Los cortes de 1, 3 y 5 km y de 4 y 4.5 estrellas son de ejemplo"). Cambiarlos no
// toca la pantalla.
export const FILTROS = {
  /** Las distancias máximas que se ofrecen, en metros, en línea recta (cercanas-E16). */
  distanciasMaximasMetros: [1000, 3000, 5000],
  /** Las calificaciones promedio mínimas que se ofrecen, de 1 a 5 (cercanas-E17). */
  calificacionesMinimas: [4.5, 4],
} as const satisfies { distanciasMaximasMetros: readonly number[]; calificacionesMinimas: readonly number[] };
