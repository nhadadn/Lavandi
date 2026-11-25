// Spec: openspec/changes/publicacion/specs/publicacion/spec.md, publicacion-E08 (tarea 3.2). El identificador de la app
// de iPhone y el nombre del paquete de Android salen de la configuración (APP_IDENTIFIER, base-E05; CLAUDE.md §5). El
// definitivo depende de la marca (DD-023 y DD-088) y no se puede cambiar después de publicar: mientras el entorno de
// producción traiga el marcador {{MARCA}} en su lugar, la compilación de producción se detiene antes de empezar y dice
// que falta el identificador.
import { readFileSync } from 'node:fs';
import path from 'node:path';

import { configuracionDeExpo, entornoMinimo, leerConfiguracion, RAIZ } from '../apoyo/configuracion';

const MARCADOR = '{{MARCA}}';

function envDelPerfil(perfil: 'preview' | 'production'): Record<string, string> {
  const eas = JSON.parse(readFileSync(path.join(RAIZ, 'eas.json'), 'utf8')) as {
    build?: Record<string, { env?: Record<string, string> }>;
  };
  return eas.build?.[perfil]?.env ?? {};
}

// Lo que EAS le da a la configuración al compilar para las tiendas, con valores de prueba.
function entornoDeProduccion(identificador: string): Record<string, string> {
  return {
    ...entornoMinimo('publicacion-E08'),
    EXPO_PUBLIC_API_URL: 'https://api.prueba.invalid/publicacion-e08',
    APP_IDENTIFIER: identificador,
    ...envDelPerfil('production'),
  };
}

describe('publicacion-E08: el identificador de la app sale de la configuración', () => {
  test('publicacion-E08 — en producción, el identificador de iPhone y el paquete de Android son los de APP_IDENTIFIER', () => {
    const configuracion = configuracionDeExpo(entornoDeProduccion('mx.prueba.publicacione08'));

    expect(configuracion.ios?.bundleIdentifier).toBe('mx.prueba.publicacione08');
    expect(configuracion.android?.package).toBe('mx.prueba.publicacione08');
  }, 120_000);

  test.each([MARCADOR, `mx.${MARCADOR}.app`, 'mx.{{ marca }}.app'])(
    'publicacion-E08 — con el marcador en el identificador (%s), la compilación de producción se detiene y dice que falta el identificador',
    (identificador) => {
      const lectura = leerConfiguracion(entornoDeProduccion(identificador));

      expect(lectura.status).not.toBe(0);
      expect(lectura.stderr).toContain('Falta el identificador de la app');
      expect(lectura.stderr).toContain('APP_IDENTIFIER');
    },
    120_000,
  );

  test('publicacion-E08 — el identificador no está escrito en eas.json: viene del entorno de EAS', () => {
    expect(envDelPerfil('production')).not.toHaveProperty('APP_IDENTIFIER');
    expect(envDelPerfil('preview')).not.toHaveProperty('APP_IDENTIFIER');
  });
});
