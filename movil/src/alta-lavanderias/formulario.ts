// El formulario de la lavandería, el mismo en su registro (alta-lavanderias-E03) y en su perfil y horarios
// (alta-lavanderias-E19): lo que la persona captura, cómo se vuelve lo que manda la API y cómo cada error del servidor
// vuelve a su campo. Sin pantalla: la pantalla está en FormularioDeLaLavanderia.tsx.
//
// - Los servicios son los que el servidor registra hoy, cada uno con su precio por kilo (alta-lavanderias-E05): el
//   lavado estándar y el lavado con planchado. El lavado en seco entra cuando DD-010 fije su unidad
//   (alta-lavanderias-E07): el servidor lo rechaza mientras tanto.
// - Las ventanas son de una hora, por día de la semana (alta-lavanderias-E21 y E22), en punto: las 24 de cada día, de
//   00:00 – 01:00 a 23:00 – 24:00. Una ventana más larga que llega del servidor, como las franjas de las lavanderías de
//   prueba, se ve como sus horas; si la persona cambia el horario, se manda en ventanas de una hora (design.md,
//   pregunta abierta 11).
// - La descripción y la lista de fotos solo son del perfil y horarios (alta-lavanderias-E26 a E30): el registro no las
//   pide y conserva su foto única (Módulo B #1). Cada foto de la lista es una que ya tiene, por su lugar en la lectura,
//   o una nueva; la primera es la principal.
import { MENSAJES } from '../acceso/mensajes';
import { mensajeDeLaFalla } from '../cercanas/textos';
import type {
  CambiosDeLaLavanderia,
  DatosDeLaLavanderia,
  FallaDelEnvio,
  FotoQueViaja,
  LavanderiaDelSocio,
  VentanaDeLaLavanderia,
} from './api';
import { avisoDeLaDireccion, esMotivoDeLaDireccion } from './textos';

/**
 * Los servicios que se registran hoy: los tipos que el servidor admite con su unidad, por kilo
 * (servidor/app/Lavanderias/ServiciosYVentanas.php, UNIDAD_POR_TIPO), con su nombre del catálogo (tipos_servicio).
 */
export const SERVICIOS_QUE_SE_REGISTRAN = [
  { tipo: 'estandar', nombre: 'Lavado estándar' },
  { tipo: 'planchado', nombre: 'Lavado con planchado' },
] as const;

/** Los días de la semana como los cuenta la API: del 1, el lunes, al 7, el domingo. */
export const DIAS_DE_LA_SEMANA = [
  { dia: 1, nombre: 'Lunes' },
  { dia: 2, nombre: 'Martes' },
  { dia: 3, nombre: 'Miércoles' },
  { dia: 4, nombre: 'Jueves' },
  { dia: 5, nombre: 'Viernes' },
  { dia: 6, nombre: 'Sábado' },
  { dia: 7, nombre: 'Domingo' },
] as const;

const MINUTOS_POR_HORA = 60;
const HORAS_POR_DIA = 24;

/** "09:00": una hora en punto, de 0 a 24. */
function enPunto(hora: number): string {
  return `${String(hora).padStart(2, '0')}:00`;
}

/** Las 24 ventanas de una hora de un día, de 00:00 – 01:00 a 23:00 – 24:00; la última termina a la medianoche. */
export const HORAS_DEL_DIA: readonly { hora_inicio: string; hora_fin: string }[] = Array.from(
  { length: HORAS_POR_DIA },
  (_, hora) => ({ hora_inicio: enPunto(hora), hora_fin: enPunto(hora + 1) }),
);

/** El nombre del día: "Jueves". */
export function nombreDelDia(dia: number): string {
  return DIAS_DE_LA_SEMANA.find((uno) => uno.dia === dia)?.nombre ?? String(dia);
}

/** La clave de una ventana marcada: su día y su hora de inicio, "4-10:00". */
export function claveDeVentana(dia: number, horaInicio: string): string {
  return `${dia}-${horaInicio}`;
}

/** El municipio elegido de la lista: el código que viaja y el texto que muestra el campo. */
export interface MunicipioElegido {
  codigo: string;
  texto: string;
}

/** La foto del formulario: la guardada, por su URL firmada, o una nueva, ya comprimida en el teléfono. */
export interface FotoDelFormulario {
  uri: string;
  nueva: boolean;
}

/**
 * Una foto de la lista del perfil: la URL firmada de una guardada, o la dirección en el teléfono de una nueva, ya
 * comprimida. Una guardada lleva su lugar en "fotos" de la lectura; una nueva, null.
 */
export interface FotoDeLaLista {
  uri: string;
  guardada: number | null;
}

/** Lo que la persona captura. */
export interface Formulario {
  nombre: string;
  /** Solo en el perfil y horarios: la descripción del perfil, opcional. */
  descripcion: string;
  calle: string;
  numero: string;
  colonia: string;
  codigoPostal: string;
  municipio: MunicipioElegido | null;
  /** La foto única del registro. */
  foto: FotoDelFormulario | null;
  /** Solo en el perfil y horarios: las fotos del perfil, en su orden; la primera es la principal. */
  fotos: readonly FotoDeLaLista[];
  /** Por tipo de servicio: si lo ofrece y su precio por kilo, como se escribe. */
  servicios: Record<string, { ofrece: boolean; precio: string }>;
  /** Las ventanas marcadas, por su clave, en orden. */
  ventanas: readonly string[];
}

/** El formulario del registro, vacío. */
export function formularioVacio(): Formulario {
  return {
    nombre: '',
    descripcion: '',
    calle: '',
    numero: '',
    colonia: '',
    codigoPostal: '',
    municipio: null,
    foto: null,
    fotos: [],
    servicios: Object.fromEntries(SERVICIOS_QUE_SE_REGISTRAN.map(({ tipo }) => [tipo, { ofrece: false, precio: '' }])),
    ventanas: [],
  };
}

/** El formulario con la lavandería como está guardada (alta-lavanderias-E19). */
export function formularioDe(lavanderia: LavanderiaDelSocio): Formulario {
  const { direccion } = lavanderia;
  const vacio = formularioVacio();
  const servicios = { ...vacio.servicios };
  for (const servicio of lavanderia.servicios) {
    if (servicio.tipo in servicios) {
      servicios[servicio.tipo] = { ofrece: true, precio: servicio.precio };
    }
  }
  const municipio = direccion.municipio;
  return {
    nombre: lavanderia.nombre,
    descripcion: lavanderia.descripcion ?? '',
    calle: direccion.calle,
    numero: direccion.numero,
    colonia: direccion.colonia,
    codigoPostal: direccion.codigo_postal,
    municipio: municipio
      ? { codigo: municipio.codigo, texto: [municipio.nombre, municipio.estado].filter(Boolean).join(', ') }
      : null,
    foto: lavanderia.foto ? { uri: lavanderia.foto.url, nueva: false } : null,
    fotos: lavanderia.fotos.map((foto, lugar) => ({ uri: foto.url, guardada: lugar })),
    servicios,
    ventanas: ordenadas(lavanderia.ventanas.flatMap(horasDeLaVentana)),
  };
}

/** Agrega al final de la lista una foto nueva, ya comprimida (alta-lavanderias-E27). */
export function conFotoNueva(fotos: readonly FotoDeLaLista[], uri: string): FotoDeLaLista[] {
  return [...fotos, { uri, guardada: null }];
}

/** Quita de la lista la foto de ese lugar (alta-lavanderias-E28). */
export function sinLaFoto(fotos: readonly FotoDeLaLista[], lugar: number): FotoDeLaLista[] {
  return fotos.filter((_, otro) => otro !== lugar);
}

/** Pasa la foto de ese lugar antes de la anterior (alta-lavanderias-E29). */
export function conLaFotoAntes(fotos: readonly FotoDeLaLista[], lugar: number): FotoDeLaLista[] {
  if (lugar <= 0 || lugar >= fotos.length) {
    return [...fotos];
  }
  const nuevas = [...fotos];
  [nuevas[lugar - 1], nuevas[lugar]] = [nuevas[lugar], nuevas[lugar - 1]];
  return nuevas;
}

/** La lista de fotos como la manda la edición: cada guardada por su lugar en la lectura, cada nueva como su foto. */
function fotosQueViajan(fotos: readonly FotoDeLaLista[]): FotoQueViaja[] {
  return fotos.map(({ uri, guardada }) => guardada ?? { uri });
}

// Los minutos desde la medianoche de una hora HH:MM; 24:00 es la medianoche siguiente.
function minutos(hora: string): number {
  const [horas, mins] = hora.split(':').map(Number);
  return horas * MINUTOS_POR_HORA + mins;
}

// Las horas en punto que caben en una ventana guardada: una de 09:00 a 14:00 son cinco ventanas de una hora.
function horasDeLaVentana({ dia_semana, hora_inicio, hora_fin }: VentanaDeLaLavanderia): string[] {
  const inicio = minutos(hora_inicio);
  const fin = minutos(hora_fin);
  return HORAS_DEL_DIA.filter((hora) => minutos(hora.hora_inicio) >= inicio && minutos(hora.hora_fin) <= fin).map(
    (hora) => claveDeVentana(dia_semana, hora.hora_inicio),
  );
}

function ordenadas(claves: string[]): string[] {
  return [...new Set(claves)].sort();
}

/** Marca o desmarca una ventana de una hora. */
export function cambiarVentana(ventanas: readonly string[], clave: string, marcada: boolean): string[] {
  const sinElla = ventanas.filter((otra) => otra !== clave);
  return marcada ? ordenadas([...sinElla, clave]) : sinElla;
}

/** Cuántas ventanas tiene marcadas un día. */
export function ventanasDelDia(ventanas: readonly string[], dia: number): number {
  return ventanas.filter((clave) => clave.startsWith(`${dia}-`)).length;
}

/** El primer día con ventanas, o el lunes: el día que muestra el horario al abrirse. */
export function primerDiaConVentanas(ventanas: readonly string[]): number {
  return DIAS_DE_LA_SEMANA.find(({ dia }) => ventanasDelDia(ventanas, dia) > 0)?.dia ?? DIAS_DE_LA_SEMANA[0].dia;
}

/** Lo que el formulario manda: los cinco datos del registro, como los pide la API. */
export function datosDe(formulario: Formulario): DatosDeLaLavanderia {
  return {
    nombre: formulario.nombre,
    direccion: {
      calle: formulario.calle,
      numero: formulario.numero,
      colonia: formulario.colonia,
      codigo_postal: formulario.codigoPostal,
      municipio: formulario.municipio?.codigo ?? '',
    },
    foto: formulario.foto?.nueva ? { uri: formulario.foto.uri } : null,
    servicios: SERVICIOS_QUE_SE_REGISTRAN.filter(({ tipo }) => formulario.servicios[tipo]?.ofrece).map(({ tipo }) => ({
      tipo,
      precio: formulario.servicios[tipo].precio,
    })),
    ventanas: formulario.ventanas.map((clave) => {
      const [dia, horaInicio] = clave.split('-');
      const hora = HORAS_DEL_DIA.find(({ hora_inicio }) => hora_inicio === horaInicio);
      return { dia_semana: Number(dia), hora_inicio: horaInicio, hora_fin: hora?.hora_fin ?? horaInicio };
    }),
  };
}

const igual = (una: unknown, otra: unknown) => JSON.stringify(una) === JSON.stringify(otra);

/**
 * Lo que cambió desde que se abrió el perfil: en la edición solo viaja eso (alta-lavanderias-E19). La dirección va
 * completa si cambió cualquiera de sus partes; los servicios, las ventanas y las fotos, completos si cambió cualquiera
 * (alta-lavanderias-E27 a E29). La descripción, si cambió algo más que los espacios de las orillas (E26).
 */
export function cambiosEntre(antes: Formulario, ahora: Formulario): CambiosDeLaLavanderia {
  const anteriores = datosDe(antes);
  const actuales = datosDe(ahora);
  const cambios: CambiosDeLaLavanderia = {};
  if (actuales.nombre !== anteriores.nombre) {
    cambios.nombre = actuales.nombre;
  }
  if (ahora.descripcion.trim() !== antes.descripcion.trim()) {
    cambios.descripcion = ahora.descripcion;
  }
  const fotos = fotosQueViajan(ahora.fotos);
  if (!igual(fotos, fotosQueViajan(antes.fotos))) {
    cambios.fotos = fotos;
  }
  if (!igual(actuales.direccion, anteriores.direccion)) {
    cambios.direccion = actuales.direccion;
  }
  if (actuales.foto) {
    cambios.foto = actuales.foto;
  }
  if (!igual(actuales.servicios, anteriores.servicios)) {
    cambios.servicios = actuales.servicios;
  }
  if (!igual(actuales.ventanas, anteriores.ventanas)) {
    cambios.ventanas = actuales.ventanas;
  }
  return cambios;
}

/** Los campos del formulario que reciben su error del servidor tal cual, por su nombre en la API. */
const CAMPOS = [
  'nombre',
  'descripcion',
  'fotos',
  'direccion',
  'direccion.calle',
  'direccion.numero',
  'direccion.colonia',
  'direccion.codigo_postal',
  'direccion.municipio',
  'foto',
  'servicios',
  'ventanas',
];

/** El campo del precio de un servicio: "precio-estandar". */
export function campoDelPrecio(tipo: string): string {
  return `precio-${tipo}`;
}

/** Los errores de un envío: cada uno en su campo y, si alguno no tiene campo, el general, arriba del botón. */
export interface ErroresDelFormulario {
  campos: Record<string, string>;
  general?: string;
}

/**
 * Cada error del servidor en su campo (alta-lavanderias-E03 y E19). Los de un servicio (servicios.N…) van en el precio
 * del servicio que se mandó en ese lugar; los de una ventana (ventanas.N…), en el horario, con su día y sus horas; los
 * de una foto de la lista (fotos.N…), en la sección de fotos (alta-lavanderias-E27 y E30). La dirección que no se pudo
 * ubicar o que queda fuera de las zonas lleva el aviso de su motivo (alta-lavanderias-E10). Sin conexión, con el
 * servidor caído o con otro rechazo, sin escenario propio: los mensajes generales de la app.
 */
export function erroresDelEnvio(falla: FallaDelEnvio, enviados: CambiosDeLaLavanderia): ErroresDelFormulario {
  if (falla.motivo === 'conflicto') {
    return { campos: {}, general: falla.mensaje || MENSAJES.servidor };
  }
  const motivo = 'motivoDelServidor' in falla ? falla.motivoDelServidor : undefined;
  if (falla.motivo === 'servidor' && esMotivoDeLaDireccion(motivo)) {
    return { campos: { direccion: avisoDeLaDireccion(motivo, falla.mensaje) ?? MENSAJES.servidor } };
  }
  if (falla.motivo !== 'invalido') {
    return { campos: {}, general: mensajeDeLaFalla(falla) };
  }

  const campos: Record<string, string> = {};
  const deLasVentanas: string[] = [];
  let general: string | undefined;
  for (const [clave, mensajes] of Object.entries(falla.errores)) {
    const mensaje = mensajes?.[0];
    if (!mensaje) {
      continue;
    }
    const servicio = /^servicios\.(\d+)(?:\..+)?$/.exec(clave);
    const ventana = /^ventanas\.(\d+)(?:\..+)?$/.exec(clave);
    if (CAMPOS.includes(clave)) {
      campos[clave] ??= mensaje;
    } else if (/^fotos\.\d+(?:\..+)?$/.test(clave)) {
      campos.fotos ??= mensaje;
    } else if (servicio && enviados.servicios?.[Number(servicio[1])]) {
      campos[campoDelPrecio(enviados.servicios[Number(servicio[1])].tipo)] ??= mensaje;
    } else if (ventana && enviados.ventanas?.[Number(ventana[1])]) {
      const { dia_semana, hora_inicio, hora_fin } = enviados.ventanas[Number(ventana[1])];
      deLasVentanas.push(`${nombreDelDia(dia_semana)} ${hora_inicio} – ${hora_fin}: ${mensaje}`);
    } else {
      general ??= mensaje;
    }
  }
  if (deLasVentanas.length > 0) {
    campos.ventanas = [campos.ventanas, ...deLasVentanas].filter(Boolean).join('\n');
  }
  if (campos.direccion !== undefined || esMotivoDeLaDireccion(motivo)) {
    campos.direccion = avisoDeLaDireccion(motivo, campos.direccion ?? falla.mensaje) ?? MENSAJES.servidor;
  }
  if (Object.keys(campos).length === 0 && general === undefined) {
    general = falla.mensaje || MENSAJES.servidor;
  }
  return { campos, general };
}
