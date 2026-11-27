// Los textos de los avisos masivos de la administración (F-D07; administracion-app-E42 y E43), juntos para revisarlos
// en un solo lugar. Son los de las imágenes 36 a 40 de la Propuesta 06 de los mocks; los que también dice el panel de
// respaldo, con sus mismas palabras, para que la app y el panel digan lo mismo: "A quién le llega"
// (servidor/app/Filament/Resources/AvisosMasivos/Schemas/AvisoMasivoForm.php:37-39), "Todavía no se ha mandado ningún
// aviso." y "En cola" (Tables/AvisosMasivosTable.php:32 y :29). Los mensajes de cada error 422 los da el servidor
// (App\Avisos\CreacionDeAvisoMasivo::mensajes()); aquí viven los que pasan en el teléfono. Ninguno trae el nombre del
// producto ni el dominio (CLAUDE.md §5; administracion-app-E03).
import { errorDeCampo, mensajeGeneral } from '../../acceso/mensajes';
import { TEXTOS_DEL_RESUMEN } from '../../pedido/textos';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';

export const TEXTOS_DE_LOS_AVISOS = {
  // La lista (imágenes 36 y 40).
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.avisos,
  escribirAviso: 'Escribir aviso',
  cargando: 'Cargando los avisos…',
  sinAvisos: 'Todavía no se ha mandado ningún aviso.',
  intentarDeNuevo: 'Intentar de nuevo',
  // Mientras el trabajo de la cola no cuenta a cuántos teléfonos salió.
  enCola: 'En cola',
  // Quién lo mandó: la API dice solo si fue la cuenta de la sesión, sin el correo de otra cuenta de administración.
  loMandasteTu: 'lo mandaste tú',
  loMandoOtraCuenta: 'lo mandó otra cuenta de administración',

  // Escribir un aviso (imágenes 37 a 39).
  tituloDelAvisoNuevo: 'Escribir aviso',
  etiquetaDelTitulo: 'Título',
  etiquetaDelTexto: 'Texto',
  aQuienLeLlega: 'A quién le llega',
  destinatarios: 'A todas las cuentas de la app con un teléfono registrado para avisos, salvo las bloqueadas.',
  // El aviso a un segmento espera DD-113 (administracion-app-E44): en ámbar, sin ofrecerlo.
  avisoAUnSegmento: 'Aviso a un segmento',
  porConfirmarSegmento: `${TEXTOS_DEL_RESUMEN.porConfirmar} · DD-113`,
  sinSegmentos: 'La app no ofrece segmentos.',
  mandarAviso: 'Mandar aviso',
  // La confirmación, con el diálogo del sistema (imagen 39).
  confirmarElAviso: '¿Mandar el aviso?',
  queHaceElAviso: 'Sale a todos los teléfonos registrados.',
  mandar: 'Mandar',
} as const;

/** "30 de 255 caracteres": cuántos caracteres lleva el campo de su tope (imágenes 37 y 38). */
export function caracteresDe(cuantos: number, tope: number): string {
  return `${cuantos} de ${tope} caracteres`;
}

/** "Salió a 3 teléfonos", o "Salió a 1 teléfono" (imagen 36). */
export function salioA(destinatarios: number): string {
  return `Salió a ${destinatarios} ${destinatarios === 1 ? 'teléfono' : 'teléfonos'}`;
}

/**
 * "24/11/2026, 10:30 · lo mandaste tú": cuándo lo mandaron y quién (imagen 36). El formato es el del panel (d/m/Y H:i),
 * pero la hora no: la app pasa "enviado_en", que llega en UTC, a la hora del teléfono, y el panel la muestra en UTC
 * (servidor/config/app.php, "timezone"), así que los dos pueden no dar la misma hora. Qué zona va aquí no lo dice
 * design.md (F-D07) ni administracion-app-E43: queda para que se decida en la spec.
 */
export function cuandoYQuien(enviadoEn: string, porEstaCuenta: boolean): string {
  const enviado = new Date(enviadoEn);
  const dia = `${dosDigitos(enviado.getDate())}/${dosDigitos(enviado.getMonth() + 1)}/${enviado.getFullYear()}`;
  const hora = `${dosDigitos(enviado.getHours())}:${dosDigitos(enviado.getMinutes())}`;
  const quien = porEstaCuenta ? TEXTOS_DE_LOS_AVISOS.loMandasteTu : TEXTOS_DE_LOS_AVISOS.loMandoOtraCuenta;
  return `${dia}, ${hora} · ${quien}`;
}

function dosDigitos(numero: number): string {
  return String(numero).padStart(2, '0');
}

/**
 * Cuántos caracteres lleva un texto, como los cuenta el tope del servidor (mb_strlen): una letra con acento o un emoji
 * cuentan uno.
 */
export function cuantosCaracteres(texto: string): number {
  return Array.from(texto).length;
}

/**
 * El mensaje de una consulta o de un envío que no salió, sin los errores que van junto a su campo: con un 422, el de
 * los campos que la pantalla no muestra; sin conexión o con otro error, los mensajes generales de la app. Con el 401 no
 * hay nada que decir: la app ya volvió al inicio de sesión.
 */
export function mensajeDeLosAvisos(
  falla: FallaDeLaAdministracion,
  camposVisibles: readonly string[] = [],
): string | undefined {
  switch (falla.motivo) {
    case 'sin-sesion':
      return undefined;
    case 'conflicto':
    case 'no-existe':
      // Las rutas de los avisos no responden 409 ni 404; si llegara uno, el mensaje del servidor o el general.
      return falla.mensaje || mensajeGeneral({ ok: false, motivo: 'servidor' });
    default:
      return mensajeGeneral(falla, camposVisibles);
  }
}

/** El primer error del servidor para un campo, para mostrarlo junto a él, como en el resto de la app. */
export function errorDelCampo(falla: FallaDeLaAdministracion | null, campo: string): string | undefined {
  return falla?.motivo === 'invalido' ? errorDeCampo(falla, campo) : undefined;
}
