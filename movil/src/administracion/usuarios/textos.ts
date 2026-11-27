// Los textos de la gestión de usuarios de la administración (F-D03), juntos para revisarlos en un solo lugar. Ninguno
// trae el nombre del producto ni el dominio (CLAUDE.md §5; ADR-0022, decisión 17). Son los de las imágenes 21 a 24 y 66
// de la Propuesta 06 de los mocks, que toman los del panel de respaldo para que la app y el panel digan lo mismo: los
// estados y los vacíos de servidor/app/Filament/Resources/Usuarios/Tables/UsuariosTable.php y
// Schemas/UsuarioInfolist.php, y los botones y las confirmaciones de Actions/AccionesDeBloqueo.php. El diálogo de
// bloquear dice la frase del panel sin el número de la DD, que queda en ámbar en la ficha (design.md del cambio,
// sección 5; imágenes 22 y 23).
import { MENSAJES, mensajeGeneral } from '../../acceso/mensajes';
import { pedidoConFolio } from '../../estados/textos';
import { TEXTOS_DEL_RESUMEN } from '../../pedido/textos';
import type { FallaDeLaAdministracion } from '../api';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../textos';
import type { EstadoDelUsuario, PedidoDelUsuario, UsuarioDeLaLista } from './api';

export const TEXTOS_DE_LOS_USUARIOS = {
  /** El encabezado de la lista: el nombre de la sección en el inicio del módulo. */
  titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.usuarios,
  tituloDeLaFicha: 'Usuario',
  buscar: 'Buscar por correo o nombre',
  cargandoLaLista: 'Cargando los usuarios…',
  sinUsuarios: 'No hay usuarios.',
  sinCoincidencias: 'Ningún usuario coincide con la búsqueda.',
  paginaAnterior: 'Página anterior',
  siguientePagina: 'Siguiente página',
  intentarDeNuevo: 'Intentar de nuevo',
  /** El filtro de usuarios activos espera DD-111 (administracion-app-E26): la lista lo marca y no lo ofrece. */
  activosDeUnPeriodo: 'Solo los usuarios activos de un periodo',
  esperaElFiltro: 'DD-111',
  estados: {
    activo: 'Activo',
    bloqueado: 'Bloqueado',
  } satisfies Record<EstadoDelUsuario, string>,
  sinNombre: 'Sin nombre',
  correo: 'Correo',
  telefono: 'Teléfono',
  sinTelefono: 'Sin teléfono',
  cargandoLaFicha: 'Cargando el usuario…',
  /** El 404 de la ficha, si el servidor no trae su mensaje (administracion-app-E12). */
  noExiste: 'No existe el usuario.',
  historial: 'Historial de pedidos',
  sinPedidos: 'Sin pedidos.',
  /** Un pedido sin monto todavía (DD-012, DD-010): ninguna cifra. */
  sinMonto: 'Sin monto',
  /** Lo que pasa con un pedido en curso al bloquear espera DD-016 (administracion-app-E27): la ficha lo marca. */
  pedidoEnCursoAlBloquear: 'Un pedido en curso al bloquear',
  esperaElPedidoEnCurso: 'DD-016',
  bloquear: 'Bloquear',
  levantarElBloqueo: 'Levantar el bloqueo',
  alLevantarlo: 'Al levantarlo, vuelve a estar activo y puede iniciar sesión otra vez.',
  confirmarElBloqueo: {
    titulo: 'Bloquear al usuario',
    texto:
      'Se cierran sus sesiones abiertas y no podrá entrar a la app con su correo ni con Google hasta que se levante el bloqueo. Sus pedidos no cambian: qué pasa con uno en curso todavía no está definido.',
  },
  confirmarElLevantamiento: {
    titulo: 'Levantar el bloqueo',
    texto: 'El usuario vuelve a estar activo y puede iniciar sesión otra vez.',
  },
} as const;

const TEXTOS = TEXTOS_DE_LOS_USUARIOS;

/**
 * "Por confirmar · DD-111": lo que espera una DD lleva la marca "Por confirmar" del resumen del pedido seguida de su
 * número (design.md del cambio, sección 5).
 */
export function porConfirmar(dd: string): string {
  return `${TEXTOS_DEL_RESUMEN.porConfirmar} · ${dd}`;
}

/** El nombre de la cuenta, o que todavía no lo escribe, como el panel. */
export function nombreDelUsuario(nombre: string | null): string {
  return nombre ?? TEXTOS.sinNombre;
}

/** "Página 2 de 3". */
export function paginaDeLaLista(actual: number, ultima: number): string {
  return `Página ${actual} de ${ultima}`;
}

/** Lo que lee el lector de pantalla en cada fila de la lista, todo junto: "Ana, Activo, ana@ejemplo.com". */
export function usuarioParaLeer({ nombre, estado, correo }: UsuarioDeLaLista): string {
  return [nombreDelUsuario(nombre), TEXTOS.estados[estado], correo].join(', ');
}

/** "24/11/2026": el día AAAA-MM-DD de la plaza, como lo da el servidor, en el orden del panel. */
export function fechaDelPedido(fecha: string): string {
  const [anio, mes, dia] = fecha.split('-');
  return `${dia}/${mes}/${anio}`;
}

/**
 * El monto en pesos como lo escribe el panel (Number::currency con es_MX): "$1,180.50". Se arma con el texto que da el
 * servidor, sin pasarlo a número, para no redondear. Sin monto, lo dice, sin cifra (DD-012, DD-010).
 */
export function montoDelPedido(monto: string | null): string {
  if (monto === null) {
    return TEXTOS.sinMonto;
  }
  const [enteros, decimales = ''] = monto.split('.');
  const conMiles = enteros.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
  return `$${conMiles}.${decimales.padEnd(2, '0')}`;
}

/** Bajo la lavandería de cada pedido: "24/11/2026 · $250.00". */
export function fechaYMonto({ fecha, monto }: PedidoDelUsuario): string {
  return `${fechaDelPedido(fecha)} · ${montoDelPedido(monto)}`;
}

/** Lo que lee el lector de pantalla en cada pedido: "Pedido 7K2M9QXA, En proceso, Lavandería del centro, 24/11/2026, Sin monto". */
export function pedidoParaLeer(pedido: PedidoDelUsuario): string {
  return [
    pedidoConFolio(pedido.folio),
    pedido.estado,
    pedido.lavanderia,
    fechaDelPedido(pedido.fecha),
    montoDelPedido(pedido.monto),
  ].join(', ');
}

/**
 * El mensaje de una consulta o una acción que no salió: el del servidor para un registro que no existe (el 404), o el
 * general de la app para lo demás, como la conexión. El 401 ya llevó al inicio de sesión (src/administracion/api.ts) y
 * no tiene nada que decir aquí.
 */
export function mensajeDeLaFalla(falla: FallaDeLaAdministracion): string | undefined {
  switch (falla.motivo) {
    case 'no-existe':
      return falla.mensaje || TEXTOS.noExiste;
    case 'conflicto':
      return falla.mensaje || MENSAJES.servidor;
    case 'sin-sesion':
      return undefined;
    default:
      return mensajeGeneral(falla);
  }
}
