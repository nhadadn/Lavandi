// Los textos de la configuración de comisiones de la administración (F-D05; administracion-app-E32 a E34), juntos para
// revisarlos en un solo lugar. Son los de las imágenes 28 a 30, 67 y 68 de la Propuesta 06 de los mocks, y los del panel
// de respaldo, para que la app y el panel digan lo mismo (servidor/app/Filament/Pages/ComisionGlobal.php y
// ComisionesPorLavanderia.php): "Sin porcentaje", "Vigente desde" en UTC, "Fijar porcentaje", "Fijar porcentaje propio",
// "Quitar porcentaje propio" y su confirmación. Los mensajes de cada error (422 y 404) los da el servidor; aquí viven los
// que pasan en el teléfono. Ninguno trae el nombre del producto ni el dominio (CLAUDE.md §5; administracion-app-E03), y
// ninguno trae un porcentaje: los captura EL CLIENTE.
import { MENSAJES, mensajeGeneral } from '../../acceso/mensajes';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';
import type { ComisionDeLaLavanderia, PorcentajeGlobal } from './api';

export const TEXTOS_DE_LAS_COMISIONES = {
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.comisiones,
  cargando: 'Cargando las comisiones…',
  intentarDeNuevo: 'Intentar de nuevo',

  // El global (imágenes 28, 29 y 67), con los textos de ComisionGlobal.php.
  porcentajeGlobal: 'Porcentaje global',
  sinPorcentaje: 'Sin porcentaje',
  vigenteDesde: 'Vigente desde',
  fijarPorcentaje: 'Fijar porcentaje',
  porcentaje: 'Porcentaje',

  // Cada lavandería (imágenes 28, 30, 67 y 68), con los textos de ComisionesPorLavanderia.php.
  porLavanderia: 'Por lavandería',
  sinLavanderias: 'No hay lavanderías.',
  fijarPorcentajePropio: 'Fijar porcentaje propio',
  quitarPorcentajePropio: 'Quitar porcentaje propio',
  porcentajePropio: 'Porcentaje propio',
  queHaceElPropio: 'Le aplica a esta lavandería en lugar del global. El anterior queda como historial.',
  // Las partes de la frase que dice qué le aplica a cada lavandería (queLeAplica(), abajo).
  sinPorcentajePropio: 'Sin porcentaje propio',
  leAplicaElPropio: 'le aplica el propio',
  noLeAplicaNinguno: 'no le aplica ninguno: falta el global',

  // El formulario del porcentaje, dentro de su tarjeta (imágenes 29 y 30): vacío, sin ningún número propuesto.
  guardar: 'Guardar',
  cancelar: TEXTOS_DE_LA_ADMINISTRACION.cancelar,

  // La confirmación de quitar el propio, con el diálogo del sistema (imagen 68).
  confirmarQuitar: 'Quitar el porcentaje propio',
  queHaceQuitar: 'A la lavandería le vuelve a aplicar el porcentaje global. El propio queda como historial.',
  quitar: 'Quitar',

  // El 404 sin mensaje del servidor; el servidor manda el mismo.
  noExiste: 'No existe la lavandería.',

  // La comisión de los socios repartidores espera DD-019 (administracion-app-E35): solo su lugar, en ámbar, con la
  // marca "Por confirmar" del resumen del pedido seguida de su número (design.md, sección 5), sin cifra ni botón.
  comisionDeLosRepartidores: 'Comisión de los socios repartidores',
  decisionDeLosRepartidores: 'DD-019',
} as const;

const TEXTOS = TEXTOS_DE_LAS_COMISIONES;

/** Un porcentaje como lo da el servidor, con su signo, como el panel: "12.50 %". */
export function porcentajeEnTexto(porcentaje: string): string {
  return `${porcentaje} %`;
}

/** El global de la tarjeta: su porcentaje, o "Sin porcentaje" mientras no haya, sin ningún número. */
export function globalEnTexto(global: PorcentajeGlobal | null): string {
  return global ? porcentajeEnTexto(global.porcentaje) : TEXTOS.sinPorcentaje;
}

/**
 * Desde cuándo rige el global, en UTC, como el panel: "01/12/2026 12:00 UTC" (ComisionGlobal.php, d/m/Y H:i). El global
 * no es de ninguna plaza, así que no lleva la hora de ninguna.
 */
export function vigenteDesdeEnTexto(vigenteDesde: string): string {
  const fecha = new Date(vigenteDesde);
  const dos = (numero: number) => String(numero).padStart(2, '0');
  return (
    `${dos(fecha.getUTCDate())}/${dos(fecha.getUTCMonth() + 1)}/${fecha.getUTCFullYear()} ` +
    `${dos(fecha.getUTCHours())}:${dos(fecha.getUTCMinutes())} UTC`
  );
}

/**
 * Qué le aplica a la lavandería y de dónde sale, en una frase, como lo dice el servidor (imágenes 28 y 67):
 * "Propio: 7.25 % · le aplica el propio", "Sin porcentaje propio · le aplica el global: 12.50 %" o "Sin porcentaje
 * propio · no le aplica ninguno: falta el global".
 */
export function queLeAplica({ propio, aplica }: Pick<ComisionDeLaLavanderia, 'propio' | 'aplica'>): string {
  const suPropio = propio === null ? TEXTOS.sinPorcentajePropio : `Propio: ${porcentajeEnTexto(propio)}`;
  const elQueAplica =
    aplica === null
      ? TEXTOS.noLeAplicaNinguno
      : aplica.origen === 'propio'
        ? TEXTOS.leAplicaElPropio
        : `le aplica el global: ${porcentajeEnTexto(aplica.porcentaje)}`;
  return `${suPropio} · ${elQueAplica}`;
}

/** Lo que lee el lector de pantalla en una acción de la lavandería: "Fijar porcentaje propio de Lavandería Centro". */
export function deLaLavanderia(accion: string, nombre: string): string {
  return `${accion} de ${nombre}`;
}

/**
 * El mensaje de una consulta o una acción que no salió, sin el error que va junto al campo del porcentaje: el del
 * servidor con el 404 y el 409; con un 422, el de los campos que la pantalla no muestra; sin conexión o con otro error,
 * los mensajes generales de la app. Con el 401 no hay nada que decir: la app ya volvió al inicio de sesión.
 */
export function mensajeDeLasComisiones(
  falla: FallaDeLaAdministracion,
  camposVisibles: readonly string[] = [],
): string | undefined {
  switch (falla.motivo) {
    case 'conflicto':
      return falla.mensaje || MENSAJES.servidor;
    case 'no-existe':
      return falla.mensaje || TEXTOS.noExiste;
    case 'sin-sesion':
      return undefined;
    default:
      return mensajeGeneral(falla, camposVisibles);
  }
}

/** El primer error del servidor para un campo, para mostrarlo junto a él (administracion-app-E34). */
export function errorDelCampo(falla: FallaDeLaAdministracion | null, campo: string): string | undefined {
  return falla?.motivo === 'invalido' ? falla.errores[campo]?.[0] : undefined;
}
