// Apoyo de las pruebas de contrato de la app con el servidor (openspec/changes/administracion-en-la-app/design.md,
// sección 6, "Los contratos con la app"). No es una prueba: Jest no lo corre como tal (testPathIgnorePatterns en
// package.json).
//
// Cada contrato es un archivo JSON de servidor/tests/Contratos/administracion/ con una respuesta real de una ruta de
// /api/administracion: su petición, su código y su cuerpo, como los da el servidor para los datos que siembran sus
// pruebas (tests/Feature/AdministracionEnLaApp/Soporte/ContratosDeLaApp.php). Los escribe y los vigila el servidor: si
// una respuesta cambia de forma, su prueba falla hasta que el archivo se reescribe. Aquí la app los lee con sus
// funciones leer… y compara con ellos las respuestas de su servidor simulado.
import { readdirSync, readFileSync } from 'node:fs';
import path from 'node:path';

/** La carpeta de los contratos de la administración, la que escribe el servidor. */
export const CARPETA_DE_LOS_CONTRATOS = path.resolve(
  __dirname,
  '..',
  '..',
  '..',
  'servidor',
  'tests',
  'Contratos',
  'administracion',
);

/** Una respuesta real del servidor: la ruta con sus parámetros por nombre, el código y el cuerpo. */
export interface Contrato {
  /** "GET /api/administracion/repartidores/{repartidor}". */
  peticion: string;
  estado: number;
  cuerpo: unknown;
}

/** Los nombres de los contratos, sin su extensión: "repartidores.lista". */
export function nombresDeLosContratos(): string[] {
  return readdirSync(CARPETA_DE_LOS_CONTRATOS)
    .filter((archivo) => archivo.endsWith('.json'))
    .map((archivo) => archivo.slice(0, -'.json'.length))
    .sort();
}

export function leerElContrato(nombre: string): Contrato {
  return JSON.parse(readFileSync(path.join(CARPETA_DE_LOS_CONTRATOS, `${nombre}.json`), 'utf8')) as Contrato;
}

/** El tipo de un valor de JSON, con los nombres que usa también el servidor. */
type Tipo = 'nulo' | 'booleano' | 'numero' | 'texto' | 'lista' | 'objeto';

/**
 * La forma de un valor de JSON: por cada ruta desde la raíz ($), con ".llave" en un objeto y "[]" en los elementos de
 * una lista, los tipos de su valor. En una lista cuentan todos sus elementos. Es la misma que arma el servidor
 * (ContratosDeLaApp::formaDelJson()).
 */
export function formaDe(valor: unknown): Map<string, Set<Tipo>> {
  const forma = new Map<string, Set<Tipo>>();
  recorrer(valor, '$', forma);
  return forma;
}

function recorrer(valor: unknown, ruta: string, forma: Map<string, Set<Tipo>>) {
  const tipos = forma.get(ruta) ?? new Set<Tipo>();
  forma.set(ruta, tipos);
  if (valor === null || valor === undefined) {
    tipos.add('nulo');
  } else if (typeof valor === 'boolean') {
    tipos.add('booleano');
  } else if (typeof valor === 'number') {
    tipos.add('numero');
  } else if (typeof valor === 'string') {
    tipos.add('texto');
  } else if (Array.isArray(valor)) {
    tipos.add('lista');
    for (const elemento of valor) {
      recorrer(elemento, `${ruta}[]`, forma);
    }
  } else {
    tipos.add('objeto');
    for (const [llave, deLaLlave] of Object.entries(valor as Record<string, unknown>)) {
      recorrer(deLaLlave, `${ruta}.${llave}`, forma);
    }
  }
}

/**
 * Lo que la respuesta del servidor simulado tiene distinto de la real, un renglón por diferencia: una llave de más o de
 * menos en cualquier nivel, o un valor de otro tipo. Un null no cuenta como diferencia de tipo: en una respuesta real
 * puede ser null lo que en la simulada trae un valor, y al revés. Sin diferencias, la lista vacía.
 */
export function diferenciasDeForma(simulada: unknown, real: unknown): string[] {
  const deLaSimulada = formaDe(simulada);
  const deLaReal = formaDe(real);
  const diferencias: string[] = [];
  for (const ruta of new Set([...deLaSimulada.keys(), ...deLaReal.keys()])) {
    const tiposSimulados = deLaSimulada.get(ruta);
    const tiposReales = deLaReal.get(ruta);
    if (!tiposReales) {
      diferencias.push(`${ruta}: el servidor simulado la manda y el real no`);
      continue;
    }
    if (!tiposSimulados) {
      diferencias.push(`${ruta}: el servidor real la manda y el simulado no`);
      continue;
    }
    const reales = [...tiposReales].filter((tipo) => tipo !== 'nulo');
    const deMas = [...tiposSimulados].filter((tipo) => tipo !== 'nulo' && !tiposReales.has(tipo));
    if (reales.length > 0 && deMas.length > 0) {
      diferencias.push(`${ruta}: el simulado manda ${deMas.join('|')} y el real ${reales.join('|')}`);
    }
  }
  return diferencias.sort();
}

/** La ruta del contrato como la pide la app, sin el /api de la dirección base: "GET /administracion/repartidores/x". */
export function patronDeLaPeticion(peticion: string): RegExp {
  const [metodo, ruta] = peticion.split(' ');
  const sinApi = ruta.replace(/^\/api\//, '/');
  const partes = sinApi.split(/\{[^}]+\}/).map((parte) => parte.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));
  return new RegExp(`^${metodo} ${partes.join('[^/?]+')}(?:\\?.*)?$`);
}
