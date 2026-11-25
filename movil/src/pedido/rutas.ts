// Las rutas de los pasos del pedido nuevo (ADR-0017): la selección de servicio (perfil-lavanderia-E11), el paso de
// fecha y hora (agenda-E04 y E08), el de la dirección, con la dirección nueva (direcciones-E01 a E11), y el resumen
// (confirmacion-E01 y E07). Después, el pago del pedido creado (pago-E02), y la confirmación con su folio
// (confirmacion-E09), /pedido/confirmacion/[id], que abre la espera del cobro cuando el servidor lo da por pagado
// (pago-E08). La espera es de src/pago/rutas.ts.
import type { Href } from 'expo-router';

import type { ParaQueEsLaDireccion } from './pedidoEnCurso';

export const RUTA_DEL_SERVICIO: Href = '/pedido/servicio';
export const RUTA_DE_FECHA_Y_HORA: Href = '/pedido/fecha-y-hora';
export const RUTA_DE_LA_DIRECCION: Href = '/pedido/direccion';
export const RUTA_DEL_RESUMEN: Href = '/pedido/resumen';

/** La dirección nueva, para la recogida o para la entrega. */
export function rutaDeLaDireccionNueva(para: ParaQueEsLaDireccion): Href {
  return { pathname: '/pedido/direccion-nueva', params: { para } };
}

/** El pago del pedido que el servidor creó al aceptar el resumen. */
export function rutaDelPago(idPublico: string): Href {
  return { pathname: '/pedido/pago/[id]', params: { id: idPublico } };
}

/** La confirmación del pedido con su folio, por su id_publico (confirmacion-E09). */
export function rutaDeLaConfirmacion(idPublico: string): Href {
  return { pathname: '/pedido/confirmacion/[id]', params: { id: idPublico } };
}
