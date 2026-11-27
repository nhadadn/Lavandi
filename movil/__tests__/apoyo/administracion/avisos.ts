// Apoyo de las pruebas de los avisos masivos en la app (F-D07; administracion-app-E42 y E43). No es una prueba: Jest no
// lo corre como tal (testPathIgnorePatterns en package.json). Es el manejador que la prueba registra en el servidor
// simulado de la administración (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/AvisosMasivosController.php:
//
// - GET /administracion/avisos-masivos: 200 con los avisos, del más reciente al más antiguo;
// - POST /administracion/avisos-masivos con titulo y mensaje: 201 con el aviso guardado, que sale en cola
//   ("destinatarios": null), o 422 con el error de cada campo y los mensajes de App\Avisos\CreacionDeAvisoMasivo, y
//   entonces no guarda nada. Como el servidor, toma los dos sin los espacios de los extremos, cuenta los caracteres
//   como mb_strlen e ignora cualquier otro campo.
//
// Los datos son de prueba, con los textos de la imagen 36 de la Propuesta 06. Cada instante sale de una hora del
// teléfono, como lo manda la API: en UTC.
import { copia, respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';

/** Un aviso como sale en la API. */
export interface AvisoGuardado {
  titulo: string;
  mensaje: string;
  enviado_en: string;
  enviado_por_esta_cuenta: boolean;
  destinatarios: number | null;
}

export const RUTA_DE_LOS_AVISOS = '/administracion/avisos-masivos';

/** Los topes y los mensajes de App\Avisos\CreacionDeAvisoMasivo. */
export const TOPE_DEL_TITULO = 255;
export const TOPE_DEL_MENSAJE = 1000;
export const MENSAJES = {
  sinTitulo: 'Escribe el título.',
  tituloLargo: `El título no puede pasar de ${TOPE_DEL_TITULO} caracteres.`,
  sinTexto: 'Escribe el texto.',
  textoLargo: `El texto no puede pasar de ${TOPE_DEL_MENSAJE} caracteres.`,
} as const;

/** Un instante, en UTC, de ese día y esa hora del teléfono (el mes va de 1 a 12). */
export function instante(anio: number, mes: number, dia: number, hora: number, minuto: number): string {
  return new Date(anio, mes - 1, dia, hora, minuto).toISOString();
}

/** El más reciente: lo mandó la cuenta de la sesión y sigue en la cola. */
export const EN_COLA: AvisoGuardado = {
  titulo: 'Aviso de prueba: mantenimiento',
  mensaje: 'Texto de prueba del aviso de mantenimiento.',
  enviado_en: instante(2026, 11, 24, 10, 30),
  enviado_por_esta_cuenta: true,
  destinatarios: null,
};

/** Ya contado: salió a tres teléfonos. Lo mandó otra cuenta de administración. */
export const CONTADO: AvisoGuardado = {
  titulo: 'Aviso de prueba: promoción',
  mensaje: 'Texto de prueba del aviso de promoción.',
  enviado_en: instante(2026, 11, 20, 18, 5),
  enviado_por_esta_cuenta: false,
  destinatarios: 3,
};

/** El más antiguo, ya contado: salió a dos teléfonos. */
export const EL_PRIMERO: AvisoGuardado = {
  titulo: 'Aviso de prueba 1',
  mensaje: 'Texto de prueba del primer aviso.',
  enviado_en: instante(2026, 11, 12, 9, 15),
  enviado_por_esta_cuenta: true,
  destinatarios: 2,
};

/** Los avisos del servidor, en el orden en que se guardaron, del más antiguo al más reciente. */
export const AVISOS: AvisoGuardado[] = [EL_PRIMERO, CONTADO, EN_COLA];

/** Los avisos masivos del servidor simulado, con los que la app mandó. */
export class AvisosDelServidor {
  private readonly guardados: AvisoGuardado[];

  constructor(iniciales: AvisoGuardado[]) {
    this.guardados = copia(iniciales);
  }

  /** Los avisos guardados, del más reciente al más antiguo, como los da GET avisos-masivos. */
  enviados(): AvisoGuardado[] {
    return [...this.guardados].sort((uno, otro) => Date.parse(otro.enviado_en) - Date.parse(uno.enviado_en));
  }

  /** Cuántos avisos tiene guardados. */
  cuantos(): number {
    return this.guardados.length;
  }

  readonly manejador = ({ metodo, ruta, cuerpo }: Peticion): unknown => {
    if (ruta !== RUTA_DE_LOS_AVISOS) {
      return undefined;
    }
    if (metodo === 'GET') {
      return respuesta(200, { avisos: this.enviados() });
    }
    if (metodo === 'POST') {
      return this.crear(cuerpo);
    }
    return undefined;
  };

  // La creación de CreacionDeAvisoMasivo: valida, guarda el aviso de la cuenta de la sesión y lo deja en cola.
  private crear(cuerpo: unknown) {
    const datos = (cuerpo ?? {}) as Record<string, unknown>;
    const titulo = recortado(datos.titulo);
    const mensaje = recortado(datos.mensaje);
    const errores: Record<string, string[]> = {};
    const errorDelTitulo = errorDe(titulo, TOPE_DEL_TITULO, MENSAJES.sinTitulo, MENSAJES.tituloLargo);
    if (errorDelTitulo) {
      errores.titulo = [errorDelTitulo];
    }
    const errorDelTexto = errorDe(mensaje, TOPE_DEL_MENSAJE, MENSAJES.sinTexto, MENSAJES.textoLargo);
    if (errorDelTexto) {
      errores.mensaje = [errorDelTexto];
    }
    const primero = Object.values(errores)[0];
    if (primero) {
      return respuesta(422, { message: primero[0], errors: errores });
    }
    const aviso: AvisoGuardado = {
      titulo: titulo as string,
      mensaje: mensaje as string,
      enviado_en: new Date().toISOString(),
      enviado_por_esta_cuenta: true,
      destinatarios: null,
    };
    this.guardados.push(aviso);
    return respuesta(201, { aviso });
  }
}

// Como TrimStrings y ConvertEmptyStringsToNull de Laravel: el texto sin los espacios de los extremos, y vacío, null.
function recortado(valor: unknown): unknown {
  if (typeof valor !== 'string') {
    return valor ?? null;
  }
  const texto = valor.trim();
  return texto === '' ? null : texto;
}

function errorDe(valor: unknown, tope: number, falta: string, largo: string): string | undefined {
  if (valor === null) {
    return falta;
  }
  return typeof valor === 'string' && Array.from(valor).length > tope ? largo : undefined;
}

/**
 * Registra los avisos del servidor en cada prueba del archivo, con los iniciales que diga la prueba. Devuelve los de la
 * prueba en curso.
 */
export function usarLosAvisos(
  servidor: () => ServidorDeLaAdministracion,
  iniciales: AvisoGuardado[] = AVISOS,
): () => AvisosDelServidor {
  let avisos: AvisosDelServidor;
  beforeEach(() => {
    avisos = new AvisosDelServidor(iniciales);
    servidor().agregar(avisos.manejador);
  });
  return () => avisos;
}

/** Los avisos que la app mandó: el cuerpo de cada POST avisos-masivos, en orden. */
export function avisosMandados(servidor: ServidorDeLaAdministracion): unknown[] {
  return servidor
    .peticiones()
    .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === RUTA_DE_LOS_AVISOS)
    .map(({ cuerpo }) => cuerpo);
}

/** Cuántas veces la app pidió la lista. */
export function consultasDeLaLista(servidor: ServidorDeLaAdministracion): number {
  return servidor.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === RUTA_DE_LOS_AVISOS).length;
}
