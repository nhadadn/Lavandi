// Apoyo de las pruebas de la gestión de repartidores en la app (F-D02; administracion-app-E19 a E23). No es una prueba:
// Jest no lo corre como tal (testPathIgnorePatterns en package.json). Son los manejadores que la prueba registra en el
// servidor simulado de la administración (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/RepartidoresController.php y PlazasController.php, con la forma de
// sus respuestas reales (servidor/tests/Contratos/administracion/; __tests__/administracion/contratos.test.ts):
//
// - GET /administracion/repartidores, con cada uno solo en su resumen (su nombre, su estado y su plaza);
//   GET /administracion/repartidores/{id_publico}, la ficha con sus transiciones; y GET /administracion/plazas;
// - POST …/alta con la plaza, POST y DELETE …/suspension y POST …/baja, con las transiciones de
//   App\Repartidores\EstadoDelRepartidor y el 409 con el mensaje de App\Repartidores\TransicionNoPermitida;
// - PATCH /administracion/repartidores/{id_publico}, también en multipart por POST con _method=PATCH, que pone lo que
//   llegó. Las validaciones las prueba el servidor; aquí, cada prueba pone con interceptar el 422 que necesita;
// - 404 con "No existe el repartidor." al que no existe.
//
// Los datos son de prueba, con los nombres de los seeders y de las imágenes de la Propuesta 06.
import { copia, respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';
import type { ArchivoDelFormulario } from '../formulario';

export type EstadoDelRepartidor = 'pendiente' | 'activo' | 'suspendido' | 'baja';

/** Una foto como sale en la API: solo su URL firmada y cuándo vence. */
export interface FotoDelRepartidor {
  url: string;
  vence_en: string;
}

/** El repartidor como lo guarda el servidor simulado: lo de su ficha, sin las transiciones, que salen de su estado. */
export interface RepartidorGuardado {
  id_publico: string;
  nombre: string | null;
  telefono: string | null;
  correo: string;
  foto: FotoDelRepartidor | null;
  tipo_vehiculo: string | null;
  estado: EstadoDelRepartidor;
  plaza: { nombre: string } | null;
}

/** Una foto de prueba ya guardada, con su URL firmada. */
export function fotoGuardada(nombre: string): FotoDelRepartidor {
  return { url: `https://fotos.prueba.invalid/${nombre}.jpg?firma=1`, vence_en: '2026-12-03T18:00:00Z' };
}

/** Las plazas del alta, como las da GET /api/administracion/plazas: por nombre, en orden alfabético. */
export const PLAZAS = [{ nombre: 'Plaza de prueba Durango' }, { nombre: 'Plaza de prueba Torreón' }];

export const ACTIVO: RepartidorGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ra1',
  nombre: 'Repartidor de prueba 1',
  telefono: '618 000 0001',
  correo: 'repartidor1@prueba.test',
  foto: fotoGuardada('repartidor1'),
  tipo_vehiculo: 'Motocicleta',
  estado: 'activo',
  plaza: { nombre: 'Plaza de prueba Durango' },
};

export const SUSPENDIDO: RepartidorGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ra2',
  nombre: 'Repartidor de prueba 2',
  telefono: '618 000 0002',
  correo: 'repartidor2@prueba.test',
  foto: fotoGuardada('repartidor2'),
  tipo_vehiculo: 'Bicicleta',
  estado: 'suspendido',
  plaza: { nombre: 'Plaza de prueba Torreón' },
};

/** Un registro pendiente, que nunca se dio de alta: sin plaza y sin foto. */
export const PENDIENTE: RepartidorGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ra3',
  nombre: 'Repartidor de prueba 3',
  telefono: '618 000 0003',
  correo: 'repartidor3@prueba.test',
  foto: null,
  tipo_vehiculo: 'Motocicleta',
  estado: 'pendiente',
  plaza: null,
};

export const OTRO_PENDIENTE: RepartidorGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ra4',
  nombre: 'Repartidor de prueba 4',
  telefono: '618 000 0004',
  correo: 'repartidor4@prueba.test',
  foto: null,
  tipo_vehiculo: 'Automóvil',
  estado: 'pendiente',
  plaza: null,
};

export const DE_BAJA: RepartidorGuardado = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0ra5',
  nombre: 'Repartidor de prueba 5',
  telefono: '618 000 0005',
  correo: 'repartidor5@prueba.test',
  foto: null,
  tipo_vehiculo: 'Motocicleta',
  estado: 'baja',
  plaza: { nombre: 'Plaza de prueba Durango' },
};

/** Un ULID que no es de ningún repartidor. */
export const QUE_NO_EXISTE = '01jd8x6m3v2c9q4w5e7r8t0rzz';

/**
 * Los repartidores del servidor, del registro más nuevo al más viejo, como los da GET repartidores: dos pendientes sin
 * plaza, un activo y uno suspendido, en dos plazas, y uno de baja.
 */
export const REPARTIDORES: RepartidorGuardado[] = [ACTIVO, PENDIENTE, SUSPENDIDO, OTRO_PENDIENTE, DE_BAJA];

export const NO_EXISTE = 'No existe el repartidor.';

/** El 409 de App\Repartidores\TransicionNoPermitida, con el estado como lo guarda la base. */
export function transicionNoPermitida(estado: EstadoDelRepartidor, accion: string): string {
  return `Un repartidor en estado ${estado} no se puede ${accion}.`;
}

/**
 * Las transiciones que permite cada estado, como los puede…() de App\Repartidores\EstadoDelRepartidor, en el orden de
 * los botones del panel.
 */
export function transicionesDe(estado: EstadoDelRepartidor): string[] {
  const permite = {
    dar_de_alta: estado === 'pendiente',
    suspender: estado === 'activo',
    levantar_suspension: estado === 'suspendido',
    // El pendiente de aquí nunca se dio de alta (repartidor-E31).
    dar_de_baja: estado !== 'baja',
  };
  return Object.entries(permite)
    .filter(([, si]) => si)
    .map(([transicion]) => transicion);
}

/** La ficha del repartidor, como sale en todas las respuestas que la traen. */
export function comoSale(repartidor: RepartidorGuardado) {
  return { ...repartidor, transiciones: transicionesDe(repartidor.estado) };
}

/** El repartidor en la lista: solo su id_publico, su nombre, su estado y su plaza (resumen() de su recurso). */
export function enLaLista({ id_publico, nombre, estado, plaza }: RepartidorGuardado) {
  return { id_publico, nombre, estado, plaza };
}

const RUTA = '/administracion/repartidores';

/** Los repartidores y las plazas del servidor simulado, con lo que la app les mandó. */
export class RepartidoresDelServidor {
  readonly repartidores: RepartidorGuardado[];
  plazas = copia(PLAZAS);

  constructor(iniciales: RepartidorGuardado[] = REPARTIDORES) {
    this.repartidores = copia(iniciales);
  }

  /** El repartidor como está ahora en el servidor. */
  de(idPublico: string): RepartidorGuardado {
    const repartidor = this.repartidores.find((guardado) => guardado.id_publico === idPublico);
    if (!repartidor) {
      throw new Error(`El servidor simulado no tiene al repartidor ${idPublico}`);
    }
    return repartidor;
  }

  /** Cambia al repartidor en el servidor, como si otra sesión lo hubiera hecho. */
  cambiar(idPublico: string, cambios: Partial<RepartidorGuardado>): void {
    Object.assign(this.de(idPublico), cambios);
  }

  /** Contesta lo de /administracion/repartidores y /administracion/plazas; lo demás, undefined. */
  readonly manejador = ({ metodo, ruta, cuerpo }: Peticion): unknown => {
    if (metodo === 'GET' && ruta === '/administracion/plazas') {
      return respuesta(200, { plazas: this.plazas });
    }
    if (metodo === 'GET' && ruta === RUTA) {
      return respuesta(200, { repartidores: this.repartidores.map(enLaLista) });
    }
    const partes = /^\/administracion\/repartidores\/([^/]+)(?:\/(alta|suspension|baja))?$/.exec(ruta);
    if (!partes) {
      return undefined;
    }
    const [, id, accion] = partes;
    const repartidor = this.repartidores.find((guardado) => guardado.id_publico === decodeURIComponent(id));
    if (!repartidor) {
      return respuesta(404, { message: NO_EXISTE });
    }
    if (accion === undefined) {
      return this.ficha(repartidor, metodo, cuerpo);
    }
    if (accion === 'alta' && metodo === 'POST') {
      return this.darDeAlta(repartidor, cuerpo);
    }
    if (accion === 'suspension' && metodo === 'POST') {
      return this.transicion(repartidor, 'suspender', 'activo', { estado: 'suspendido' });
    }
    if (accion === 'suspension' && metodo === 'DELETE') {
      return this.transicion(repartidor, 'levantar de una suspensión', 'suspendido', { estado: 'activo' });
    }
    if (accion === 'baja' && metodo === 'POST') {
      return transicionesDe(repartidor.estado).includes('dar_de_baja')
        ? this.aplicar(repartidor, { estado: 'baja' })
        : respuesta(409, { message: transicionNoPermitida(repartidor.estado, 'dar de baja') });
    }
    return undefined;
  };

  // GET de la ficha, o la edición: PATCH, o POST con _method=PATCH si lleva foto.
  private ficha(repartidor: RepartidorGuardado, metodo: string, cuerpo: unknown) {
    if (metodo === 'GET') {
      return respuesta(200, { repartidor: comoSale(repartidor) });
    }
    const datos = esObjeto(cuerpo) ? cuerpo : {};
    if (metodo !== 'PATCH' && !(metodo === 'POST' && datos._method === 'PATCH')) {
      return undefined;
    }
    for (const campo of ['nombre', 'telefono', 'correo', 'tipo_vehiculo'] as const) {
      if (typeof datos[campo] === 'string') {
        repartidor[campo] = (datos[campo] as string).trim();
      }
    }
    if (esObjeto(datos.foto)) {
      const archivo = datos.foto as unknown as ArchivoDelFormulario;
      repartidor.foto = {
        url: `https://fotos.prueba.invalid/${encodeURIComponent(archivo.contenido)}?firma=2`,
        vence_en: '2026-12-03T19:00:00Z',
      };
    }
    return respuesta(200, { repartidor: comoSale(repartidor) });
  }

  // Como PlazasDelAlta::laElegida(), antes del cambio de estado: sin plaza, o una que no es del alta, 422.
  private darDeAlta(repartidor: RepartidorGuardado, cuerpo: unknown) {
    const plaza = esObjeto(cuerpo) && typeof cuerpo.plaza === 'string' ? cuerpo.plaza.trim() : '';
    if (plaza === '') {
      return respuesta(422, { message: 'Elige la plaza.', errors: { plaza: ['Elige la plaza.'] } });
    }
    if (!this.plazas.some(({ nombre }) => nombre === plaza)) {
      const mensaje = 'Esa plaza no está entre las del alta.';
      return respuesta(422, { message: mensaje, errors: { plaza: [mensaje] } });
    }
    return this.transicion(repartidor, 'dar de alta', 'pendiente', { estado: 'activo', plaza: { nombre: plaza } });
  }

  private transicion(
    repartidor: RepartidorGuardado,
    accion: string,
    desde: EstadoDelRepartidor,
    cambios: Partial<RepartidorGuardado>,
  ) {
    if (repartidor.estado !== desde) {
      return respuesta(409, { message: transicionNoPermitida(repartidor.estado, accion) });
    }
    return this.aplicar(repartidor, cambios);
  }

  private aplicar(repartidor: RepartidorGuardado, cambios: Partial<RepartidorGuardado>) {
    Object.assign(repartidor, cambios);
    return respuesta(200, { repartidor: comoSale(repartidor) });
  }
}

/**
 * Prepara cada prueba del archivo con los repartidores del servidor simulado de la administración, ya registrados en
 * él. Devuelve los de la prueba en curso. Va después de usarElServidorDeLaAdministracion().
 */
export function usarLosRepartidores(
  servidor: () => ServidorDeLaAdministracion,
  iniciales: RepartidorGuardado[] = REPARTIDORES,
): () => RepartidoresDelServidor {
  let repartidores: RepartidoresDelServidor;
  beforeEach(() => {
    repartidores = new RepartidoresDelServidor(iniciales);
    servidor().agregar(repartidores.manejador);
  });
  return () => repartidores;
}

/** Lo que la app mandó a los repartidores, sin las consultas: "POST /administracion/repartidores/<id>/baja". */
export function accionesMandadas(servidor: ServidorDeLaAdministracion): { metodo: string; ruta: string; cuerpo: unknown }[] {
  return servidor
    .peticiones()
    .filter(({ metodo, ruta }) => metodo !== 'GET' && ruta.startsWith(RUTA))
    .map(({ metodo, ruta, cuerpo }) => ({ metodo, ruta, cuerpo }));
}

/** Cuántas veces la app pidió esa ruta con GET. */
export function consultas(servidor: ServidorDeLaAdministracion, ruta: string): number {
  return servidor.peticiones().filter((peticion) => peticion.metodo === 'GET' && peticion.ruta === ruta).length;
}

function esObjeto(valor: unknown): valor is Record<string, unknown> {
  return typeof valor === 'object' && valor !== null;
}
