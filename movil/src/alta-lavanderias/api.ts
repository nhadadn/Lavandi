// La API de la lavandería del socio, el Módulo B (openspec/changes/alta-de-lavanderias, "El contrato de la API"; el
// mismo contrato del comentario de servidor/app/Http/Controllers/Api/LavanderiaDelSocioController.php): sus lavanderías,
// para saber al entrar si abre el registro o los pedidos; su registro (alta-lavanderias-E01 a E03); su lavandería con
// sus ventanas actuales, y su edición (alta-lavanderias-E18 a E22). Todas van con la sesión de una cuenta de socio
// lavandería, y el servidor solo le da y le deja tocar la suya (alta-lavanderias-E20). Como las demás consultas, nunca
// lanza: devuelve un Resultado.
//
// El registro va en multipart, porque lleva la foto. La edición va en JSON, salvo que cambie la foto: entonces va en
// multipart por POST con _method=PATCH, porque PHP solo lee los archivos de un POST. Los servicios y las ventanas
// siempre van completos: los que no vienen dejan de ofrecerse.
import { pedir, pedirConEstado, type Falla, type Resultado } from '../configuracion/api';
import { agregarFoto } from '../configuracion/foto-del-formulario';
import { esObjeto } from '../configuracion/lectura';

/** El contrato con el servidor, en un solo lugar para ajustarlo. */
export const CONTRATO_DE_LA_LAVANDERIA_DEL_SOCIO = {
  /** GET: { "<lista>": LavanderiaDelSocio[] }; POST: el registro; GET y PATCH <ruta>/<id_publico>. */
  ruta: 'socio/lavanderias',
  lista: 'lavanderias',
  /** Lo que devuelven el registro, la lectura y la edición: { "<clave>": LavanderiaDelSocio }. */
  clave: 'lavanderia',
  /** El campo con que la edición con foto dice que es un PATCH. */
  metodo: '_method',
  /**
   * El campo de la foto. Su nombre y su tipo son los de toda foto que sube la app
   * (src/configuracion/foto-del-formulario.ts).
   */
  foto: 'foto',
} as const;

/** Un municipio del catálogo, como lo trae la dirección de la lavandería; el estado es su nombre. */
export interface MunicipioDeLaLavanderia {
  codigo: string;
  nombre: string;
  estado: string | null;
}

/** La dirección de la lavandería; el municipio es null en una lavandería de prueba sin él. */
export interface DireccionDeLaLavanderia {
  calle: string;
  numero: string;
  colonia: string;
  codigo_postal: string;
  municipio: MunicipioDeLaLavanderia | null;
}

/** Un servicio que ofrece, con su precio en pesos, como texto con dos decimales, y su unidad. */
export interface ServicioDeLaLavanderia {
  tipo: string;
  nombre: string;
  precio: string;
  unidad: { codigo: string; nombre: string };
}

/** Una ventana de disponibilidad: el día del 1, el lunes, al 7, el domingo, y las horas HH:MM de la plaza. */
export interface VentanaDeLaLavanderia {
  dia_semana: number;
  hora_inicio: string;
  hora_fin: string;
}

/** La lavandería de la cuenta, como sale en todas las respuestas. Sin su estado: lo que ve suspendida es DD-104. */
export interface LavanderiaDelSocio {
  id_publico: string;
  nombre: string;
  direccion: DireccionDeLaLavanderia;
  /** La URL firmada vence; al volver a pedir la lavandería llega otra. null si no tiene. */
  foto: { url: string; vence_en: string } | null;
  servicios: ServicioDeLaLavanderia[];
  ventanas: VentanaDeLaLavanderia[];
}

/** Lo que la app manda: los cinco datos del registro (Módulo B #1), cada uno como lo pide la API. */
export interface DatosDeLaLavanderia {
  nombre: string;
  /** Las cinco partes; el municipio es el código del catálogo (GET /api/catalogos/municipios). */
  direccion: { calle: string; numero: string; colonia: string; codigo_postal: string; municipio: string };
  /** La foto ya comprimida, en el teléfono; null si no hay. */
  foto: { uri: string } | null;
  servicios: { tipo: string; precio: string }[];
  ventanas: VentanaDeLaLavanderia[];
}

/** Por qué no salió un envío: además de las fallas generales, el 409 de la cuenta que ya tiene su lavandería. */
export type FallaDelEnvio = Falla | { ok: false; motivo: 'conflicto'; mensaje?: string };

export type ResultadoDelEnvio = { ok: true; datos: LavanderiaDelSocio } | FallaDelEnvio;

/** GET de las lavanderías de la cuenta: hoy, a lo más una; vacía si todavía no registra la suya. */
export async function consultarLavanderiasDelSocio(token: string): Promise<Resultado<LavanderiaDelSocio[]>> {
  const { ruta, lista } = CONTRATO_DE_LA_LAVANDERIA_DEL_SOCIO;
  const resultado = await pedir<unknown>(ruta, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[lista] : null;
  const lavanderias = Array.isArray(filas) ? filas.map(leerLavanderiaDelSocio) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (lavanderias === null || !lavanderias.every((lavanderia) => lavanderia !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: lavanderias as LavanderiaDelSocio[] };
}

/** GET de su lavandería, con su información y sus ventanas actuales (alta-lavanderias-E19). */
export async function consultarLavanderiaDelSocio(
  token: string,
  idPublico: string,
): Promise<Resultado<LavanderiaDelSocio>> {
  const resultado = await pedir<unknown>(rutaDe(idPublico), { metodo: 'GET', token });
  return resultado.ok ? lavanderiaDeLaRespuesta(resultado.datos) : resultado;
}

/** POST del registro, en multipart con la foto (alta-lavanderias-E03). */
export async function registrarLavanderia(token: string, datos: DatosDeLaLavanderia): Promise<ResultadoDelEnvio> {
  return enviar(CONTRATO_DE_LA_LAVANDERIA_DEL_SOCIO.ruta, { metodo: 'POST', token, formulario: formularioDe(datos) });
}

/**
 * PATCH de lo que cambió de su lavandería (alta-lavanderias-E19): solo van los datos que vienen. Con foto nueva, en
 * multipart por POST con _method=PATCH; sin ella, en JSON.
 */
export async function editarLavanderia(
  token: string,
  idPublico: string,
  cambios: Partial<DatosDeLaLavanderia>,
): Promise<ResultadoDelEnvio> {
  const ruta = rutaDe(idPublico);
  if (cambios.foto) {
    return enviar(ruta, { metodo: 'POST', token, formulario: formularioDe(cambios, 'PATCH') });
  }
  return enviar(ruta, { metodo: 'PATCH', token, cuerpo: limpio(cambios) });
}

function rutaDe(idPublico: string): string {
  return `${CONTRATO_DE_LA_LAVANDERIA_DEL_SOCIO.ruta}/${encodeURIComponent(idPublico)}`;
}

async function enviar(ruta: string, peticion: Parameters<typeof pedirConEstado>[1]): Promise<ResultadoDelEnvio> {
  const { resultado, estado } = await pedirConEstado<unknown>(ruta, peticion);
  if (!resultado.ok) {
    if (estado === 409) {
      return { ok: false, motivo: 'conflicto', mensaje: 'mensaje' in resultado ? resultado.mensaje : undefined };
    }
    return resultado;
  }
  return lavanderiaDeLaRespuesta(resultado.datos);
}

function lavanderiaDeLaRespuesta(datos: unknown): Resultado<LavanderiaDelSocio> {
  const lavanderia = esObjeto(datos) ? leerLavanderiaDelSocio(datos[CONTRATO_DE_LA_LAVANDERIA_DEL_SOCIO.clave]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return lavanderia ? { ok: true, datos: lavanderia } : { ok: false, motivo: 'servidor' };
}

// Los datos como viajan, con cada texto sin los espacios de las orillas. Lo vacío viaja vacío: el servidor dice que
// falta, también en la edición, donde un campo que no viaja no cambia. La foto no: solo viaja en multipart.
function limpio(datos: Partial<DatosDeLaLavanderia>): Record<string, unknown> {
  const cuerpo: Record<string, unknown> = {};
  if (datos.nombre !== undefined) {
    cuerpo.nombre = datos.nombre.trim();
  }
  if (datos.direccion) {
    cuerpo.direccion = Object.fromEntries(
      Object.entries(datos.direccion).map(([parte, valor]) => [parte, valor.trim()]),
    );
  }
  if (datos.servicios) {
    cuerpo.servicios = datos.servicios.map(({ tipo, precio }) => ({ tipo, precio: precio.trim() }));
  }
  if (datos.ventanas) {
    cuerpo.ventanas = datos.ventanas;
  }
  return cuerpo;
}

/**
 * Los datos en un formulario multipart, con los nombres que lee Laravel: "direccion[calle]", "servicios[0][tipo]",
 * "ventanas[0][dia_semana]". La foto va con sus bytes, como la arma src/configuracion/foto-del-formulario.ts.
 */
function formularioDe(datos: Partial<DatosDeLaLavanderia>, metodo?: 'PATCH'): FormData {
  const formulario = new FormData();
  const { foto: campoDeLaFoto, metodo: campoDelMetodo } = CONTRATO_DE_LA_LAVANDERIA_DEL_SOCIO;
  if (metodo) {
    formulario.append(campoDelMetodo, metodo);
  }
  for (const [campo, valor] of Object.entries(limpio(datos))) {
    agregar(formulario, campo, valor);
  }
  if (datos.foto) {
    agregarFoto(formulario, campoDeLaFoto, datos.foto);
  }
  return formulario;
}

// Un valor del cuerpo en sus campos de formulario: los objetos y las listas, con corchetes.
function agregar(formulario: FormData, campo: string, valor: unknown) {
  if (typeof valor === 'object' && valor !== null) {
    for (const [parte, interior] of Object.entries(valor)) {
      agregar(formulario, `${campo}[${parte}]`, interior);
    }
    return;
  }
  formulario.append(campo, String(valor));
}

/** La lavandería leída campo por campo; null si no es del contrato. */
export function leerLavanderiaDelSocio(datos: unknown): LavanderiaDelSocio | null {
  if (!esObjeto(datos) || typeof datos.id_publico !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  const direccion = leerDireccion(datos.direccion);
  const foto = datos.foto === null ? null : leerFoto(datos.foto);
  const servicios = Array.isArray(datos.servicios) ? datos.servicios.map(leerServicio) : null;
  const ventanas = Array.isArray(datos.ventanas) ? datos.ventanas.map(leerVentana) : null;
  if (
    !direccion ||
    foto === undefined ||
    !servicios ||
    !servicios.every((servicio) => servicio !== null) ||
    !ventanas ||
    !ventanas.every((ventana) => ventana !== null)
  ) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    nombre: datos.nombre,
    direccion,
    foto,
    servicios: servicios as ServicioDeLaLavanderia[],
    ventanas: ventanas as VentanaDeLaLavanderia[],
  };
}

// Las partes de la dirección pueden venir vacías en una lavandería de prueba: se leen como texto vacío.
function leerDireccion(datos: unknown): DireccionDeLaLavanderia | null {
  if (!esObjeto(datos)) {
    return null;
  }
  const partes = (['calle', 'numero', 'colonia', 'codigo_postal'] as const).map((parte) => datos[parte]);
  if (!partes.every((parte) => parte === null || parte === undefined || typeof parte === 'string')) {
    return null;
  }
  const [calle, numero, colonia, codigoPostal] = partes as (string | null | undefined)[];
  const municipio = datos.municipio === null || datos.municipio === undefined ? null : leerMunicipio(datos.municipio);
  if (municipio === undefined) {
    return null;
  }
  return { calle: calle ?? '', numero: numero ?? '', colonia: colonia ?? '', codigo_postal: codigoPostal ?? '', municipio };
}

// El municipio, o undefined si no es del contrato.
function leerMunicipio(datos: unknown): MunicipioDeLaLavanderia | undefined {
  if (!esObjeto(datos) || typeof datos.codigo !== 'string' || typeof datos.nombre !== 'string') {
    return undefined;
  }
  return { codigo: datos.codigo, nombre: datos.nombre, estado: typeof datos.estado === 'string' ? datos.estado : null };
}

// La foto, o undefined si no es del contrato (null ya se leyó antes: no tiene).
function leerFoto(datos: unknown): LavanderiaDelSocio['foto'] | undefined {
  if (!esObjeto(datos) || typeof datos.url !== 'string' || typeof datos.vence_en !== 'string') {
    return undefined;
  }
  return { url: datos.url, vence_en: datos.vence_en };
}

function leerServicio(datos: unknown): ServicioDeLaLavanderia | null {
  if (
    !esObjeto(datos) ||
    typeof datos.tipo !== 'string' ||
    typeof datos.nombre !== 'string' ||
    typeof datos.precio !== 'string' ||
    !esObjeto(datos.unidad) ||
    typeof datos.unidad.codigo !== 'string' ||
    typeof datos.unidad.nombre !== 'string'
  ) {
    return null;
  }
  return {
    tipo: datos.tipo,
    nombre: datos.nombre,
    precio: datos.precio,
    unidad: { codigo: datos.unidad.codigo, nombre: datos.unidad.nombre },
  };
}

const HORA = /^\d{2}:\d{2}$/;

function leerVentana(datos: unknown): VentanaDeLaLavanderia | null {
  if (
    !esObjeto(datos) ||
    typeof datos.dia_semana !== 'number' ||
    typeof datos.hora_inicio !== 'string' ||
    typeof datos.hora_fin !== 'string' ||
    !HORA.test(datos.hora_inicio) ||
    !HORA.test(datos.hora_fin)
  ) {
    return null;
  }
  return { dia_semana: datos.dia_semana, hora_inicio: datos.hora_inicio, hora_fin: datos.hora_fin };
}
