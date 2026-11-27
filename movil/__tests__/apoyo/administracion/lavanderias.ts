// Apoyo de las pruebas de la gestión de lavanderías de la administración (administracion-app-E12 a E16; tareas 4.1 a
// 4.3). No es una prueba: Jest no lo corre como tal (testPathIgnorePatterns en package.json). Son los manejadores de las
// rutas de /administracion/lavanderias para el servidor simulado de la administración
// (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/LavanderiasController.php:
//
// - GET /administracion/lavanderias: todas, por nombre, cada una con su nombre, su estado y su plaza; con ?buscar=, solo
//   las que lo llevan en su nombre, sin distinguir mayúsculas;
// - GET /administracion/lavanderias/{id_publico}: la ficha, con lo que ve y edita su socio, su estado, su plaza y sus
//   transiciones; 404 si no existe;
// - PATCH …/{id_publico}, en JSON, o POST con _method=PATCH en multipart si lleva fotos: pone lo que llegó, como lo
//   guardaría el servidor, y responde la ficha; las validaciones las prueba el servidor, y cada prueba pone con
//   rechazo el 422 que necesita. La lista de fotos se cuenta sobre las de su marca, fotos_leidas, como el servidor: si
//   la marca no es la de las fotos de ahora, 422 en fotos con el mensaje de FotosDeLaLavanderia y nada cambia. La marca
//   de prueba es un texto con las URL de las fotos, no la cifrada del servidor: la app no la lee;
// - POST y DELETE …/{id_publico}/suspension, POST …/{id_publico}/baja: el cambio de estado, con la regla de
//   EstadoDeLaLavanderia; si su estado ya no lo permite, 409 con el mensaje de TransicionNoPermitida y nada cambia.
//
// El 401 y el 403 los responde antes el servidor simulado de la administración, y también el catálogo de municipios
// (GET /catalogos/municipios), que pide el formulario de la edición: como el servidor real, se lo da a la administración
// (adenda del 2026-11-26 de ADR-0022, decisión 6; administracion-app-E14).
import type { ArchivoDelFormulario } from '../formulario';
import {
  copia,
  fotoGuardada,
  MUNICIPIOS,
  respuesta,
  type FotoDelSocio,
  type LavanderiaDelSocio,
  type Peticion,
} from '../lavanderia';
import type { ServidorDeLaAdministracion } from '../administracion';

export type EstadoDeLaLavanderia = 'pendiente' | 'activa' | 'suspendida' | 'baja';

/** Una lavandería en el servidor: lo que ve y edita su socio, su estado, su plaza y si ya tuvo alta. */
export interface LavanderiaDelServidor {
  datos: LavanderiaDelSocio;
  estado: EstadoDeLaLavanderia;
  plaza: string;
  /** Si ya tuvo alta (aprobada_en): al levantar la suspensión vuelve a activa; si no, a pendiente. */
  conAlta: boolean;
}

export const PLAZA_DURANGO = 'Plaza de prueba Durango';
export const PLAZA_TORREON = 'Plaza de prueba Torreón';

export const RUTA_DE_LAS_LAVANDERIAS = '/administracion/lavanderias';

/** El mensaje del 404 de una lavandería que no existe. */
export const NO_EXISTE_LA_LAVANDERIA = 'No existe la lavandería.';

/** El 422 de una lista de fotos que ya no es la que se leyó (FotosDeLaLavanderia::FOTOS_QUE_CAMBIARON). */
export const FOTOS_QUE_CAMBIARON =
  'Las fotos de la lavandería cambiaron desde que abriste la página. Vuelve a abrirla y haz el cambio otra vez.';

/** La marca de prueba de las fotos de una lectura: la ficha la trae como fotos_leidas. */
export function marcaDeLasFotos(lavanderia: LavanderiaDelServidor): string {
  return `lectura de prueba: ${lavanderia.datos.fotos.map(({ url }) => url).join(' | ')}`;
}

/** La ruta de la API de una lavandería: "/administracion/lavanderias/<id_publico>". */
export function rutaDe(lavanderia: LavanderiaDelServidor | string): string {
  return `${RUTA_DE_LAS_LAVANDERIAS}/${typeof lavanderia === 'string' ? lavanderia : lavanderia.datos.id_publico}`;
}

/** Una lavandería de prueba en Durango, con el lavado estándar y los lunes de 09:00 a 11:00, en ese estado y plaza. */
export function lavanderiaDePrueba(
  idPublico: string,
  nombre: string,
  estado: EstadoDeLaLavanderia,
  plaza = PLAZA_DURANGO,
): LavanderiaDelServidor {
  return {
    datos: {
      id_publico: idPublico,
      nombre,
      descripcion: 'Texto de prueba.',
      direccion: {
        calle: 'Calle de Prueba',
        numero: '100',
        colonia: 'Zona Centro',
        codigo_postal: '34000',
        municipio: { codigo: '10005', nombre: 'Durango', estado: 'Durango' },
      },
      foto: fotoGuardada(`${idPublico}-1`),
      fotos: [fotoGuardada(`${idPublico}-1`), fotoGuardada(`${idPublico}-2`)],
      maximo_de_fotos: 5,
      servicios: [
        { tipo: 'estandar', nombre: 'Lavado estándar', precio: '18.50', unidad: { codigo: 'kg', nombre: 'kilo' } },
      ],
      ventanas: [
        { dia_semana: 1, hora_inicio: '09:00', hora_fin: '10:00' },
        { dia_semana: 1, hora_inicio: '10:00', hora_fin: '11:00' },
      ],
    },
    estado,
    plaza,
    conAlta: estado !== 'pendiente',
  };
}

// Las de los mocks de la Propuesta 06, con los nombres de los seeders: una de cada estado, en dos plazas.
export const ACTIVA = lavanderiaDePrueba('01jd8x6m3v2c9q4w5e7r8t0ac1', 'Lavandería de prueba Durango Centro', 'activa');
export const PENDIENTE = lavanderiaDePrueba(
  '01jd8x6m3v2c9q4w5e7r8t0pe1',
  'Lavandería de prueba Durango Norte',
  'pendiente',
);
export const SUSPENDIDA = lavanderiaDePrueba(
  '01jd8x6m3v2c9q4w5e7r8t0su1',
  'Lavandería de prueba suspendida',
  'suspendida',
  PLAZA_TORREON,
);
export const DE_BAJA = lavanderiaDePrueba('01jd8x6m3v2c9q4w5e7r8t0ba1', 'Lavandería de prueba Durango Sur', 'baja');

/** Las transiciones de cada estado, en el orden del contrato (EstadoDeLaLavanderia::puede…()). */
const TRANSICIONES: Record<EstadoDeLaLavanderia, string[]> = {
  pendiente: ['suspender', 'dar_de_baja'],
  activa: ['suspender', 'dar_de_baja'],
  suspendida: ['levantar_suspension', 'dar_de_baja'],
  baja: [],
};

/** La ficha como sale en todas las respuestas que la traen. */
export function fichaDe(lavanderia: LavanderiaDelServidor) {
  const { datos, estado, plaza } = lavanderia;
  return {
    ...datos,
    estado,
    plaza: { nombre: plaza },
    transiciones: TRANSICIONES[estado],
    fotos_leidas: marcaDeLasFotos(lavanderia),
  };
}

/** El mensaje del 409 de una transición que ya no se puede, como TransicionNoPermitida::de(). */
export function transicionNoPermitida(estado: EstadoDeLaLavanderia, accion: string): string {
  return `Una lavandería en estado ${estado} no se puede ${accion}.`;
}

/** Las lavanderías del servidor simulado de la administración, con lo que la app le pidió de ellas. */
export class LavanderiasDelServidor {
  lavanderias: LavanderiaDelServidor[];
  /** El 422 con que el servidor contesta la próxima edición, sin cambiar nada; null, la guarda. */
  rechazo: Record<string, unknown> | null = null;

  constructor(
    private readonly servidor: ServidorDeLaAdministracion,
    lavanderias: LavanderiaDelServidor[] = [ACTIVA, PENDIENTE, SUSPENDIDA, DE_BAJA],
  ) {
    this.lavanderias = lavanderias.map(copia);
    servidor.agregar((peticion) => this.contestar(peticion));
  }

  /** La lavandería del servidor con ese id_publico, como está ahora. */
  de(idPublico: string): LavanderiaDelServidor {
    const encontrada = this.lavanderias.find(({ datos }) => datos.id_publico === idPublico);
    if (!encontrada) {
      throw new Error(`El servidor simulado no tiene la lavandería ${idPublico}`);
    }
    return encontrada;
  }

  /** Lo que la app pidió a las rutas de las lavanderías, sin la autorización: "GET /administracion/lavanderias". */
  pedidas(): string[] {
    return this.servidor
      .peticiones()
      .filter(({ ruta }) => /^\/administracion\/lavanderias(?:[/?]|$)/.test(ruta))
      .map(({ metodo, ruta }) => `${metodo} ${ruta}`);
  }

  /**
   * Lo que la app mandó para cambiar esa lavandería, su edición y sus cambios de estado, con su método, su ruta, si fue
   * multipart y su cuerpo.
   */
  envios(lavanderia: LavanderiaDelServidor) {
    const suya = rutaDe(lavanderia);
    return this.servidor
      .peticiones()
      .filter(({ metodo, ruta }) => metodo !== 'GET' && (ruta === suya || ruta.startsWith(`${suya}/`)))
      .map(({ metodo, ruta, multipart, cuerpo }) => ({ metodo, ruta, multipart, cuerpo }));
  }

  private contestar({ metodo, ruta, cuerpo }: Peticion) {
    const [camino, consulta = ''] = ruta.split('?');
    if (camino === RUTA_DE_LAS_LAVANDERIAS && metodo === 'GET') {
      return this.lista(new URLSearchParams(consulta).get('buscar') ?? '');
    }
    if (!camino.startsWith(`${RUTA_DE_LAS_LAVANDERIAS}/`)) {
      return undefined;
    }
    const [idPublico, accion, ...resto] = camino.slice(RUTA_DE_LAS_LAVANDERIAS.length + 1).split('/');
    if (resto.length > 0) {
      return undefined;
    }
    const lavanderia = this.lavanderias.find(({ datos }) => datos.id_publico === idPublico);
    if (!lavanderia) {
      return respuesta(404, { message: NO_EXISTE_LA_LAVANDERIA });
    }
    if (accion === undefined) {
      // Con fotos, la edición llega por POST con _method=PATCH, porque PHP solo lee los archivos de un POST.
      const edicion = metodo === 'PATCH' || (metodo === 'POST' && esObjeto(cuerpo) && cuerpo._method === 'PATCH');
      if (metodo === 'GET') {
        return respuesta(200, { lavanderia: fichaDe(lavanderia) });
      }
      if (edicion && esObjeto(cuerpo)) {
        if (this.rechazo) {
          return respuesta(422, this.rechazo);
        }
        if (cuerpo.fotos !== undefined && cuerpo.fotos_leidas !== marcaDeLasFotos(lavanderia)) {
          return respuesta(422, { message: FOTOS_QUE_CAMBIARON, errors: { fotos: [FOTOS_QUE_CAMBIARON] } });
        }
        aplicar(lavanderia.datos, cuerpo);
        return respuesta(200, { lavanderia: fichaDe(lavanderia) });
      }
      return undefined;
    }
    if (accion === 'suspension' && metodo === 'POST') {
      return this.cambiar(lavanderia, 'suspender', ['activa', 'pendiente'], () => 'suspendida');
    }
    if (accion === 'suspension' && metodo === 'DELETE') {
      return this.cambiar(lavanderia, 'levantar de una suspensión', ['suspendida'], (actual) =>
        actual.conAlta ? 'activa' : 'pendiente',
      );
    }
    if (accion === 'baja' && metodo === 'POST') {
      return this.cambiar(lavanderia, 'dar de baja', ['pendiente', 'activa', 'suspendida'], () => 'baja');
    }
    return undefined;
  }

  // Todas, por nombre; con la búsqueda, solo las que la llevan en su nombre, sin distinguir mayúsculas.
  private lista(buscar: string) {
    const texto = buscar.trim().toLocaleLowerCase('es');
    const lavanderias = this.lavanderias
      .filter(({ datos }) => texto === '' || datos.nombre.toLocaleLowerCase('es').includes(texto))
      .sort((una, otra) => una.datos.nombre.localeCompare(otra.datos.nombre, 'es'))
      .map(({ datos, estado, plaza }) => ({
        id_publico: datos.id_publico,
        nombre: datos.nombre,
        estado,
        plaza: { nombre: plaza },
      }));
    return respuesta(200, { lavanderias });
  }

  private cambiar(
    lavanderia: LavanderiaDelServidor,
    accion: string,
    desde: EstadoDeLaLavanderia[],
    hacia: (actual: LavanderiaDelServidor) => EstadoDeLaLavanderia,
  ) {
    if (!desde.includes(lavanderia.estado)) {
      return respuesta(409, { message: transicionNoPermitida(lavanderia.estado, accion) });
    }
    lavanderia.estado = hacia(lavanderia);
    return respuesta(200, { lavanderia: fichaDe(lavanderia) });
  }
}

/** Los nombres de los tipos de servicio del catálogo del servidor (tipos_servicio). */
const NOMBRES_DE_SERVICIO: Record<string, string> = {
  estandar: 'Lavado estándar',
  planchado: 'Lavado con planchado',
};

// La foto nueva que subió la app, con su URL firmada.
function fotoSubida(archivo: ArchivoDelFormulario): FotoDelSocio {
  return {
    url: `https://fotos.prueba.invalid/${encodeURIComponent(archivo.contenido)}?firma=2`,
    vence_en: '2026-12-03T19:00:00Z',
  };
}

// Pone en la lavandería lo que llegó, como lo guardaría el servidor, igual que la edición del socio: el municipio con su
// nombre, la descripción vacía como ninguna, la lista de fotos en su orden (cada una que ya tiene por su lugar en la
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
      municipio: municipio
        ? { codigo: municipio.codigo, nombre: municipio.nombre, estado: municipio.estado.nombre }
        : null,
    };
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
