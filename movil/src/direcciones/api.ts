// Las direcciones de la cuenta (openspec/changes/direcciones): guardar una nueva, escrita o marcada en el mapa
// (direcciones-E01 a E04 y E06), la lista de favoritas (direcciones-E06 a E08) y la recogida y la entrega de un pedido,
// que el servidor acepta solo dentro de la zona de servicio de la plaza de la lavandería (direcciones-E09 a E11). Y el
// catálogo del territorio para escribirla (C-238): los municipios, para elegir uno de la lista (direcciones-E17), y las
// colonias de un código postal (direcciones-E19 y E20). Todo va con el token de la sesión y la API identifica cada
// dirección por su id_publico; al municipio, por su código del catálogo. El contrato exacto con cada cuerpo y cada error está en el comentario de
// servidor/app/Http/Controllers/Api/DireccionesController.php. Como las demás consultas, nunca lanza: devuelve un
// Resultado.
//
// La geocodificación vive en el servidor (ADR-0009, decisión 4): la app manda la dirección escrita sin punto, y el
// servidor la convierte una sola vez, al guardarla. La del mapa viaja con el punto del pin y no se geocodifica. La app
// nunca consulta la geocodificación, ni de ida ni de vuelta.
import { pedir, type Falla, type Resultado } from '../configuracion/api';
import { esObjeto, numero } from '../configuracion/lectura';
import type { ParaQueEsLaDireccion } from '../pedido/pedidoEnCurso';

/** Las rutas de las direcciones, en un solo lugar para ajustarlas. */
export const CONTRATO_DE_DIRECCIONES = {
  ruta: 'direcciones',
  clave: 'direccion',
  favoritas: 'direcciones/favoritas',
  lista: 'direcciones',
  recogidaYEntrega: 'direcciones/recogida-y-entrega',
  municipios: 'catalogos/municipios',
  /** GET codigos-postales/{codigo_postal}/colonias. */
  colonias: (codigoPostal: string) => `codigos-postales/${encodeURIComponent(codigoPostal)}/colonias`,
} as const;

/** Los motivos con los que el servidor rechaza guardar una dirección escrita (direcciones-E03). */
const NO_ENCONTRADA = 'direccion_no_encontrada';
const GEOCODIFICACION_NO_DISPONIBLE = 'geocodificacion_no_disponible';

/** Una dirección de la cuenta, como la devuelve el servidor, con el punto guardado en grados (WGS 84). */
export interface Direccion {
  id_publico: string;
  /** El nombre de la favorita ("Casa"); null en las que no son favoritas. */
  alias: string | null;
  es_favorita: boolean;
  calle: string | null;
  numero_exterior: string | null;
  numero_interior: string | null;
  /** Siempre: es obligatoria (direcciones-E02). */
  colonia: string;
  codigo_postal: string | null;
  /** El nombre del municipio del catálogo; null en una del mapa sin municipio. */
  municipio: string | null;
  /** El nombre del estado de ese municipio (direcciones-E17); null sin municipio. */
  estado: string | null;
  referencias: string | null;
  latitud: number;
  longitud: number;
}

/** Las señas que escribe la persona, en las dos formas. */
export interface SenasDeLaDireccion {
  calle: string;
  numero_exterior: string;
  numero_interior: string;
  /** Opcional: si viene, cinco números (direcciones-E20). */
  codigo_postal: string;
  colonia: string;
  /** El código del municipio elegido de la lista (direcciones-E17); vacío si no eligió ninguno. */
  municipio: string;
  referencias: string;
}

/** Si se guarda como favorita, con su nombre (direcciones-E06). */
export interface Favorita {
  es_favorita: boolean;
  alias: string;
}

/** Una dirección nueva: escrita, que el servidor geocodifica (direcciones-E01). */
export interface DireccionEscrita extends SenasDeLaDireccion, Favorita {
  origen: 'escrita';
}

/** Una dirección nueva marcada en el mapa: el punto del pin, que se guarda tal cual (direcciones-E04). */
export interface DireccionDelMapa extends SenasDeLaDireccion, Favorita {
  origen: 'mapa';
  latitud: number;
  longitud: number;
}

export type DireccionNueva = DireccionEscrita | DireccionDelMapa;

/**
 * Una dirección nueva y falla con motivo propio: la escrita que el servicio de geocodificación no encontró, o el
 * servicio que no respondió. En los dos casos el servidor no guardó nada y la app ofrece el mapa (direcciones-E03).
 */
export type FallaDelAlta = Falla | { ok: false; motivo: 'no-encontrada' } | { ok: false; motivo: 'sin-geocodificacion' };

/** POST de una dirección nueva, con la sesión. Devuelve la dirección guardada, con su punto. */
export async function guardarDireccion(
  token: string,
  nueva: DireccionNueva,
): Promise<{ ok: true; datos: Direccion } | FallaDelAlta> {
  const { ruta, clave } = CONTRATO_DE_DIRECCIONES;
  const resultado = await pedir<unknown>(ruta, { metodo: 'POST', token, cuerpo: cuerpoDelAlta(nueva) });
  if (!resultado.ok) {
    return fallaDelAlta(resultado);
  }
  const direccion = esObjeto(resultado.datos) ? leerDireccion(resultado.datos[clave]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return direccion ? { ok: true, datos: direccion } : { ok: false, motivo: 'servidor' };
}

// El cuerpo del alta: el texto de cada seña sin espacios de sobra; las vacías no viajan, y el servidor dice cuál
// falta (direcciones-E02). El punto solo viaja con la del mapa, y el nombre solo con la favorita.
function cuerpoDelAlta({ es_favorita, alias, ...resto }: DireccionNueva): Record<string, unknown> {
  const cuerpo: Record<string, unknown> = {};
  for (const [campo, valor] of Object.entries(resto)) {
    const limpio = typeof valor === 'string' ? valor.trim() : valor;
    if (limpio !== '') {
      cuerpo[campo] = limpio;
    }
  }
  cuerpo.es_favorita = es_favorita;
  if (es_favorita && alias.trim() !== '') {
    cuerpo.alias = alias.trim();
  }
  return cuerpo;
}

function fallaDelAlta(falla: Falla): FallaDelAlta {
  if (falla.motivo === 'invalido' && falla.motivoDelServidor === NO_ENCONTRADA) {
    return { ok: false, motivo: 'no-encontrada' };
  }
  if (falla.motivo === 'servidor' && falla.motivoDelServidor === GEOCODIFICACION_NO_DISPONIBLE) {
    return { ok: false, motivo: 'sin-geocodificacion' };
  }
  return falla;
}

/** GET de las favoritas de la cuenta, en el orden en que se guardaron, con su punto guardado (direcciones-E06). */
export async function consultarFavoritas(token: string): Promise<Resultado<Direccion[]>> {
  const { favoritas, lista } = CONTRATO_DE_DIRECCIONES;
  const resultado = await pedir<unknown>(favoritas, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  return listaDelContrato(resultado.datos, lista, leerDireccion);
}

/**
 * Los motivos con los que el servidor rechaza una recogida o una entrega: fuera de toda zona de servicio
 * (direcciones-E11), en una zona de otra plaza que la de la lavandería (direcciones-E12) o, al crear el pedido, con la
 * plaza de antes de un cambio de zonas (confirmacion-E15). Otro motivo que llegue se conserva tal cual.
 */
export type MotivoDeRechazo =
  | 'fuera_de_la_zona_de_servicio'
  | 'fuera_de_la_plaza_de_la_lavanderia'
  | 'plaza_de_la_direccion_desactualizada'
  | (string & {});

/** La recogida y la entrega que el servidor aceptó para un pedido con esa lavandería. */
export interface RecogidaYEntrega {
  recogida: Direccion;
  entrega: Direccion;
}

/** Por qué el servidor rechazó una dirección: su motivo y el mensaje que dio para la persona. */
export interface Rechazo {
  motivo: MotivoDeRechazo;
  mensaje?: string;
}

/** El servidor rechazó la recogida, la entrega o las dos, cada una con su motivo (direcciones-E11). */
export type FallaDeLaEleccion =
  | Falla
  | { ok: false; motivo: 'rechazadas'; rechazos: Partial<Record<ParaQueEsLaDireccion, Rechazo>> };

/**
 * POST de la recogida y la entrega elegidas para un pedido con esa lavandería, por sus id_publico. Sin entrega (null),
 * se entrega en la misma dirección de la recogida (direcciones-E09). El servidor no guarda nada y no geocodifica: usa el
 * punto guardado (direcciones-E07).
 */
export async function validarRecogidaYEntrega(
  token: string,
  eleccion: { lavanderia: string; recogida: string; entrega: string | null },
): Promise<{ ok: true; datos: RecogidaYEntrega } | FallaDeLaEleccion> {
  const resultado = await pedir<unknown>(CONTRATO_DE_DIRECCIONES.recogidaYEntrega, {
    metodo: 'POST',
    token,
    cuerpo: eleccion,
  });
  if (!resultado.ok) {
    const rechazos = resultado.motivo === 'invalido' ? rechazosDe(resultado.motivosPorCampo, resultado.errores) : null;
    return rechazos ? { ok: false, motivo: 'rechazadas', rechazos } : resultado;
  }
  const datos = esObjeto(resultado.datos) ? resultado.datos : {};
  const recogida = leerDireccion(datos.recogida);
  const entrega = leerDireccion(datos.entrega);
  return recogida && entrega ? { ok: true, datos: { recogida, entrega } } : { ok: false, motivo: 'servidor' };
}

/**
 * Los motivos de la recogida y de la entrega, con su mensaje, si el servidor dio alguno; null si no dio ninguno. Los
 * da POST /api/direcciones/recogida-y-entrega y, con la misma forma, POST /api/pedidos (confirmacion-E08 y E15).
 */
export function rechazosDe(
  motivos: Record<string, string> | undefined,
  errores: Record<string, string[]>,
): Partial<Record<ParaQueEsLaDireccion, Rechazo>> | null {
  const rechazos: Partial<Record<ParaQueEsLaDireccion, Rechazo>> = {};
  for (const para of ['recogida', 'entrega'] as const) {
    const motivo = motivos?.[para];
    if (motivo) {
      rechazos[para] = { motivo, mensaje: errores[para]?.[0] };
    }
  }
  return Object.keys(rechazos).length > 0 ? rechazos : null;
}

/** Una dirección leída campo por campo; null si no es del contrato. */
export function leerDireccion(fila: unknown): Direccion | null {
  if (
    !esObjeto(fila) ||
    typeof fila.id_publico !== 'string' ||
    typeof fila.es_favorita !== 'boolean' ||
    typeof fila.colonia !== 'string'
  ) {
    return null;
  }
  const latitud = numero(fila.latitud);
  const longitud = numero(fila.longitud);
  const textos = CAMPOS_DE_TEXTO.map((campo) => textoOVacio(fila[campo]));
  if (latitud === null || longitud === null || textos.some((texto) => texto === undefined)) {
    return null;
  }
  const [alias, calle, numero_exterior, numero_interior, codigo_postal, municipio, estado, referencias] = textos as (
    | string
    | null
  )[];
  return {
    id_publico: fila.id_publico,
    alias,
    es_favorita: fila.es_favorita,
    calle,
    numero_exterior,
    numero_interior,
    colonia: fila.colonia,
    codigo_postal,
    municipio,
    estado,
    referencias,
    latitud,
    longitud,
  };
}

// Los campos de texto que pueden venir vacíos, en el orden en que leerDireccion los toma.
const CAMPOS_DE_TEXTO = [
  'alias',
  'calle',
  'numero_exterior',
  'numero_interior',
  'codigo_postal',
  'municipio',
  'estado',
  'referencias',
] as const;

/** Un municipio del territorio, con su estado, como lo da GET /api/catalogos/municipios (direcciones-E17). */
export interface Municipio {
  /** El que la app manda en POST /api/direcciones. */
  codigo: string;
  nombre: string;
  estado: { codigo: string; nombre: string };
}

/**
 * GET de los municipios del catálogo del territorio, con la sesión, en el orden del servidor: por el nombre del estado y
 * luego por el del municipio. Sin catálogo cargado, la lista viene vacía.
 */
export async function consultarMunicipios(token: string): Promise<Resultado<Municipio[]>> {
  const resultado = await pedir<unknown>(CONTRATO_DE_DIRECCIONES.municipios, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  return listaDelContrato(resultado.datos, 'municipios', leerMunicipio);
}

/** Una colonia del catálogo, con su municipio, como la da GET /api/codigos-postales/{codigo_postal}/colonias. */
export interface ColoniaDelCatalogo {
  nombre: string;
  /** El tipo de asentamiento: Colonia, Fraccionamiento, Barrio… Distingue dos con el mismo nombre. */
  tipo: string;
  municipio: { codigo: string; nombre: string };
}

/**
 * GET de las colonias de un código postal de cinco números, con la sesión, ordenadas por nombre (direcciones-E19). Un
 * código postal que no está en el catálogo da la lista vacía, y no es un error: la colonia se escribe a mano
 * (direcciones-E20).
 */
export async function consultarColonias(token: string, codigoPostal: string): Promise<Resultado<ColoniaDelCatalogo[]>> {
  const resultado = await pedir<unknown>(CONTRATO_DE_DIRECCIONES.colonias(codigoPostal), { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  return listaDelContrato(resultado.datos, 'colonias', leerColonia);
}

// La lista de una respuesta, leída fila por fila. Una respuesta fuera del contrato no se pinta a medias: cuenta como
// falla del servidor.
function listaDelContrato<T>(datos: unknown, clave: string, leer: (fila: unknown) => T | null): Resultado<T[]> {
  const filas = esObjeto(datos) ? datos[clave] : undefined;
  const leidas = Array.isArray(filas) ? filas.map(leer) : null;
  return leidas && leidas.every((fila) => fila !== null)
    ? { ok: true, datos: leidas as T[] }
    : { ok: false, motivo: 'servidor' };
}

// Un código con su nombre, como el estado de un municipio o el municipio de una colonia; null si no es del contrato.
function leerCodigoYNombre(valor: unknown): { codigo: string; nombre: string } | null {
  return esObjeto(valor) && typeof valor.codigo === 'string' && typeof valor.nombre === 'string'
    ? { codigo: valor.codigo, nombre: valor.nombre }
    : null;
}

function leerMunicipio(fila: unknown): Municipio | null {
  const municipio = leerCodigoYNombre(fila);
  const estado = esObjeto(fila) ? leerCodigoYNombre(fila.estado) : null;
  return municipio && estado ? { ...municipio, estado } : null;
}

function leerColonia(fila: unknown): ColoniaDelCatalogo | null {
  if (!esObjeto(fila) || typeof fila.nombre !== 'string' || typeof fila.tipo !== 'string') {
    return null;
  }
  const municipio = leerCodigoYNombre(fila.municipio);
  return municipio ? { nombre: fila.nombre, tipo: fila.tipo, municipio } : null;
}

// Texto o null; cualquier otra cosa no es del contrato (undefined).
function textoOVacio(valor: unknown): string | null | undefined {
  return valor === null || typeof valor === 'string' ? valor : undefined;
}
