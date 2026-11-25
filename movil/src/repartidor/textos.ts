// Los textos del Módulo C, del socio repartidor (openspec/changes/repartidor), juntos para revisarlos en un solo lugar.
// Los títulos son los de las imágenes del Módulo C de la Propuesta 04 (c01-registro a c05-estadisticas). Los errores de
// cada dato del registro, los nombres de los estados y los de los botones los da el servidor; aquí viven los mensajes que
// pasan en el teléfono. Sin montos, ingreso ni calificación: esperan DD-019, DD-013 y DD-052 (repartidor-E26, E27 y E29).
import { MENSAJES } from '../acceso/mensajes';
import type { Ventana } from '../agenda/api';
import { eleccionEnTexto } from '../agenda/textos';
import { mensajeDeLaFalla } from '../cercanas/textos';
import { TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA } from '../historial-lavanderia/textos';
import { TEXTOS_DEL_RESUMEN } from '../pedido/textos';
import { TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../pedidos-lavanderia/textos';
import type { FallaDelRepartidor, LavanderiaDelPedido, PedidoDelRepartidor } from './api';

export const TEXTOS_DEL_REPARTIDOR = {
  // El registro (repartidor-E01 y E02), con los títulos de la imagen c01-registro.
  registro: 'Registro de repartidor',
  tusDatos: 'Tus datos',
  // Lo que pasa al enviarlo: el alta, la validación y la autorización corresponden a EL CLIENTE (Módulo C #1).
  queSigue: 'Tu registro queda pendiente hasta que la administración lo autorice.',
  fotoDePerfil: 'Foto de perfil',
  // La foto y los nombres de los datos también van en la cabecera de Pedidos asignados, donde el repartidor ve lo que
  // le editó la administración (repartidor-E08).
  tuFotoDePerfil: 'Tu foto de perfil',
  nombre: 'Nombre',
  telefono: 'Teléfono',
  correo: 'Correo electrónico',
  contrasena: 'Contraseña',
  tipoDeVehiculo: 'Tipo de vehículo',
  enviarRegistro: 'Enviar registro',
  // La foto, como en el registro de la lavandería: sin escenario propio, sin permiso de la cámara sigue la galería, y la
  // foto que no se pudo leer o comprimir.
  tomarFoto: 'Tomar foto',
  elegirDeLaGaleria: 'Elegir de la galería',
  sinPermisoDeLaCamara: 'Sin permiso de la cámara no podemos tomar la foto. Puedes elegirla de tu galería.',
  fotoNoPreparada: 'No pudimos preparar la foto. Intenta con otra.',
  // La entrada al módulo: mientras se lee su estado, o si no llega (sin escenario propio).
  leyendo: 'Cargando tu cuenta de repartidor…',
  intentarDeNuevo: 'Intentar de nuevo',
  // La espera de su alta (repartidor-E01 y E03) o de que le quiten la suspensión (repartidor-E06). Los dos mensajes son
  // los del servidor (servidor/app/Repartidores/RepartidorSinAcceso.php).
  registroEnviado: 'Registro enviado',
  cuentaSuspendida: 'Cuenta suspendida',
  esperaLaAutorizacion: 'Tu registro espera la autorización de la administración.',
  estaSuspendida: 'Tu cuenta de repartidor está suspendida.',
  cuandoTeDenDeAlta: 'Cuando te den de alta, aquí verás los pedidos que te asignen.',
  mientrasSuspendida: 'Mientras esté suspendida no puedes ver ni mover pedidos.',
  revisarDeNuevo: 'Revisar de nuevo',
  // Los pedidos asignados (repartidor-E10), con el título de la imagen c02-asignados y los datos del componente 5.
  pedidosAsignados: 'Pedidos asignados',
  cargando: 'Cargando tus pedidos…',
  // La lista vacía: un mensaje neutro, como la de la lavandería.
  sinPedidos: 'No tienes pedidos asignados.',
  recoleccion: 'Recolección',
  recogerEn: 'Recoger en',
  entregarEn: 'Entregar en',
  lavanderia: 'Lavandería',
  // Sin entrega distinta, el servidor manda la misma dirección: no se repite (direcciones-E09).
  mismaDeLaRecogida: TEXTOS_DEL_RESUMEN.mismaDeLaRecogida,
  // Las respuestas de la tarjeta (repartidor-E15 y E16), con la nota del componente 5, y el rechazo con motivo, con los
  // textos de la imagen c02-rechazo: los mismos de la lavandería. Sin motivo, el botón no se puede tocar y dice qué
  // falta (repartidor-E17).
  aceptar: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.aceptar,
  rechazar: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.rechazar,
  notaDelRechazo: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.notaDelRechazo,
  aceptaste: 'Aceptaste este pedido.',
  tituloDelRechazo: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.tituloDelRechazo,
  motivo: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.motivo,
  rechazarPedido: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.rechazarPedido,
  escribeElMotivo: TEXTOS_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA.escribeElMotivo,
  // La entrega con la foto de evidencia (repartidor-E23), con la composición del rechazo. La foto solo se toma con la
  // cámara. Sin escenario propio: sin permiso de la cámara, el permiso se da en los ajustes del teléfono.
  tituloDeLaEntrega: 'Entregar pedido',
  paraLaEvidencia: 'Toma una foto de la entrega como evidencia.',
  fotoDeEvidencia: 'Foto de evidencia',
  laFotoDeLaEvidencia: 'La foto de la evidencia',
  sinPermisoParaLaEvidencia: 'Sin permiso de la cámara no podemos tomar la foto. Puedes darlo en los ajustes del teléfono.',
  marcarEntregado: 'Marcar entregado',
  // El historial de entregas (repartidor-E25), con el título de la imagen c04-historial. Sin escenario propio: el
  // historial vacío, con un mensaje neutro, como el de la lavandería.
  historialDeEntregas: 'Historial de entregas',
  cargandoElHistorial: 'Cargando tu historial…',
  sinEntregas: 'Todavía no tienes entregas completadas.',
  // Las estadísticas (repartidor-E28), con los títulos de la imagen c05-estadisticas. "Del día" y "de la semana" se
  // cuentan en la hora de la plaza, de lunes a domingo (design.md, lectura 5).
  misEstadisticas: 'Mis estadísticas',
  cargandoLasEstadisticas: 'Cargando tus estadísticas…',
  entregasDeHoy: 'Entregas de hoy',
  entregasDeLaSemana: 'Entregas de esta semana',
  laSemana: TEXTOS_DEL_HISTORIAL_DE_LA_LAVANDERIA.laSemana,
  // El 409 sin mensaje del servidor: el pedido ya cambió. El servidor manda el suyo: "La oferta ya fue respondida.",
  // "Primero acepta el pedido." o "Ese no es el paso siguiente del pedido.".
  yaCambio: 'El pedido ya cambió. Revisa cómo está.',
} as const;

/** "Jueves 3 de diciembre · 11:00 – 12:00": la fecha y la ventana de la recolección, en la hora de la plaza. */
export function recoleccionEnTexto(ventana: Ventana): string {
  return eleccionEnTexto(ventana, null);
}

/** "Calle de la lavandería 45, Del Norte": la dirección de la lavandería, con lo que tenga. */
export function direccionDeLaLavanderia({ calle, numero, colonia }: LavanderiaDelPedido): string {
  return [[calle, numero].filter(Boolean).join(' '), colonia].filter(Boolean).join(', ');
}

/** Si la entrega es la misma dirección de la recogida: el servidor manda la copia de la recogida en las dos. */
export function seEntregaDondeSeRecoge({ recogida, entrega }: Pick<PedidoDelRepartidor, 'recogida' | 'entrega'>): boolean {
  return JSON.stringify(recogida) === JSON.stringify(entrega);
}

/**
 * El mensaje de una consulta o una acción que no salió: el del servidor con el 409 y con el 403 del repartidor que ya
 * no opera; sin conexión o con otro error, los mensajes generales de la app.
 */
export function mensajeDelRepartidor(falla: FallaDelRepartidor): string {
  if (falla.motivo === 'conflicto') {
    return falla.mensaje || TEXTOS_DEL_REPARTIDOR.yaCambio;
  }
  if (falla.motivo === 'sin-acceso') {
    return falla.mensaje || MENSAJES.servidor;
  }
  return mensajeDeLaFalla(falla);
}
