// Apoyo de las pruebas del Módulo D en la app, lo que ve y hace la administración
// (openspec/changes/administracion-en-la-app; ADR-0022). No es una prueba: Jest no lo corre como tal
// (testPathIgnorePatterns en package.json). Abre la app real, con las rutas de movil/app/ (ADR-0017), y simula el
// servidor con el contrato de la sesión de design.md, sección 2. La preparación de cada prueba, la apertura de la app,
// las respuestas y la lectura de las peticiones son las del apoyo del Módulo B (__tests__/apoyo/lavanderia.ts).
//
// Lo que responde por su cuenta es la sesión, como el servidor real después de la tarea 1.3:
// - POST /sesion: 200 con el token y la cuenta (administracion-app-E01);
// - POST /sesion/google: 422 en id_token a la cuenta de administración, sin token (administracion-app-E08);
// - GET /cuenta: 200 con la cuenta y su rol;
// - DELETE /sesion: 204, y el token queda revocado (administracion-app-E04);
// - GET /catalogos/municipios: 200 con los municipios del territorio, que la edición de la lavandería ofrece para
//   elegir: el catálogo de municipios es de los cuatro roles (adenda del 2026-11-26 de ADR-0022, decisión 6;
//   administracion-app-E14);
// - 401 a todo con un token que no es el de la sesión, con uno revocado o después de vencerLaSesion()
//   (administracion-app-E05);
// - 403 con solo su mensaje a cualquier ruta de /administracion si la cuenta no es de administración: la prueba le pone
//   la de otro rol (administracion-app-E07).
//
// Las rutas de /administracion de cada función las contestan los manejadores que la prueba registra con agregar(), en
// el orden en que los registró; cada función pone los suyos en __tests__/apoyo/administracion/<función>.ts, con el
// contrato del comentario de su controlador. Una petición que ningún manejador contesta hace fallar la prueba con el
// método y la ruta, salvo las dos que pide el inicio del módulo para sus conteos (administracion-app-E03): GET
// /administracion/repartidores y GET /administracion/monitoreo, que sin su manejador responden sin repartidores ni
// pedidos activos.
import {
  abrirLaAppConSesion,
  CUENTAS,
  leer,
  MUNICIPIOS,
  prepararElTelefonoConSesion,
  recibir,
  respuesta,
  usarElServidorSimulado,
  type Interceptor,
  type Peticion,
  type Plataforma,
} from './lavanderia';

export {
  abrirLaAppSinSesion,
  APP,
  copia,
  CUENTAS,
  PLATAFORMAS,
  respuesta,
  type Interceptor,
  type Peticion,
  type Plataforma,
} from './lavanderia';

/** El inicio del módulo, a donde entra la cuenta de administración (administracion-app-E02). */
export const RUTA_DEL_INICIO = '/administracion';

export const TOKEN_DE_LA_ADMINISTRACION = 'token-de-la-administracion';

/** La cuenta de administración como la devuelven GET /api/cuenta y POST /api/sesion. */
export const CUENTA_DE_LA_ADMINISTRACION = CUENTAS.administracion;

/** El 422 de POST /api/sesion/google a la administración (design.md, sección 2; administracion-app-E08). */
export const MENSAJE_DE_GOOGLE = 'La administración entra con su correo y su contraseña.';

/** La cuenta de la sesión: la de administración, o la de otro rol para probar el 403 del módulo. */
export interface CuentaDePrueba {
  id_publico: string;
  correo: string;
  rol: string | null;
}

/**
 * Contesta una petición a /administracion de la cuenta de administración: devuelve la respuesta, o undefined si no es
 * suya y le toca al siguiente. La ruta de la petición trae el prefijo: "/administracion/lavanderias".
 */
export type Manejador = (peticion: Peticion) => unknown;

const PREFIJO = '/administracion';

/** Las dos rutas que consulta el inicio del módulo para sus conteos (administracion-app-E03). */
export const RUTAS_DE_LOS_CONTEOS = {
  repartidores: `${PREFIJO}/repartidores`,
  monitoreo: `${PREFIJO}/monitoreo`,
} as const;

/** El servidor simulado de la administración, con lo que la app le pidió. */
export class ServidorDeLaAdministracion {
  /** La cuenta de la sesión: la de administración, salvo que la prueba diga otra. */
  cuenta: CuentaDePrueba = CUENTA_DE_LA_ADMINISTRACION;
  interceptar: Interceptor | null = null;
  readonly fetch: jest.Mock;
  private readonly manejadores: Manejador[] = [];
  private readonly revocados = new Set<string>();
  private vencida = false;

  constructor(private readonly base: string) {
    this.fetch = jest.fn((url: string, opciones: RequestInit = {}) => this.contestar(url, opciones));
  }

  /** Suma un manejador de las rutas de /administracion, después de los que ya había. */
  agregar(manejador: Manejador): void {
    this.manejadores.push(manejador);
  }

  /** El token de la sesión vence: desde ahora, todo responde 401 (administracion-app-E05). */
  vencerLaSesion(): void {
    this.vencida = true;
  }

  /** Lo que la app pidió, en orden. */
  peticiones(): Peticion[] {
    return this.fetch.mock.calls.map(([url, opciones]: [string, RequestInit | undefined]) =>
      leer(this.base, url, opciones),
    );
  }

  private async contestar(url: string, opciones: RequestInit) {
    const peticion = await recibir(this.base, url, opciones);
    const interceptada = this.interceptar?.(peticion);
    if (interceptada !== undefined) {
      return interceptada;
    }
    const { metodo, ruta, autorizacion } = peticion;
    if (metodo === 'POST' && ruta === '/sesion') {
      // Una sesión nueva: su token sirve hasta que se revoque o venza.
      this.revocados.delete(TOKEN_DE_LA_ADMINISTRACION);
      this.vencida = false;
      return respuesta(200, { token: TOKEN_DE_LA_ADMINISTRACION, usuario: this.cuenta });
    }
    if (metodo === 'POST' && ruta === '/sesion/google') {
      // Antes de ligar nada: la cuenta queda como estaba (registro-E18 no la alcanza).
      return this.cuenta.rol === 'administracion'
        ? respuesta(422, { message: MENSAJE_DE_GOOGLE, errors: { id_token: [MENSAJE_DE_GOOGLE] } })
        : respuesta(200, { token: TOKEN_DE_LA_ADMINISTRACION, usuario: this.cuenta });
    }
    const token = autorizacion?.replace(/^Bearer /, '') ?? null;
    if (token !== TOKEN_DE_LA_ADMINISTRACION || this.revocados.has(token) || this.vencida) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (metodo === 'GET' && ruta === '/cuenta') {
      return respuesta(200, { usuario: this.cuenta });
    }
    if (metodo === 'DELETE' && ruta === '/sesion') {
      this.revocados.add(token);
      return respuesta(204);
    }
    if (metodo === 'GET' && ruta === '/catalogos/municipios') {
      return respuesta(200, { municipios: MUNICIPIOS });
    }
    if (ruta !== PREFIJO && !ruta.startsWith(`${PREFIJO}/`)) {
      throw new Error(`El servidor simulado de la administración no esperaba ${metodo} ${ruta}`);
    }
    // El componente de autorización: el módulo es solo de la administración (ADR-0022, decisión 8).
    if (this.cuenta.rol !== 'administracion') {
      return respuesta(403, { message: 'Esta cuenta no tiene acceso.' });
    }
    for (const manejador of this.manejadores) {
      const contestada = manejador(peticion);
      if (contestada !== undefined) {
        return contestada;
      }
    }
    // Los dos conteos del inicio del módulo (administracion-app-E03), si la prueba no puso los manejadores de los
    // repartidores o del monitoreo: como un servidor sin repartidores ni pedidos activos.
    if (metodo === 'GET' && ruta === RUTAS_DE_LOS_CONTEOS.repartidores) {
      return respuesta(200, { repartidores: [] });
    }
    if (metodo === 'GET' && ruta === RUTAS_DE_LOS_CONTEOS.monitoreo) {
      return respuesta(200, { pedidos: [], marcadores: [] });
    }
    throw new Error(`El servidor simulado de la administración no esperaba ${metodo} ${ruta}`);
  }
}

/**
 * Prepara cada prueba del archivo con la app recién abierta en esta instalación y el servidor simulado de la
 * administración en la dirección base, como el de la lavandería. Devuelve el servidor de la prueba en curso.
 */
export function usarElServidorDeLaAdministracion(nombre: string): () => ServidorDeLaAdministracion {
  return usarElServidorSimulado(nombre, (base) => new ServidorDeLaAdministracion(base));
}

/**
 * Deja el teléfono, en esa plataforma, como el de la cuenta de administración que ya entró antes en esta instalación:
 * vio la bienvenida y tiene su token guardado.
 */
export async function prepararElTelefonoConLaAdministracion(plataforma: Plataforma): Promise<void> {
  await prepararElTelefonoConSesion(plataforma, TOKEN_DE_LA_ADMINISTRACION);
}

/**
 * Abre la app como al tocar su ícono, en esa plataforma, con la sesión guardada de la cuenta de administración. La cuenta
 * es la del servidor: la de administración, si la prueba no dice otra.
 */
export async function abrirLaAppConLaAdministracion(plataforma: Plataforma, ruta = '/'): Promise<void> {
  await abrirLaAppConSesion(plataforma, ruta, TOKEN_DE_LA_ADMINISTRACION);
}
