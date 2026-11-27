// Cliente mínimo de la API: toda consulta sale de la dirección base del entorno (base-E07) y tiene un plazo. pedir()
// arma la petición en JSON y lee la respuesta sin lanzar: cada cliente (acceso, cercanas) devuelve un Resultado que la
// pantalla lee.
import { direccionDeLaApi, plazoDeLaApi } from './entorno';

/** Los errores de validación de Laravel: el campo y sus mensajes. */
export type ErroresPorCampo = Record<string, string[]>;

/**
 * Lo que algunas rutas agregan al cuerpo de un error para que la app sepa qué pasó, además del mensaje: el motivo
 * ("motivo", como direccion_no_encontrada) y el motivo de cada campo ("motivos", como fuera_de_la_zona_de_servicio).
 * Solo vienen si el servidor los manda (direcciones-E03 y E11).
 */
export interface MotivosDelServidor {
  motivoDelServidor?: string;
  motivosPorCampo?: Record<string, string>;
}

/** Por qué no se pudo: lo que dijo el servidor, o que no se llegó a él. */
export type Falla =
  | ({ ok: false; motivo: 'invalido'; mensaje: string; errores: ErroresPorCampo } & MotivosDelServidor)
  | { ok: false; motivo: 'sin-sesion'; mensaje?: string }
  | { ok: false; motivo: 'demasiados-intentos'; mensaje?: string }
  | ({ ok: false; motivo: 'servidor'; mensaje?: string } & MotivosDelServidor)
  | { ok: false; motivo: 'sin-conexion' };

export type Resultado<T> = { ok: true; datos: T } | Falla;

export interface Peticion {
  /** PUT, para las rutas que reemplazan un valor entero, como el porcentaje de comisión (administracion-app-E33). */
  metodo: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';
  cuerpo?: unknown;
  /**
   * Un formulario multipart en lugar del cuerpo JSON, para las rutas que reciben un archivo, como la foto de la
   * lavandería (alta-lavanderias-E03). El teléfono pone el Content-Type con su separador.
   */
  formulario?: FormData;
  token?: string;
}

/** La dirección completa de una ruta de la API, sin barras dobles ni faltantes entre la base y la ruta. */
export function direccionDe(ruta: string): string {
  const base = direccionDeLaApi().replace(/\/+$/, '');
  return `${base}/${ruta.replace(/^\/+/, '')}`;
}

/**
 * Hace una consulta a una ruta de la API. Si el servidor no contesta dentro del plazo del entorno, la cancela y la
 * promesa falla como un error de red: en Android la petición no tiene límite propio, y sin el plazo la app esperaría
 * para siempre a un servidor que aceptó la conexión y no contesta (registro-E11 y registro-E13). La señal de
 * cancelación es la del plazo, por eso no se recibe una.
 */
export async function consultarApi(ruta: string, opciones: Omit<RequestInit, 'signal'> = {}): Promise<Response> {
  const direccion = direccionDe(ruta);
  const controlador = new AbortController();
  const plazo = setTimeout(() => controlador.abort(), plazoDeLaApi());
  try {
    return await fetch(direccion, { ...opciones, signal: controlador.signal });
  } finally {
    clearTimeout(plazo);
  }
}

/** Hace una petición a la API y lee su respuesta en un Resultado. Nunca lanza. */
export async function pedir<T>(ruta: string, peticion: Peticion): Promise<Resultado<T>> {
  return (await pedirConEstado<T>(ruta, peticion)).resultado;
}

/** El Resultado de una petición y el código HTTP de su respuesta; null si no se llegó al servidor. */
export interface ResultadoConEstado<T> {
  resultado: Resultado<T>;
  estado: number | null;
}

/**
 * Como pedir(), con el código HTTP de la respuesta, para las rutas cuyo contrato distingue un 404 o un 503 de los demás
 * errores del servidor (el pedido: servidor/app/Http/Controllers/Api/PedidosController.php). Nunca lanza.
 */
export async function pedirConEstado<T>(
  ruta: string,
  { metodo, cuerpo, formulario, token }: Peticion,
): Promise<ResultadoConEstado<T>> {
  // Accept: application/json hace que Laravel conteste los errores en JSON y no con una redirección.
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (cuerpo !== undefined && formulario === undefined) {
    headers['Content-Type'] = 'application/json';
  }
  if (token) {
    headers.Authorization = `Bearer ${token}`;
  }

  let respuesta: Response;
  try {
    respuesta = await consultarApi(ruta, {
      method: metodo,
      headers,
      body: formulario ?? (cuerpo === undefined ? undefined : JSON.stringify(cuerpo)),
    });
  } catch {
    // Un error de red o el plazo vencido (consultarApi cancela la petición): para la persona, no hubo conexión.
    return { resultado: { ok: false, motivo: 'sin-conexion' }, estado: null };
  }

  return { resultado: await leerResultado<T>(respuesta), estado: respuesta.status };
}

// La respuesta del servidor en un Resultado: los datos, o la falla según su código.
async function leerResultado<T>(respuesta: Response): Promise<Resultado<T>> {
  const datos = respuesta.status === 204 ? undefined : await leerJson(respuesta);

  if (respuesta.ok) {
    return { ok: true, datos: datos as T };
  }

  const mensaje = mensajeDe(datos);
  switch (respuesta.status) {
    case 422:
      return { ok: false, motivo: 'invalido', mensaje: mensaje ?? '', errores: erroresDe(datos), ...motivosDe(datos) };
    case 401:
      return { ok: false, motivo: 'sin-sesion', mensaje };
    case 429:
      return { ok: false, motivo: 'demasiados-intentos', mensaje };
    default:
      return { ok: false, motivo: 'servidor', mensaje, ...motivosDe(datos) };
  }
}

async function leerJson(respuesta: Response): Promise<unknown> {
  try {
    return await respuesta.json();
  } catch {
    return undefined;
  }
}

function mensajeDe(datos: unknown): string | undefined {
  if (datos && typeof datos === 'object' && 'message' in datos && typeof datos.message === 'string') {
    return datos.message;
  }
  return undefined;
}

// Solo las claves que el servidor mandó: un error sin motivos queda igual que antes.
function motivosDe(datos: unknown): MotivosDelServidor {
  if (!datos || typeof datos !== 'object') {
    return {};
  }
  const motivos: MotivosDelServidor = {};
  if ('motivo' in datos && typeof datos.motivo === 'string') {
    motivos.motivoDelServidor = datos.motivo;
  }
  if ('motivos' in datos && datos.motivos && typeof datos.motivos === 'object') {
    const porCampo = Object.entries(datos.motivos).filter(
      (entrada): entrada is [string, string] => typeof entrada[1] === 'string',
    );
    motivos.motivosPorCampo = Object.fromEntries(porCampo);
  }
  return motivos;
}

function erroresDe(datos: unknown): ErroresPorCampo {
  if (datos && typeof datos === 'object' && 'errors' in datos && datos.errors && typeof datos.errors === 'object') {
    return datos.errors as ErroresPorCampo;
  }
  return {};
}
