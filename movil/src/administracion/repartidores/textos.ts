// Los textos de la gestión de repartidores de la administración (F-D02; administracion-app-E19 a E23), juntos para
// revisarlos en un solo lugar. Son los de las imágenes 14 a 20 y 62 a 65 de la Propuesta 06 de los mocks; los de las
// confirmaciones y los nombres de los estados, los del panel de respaldo, para que la app y el panel digan lo mismo
// (servidor/app/Filament/Resources/Repartidores/Actions/AccionesDeEstado.php y Tables/RepartidoresTable.php), con "desde
// la app" en lugar de "desde el panel" (design.md, sección 5). Los de la foto son los del registro del repartidor, que ya
// están en la app. Los que ninguna imagen dibuja (la carga, la lista vacía y el buscador de la hoja) dicen de dónde
// salen. Los mensajes de cada error (422, 404 y 409) los da el servidor; aquí viven los que pasan en el teléfono.
// Ninguno trae el nombre del producto ni el dominio (CLAUDE.md §5; administracion-app-E03).
import { errorDeCampo, MENSAJES, mensajeGeneral } from '../../acceso/mensajes';
import { TEXTOS_DEL_REPARTIDOR } from '../../repartidor/textos';
import type { FallaDeLaAdministracion } from '../api';
import type { EstadoDelRepartidor } from './api';

export const TEXTOS_DE_LOS_REPARTIDORES = {
  // La lista (imagen 14).
  titulo: 'Repartidores',
  // Mientras llega: ninguna imagen dibuja la carga, así que va con la forma de las de la app ("Cargando la lavandería…",
  // src/perfil/textos.ts:7).
  cargando: 'Cargando los repartidores…',
  // Sin ningún repartidor: ninguna imagen la dibuja, así que dice lo que el panel con su tabla vacía, el encabezado de
  // Filament en español (filament/tables v5.11.4, servidor/composer.lock:1569-1570, resources/lang/es/table.php,
  // "empty.heading"; APP_LOCALE=es, servidor/.env.example:12).
  sinRepartidores: 'No se encontraron registros',
  // El de la imagen 05.
  intentarDeNuevo: 'Intentar de nuevo',
  // Los nombres de los estados, como los lee el panel.
  estados: {
    pendiente: 'Pendiente',
    activo: 'Activo',
    suspendido: 'Suspendido',
    baja: 'Dado de baja',
  } satisfies Record<EstadoDelRepartidor, string>,
  // Lo que el panel pone cuando falta el dato (RepartidoresTable.php:43 y :29).
  sinPlaza: 'Sin plaza',
  sinNombre: 'Sin nombre',

  // La ficha (imágenes 15, 18 y 19).
  tituloDeLaFicha: 'Repartidor',
  // Como la carga de la lista.
  cargandoLaFicha: 'Cargando al repartidor…',
  // El 404 sin mensaje del servidor: el mismo que manda (el contrato de RepartidoresController, paquete A06).
  noExiste: 'No existe el repartidor.',
  telefono: 'Teléfono',
  correo: 'Correo',
  tipoDeVehiculo: 'Tipo de vehículo',
  // El alta (imágenes 15 a 17): la plaza se elige en la hoja del Selector, con las plazas del alta.
  plazaParaElAlta: 'Plaza para el alta',
  eligeLaPlaza: 'Elige la plaza',
  // El buscador que la hoja del Selector siempre trae (src/piezas/Selector.tsx:47) y la imagen 16 no dibuja, con la
  // forma de "Buscar municipio" (src/alta-lavanderias/textos.ts:28).
  buscarPlaza: 'Buscar plaza',
  validacionFuera: 'La validación de sus datos ocurre fuera de la plataforma.',
  siNoLoAutorizas: 'Si no autorizas el registro, dale de baja: ya no podrá iniciar sesión.',
  // Sin plaza elegida, el botón no se puede tocar y dice qué falta (administracion-app-E20).
  eligeUnaPlaza: 'Elige una plaza para darlo de alta',
  // Lo que pasa al levantar la suspensión (imagen 19).
  alLevantarLaSuspension: 'Al levantar la suspensión, vuelve a estar activo, en su misma plaza, y recupera sus pedidos.',
  editar: 'Editar',
  darDeAlta: 'Dar de alta',
  suspender: 'Suspender',
  levantarLaSuspension: 'Levantar la suspensión',
  darDeBaja: 'Dar de baja',
  // Las confirmaciones, con el diálogo del sistema (imágenes 17 y 63 a 65).
  confirmarElAlta: 'Dar de alta al repartidor',
  confirmarLaSuspension: 'Suspender al repartidor',
  queHaceLaSuspension:
    'Mientras esté suspendido, no ve ni mueve pedidos. Sigue entrando a la app, que le dice que está suspendido.',
  confirmarElLevantamiento: 'Levantar la suspensión',
  queHaceElLevantamiento: 'El repartidor vuelve a estar activo, en su misma plaza, y recupera sus pedidos.',
  confirmarLaBaja: 'Dar de baja al repartidor',
  queHaceLaBaja:
    'Ya no podrá iniciar sesión y se cierran sus sesiones abiertas. Sus entregas completadas se quedan en la base. La baja no se deshace desde la app.',

  // La edición (imagen 20).
  tituloDeLaEdicion: 'Editar repartidor',
  nombre: 'Nombre',
  correoElectronico: 'Correo electrónico',
  // La foto, con los textos del registro del repartidor (src/repartidor/textos.ts:20 y :32-35): la galería es la del
  // teléfono de quien edita. La foto se anuncia "Foto de perfil", como en las imágenes 15 a 20.
  fotoDePerfil: TEXTOS_DEL_REPARTIDOR.fotoDePerfil,
  tomarFoto: TEXTOS_DEL_REPARTIDOR.tomarFoto,
  elegirDeLaGaleria: TEXTOS_DEL_REPARTIDOR.elegirDeLaGaleria,
  sinPermisoDeLaCamara: TEXTOS_DEL_REPARTIDOR.sinPermisoDeLaCamara,
  fotoNoPreparada: TEXTOS_DEL_REPARTIDOR.fotoNoPreparada,
  guardarCambios: 'Guardar cambios',
} as const;

/** "Dar de alta en Plaza de prueba Durango": con la plaza elegida, el botón la nombra (imagen 17). */
export function darDeAltaEn(plaza: string): string {
  return `${TEXTOS_DE_LOS_REPARTIDORES.darDeAlta} en ${plaza}`;
}

/** Lo que pasa al dar de alta, en la confirmación: el texto del panel con la plaza elegida (imagen 17). */
export function queHaceElAlta(plaza: string): string {
  return `Queda activo en ${plaza} y ve los pedidos que se le asignen en ella. ${TEXTOS_DE_LOS_REPARTIDORES.validacionFuera}`;
}

/**
 * El mensaje de una consulta o una acción que no salió, sin los errores que van junto a su campo: el del servidor con
 * el 409 y el 404; con un 422, el de los campos que la pantalla no muestra; sin conexión o con otro error, los mensajes
 * generales de la app. Con el 401 no hay nada que decir: la app ya volvió al inicio de sesión.
 *
 * El 409 trae siempre el mensaje de App\Repartidores\TransicionNoPermitida (administracion-app-E12); si una respuesta
 * llegara sin él, sale el mensaje general de la app, como en la ficha de la lavandería.
 */
export function mensajeDeLosRepartidores(
  falla: FallaDeLaAdministracion,
  camposVisibles: readonly string[] = [],
): string | undefined {
  switch (falla.motivo) {
    case 'conflicto':
      return falla.mensaje || MENSAJES.servidor;
    case 'no-existe':
      return falla.mensaje || TEXTOS_DE_LOS_REPARTIDORES.noExiste;
    case 'sin-sesion':
      return undefined;
    default:
      return mensajeGeneral(falla, camposVisibles);
  }
}

/** El primer error del servidor para un campo, para mostrarlo junto a él: el de la app (errorDeCampo). */
export function errorDelCampo(falla: FallaDeLaAdministracion | null, campo: string): string | undefined {
  if (!falla || falla.motivo === 'conflicto' || falla.motivo === 'no-existe') {
    return undefined;
  }
  return errorDeCampo(falla, campo);
}
