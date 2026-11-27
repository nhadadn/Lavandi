// Los textos de la gestión de lavanderías de la administración (administracion-app-E13 a E16), juntos para revisarlos
// en un solo lugar. Ninguno trae el nombre del producto ni el dominio (CLAUDE.md §5; ADR-0022, decisión 17). Son los de
// las imágenes 06 a 13 y 57 a 61 de la Propuesta 06 de los mocks, que toman los del panel de respaldo para que la app y
// el panel digan lo mismo: los nombres de los estados (servidor/app/Filament/Resources/Lavanderias/Tables/
// LavanderiasTable.php, ESTADOS) y las confirmaciones (servidor/app/Filament/Resources/Lavanderias/Actions/
// AccionesDeEstado.php). La app adapta solo lo que dice design.md, sección 5: "desde la app" en lugar de "desde el
// panel" al dar de baja, la confirmación de suspender sin el número de DD-104, que queda en la ficha, y las
// instrucciones de las fotos y de las ventanas de la edición, que hablan de la lavandería y no de tú.
//
// Los errores de cada dato al editar son los del servidor (ReglasDeLaLavanderia); el 409 de un cambio de estado trae el
// mensaje de EstadoDeLaLavanderia tal cual (administracion-app-E12), y el 404, el del servidor. Los que la app ya dice
// en el perfil de la lavandería o en su perfil y horarios se toman de ahí, para que digan lo mismo.
//
// Los marcados "Provisional" son textos nuevos de la app: no salen de una imagen de la Propuesta 06 ni de un escenario.
// La Propuesta 06 no dibuja la lista sin lavanderías, la búsqueda sin coincidencias, la lista mientras carga ni una
// ficha sin servicios, sin ventanas, sin descripción o sin fotos (sus estados vacíos son los de las imágenes 27, 40 y
// 55). Esperan que Arthur los apruebe en la spec o en una adenda de los mocks, como "Todavía no hay descargas." de la 55.
import { MENSAJES } from '../../acceso/mensajes';
import type { LavanderiaDelSocio } from '../../alta-lavanderias/api';
import { nombreDelDia } from '../../alta-lavanderias/formulario';
import type { TextosDeQuienEdita } from '../../alta-lavanderias/FormularioDeLaLavanderia';
import { cuantasVentanas, TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO } from '../../alta-lavanderias/textos';
import { mensajeDeLaFalla } from '../../cercanas/textos';
import { TEXTOS_DEL_RESUMEN } from '../../pedido/textos';
import { precioEnTexto, TEXTOS_DEL_PERFIL } from '../../perfil/textos';
import type { FallaDeLaAdministracion } from '../api';
import type { EstadoDeLaLavanderia, Transicion } from './api';

export const TEXTOS_DE_LAS_LAVANDERIAS = {
  // La lista (administracion-app-E13; imágenes 05 y 06). Su título es el de su entrada en el inicio del módulo.
  buscarPorNombre: 'Buscar por nombre',
  /** Provisional: texto nuevo de la app. */
  cargandoLaLista: 'Cargando las lavanderías…',
  /** Provisional: texto nuevo de la app. */
  sinLavanderias: 'Todavía no hay lavanderías.',
  /** Provisional: texto nuevo de la app. */
  sinCoincidencias: 'Ninguna lavandería tiene ese nombre.',
  intentarDeNuevo: TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO.intentarDeNuevo,
  // El alta espera DD-053 (administracion-app-E17): ni la lista ni la ficha la ofrecen (imagen 06).
  darDeAltaUnaLavanderia: 'Dar de alta una lavandería',
  sinAlta: 'La lista y las fichas no ofrecen el alta.',
  // La ficha (administracion-app-E13; imágenes 07, 09, 10 y 11).
  lavanderia: TEXTOS_DEL_PERFIL.encabezado,
  cargandoLaFicha: TEXTOS_DEL_PERFIL.cargando,
  direccion: TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO.direccion,
  serviciosYPrecios: TEXTOS_DEL_PERFIL.servicios,
  ventanas: 'Ventanas de disponibilidad',
  descripcionYFotos: 'Descripción y fotos',
  /** Provisional: texto nuevo de la app. */
  sinServicios: 'Sin servicios',
  /** Provisional: el del día sin ventanas del perfil y horarios, para la lavandería que no tiene ninguna. */
  sinVentanas: cuantasVentanas(0),
  /** Provisional: texto nuevo de la app. */
  sinDescripcion: 'Sin descripción',
  /** Provisional: texto nuevo de la app. */
  sinFotos: 'Sin fotos',
  editar: 'Editar',
  // Lo que pasa con los pedidos en curso espera DD-104 (administracion-app-E18): la ficha lo marca sobre los botones.
  pedidosEnCursoAlSuspenderODarDeBaja: 'Pedidos en curso al suspender o dar de baja',
  pedidosEnCursoAlDarDeBaja: 'Pedidos en curso al dar de baja',
  alLevantarLaSuspension:
    'Al levantar la suspensión, la lavandería recupera el estado que tenía: activa si ya tenía alta, pendiente si no.',
  deBaja: 'Ninguna acción de estado sale de la baja. Sus datos y sus pedidos pasados se quedan.',
  // La edición (administracion-app-E14; imágenes 12, 60, 13 y 61).
  editarLavanderia: 'Editar lavandería',
  guardarCambios: TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO.guardarCambios,
  // El lavado en seco espera la unidad que fije DD-010 (alta-lavanderias-E07): sin interruptor (imagen 13).
  lavadoEnSeco: 'Lavado en seco',
  sinLavadoEnSeco: 'La app no lo ofrece mientras no se fije su unidad.',
} as const;

/** Las decisiones que esperan las lavanderías, con su número (vault/meta/deuda/). */
export const DECISIONES = {
  /** El alta de una lavandería (administracion-app-E17). */
  alta: 'DD-053',
  /** Los pedidos en curso al suspender o dar de baja (administracion-app-E18). */
  pedidosEnCurso: 'DD-104',
  /** La unidad del lavado en seco (alta-lavanderias-E07). */
  lavadoEnSeco: 'DD-010',
} as const;

/** "Por confirmar · DD-104": la marca del resumen del pedido, seguida del número de la decisión que espera. */
export function porConfirmar(dd: string): string {
  return `${TEXTOS_DEL_RESUMEN.porConfirmar} · ${dd}`;
}

/** Cómo se lee cada estado, como en el panel (LavanderiasTable::ESTADOS). */
export const NOMBRES_DE_LOS_ESTADOS: Record<EstadoDeLaLavanderia, string> = {
  pendiente: 'Pendiente',
  activa: 'Activa',
  suspendida: 'Suspendida',
  baja: 'Dada de baja',
};

/**
 * El botón de cada transición y su confirmación con el diálogo del sistema (src/administracion/confirmar.ts): el
 * título, lo que pasa en palabras del dueño y el botón que la hace (imágenes 08, 57 y 59). La de suspender dice, como
 * el panel, que lo que pasa con los pedidos en curso todavía no está definido, sin decir qué pasa ni el número de la
 * DD, que queda en la ficha: eso espera DD-104 (administracion-app-E18).
 */
export const ACCIONES_DE_ESTADO: Record<Transicion, { boton: string; titulo: string; texto: string }> = {
  suspender: {
    boton: 'Suspender',
    titulo: 'Suspender la lavandería',
    texto:
      'Mientras esté suspendida, no aparece a los usuarios ni recibe pedidos nuevos. Lo que pasa con sus pedidos en curso todavía no está definido.',
  },
  levantar_suspension: {
    boton: 'Levantar la suspensión',
    titulo: 'Levantar la suspensión',
    texto: 'La lavandería recupera el estado que tenía: activa si ya tenía alta, pendiente si no.',
  },
  dar_de_baja: {
    boton: 'Dar de baja',
    titulo: 'Dar de baja la lavandería',
    texto:
      'Deja de aparecer a los usuarios y de recibir pedidos. Sus datos y sus pedidos pasados se quedan en la base. La baja no se deshace desde la app.',
  },
};

/**
 * Los textos del formulario de la lavandería que hablan de ella y no de tú, porque la edita la administración
 * (src/alta-lavanderias/FormularioDeLaLavanderia.tsx; imágenes 12, 60 y 61). El máximo de fotos lo da el servidor.
 */
export const TEXTOS_DEL_FORMULARIO: TextosDeQuienEdita = {
  datos: 'Datos de la lavandería',
  fotos: (maximo: number) => `La primera es la principal. Puede tener hasta ${maximo}.`,
  ventanas: 'Marca, día por día, las horas en que la lavandería puede recolectar. Cada ventana dura una hora.',
};

/** El mensaje de una falla del módulo: el del servidor en el 409 y el 404; los generales de la app en lo demás. */
export function mensajeDeLaAdministracion(falla: FallaDeLaAdministracion): string {
  if (falla.motivo === 'conflicto' || falla.motivo === 'no-existe') {
    return falla.mensaje || MENSAJES.servidor;
  }
  return mensajeDeLaFalla(falla);
}

/** "Calle de Prueba 100, Centro, C. P. 34000, Durango, Durango": la dirección en un renglón, sin las partes vacías. */
export function direccionEnTexto({ calle, numero, colonia, codigo_postal, municipio }: LavanderiaDelSocio['direccion']) {
  return [
    `${calle} ${numero}`.trim(),
    colonia,
    codigo_postal ? `C. P. ${codigo_postal}` : '',
    municipio ? [municipio.nombre, municipio.estado].filter(Boolean).join(', ') : '',
  ]
    .filter(Boolean)
    .join(', ');
}

/** "Lavado estándar: $18.50 por kilo · Lavado con planchado: $22.00 por kilo", con el precio que da el servidor. */
export function serviciosEnTexto(servicios: LavanderiaDelSocio['servicios']): string {
  if (servicios.length === 0) {
    return TEXTOS_DE_LAS_LAVANDERIAS.sinServicios;
  }
  return servicios.map((servicio) => `${servicio.nombre}: ${precioEnTexto(servicio)}`).join(' · ');
}

/**
 * Las ventanas como las dice la ficha de las imágenes 07 y 10: "Lunes a sábado, de 09:00 a 18:00". Las de una hora
 * seguidas van en un tramo; los tramos de un día, juntos con "y": "Domingo, de 10:00 a 12:00 y de 16:00 a 18:00"; y los
 * días seguidos con los mismos tramos, en un renglón. Las da el servidor por día y por hora, en la hora de la plaza.
 */
export function ventanasEnTexto(ventanas: LavanderiaDelSocio['ventanas']): string {
  if (ventanas.length === 0) {
    return TEXTOS_DE_LAS_LAVANDERIAS.sinVentanas;
  }
  const ordenadas = [...ventanas].sort(
    (una, otra) => una.dia_semana - otra.dia_semana || una.hora_inicio.localeCompare(otra.hora_inicio),
  );
  const porDia = new Map<number, { hora_inicio: string; hora_fin: string }[]>();
  for (const { dia_semana, hora_inicio, hora_fin } of ordenadas) {
    const delDia = porDia.get(dia_semana) ?? [];
    const ultima = delDia[delDia.length - 1];
    if (ultima && ultima.hora_fin === hora_inicio) {
      ultima.hora_fin = hora_fin;
    } else {
      delDia.push({ hora_inicio, hora_fin });
    }
    porDia.set(dia_semana, delDia);
  }
  // Cada renglón va del primer día al último seguido con los mismos tramos; el Map guarda los días en orden.
  const renglones: { desde: number; hasta: number; tramos: string }[] = [];
  for (const [dia, delDia] of porDia) {
    const tramos = delDia.map(({ hora_inicio, hora_fin }) => `de ${hora_inicio} a ${hora_fin}`).join(' y ');
    const anterior = renglones[renglones.length - 1];
    if (anterior && anterior.hasta === dia - 1 && anterior.tramos === tramos) {
      anterior.hasta = dia;
    } else {
      renglones.push({ desde: dia, hasta: dia, tramos });
    }
  }
  return renglones.map(({ desde, hasta, tramos }) => `${diasEnTexto(desde, hasta)}, ${tramos}`).join('\n');
}

/** "Lunes", o "Lunes a sábado" si son varios días seguidos. */
function diasEnTexto(desde: number, hasta: number): string {
  if (desde === hasta) {
    return nombreDelDia(desde);
  }
  return `${nombreDelDia(desde)} a ${nombreDelDia(hasta).toLocaleLowerCase('es')}`;
}

/** "Lavamos el mismo día. · 3 fotos": la descripción, o que no tiene, y cuántas fotos tiene su perfil. */
export function descripcionYFotosEnTexto({ descripcion, fotos }: Pick<LavanderiaDelSocio, 'descripcion' | 'fotos'>) {
  const cuantas =
    fotos.length === 0 ? TEXTOS_DE_LAS_LAVANDERIAS.sinFotos : fotos.length === 1 ? '1 foto' : `${fotos.length} fotos`;
  return `${descripcion?.trim() || TEXTOS_DE_LAS_LAVANDERIAS.sinDescripcion} · ${cuantas}`;
}
