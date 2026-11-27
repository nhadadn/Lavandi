// La API de la gestión de lavanderías de la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1,
// "Alta, baja, suspensión y edición de socios."; openspec/changes/administracion-en-la-app/, tareas 4.1 a 4.3; design.md,
// sección 4, F-D01). Todas las peticiones pasan por el cliente del módulo (src/administracion/api.ts): el 401 borra la
// sesión y vuelve al inicio de sesión, el 404 sale como que no existe, el 409 como conflicto con su mensaje y el 422 con
// el error de cada campo. Como las demás consultas, nunca lanza: devuelve un Resultado.
//
// La app no valida ni decide nada por su cuenta: las transiciones que ofrece la ficha son las que da el servidor, y los
// errores de la edición son los suyos. La ficha trae lo mismo que ve y edita el socio de su lavandería, así que se lee y
// se edita con las piezas del Módulo B (src/alta-lavanderias/api.ts): leerLavanderiaDelSocio() y peticionDeLaEdicion().
// Trae además la marca de las fotos que se leyeron, que la edición devuelve tal cual con la lista de fotos.
//
// El contrato, copiado del comentario de servidor/app/Http/Controllers/Api/Administracion/LavanderiasController.php
// (tareas 3.1, 3.3 y 3.4). La URL de ejemplo de las fotos va como <URL firmada>: el código de la app no lleva
// direcciones escritas (registro-E28).
//
// Todas las rutas llevan la sesión de la administración, con su token de administración (ADR-0022, decisiones 7 y 8):
//
//     Authorization: Bearer <token de la sesión>        (auth:sanctum y autorizar:administracion)
//
//     401  { "message": "Unauthenticated." }              sin sesión, o con el token vencido o revocado
//     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o el token no lleva la
//                                                         habilidad administracion (administracion-app-E06 y E07)
//
// La lavandería en la ficha, como sale en todas las respuestas que la traen:
//
//     {
//       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",       el ULID de la lavandería; la API nunca usa otro
//       "nombre": "Lavandería Ejemplo",
//       "descripcion": "Entregamos el mismo día." | null,
//       "direccion": {
//         "calle": "Calle Ejemplo",
//         "numero": "123",
//         "colonia": "Centro",
//         "codigo_postal": "34000",
//         "municipio": { "codigo": "10005", "nombre": "Durango", "estado": "Durango" } | null
//       },
//       "foto": { "url": "<URL firmada>", "vence_en": "2026-11-22T18:00:00Z" } | null,
//       "fotos": [ { "url": "<URL firmada>", "vence_en": "2026-11-22T18:00:00Z" } ],
//       "maximo_de_fotos": 5,
//       "servicios": [
//         { "tipo": "estandar", "nombre": "Lavado estándar", "precio": "18.50",
//           "unidad": { "codigo": "kg", "nombre": "kilo" } }
//       ],
//       "ventanas": [ { "dia_semana": 4, "hora_inicio": "10:00", "hora_fin": "11:00" } ],
//                                                        hasta aquí, lo mismo que ve y edita su socio en la app, con
//                                                        el mismo significado: el contrato de
//                                                        App\Http\Controllers\Api\LavanderiaDelSocioController
//       "estado": "pendiente" | "activa" | "suspendida" | "baja",
//       "plaza": { "nombre": "Durango" },
//       "transiciones": ["suspender", "dar_de_baja"],    las que su estado permite ahora, en este orden, y solo esas:
//                                                          "suspender"           POST …/suspension
//                                                          "levantar_suspension" DELETE …/suspension
//                                                          "dar_de_baja"         POST …/baja
//                                                        Activa o pendiente: suspender y dar_de_baja. Suspendida:
//                                                        levantar_suspension y dar_de_baja. De baja: ninguna, porque
//                                                        ninguna sale de la baja (alta-lavanderias-E14, E15 y E17).
//       "fotos_leidas": "eyJpdiI6…"                     la marca de las fotos de esta lectura: un texto cifrado, que
//     }                                                  cambia en cada respuesta y que la app no lee; lo devuelve tal
//                                                        cual con la lista de fotos (PATCH, abajo)
//
// Una lavandería que no existe, está borrada o no se ve responde 404 sin ningún dato, igual en todas las rutas que la
// nombran; un valor que no es ULID también (administracion-app-E12; ADR-0019, decisión 3):
//
//     404  { "message": "No existe la lavandería." }
//
// ---------------------------------------------------------------------------------------------------------------------
//
//     GET /api/administracion/lavanderias        Todas las lavanderías, de todas las plazas y en cualquier estado
//                                                (administracion-app-E13)
//
//     Consulta, opcional:
//       buscar      texto: solo las que lo llevan en su nombre, sin distinguir mayúsculas. Vacío, todas.
//
//     200  { "lavanderias": [
//            { "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z", "nombre": "Lavandería Ejemplo", "estado": "activa",
//              "plaza": { "nombre": "Durango" } }
//          ] }
//          Por nombre. Sin páginas. Vacía si ninguna coincide.
//
// ---------------------------------------------------------------------------------------------------------------------
//
//     GET /api/administracion/lavanderias/{id_publico}     La ficha (administracion-app-E13)
//
//     200  { "lavanderia": { ...la lavandería en la ficha... } }
//     404  Como arriba.
//
// ---------------------------------------------------------------------------------------------------------------------
//
//     PATCH /api/administracion/lavanderias/{id_publico}   Edita sus datos (administracion-app-E14)
//
//     En cualquier estado, pendiente, activa, suspendida o de baja, como el panel, que ofrece "Editar" en toda
//     lavandería; la edición no cambia su estado ni sus transiciones.
//
//     Los mismos campos, con las mismas reglas y los mismos errores que PATCH /api/socio/lavanderias/{id_publico}
//     (alta-lavanderias-E13 y E31, y las validaciones del registro), y solo van los que cambian: nombre, direccion
//     completa, foto, servicios, ventanas, descripcion y fotos. Las fotos se nombran por su lugar en "fotos" de la
//     lectura de la ficha con que se abrió la edición (0, la primera), y con ellas va "fotos_leidas", la marca de esa
//     misma lectura, tal cual. Sin archivos, en JSON. Con foto o con una foto nueva en fotos, en multipart/form-data por
//     POST con el campo _method=PATCH, porque PHP solo lee los archivos de un POST:
//
//         JSON:       { "nombre": "Lavandería Nueva", "fotos": [2, 0], "fotos_leidas": "eyJpdiI6…" }
//         multipart:  _method=PATCH, fotos[0]=2, fotos[1]=<archivo>, fotos[2]=0, fotos_leidas=eyJpdiI6…
//
//     El servidor cuenta los lugares sobre las fotos de esa marca, como el panel sobre las que leyó al abrir la página
//     (administracion-app-E14; design.md, F-D01): si otra sesión cambió las fotos después de la lectura, por ejemplo su
//     socio desde su app, la lista se rechaza y nada cambia. Sin la marca, o con una que no dio el servidor, la lista
//     tampoco nombra las fotos que tiene la lavandería, y se rechaza igual. Sin la lista de fotos, la marca no cuenta.
//
//     200  { "lavanderia": { ...la lavandería en la ficha, ya con los cambios... } }
//     404  Como arriba. La lavandería se busca antes de validar.
//     422  { "message": "...",
//            "errors": { "nombre": ["Falta el nombre de la lavandería."], "servicios.0.precio": ["..."] } }
//          Un dato que no cumple las reglas, cada error en su campo; nada cambia. Los de la lista de fotos van en fotos
//          o en su lugar, fotos.N.
//     422  { "message": "Las fotos de la lavandería cambiaron desde que abriste la página. Vuelve a abrirla y haz el
//                        cambio otra vez.",
//            "errors": { "fotos": ["Las fotos de la lavandería cambiaron desde que abriste la página. Vuelve a abrirla y
//                                   haz el cambio otra vez."] } }
//          Las fotos de la lavandería ya no son las de "fotos_leidas", o la marca no vino o no la dio el servidor. El
//          mensaje es el de FotosDeLaLavanderia, el mismo del panel. Nada cambia; al volver a abrir la edición, la ficha
//          trae las fotos y la marca de ahora.
//     422  { "message": "...", "errors": { "direccion": ["..."] },
//            "motivo": "direccion_no_encontrada" | "fuera_de_la_zona_de_servicio" | "en_otra_plaza" }
//          La dirección nueva no se ubica, cae fuera de las zonas de servicio o en una zona de otra plaza: la
//          lavandería no cambia de plaza (alta-lavanderias-E10 y E25). Nada cambia.
//     503  { "message": "No pudimos ubicar la dirección. Inténtalo más tarde.",
//            "motivo": "geocodificacion_no_disponible" }
//          El servicio de geocodificación no respondió. Nada cambia.
//
// ---------------------------------------------------------------------------------------------------------------------
//
//     POST   /api/administracion/lavanderias/{id_publico}/suspension    La suspende (administracion-app-E15;
//                                                                       alta-lavanderias-E14)
//     DELETE /api/administracion/lavanderias/{id_publico}/suspension    Le levanta la suspensión: vuelve a activa si ya
//                                                                       tenía alta, a pendiente si no
//                                                                       (administracion-app-E15; alta-lavanderias-E15)
//     POST   /api/administracion/lavanderias/{id_publico}/baja          La da de baja: deja de aparecer a los usuarios y
//                                                                       de recibir pedidos, y sus datos y su historial
//                                                                       se quedan (administracion-app-E16;
//                                                                       alta-lavanderias-E17)
//
//     Sin cuerpo.
//
//     200  { "lavanderia": { ...la lavandería en la ficha, con su estado nuevo y sus transiciones... } }
//     404  Como arriba.
//     409  { "message": "Una lavandería en estado baja no se puede suspender." }
//          Su estado ya no permite el cambio, por ejemplo porque otra sesión la dio de baja. El mensaje es el de
//          EstadoDeLaLavanderia. Nada cambia; la app vuelve a pedir la ficha (administracion-app-E12).
import {
  leerLavanderiaDelSocio,
  peticionDeLaEdicion,
  type CambiosDeLaLavanderia,
  type LavanderiaDelSocio,
} from '../../alta-lavanderias/api';
import { esObjeto } from '../../configuracion/lectura';
import type { PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';

/** El contrato con el servidor, en un solo lugar para ajustarlo. */
export const CONTRATO_DE_LAS_LAVANDERIAS = {
  /** GET: { "<lista>": LavanderiaEnLaLista[] }; GET y PATCH <ruta>/<id_publico>. */
  ruta: 'lavanderias',
  lista: 'lavanderias',
  /** La búsqueda por nombre de la lista, en la consulta. */
  buscar: 'buscar',
  /** Lo que devuelven la ficha, la edición y cada cambio de estado: { "<clave>": LavanderiaDeLaAdministracion }. */
  clave: 'lavanderia',
  /** La marca de las fotos de la lectura: la trae la ficha y la edición la devuelve con la lista de fotos. */
  fotosLeidas: 'fotos_leidas',
} as const;

/** Los estados de una lavandería. */
export const ESTADOS_DE_LA_LAVANDERIA = ['pendiente', 'activa', 'suspendida', 'baja'] as const;
export type EstadoDeLaLavanderia = (typeof ESTADOS_DE_LA_LAVANDERIA)[number];

/** Cada transición que puede dar el servidor, con el método y la ruta que la hace, bajo la de su ficha. */
export const TRANSICIONES = {
  suspender: { metodo: 'POST', ruta: 'suspension' },
  levantar_suspension: { metodo: 'DELETE', ruta: 'suspension' },
  dar_de_baja: { metodo: 'POST', ruta: 'baja' },
} as const satisfies Record<string, { metodo: 'POST' | 'DELETE'; ruta: string }>;
export type Transicion = keyof typeof TRANSICIONES;

/** La plaza de la lavandería: solo su nombre. */
export interface PlazaDeLaLavanderia {
  nombre: string;
}

/** Una lavandería de la lista: su nombre, su estado y su plaza. */
export interface LavanderiaEnLaLista {
  id_publico: string;
  nombre: string;
  estado: EstadoDeLaLavanderia;
  plaza: PlazaDeLaLavanderia;
}

/** La ficha: lo que ve y edita el socio, más su estado, su plaza y las transiciones que permite ahora. */
export interface LavanderiaDeLaAdministracion extends LavanderiaDelSocio {
  estado: EstadoDeLaLavanderia;
  plaza: PlazaDeLaLavanderia;
  transiciones: Transicion[];
  /** La marca de las fotos de esta lectura: la app no la lee, solo la devuelve con la lista de fotos. */
  fotos_leidas: string;
}

/** GET de todas las lavanderías, con la búsqueda por nombre; sin texto, todas (administracion-app-E13). */
export async function consultarLavanderias(
  pedir: PedirALaAdministracion,
  buscar = '',
): Promise<ResultadoDeLaAdministracion<LavanderiaEnLaLista[]>> {
  const { ruta, lista, buscar: campo } = CONTRATO_DE_LAS_LAVANDERIAS;
  const texto = buscar.trim();
  const resultado = await pedir<unknown>(texto === '' ? ruta : `${ruta}?${campo}=${encodeURIComponent(texto)}`);
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[lista] : null;
  const lavanderias = Array.isArray(filas) ? filas.map(leerDeLaLista) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (lavanderias === null || !lavanderias.every((lavanderia) => lavanderia !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: lavanderias as LavanderiaEnLaLista[] };
}

/** GET de la ficha (administracion-app-E13). */
export async function consultarLavanderia(
  pedir: PedirALaAdministracion,
  idPublico: string,
): Promise<ResultadoDeLaAdministracion<LavanderiaDeLaAdministracion>> {
  return fichaDeLaRespuesta(await pedir<unknown>(rutaDe(idPublico)));
}

/**
 * Suspende, levanta la suspensión o da de baja (administracion-app-E15 y E16): la ficha con su estado nuevo y sus
 * transiciones, o el 409 si su estado ya no lo permite (administracion-app-E12).
 */
export async function cambiarElEstado(
  pedir: PedirALaAdministracion,
  idPublico: string,
  transicion: Transicion,
): Promise<ResultadoDeLaAdministracion<LavanderiaDeLaAdministracion>> {
  const { metodo, ruta } = TRANSICIONES[transicion];
  return fichaDeLaRespuesta(await pedir<unknown>(`${rutaDe(idPublico)}/${ruta}`, { metodo }));
}

/**
 * PATCH de lo que cambió (administracion-app-E14): la misma petición que la edición del socio, a la ruta de la
 * administración. Con una foto nueva, en multipart por POST con _method=PATCH; sin ella, en JSON. Con la lista de fotos
 * va la marca de la lectura con que se abrió la edición, tal cual: el servidor cuenta los lugares sobre esas fotos.
 */
export async function editarLavanderia(
  pedir: PedirALaAdministracion,
  idPublico: string,
  cambios: CambiosDeLaLavanderia,
  fotosLeidas: string,
): Promise<ResultadoDeLaAdministracion<LavanderiaDeLaAdministracion>> {
  const peticion = peticionDeLaEdicion(cambios);
  if (cambios.fotos) {
    const campo = CONTRATO_DE_LAS_LAVANDERIAS.fotosLeidas;
    if (peticion.formulario) {
      peticion.formulario.append(campo, fotosLeidas);
    } else if (esObjeto(peticion.cuerpo)) {
      peticion.cuerpo[campo] = fotosLeidas;
    }
  }
  return fichaDeLaRespuesta(await pedir<unknown>(rutaDe(idPublico), peticion));
}

function rutaDe(idPublico: string): string {
  return `${CONTRATO_DE_LAS_LAVANDERIAS.ruta}/${encodeURIComponent(idPublico)}`;
}

function fichaDeLaRespuesta(
  resultado: ResultadoDeLaAdministracion<unknown>,
): ResultadoDeLaAdministracion<LavanderiaDeLaAdministracion> {
  if (!resultado.ok) {
    return resultado;
  }
  const ficha = esObjeto(resultado.datos) ? leerLaFicha(resultado.datos[CONTRATO_DE_LAS_LAVANDERIAS.clave]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return ficha ? { ok: true, datos: ficha } : { ok: false, motivo: 'servidor' };
}

function esEstado(valor: unknown): valor is EstadoDeLaLavanderia {
  return ESTADOS_DE_LA_LAVANDERIA.some((estado) => estado === valor);
}

function esTransicion(valor: unknown): valor is Transicion {
  return typeof valor === 'string' && Object.prototype.hasOwnProperty.call(TRANSICIONES, valor);
}

function leerPlaza(datos: unknown): PlazaDeLaLavanderia | null {
  return esObjeto(datos) && typeof datos.nombre === 'string' ? { nombre: datos.nombre } : null;
}

/** Una lavandería de la lista, leída campo por campo; null si no es del contrato. */
function leerDeLaLista(datos: unknown): LavanderiaEnLaLista | null {
  if (!esObjeto(datos) || typeof datos.id_publico !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  const plaza = leerPlaza(datos.plaza);
  if (!esEstado(datos.estado) || !plaza) {
    return null;
  }
  return { id_publico: datos.id_publico, nombre: datos.nombre, estado: datos.estado, plaza };
}

/**
 * La ficha leída campo por campo: lo del socio, con su lectura, más el estado, la plaza, las transiciones y la marca de
 * las fotos leídas.
 */
function leerLaFicha(datos: unknown): LavanderiaDeLaAdministracion | null {
  const delSocio = leerLavanderiaDelSocio(datos);
  if (!delSocio || !esObjeto(datos)) {
    return null;
  }
  const plaza = leerPlaza(datos.plaza);
  const { estado, transiciones, fotos_leidas: fotosLeidas } = datos;
  if (
    !esEstado(estado) ||
    !plaza ||
    !Array.isArray(transiciones) ||
    !transiciones.every(esTransicion) ||
    typeof fotosLeidas !== 'string'
  ) {
    return null;
  }
  return { ...delSocio, estado, plaza, transiciones, fotos_leidas: fotosLeidas };
}
