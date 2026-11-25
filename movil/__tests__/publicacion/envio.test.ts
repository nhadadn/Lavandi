// Spec: openspec/changes/publicacion/specs/publicacion/spec.md, publicacion-E12 (tarea 4.2). La app "se publicará de
// forma simultánea" en las dos tiendas (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3): el perfil de envío de Android de
// EAS Submit (eas.json, ADR-0002) deja la compilación en Google Play Console como borrador, sin publicarse sola, para
// liberarla a mano el mismo día que en App Store, cuando las dos revisiones estén aprobadas.
//
// El envío mismo a las cuentas de EL CLIENTE es publicacion-E11, bloqueado (DD-017, DD-084, DD-121 y DD-109); el de
// iPhone llega con él.
import { readFileSync } from 'node:fs';
import path from 'node:path';

import { RAIZ } from '../apoyo/configuracion';

interface EnvioDeAndroid {
  track?: string;
  releaseStatus?: string;
  serviceAccountKeyPath?: string;
}

interface Eas {
  build?: Record<string, unknown>;
  submit?: Record<string, { android?: EnvioDeAndroid; ios?: unknown }>;
}

function eas(): Eas {
  return JSON.parse(readFileSync(path.join(RAIZ, 'eas.json'), 'utf8')) as Eas;
}

describe('publicacion-E12: ninguna tienda libera antes que la otra', () => {
  test('publicacion-E12 — el envío de Android deja la compilación como borrador: no se publica sola', () => {
    expect(eas().submit?.production?.android?.releaseStatus).toBe('draft');
  });

  test('publicacion-E12 — el borrador va a la pista de producción de Google Play, donde se libera a mano', () => {
    expect(eas().submit?.production?.android?.track).toBe('production');
  });

  test('publicacion-E12 — el perfil de envío se llama como el de la compilación de las tiendas', () => {
    expect(Object.keys(eas().submit ?? {})).toEqual(['production']);
    expect(eas().build).toHaveProperty('production');
  });

  test('publicacion-E12 — la llave de la cuenta de servicio de Google Play no se busca en el repositorio: vive en EAS', () => {
    expect(eas().submit?.production?.android).not.toHaveProperty('serviceAccountKeyPath');
  });

  test('publicacion-E12 — el envío de iPhone no se configura todavía: espera publicacion-E11', () => {
    expect(eas().submit?.production).not.toHaveProperty('ios');
  });
});
