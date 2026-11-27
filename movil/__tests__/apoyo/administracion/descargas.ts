// Apoyo de las pruebas de las descargas en CSV y en Excel de la administración (administracion-app-E56 a E60; tareas
// 20.1 a 20.3). No es una prueba: Jest no lo corre como tal (testPathIgnorePatterns en package.json). Son los
// manejadores de las rutas de /administracion/descargas para el servidor simulado de la administración
// (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/DescargasController.php:
//
// - POST /administracion/descargas: 422 a un reporte que no es de los doce, a una fecha que falta y al rango al revés
//   (exportacion-E06), y a la granularidad que falta salvo en la conciliación, sin crear nada; si no, 202 con la descarga
//   de la cuenta de la sesión, generándose (o como diga estadoAlNacer), con el rango de su archivo ya ajustado a
//   periodos completos, como App\Indicadores\Rango;
// - GET /administracion/descargas: las vivas de la cuenta, de la más nueva a la más vieja, y sus días de vida;
// - GET /administracion/descargas/{id_publico}: la descarga, o 404 si no existe, ya se borró o es de otra cuenta;
// - GET /administracion/descargas/{id_publico}/{formato}: el contenido del archivo, o 404 como arriba, o con el formato
//   que no es csv ni xlsx, o si no está lista.
//
// El 401 y el 403 los responde antes el servidor simulado de la administración. El archivo lo pide expo-file-system y no
// fetch: bajarConElServidor() pone en File.downloadFileAsync una simulación que pasa por el mismo servidor, con los
// encabezados que mandó la app, y escribe en la caché lo que el servidor entregó.
import { Directory, File, Paths } from 'expo-file-system';

import { CUENTA_DE_LA_ADMINISTRACION, respuesta, type Manejador, type ServidorDeLaAdministracion } from '../administracion';

type Formato = 'csv' | 'xlsx';
type Estado = 'generandose' | 'lista' | 'no_se_pudo';

/** Los doce reportes que se descargan, con el título que les da su exportador (servidor/app/Exportacion/Exportadores/). */
export const REPORTES: Record<string, string> = {
  'pedidos-totales': 'Pedidos totales',
  'pedidos-por-estado': 'Pedidos por estado',
  'usuarios-activos': 'Usuarios activos',
  'usuarios-que-vuelven-a-pedir': 'Usuarios que vuelven a pedir',
  'monto-promedio-por-pedido': 'Monto promedio por pedido',
  'calificacion-por-lavanderia': 'Calificación promedio por lavandería',
  'entregas-por-repartidor': 'Entregas completadas por repartidor',
  'ingresos-brutos': 'Ingresos brutos',
  'pedidos-por-colonia': 'Pedidos por colonia',
  'pedidos-por-zona': 'Pedidos por zona de servicio',
  'ingresos-por-periodo': 'Ingresos por periodo',
  'conciliacion-con-la-pasarela': 'Conciliación con la pasarela',
};

const CONCILIACION = 'conciliacion-con-la-pasarela';

// Los mensajes del servidor (App\Exportacion\CreacionDeDescarga y App\Validacion\RangoDeFechas).
export const MENSAJE_DEL_REPORTE = 'Ese no es un reporte que se pueda descargar.';
export const MENSAJE_DEL_RANGO_AL_REVES = 'La fecha de fin no puede ser anterior a la de inicio.';
export const MENSAJE_DE_LA_GRANULARIDAD = 'Elige ver el reporte por día, por semana o por mes.';
export const NO_EXISTE_LA_DESCARGA = 'No existe la descarga.';
export const NO_EXISTE_EL_ARCHIVO = 'No existe ese archivo.';

/** Una descarga en el servidor: lo que da la API, de quién es, si sigue viva y el contenido de sus dos archivos. */
export interface DescargaDelServidor {
  id_publico: string;
  reporte: string;
  titulo: string;
  /**
   * El rango que cubre su archivo, AAAA-MM-DD, ya ajustado a periodos completos: el de su nombre. null si el servidor no
   * lo pudo leer del nombre (ExportadorDeIndicador::diasDelArchivo()).
   */
  desde: string | null;
  hasta: string | null;
  pedida_en: string;
  estado: Estado;
  archivos: Record<Formato, string>;
  /** El id_publico de la cuenta que la pidió. */
  cuenta: string;
  /** false: ya pasó sus días de vida y se borró (exportacion-E12). */
  viva: boolean;
  contenido: Record<Formato, string>;
}

/**
 * Una descarga de la cuenta de administración, con lo que la prueba cambie. Por omisión, lista, del 26 de octubre al 29
 * de noviembre de 2026, con su rango en el nombre de sus archivos.
 */
export function descarga(datos: Partial<DescargaDelServidor> & { id_publico: string }): DescargaDelServidor {
  const reporte = datos.reporte ?? 'pedidos-totales';
  const desde = datos.desde ?? '2026-10-26';
  const hasta = datos.hasta ?? '2026-11-29';
  return {
    reporte,
    titulo: REPORTES[reporte],
    desde,
    hasta,
    pedida_en: new Date().toISOString(),
    estado: 'lista',
    archivos: {
      csv: `${reporte}-${desde}-al-${hasta}.csv`,
      xlsx: `${reporte}-${desde}-al-${hasta}.xlsx`,
    },
    cuenta: CUENTA_DE_LA_ADMINISTRACION.id_publico,
    viva: true,
    contenido: { csv: contenidoDelCsv(reporte), xlsx: `XLSX de ${reporte}` },
    ...datos,
  };
}

/** Un CSV como los del servidor: con la marca de orden de bytes, acentos y eñes (exportacion-E07). */
export function contenidoDelCsv(reporte: string): string {
  return `﻿Periodo,Pedidos\r\nSemana del 26/10/2026,3\r\nColonia Peñón,5\r\n${reporte}\r\n`;
}

/** La descarga como la manda la API, sin lo que solo sabe el servidor. */
function comoLaDaLaApi({ id_publico, reporte, titulo, desde, hasta, pedida_en, estado, archivos }: DescargaDelServidor) {
  return { id_publico, reporte, titulo, desde, hasta, pedida_en, estado, archivos };
}

/**
 * El rango del archivo, ajustado a periodos completos como App\Indicadores\Rango (inicioDe() y finDe()): por semana, del
 * lunes de la semana del primer día al domingo de la del último; por mes, del día 1 del primer mes al último día del
 * último; por día, y en la conciliación, que no lleva granularidad, los mismos días.
 */
function rangoDelArchivo(desde: string, hasta: string, granularidad: unknown): [string, string] {
  const inicio = new Date(`${desde}T00:00:00Z`);
  const fin = new Date(`${hasta}T00:00:00Z`);
  if (granularidad === 'semana') {
    inicio.setUTCDate(inicio.getUTCDate() - ((inicio.getUTCDay() + 6) % 7));
    fin.setUTCDate(fin.getUTCDate() + ((7 - fin.getUTCDay()) % 7));
  } else if (granularidad === 'mes') {
    inicio.setUTCDate(1);
    fin.setUTCMonth(fin.getUTCMonth() + 1, 0);
  }
  return [inicio.toISOString().slice(0, 10), fin.toISOString().slice(0, 10)];
}

/** El archivo que entrega el servidor: su contenido, que lee la simulación de File.downloadFileAsync. */
function archivo(contenido: string) {
  return { ok: true, status: 200, text: async () => contenido, json: async () => ({}) };
}

const RUTA = '/administracion/descargas';
const UNA_DESCARGA = /^\/administracion\/descargas\/([^/]+)$/;
const SU_ARCHIVO = /^\/administracion\/descargas\/([^/]+)\/([^/]+)$/;
const DIA = /^\d{4}-\d{2}-\d{2}$/;

/** Las descargas del servidor simulado, con lo que la app le pidió. */
export class DescargasDelServidor {
  descargas: DescargaDelServidor[] = [];
  /** Cuántos días vive cada descarga: EXPORTACION_DIAS_DE_VIDA, 7 por omisión (exportacion-E12). */
  diasDeVida = 7;
  /** Cómo nace la descarga que crea POST: generándose, o ya lista si la cola corrió en la misma petición. */
  estadoAlNacer: Estado = 'generandose';
  /** Cuántas descargas creó POST: con cada una, el id_publico que sigue. */
  private creadas = 0;

  constructor(private readonly servidor: ServidorDeLaAdministracion) {
    servidor.agregar(this.contestar);
  }

  /** La descarga con ese id_publico. */
  de(idPublico: string): DescargaDelServidor {
    const encontrada = this.descargas.find((otra) => otra.id_publico === idPublico);
    if (!encontrada) {
      throw new Error(`El servidor simulado no tiene la descarga ${idPublico}`);
    }
    return encontrada;
  }

  /** Lo que la app mandó a POST, en orden. */
  pedidas(): unknown[] {
    return this.servidor
      .peticiones()
      .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === RUTA)
      .map(({ cuerpo }) => cuerpo);
  }

  /** Cuántas veces la app consultó el estado de esa descarga. */
  consultasDe(idPublico: string): number {
    return this.servidor.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === `${RUTA}/${idPublico}`)
      .length;
  }

  /** Cuántas veces la app pidió la lista. */
  consultasDeLaLista(): number {
    return this.servidor.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === RUTA).length;
  }

  private readonly contestar: Manejador = ({ metodo, ruta, cuerpo }) => {
    const cuenta = this.servidor.cuenta.id_publico;
    const deLaCuenta = (idPublico: string) =>
      this.descargas.find((otra) => otra.id_publico === idPublico && otra.cuenta === cuenta && otra.viva);

    if (metodo === 'POST' && ruta === RUTA) {
      return this.crear(cuerpo as Record<string, unknown>, cuenta);
    }
    if (metodo === 'GET' && ruta === RUTA) {
      const vivas = this.descargas
        .filter((otra) => otra.cuenta === cuenta && otra.viva)
        .sort((una, otra) => otra.pedida_en.localeCompare(una.pedida_en));
      return respuesta(200, { descargas: vivas.map(comoLaDaLaApi), dias_de_vida: this.diasDeVida });
    }
    const una = UNA_DESCARGA.exec(ruta);
    if (metodo === 'GET' && una) {
      const encontrada = deLaCuenta(decodeURIComponent(una[1]));
      return encontrada
        ? respuesta(200, { descarga: comoLaDaLaApi(encontrada) })
        : respuesta(404, { message: NO_EXISTE_LA_DESCARGA });
    }
    const delArchivo = SU_ARCHIVO.exec(ruta);
    if (metodo === 'GET' && delArchivo) {
      const encontrada = deLaCuenta(decodeURIComponent(delArchivo[1]));
      if (!encontrada) {
        return respuesta(404, { message: NO_EXISTE_LA_DESCARGA });
      }
      const formato = delArchivo[2];
      if ((formato !== 'csv' && formato !== 'xlsx') || encontrada.estado !== 'lista') {
        return respuesta(404, { message: NO_EXISTE_EL_ARCHIVO });
      }
      return archivo(encontrada.contenido[formato]);
    }
    return undefined;
  };

  private crear(cuerpo: Record<string, unknown>, cuenta: string) {
    const { reporte, desde, hasta, granularidad } = cuerpo;
    const errores: Record<string, string[]> = {};
    if (typeof reporte !== 'string' || !(reporte in REPORTES)) {
      errores.reporte = [MENSAJE_DEL_REPORTE];
    }
    for (const [campo, valor] of [
      ['desde', desde],
      ['hasta', hasta],
    ] as const) {
      if (typeof valor !== 'string' || !DIA.test(valor)) {
        errores[campo] = [`El campo ${campo} no es una fecha válida.`];
      }
    }
    if (!errores.desde && !errores.hasta && (hasta as string) < (desde as string)) {
      errores.hasta = [MENSAJE_DEL_RANGO_AL_REVES];
    }
    if (reporte !== CONCILIACION && !['dia', 'semana', 'mes'].includes(granularidad as string)) {
      errores.granularidad = [MENSAJE_DE_LA_GRANULARIDAD];
    }
    const [primero] = Object.values(errores);
    if (primero) {
      return respuesta(422, { message: primero[0], errors: errores });
    }
    this.creadas += 1;
    const [desdeDelArchivo, hastaDelArchivo] = rangoDelArchivo(
      desde as string,
      hasta as string,
      reporte === CONCILIACION ? undefined : granularidad,
    );
    const nueva = descarga({
      id_publico: `01JDDESCARGANUEVA0000000${String(this.creadas).padStart(2, '0')}`,
      reporte: reporte as string,
      desde: desdeDelArchivo,
      hasta: hastaDelArchivo,
      estado: this.estadoAlNacer,
      cuenta,
    });
    this.descargas.push(nueva);
    return respuesta(202, { descarga: comoLaDaLaApi(nueva) });
  }
}

/**
 * Pone en File.downloadFileAsync una simulación que pasa por el servidor simulado: le pide la dirección con los
 * encabezados que mandó la app, y si responde 2xx escribe su contenido en el destino; si no, rechaza como el teléfono,
 * con el código en el mensaje (UnableToDownload). La de expo-file-system para Jest no pide nada: escribe "mock:<url>".
 */
export function bajarConElServidor(servidor: ServidorDeLaAdministracion): jest.SpyInstance {
  return jest.spyOn(File, 'downloadFileAsync').mockImplementation(async (url, destino, opciones) => {
    const contestada = (await servidor.fetch(url, { method: 'GET', headers: opciones?.headers ?? {} })) as {
      ok: boolean;
      status: number;
      text?: () => Promise<string>;
    };
    if (!contestada.ok || !contestada.text) {
      throw Object.assign(new Error(`Unable to download a file: response has status: ${contestada.status}`), {
        code: 'ERR_UNABLE_TO_DOWNLOAD',
      });
    }
    const enDestino = destino instanceof Directory ? new File(destino, 'descargado') : destino;
    if (enDestino.exists && opciones?.idempotent) {
      enDestino.delete();
    }
    enDestino.create();
    await enDestino.write(await contestada.text());
    return enDestino;
  });
}

/**
 * La carpeta que la persona elige en el selector del sistema: "Descargas", vacía, en los documentos de la simulación,
 * que guarda sus archivos en memoria mientras corre el archivo de pruebas.
 */
export function carpetaElegida(): Directory {
  const carpeta = new Directory(Paths.document, 'Descargas');
  if (carpeta.exists) {
    carpeta.delete();
  }
  carpeta.create();
  return carpeta;
}

/** El código con el que el selector de cada teléfono dice que la persona lo cerró (src/administracion/descargas/guardar.ts). */
export const SELECTOR_CERRADO = { android: 'ERR_PICKER_CANCELLED', ios: 'ERR_FILE_PICKING_CANCELLED' } as const;
