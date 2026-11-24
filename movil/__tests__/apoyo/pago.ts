// Apoyo de las pruebas del pago del pedido (openspec/changes/pago, pago-E02 y pago-E08): la pantalla de pago, que abre el
// checkout de la pasarela, y la espera "Procesando pago", que consulta el cobro. No es una prueba: Jest no lo corre como
// tal (testPathIgnorePatterns en package.json). Abre la app real, con las rutas de movil/app/ (ADR-0017), y simula el
// servidor con el "Contrato de la API" de openspec/changes/pago/design.md: POST /api/pagos/checkout y
// GET /api/pagos/{id_publico}, con el pedido de GET /api/pedidos/{id_publico} para la confirmación que abre el pago.
//
// La pasarela no existe aquí: el checkout es una dirección de prueba y lo que pasa en él lo programa cada prueba con la
// simulación de expo-web-browser (__mocks__/expo-web-browser.ts) y con el estado del cobro en este servidor, que es lo
// que dejaría el aviso de la pasarela (pago-E03 y pago-E04, del servidor).
import path from 'node:path';

import { renderRouter } from 'expo-router/testing-library';
import * as SecureStore from 'expo-secure-store';
import * as WebBrowser from 'expo-web-browser';
import { Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { vaciarPedidoEnCurso } from '../../src/pedido/pedidoEnCurso';
import { guardarToken } from '../../src/sesion/almacen';

export const APP = path.resolve(__dirname, '..', '..', 'app');
export const PLATAFORMAS = ['ios', 'android'] as const;
export type Plataforma = (typeof PLATAFORMAS)[number];

export const TOKEN = 'token-del-pago';
const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };

/** El pedido que se paga, creado al aceptar el resumen, y su cobro (pago-E14 todavía no lo crea: lo pone la prueba). */
export const PEDIDO = '01jd8x6m3v2c9q4w5e7r8t0yp1';
export const COBRO = '01jd8x6m3v2c9q4w5e7r8t0yq1';
// Ocho caracteres del alfabeto de Crockford, como los da ADR-0020. Es de prueba.
export const FOLIO = 'K7M2Q9XA';

/** La dirección del checkout de la pasarela simulada y la de regreso a la app: el esquema de prueba seguido de ://pago. */
export const URL_DEL_CHECKOUT = 'https://pasarela.prueba.invalid/checkout/orden-de-prueba-1';
export const REGRESO = 'mx.ejemplo.prueba://pago';

/** El regreso del checkout a la app, como lo arma el servidor: <regreso>?resultado=exito|error&pago=<cobro>. */
export function enlaceDeRegreso(resultado: 'exito' | 'error', cobro = COBRO): string {
  return `${REGRESO}?resultado=${resultado}&pago=${cobro}`;
}

export type EstadoDelCobro = 'pendiente' | 'pagado' | 'fallido' | 'vencido' | 'cancelado';

/** La simulación de __mocks__/, la misma instancia que importa la app. */
export const navegador = WebBrowser as unknown as typeof import('../../__mocks__/expo-web-browser');
const almacen = SecureStore as unknown as { __vaciar(): void };

export function respuesta(status: number, cuerpo?: unknown) {
  return { ok: status >= 200 && status < 300, status, json: async () => cuerpo };
}

const COPIA_DE_CASA = {
  alias: 'Casa',
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: null,
  colonia: 'Colonia de prueba',
  codigo_postal: null,
  municipio: 'Municipio de prueba',
  referencias: null,
};

/** El pedido como lo devuelve GET /api/pedidos/{id_publico}: Confirmado con su folio, o antes de su pago, sin él. */
function pedidoDelServidor(confirmado: boolean) {
  return {
    id_publico: PEDIDO,
    folio: confirmado ? FOLIO : null,
    confirmado_en: confirmado ? '2026-12-02T15:30:00Z' : null,
    estado: confirmado
      ? { codigo: 'confirmado', nombre: 'Confirmado', visible_usuario: true }
      : { codigo: 'por_pagar', nombre: 'Por pagar', visible_usuario: false },
    lavanderia: { id_publico: '01jd8x6m3v2c9q4w5e7r8t0yl1', nombre: 'Lavandería de prueba del pago' },
    servicios: [{ codigo: 'estandar', nombre: 'Lavado estándar', unidad: { codigo: 'kg', nombre: 'kilo' } }],
    zona_horaria: 'America/Mexico_City',
    ventana: {
      inicio: '2026-12-03T11:00:00-06:00',
      fin: '2026-12-03T12:00:00-06:00',
      fecha: '2026-12-03',
      hora_inicio: '11:00',
      hora_fin: '12:00',
    },
    recogida: COPIA_DE_CASA,
    entrega: COPIA_DE_CASA,
  };
}

export interface Peticion {
  metodo: string;
  ruta: string;
  cuerpo: unknown;
  autorizacion: string | null;
}

/**
 * Lo que una prueba pone en lugar de la respuesta normal: si devuelve algo, el servidor contesta eso (una respuesta, una
 * promesa que la prueba suelta cuando quiere, o lanza un error de red); si devuelve undefined, contesta lo normal.
 */
export type Interceptor = (peticion: Peticion) => unknown;

/** El servidor simulado del pago: el cobro del pedido, su checkout y lo que la app le pidió. */
export class ServidorDelPago {
  /** El estado del cobro del pedido; null mientras no tiene cobro (pago-E14, bloqueado por DD-012). */
  cobro: EstadoDelCobro | null = 'pendiente';
  /** La dirección de regreso que da el servidor; null si su entorno no tiene PASARELA_REGRESO. */
  regreso: string | null = REGRESO;
  interceptar: Interceptor | null = null;
  readonly fetch: jest.Mock;

  constructor(private readonly base: string) {
    this.fetch = jest.fn((url: string, opciones: RequestInit = {}) => this.contestar(url, opciones));
  }

  /** Lo que la app pidió, en orden. */
  peticiones(): Peticion[] {
    return this.fetch.mock.calls.map(([url, opciones]: [string, RequestInit | undefined]) =>
      leer(this.base, url, opciones),
    );
  }

  /** Los cuerpos con que la app pidió el checkout. */
  pedidosDeCheckout(): unknown[] {
    return this.peticiones()
      .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === '/pagos/checkout')
      .map(({ cuerpo }) => cuerpo);
  }

  /** Cuántas veces la app consultó el cobro. */
  consultasDelCobro(): number {
    return this.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta.startsWith('/pagos/')).length;
  }

  /** El cobro como sale en las dos rutas del pago: sin plaza, sin pasarela y sin ningún dato de tarjeta. */
  private cobroComoSale(estado: EstadoDelCobro) {
    return {
      id_publico: COBRO,
      pedido: PEDIDO,
      tipo: 'cobro',
      metodo: 'tarjeta',
      estado,
      monto: '180.00',
      pagado_en: estado === 'pagado' ? '2026-12-02T15:30:00Z' : null,
    };
  }

  private async contestar(url: string, opciones: RequestInit) {
    const peticion = leer(this.base, url, opciones);
    const interceptada = this.interceptar?.(peticion);
    if (interceptada !== undefined) {
      return interceptada;
    }
    const { metodo, ruta, cuerpo, autorizacion } = peticion;
    if (autorizacion !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (metodo === 'GET' && ruta === '/cuenta') {
      return respuesta(200, { usuario: USUARIO });
    }
    // La pantalla principal, si la prueba vuelve al inicio.
    if (metodo === 'GET' && ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    if (metodo === 'POST' && ruta === '/pagos/checkout') {
      return this.checkout(cuerpo);
    }
    if (metodo === 'GET' && ruta.startsWith('/pagos/')) {
      if (ruta !== `/pagos/${COBRO}` || this.cobro === null) {
        return respuesta(404, { message: 'No existe el pago.' });
      }
      return respuesta(200, { pago: this.cobroComoSale(this.cobro) });
    }
    if (metodo === 'GET' && ruta === `/pedidos/${PEDIDO}`) {
      return respuesta(200, { pedido: pedidoDelServidor(this.cobro === 'pagado') });
    }
    throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
  }

  // POST /api/pagos/checkout, en el orden de revisión del contrato: el pedido, el cobro y luego el método.
  private checkout(cuerpo: unknown) {
    const pedido = esObjeto(cuerpo) ? cuerpo.pedido : undefined;
    if (pedido !== PEDIDO) {
      return respuesta(404, { message: 'No existe el pedido.' });
    }
    if (this.cobro === null) {
      return respuesta(409, { message: 'El cobro de este pedido todavía no está listo.' });
    }
    if (this.cobro === 'pagado') {
      return respuesta(409, { message: 'Este pedido ya está pagado.' });
    }
    if (this.cobro === 'vencido' || this.cobro === 'cancelado') {
      return respuesta(409, { message: 'Este cobro ya no se puede pagar.' });
    }
    if (!esObjeto(cuerpo) || cuerpo.metodo !== 'tarjeta') {
      return respuesta(422, {
        message: 'El método de pago no es válido.',
        errors: { metodo: ['El método de pago no es válido.'] },
      });
    }
    // Con el cobro fallido, el checkout lo reabre (pago-E04).
    this.cobro = 'pendiente';
    return respuesta(200, {
      checkout: { url: URL_DEL_CHECKOUT, regreso: this.regreso },
      pago: this.cobroComoSale(this.cobro),
    });
  }
}

function esObjeto(valor: unknown): valor is Record<string, unknown> {
  return typeof valor === 'object' && valor !== null;
}

function leer(base: string, url: string, opciones: RequestInit = {}): Peticion {
  const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
  const cuerpo = typeof opciones.body === 'string' ? JSON.parse(opciones.body) : undefined;
  return {
    metodo: opciones.method ?? 'GET',
    ruta: url.slice(base.length),
    cuerpo,
    autorizacion: cabeceras.Authorization ?? null,
  };
}

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
const fetchOriginal = global.fetch;

/** Prepara cada prueba: el servidor simulado en la base de la prueba, el teléfono vacío y el navegador sin llamadas. */
export function prepararElServidor(base: string): ServidorDelPago {
  almacen.__vaciar();
  vaciarPedidoEnCurso();
  navegador.__reiniciar();
  process.env.EXPO_PUBLIC_API_URL = base;
  process.env.EXPO_PUBLIC_NOMBRE_APP = 'Nombre de prueba del pago';
  const servidor = new ServidorDelPago(base);
  global.fetch = servidor.fetch as unknown as typeof fetch;
  return servidor;
}

/** Deja el entorno como estaba. */
export function limpiarElServidor(): void {
  for (const [variable, original] of [
    ['EXPO_PUBLIC_API_URL', direccionOriginal],
    ['EXPO_PUBLIC_NOMBRE_APP', nombreOriginal],
  ] as const) {
    if (original === undefined) {
      delete process.env[variable];
    } else {
      process.env[variable] = original;
    }
  }
  global.fetch = fetchOriginal;
}

/** La persona con sesión, en esa plataforma. */
export async function conSesion(plataforma: Plataforma): Promise<void> {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
}

/** Abre la app en esa dirección, con sesión y en esa plataforma. */
export async function abrir(plataforma: Plataforma, direccion: string): Promise<void> {
  await conSesion(plataforma);
  await renderRouter(APP, { initialUrl: direccion });
}

/** La ruta de la pantalla de pago del pedido y la de la espera de su cobro. */
export const RUTA_DEL_PAGO = `/pedido/pago/${PEDIDO}`;
export const RUTA_DE_LA_ESPERA = `/pedido/pago/procesando/${COBRO}`;
