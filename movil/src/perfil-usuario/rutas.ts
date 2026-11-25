// Las rutas del perfil del usuario y del soporte (ADR-0017): "Mi perfil" (perfil-usuario-E01, E02, E05 y E15) y
// "Ayuda" (perfil-usuario-E09 a E12).
import type { Href } from 'expo-router';

export const RUTA_DEL_PERFIL_DE_USUARIO: Href = '/perfil';
export const RUTA_DE_AYUDA: Href = '/ayuda';

/**
 * "Ayuda" con el reporte de un pedido de la cuenta (perfil-usuario-E11), para abrirla desde el seguimiento o el historial
 * de ese pedido: su id_publico y, si se tiene, su folio para mostrarlo.
 */
export function rutaDeAyuda(pedido?: { id_publico: string; folio?: string }): Href {
  if (!pedido) {
    return RUTA_DE_AYUDA;
  }
  const params: Record<string, string> = { pedido: pedido.id_publico };
  if (pedido.folio) {
    params.folio = pedido.folio;
  }
  return { pathname: '/ayuda', params };
}
