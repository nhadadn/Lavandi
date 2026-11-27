// Las fechas del filtro de "Mis pedidos" (historial-E03): se escriben en dos campos de texto, DD/MM/AAAA, con el teclado
// de números, y la app pone las diagonales. Al servidor van como AAAA-MM-DD, el día de la plaza que filtra
// GET /api/pedidos (design.md de historial-y-calificacion, decisión 1). Sin dependencia nativa: el selector de fechas del
// sistema no viene en React Native (design.md, Open Questions).

/** Los dígitos de una fecha DD/MM/AAAA. */
const DIGITOS_DE_UNA_FECHA = 8;
const FECHA_ESCRITA = /^(\d{2})\/(\d{2})\/(\d{4})$/;

/**
 * Lo escrito, como se ve en el campo: solo los dígitos, hasta ocho, con las diagonales del día y del mes. "10032026" se
 * ve "10/03/2026"; "100" se ve "10/0". Así, al borrar, la diagonal se va con el dígito que la sigue.
 */
export function conDiagonales(texto: string): string {
  const digitos = texto.replace(/\D/g, '').slice(0, DIGITOS_DE_UNA_FECHA);
  const partes = [digitos.slice(0, 2), digitos.slice(2, 4), digitos.slice(4)];
  return partes.filter((parte) => parte !== '').join('/');
}

/** El día AAAA-MM-DD de una fecha escrita DD/MM/AAAA, o null si está a medias o no existe, como el 31/02. */
export function fechaDeTexto(texto: string): string | null {
  const partes = FECHA_ESCRITA.exec(texto);
  if (!partes) {
    return null;
  }
  const [, dia, mes, anio] = partes;
  // El calendario en UTC, que no depende de la zona del teléfono: si el día no existe, Date lo pasa al mes siguiente.
  const fecha = new Date(Date.UTC(Number(anio), Number(mes) - 1, Number(dia)));
  const existe =
    fecha.getUTCFullYear() === Number(anio) &&
    fecha.getUTCMonth() === Number(mes) - 1 &&
    fecha.getUTCDate() === Number(dia);
  return existe ? `${anio}-${mes}-${dia}` : null;
}
