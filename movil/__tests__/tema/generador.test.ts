// El script de tokens (vault/arquitectura/adr/ADR-0013-adaptador-de-tokens.md): lee tokens.json y genera theme.ts.
// No tiene escenario en una spec: lo pide la pregunta abierta 10 de openspec/changes/registro-e-inicio-de-sesion/design.md
// (DD-090). Se prueba con tokens-de-prueba.json, que trae valores inventados y distintos en cada modo.
//
// El script se corre con node, como en la terminal y en la integración continua: dentro de Jest pasaría por Babel, que
// le agrega dependencias que fuera de movil/ no existen.
import { spawnSync } from 'node:child_process';
import { existsSync, mkdirSync, mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import vm from 'node:vm';
import ts from 'typescript';

const RAIZ_DEL_REPOSITORIO = path.resolve(__dirname, '..', '..', '..');
const SCRIPT = path.join(RAIZ_DEL_REPOSITORIO, 'herramientas', 'tokens', 'generar.js');
const TOKENS_DE_PRUEBA = path.join(__dirname, 'tokens-de-prueba.json');

let carpeta: string;

beforeAll(() => {
  carpeta = mkdtempSync(path.join(os.tmpdir(), 'tokens-'));
});

afterAll(() => {
  rmSync(carpeta, { recursive: true, force: true });
});

// Una copia nueva en cada uso, para que una prueba que la cambia no afecte a otra.
function tokensDePrueba(): Record<string, unknown> {
  return JSON.parse(readFileSync(TOKENS_DE_PRUEBA, 'utf8')) as Record<string, unknown>;
}

// Cambia (o borra, con undefined) el valor que está en la ruta indicada.
function cambiar(tokens: Record<string, unknown>, ruta: string[], valor: unknown): Record<string, unknown> {
  let nodo = tokens;
  for (const clave of ruta.slice(0, -1)) {
    nodo = nodo[clave] as Record<string, unknown>;
  }
  const ultima = ruta[ruta.length - 1];
  if (valor === undefined) {
    delete nodo[ultima];
  } else {
    nodo[ultima] = valor;
  }
  return tokens;
}

function correr(origen: string, destino: string) {
  return spawnSync(process.execPath, [SCRIPT, origen, destino], { encoding: 'utf8' });
}

// Escribe los tokens en una carpeta nueva, corre el script y devuelve lo que generó (null si no generó nada).
function generar(tokens: unknown) {
  const aqui = mkdtempSync(path.join(carpeta, 'corrida-'));
  const origen = path.join(aqui, 'tokens.json');
  const destino = path.join(aqui, 'theme.ts');
  writeFileSync(origen, JSON.stringify(tokens));
  const corrida = correr(origen, destino);
  return { ...corrida, codigo: existsSync(destino) ? readFileSync(destino, 'utf8') : null };
}

// Compila el TypeScript generado y devuelve lo que exporta, como datos simples.
function evaluar(codigo: string): Record<string, any> {
  const { outputText } = ts.transpileModule(codigo, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020 },
    reportDiagnostics: false,
  });
  const modulo = { exports: {} as Record<string, unknown> };
  vm.runInNewContext(outputText, { module: modulo, exports: modulo.exports });
  return JSON.parse(JSON.stringify(modulo.exports)) as Record<string, any>;
}

describe('ADR-0013: el script de tokens genera theme.ts', () => {
  describe('lo que genera', () => {
    let codigo: string;
    let exportado: Record<string, any>;

    beforeAll(() => {
      const corrida = generar(tokensDePrueba());
      if (corrida.status !== 0 || corrida.codigo === null) {
        throw new Error(`El script no generó theme.ts: ${corrida.stderr}`);
      }
      codigo = corrida.codigo;
      exportado = evaluar(codigo);
    });

    test('ADR-0013 — arriba dice que es generado, de dónde sale y cómo se regenera', () => {
      const encabezado = codigo.split('\n').slice(0, 8).join('\n');

      expect(encabezado).toMatch(/GENERADO/);
      expect(encabezado).toContain('no se edita a mano');
      expect(encabezado).toContain('vault/design-system/tokens.json');
      expect(encabezado).toContain('9.9.9');
      expect(encabezado).toContain('herramientas/tokens/generar.js');
      expect(encabezado).toContain('npm run tokens');
      expect(encabezado).toContain('ADR-0013');
    });

    test('ADR-0013 — el archivo generado es TypeScript válido', () => {
      const { diagnostics } = ts.transpileModule(codigo, { reportDiagnostics: true });

      expect(diagnostics ?? []).toEqual([]);
    });

    test('ADR-0013 — trae solo el modo claro: ningún valor del modo oscuro llega al tema', () => {
      expect(exportado.tema.modo).toBe('claro');
      expect(exportado.color.semantico).toEqual({ fondo: '#FAFAFA', texto: '#101010' });
      for (const oscuro of ['#0C0C0C', '#EFEFEF', '#0B0B01', '#0B0B02', '#0B0B03']) {
        expect(codigo).not.toContain(oscuro);
      }
    });

    test('ADR-0013 — de los colores de marca y de la marca extendida pasa solo el hexadecimal', () => {
      expect(exportado.color.marca).toEqual({ primario: '#112233', primario_oscuro: '#445566' });
      expect(exportado.color.marca_extendida).toEqual({ hueso: '#F1F2F3' });
    });

    test('ADR-0013 — de los colores de estado y de pedido pasa el valor del modo claro', () => {
      expect(exportado.color.estado).toEqual({ exito: '#0A0A01', error: '#0A0A03' });
      expect(exportado.color.pedido).toEqual({ confirmado: '#0A0A02' });
    });

    test('ADR-0013 — la rampa neutra, los espacios, los radios, las sombras y los tiempos pasan tal cual', () => {
      const { color, espaciado, radio, elevacion, duracion } = exportado;

      expect(color.neutro).toEqual({ 0: '#FFFFFF', 50: '#F0F0F0', 1000: '#000000' });
      expect(espaciado).toEqual({ 0: 0, 1: 4 });
      expect(radio).toEqual({ chico: 4, pildora: 999 });
      expect(elevacion).toEqual({ 0: 'none', 1: '0 1px 2px rgba(0,0,0,.08)' });
      expect(duracion).toEqual({ rapida: 120 });
    });

    test('ADR-0013 — cada familia pasa como el primer nombre de su pila, porque React Native no acepta pilas', () => {
      expect(exportado.tipografia.familia).toEqual({ titulares: 'Fuente Uno', cuerpo: 'Fuente Dos' });
    });

    test('ADR-0013 — la escala, los pesos y el interlineado pasan como números; el espaciado de letra, de em a número', () => {
      const { tipografia } = exportado;

      expect(tipografia.escala).toEqual({ cuerpo: 16, titulo: 24 });
      expect(tipografia.peso).toEqual({ regular: 400, fuerte: 700 });
      expect(tipografia.interlineado).toEqual({ normal: 1.5 });
      expect(tipografia.espaciado_letra).toEqual({ normal: 0, titular: -0.01 });
    });

    // DD-133, punto 5 (decisión 24 del acta del 2026-11-20): el grosor del borde de los controles, el del foco y el alto
    // del botón principal, que las piezas leían como cifras o como el borde más fino.
    test('ADR-0013 — los grosores de borde y los altos de control pasan tal cual, sin su comentario', () => {
      expect(exportado.grosor).toEqual({ control: 2, foco: 5 });
      expect(exportado.alto).toEqual({ boton_principal: 60 });
    });

    test('ADR-0013 — las curvas pasan de cubic-bezier a los cuatro números de Easing.bezier', () => {
      expect(exportado.curva).toEqual({ estandar: [0.2, 0, 0, 1] });
    });

    test('ADR-0013 — los umbrales de accesibilidad pasan como números, sin su fuente', () => {
      expect(exportado.accesibilidad).toEqual({
        contraste_minimo_texto_normal: 4.5,
        contraste_minimo_texto_grande: 3,
        contraste_minimo_componente: 3,
        texto_grande_desde_px: 18.66,
        area_tactil_minima_dp: 48,
        area_tactil_minima_pt_ios: 44,
        separacion_minima_entre_objetivos_dp: 8,
      });
    });

    test('ADR-0013 — tema reúne todos los grupos', () => {
      expect(exportado.tema).toEqual({
        modo: 'claro',
        color: exportado.color,
        tipografia: exportado.tipografia,
        espaciado: exportado.espaciado,
        radio: exportado.radio,
        grosor: exportado.grosor,
        alto: exportado.alto,
        elevacion: exportado.elevacion,
        duracion: exportado.duracion,
        curva: exportado.curva,
        accesibilidad: exportado.accesibilidad,
      });
    });

    test('ADR-0013 — no copia el nombre de $meta ni los comentarios, que pueden traer la marca', () => {
      expect(codigo).not.toContain('MarcaFicticia');
      expect(codigo).not.toContain('Comentario de prueba');
      expect(codigo).not.toContain('Nota de prueba');
      expect(codigo).not.toContain('Texto de la fuente');
    });

    test('ADR-0013 — la misma entrada da exactamente el mismo archivo, sin fechas de generación', () => {
      expect(generar(tokensDePrueba()).codigo).toBe(codigo);
      expect(codigo).not.toContain(String(new Date().getFullYear()));
    });
  });

  describe('cuando tokens.json no tiene la forma esperada, falla, nombra la clave y no escribe nada', () => {
    test.each([
      ['falta el modo claro', ['color', 'semantico', 'claro'], undefined, 'color.semantico.claro'],
      ['un color no es hexadecimal', ['color', 'semantico', 'claro', 'fondo'], 'azul', 'color.semantico.claro.fondo'],
      ['a un color de marca le falta el hexadecimal', ['color', 'marca', 'primario', 'hex'], undefined, 'color.marca.primario.hex'],
      ['a un estado le falta el modo claro', ['color', 'estado', 'exito', 'claro'], undefined, 'color.estado.exito.claro'],
      ['una medida no es número', ['espaciado', '1'], '4px', 'espaciado.1'],
      ['un espaciado de letra no está en em', ['tipografia', 'espaciado_letra', 'titular'], '1px', 'tipografia.espaciado_letra.titular'],
      ['una curva no es cubic-bezier', ['curva', 'estandar'], 'ease-in', 'curva.estandar'],
      ['a una curva le falta una cifra', ['curva', 'estandar'], 'cubic-bezier(.2,,0,1)', 'curva.estandar'],
      ['una cifra de la curva es solo espacio', ['curva', 'estandar'], 'cubic-bezier(.2, ,0,1)', 'curva.estandar'],
      ['falta un grupo entero', ['radio'], undefined, 'radio'],
      ['faltan los grosores de borde', ['grosor'], undefined, 'grosor'],
      ['un grosor no es número', ['grosor', 'foco'], '3px', 'grosor.foco'],
      ['faltan los altos de control', ['alto'], undefined, 'alto'],
      ['un alto no es número', ['alto', 'boton_principal'], '52px', 'alto.boton_principal'],
    ])('ADR-0013 — %s', (_caso, ruta, valor, clave) => {
      const corrida = generar(cambiar(tokensDePrueba(), ruta, valor));

      expect(corrida.status).not.toBe(0);
      expect(corrida.stderr).toContain(clave);
      expect(corrida.codigo).toBeNull();
    });

    test('ADR-0013 — si tokens.json no existe', () => {
      const destino = path.join(carpeta, 'sin-origen', 'theme.ts');

      const corrida = correr(path.join(carpeta, 'no-existe.json'), destino);

      expect(corrida.status).not.toBe(0);
      expect(corrida.stderr).toContain('no-existe.json');
      expect(existsSync(destino)).toBe(false);
    });
  });

  describe('el archivo en disco', () => {
    let origen: string;
    let destino: string;

    beforeEach(() => {
      const aqui = mkdtempSync(path.join(carpeta, 'disco-'));
      origen = path.join(aqui, 'tokens.json');
      destino = path.join(aqui, 'src', 'tema', 'theme.ts');
      writeFileSync(origen, readFileSync(TOKENS_DE_PRUEBA));
    });

    test('ADR-0013 — escribe theme.ts, con su carpeta, y si se corre otra vez no lo toca', () => {
      const primera = correr(origen, destino);
      const generado = readFileSync(destino, 'utf8');
      const segunda = correr(origen, destino);

      expect(primera.status).toBe(0);
      expect(primera.stdout).toContain('theme.ts generado');
      expect(segunda.status).toBe(0);
      expect(segunda.stdout).toContain('theme.ts ya estaba al día');
      expect(readFileSync(destino, 'utf8')).toBe(generado);
    });

    test('ADR-0013 — un theme.ts igual pero con fin de línea de Windows ya está al día', () => {
      correr(origen, destino);
      const conCrlf = readFileSync(destino, 'utf8').replace(/\n/g, '\r\n');
      writeFileSync(destino, conCrlf);

      const corrida = correr(origen, destino);

      expect(corrida.stdout).toContain('theme.ts ya estaba al día');
      expect(readFileSync(destino, 'utf8')).toBe(conCrlf);
    });

    test('ADR-0013 — si theme.ts se editó a mano, lo vuelve a generar', () => {
      correr(origen, destino);
      const generado = readFileSync(destino, 'utf8');
      writeFileSync(destino, generado.replace('#FAFAFA', '#ABCDEF'));

      const corrida = correr(origen, destino);

      expect(corrida.stdout).toContain('theme.ts generado');
      expect(readFileSync(destino, 'utf8')).toBe(generado);
    });
  });

  describe('cómo se corre', () => {
    test('ADR-0013 — `npm run tokens` en movil/ y `npm run generar` en herramientas/tokens/ corren el mismo script', () => {
      const leer = (ruta: string) =>
        JSON.parse(readFileSync(path.join(RAIZ_DEL_REPOSITORIO, ruta), 'utf8')) as { scripts?: Record<string, string> };

      expect(leer('movil/package.json').scripts?.tokens).toBe('node ../herramientas/tokens/generar.js');
      expect(leer('herramientas/tokens/package.json').scripts?.generar).toBe('node generar.js');
    });

    test('ADR-0013 — sin rutas, lee vault/design-system/tokens.json y escribe movil/src/tema/theme.ts', () => {
      // Una copia del repositorio con solo el script y unos tokens de prueba, para no tocar los archivos reales.
      const copia = mkdtempSync(path.join(carpeta, 'repositorio-'));
      mkdirSync(path.join(copia, 'herramientas', 'tokens'), { recursive: true });
      mkdirSync(path.join(copia, 'vault', 'design-system'), { recursive: true });
      writeFileSync(path.join(copia, 'herramientas', 'tokens', 'generar.js'), readFileSync(SCRIPT));
      writeFileSync(path.join(copia, 'vault', 'design-system', 'tokens.json'), readFileSync(TOKENS_DE_PRUEBA));

      const corrida = spawnSync(process.execPath, [path.join(copia, 'herramientas', 'tokens', 'generar.js')], {
        cwd: copia,
        encoding: 'utf8',
      });

      expect(corrida.status).toBe(0);
      expect(readFileSync(path.join(copia, 'movil', 'src', 'tema', 'theme.ts'), 'utf8')).toBe(
        generar(tokensDePrueba()).codigo,
      );
    });
  });
});
