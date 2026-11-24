// Para leer las respuestas de la API sin confiar en su forma: cada cliente (cercanas, perfil) comprueba campo por campo
// lo que le llega, y una respuesta fuera del contrato no se pinta a medias.

/** Si el valor es un objeto con campos que se pueden leer. */
export function esObjeto(valor: unknown): valor is Record<string, unknown> {
  return typeof valor === 'object' && valor !== null;
}

/**
 * Un número, aunque llegue como texto: PostgreSQL entrega los decimales (el promedio, las coordenadas) como texto si el
 * servidor no los convierte. Lo que no es un número finito es null.
 */
export function numero(valor: unknown): number | null {
  const convertido = typeof valor === 'string' && valor.trim() !== '' ? Number(valor) : valor;
  return typeof convertido === 'number' && Number.isFinite(convertido) ? convertido : null;
}

/** Un número que puede faltar: null es null; cualquier otra cosa tiene que ser un número (undefined si no lo es). */
export function numeroOVacio(valor: unknown): number | null | undefined {
  if (valor === null) {
    return null;
  }
  return numero(valor) ?? undefined;
}

/**
 * Una foto del almacenamiento privado, como la entrega la API (ADR-0006): solo su URL firmada y el instante en que
 * vence, en UTC. Al volver a pedir lo que la trae llega otra URL. Es la misma forma en las fotos del perfil de la
 * lavandería y en la foto de perfil del usuario.
 */
export interface FotoFirmada {
  url: string;
  vence_en: string;
}

/** La foto leída campo por campo; null si no es del contrato. */
export function fotoFirmada(valor: unknown): FotoFirmada | null {
  if (!esObjeto(valor) || typeof valor.url !== 'string' || valor.url === '' || typeof valor.vence_en !== 'string') {
    return null;
  }
  return { url: valor.url, vence_en: valor.vence_en };
}
