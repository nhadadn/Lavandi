// Genera desde vault/design-system/tokens.json las dos salidas que decide el ADR-0013: movil/src/tema/theme.ts para la
// app y servidor/resources/css/tokens.css, las variables CSS de la web.
//
// Uso, desde la raíz del repositorio:   node herramientas/tokens/generar.js   (escribe los dos en sus rutas)
//                                       node herramientas/tokens/generar.js <origen> <destino-ts> [destino-css]
//                                       (con rutas, el CSS se escribe solo si se da la tercera)
// o desde movil/:                       npm run tokens
// o desde herramientas/tokens/:         npm run generar   (lo que corre la integración continua)
//
// Es idempotente: si un archivo ya corresponde a tokens.json, no lo toca. Falla, nombrando la clave, si tokens.json no
// tiene la forma que espera, y entonces no escribe ninguno: es preferible a generar un tema incompleto.
'use strict';

const fs = require('node:fs');
const path = require('node:path');

const RAIZ = path.resolve(__dirname, '..', '..');
const RUTA_TOKENS = path.join(RAIZ, 'vault', 'design-system', 'tokens.json');
const RUTA_TEMA = path.join(RAIZ, 'movil', 'src', 'tema', 'theme.ts');
const RUTA_CSS = path.join(RAIZ, 'servidor', 'resources', 'css', 'tokens.css');

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

/** CSS sí acepta pilas de familias: pasa la pila entera. */
function pilaDeFamilias(valor, ruta) {
  return texto(valor, ruta).trim();
}

/** CSS usa la curva tal como está en tokens.json, después de comprobar que es una cubic-bezier de cuatro números. */
function curvaEnCss(valor, ruta) {
  bezier(valor, ruta);
  return valor.trim();
}

/**
 * Los valores de tokens.json que usa cada salida, ya validados. Las familias y las curvas pasan como las pide cada
 * plataforma: para React Native, la primera familia y los cuatro números de la curva.
 */
function leerTema(tokens, { familia = primeraFamilia, curva = bezier } = {}) {
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
      familia: mapear(tipografia.familia, ['tipografia', 'familia'], familia, ['estado']),
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
    curva: mapear(raiz.curva, ['curva'], curva),
    accesibilidad: mapear(raiz.accesibilidad, ['accesibilidad'], numero, ['fuente']),
  };
}

/** La versión y la fecha de $meta, para el encabezado de cada archivo generado; vacía si no están. */
function versionDe(tokens) {
  const meta = tokens && typeof tokens.$meta === 'object' && tokens.$meta !== null ? tokens.$meta : {};
  return [meta.version, meta.fecha].filter((v) => typeof v === 'string' && v !== '').join(', ');
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
  const version = versionDe(tokens);

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

// ---------------------------------------------------------------------------------------------------------------
// Escritura de tokens.css: las mismas claves que theme.ts, como variables --ds-* con las unidades de la web. Siempre el
// mismo texto para la misma entrada (sin fechas), con fin de línea \n.
// ---------------------------------------------------------------------------------------------------------------

const px = (n) => (n === 0 ? '0' : `${n}px`);

// La unidad de cada grupo en CSS. Lo que no está aquí pasa tal cual: colores, pilas de familias, pesos, interlineados,
// sombras y curvas.
const UNIDADES_CSS = {
  // Los tamaños de letra, en rem sobre 16 px, para que sigan el tamaño de letra que elija quien lee.
  'tipografia.escala': (n) => (n === 0 ? '0' : `${n / 16}rem`),
  'tipografia.espaciado_letra': (n) => (n === 0 ? '0' : `${n}em`),
  espaciado: px,
  radio: px,
  grosor: px,
  alto: px,
  // Una duración sin unidad no es válida en CSS, ni siquiera en 0.
  duracion: (n) => `${n}ms`,
  // Los contrastes son razones, sin unidad; las medidas (en px, dp o pt) pasan a px.
  accesibilidad: (n, nombre) => (/_(px|dp|pt_ios)$/.test(nombre) ? px(n) : String(n)),
};

/** Una línea `--ds-grupo-clave: valor;` por cada valor del grupo, con los guiones bajos de las claves como guiones. */
function variablesCss(valor, ruta) {
  if (typeof valor === 'object' && valor !== null) {
    return Object.entries(valor).flatMap(([nombre, hijo]) => variablesCss(hijo, [...ruta, nombre]));
  }
  const unidad = UNIDADES_CSS[ruta.slice(0, 2).join('.')] ?? UNIDADES_CSS[ruta[0]];
  const escrito = unidad && typeof valor === 'number' ? unidad(valor, ruta[ruta.length - 1]) : String(valor);
  return [`--ds-${ruta.join('-').replace(/_/g, '-')}: ${escrito};`];
}

/**
 * El contenido de tokens.css para unos tokens: las variables --ds-* en :root, solo del modo claro, y las duraciones en
 * 0 con movimiento reducido. Lanza un error que nombra la clave si los tokens no tienen la forma esperada.
 * @param {unknown} tokens el contenido de tokens.json, ya leído como JSON
 * @returns {string}
 */
function generarCss(tokens) {
  const tema = leerTema(tokens, { familia: pilaDeFamilias, curva: curvaEnCss });
  const version = versionDe(tokens);

  const encabezado = [
    '/*',
    ' * ARCHIVO GENERADO: no se edita a mano.',
    ` * Sale de vault/design-system/tokens.json${version ? ` (versión ${version})` : ''} con`,
    ' * herramientas/tokens/generar.js (ADR-0013). Para cambiar un valor se cambia tokens.json y se corre el script.',
    ` * Solo modo ${MODO} (C-141). Los componentes usan --ds-color-semantico-*, nunca --ds-color-marca-* directo`,
    ' * (design-system.md, Decisión 3). Las medidas van en px, y los tamaños de letra en rem sobre 16 px.',
    ' */',
  ];

  const raiz = [':root {', ...variablesCss(tema, []).map((linea) => `    ${linea}`), '}'];

  const enCero = Object.fromEntries(Object.keys(tema.duracion).map((nombre) => [nombre, 0]));
  const movimientoReducido = [
    '/* Movimiento reducido: todas las duraciones valen 0 (componentes.md, reglas transversales). */',
    '@media (prefers-reduced-motion: reduce) {',
    '    :root {',
    ...variablesCss(enCero, ['duracion']).map((linea) => `        ${linea}`),
    '    }',
    '}',
  ];

  return `${[encabezado, raiz, movimientoReducido].map((bloque) => bloque.join('\n')).join('\n\n')}\n`;
}

// ---------------------------------------------------------------------------------------------------------------
// Lectura del origen y escritura en disco.
// ---------------------------------------------------------------------------------------------------------------

function leerTokens(origen) {
  try {
    return JSON.parse(fs.readFileSync(origen, 'utf8'));
  } catch (error) {
    throw new Error(`No se pudo leer ${origen}: ${error.message}`);
  }
}

/**
 * Escribe el contenido solo si cambió. Un archivo que difiere solo en el fin de línea (\r\n, como lo deja git en
 * Windows) ya está al día.
 * @returns {{ cambio: boolean }}
 */
function escribirSiCambio(destino, contenido) {
  const actual = fs.existsSync(destino) ? fs.readFileSync(destino, 'utf8').replace(/\r\n/g, '\n') : null;
  if (actual === contenido) {
    return { cambio: false };
  }
  fs.mkdirSync(path.dirname(destino), { recursive: true });
  fs.writeFileSync(destino, contenido, 'utf8');
  return { cambio: true };
}

/**
 * Lee tokens.json y escribe theme.ts solo si cambió.
 * @param {{ origen?: string, destino?: string }} [rutas]
 * @returns {{ cambio: boolean }}
 */
function escribirTema({ origen = RUTA_TOKENS, destino = RUTA_TEMA } = {}) {
  return escribirSiCambio(destino, generarTema(leerTokens(origen)));
}

module.exports = { generarTema, generarCss, escribirTema, RUTA_TOKENS, RUTA_TEMA, RUTA_CSS };

if (require.main === module) {
  // Sin argumentos, los dos archivos del repositorio. Con rutas, el CSS solo si se da la tercera: así una prueba que
  // genera theme.ts en una carpeta temporal nunca reescribe el tokens.css del repositorio.
  const argumentos = process.argv.slice(2);
  const [origen, destinoTema, destinoCss] = argumentos.map((ruta) => path.resolve(ruta));
  const rutas =
    argumentos.length === 0
      ? { origen: RUTA_TOKENS, tema: RUTA_TEMA, css: RUTA_CSS }
      : { origen, tema: destinoTema ?? RUTA_TEMA, css: destinoCss ?? null };
  // Las salidas que faltan por escribir, en el orden en que se escriben: si una escritura falla a medias, el mensaje
  // nombra solo las que no se escribieron.
  const faltan = rutas.css ? ['theme.ts', 'tokens.css'] : ['theme.ts'];
  try {
    // Los dos se generan antes de escribir el primero: si tokens.json no tiene la forma esperada, no se escribe nada.
    const tokens = leerTokens(rutas.origen);
    const salidas = [{ nombre: 'theme.ts', destino: rutas.tema, contenido: generarTema(tokens) }];
    if (rutas.css) {
      salidas.push({ nombre: 'tokens.css', destino: rutas.css, contenido: generarCss(tokens) });
    }
    for (const { nombre, destino, contenido } of salidas) {
      const { cambio } = escribirSiCambio(destino, contenido);
      faltan.shift();
      console.log(`${nombre} ${cambio ? 'generado' : 'ya estaba al día'}: ${path.relative(process.cwd(), destino)}`);
    }
  } catch (error) {
    console.error(`No se generó ${faltan.join(' ni ')}. ${error.message}`);
    process.exitCode = 1;
  }
}
