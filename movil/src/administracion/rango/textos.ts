// Los textos del rango y la granularidad de los reportes de la administración (design.md, sección 4, F-D06 e
// "Indicadores del A.5"): la tarjeta con "Cambiar" y la hoja "Rango y granularidad", de las imágenes 31 y 42 de la
// Propuesta 06 de los mocks. Los nombres de cada granularidad son los del panel (App\Indicadores\Granularidad::nombre()),
// y cómo se ajusta el rango, el de App\Indicadores\Rango: por semana, de lunes a domingo; por mes, del día 1 al último
// día. Los días del rango ajustado los da el servidor: la app no los calcula. Ninguno trae el nombre del producto ni el
// dominio (CLAUDE.md §5; administracion-app-E03).
import { TEXTOS_DEL_HISTORIAL } from '../../historial/textos';
import type { Granularidad } from './rango';

export const TEXTOS_DEL_RANGO = {
  cambiar: 'Cambiar',
  // La hoja (imagen 42).
  titulo: 'Rango y granularidad',
  cerrar: 'Cerrar',
  desde: 'Desde',
  hasta: 'Hasta',
  verPor: 'Ver por',
  aplicar: 'Aplicar',
  // Junto al campo con una fecha a medias o que no existe, el texto del filtro de "Mis pedidos".
  fechaInvalida: TEXTOS_DEL_HISTORIAL.fechaInvalida,
  granularidades: {
    dia: 'Día',
    semana: 'Semana',
    mes: 'Mes',
  } satisfies Record<Granularidad, string>,
} as const;

// Cómo ajusta el servidor el rango a periodos completos (Rango::deLosFiltros()). Por día no hay nada que ajustar.
const COMO_SE_AJUSTA: Record<Granularidad, string | null> = {
  dia: null,
  semana: 'Por semana, el rango se ajusta a semanas completas, de lunes a domingo',
  mes: 'Por mes, el rango se ajusta a meses completos, del día 1 al último día',
};

/**
 * Cómo se ajusta el rango con esa granularidad, como la imagen 42: "Por semana, el rango se ajusta a semanas completas,
 * de lunes a domingo: del 26/10/2026 al 29/11/2026.". Los días van solo si el servidor ya ajustó ese rango; si no, la
 * frase termina en punto. Por día, nada.
 */
export function comoSeAjusta(
  granularidad: Granularidad,
  ajustado: { desde: string; hasta: string } | null,
): string | null {
  const frase = COMO_SE_AJUSTA[granularidad];
  if (frase === null) {
    return null;
  }
  return ajustado ? `${frase}: del ${ajustado.desde} al ${ajustado.hasta}.` : `${frase}.`;
}
