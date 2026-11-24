// La API del Módulo C, del socio repartidor (openspec/changes/repartidor/design.md, "Contrato de la API"; el mismo
// contrato de los comentarios de servidor/app/Http/Controllers/Api/RepartidorController.php,
// PedidosDelRepartidorController.php y HistorialDelRepartidorController.php). Como las demás consultas, nunca lanza:
// devuelve un Resultado.
//
// El registro es público y va en multipart, porque lleva la foto; abre la sesión como el registro del usuario. Lo demás
// va con la sesión de una cuenta de repartidor. El perfil responde en cualquier estado; los pedidos, el historial y las
// estadísticas, solo si el repartidor opera: uno pendiente o suspendido recibe 403 con su estado (repartidor-E03 y E06).
import type { SesionNueva } from '../acceso/api';
import { leerVentana, type Ventana } from '../agenda/api';
import { pedir, pedirConEstado, type Falla, type Resultado } from '../configuracion/api';
import { agregarFoto } from '../configuracion/foto-del-formulario';
import { esObjeto, fotoFirmada, type FotoFirmada } from '../configuracion/lectura';
import { leerPedidoEntregado, type PedidoEntregado } from '../historial-lavanderia/api';
import { leerDireccionDelPedido, type DireccionDelPedido } from '../pedido/api';

/** El contrato con el servidor, en un solo lugar para ajustarlo. */
export const CONTRATO_DEL_REPARTIDOR = {
  /** POST, sin sesión y en multipart: { token, usuario, repartidor } (repartidor-E01 y E02). */
  registro: 'repartidor/registro',
  /** GET: { "<clave>": PerfilDelRepartidor }, en cualquier estado. */
  perfil: 'repartidor/perfil',
  clave: 'repartidor',
  /** GET: { "<lista>": PedidoDelRepartidor[] }, de la recolección más próxima a la más lejana (repartidor-E10). */
  pedidos: 'repartidor/pedidos',
  lista: 'pedidos',
  /** Lo que devuelven la lectura, aceptar y los botones: { "<pedido>": PedidoDelRepartidor }. */
  pedido: 'pedido',
  /** POST pedidos/<id_publico>/<aceptar>, sin cuerpo (repartidor-E15). */
  aceptar: 'aceptar',
  /** POST pedidos/<id_publico>/<rechazar>, con { "<motivo>": "…" }; 422 con errors.<motivo> (E16 y E17). */
  rechazar: 'rechazar',
  motivo: 'motivo',
  /** POST pedidos/<id_publico>/<estado>, con { "<estado>": siguiente.estado }; con Entregado, la <foto> (E19 y E23). */
  estado: 'estado',
  foto: 'foto',
  /** GET: { "<lista>": PedidoEntregado[] }, sus entregas completadas, de la más reciente a la más antigua (E25). */
  historial: 'repartidor/historial',
  /** GET: { "entregas_del_dia": n, "entregas_de_la_semana": n } (E28). */
  estadisticas: 'repartidor/estadisticas',
} as const;

/** Los campos del registro, con sus nombres en la API: los cinco datos del Módulo C #1 y la contraseña (ADR-0005). */
export const CAMPOS_DEL_REGISTRO = ['nombre', 'telefono', 'correo', 'password', 'foto', 'tipo_vehiculo'] as const;

/** El repartidor de la cuenta, como sale en el registro y en el perfil. */
export interface PerfilDelRepartidor {
  id_publico: string;
  nombre: string;
  telefono: string;
  correo: string;
  /** La URL firmada vence; al volver a pedir el perfil llega otra. null si no tiene. */
  foto: FotoFirmada | null;
  tipo_vehiculo: string;
  /** pendiente, activo o suspendido. */
  estado: string;
  /** true solo si está activo y con su plaza: ve y mueve pedidos. */
  puede_operar: boolean;
  /** La del alta; null mientras está pendiente. */
  plaza: { nombre: string } | null;
}

/** Lo que la persona manda al registrarse. */
export interface DatosDelRegistro {
  nombre: string;
  telefono: string;
  correo: string;
  password: string;
  tipo_vehiculo: string;
  /** La foto ya comprimida, en el teléfono; null si todavía no hay. */
  foto: { uri: string } | null;
}

/**
 * POST del registro, sin sesión y en multipart con la foto (repartidor-E01). Con el 201 llegan el token y la cuenta,
 * con el rol de repartidor, como en el registro del usuario: la sesión se abre igual. Sin alguno de los datos, el
 * servidor responde 422 con el error de cada campo y no crea la cuenta (repartidor-E02).
 */
export async function registrarRepartidor(datos: DatosDelRegistro): Promise<Resultado<SesionNueva>> {
  const resultado = await pedir<unknown>(CONTRATO_DEL_REPARTIDOR.registro, {
    metodo: 'POST',
    formulario: formularioDelRegistro(datos),
  });
  if (!resultado.ok) {
    return resultado;
  }
  const { datos: cuerpo } = resultado;
  // Una respuesta fuera del contrato no abre una sesión a medias: cuenta como falla del servidor.
  if (
    !esObjeto(cuerpo) ||
    typeof cuerpo.token !== 'string' ||
    !esObjeto(cuerpo.usuario) ||
    typeof cuerpo.usuario.id_publico !== 'string' ||
    typeof cuerpo.usuario.correo !== 'string'
  ) {
    return { ok: false, motivo: 'servidor' };
  }
  const { usuario } = cuerpo;
  return {
    ok: true,
    datos: {
      token: cuerpo.token,
      usuario: {
        id_publico: usuario.id_publico as string,
        correo: usuario.correo as string,
        rol: typeof usuario.rol === 'string' ? usuario.rol : null,
      },
    },
  };
}

// Los datos en un formulario multipart. Los textos van como se escribieron; la foto, con sus bytes, como la arma
// src/configuracion/foto-del-formulario.ts. Sin foto, no va: el servidor dice que falta.
function formularioDelRegistro({ foto, ...textos }: DatosDelRegistro): FormData {
  const formulario = new FormData();
  for (const [campo, valor] of Object.entries(textos)) {
    formulario.append(campo, valor);
  }
  if (foto) {
    agregarFoto(formulario, 'foto', foto);
  }
  return formulario;
}

/** GET del perfil del repartidor de la cuenta, con su estado y si ya opera (repartidor-E01 y E03). */
export async function consultarPerfilDelRepartidor(token: string): Promise<Resultado<PerfilDelRepartidor>> {
  const resultado = await pedir<unknown>(CONTRATO_DEL_REPARTIDOR.perfil, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const perfil = esObjeto(resultado.datos) ? leerPerfil(resultado.datos[CONTRATO_DEL_REPARTIDOR.clave]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return perfil ? { ok: true, datos: perfil } : { ok: false, motivo: 'servidor' };
}

/** El perfil leído campo por campo; null si no es del contrato. */
function leerPerfil(datos: unknown): PerfilDelRepartidor | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    typeof datos.nombre !== 'string' ||
    typeof datos.telefono !== 'string' ||
    typeof datos.correo !== 'string' ||
    typeof datos.tipo_vehiculo !== 'string' ||
    typeof datos.estado !== 'string' ||
    typeof datos.puede_operar !== 'boolean'
  ) {
    return null;
  }
  const foto = datos.foto === null ? null : fotoFirmada(datos.foto);
  const plaza =
    datos.plaza === null ? null : esObjeto(datos.plaza) && typeof datos.plaza.nombre === 'string' ? datos.plaza : undefined;
  if (foto === null && datos.foto !== null) {
    return null;
  }
  if (plaza === undefined) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    nombre: datos.nombre,
    telefono: datos.telefono,
    correo: datos.correo,
    foto,
    tipo_vehiculo: datos.tipo_vehiculo,
    estado: datos.estado,
    puede_operar: datos.puede_operar,
    plaza: plaza === null ? null : { nombre: plaza.nombre as string },
  };
}

/** El estado operativo del pedido (ADR-0021), con el nombre de la app del repartidor: "En tránsito" en en_entrega. */
export interface EstadoOperativo {
  codigo: string;
  nombre: string;
}

/** El único botón que el repartidor puede tocar: el estado al que lleva y su texto (Módulo C #3). */
export interface PasoSiguiente {
  estado: string;
  boton: string;
}

/** La lavandería del pedido, con su dirección; las partes pueden venir vacías en una lavandería de prueba. */
export interface LavanderiaDelPedido {
  id_publico: string;
  nombre: string;
  calle: string | null;
  numero: string | null;
  colonia: string | null;
  codigo_postal: string | null;
}

/** Un pedido como lo ve el repartidor al que se le ofreció. Sin datos de la cuenta que lo hizo ni montos (E26). */
export interface PedidoDelRepartidor {
  id_publico: string;
  folio: string;
  estado: EstadoOperativo;
  /** Su oferta: ofrecido mientras no responde, aceptado cuando la aceptó; null sin oferta. */
  oferta: string | null;
  /** true solo mientras la oferta espera su respuesta: la tarjeta ofrece Aceptar y Rechazar (repartidor-E15 y E16). */
  por_responder: boolean;
  /** null si no aceptó, si la lavandería todavía no acepta el pedido, entre Recolectado y Listo y tras Entregado. */
  siguiente: PasoSiguiente | null;
  /** La recolección, con la fecha y las horas ya en la hora de la plaza. */
  ventana: Ventana;
  recogida: DireccionDelPedido;
  /** Sin entrega distinta, la misma de la recogida. */
  entrega: DireccionDelPedido;
  lavanderia: LavanderiaDelPedido;
  /** Las fotos de evidencia del pedido, sin ruta ni URL: solo su tipo y su hora (repartidor-E23). */
  evidencias: { tipo: string; tomada_en: string }[];
}

/**
 * Por qué no salió una consulta o una acción del repartidor. Además de las fallas generales: el 403 del repartidor que
 * ya no opera, pendiente o suspendido (repartidor-E03 y E06), y el 409 de una acción que ya no es posible, los dos con
 * el mensaje del servidor. En los dos, el pedido no cambió.
 */
export type FallaDelRepartidor =
  | Falla
  | { ok: false; motivo: 'sin-acceso'; mensaje?: string }
  | { ok: false; motivo: 'conflicto'; mensaje?: string };

export type ResultadoDelRepartidor<T> = { ok: true; datos: T } | FallaDelRepartidor;

/** GET de sus pedidos en curso, la lista en tiempo real, con la sesión (repartidor-E10 y E11). */
export async function consultarPedidosDelRepartidor(
  token: string,
): Promise<ResultadoDelRepartidor<PedidoDelRepartidor[]>> {
  const { pedidos: ruta, lista } = CONTRATO_DEL_REPARTIDOR;
  const resultado = await pedirDelRepartidor(ruta, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[lista] : null;
  const pedidos = Array.isArray(filas) ? filas.map(leerPedidoDelRepartidor) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (pedidos === null || !pedidos.every((pedido) => pedido !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: pedidos as PedidoDelRepartidor[] };
}

/** POST de aceptar la oferta que espera su respuesta (repartidor-E15). El estado del pedido no cambia. */
export function aceptarPedidoDelRepartidor(
  token: string,
  idPublico: string,
): Promise<ResultadoDelRepartidor<PedidoDelRepartidor>> {
  return mandarAccion(rutaDeLaAccion(idPublico, CONTRATO_DEL_REPARTIDOR.aceptar), { metodo: 'POST', token });
}

/**
 * POST de rechazar la oferta con su motivo, sin los espacios de las orillas (repartidor-E16). El servidor exige el
 * motivo y su largo máximo vive en su configuración: lo que no acepta lo dice con 422 en el campo (repartidor-E17). La
 * respuesta es la oferta rechazada, no el pedido, que deja de estar asignado a él.
 */
export async function rechazarPedidoDelRepartidor(
  token: string,
  idPublico: string,
  motivo: string,
): Promise<ResultadoDelRepartidor<null>> {
  const { rechazar, motivo: campo } = CONTRATO_DEL_REPARTIDOR;
  const resultado = await pedirDelRepartidor(rutaDeLaAccion(idPublico, rechazar), {
    metodo: 'POST',
    token,
    cuerpo: { [campo]: motivo.trim() },
  });
  if (!resultado.ok) {
    return resultado;
  }
  return esObjeto(resultado.datos) && esObjeto(resultado.datos.asignacion)
    ? { ok: true, datos: null }
    : { ok: false, motivo: 'servidor' };
}

/**
 * POST del botón del paso siguiente, con el estado al que lleva, tal como lo dio el servidor en "siguiente"
 * (repartidor-E19). Si ese ya no es el paso siguiente, o la oferta sigue sin aceptar, el servidor responde 409 y el
 * pedido no cambia (repartidor-E20).
 */
export function marcarPasoDelRepartidor(
  token: string,
  idPublico: string,
  paso: Pick<PasoSiguiente, 'estado'>,
): Promise<ResultadoDelRepartidor<PedidoDelRepartidor>> {
  const { estado } = CONTRATO_DEL_REPARTIDOR;
  return mandarAccion(rutaDeLaAccion(idPublico, estado), { metodo: 'POST', token, cuerpo: { [estado]: paso.estado } });
}

/**
 * GET de su historial de entregas, con la sesión (repartidor-E25): las que él marcó Entregado, cada una con su folio, el
 * instante en UTC y su fecha en la de la plaza, con la misma forma del historial de la lavandería. El servidor no da la
 * que sigue en curso ni la de otro repartidor. Sin monto ni calificación: esperan DD-019, DD-013 y DD-052 (E26 y E27).
 */
export async function consultarHistorialDelRepartidor(token: string): Promise<ResultadoDelRepartidor<PedidoEntregado[]>> {
  const { historial, lista } = CONTRATO_DEL_REPARTIDOR;
  const resultado = await pedirDelRepartidor(historial, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const filas = esObjeto(resultado.datos) ? resultado.datos[lista] : null;
  const entregas = Array.isArray(filas) ? filas.map(leerPedidoEntregado) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  if (entregas === null || !entregas.every((entrega) => entrega !== null)) {
    return { ok: false, motivo: 'servidor' };
  }
  return { ok: true, datos: entregas as PedidoEntregado[] };
}

/** Sus entregas completadas del día y de la semana, de lunes a domingo, en la zona horaria de su plaza. */
export interface EstadisticasDelRepartidor {
  entregas_del_dia: number;
  entregas_de_la_semana: number;
}

/**
 * GET de sus estadísticas, con la sesión (repartidor-E28): las mismas entregas del historial, contadas por el servidor
 * en la hora de su plaza. Sin ingreso total acumulado: espera DD-019 y DD-013 (repartidor-E29).
 */
export async function consultarEstadisticasDelRepartidor(
  token: string,
): Promise<ResultadoDelRepartidor<EstadisticasDelRepartidor>> {
  const resultado = await pedirDelRepartidor(CONTRATO_DEL_REPARTIDOR.estadisticas, { metodo: 'GET', token });
  if (!resultado.ok) {
    return resultado;
  }
  const datos = resultado.datos;
  if (!esObjeto(datos) || !esConteo(datos.entregas_del_dia) || !esConteo(datos.entregas_de_la_semana)) {
    return { ok: false, motivo: 'servidor' };
  }
  return {
    ok: true,
    datos: { entregas_del_dia: datos.entregas_del_dia, entregas_de_la_semana: datos.entregas_de_la_semana },
  };
}

// Un conteo: un número entero, cero o más.
function esConteo(valor: unknown): valor is number {
  return typeof valor === 'number' && Number.isInteger(valor) && valor >= 0;
}

/** El estado del botón Entregado, el único que lleva la foto de evidencia (repartidor-E23). */
export const ESTADO_ENTREGADO = 'entregado';

/**
 * POST de Entregado con la foto de evidencia, si la hay (repartidor-E23): con foto, en multipart, estado y foto juntos,
 * y el servidor la guarda en el almacenamiento privado, ligada al pedido y sin URL pública; sin foto, en JSON, como los
 * demás botones. Si la evidencia es obligatoria espera BR-29 y DD-110 (repartidor-E24). Lo que el servidor no acepta de
 * la foto lo dice con 422 en el campo, y entonces no guarda nada.
 */
export function entregarPedidoDelRepartidor(
  token: string,
  idPublico: string,
  foto: { uri: string } | null,
): Promise<ResultadoDelRepartidor<PedidoDelRepartidor>> {
  if (!foto) {
    return marcarPasoDelRepartidor(token, idPublico, { estado: ESTADO_ENTREGADO });
  }
  const { estado, foto: campoDeLaFoto } = CONTRATO_DEL_REPARTIDOR;
  const formulario = new FormData();
  formulario.append(estado, ESTADO_ENTREGADO);
  agregarFoto(formulario, campoDeLaFoto, foto);
  return mandarAccion(rutaDeLaAccion(idPublico, estado), { metodo: 'POST', token, formulario });
}

function rutaDeLaAccion(idPublico: string, accion: string): string {
  return `${CONTRATO_DEL_REPARTIDOR.pedidos}/${encodeURIComponent(idPublico)}/${accion}`;
}

// Una acción sobre un pedido: el servidor responde con el pedido en su estado nuevo.
async function mandarAccion(
  ruta: string,
  peticion: Parameters<typeof pedirConEstado>[1],
): Promise<ResultadoDelRepartidor<PedidoDelRepartidor>> {
  const resultado = await pedirDelRepartidor(ruta, peticion);
  if (!resultado.ok) {
    return resultado;
  }
  const pedido = esObjeto(resultado.datos) ? leerPedidoDelRepartidor(resultado.datos[CONTRATO_DEL_REPARTIDOR.pedido]) : null;
  // Una respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
  return pedido ? { ok: true, datos: pedido } : { ok: false, motivo: 'servidor' };
}

/**
 * Una petición a las rutas que piden que el repartidor opere: el 403 dice que ya no opera y el 409, que la acción ya no
 * es posible, los dos con el mensaje del servidor. Nunca lanza.
 */
export async function pedirDelRepartidor(
  ruta: string,
  peticion: Parameters<typeof pedirConEstado>[1],
): Promise<ResultadoDelRepartidor<unknown>> {
  const { resultado, estado } = await pedirConEstado<unknown>(ruta, peticion);
  if (resultado.ok) {
    return resultado;
  }
  const mensaje = 'mensaje' in resultado ? resultado.mensaje : undefined;
  if (estado === 403) {
    return { ok: false, motivo: 'sin-acceso', mensaje };
  }
  if (estado === 409) {
    return { ok: false, motivo: 'conflicto', mensaje };
  }
  return resultado;
}

/** Un pedido leído campo por campo, como lo dan todas las respuestas del repartidor; null si no es del contrato. */
export function leerPedidoDelRepartidor(datos: unknown): PedidoDelRepartidor | null {
  if (
    !esObjeto(datos) ||
    typeof datos.id_publico !== 'string' ||
    typeof datos.folio !== 'string' ||
    typeof datos.por_responder !== 'boolean' ||
    !Array.isArray(datos.evidencias)
  ) {
    return null;
  }
  const estado = leerEstado(datos.estado);
  const oferta = datos.asignacion === null ? null : leerOferta(datos.asignacion);
  const siguiente = datos.siguiente === null ? null : leerPaso(datos.siguiente);
  const ventana = leerVentana(datos.ventana);
  const recogida = leerDireccionDelPedido(datos.recogida);
  const entrega = leerDireccionDelPedido(datos.entrega);
  const lavanderia = leerLavanderia(datos.lavanderia);
  const evidencias = datos.evidencias.map(leerEvidencia);
  if (
    !estado ||
    oferta === undefined ||
    siguiente === undefined ||
    !ventana ||
    !recogida ||
    !entrega ||
    !lavanderia ||
    !evidencias.every((evidencia) => evidencia !== null)
  ) {
    return null;
  }
  return {
    id_publico: datos.id_publico,
    folio: datos.folio,
    estado,
    oferta,
    por_responder: datos.por_responder,
    siguiente,
    ventana,
    recogida,
    entrega,
    lavanderia,
    evidencias: evidencias as PedidoDelRepartidor['evidencias'],
  };
}

function leerEstado(datos: unknown): EstadoOperativo | null {
  if (!esObjeto(datos) || typeof datos.codigo !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  return { codigo: datos.codigo, nombre: datos.nombre };
}

// El estado de su oferta, o undefined si no es del contrato (null ya se leyó antes: sin oferta).
function leerOferta(datos: unknown): string | undefined {
  return esObjeto(datos) && typeof datos.estado === 'string' ? datos.estado : undefined;
}

// El paso siguiente, o undefined si no es del contrato (null ya se leyó antes: no hay botón).
function leerPaso(datos: unknown): PasoSiguiente | undefined {
  if (!esObjeto(datos) || typeof datos.estado !== 'string' || typeof datos.boton !== 'string') {
    return undefined;
  }
  return { estado: datos.estado, boton: datos.boton };
}

// Texto, o null si viene vacío; cualquier otra cosa no es del contrato (undefined).
function textoOVacio(valor: unknown): string | null | undefined {
  if (valor === null || valor === undefined) {
    return null;
  }
  return typeof valor === 'string' ? valor : undefined;
}

function leerLavanderia(datos: unknown): LavanderiaDelPedido | null {
  if (!esObjeto(datos) || typeof datos.id_publico !== 'string' || typeof datos.nombre !== 'string') {
    return null;
  }
  const partes = (['calle', 'numero', 'colonia', 'codigo_postal'] as const).map((parte) => textoOVacio(datos[parte]));
  if (partes.some((parte) => parte === undefined)) {
    return null;
  }
  const [calle, numero, colonia, codigoPostal] = partes as (string | null)[];
  return { id_publico: datos.id_publico, nombre: datos.nombre, calle, numero, colonia, codigo_postal: codigoPostal };
}

function leerEvidencia(datos: unknown): { tipo: string; tomada_en: string } | null {
  if (!esObjeto(datos) || typeof datos.tipo !== 'string' || typeof datos.tomada_en !== 'string') {
    return null;
  }
  return { tipo: datos.tipo, tomada_en: datos.tomada_en };
}

