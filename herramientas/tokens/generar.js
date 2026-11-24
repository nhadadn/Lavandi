// Genera movil/src/tema/theme.ts desde vault/design-system/tokens.json (ADR-0013).
//
// Uso, desde la raíz del repositorio:   node herramientas/tokens/generar.js [origen] [destino]
// o desde movil/:                       npm run tokens
// o desde herramientas/tokens/:         npm run generar   (lo que corre la integración continua)
//
// Es idempotente: si theme.ts ya corresponde a tokens.json, no lo toca. Falla, nombrando la clave, si tokens.json no
// tiene la forma que espera: es preferible a generar un tema incompleto.
//
// Solo genera theme.ts. Las variables CSS para la web que también decide el ADR-0013 llegan con el cambio de la landing.
'use strict';

const fs = require('node:fs');
const path = require('node:path');

const RAIZ = path.resolve(__dirname, '..', '..');
const RUTA_TOKENS = path.join(RAIZ, 'vault', 'design-system', 'tokens.json');
const RUTA_TEMA = path.join(RAIZ, 'movil', 'src', 'tema', 'theme.ts');

// La v1 lleva solo modo claro (C-141). El bloque oscuro se queda en tokens.json sin usarse.
const MODO = 'claro';

// ---------------------------------------------------------------------------------------------------------------
// Lectura de tokens.json: cada valor se valida y, si no es lo esperado, el error nombra su clave.
// ---------------------------------------------------------------------------------------------------------------

function fallar(ruta, problema) {
  throw new Error(`tokens.json: ${ruta.join('.')} ${problema}`);
}

function objeto(valor, ruta) {
  if (valor === null || typeof valor !== 'object' || Array.isArray(valor)) {
    fallar(ruta, 'falta o no es un grupo de valores');
  }
  return valor;
}

/** Recorre un grupo sin sus claves de documentación ($comentario y las que se indiquen) y transforma cada valor. */
function mapear(valor, ruta, transformar, ignorar = []) {
  const grupo = objeto(valor, ruta);
  const salida = {};
  for (const [clave, hijo] of Object.entries(grupo)) {
    if (clave.startsWith('$') || ignorar.includes(clave)) {
      continue;
    }
    salida[clave] = transformar(hijo, [...ruta, clave]);
  }
  return salida;
}

function hex(valor, ruta) {
  if (typeof valor !== 'string' || !/^#[0-9A-Fa-f]{6}$/.test(valor)) {
    fallar(ruta, `no es un color hexadecimal de seis cifras: ${JSON.stringify(valor)}`);
  }
  return valor;
}

function numero(valor, ruta) {
  if (typeof valor !== 'number' || !Number.isFinite(valor)) {
    fallar(ruta, `no es un número: ${JSON.stringify(valor)}`);
  }
  return valor;
}

function texto(valor, ruta) {
  if (typeof valor !== 'string' || valor.trim() === '') {
    fallar(ruta, `no es un texto: ${JSON.stringify(valor)}`);
  }
  return valor;
}

/** Un campo dentro de un token compuesto, como el hex de un color de marca o el modo claro de un estado. */
function campo(nombre, transformar) {
  return (valor, ruta) => transformar(objeto(valor, ruta)[nombre], [...ruta, nombre]);
}

/** React Native no acepta pilas de familias: pasa la primera, sin comillas. */
function primeraFamilia(valor, ruta) {
  const primera = texto(valor, ruta).split(',')[0].trim().replace(/^['"]|['"]$/g, '');
  if (primera === '') {
    fallar(ruta, 'no nombra ninguna familia');
  }
  return primera;
}

/** "-0.01em" pasa a -0.01: React Native mide letterSpacing en puntos, y quien lo usa lo multiplica por el tamaño. */
function enEm(valor, ruta) {
  const encontrado = /^\s*(-?\d*\.?\d+)\s*(em)?\s*$/.exec(texto(valor, ruta));
  if (!encontrado || (encontrado[2] === undefined && Number(encontrado[1]) !== 0)) {
    fallar(ruta, `no está en em: ${JSON.stringify(valor)}`);
  }
  return Number(encontrado[1]);
}

/**
 * "cubic-bezier(.2,0,0,1)" pasa a [0.2, 0, 0, 1], los cuatro números de Easing.bezier. Una cifra vacía falla: Number('')
 * vale 0, y "cubic-bezier(.2,,0,1)" pasaría como una curva distinta sin avisar.
 */
function bezier(valor, ruta) {
  const encontrado = /^\s*cubic-bezier\(([^)]*)\)\s*$/.exec(texto(valor, ruta));
  const partes = encontrado ? encontrado[1].split(',').map((parte) => parte.trim()) : [];
  const numeros = partes.map((parte) => (parte === '' ? NaN : Number(parte)));
  if (numeros.length !== 4 || numeros.some((n) => !Number.isFinite(n))) {
    fallar(ruta, `no es una curva cubic-bezier de cuatro números: ${JSON.stringify(valor)}`);
  }
  return numeros;
}

function leerTema(tokens) {
  const raiz = objeto(tokens, ['(raíz)']);
  const color = objeto(raiz.color, ['color']);
  const tipografia = objeto(raiz.tipografia, ['tipografia']);

  return {
    color: {
      marca: mapear(color.marca, ['color', 'marca'], campo('hex', hex)),
      neutro: mapear(color.neutro, ['color', 'neutro'], hex),
      estado: mapear(color.estado, ['color', 'estado'], campo(MODO, hex)),
      semantico: mapear(objeto(color.semantico, ['color', 'semantico'])[MODO], ['color', 'semantico', MODO], hex),
      pedido: mapear(color.pedido, ['color', 'pedido'], campo(MODO, hex)),
      marca_extendida: mapear(color.marca_extendida, ['color', 'marca_extendida'], campo('hex', hex)),
    },
    tipografia: {
      familia: mapear(tipografia.familia, ['tipografia', 'familia'], primeraFamilia, ['estado']),
      escala: mapear(tipografia.escala, ['tipografia', 'escala'], numero),
      peso: mapear(tipografia.peso, ['tipografia', 'peso'], numero),
      interlineado: mapear(tipografia.interlineado, ['tipografia', 'interlineado'], numero),
      espaciado_letra: mapear(tipografia.espaciado_letra, ['tipografia', 'espaciado_letra'], enEm),
    },
    espaciado: mapear(raiz.espaciado, ['espaciado'], numero),
    radio: mapear(raiz.radio, ['radio'], numero),
    grosor: mapear(raiz.grosor, ['grosor'], numero),
    alto: mapear(raiz.alto, ['alto'], numero),
    elevacion: mapear(raiz.elevacion, ['elevacion'], texto),
    duracion: mapear(raiz.duracion, ['duracion'], numero),
    curva: mapear(raiz.curva, ['curva'], bezier),
    accesibilidad: mapear(raiz.accesibilidad, ['accesibilidad'], numero, ['fuente']),
  };
}

// ---------------------------------------------------------------------------------------------------------------
// Escritura de theme.ts: siempre el mismo texto para la misma entrada (sin fechas), con fin de línea \n.
// ---------------------------------------------------------------------------------------------------------------

// Comentarios fijos del generador, no de tokens.json: los de tokens.json pueden traer la marca.
const NOTAS = {
  'tipografia.familia': 'La primera familia de cada pila de tokens.json: React Native no acepta pilas.',
  'tipografia.interlineado': 'Multiplicadores: en React Native, lineHeight va en puntos (tamaño × interlineado).',
  'tipografia.espaciado_letra': 'En em: en React Native, letterSpacing va en puntos (tamaño × espaciado).',
  grosor: 'Grosores de borde: control, el de campos y botones; foco, el contorno de foco visible.',
  alto: 'Altos fijos de controles. Los demás miden al menos accesibilidad.area_tactil_minima_dp.',
  elevacion: 'Sombras con la sintaxis de CSS, que React Native acepta en la propiedad boxShadow.',
  duracion: 'Milisegundos.',
  curva: 'Los cuatro números de Easing.bezier.',
};

function clave(nombre) {
  if (/^[A-Za-z_$][\w$]*$/.test(nombre) || /^(0|[1-9]\d*)$/.test(nombre)) {
    return nombre;
  }
  return `'${nombre.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function valorEnTs(valor, sangria, ruta) {
  if (typeof valor === 'number') {
    return String(valor);
  }
  if (typeof valor === 'string') {
    return `'${valor.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
  }
  if (Array.isArray(valor)) {
    return `[${valor.map((v) => valorEnTs(v, sangria, ruta)).join(', ')}]`;
  }
  const interior = `${sangria}  `;
  const lineas = Object.entries(valor).flatMap(([nombre, hijo]) => {
    const rutaHija = [...ruta, nombre].join('.');
    const nota = NOTAS[rutaHija] ? [`${interior}// ${NOTAS[rutaHija]}`] : [];
    return [...nota, `${interior}${clave(nombre)}: ${valorEnTs(hijo, interior, [...ruta, nombre])},`];
  });
  return `{\n${lineas.join('\n')}\n${sangria}}`;
}

/**
 * El contenido de theme.ts para unos tokens. Lanza un error que nombra la clave si los tokens no tienen la forma
 * esperada.
 * @param {unknown} tokens el contenido de tokens.json, ya leído como JSON
 * @returns {string}
 */
function generarTema(tokens) {
  const tema = leerTema(tokens);
  const meta = tokens && typeof tokens.$meta === 'object' && tokens.$meta !== null ? tokens.$meta : {};
  const version = [meta.version, meta.fecha].filter((v) => typeof v === 'string' && v !== '').join(', ');

  const encabezado = [
    '// ARCHIVO GENERADO: no se edita a mano.',
    `// Sale de vault/design-system/tokens.json${version ? ` (versión ${version})` : ''} con`,
    '// herramientas/tokens/generar.js (ADR-0013). Para cambiar un valor se cambia tokens.json y se corre',
    '// `npm run tokens` en movil/. Una prueba falla si este archivo no corresponde a tokens.json.',
    `// Solo modo ${MODO} (C-141). Los componentes usan color.semantico, nunca color.marca directo (design-system.md,`,
    '// Decisión 3). Las medidas de tokens.json están en px y se usan tal cual como puntos de React Native.',
  ];

  const grupos = Object.entries(tema).map(([nombre, valor]) => {
    const nota = NOTAS[nombre] ? [`// ${NOTAS[nombre]}`] : [];
    return [...nota, `export const ${nombre} = ${valorEnTs(valor, '', [nombre])} as const;`].join('\n');
  });

  const reunion = [
    'export const tema = {',
    `  modo: '${MODO}',`,
    ...Object.keys(tema).map((nombre) => `  ${nombre},`),
    '} as const;',
  ].join('\n');

  return `${[encabezado.join('\n'), ...grupos, reunion, 'export type Tema = typeof tema;'].join('\n\n')}\n`;
}

/**
 * Lee tokens.json y escribe theme.ts solo si cambió. Un theme.ts que difiere solo en el fin de línea (\r\n, como lo
 * deja git en Windows) ya está al día.
 * @param {{ origen?: string, destino?: string }} [rutas]
 * @returns {{ cambio: boolean }}
 */
function escribirTema({ origen = RUTA_TOKENS, destino = RUTA_TEMA } = {}) {
  let tokens;
  try {
    tokens = JSON.parse(fs.readFileSync(origen, 'utf8'));
  } catch (error) {
    throw new Error(`No se pudo leer ${origen}: ${error.message}`);
  }
  const contenido = generarTema(tokens);

  const actual = fs.existsSync(destino) ? fs.readFileSync(destino, 'utf8').replace(/\r\n/g, '\n') : null;
  if (actual === contenido) {
    return { cambio: false };
  }
  fs.mkdirSync(path.dirname(destino), { recursive: true });
  fs.writeFileSync(destino, contenido, 'utf8');
  return { cambio: true };
}

module.exports = { generarTema, escribirTema, RUTA_TOKENS, RUTA_TEMA };

if (require.main === module) {
  const [origen, destino] = process.argv.slice(2);
  const rutas = { origen: origen ? path.resolve(origen) : RUTA_TOKENS, destino: destino ? path.resolve(destino) : RUTA_TEMA };
  try {
    const { cambio } = escribirTema(rutas);
    console.log(`${cambio ? 'theme.ts generado' : 'theme.ts ya estaba al día'}: ${path.relative(process.cwd(), rutas.destino)}`);
  } catch (error) {
    console.error(`No se generó theme.ts. ${error.message}`);
    process.exitCode = 1;
  }
}
