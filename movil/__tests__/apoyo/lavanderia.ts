// Apoyo de las pruebas del Módulo B, lo que ve y hace el socio lavandería: sus pedidos
// (openspec/changes/pedidos-de-la-lavanderia y openspec/changes/estados-del-pedido), su historial y sus estadísticas
// (openspec/changes/historial-de-la-lavanderia) y su lavandería, con el registro y el perfil y horarios
// (openspec/changes/alta-de-lavanderias). No es una prueba: Jest no lo corre como tal (testPathIgnorePatterns en
// package.json). Abre la app real, con las rutas de movil/app/ (ADR-0017), y simula el servidor con el contrato de la
// API de cada cambio: el "Contrato de la API" de sus design.md, el mismo de los comentarios de
// servidor/app/Http/Controllers/Api/PedidosDeLaLavanderiaController.php, HistorialDeLaLavanderiaController.php y
// LavanderiaDelSocioController.php.
import path from 'node:path';

import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { renderRouter } from 'expo-router/testing-library';
import * as Location from 'expo-location';
import * as SecureStore from 'expo-secure-store';
import { AccessibilityInfo, Platform } from 'react-native';

import { marcarBienvenidaVista } from '../../src/bienvenida/vista';
import { olvidarMunicipios } from '../../src/direcciones/useMunicipios';
import { comprobarInstalacion } from '../../src/instalacion/marca';
import { guardarToken } from '../../src/sesion/almacen';
import {
  esFormulario,
  FormDataDelTelefono,
  leerFormulario,
  partesLeidas,
  type ArchivoDelFormulario,
  type ParteDelFormulario,
} from './formulario';

export const APP = path.resolve(__dirname, '..', '..', 'app');
export const PLATAFORMAS = ['ios', 'android'] as const;
export type Plataforma = (typeof PLATAFORMAS)[number];

/** La pantalla de pedidos de la lavandería, a donde entra su cuenta. */
export const RUTA_DE_LOS_PEDIDOS = '/lavanderia/pedidos';

export const TOKEN = 'token-de-la-lavanderia';

/** Las cuentas como las devuelven GET /api/cuenta y POST /api/sesion (registro-E33): el rol dice a qué módulo entra. */
export const CUENTAS = {
  lavanderia: { id_publico: '01JD00000000000000000000LV', correo: 'lavanderia@ejemplo.com', rol: 'lavanderia' },
  usuario: { id_publico: '01JD00000000000000000000US', correo: 'persona@ejemplo.com', rol: 'usuario' },
  administracion: { id_publico: '01JD00000000000000000000AD', correo: 'panel@ejemplo.com', rol: 'administracion' },
} as const;
export type Cuenta = (typeof CUENTAS)[keyof typeof CUENTAS];

export type CodigoOperativo =
  | 'confirmado'
  | 'aceptado'
  | 'rechazado'
  | 'recolectado'
  | 'en_proceso'
  | 'listo'
  | 'en_entrega'
  | 'entregado';

/**
 * Cómo nombra el servidor cada estado operativo para la app de la lavandería, y cada botón por el estado al que lleva
 * (servidor/app/Http/Resources/PedidoDeLaLavanderiaResource.php).
 */
export const NOMBRES: Record<CodigoOperativo, string> = {
  confirmado: 'Por aceptar',
  aceptado: 'Aceptado',
  rechazado: 'Rechazado',
  recolectado: 'Recolectado',
  en_proceso: 'En proceso',
  listo: 'Listo',
  en_entrega: 'En proceso de entrega',
  entregado: 'Entregado',
};

/** El paso siguiente de cada estado (ADR-0021 y su adenda): el único botón que el servidor acepta. */
const SIGUIENTE: Partial<Record<CodigoOperativo, CodigoOperativo>> = {
  aceptado: 'recolectado',
  recolectado: 'en_proceso',
  en_proceso: 'listo',
  listo: 'en_entrega',
};

/** Los cuatro botones de la lavandería (Módulo B #3): lo demás responde 422. */
const BOTONES: CodigoOperativo[] = ['recolectado', 'en_proceso', 'listo', 'en_entrega'];

/** Los estados de la lista: el que espera respuesta y los aceptados que aún no llegan a Entregado. */
const ACTIVOS: CodigoOperativo[] = ['confirmado', 'aceptado', 'recolectado', 'en_proceso', 'listo', 'en_entrega'];

/** Lo que distingue a un pedido de otro en las pruebas. */
export interface DatosDelPedido {
  id_publico: string;
  folio: string;
  /** AAAA-MM-DD, el día de la recolección en la hora de la plaza. */
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  servicio: { codigo: string; nombre: string };
}

/** Un pedido nuevo de la mañana del jueves 3 de diciembre. */
export const NUEVO: DatosDelPedido = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yb1',
  folio: '7KQ2M9XA',
  fecha: '2026-12-03',
  hora_inicio: '11:00',
  hora_fin: '12:00',
  servicio: { codigo: 'estandar', nombre: 'Lavado estándar' },
};

/** Otro, del viernes 4 de diciembre, con otro servicio. */
export const OTRO: DatosDelPedido = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yb2',
  folio: '3HT8W2QZ',
  fecha: '2026-12-04',
  hora_inicio: '09:00',
  hora_fin: '10:00',
  servicio: { codigo: 'planchado', nombre: 'Planchado' },
};

/** Un tercero, que llega después (pedidos-lavanderia-E05). */
export const TERCERO: DatosDelPedido = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yb3',
  folio: '9PL4X7RB',
  fecha: '2026-12-05',
  hora_inicio: '16:00',
  hora_fin: '17:00',
  servicio: { codigo: 'seco', nombre: 'Lavado en seco' },
};

/** El pedido en ese estado, como sale en todas las respuestas de la API de la lavandería. Sin datos del usuario. */
export function pedidoEn(codigo: CodigoOperativo, datos: DatosDelPedido) {
  const siguiente = SIGUIENTE[codigo];
  return {
    id_publico: datos.id_publico,
    folio: datos.folio,
    confirmado_en: '2026-12-01T15:30:00Z',
    estado: { codigo, nombre: NOMBRES[codigo] },
    por_responder: codigo === 'confirmado',
    siguiente: siguiente ? { estado: siguiente, boton: NOMBRES[siguiente] } : null,
    servicios: [{ ...datos.servicio, unidad: { codigo: 'kg', nombre: 'kilo' } }],
    zona_horaria: 'America/Mexico_City',
    ventana: {
      inicio: `${datos.fecha}T${datos.hora_inicio}:00-06:00`,
      fin: `${datos.fecha}T${datos.hora_fin}:00-06:00`,
      fecha: datos.fecha,
      hora_inicio: datos.hora_inicio,
      hora_fin: datos.hora_fin,
    },
  };
}

/**
 * Cuándo se entregó un pedido, como lo da GET /api/lavanderia/historial: el instante en UTC y su fecha en la hora de la
 * plaza (historial-de-la-lavanderia, design.md, "Contrato de la API").
 */
export interface Entrega {
  entregado_en: string;
  fecha: string;
}

/** Los municipios del territorio como los da GET /api/catalogos/municipios (direcciones-E17). Códigos de prueba. */
export const MUNICIPIOS = [
  { codigo: '05017', nombre: 'Matamoros', estado: { codigo: '05', nombre: 'Coahuila de Zaragoza' } },
  { codigo: '05035', nombre: 'Torreón', estado: { codigo: '05', nombre: 'Coahuila de Zaragoza' } },
  { codigo: '10005', nombre: 'Durango', estado: { codigo: '10', nombre: 'Durango' } },
];

/** Los nombres de los tipos de servicio del catálogo del servidor (tipos_servicio). */
const NOMBRES_DE_SERVICIO: Record<string, string> = {
  estandar: 'Lavado estándar',
  planchado: 'Lavado con planchado',
  seco: 'Lavado en seco',
};

/** Una ventana de una hora de la lavandería, como sale en la API: día del 1, el lunes, al 7, y horas de la plaza. */
export interface VentanaDelSocio {
  dia_semana: number;
  hora_inicio: string;
  hora_fin: string;
}

/** Una foto como sale en la API: solo su URL firmada y cuándo vence. */
export interface FotoDelSocio {
  url: string;
  vence_en: string;
}

/** La lavandería del socio, como sale en todas las respuestas de /api/socio/lavanderias. */
export interface LavanderiaDelSocio {
  id_publico: string;
  nombre: string;
  descripcion: string | null;
  direccion: {
    calle: string;
    numero: string;
    colonia: string;
    codigo_postal: string;
    municipio: { codigo: string; nombre: string; estado: string } | null;
  };
  /** La principal: la primera de fotos. */
  foto: FotoDelSocio | null;
  fotos: FotoDelSocio[];
  maximo_de_fotos: number;
  servicios: { tipo: string; nombre: string; precio: string; unidad: { codigo: string; nombre: string } }[];
  ventanas: VentanaDelSocio[];
}

/** Una foto de prueba ya guardada, con su URL firmada. */
export function fotoGuardada(nombre: string): FotoDelSocio {
  return { url: `https://fotos.prueba.invalid/${nombre}.jpg?firma=1`, vence_en: '2026-12-03T18:00:00Z' };
}

/**
 * La lavandería ya registrada de la cuenta: en Durango, sin descripción, con la foto del registro, el lavado estándar a
 * 18.50 por kilo y los jueves de 10:00 a 11:00. Puede tener hasta cinco fotos, el máximo del servidor por omisión.
 */
export const LAVANDERIA_DEL_SOCIO: LavanderiaDelSocio = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0la1',
  nombre: 'Lavandería de prueba del socio',
  descripcion: null,
  direccion: {
    calle: 'Calle de prueba',
    numero: '123',
    colonia: 'Centro',
    codigo_postal: '34000',
    municipio: { codigo: '10005', nombre: 'Durango', estado: 'Durango' },
  },
  foto: fotoGuardada('lavanderia'),
  fotos: [fotoGuardada('lavanderia')],
  maximo_de_fotos: 5,
  servicios: [{ tipo: 'estandar', nombre: 'Lavado estándar', precio: '18.50', unidad: { codigo: 'kg', nombre: 'kilo' } }],
  ventanas: [{ dia_semana: 4, hora_inicio: '10:00', hora_fin: '11:00' }],
};

/** El id_publico que el servidor simulado le da a la lavandería que se registra. */
export const ID_DE_LA_REGISTRADA = '01jd8x6m3v2c9q4w5e7r8t0la2';

/** Una copia que la prueba puede cambiar sin tocar la de las demás. */
export function copia<T>(valor: T): T {
  return JSON.parse(JSON.stringify(valor)) as T;
}

export function respuesta(status: number, cuerpo?: unknown) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => {
      if (cuerpo === undefined) {
        throw new SyntaxError('Respuesta sin cuerpo');
      }
      return cuerpo;
    },
  };
}

export interface Peticion {
  metodo: string;
  ruta: string;
  /**
   * El cuerpo leído: el JSON, o los campos del formulario multipart ya anidados como los lee Laravel
   * ("direccion[calle]" queda en direccion.calle y "servicios[0][tipo]" en servicios[0].tipo), leído como lo manda el
   * teléfono (__tests__/apoyo/formulario.ts): un archivo, con su nombre, su tipo y su contenido.
   */
  cuerpo: unknown;
  /** Si el cuerpo fue un formulario multipart (FormData), como el registro con su foto. */
  multipart: boolean;
  autorizacion: string | null;
}

/**
 * Lo que una prueba pone en lugar de la respuesta normal: si devuelve algo, el servidor contesta eso (una respuesta,
 * una promesa que la prueba suelta cuando quiere, o lanza un error de red); si devuelve undefined, contesta lo normal.
 */
export type Interceptor = (peticion: Peticion) => unknown;

const RUTA_DEL_SOCIO = '/socio/lavanderias';

/** El servidor simulado, con los pedidos y la lavandería de la cuenta, y lo que la app le pidió. */
export class ServidorDeLaLavanderia {
  /** La cuenta de la sesión: la de la lavandería, salvo que la prueba diga otra. */
  cuenta: Cuenta = CUENTAS.lavanderia;
  interceptar: Interceptor | null = null;
  /** Las lavanderías de la cuenta (GET /api/socio/lavanderias): por omisión, la suya ya registrada. */
  lavanderias: LavanderiaDelSocio[] = [copia(LAVANDERIA_DEL_SOCIO)];
  /** Lo que da GET /api/lavanderia/estadisticas. */
  estadisticas = { pedidos_del_dia: 0, pedidos_de_la_semana: 0 };
  readonly fetch: jest.Mock;
  private readonly estados = new Map<string, { codigo: CodigoOperativo; datos: DatosDelPedido }>();
  private readonly entregas = new Map<string, Entrega>();

  constructor(private readonly base: string) {
    this.fetch = jest.fn((url: string, opciones: RequestInit = {}) => this.contestar(url, opciones));
  }

  /** Pone un pedido de la lavandería en ese estado; la lista los da en el orden en que se pusieron. */
  poner(codigo: CodigoOperativo, datos: DatosDelPedido): void {
    this.estados.set(datos.id_publico, { codigo, datos });
  }

  /** Pone un pedido de la lavandería en Entregado, con el momento de su entrega (historial-lavanderia-E01). */
  entregar(datos: DatosDelPedido, entrega: Entrega): void {
    this.poner('entregado', datos);
    this.entregas.set(datos.id_publico, entrega);
  }

  /** El estado del pedido en el servidor. */
  estadoDe(datos: DatosDelPedido): CodigoOperativo | undefined {
    return this.estados.get(datos.id_publico)?.codigo;
  }

  /** Lo que la app pidió, en orden. */
  peticiones(): Peticion[] {
    return this.fetch.mock.calls.map(([url, opciones]: [string, RequestInit | undefined]) =>
      leer(this.base, url, opciones),
    );
  }

  /** Cuántas veces la app pidió la lista de pedidos. */
  consultasDeLaLista(): number {
    return this.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === '/lavanderia/pedidos').length;
  }

  /** Las acciones que la app mandó: "POST /lavanderia/pedidos/<id>/aceptar" y sus cuerpos. */
  acciones(): { ruta: string; cuerpo: unknown }[] {
    return this.peticiones()
      .filter(({ metodo, ruta }) => metodo === 'POST' && ruta.startsWith('/lavanderia/pedidos/'))
      .map(({ ruta, cuerpo }) => ({ ruta, cuerpo }));
  }

  /** Lo que la app mandó a la lavandería de la cuenta: el registro y sus cambios, con su método y su cuerpo. */
  envios(): Pick<Peticion, 'metodo' | 'ruta' | 'cuerpo' | 'multipart'>[] {
    return this.peticiones()
      .filter(({ metodo, ruta }) => metodo !== 'GET' && ruta.startsWith(RUTA_DEL_SOCIO))
      .map(({ metodo, ruta, cuerpo, multipart }) => ({ metodo, ruta, cuerpo, multipart }));
  }

  private async contestar(url: string, opciones: RequestInit) {
    const peticion = await recibir(this.base, url, opciones);
    const interceptada = this.interceptar?.(peticion);
    if (interceptada !== undefined) {
      return interceptada;
    }
    const { metodo, ruta, cuerpo, autorizacion } = peticion;
    if (metodo === 'POST' && ruta === '/sesion') {
      return respuesta(200, { token: TOKEN, usuario: this.cuenta });
    }
    if (autorizacion !== `Bearer ${TOKEN}`) {
      return respuesta(401, { message: 'Unauthenticated.' });
    }
    if (metodo === 'GET' && ruta === '/cuenta') {
      return this.cuenta.rol === 'administracion'
        ? respuesta(403, { message: 'Esta cuenta no tiene acceso.' })
        : respuesta(200, { usuario: this.cuenta });
    }
    if (metodo === 'DELETE' && ruta === '/sesion') {
      // Como el servidor real: la administración no usa el cierre de sesión de la app y su token no se revoca
      // (servidor/routes/api.php:58-60; CuentaDeLosRolesDeLaAppTest.php, registro-E33).
      return this.cuenta.rol === 'administracion'
        ? respuesta(403, { message: 'Esta cuenta no tiene acceso.' })
        : respuesta(204);
    }
    // La pantalla principal del usuario, a donde entra una cuenta de usuario.
    if (metodo === 'GET' && ruta.startsWith('/lavanderias/cercanas')) {
      return respuesta(200, { lavanderias: [] });
    }
    // El catálogo del territorio para la dirección (direcciones-E17).
    if (metodo === 'GET' && ruta === '/catalogos/municipios') {
      return respuesta(200, { municipios: MUNICIPIOS });
    }
    if (this.cuenta.rol !== 'lavanderia') {
      return respuesta(403, { message: 'Esta cuenta no tiene acceso.' });
    }
    if (ruta.startsWith(RUTA_DEL_SOCIO)) {
      return this.lavanderiaDelSocio(metodo, ruta, cuerpo);
    }
    if (metodo === 'GET' && ruta === '/lavanderia/pedidos') {
      const activos = [...this.estados.values()].filter(({ codigo }) => ACTIVOS.includes(codigo));
      return respuesta(200, { pedidos: activos.map(({ codigo, datos }) => pedidoEn(codigo, datos)) });
    }
    if (metodo === 'GET' && ruta === '/lavanderia/historial') {
      return respuesta(200, { pedidos: this.historial() });
    }
    if (metodo === 'GET' && ruta === '/lavanderia/estadisticas') {
      return respuesta(200, this.estadisticas);
    }
    const partes = /^\/lavanderia\/pedidos\/([^/]+)(?:\/(aceptar|rechazar|estado))?$/.exec(ruta);
    const guardado = partes ? this.estados.get(partes[1]) : undefined;
    if (!partes || !guardado) {
      throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
    }
    const accion = partes[2];
    if (metodo === 'GET' && accion === undefined) {
      return respuesta(200, { pedido: pedidoEn(guardado.codigo, guardado.datos) });
    }
    if (metodo !== 'POST' || accion === undefined) {
      throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
    }
    return this.responder(accion, guardado, cuerpo);
  }

  // Solo los Entregados, de la entrega más reciente a la más antigua (historial-lavanderia-E01).
  private historial() {
    return [...this.estados.values()]
      .filter(({ codigo, datos }) => codigo === 'entregado' && this.entregas.has(datos.id_publico))
      .map(({ datos }) => ({ id_publico: datos.id_publico, folio: datos.folio, ...this.entregas.get(datos.id_publico)! }))
      .sort((una, otra) => otra.entregado_en.localeCompare(una.entregado_en));
  }

  private responder(
    accion: string,
    guardado: { codigo: CodigoOperativo; datos: DatosDelPedido },
    cuerpo: unknown,
  ) {
    const comoEsta = pedidoEn(guardado.codigo, guardado.datos);
    if (accion === 'rechazar') {
      const motivo = esObjeto(cuerpo) && typeof cuerpo.motivo === 'string' ? cuerpo.motivo.trim() : '';
      if (motivo === '') {
        return respuesta(422, {
          message: 'Escribe el motivo del rechazo.',
          errors: { motivo: ['Escribe el motivo del rechazo.'] },
        });
      }
    }
    if (accion === 'estado') {
      const estado = esObjeto(cuerpo) ? cuerpo.estado : undefined;
      if (!BOTONES.includes(estado as CodigoOperativo)) {
        return respuesta(422, {
          message: 'Ese no es un botón de la lavandería.',
          errors: { estado: ['Ese no es un botón de la lavandería.'] },
        });
      }
      if (estado !== SIGUIENTE[guardado.codigo]) {
        return respuesta(409, { message: 'Ese no es el paso siguiente del pedido.', pedido: comoEsta });
      }
      guardado.codigo = estado as CodigoOperativo;
      return respuesta(200, { pedido: pedidoEn(guardado.codigo, guardado.datos) });
    }
    if (guardado.codigo !== 'confirmado') {
      return respuesta(409, { message: 'El pedido ya fue respondido.', pedido: comoEsta });
    }
    guardado.codigo = accion === 'aceptar' ? 'aceptado' : 'rechazado';
    return respuesta(200, { pedido: pedidoEn(guardado.codigo, guardado.datos) });
  }

  // /api/socio/lavanderias (alta-de-lavanderias, design.md, "El contrato de la API"). Las validaciones las prueba el
  // servidor; aquí, cada prueba pone con interceptar el 422 que necesita.
  private lavanderiaDelSocio(metodo: string, ruta: string, cuerpo: unknown) {
    if (ruta === RUTA_DEL_SOCIO) {
      if (metodo === 'GET') {
        return respuesta(200, { lavanderias: this.lavanderias });
      }
      if (metodo === 'POST') {
        return this.registrar(esObjeto(cuerpo) ? cuerpo : {});
      }
    }
    const id = ruta.slice(RUTA_DEL_SOCIO.length + 1);
    const propia = this.lavanderias.find((lavanderia) => lavanderia.id_publico === id);
    if (!propia) {
      return respuesta(404, { message: 'No existe la lavandería.' });
    }
    // Con foto, la edición llega por POST con _method=PATCH, porque PHP solo lee los archivos de un POST.
    const edicion = metodo === 'PATCH' || (metodo === 'POST' && esObjeto(cuerpo) && cuerpo._method === 'PATCH');
    if (metodo === 'GET') {
      return respuesta(200, { lavanderia: propia });
    }
    if (!edicion || !esObjeto(cuerpo)) {
      throw new Error(`El servidor falso no esperaba ${metodo} ${ruta}`);
    }
    aplicar(propia, cuerpo);
    return respuesta(200, { lavanderia: propia });
  }

  private registrar(cuerpo: Record<string, unknown>) {
    if (this.lavanderias.length > 0) {
      return respuesta(409, { message: 'Esta cuenta ya tiene una lavandería registrada.' });
    }
    const nueva: LavanderiaDelSocio = {
      id_publico: ID_DE_LA_REGISTRADA,
      nombre: '',
      descripcion: null,
      direccion: { calle: '', numero: '', colonia: '', codigo_postal: '', municipio: null },
      foto: null,
      fotos: [],
      maximo_de_fotos: LAVANDERIA_DEL_SOCIO.maximo_de_fotos,
      servicios: [],
      ventanas: [],
    };
    aplicar(nueva, cuerpo);
    this.lavanderias = [nueva];
    return respuesta(201, { lavanderia: nueva });
  }
}

// La foto nueva que subió la app, con su URL firmada.
function fotoSubida(archivo: ArchivoDelFormulario): FotoDelSocio {
  return { url: `https://fotos.prueba.invalid/${encodeURIComponent(archivo.contenido)}?firma=2`, vence_en: '2026-12-03T19:00:00Z' };
}

// Pone en la lavandería lo que llegó, como lo guardaría el servidor: el municipio con su nombre, la foto con su URL
// firmada, la descripción vacía como ninguna, la lista de fotos en su orden (cada una que ya tiene por su lugar en la
// lista anterior, cada nueva con su URL), cada servicio con su nombre y su unidad, y los días como números.
function aplicar(lavanderia: LavanderiaDelSocio, cuerpo: Record<string, unknown>) {
  if (typeof cuerpo.nombre === 'string') {
    lavanderia.nombre = cuerpo.nombre;
  }
  if (typeof cuerpo.descripcion === 'string') {
    lavanderia.descripcion = cuerpo.descripcion.trim() === '' ? null : cuerpo.descripcion;
  }
  if (Array.isArray(cuerpo.fotos)) {
    const anteriores = lavanderia.fotos;
    lavanderia.fotos = (cuerpo.fotos as unknown[]).map((foto) =>
      esObjeto(foto) ? fotoSubida(foto as unknown as ArchivoDelFormulario) : anteriores[Number(foto)],
    );
    lavanderia.foto = lavanderia.fotos[0] ?? null;
  }
  if (esObjeto(cuerpo.direccion)) {
    const direccion = cuerpo.direccion as Record<string, string>;
    const municipio = MUNICIPIOS.find(({ codigo }) => codigo === direccion.municipio);
    lavanderia.direccion = {
      calle: direccion.calle,
      numero: direccion.numero,
      colonia: direccion.colonia,
      codigo_postal: direccion.codigo_postal,
      municipio: municipio ? { codigo: municipio.codigo, nombre: municipio.nombre, estado: municipio.estado.nombre } : null,
    };
  }
  if (esObjeto(cuerpo.foto)) {
    // La foto sola es la principal: reemplaza a la primera de la lista.
    lavanderia.foto = fotoSubida(cuerpo.foto as unknown as ArchivoDelFormulario);
    lavanderia.fotos = [lavanderia.foto, ...lavanderia.fotos.slice(1)];
  }
  if (Array.isArray(cuerpo.servicios)) {
    lavanderia.servicios = (cuerpo.servicios as { tipo: string; precio: string }[]).map(({ tipo, precio }) => ({
      tipo,
      nombre: NOMBRES_DE_SERVICIO[tipo] ?? tipo,
      precio: Number(precio).toFixed(2),
      unidad: { codigo: 'kg', nombre: 'kilo' },
    }));
  }
  if (Array.isArray(cuerpo.ventanas)) {
    lavanderia.ventanas = (cuerpo.ventanas as { dia_semana: string | number; hora_inicio: string; hora_fin: string }[])
      .map(({ dia_semana, hora_inicio, hora_fin }) => ({ dia_semana: Number(dia_semana), hora_inicio, hora_fin }))
      .sort((una, otra) => una.dia_semana - otra.dia_semana || una.hora_inicio.localeCompare(otra.hora_inicio));
  }
}

function esObjeto(valor: unknown): valor is Record<string, unknown> {
  return typeof valor === 'object' && valor !== null;
}

// Los campos del formulario anidados como los lee Laravel: "servicios[0][tipo]" queda en servicios[0].tipo.
function anidar(partes: ParteDelFormulario[]): Record<string, unknown> {
  const raiz: Record<string, unknown> = {};
  for (const parte of partes) {
    const valor = 'texto' in parte ? parte.texto : parte.archivo;
    const claves = parte.campo.replace(/\]/g, '').split('[');
    let nodo: Record<string, unknown> = raiz;
    claves.forEach((clave, indice) => {
      if (indice === claves.length - 1) {
        nodo[clave] = valor;
        return;
      }
      if (nodo[clave] === undefined) {
        nodo[clave] = /^\d+$/.test(claves[indice + 1]) ? [] : {};
      }
      nodo = nodo[clave] as Record<string, unknown>;
    });
  }
  return raiz;
}

/**
 * La petición que hizo la app, leída como la lee el servidor: su método, su ruta sin la dirección base, su cuerpo (el
 * JSON o los campos del formulario multipart ya anidados), si fue multipart y su autorización. Un formulario se lee al
 * recibirlo (recibir()); antes, su cuerpo queda sin leer. La usa también el servidor simulado del repartidor
 * (__tests__/apoyo/repartidor.ts).
 */
export function leer(base: string, url: string, opciones: RequestInit = {}): Peticion {
  const cabeceras = (opciones.headers ?? {}) as Record<string, string>;
  const multipart = esFormulario(opciones.body);
  let cuerpo: unknown;
  if (typeof opciones.body === 'string') {
    cuerpo = JSON.parse(opciones.body);
  } else if (esFormulario(opciones.body)) {
    const partes = partesLeidas(opciones.body);
    cuerpo = partes ? anidar(partes) : undefined;
  }
  return {
    metodo: opciones.method ?? 'GET',
    ruta: url.slice(base.length),
    cuerpo,
    multipart,
    autorizacion: cabeceras.Authorization ?? null,
  };
}

/**
 * La petición al llegar al servidor: un formulario multipart se lee como lo manda el teléfono, con expo/fetch
 * (__tests__/apoyo/formulario.ts). Si el teléfono no lo sabe mandar, falla como la petición en el teléfono, y la app
 * dice que no hubo conexión.
 */
export async function recibir(base: string, url: string, opciones: RequestInit = {}): Promise<Peticion> {
  if (esFormulario(opciones.body)) {
    await leerFormulario(opciones.body);
  }
  return leer(base, url, opciones);
}

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const almacen = SecureStore as unknown as { __vaciar(): void };
const gps = Location as unknown as { __reiniciar(): void };
const camara = ImagePicker as unknown as { __reiniciar(): void };
const manipulador = ImageManipulator as unknown as { __reiniciar(): void };

/**
 * Prepara cada prueba del archivo con la app recién abierta en esta instalación y el servidor simulado en la dirección
 * base, y deja todo como estaba al terminar. Devuelve el servidor de la prueba en curso.
 */
export function usarElServidorDeLaLavanderia(nombre: string): () => ServidorDeLaLavanderia {
  const base = `http://api.prueba.invalid/${nombre}`;
  const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
  const fetchOriginal = global.fetch;
  const formDataOriginal = global.FormData;
  let servidor: ServidorDeLaLavanderia;

  beforeEach(() => {
    almacen.__vaciar();
    gps.__reiniciar();
    camara.__reiniciar();
    manipulador.__reiniciar();
    // La lista de municipios se pide una vez por sesión de la app: cada prueba abre la app de nuevo.
    olvidarMunicipios();
    process.env.EXPO_PUBLIC_API_URL = base;
    // El inicio de sesión muestra el nombre de la app, que sale de la configuración (base-E09).
    process.env.EXPO_PUBLIC_NOMBRE_APP = `Nombre de prueba ${nombre}`;
    servidor = new ServidorDeLaLavanderia(base);
    global.fetch = servidor.fetch as unknown as typeof fetch;
    // El FormData del teléfono: en Jest, el global es el de Node.
    global.FormData = FormDataDelTelefono;
    // El selector anuncia la elección al lector de pantalla; en Jest no hay lector.
    jest.spyOn(AccessibilityInfo, 'announceForAccessibilityWithOptions').mockImplementation(() => {});
  });

  afterEach(() => {
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
    global.FormData = formDataOriginal;
    jest.restoreAllMocks();
  });

  return () => servidor;
}

/**
 * Abre la app como al tocar su ícono, en esa plataforma, con la sesión guardada de una cuenta que ya entró antes en
 * esta instalación (vio la bienvenida y tiene su token). La cuenta es la del servidor: la de la lavandería, si la
 * prueba no dice otra.
 */
export async function abrirLaAppConSesion(plataforma: Plataforma, ruta = '/'): Promise<void> {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await guardarToken(TOKEN);
  await renderRouter(APP, { initialUrl: ruta });
}

/**
 * Abre la app sin sesión, ya vista la bienvenida: llega al inicio de sesión (bienvenida-E04), o a la ruta que diga la
 * prueba, como el registro del repartidor, que todavía no tiene entrada desde la app (DD-053).
 */
export async function abrirLaAppSinSesion(plataforma: Plataforma, ruta = '/'): Promise<void> {
  jest.replaceProperty(Platform, 'OS', plataforma);
  await comprobarInstalacion();
  await marcarBienvenidaVista();
  await renderRouter(APP, { initialUrl: ruta });
}
