// Spec: openspec/changes/publicacion/specs/publicacion/spec.md, publicacion-E06 (tarea 2.2). "Toda la información que
// viaja entre el teléfono del usuario y el servidor va codificada" (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3): las
// compilaciones de los perfiles `preview` y `production` de EAS (eas.json; ADR-0002 y ADR-0012) solo hablan con una API
// por https y no permiten el tráfico sin cifrar, ni en Android ni en iPhone (SEG-1).
//
// Cada perfil deja su nombre en PERFIL_DE_COMPILACION (el "env" de eas.json, que EAS usa al leer la configuración en la
// máquina y en la nube), y app.config.ts revisa con él la dirección de la API. La dirección no está en eas.json: es la
// variable EXPO_PUBLIC_API_URL del entorno de EAS de cada perfil (publicacion-E07). Sin perfil, en local, la app sigue
// hablando con el servidor de la máquina por http (vault/formas-de-trabajo/correr-la-app-en-local.md).
import { readFileSync } from 'node:fs';
import path from 'node:path';

import {
  aplicacionDelManifiesto,
  configuracionDeExpo,
  entornoMinimo,
  leerConfiguracion,
  RAIZ,
  type ConfiguracionLeida,
  type Lectura,
} from '../apoyo/configuracion';

interface PerfilDeEas {
  env?: Record<string, string>;
}

const PERFILES = ['preview', 'production'] as const;
type Perfil = (typeof PERFILES)[number];

function perfilesDeEas(): Record<string, PerfilDeEas> {
  const eas = JSON.parse(readFileSync(path.join(RAIZ, 'eas.json'), 'utf8')) as { build?: Record<string, PerfilDeEas> };
  return eas.build ?? {};
}

// Lo que EAS le da a la configuración al compilar con ese perfil: el "env" de eas.json y las variables de su entorno de
// EAS, aquí con valores de prueba.
function entornoDelPerfil(perfil: Perfil, api: string): Record<string, string> {
  return {
    ...entornoMinimo(`publicacion-E06-${perfil}`),
    APP_IDENTIFIER: `mx.prueba.publicacione06${perfil}`,
    EXPO_PUBLIC_API_URL: api,
    ...(perfilesDeEas()[perfil]?.env ?? {}),
  };
}

function seguridadDeTransporte(configuracion: ConfiguracionLeida): Record<string, unknown> {
  return (configuracion._internal?.modResults?.ios?.infoPlist?.NSAppTransportSecurity ?? {}) as Record<string, unknown>;
}

describe('publicacion-E06: la app solo habla por https', () => {
  const porPerfil = {} as Record<Perfil, { https: ConfiguracionLeida; http: Lectura }>;
  let local: ConfiguracionLeida;

  beforeAll(() => {
    for (const perfil of PERFILES) {
      porPerfil[perfil] = {
        https: configuracionDeExpo(entornoDelPerfil(perfil, `https://api.prueba.invalid/publicacion-e06-${perfil}`)),
        http: leerConfiguracion(entornoDelPerfil(perfil, `http://api.prueba.invalid/publicacion-e06-${perfil}`)),
      };
    }
    local = configuracionDeExpo(entornoMinimo('publicacion-E06-local'));
  }, 240_000);

  test('publicacion-E06 — eas.json tiene los perfiles preview y production, y cada uno deja su nombre a la configuración', () => {
    const perfiles = perfilesDeEas();
    for (const perfil of PERFILES) {
      expect(perfiles[perfil]?.env?.PERFIL_DE_COMPILACION).toBe(perfil);
    }
  });

  test.each(PERFILES)('publicacion-E06 — (%s) con una API por http, la configuración se detiene y lo dice', (perfil) => {
    const { http } = porPerfil[perfil];
    expect(http.status).not.toBe(0);
    expect(http.stderr).toContain('EXPO_PUBLIC_API_URL');
    expect(http.stderr).toContain('https');
  });

  test.each(PERFILES)('publicacion-E06 — (%s) con la API por https, la configuración sale', (perfil) => {
    expect(porPerfil[perfil].https.extra).toBeDefined();
  });

  test.each(PERFILES)('publicacion-E06 — (%s) Android no permite el tráfico sin cifrar', (perfil) => {
    expect(aplicacionDelManifiesto(porPerfil[perfil].https)['android:usesCleartextTraffic']).toBe('false');
  });

  test.each(PERFILES)('publicacion-E06 — (%s) iPhone no permite cargas sin cifrar, ni a la red local ni con excepciones', (perfil) => {
    const transporte = seguridadDeTransporte(porPerfil[perfil].https);
    expect(transporte.NSAllowsArbitraryLoads).toBe(false);
    expect(transporte.NSAllowsLocalNetworking).not.toBe(true);
    expect(transporte).not.toHaveProperty('NSExceptionDomains');
  });

  test('publicacion-E06 — sin perfil, en local, la configuración acepta el servidor de la máquina por http', () => {
    expect(aplicacionDelManifiesto(local)).not.toHaveProperty('android:usesCleartextTraffic');
  });

  test('publicacion-E06 — con un perfil que no existe, la configuración se detiene en vez de tratarlo como local', () => {
    const lectura = leerConfiguracion({ ...entornoMinimo('publicacion-E06'), PERFIL_DE_COMPILACION: 'otro' });
    expect(lectura.status).not.toBe(0);
    expect(lectura.stderr).toContain('PERFIL_DE_COMPILACION');
  });
});
