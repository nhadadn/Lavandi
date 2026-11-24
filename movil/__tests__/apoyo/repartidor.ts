// Apoyo de las pruebas del Módulo C, lo que ve y hace el socio repartidor (openspec/changes/repartidor): su registro, la
// espera de su alta, sus pedidos asignados con aceptar, rechazar y sus botones, la evidencia de entrega, su historial y
// sus estadísticas. No es una prueba: Jest no lo corre como tal (testPathIgnorePatterns en package.json).
//
// Abre la app real, con las rutas de movil/app/ (ADR-0017), y simula el servidor con el "Contrato de la API" de
// openspec/changes/repartidor/design.md, el mismo de los comentarios de
// servidor/app/Http/Controllers/Api/RepartidorController.php, PedidosDelRepartidorController.php y
// HistorialDelRepartidorController.php. La apertura de la app y la lectura de las peticiones son las del apoyo del
// Módulo B (__tests__/apoyo/lavanderia.ts), con su mismo token.
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import * as Location from 'expo-location';
import * as SecureStore from 'expo-secure-store';
import { AccessibilityInfo } from 'react-native';

import { FormDataDelTelefono, type ArchivoDelFormulario } from './formulario';
import { CUENTAS, leer, recibir, respuesta, TOKEN, type Cuenta, type Interceptor, type Peticion } from './lavanderia';

export {
  abrirLaAppConSesion,
  abrirLaAppSinSesion,
  copia,
  PLATAFORMAS,
  respuesta,
  TOKEN,
  type Peticion,
  type Plataforma,
} from './lavanderia';

/** La primera pantalla del Módulo C: sus pedidos asignados. */
export const RUTA_DE_LOS_PEDIDOS = '/repartidor/pedidos';
/** La espera de su alta (repartidor-E01 y E03). */
export const RUTA_DE_LA_ESPERA = '/repartidor/espera';
/** El registro, sin sesión. Su entrada desde la app espera DD-053 (repartidor-E04). */
export const RUTA_DEL_REGISTRO = '/registro-de-repartidor';
export const RUTA_DEL_HISTORIAL = '/repartidor/historial';
export const RUTA_DE_LAS_ESTADISTICAS = '/repartidor/estadisticas';

/** La cuenta de un repartidor como la devuelven GET /api/cuenta y POST /api/sesion: el rol dice a qué módulo entra. */
export const CUENTA_DEL_REPARTIDOR = {
  id_publico: '01JD00000000000000000000RP',
  correo: 'repartidor@ejemplo.com',
  rol: 'repartidor',
} as const;

export type CuentaDePrueba = Cuenta | typeof CUENTA_DEL_REPARTIDOR | { id_publico: string; correo: string; rol: string };

export { CUENTAS };

export type EstadoDelRepartidor = 'pendiente' | 'activo' | 'suspendido';

/** Los cinco datos del registro del repartidor ya dado de alta, como los guardó el servidor. */
export const DATOS_DEL_REPARTIDOR = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0rp1',
  nombre: 'Rubén Repartidor',
  telefono: '618 123 4567',
  correo: 'repartidor@ejemplo.com',
  foto: { url: 'https://fotos.prueba.invalid/repartidor.jpg?firma=1', vence_en: '2026-12-03T18:00:00Z' },
  tipo_vehiculo: 'Motocicleta',
};

/** Los 403 de un repartidor que no opera (servidor/app/Repartidores/RepartidorSinAcceso.php). */
export const MENSAJES_SIN_ACCESO = {
  pendiente: 'Tu registro espera la autorización de la administración.',
  suspendido: 'Tu cuenta de repartidor está suspendida.',
} as const;

export type CodigoOperativo =
  | 'confirmado'
  | 'aceptado'
  | 'recolectado'
  | 'en_proceso'
  | 'listo'
  | 'en_entrega'
  | 'entregado';

export type EstadoDeLaOferta = 'ofrecido' | 'aceptado' | 'rechazado';

/**
 * Cómo nombra el servidor cada estado para la app del repartidor: el nombre que ve el usuario, salvo en_entrega, que es
 * "En tránsito" (servidor/app/Http/Resources/PedidoDelRepartidorResource.php).
 */
export const NOMBRES: Record<CodigoOperativo, string> = {
  confirmado: 'Confirmado',
  aceptado: 'Confirmado',
  recolectado: 'Recolectado',
  en_proceso: 'En proceso',
  listo: 'Listo',
  en_entrega: 'En tránsito',
  entregado: 'Entregado',
};

/** Los tres botones del repartidor, por el estado al que llevan (Módulo C #3). Lo demás responde 422. */
const BOTONES: Partial<Record<CodigoOperativo, string>> = {
  recolectado: 'Recolectado',
  en_entrega: 'En tránsito',
  entregado: 'Entregado',
};

/**
 * El paso siguiente del repartidor en cada estado (adenda propuesta a ADR-0021 en design.md): Recolectado desde
 * aceptado, En tránsito desde listo y Entregado desde en_entrega. Entre Recolectado y Listo le toca a la lavandería.
 */
const SIGUIENTE: Partial<Record<CodigoOperativo, CodigoOperativo>> = {
  aceptado: 'recolectado',
  listo: 'en_entrega',
  en_entrega: 'entregado',
};

/** Los de la lista: de confirmado a en_entrega. */
const EN_CURSO: CodigoOperativo[] = ['confirmado', 'aceptado', 'recolectado', 'en_proceso', 'listo', 'en_entrega'];

/** Una dirección de la copia del pedido, como la da el servidor. */
export interface DireccionDePrueba {
  alias: string | null;
  calle: string | null;
  numero_exterior: string | null;
  numero_interior: string | null;
  colonia: string;
  codigo_postal: string | null;
  municipio: string | null;
  referencias: string | null;
  latitud: number;
  longitud: number;
}

/** Lo que distingue a un pedido de otro en las pruebas. */
export interface DatosDelPedido {
  id_publico: string;
  folio: string;
  /** AAAA-MM-DD, el día de la recolección en la hora de la plaza. */
  fecha: string;
  hora_inicio: string;
  hora_fin: string;
  recogida: DireccionDePrueba;
  /** La entrega; sin entrega distinta, el servidor manda la misma de la recogida. */
  entrega: DireccionDePrueba;
  lavanderia: { id_publico: string; nombre: string; calle: string; numero: string; colonia: string; codigo_postal: string };
}

const CASA: DireccionDePrueba = {
  alias: 'Casa',
  calle: 'Calle de prueba',
  numero_exterior: '123',
  numero_interior: '4',
  colonia: 'Colonia de prueba',
  codigo_postal: '34000',
  municipio: 'Durango',
  referencias: 'Portón verde',
  latitud: 24.042,
  longitud: -104.655,
};

const OFICINA: DireccionDePrueba = {
  alias: 'Oficina',
  calle: 'Avenida de prueba',
  numero_exterior: '900',
  numero_interior: null,
  colonia: 'Zona Centro',
  codigo_postal: '34100',
  municipio: 'Durango',
  referencias: null,
  latitud: 24.025,
  longitud: -104.667,
};

const LAVANDERIA_NORTE = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0la1',
  nombre: 'Lavandería de prueba Durango Norte',
  calle: 'Calle de la lavandería',
  numero: '45',
  colonia: 'Del Norte',
  codigo_postal: '34120',
};

/** Un pedido de la mañana del jueves 3 de diciembre, que se recoge en casa y se entrega en la oficina. */
export const PRIMERO: DatosDelPedido = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc1',
  folio: '7KQ2M9XA',
  fecha: '2026-12-03',
  hora_inicio: '11:00',
  hora_fin: '12:00',
  recogida: CASA,
  entrega: OFICINA,
  lavanderia: LAVANDERIA_NORTE,
};

/** Otro, del viernes 4 de diciembre, que se entrega donde se recoge. */
export const SEGUNDO: DatosDelPedido = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc2',
  folio: '3HT8W2QZ',
  fecha: '2026-12-04',
  hora_inicio: '09:00',
  hora_fin: '10:00',
  recogida: OFICINA,
  entrega: OFICINA,
  lavanderia: LAVANDERIA_NORTE,
};

/** Un tercero, que llega después (repartidor-E11). */
export const TERCERO: DatosDelPedido = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0yc3',
  folio: '9PL4X7RB',
  fecha: '2026-12-05',
  hora_inicio: '16:00',
  hora_fin: '17:00',
  recogida: CASA,
  entrega: CASA,
  lavanderia: LAVANDERIA_NORTE,
};

/** Una evidencia como la da el servidor: su tipo y su hora, sin ruta ni URL (repartidor-E23). */
export interface EvidenciaDePrueba {
  tipo: 'entrega';
  tomada_en: string;
}

/** El pedido como lo guarda el servidor simulado. */
interface Guardado {
  codigo: CodigoOperativo;
  oferta: EstadoDeLaOferta;
  datos: DatosDelPedido;
  respondido_en: string | null;
  motivo: string | null;
  evidencias: EvidenciaDePrueba[];
}

/** La hora fija en que el servidor simulado registra las respuestas, los botones y las evidencias. */
export const AHORA = '2026-12-03T17:45:00Z';

/** El pedido en ese estado y con esa oferta, como sale en todas las respuestas de la API del repartidor. */
export function pedidoDelRepartidor(
  codigo: CodigoOperativo,
  oferta: EstadoDeLaOferta,
  datos: DatosDelPedido,
  evidencias: EvidenciaDePrueba[] = [],
) {
  const siguiente = oferta === 'aceptado' ? SIGUIENTE[codigo] : undefined;
  return {
    id_publico: datos.id_publico,
    folio: datos.folio,
    confirmado_en: '2026-12-01T15:30:00Z',
    estado: { codigo, nombre: NOMBRES[codigo] },
    asignacion: {
      estado: oferta,
      ofrecido_en: '2026-12-01T15:40:00Z',
      respondido_en: oferta === 'ofrecido' ? null : AHORA,
    },
    por_responder: oferta === 'ofrecido',
    siguiente: siguiente ? { estado: siguiente, boton: BOTONES[siguiente] } : null,
    zona_horaria: 'America/Mexico_City',
    ventana: {
      inicio: `${datos.fecha}T${datos.hora_inicio}:00-06:00`,
      fin: `${datos.fecha}T${datos.hora_fin}:00-06:00`,
      fecha: datos.fecha,
      hora_inicio: datos.hora_inicio,
      hora_fin: datos.hora_fin,
    },
    recogida: datos.recogida,
    entrega: datos.entrega,
    lavanderia: datos.lavanderia,
    evidencias,
  };
}

/** Una entrega del historial (GET /api/repartidor/historial): el instante en UTC y su fecha en la hora de la plaza. */
export interface EntregaDelHistorial {
  id_publico: string;
  folio: string;
  entregado_en: string;
  fecha: string;
}

/** Los mensajes del registro (servidor/app/Http/Requests/RegistroDeRepartidorRequest.php), los de un dato que falta. */
export const FALTA: Record<string, string> = {
  nombre: 'Escribe tu nombre.',
  telefono: 'Escribe tu teléfono.',
  correo: 'Escribe tu correo.',
  password: 'Escribe una contraseña.',
  foto: 'Toma o elige tu foto de perfil.',
  tipo_vehiculo: 'Escribe tu tipo de vehículo.',
};

const RUTA_DEL_MODULO = '/repartidor/';

/** El servidor simulado, con el repartidor de la cuenta, sus pedidos, y lo que la app le pidió. */
export class ServidorDelRepartidor {
  /** La cuenta de la sesión: la del repartidor, salvo que la prueba diga otra. */
  cuenta: CuentaDePrueba = CUENTA_DEL_REPARTIDOR;
  /** El estado del repartidor de la cuenta: activo y con su plaza, salvo que la prueba diga otro. */
  estado: EstadoDelRepartidor = 'activo';
  /** Sus cinco datos, como los da el perfil. */
  datos: Omit<typeof DATOS_DEL_REPARTIDOR, 'foto'> & { foto: { url: string; vence_en: string } | null } = {
    ...DATOS_DEL_REPARTIDOR,
  };
  interceptar: Interceptor | null = null;
  /** Lo que da GET /api/repartidor/historial, de la más reciente a la más antigua. */
  historial: EntregaDelHistorial[] = [];
  /** Lo que da GET /api/repartidor/estadisticas. */
  estadisticas = { entregas_del_dia: 0, entregas_de_la_semana: 0 };
  readonly fetch: jest.Mock;
  private readonly pedidos = new Map<string, Guardado>();

  constructor(private readonly base: string) {
    this.fetch = jest.fn((url: string, opciones: RequestInit = {}) => this.contestar(url, opciones));
  }

  /** Le ofrece un pedido al repartidor, en ese estado (la oferta se siembra en la base, DD-014). */
  ofrecer(datos: DatosDelPedido, codigo: CodigoOperativo = 'aceptado'): void {
    this.poner(codigo, datos, 'ofrecido');
  }

  /** Pone un pedido suyo en ese estado y con esa oferta; la lista los da en el orden en que se pusieron. */
  poner(codigo: CodigoOperativo, datos: DatosDelPedido, oferta: EstadoDeLaOferta = 'aceptado'): void {
    const anterior = this.pedidos.get(datos.id_publico);
    this.pedidos.set(datos.id_publico, {
      codigo,
      oferta,
      datos,
      respondido_en: oferta === 'ofrecido' ? null : AHORA,
      motivo: null,
      evidencias: anterior?.evidencias ?? [],
    });
  }

  /** Quita el pedido: se lo asignaron a otro, o ya no está en la base. */
  quitar(datos: DatosDelPedido): void {
    this.pedidos.delete(datos.id_publico);
  }

  /** El estado del pedido en el servidor. */
  estadoDe(datos: DatosDelPedido): CodigoOperativo | undefined {
    return this.pedidos.get(datos.id_publico)?.codigo;
  }

  /** La oferta del pedido en el servidor. */
  ofertaDe(datos: DatosDelPedido): EstadoDeLaOferta | undefined {
    return this.pedidos.get(datos.id_publico)?.oferta;
  }

  /** El motivo con que la rechazó. */
  motivoDe(datos: DatosDelPedido): string | null | undefined {
    return this.pedidos.get(datos.id_publico)?.motivo;
  }

  /** Las evidencias del pedido. */
  evidenciasDe(datos: DatosDelPedido): EvidenciaDePrueba[] {
    return this.pedidos.get(datos.id_publico)?.evidencias ?? [];
  }

  /** Lo que la app pidió, en orden. */
  peticiones(): Peticion[] {
    return this.fetch.mock.calls.map(([url, opciones]: [string, RequestInit | undefined]) =>
      leer(this.base, url, opciones),
    );
  }

  /** Cuántas veces la app pidió la lista de pedidos. */
  consultasDeLaLista(): number {
    return this.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === '/repartidor/pedidos').length;
  }

  /** Cuántas veces la app pidió el perfil del repartidor. */
  consultasDelPerfil(): number {
    return this.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === '/repartidor/perfil').length;
  }

  /** Las acciones que la app mandó sobre un pedido: "/repartidor/pedidos/<id>/aceptar", con su cuerpo. */
  acciones(): Pick<Peticion, 'ruta' | 'cuerpo' | 'multipart'>[] {
    return this.peticiones()
      .filter(({ metodo, ruta }) => metodo === 'POST' && ruta.startsWith('/repartidor/pedidos/'))
      .map(({ ruta, cuerpo, multipart }) => ({ ruta, cuerpo, multipart }));
  }

  /** Los registros que la app mandó, con su cuerpo y su autorización. */
  registros(): Pick<Peticion, 'cuerpo' | 'multipart' | 'autorizacion'>[] {
    return this.peticiones()
      .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === '/repartidor/registro')
      .map(({ cuerpo, multipart, autorizacion }) => ({ cuerpo, multipart, autorizacion }));
  }

  /** El repartidor como sale en el registro y en el perfil. */
  perfil() {
    return {
      ...this.datos,
      estado: this.estado,
      puede_operar: this.estado === 'activo',
      plaza: this.estado === 'pendiente' ? null : { nombre: 'Durango' },
    };
  }

  private async contestar(url: string, opciones: RequestInit) {
    const peticion = await recibir(this.base, url, opciones);
    const interceptada = this.interceptar?.(peticion);
    if (interceptada !== undefined) {
      return interceptada;
    }
    const { metodo, ruta, cuerpo, autorizacion } = peticion;
    // Pública: el registro abre su sesión (repartidor-E01).
    if (metodo === 'POST' && ruta === '/repartidor/registro') {
      return this.registrar(esObjeto(cuerpo) ? cuerpo : {});
    }
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
      return respuesta(204);
    }
    if (!ruta.startsWith(RUTA_DEL_MODULO) || this.cuenta.rol !== 'repartidor') {
      throw new Error(`El servidor falso del repartidor no esperaba ${metodo} ${ruta}`);
    }
    if (metodo === 'GET' && ruta === '/repartidor/perfil') {
      return respuesta(200, { repartidor: this.perfil() });
    }
    // Lo demás, solo si opera (repartidor-opera): uno pendiente o suspendido recibe 403 con su estado (E03 y E06).
    if (this.estado !== 'activo') {
      return respuesta(403, { message: MENSAJES_SIN_ACCESO[this.estado], estado: this.estado });
    }
    if (metodo === 'GET' && ruta === '/repartidor/pedidos') {
      const enCurso = [...this.pedidos.values()].filter(
        ({ codigo, oferta }) => EN_CURSO.includes(codigo) && oferta !== 'rechazado',
      );
      return respuesta(200, { pedidos: enCurso.map((guardado) => this.comoSale(guardado)) });
    }
    if (metodo === 'GET' && ruta === '/repartidor/historial') {
      return respuesta(200, { pedidos: this.historial });
    }
    if (metodo === 'GET' && ruta === '/repartidor/estadisticas') {
      return respuesta(200, this.estadisticas);
    }
    const partes = /^\/repartidor\/pedidos\/([^/]+)(?:\/(aceptar|rechazar|estado))?$/.exec(ruta);
    if (!partes) {
      throw new Error(`El servidor falso del repartidor no esperaba ${metodo} ${ruta}`);
    }
    const accion = partes[2];
    // El motivo y el estado se validan antes de buscar el pedido.
    if (metodo === 'POST' && accion === 'rechazar' && motivoDe(cuerpo) === '') {
      return respuesta(422, {
        message: 'Escribe el motivo del rechazo.',
        errors: { motivo: ['Escribe el motivo del rechazo.'] },
      });
    }
    if (metodo === 'POST' && accion === 'estado') {
      const invalido = validarElBoton(cuerpo);
      if (invalido) {
        return invalido;
      }
    }
    const guardado = this.pedidos.get(partes[1]);
    if (!guardado || guardado.oferta === 'rechazado') {
      return respuesta(404, { message: 'No existe el pedido.' });
    }
    if (metodo === 'GET' && accion === undefined) {
      return respuesta(200, { pedido: this.comoSale(guardado) });
    }
    if (metodo !== 'POST' || accion === undefined) {
      throw new Error(`El servidor falso del repartidor no esperaba ${metodo} ${ruta}`);
    }
    return this.responder(accion, guardado, cuerpo);
  }

  private comoSale({ codigo, oferta, datos, evidencias }: Guardado) {
    return pedidoDelRepartidor(codigo, oferta, datos, evidencias);
  }

  private responder(accion: string, guardado: Guardado, cuerpo: unknown) {
    if (accion === 'aceptar' || accion === 'rechazar') {
      if (guardado.oferta !== 'ofrecido') {
        return respuesta(409, { message: 'La oferta ya fue respondida.', pedido: this.comoSale(guardado) });
      }
      guardado.respondido_en = AHORA;
      if (accion === 'aceptar') {
        guardado.oferta = 'aceptado';
        return respuesta(200, { pedido: this.comoSale(guardado) });
      }
      guardado.oferta = 'rechazado';
      guardado.motivo = motivoDe(cuerpo);
      return respuesta(200, {
        asignacion: { pedido: guardado.datos.id_publico, estado: 'rechazado', motivo: guardado.motivo, respondido_en: AHORA },
      });
    }
    const estado = (cuerpo as Record<string, unknown>).estado as CodigoOperativo;
    if (guardado.oferta === 'ofrecido') {
      return respuesta(409, { message: 'Primero acepta el pedido.', pedido: this.comoSale(guardado) });
    }
    if (estado !== SIGUIENTE[guardado.codigo]) {
      return respuesta(409, { message: 'Ese no es el paso siguiente del pedido.', pedido: this.comoSale(guardado) });
    }
    guardado.codigo = estado;
    if (esObjeto((cuerpo as Record<string, unknown>).foto)) {
      guardado.evidencias = [...guardado.evidencias, { tipo: 'entrega', tomada_en: AHORA }];
    }
    return respuesta(200, { pedido: this.comoSale(guardado) });
  }

  /**
   * El registro (repartidor-E01 y E02): los seis datos son obligatorios; sin alguno, 422 con su mensaje y sin cuenta.
   * Las demás validaciones las prueba el servidor; aquí, cada prueba pone con interceptar el 422 que necesita. Es
   * público para que una prueba suelte el registro cuando quiera.
   */
  registrar(cuerpo: Record<string, unknown>) {
    const faltan = Object.keys(FALTA).filter((campo) =>
      campo === 'foto' ? !esObjeto(cuerpo.foto) : typeof cuerpo[campo] !== 'string' || cuerpo[campo] === '',
    );
    if (faltan.length > 0) {
      return respuesta(422, {
        message: FALTA[faltan[0]],
        errors: Object.fromEntries(faltan.map((campo) => [campo, [FALTA[campo]]])),
      });
    }
    const foto = cuerpo.foto as unknown as ArchivoDelFormulario;
    this.cuenta = { id_publico: '01JD00000000000000000000RN', correo: String(cuerpo.correo), rol: 'repartidor' };
    this.estado = 'pendiente';
    this.datos = {
      id_publico: '01jd8x6m3v2c9q4w5e7r8t0rp2',
      nombre: String(cuerpo.nombre),
      telefono: String(cuerpo.telefono),
      correo: String(cuerpo.correo),
      foto: { url: `https://fotos.prueba.invalid/${encodeURIComponent(foto.contenido)}?firma=2`, vence_en: '2026-12-03T19:00:00Z' },
      tipo_vehiculo: String(cuerpo.tipo_vehiculo),
    };
    return respuesta(201, { token: TOKEN, usuario: this.cuenta, repartidor: this.perfil() });
  }
}

function esObjeto(valor: unknown): valor is Record<string, unknown> {
  return typeof valor === 'object' && valor !== null;
}

function motivoDe(cuerpo: unknown): string {
  return esObjeto(cuerpo) && typeof cuerpo.motivo === 'string' ? cuerpo.motivo.trim() : '';
}

// El estado tiene que ser uno de los tres botones; la foto solo va con Entregado.
function validarElBoton(cuerpo: unknown) {
  const estado = esObjeto(cuerpo) ? cuerpo.estado : undefined;
  if (typeof estado !== 'string' || !(estado in BOTONES)) {
    return respuesta(422, {
      message: 'Ese no es un botón del repartidor.',
      errors: { estado: ['Ese no es un botón del repartidor.'] },
    });
  }
  if (esObjeto(cuerpo) && esObjeto(cuerpo.foto) && estado !== 'entregado') {
    return respuesta(422, {
      message: 'La foto de evidencia se toma al marcar Entregado.',
      errors: { foto: ['La foto de evidencia se toma al marcar Entregado.'] },
    });
  }
  return undefined;
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
export function usarElServidorDelRepartidor(nombre: string): () => ServidorDelRepartidor {
  const base = `http://api.prueba.invalid/${nombre}`;
  const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
  const nombreOriginal = process.env.EXPO_PUBLIC_NOMBRE_APP;
  const fetchOriginal = global.fetch;
  const formDataOriginal = global.FormData;
  let servidor: ServidorDelRepartidor;

  beforeEach(() => {
    almacen.__vaciar();
    gps.__reiniciar();
    camara.__reiniciar();
    manipulador.__reiniciar();
    process.env.EXPO_PUBLIC_API_URL = base;
    // El inicio de sesión muestra el nombre de la app, que sale de la configuración (base-E09).
    process.env.EXPO_PUBLIC_NOMBRE_APP = `Nombre de prueba ${nombre}`;
    servidor = new ServidorDelRepartidor(base);
    global.fetch = servidor.fetch as unknown as typeof fetch;
    // El FormData del teléfono: en Jest, el global es el de Node.
    global.FormData = FormDataDelTelefono;
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
