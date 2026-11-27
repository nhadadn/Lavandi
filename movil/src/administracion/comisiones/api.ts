// La configuración de comisiones de la administración en la app (F-D05; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
// Módulo D #5, "Porcentaje de comisión por socio o global."; administracion-app-E32 a E34): el porcentaje global, desde
// cuándo rige, y el propio de cada lavandería, de todas las plazas, con el que le aplica y de dónde sale.
//
// El contrato es el del comentario de servidor/app/Http/Controllers/Api/Administracion/ComisionesController.php, copiado
// aquí. Las reglas (de 0 a 100, con a lo más dos decimales; cuál le aplica a cada lavandería; qué queda como historial)
// las aplica el servidor, con la misma clase que el panel, App\Comisiones\PorcentajesDeComision (ADR-0022, decisión 9):
// la app no valida ni calcula ninguna. Manda el porcentaje que se escribió, solo sin los espacios de los lados, y muestra
// lo que el servidor responde, con el error 422 de "porcentaje" junto a su campo. Ningún porcentaje viene de fábrica: los
// captura EL CLIENTE.
//
//     GET    comisiones                              El global y el de cada lavandería (administracion-app-E32)
//     PUT    comisiones/global                       Fija el global: { porcentaje } (administracion-app-E33 y E34)
//     PUT    comisiones/lavanderias/{id_publico}     Fija el propio de la lavandería: { porcentaje }
//     DELETE comisiones/lavanderias/{id_publico}     Quita el propio: le vuelve a aplicar el global
//
// Las cuatro responden 200 con las comisiones, ya con el cambio:
//
//     {
//       "global": { "porcentaje": "12.50", "vigente_desde": "2026-12-01T12:00:00Z" } | null,
//       "lavanderias": [
//         {
//           "id_publico": "01jd8x6m3v2c9q4w5e7r8t0y1z",
//           "nombre": "Lavandería Ejemplo",
//           "plaza": { "nombre": "Plaza Ejemplo" },
//           "propio": "7.25" | null,
//           "aplica": { "porcentaje": "7.25", "origen": "propio" | "global" } | null
//         }
//       ]
//     }
//
// - el global es uno para todas las plazas; vigente_desde va en UTC, porque no es de ninguna plaza; null mientras no se
//   guarde ninguno, sin ningún número;
// - las lavanderías son todas, de todas las plazas y en cualquier estado, por nombre;
// - "aplica" es el propio si tiene; si no, el global; null si no tiene propio y no hay global;
// - 422 { message, errors: { porcentaje: [...] } }: el porcentaje falta o no vale, con el mensaje de las comisiones ("El
//   porcentaje va de 0 a 100."); el vigente no cambia (administracion-app-E34);
// - 404 { message: "No existe la lavandería." }: la lavandería no existe, está borrada o el valor no es ULID;
// - 403 a una cuenta que no puede fijar el global, y 401 sin sesión, que el cliente de la administración ya atiende.
//
// La comisión del repartidor espera DD-019 (administracion-app-E35) y la de cada pedido, DD-020 y DD-013
// (administracion-app-E36): no tienen ruta.
//
// Va sobre el cliente de la administración (src/administracion/api.ts), que pone el token, borra la sesión con un 401 y
// entrega el 404 y el 409 con su propio motivo. Como las demás consultas, nunca lanza: devuelve un Resultado. Una
// respuesta fuera del contrato no se pinta a medias: cuenta como falla del servidor.
import { esObjeto } from '../../configuracion/lectura';
import type { PedirALaAdministracion, ResultadoDeLaAdministracion } from '../api';

/** Las rutas y el campo de las comisiones, bajo /api/administracion, en un solo lugar para ajustarlos. */
export const CONTRATO_DE_LAS_COMISIONES = {
  comisiones: 'comisiones',
  global: 'comisiones/global',
  lavanderias: 'comisiones/lavanderias',
  /** El porcentaje, en el cuerpo de las dos rutas que lo fijan, y el campo de su error 422. */
  porcentaje: 'porcentaje',
} as const;

/** De dónde sale el porcentaje que le aplica a una lavandería: su propio, o el global. */
export type OrigenDelPorcentaje = 'propio' | 'global';

/** El porcentaje global vigente: de 0 a 100, con dos decimales, como texto ("12.50"), y desde cuándo rige, en UTC. */
export interface PorcentajeGlobal {
  porcentaje: string;
  vigente_desde: string;
}

/** Una lavandería, de cualquier plaza, con su porcentaje propio y el que le aplica. */
export interface ComisionDeLaLavanderia {
  id_publico: string;
  nombre: string;
  plaza: { nombre: string };
  /** Su porcentaje propio vigente; null si no tiene. */
  propio: string | null;
  /** El que le aplica, con de dónde sale; null si no tiene propio y no hay global. */
  aplica: { porcentaje: string; origen: OrigenDelPorcentaje } | null;
}

/** Las comisiones, como salen en todas las respuestas. */
export interface Comisiones {
  /** null mientras no se guarde ninguno. */
  global: PorcentajeGlobal | null;
  lavanderias: ComisionDeLaLavanderia[];
}

type Resultado<T> = ResultadoDeLaAdministracion<T>;

const FUERA_DEL_CONTRATO = { ok: false, motivo: 'servidor' } as const;

/** GET comisiones: el global y el de cada lavandería (administracion-app-E32). */
export async function consultarComisiones(pedir: PedirALaAdministracion): Promise<Resultado<Comisiones>> {
  return comisionesDeLaRespuesta(await pedir<unknown>(CONTRATO_DE_LAS_COMISIONES.comisiones));
}

/**
 * PUT comisiones/global: fija el porcentaje global que se escribió (administracion-app-E33). Le aplica a toda
 * lavandería sin porcentaje propio, de cualquier plaza; el anterior queda como historial. Con un porcentaje que no vale,
 * 422 en "porcentaje" y el vigente no cambia (administracion-app-E34).
 */
export async function fijarElGlobal(pedir: PedirALaAdministracion, porcentaje: string): Promise<Resultado<Comisiones>> {
  return comisionesDeLaRespuesta(
    await pedir<unknown>(CONTRATO_DE_LAS_COMISIONES.global, {
      metodo: 'PUT',
      cuerpo: { [CONTRATO_DE_LAS_COMISIONES.porcentaje]: porcentaje },
    }),
  );
}

/**
 * PUT comisiones/lavanderias/{id_publico}: fija el porcentaje propio de la lavandería que se escribió
 * (administracion-app-E33). Le aplica el suyo y no el global; el anterior queda como historial. El 422, como en el
 * global.
 */
export async function fijarElPropio(
  pedir: PedirALaAdministracion,
  idPublico: string,
  porcentaje: string,
): Promise<Resultado<Comisiones>> {
  return comisionesDeLaRespuesta(
    await pedir<unknown>(rutaDeLaLavanderia(idPublico), {
      metodo: 'PUT',
      cuerpo: { [CONTRATO_DE_LAS_COMISIONES.porcentaje]: porcentaje },
    }),
  );
}

/**
 * DELETE comisiones/lavanderias/{id_publico}: quita el porcentaje propio de la lavandería; le vuelve a aplicar el global
 * y el propio queda como historial (administracion-app-E33).
 */
export async function quitarElPropio(pedir: PedirALaAdministracion, idPublico: string): Promise<Resultado<Comisiones>> {
  return comisionesDeLaRespuesta(await pedir<unknown>(rutaDeLaLavanderia(idPublico), { metodo: 'DELETE' }));
}

function rutaDeLaLavanderia(idPublico: string): string {
  return `${CONTRATO_DE_LAS_COMISIONES.lavanderias}/${encodeURIComponent(idPublico)}`;
}

// Las comisiones de una respuesta, leídas campo por campo; fuera del contrato, la falla del servidor.
function comisionesDeLaRespuesta(resultado: Resultado<unknown>): Resultado<Comisiones> {
  if (!resultado.ok) {
    return resultado;
  }
  const comisiones = leerComisiones(resultado.datos);
  return comisiones ? { ok: true, datos: comisiones } : FUERA_DEL_CONTRATO;
}

function leerComisiones(valor: unknown): Comisiones | null {
  if (!esObjeto(valor) || !Array.isArray(valor.lavanderias)) {
    return null;
  }
  const global = valor.global === null ? null : leerGlobal(valor.global);
  const lavanderias = valor.lavanderias.map(leerLavanderia);
  if (global === undefined || !lavanderias.every((lavanderia) => lavanderia !== null)) {
    return null;
  }
  return { global, lavanderias };
}

// El global vigente; undefined si no es del contrato.
function leerGlobal(valor: unknown): PorcentajeGlobal | undefined {
  if (
    !esObjeto(valor) ||
    !esPorcentaje(valor.porcentaje) ||
    typeof valor.vigente_desde !== 'string' ||
    Number.isNaN(Date.parse(valor.vigente_desde))
  ) {
    return undefined;
  }
  return { porcentaje: valor.porcentaje, vigente_desde: valor.vigente_desde };
}

function leerLavanderia(valor: unknown): ComisionDeLaLavanderia | null {
  if (
    !esObjeto(valor) ||
    typeof valor.id_publico !== 'string' ||
    valor.id_publico === '' ||
    typeof valor.nombre !== 'string' ||
    !esObjeto(valor.plaza) ||
    typeof valor.plaza.nombre !== 'string' ||
    !(valor.propio === null || esPorcentaje(valor.propio))
  ) {
    return null;
  }
  const aplica = valor.aplica === null ? null : leerAplica(valor.aplica);
  if (aplica === undefined) {
    return null;
  }
  return {
    id_publico: valor.id_publico,
    nombre: valor.nombre,
    plaza: { nombre: valor.plaza.nombre },
    propio: valor.propio,
    aplica,
  };
}

// El que le aplica; undefined si no es del contrato.
function leerAplica(valor: unknown): ComisionDeLaLavanderia['aplica'] | undefined {
  if (!esObjeto(valor) || !esPorcentaje(valor.porcentaje) || (valor.origen !== 'propio' && valor.origen !== 'global')) {
    return undefined;
  }
  return { porcentaje: valor.porcentaje, origen: valor.origen };
}

// Un porcentaje llega como texto, como lo guarda la base ("12.50"): se muestra tal cual, sin redondear.
function esPorcentaje(valor: unknown): valor is string {
  return typeof valor === 'string' && valor.trim() !== '';
}
