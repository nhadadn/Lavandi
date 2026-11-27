// Apoyo de las pruebas de la configuración de comisiones en la app (F-D05; administracion-app-E32 a E34). No es una
// prueba: Jest no lo corre como tal (testPathIgnorePatterns en package.json). Son los manejadores que la prueba registra
// en el servidor simulado de la administración (__tests__/apoyo/administracion.ts), con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/ComisionesController.php:
//
// - GET /administracion/comisiones: el global vigente y desde cuándo rige, o null, y cada lavandería, de todas las
//   plazas, por nombre, con su propio y el que le aplica, con de dónde sale;
// - PUT /administracion/comisiones/global y PUT /administracion/comisiones/lavanderias/{id_publico}: fijan el porcentaje
//   y responden las comisiones ya con él, con dos decimales, como los guarda la base;
// - DELETE /administracion/comisiones/lavanderias/{id_publico}: quita el propio; sin propio, no cambia nada;
// - 422 con el mensaje de App\Comisiones\PorcentajesDeComision::mensajes() a un porcentaje que falta o no vale, sin
//   cambiar el vigente (administracion-app-E34). La regla misma la prueba el servidor (ComisionesTest.php); aquí basta
//   para que la app reciba el 422 como lo manda;
// - 404 con "No existe la lavandería." a la que no existe.
//
// Los datos son de prueba, con los nombres de los seeders y de las imágenes de la Propuesta 06. Los porcentajes son
// cifras de prueba, no del negocio: los reales los captura EL CLIENTE.
import { copia, respuesta, type Peticion, type ServidorDeLaAdministracion } from '../administracion';

/** Una lavandería como la guarda el servidor simulado: lo de la API, sin el que le aplica, que sale de su propio. */
export interface LavanderiaGuardada {
  id_publico: string;
  nombre: string;
  plaza: { nombre: string };
  propio: string | null;
}

export interface GlobalGuardado {
  porcentaje: string;
  vigente_desde: string;
}

/** Lo que guarda el servidor simulado: el global, o ninguno, y las lavanderías. */
export interface ComisionesGuardadas {
  global: GlobalGuardado | null;
  lavanderias: LavanderiaGuardada[];
}

export const DURANGO_CENTRO: LavanderiaGuardada = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0cm1',
  nombre: 'Lavandería de prueba Durango Centro',
  plaza: { nombre: 'Plaza de prueba Durango' },
  propio: '7.25',
};

export const DE_TORREON: LavanderiaGuardada = {
  id_publico: '01jd8x6m3v2c9q4w5e7r8t0cm2',
  nombre: 'Lavandería de prueba 1',
  plaza: { nombre: 'Plaza de prueba Torreón' },
  propio: null,
};

/** El global de prueba, vigente desde el 1 de diciembre de 2026 a las 12:00 UTC. */
export const GLOBAL: GlobalGuardado = { porcentaje: '12.50', vigente_desde: '2026-12-01T12:00:00Z' };

/** Desde cuándo rige lo que se fija en la prueba: el instante del servidor simulado. */
export const AHORA = '2026-12-03T18:45:00Z';

/** Un global guardado, una lavandería con propio y otra sin él, en plazas distintas (administracion-app-E32). */
export const CON_GLOBAL: ComisionesGuardadas = { global: GLOBAL, lavanderias: [DURANGO_CENTRO, DE_TORREON] };

/** Sin global guardado: la de propio sigue con el suyo y a la otra no le aplica ninguno. */
export const SIN_GLOBAL: ComisionesGuardadas = { global: null, lavanderias: [DURANGO_CENTRO, DE_TORREON] };

/** Sin ningún porcentaje guardado, ni global ni propio. */
export const SIN_NINGUNO: ComisionesGuardadas = {
  global: null,
  lavanderias: [
    { ...DURANGO_CENTRO, propio: null },
    { ...DE_TORREON, propio: null },
  ],
};

/** Un ULID que no es de ninguna lavandería. */
export const QUE_NO_EXISTE = '01jd8x6m3v2c9q4w5e7r8t0czz';

export const NO_EXISTE = 'No existe la lavandería.';

/** Los mensajes de App\Comisiones\PorcentajesDeComision::mensajes(). */
export const MENSAJES_DEL_PORCENTAJE = {
  required: 'Escribe el porcentaje.',
  numeric: 'El porcentaje es un número.',
  between: 'El porcentaje va de 0 a 100.',
  decimal: 'El porcentaje lleva a lo más dos decimales.',
} as const;

const RUTA = '/administracion/comisiones';

/** Las comisiones como salen en todas las respuestas, por nombre de lavandería, como las ordena el servidor. */
export function comoSalen({ global, lavanderias }: ComisionesGuardadas) {
  return {
    global,
    lavanderias: [...lavanderias]
      .sort((una, otra) => (una.nombre < otra.nombre ? -1 : una.nombre > otra.nombre ? 1 : 0))
      .map((lavanderia) => ({
        ...lavanderia,
        aplica:
          lavanderia.propio !== null
            ? { porcentaje: lavanderia.propio, origen: 'propio' }
            : global
              ? { porcentaje: global.porcentaje, origen: 'global' }
              : null,
      })),
  };
}

/** Las comisiones del servidor simulado, con lo que la app les mandó. */
export class ComisionesDelServidor {
  readonly guardadas: ComisionesGuardadas;

  constructor(iniciales: ComisionesGuardadas) {
    this.guardadas = copia(iniciales);
  }

  /** La lavandería como está ahora en el servidor. */
  de(idPublico: string): LavanderiaGuardada {
    const lavanderia = this.guardadas.lavanderias.find((guardada) => guardada.id_publico === idPublico);
    if (!lavanderia) {
      throw new Error(`El servidor simulado no tiene la lavandería ${idPublico}`);
    }
    return lavanderia;
  }

  /** Contesta lo de /administracion/comisiones; lo demás, undefined. */
  readonly manejador = ({ metodo, ruta, cuerpo }: Peticion): unknown => {
    if (metodo === 'GET' && ruta === RUTA) {
      return this.comisiones();
    }
    if (metodo === 'PUT' && ruta === `${RUTA}/global`) {
      return this.fijar(cuerpo, (porcentaje) => {
        this.guardadas.global = { porcentaje, vigente_desde: AHORA };
      });
    }
    const partes = /^\/administracion\/comisiones\/lavanderias\/([^/]+)$/.exec(ruta);
    if (!partes || (metodo !== 'PUT' && metodo !== 'DELETE')) {
      return undefined;
    }
    // La lavandería se busca antes de validar: una que no existe responde 404 aunque el porcentaje no valga.
    const lavanderia = this.guardadas.lavanderias.find(
      (guardada) => guardada.id_publico === decodeURIComponent(partes[1]).toLowerCase(),
    );
    if (!lavanderia) {
      return respuesta(404, { message: NO_EXISTE });
    }
    if (metodo === 'DELETE') {
      lavanderia.propio = null;
      return this.comisiones();
    }
    return this.fijar(cuerpo, (porcentaje) => {
      lavanderia.propio = porcentaje;
    });
  };

  private comisiones() {
    return respuesta(200, comoSalen(this.guardadas));
  }

  // Valida como PorcentajesDeComision::reglas() (required, numeric, between:0,100 y decimal:0,2), con el primer mensaje
  // que no cumple; si vale, lo guarda con dos decimales, como la base.
  private fijar(cuerpo: unknown, guardar: (porcentaje: string) => void) {
    const valor = typeof cuerpo === 'object' && cuerpo !== null ? (cuerpo as Record<string, unknown>).porcentaje : undefined;
    const texto = typeof valor === 'number' ? String(valor) : typeof valor === 'string' ? valor.trim() : '';
    const numero = Number(texto);
    const mensaje =
      texto === ''
        ? MENSAJES_DEL_PORCENTAJE.required
        : !Number.isFinite(numero)
          ? MENSAJES_DEL_PORCENTAJE.numeric
          : numero < 0 || numero > 100
            ? MENSAJES_DEL_PORCENTAJE.between
            : !/^-?\d+(\.\d{1,2})?$/.test(texto)
              ? MENSAJES_DEL_PORCENTAJE.decimal
              : null;
    if (mensaje) {
      return respuesta(422, { message: mensaje, errors: { porcentaje: [mensaje] } });
    }
    guardar(numero.toFixed(2));
    return this.comisiones();
  }
}

/**
 * Prepara cada prueba del archivo con las comisiones del servidor simulado de la administración, ya registradas en él.
 * Devuelve las de la prueba en curso; la prueba puede cambiarlas antes de abrir la app. Va después de
 * usarElServidorDeLaAdministracion().
 */
export function usarLasComisiones(
  servidor: () => ServidorDeLaAdministracion,
  iniciales: ComisionesGuardadas = CON_GLOBAL,
): () => ComisionesDelServidor {
  let comisiones: ComisionesDelServidor;
  beforeEach(() => {
    comisiones = new ComisionesDelServidor(iniciales);
    servidor().agregar(comisiones.manejador);
  });
  return () => comisiones;
}

/** Lo que la app mandó a las comisiones, sin las consultas: "PUT /administracion/comisiones/global" con su cuerpo. */
export function cambiosMandados(servidor: ServidorDeLaAdministracion): { metodo: string; ruta: string; cuerpo: unknown }[] {
  return servidor
    .peticiones()
    .filter(({ metodo, ruta }) => metodo !== 'GET' && ruta.startsWith(RUTA))
    .map(({ metodo, ruta, cuerpo }) => ({ metodo, ruta, cuerpo }));
}

/** Cuántas veces la app pidió las comisiones. */
export function consultas(servidor: ServidorDeLaAdministracion): number {
  return servidor.peticiones().filter(({ metodo, ruta }) => metodo === 'GET' && ruta === RUTA).length;
}
