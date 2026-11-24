// theme.ts frente a su fuente (vault/arquitectura/adr/ADR-0013-adaptador-de-tokens.md): que nadie lo edite a mano, que
// los pares de contraste del design system sigan cumpliendo después de generar y que ningún color se escriba fuera de él.
import { spawnSync } from 'node:child_process';
import { existsSync, mkdtempSync, readdirSync, readFileSync, rmSync, statSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';

import { tema } from '../../src/tema/theme';

const RAIZ = path.resolve(__dirname, '..', '..');
const TOKENS = path.resolve(RAIZ, '..', 'vault', 'design-system', 'tokens.json');
const THEME = path.join(RAIZ, 'src', 'tema', 'theme.ts');
const SCRIPT = path.resolve(RAIZ, '..', 'herramientas', 'tokens', 'generar.js');

describe('ADR-0013: theme.ts corresponde a tokens.json', () => {
  test('ADR-0013 — theme.ts es exactamente lo que genera tokens.json (si falla, corre `npm run tokens`)', () => {
    // Se genera aparte, con node, y se compara: la prueba no reescribe el theme.ts que revisa.
    const carpeta = mkdtempSync(path.join(os.tmpdir(), 'tema-'));
    try {
      const destino = path.join(carpeta, 'theme.ts');
      const corrida = spawnSync(process.execPath, [SCRIPT, TOKENS, destino], { encoding: 'utf8' });
      expect(corrida.stderr).toBe('');

      // En Windows, git puede dejar el archivo con fin de línea \r\n: no es una edición.
      const actual = readFileSync(THEME, 'utf8').replace(/\r\n/g, '\n');
      expect(actual).toBe(readFileSync(destino, 'utf8'));
    } finally {
      rmSync(carpeta, { recursive: true, force: true });
    }
  });
});

// Los pares de vault/design-system/design-system.md ("Resultados completos", modo claro), más Agua Profundo sobre
// Hueso (accion / fondo), el de 4.51:1 que cita el ADR-0013. El umbral sale de los tokens de accesibilidad.
type Umbral = 'contraste_minimo_texto_normal' | 'contraste_minimo_componente';
const { semantico, estado, pedido } = tema.color;
const PARES: [string, string, string, Umbral][] = [
  ['texto', semantico.texto, 'fondo', 'contraste_minimo_texto_normal'],
  ['texto', semantico.texto, 'superficie', 'contraste_minimo_texto_normal'],
  ['texto', semantico.texto, 'superficie_elevada', 'contraste_minimo_texto_normal'],
  ['texto_suave', semantico.texto_suave, 'superficie', 'contraste_minimo_texto_normal'],
  ['texto_suave', semantico.texto_suave, 'fondo', 'contraste_minimo_texto_normal'],
  ['texto_deshabilitado', semantico.texto_deshabilitado, 'superficie', 'contraste_minimo_componente'],
  ['accion_texto', semantico.accion_texto, 'accion', 'contraste_minimo_texto_normal'],
  ['accion', semantico.accion, 'superficie', 'contraste_minimo_texto_normal'],
  ['accion', semantico.accion, 'fondo', 'contraste_minimo_texto_normal'],
  ['borde_fuerte', semantico.borde_fuerte, 'superficie', 'contraste_minimo_componente'],
  ['foco', semantico.foco, 'superficie', 'contraste_minimo_componente'],
  ['estado exito', estado.exito, 'superficie', 'contraste_minimo_texto_normal'],
  ['estado atencion', estado.atencion, 'superficie', 'contraste_minimo_texto_normal'],
  ['estado error', estado.error, 'superficie', 'contraste_minimo_texto_normal'],
  ['estado informacion', estado.informacion, 'superficie', 'contraste_minimo_texto_normal'],
  ['pedido confirmado', pedido.confirmado, 'superficie', 'contraste_minimo_texto_normal'],
  ['pedido recolectado', pedido.recolectado, 'superficie', 'contraste_minimo_texto_normal'],
  ['pedido en_proceso', pedido.en_proceso, 'superficie', 'contraste_minimo_texto_normal'],
  ['pedido listo', pedido.listo, 'superficie', 'contraste_minimo_texto_normal'],
  ['pedido entregado', pedido.entregado, 'superficie', 'contraste_minimo_texto_normal'],
];

// Luminancia relativa y razón de contraste de WCAG 2.1.
function luminancia(hex: string): number {
  const canales = [1, 3, 5].map((inicio) => parseInt(hex.slice(inicio, inicio + 2), 16) / 255);
  const [r, g, b] = canales.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contraste(a: string, b: string): number {
  const [clara, oscura] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
  return (clara + 0.05) / (oscura + 0.05);
}

describe('ADR-0013: los pares de contraste del design system cumplen con el tema generado', () => {
  test.each(PARES)('ADR-0013 — %s (%s) sobre %s cumple %s', (_nombre, color, fondo, umbral) => {
    const sobre = semantico[fondo as keyof typeof semantico];

    expect(contraste(color, sobre)).toBeGreaterThanOrEqual(tema.accesibilidad[umbral]);
  });
});

describe('ADR-0013: ningún color se escribe a mano fuera de theme.ts', () => {
  test('ADR-0013 — el código de la app no trae colores sueltos', () => {
    const archivos = archivosDeCodigo().filter((archivo) => path.resolve(archivo) !== path.resolve(THEME));
    expect(archivos.length).toBeGreaterThan(0);

    // Textos con un hexadecimal ('#FFF') o una función de color de CSS ('rgba(…)'), y propiedades de color con un
    // texto literal (color: 'red', tintColor="red").
    const colorSuelto = /['"`]\s*(?:#[0-9a-f]{3,8}|(?:rgba?|hsla?)\()|color\s*[:=]\s*\{?\s*['"`]/i;
    const hallazgos = archivos.filter((archivo) => colorSuelto.test(readFileSync(archivo, 'utf8')));
    expect(hallazgos).toEqual([]);
  });
});

// El código de las pantallas: la entrada, el componente raíz, app/ y src/. Queda fuera app.config.ts: el fondo de su
// ícono adaptable (#E6F4FE) es el marcador neutro de la plantilla de Expo, que espera a la marca junto con el ícono
// (openspec/changes/base-tecnica/design.md, pregunta abierta 3), y no es un color del design system.
function archivosDeCodigo(): string[] {
  const raiz = ['index.ts', 'App.tsx']
    .map((archivo) => path.join(RAIZ, archivo))
    .filter((archivo) => existsSync(archivo));
  return [...raiz, ...recorrer(path.join(RAIZ, 'app')), ...recorrer(path.join(RAIZ, 'src'))];
}

function recorrer(carpeta: string): string[] {
  if (!existsSync(carpeta)) {
    return [];
  }
  return readdirSync(carpeta).flatMap((nombre) => {
    const ruta = path.join(carpeta, nombre);
    if (statSync(ruta).isDirectory()) {
      return recorrer(ruta);
    }
    return /\.(ts|tsx|js|jsx)$/.test(nombre) ? [ruta] : [];
  });
}
