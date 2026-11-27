// La gestión de usuarios de la administración en la app (F-D03; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
// #3, "Consulta de usuarios activos, historial y bloqueo"; administracion-app-E24 y E25): la lista de las cuentas de
// usuario, la ficha de cada una con su historial de pedidos, y el bloqueo y su levantamiento. Va por el cliente de la
// administración (src/administracion/api.ts), que pone el token, cierra la sesión con un 401 y entrega el 404 como que
// no existe. Como las demás consultas, nunca lanza: devuelve un Resultado.
//
// El contrato es el del comentario de servidor/app/Http/Controllers/Api/Administracion/UsuariosController.php, copiado
// aquí al pie de la letra para leerlo sin abrir el servidor. Todas las rutas llevan el token de administración y pasan
// por el componente de autorización:
//
//     Authorization: Bearer <token de administración>     (auth:sanctum y autorizar:administracion)
//
//     401  { "message": "Unauthenticated." }            sin sesión, o con el token vencido o revocado
//     403  { "message": "Esta cuenta no tiene acceso." }  la cuenta no es de administración, o su token no lleva la
//                                                         habilidad administracion (administracion-app-E06 y E07)
//     404  { "message": "No existe el usuario." }         la cuenta no existe, no es de usuario (la de un socio o la de
//                                                         la administración) o el valor no es un ULID: todas responden
//                                                         igual y sin ningún dato (administracion-app-E12; ADR-0019)
//
// La cuenta, como sale en la lista:
//
//     {
//       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",   el ULID de la cuenta
//       "correo": "persona@ejemplo.com",
//       "nombre": "Ana" | null,                       null si todavía no lo escribe
//       "estado": "activo" | "bloqueado"
//     }
//
// Y en su ficha, con su teléfono y su historial:
//
//     {
//       "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
//       "correo": "persona@ejemplo.com",
//       "nombre": "Ana" | null,
//       "telefono": "6181234567" | null,
//       "estado": "activo" | "bloqueado",
//       "historial": [                                sus pedidos confirmados, del más reciente al más antiguo; vacía
//         {                                           si no tiene. Uno que espera su pago no va (C-188)
//           "folio": "7KQ2M9XA",
//           "fecha": "2026-12-03",                    el día de la recolección, en la hora de la plaza
//           "lavanderia": "Lavandería del centro",    su nombre
//           "estado": "En proceso",                   el estado operativo, como lo nombra la administración (ADR-0021)
//           "monto": "1180.50" | null                 en pesos: el final si ya se pesó y si no el estimado; null si no
//         }                                           tiene ninguno, como hoy (DD-012, DD-010)
//       ]
//     }
//
//     GET /api/administracion/usuarios                     La lista de las cuentas de usuario (administracion-app-E24)
//
//     ?buscar=ana     opcional: el texto se busca dentro del correo o del nombre, sin distinguir mayúsculas y sin los
//                     espacios de las orillas; un % o un _ se buscan como letras. Sin texto, o si no es texto, la
//                     lista completa
//     ?pagina=2       opcional: la página, de 1 en adelante; si no es un número, la primera
//
//     200  {
//            "usuarios": [ { ...la cuenta... } ],      por correo; vacía si no hay o si la página ya no tiene cuentas
//            "pagina": {
//              "actual": 2,
//              "ultima": 3,                          la última página con cuentas; 1 si no hay ninguna
//              "por_pagina": 25,                     cuántas cuentas trae cada página
//              "total": 61                           cuántas cuentas hay con esa búsqueda, en todas las páginas
//            }
//          }
//          Las cuentas con el rol de usuario, cualquiera que sea la plaza de sus pedidos. Las de los socios se gestionan
//          con su lavandería y su repartidor (Módulo D #1 y #2), y las de la administración no se listan.
//
//     GET /api/administracion/usuarios/{id_publico}        La ficha de la cuenta, con su historial
//                                                          (administracion-app-E24, con la regla de administracion-E01)
//
//     200  { "usuario": { ...la cuenta, con su teléfono y su historial... } }
//     404  Como en todas las rutas.
//
//     POST /api/administracion/usuarios/{id_publico}/bloqueo     Bloquea la cuenta (administracion-app-E25, con la regla
//                                                                de administracion-E03)
//
//     Sin cuerpo.
//
//     200  { "usuario": { ...su ficha, con "estado" en "bloqueado"... } }
//          Se guarda la hora del bloqueo y se cierran todas sus sesiones: la siguiente petición de la app con cualquiera
//          de ellas responde 401, y mientras siga bloqueada no entra con su correo ni con Google. Sus pedidos no cambian
//          (DD-016). Una cuenta ya bloqueada se queda como está, con su hora de bloqueo.
//     404  Como en todas las rutas. Nada cambia.
//
//     DELETE /api/administracion/usuarios/{id_publico}/bloqueo   Levanta el bloqueo (administracion-app-E25, con la
//                                                                regla de administracion-E04)
//
//     200  { "usuario": { ...su ficha, con "estado" en "activo"... } }
//          Vuelve a entrar con su contraseña o con Google. Una cuenta activa se queda activa.
//     404  Como en todas las rutas. Nada cambia.
//
// No hay rutas para dar de alta, editar ni borrar usuarios: el Módulo D #3 dice consulta, historial y bloqueo. El filtro
// de usuarios activos espera DD-111 (administracion-app-E26), y lo que pasa con un pedido activo al bloquear, DD-016
// (administracion-app-E27).
import { esObjeto } from '../../configuracion/lectura';
import type { PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';

/** Las rutas y los campos del contrato, en un solo lugar para ajustarlo. */
export const CONTRATO_DE_LOS_USUARIOS = {
  ruta: 'usuarios',
  buscar: 'buscar',
  pagina: 'pagina',
  bloqueo: 'bloqueo',
} as const;

/** Los dos estados de una cuenta de usuario. */
export const ESTADOS_DEL_USUARIO = ['activo', 'bloqueado'] as const;
export type EstadoDelUsuario = (typeof ESTADOS_DEL_USUARIO)[number];

/** Una cuenta de usuario como sale en la lista. */
export interface UsuarioDeLaLista {
  id_publico: string;
  correo: string;
  /** null si todavía no lo escribe. */
  nombre: string | null;
  estado: EstadoDelUsuario;
}

/** En qué página va la lista y cuántas hay. */
export interface PaginaDeLaLista {
  actual: number;
  /** La última página con cuentas; 1 si no hay ninguna. */
  ultima: number;
  por_pagina: number;
  /** Cuántas cuentas hay con esa búsqueda, en todas las páginas. */
  total: number;
}

export interface ListaDeUsuarios {
  usuarios: UsuarioDeLaLista[];
  pagina: PaginaDeLaLista;
}

/** Un pedido del historial de la ficha, como lo da la API. */
export interface PedidoDelUsuario {
  folio: string;
  /** AAAA-MM-DD: el día de la recolección en la hora de la plaza, que se muestra tal cual, sin pasarlo a otra zona. */
  fecha: string;
  /** El nombre de su lavandería. */
  lavanderia: string;
  /** El estado operativo, ya nombrado por el servidor como lo nombra la administración (ADR-0021). */
  estado: string;
  /** En pesos, con sus decimales, tal como llega; null si todavía no tiene ninguno (DD-012, DD-010). */
  monto: string | null;
}

/** La ficha de la cuenta: la de la lista, con su teléfono y su historial del más reciente al más antiguo. */
export interface FichaDelUsuario extends UsuarioDeLaLista {
  telefono: string | null;
  historial: PedidoDelUsuario[];
}

/** Lo que pide la lista: el texto de la búsqueda, tal como se escribió, y la página, de 1 en adelante. */
export interface ConsultaDeLaLista {
  buscar: string;
  pagina: number;
}

const FECHA = /^\d{4}-\d{2}-\d{2}$/;
const MONTO = /^\d+(\.\d+)?$/;

/**
 * GET de la lista de cuentas de usuario, con la búsqueda y la página (administracion-app-E24). Sin texto no se manda la
 * búsqueda, y la primera página no se nombra.
 */
export async function consultarUsuarios(
  pedir: PedirALaAdministracion,
  { buscar, pagina }: ConsultaDeLaLista,
): Promise<ResultadoDeLaAdministracion<ListaDeUsuarios>> {
  const { ruta } = CONTRATO_DE_LOS_USUARIOS;
  const parametros: string[] = [];
  const texto = buscar.trim();
  if (texto !== '') {
    parametros.push(`${CONTRATO_DE_LOS_USUARIOS.buscar}=${encodeURIComponent(texto)}`);
  }
  if (pagina > 1) {
    parametros.push(`${CONTRATO_DE_LOS_USUARIOS.pagina}=${pagina}`);
  }
  const resultado = await pedir<unknown>(parametros.length > 0 ? `${ruta}?${parametros.join('&')}` : ruta);
  if (!resultado.ok) {
    return resultado;
  }
  const lista = leerLista(resultado.datos);
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return lista ? { ok: true, datos: lista } : { ok: false, motivo: 'servidor' };
}

/** GET de la ficha de la cuenta, con su historial (administracion-app-E24); 404 si no existe (administracion-app-E12). */
export async function consultarUsuario(
  pedir: PedirALaAdministracion,
  idPublico: string,
): Promise<ResultadoDeLaAdministracion<FichaDelUsuario>> {
  return conLaFicha(await pedir<unknown>(rutaDelUsuario(idPublico)));
}

/** POST del bloqueo de la cuenta (administracion-app-E25): responde su ficha, ya bloqueada. */
export async function bloquearAlUsuario(
  pedir: PedirALaAdministracion,
  idPublico: string,
): Promise<ResultadoDeLaAdministracion<FichaDelUsuario>> {
  return conLaFicha(await pedir<unknown>(rutaDelBloqueo(idPublico), { metodo: 'POST' }));
}

/** DELETE del bloqueo de la cuenta (administracion-app-E25): responde su ficha, ya activa. */
export async function levantarElBloqueo(
  pedir: PedirALaAdministracion,
  idPublico: string,
): Promise<ResultadoDeLaAdministracion<FichaDelUsuario>> {
  return conLaFicha(await pedir<unknown>(rutaDelBloqueo(idPublico), { metodo: 'DELETE' }));
}

function rutaDelUsuario(idPublico: string): string {
  return `${CONTRATO_DE_LOS_USUARIOS.ruta}/${encodeURIComponent(idPublico)}`;
}

function rutaDelBloqueo(idPublico: string): string {
  return `${rutaDelUsuario(idPublico)}/${CONTRATO_DE_LOS_USUARIOS.bloqueo}`;
}

// { "usuario": ficha } en la ficha leída, o la falla como llegó; fuera del contrato, falla del servidor.
function conLaFicha(
  resultado: ResultadoDeLaAdministracion<unknown>,
): ResultadoDeLaAdministracion<FichaDelUsuario> {
  if (!resultado.ok) {
    return resultado;
  }
  const ficha = esObjeto(resultado.datos) ? leerFicha(resultado.datos.usuario) : null;
  return ficha ? { ok: true, datos: ficha } : { ok: false, motivo: 'servidor' };
}

function leerLista(datos: unknown): ListaDeUsuarios | null {
  if (!esObjeto(datos) || !Array.isArray(datos.usuarios)) {
    return null;
  }
  const usuarios = datos.usuarios.map(leerUsuario);
  const pagina = leerPagina(datos.pagina);
  if (pagina === null || !usuarios.every((usuario) => usuario !== null)) {
    return null;
  }
  return { usuarios: usuarios as UsuarioDeLaLista[], pagina };
}

function leerPagina(datos: unknown): PaginaDeLaLista | null {
  if (!esObjeto(datos)) {
    return null;
  }
  const { actual, ultima, por_pagina: porPagina, total } = datos;
  if (![actual, ultima, porPagina, total].every((valor) => Number.isInteger(valor))) {
    return null;
  }
  return { actual: actual as number, ultima: ultima as number, por_pagina: porPagina as number, total: total as number };
}

function leerUsuario(datos: unknown): UsuarioDeLaLista | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    typeof datos.correo !== 'string' ||
    !textoOVacio(datos.nombre) ||
    !ESTADOS_DEL_USUARIO.includes(datos.estado as EstadoDelUsuario)
  ) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    correo: datos.correo,
    nombre: datos.nombre,
    estado: datos.estado as EstadoDelUsuario,
  };
}

function leerFicha(datos: unknown): FichaDelUsuario | null {
  const usuario = leerUsuario(datos);
  if (usuario === null || !esObjeto(datos) || !textoOVacio(datos.telefono) || !Array.isArray(datos.historial)) {
    return null;
  }
  const historial = datos.historial.map(leerPedido);
  if (!historial.every((pedido) => pedido !== null)) {
    return null;
  }
  return { ...usuario, telefono: datos.telefono, historial: historial as PedidoDelUsuario[] };
}

function leerPedido(datos: unknown): PedidoDelUsuario | null {
  if (
    !esObjeto(datos) ||
    typeof datos.folio !== 'string' ||
    typeof datos.fecha !== 'string' ||
    !FECHA.test(datos.fecha) ||
    typeof datos.lavanderia !== 'string' ||
    typeof datos.estado !== 'string' ||
    !(datos.monto === null || (typeof datos.monto === 'string' && MONTO.test(datos.monto)))
  ) {
    return null;
  }
  return {
    folio: datos.folio,
    fecha: datos.fecha,
    lavanderia: datos.lavanderia,
    estado: datos.estado,
    monto: datos.monto,
  };
}

/** Un texto o null. */
function textoOVacio(valor: unknown): valor is string | null {
  return valor === null || typeof valor === 'string';
}
