// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md.
// El cliente de la API de acceso, con fetch simulado. Las pantallas lo usan; aquí se prueba solo lo que manda
// al servidor y cómo lee cada respuesta del contrato de la API.
import {
  cerrarSesionEnServidor,
  consultarCuenta,
  iniciarSesion,
  iniciarSesionConGoogle,
  pedirRecuperacion,
  registrar,
} from '../../src/acceso/api';
import { plazoDeLaApi } from '../../src/configuracion/entorno';

// Una dirección de prueba que no puede estar escrita en el código: si la consulta va ahí, salió del entorno.
const BASE = 'http://api.prueba.invalid/registro/api';

const USUARIO = { id_publico: '01JD0000000000000000000000', correo: 'persona@ejemplo.com', rol: 'usuario' };

interface RespuestaFalsa {
  ok: boolean;
  status: number;
  json: () => Promise<unknown>;
}

function respuesta(status: number, cuerpo?: unknown): RespuestaFalsa {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => {
      if (cuerpo === undefined) {
        throw new SyntaxError('Respuesta sin cuerpo');
      }
      return cuerpo;
    },
  };
}

describe('registro: cliente de la API de acceso', () => {
  const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
  const fetchOriginal = global.fetch;
  let fetchFalso: jest.Mock;

  beforeEach(() => {
    process.env.EXPO_PUBLIC_API_URL = BASE;
    fetchFalso = jest.fn();
    global.fetch = fetchFalso as unknown as typeof fetch;
  });

  afterEach(() => {
    if (direccionOriginal === undefined) {
      delete process.env.EXPO_PUBLIC_API_URL;
    } else {
      process.env.EXPO_PUBLIC_API_URL = direccionOriginal;
    }
    global.fetch = fetchOriginal;
  });

  function llamada(indice = 0): { url: string; opciones: RequestInit; cuerpo: unknown } {
    const [url, opciones] = fetchFalso.mock.calls[indice] as [string, RequestInit];
    const cuerpo = typeof opciones.body === 'string' ? JSON.parse(opciones.body) : undefined;
    return { url, opciones, cuerpo };
  }

  function encabezados(opciones: RequestInit): Record<string, string> {
    return opciones.headers as Record<string, string>;
  }

  describe('registro-E04: el registro va al servidor y su respuesta se lee', () => {
    test('registro-E04 — manda el correo y la contraseña, y nada más, a la ruta de registro', async () => {
      fetchFalso.mockResolvedValue(respuesta(201, { token: 'token-1', usuario: USUARIO }));

      await registrar({ correo: 'persona@ejemplo.com', password: 'secreta123' });

      const { url, opciones, cuerpo } = llamada();
      expect(url).toBe(`${BASE}/registro`);
      expect(opciones.method).toBe('POST');
      expect(encabezados(opciones)).toMatchObject({
        Accept: 'application/json',
        'Content-Type': 'application/json',
      });
      expect(cuerpo).toEqual({ correo: 'persona@ejemplo.com', password: 'secreta123' });
    });

    test('registro-E04 — con 201 entrega el token y la cuenta', async () => {
      fetchFalso.mockResolvedValue(respuesta(201, { token: 'token-1', usuario: USUARIO }));

      const resultado = await registrar({ correo: 'persona@ejemplo.com', password: 'secreta123' });

      expect(resultado).toEqual({ ok: true, datos: { token: 'token-1', usuario: USUARIO } });
    });

    test('registro-E04 — con 422 entrega el error de cada campo', async () => {
      fetchFalso.mockResolvedValue(
        respuesta(422, {
          message: 'El correo ya tiene cuenta.',
          errors: { correo: ['El correo ya tiene cuenta.'], password: ['Mínimo 8 caracteres.'] },
        }),
      );

      const resultado = await registrar({ correo: 'persona@ejemplo.com', password: 'corta' });

      expect(resultado).toEqual({
        ok: false,
        motivo: 'invalido',
        mensaje: 'El correo ya tiene cuenta.',
        errores: { correo: ['El correo ya tiene cuenta.'], password: ['Mínimo 8 caracteres.'] },
      });
    });

    test('registro-E04 — sin conexión con el servidor, lo dice sin lanzar el error', async () => {
      fetchFalso.mockRejectedValue(new TypeError('Network request failed'));

      const resultado = await registrar({ correo: 'persona@ejemplo.com', password: 'secreta123' });

      expect(resultado).toEqual({ ok: false, motivo: 'sin-conexion' });
    });
  });

  describe('registro-E09: el inicio de sesión va al servidor y su respuesta se lee', () => {
    test('registro-E09 — manda el correo y la contraseña a la ruta de sesión', async () => {
      fetchFalso.mockResolvedValue(respuesta(200, { token: 'token-2', usuario: USUARIO }));

      const resultado = await iniciarSesion({ correo: 'persona@ejemplo.com', password: 'secreta123' });

      const { url, opciones, cuerpo } = llamada();
      expect(url).toBe(`${BASE}/sesion`);
      expect(opciones.method).toBe('POST');
      expect(cuerpo).toEqual({ correo: 'persona@ejemplo.com', password: 'secreta123' });
      expect(resultado).toEqual({ ok: true, datos: { token: 'token-2', usuario: USUARIO } });
    });

    test('registro-E09 — con 422 entrega el error que manda el servidor', async () => {
      fetchFalso.mockResolvedValue(
        respuesta(422, {
          message: 'Las credenciales no son válidas.',
          errors: { correo: ['Las credenciales no son válidas.'] },
        }),
      );

      const resultado = await iniciarSesion({ correo: 'persona@ejemplo.com', password: 'otra' });

      expect(resultado).toEqual({
        ok: false,
        motivo: 'invalido',
        mensaje: 'Las credenciales no son válidas.',
        errores: { correo: ['Las credenciales no son válidas.'] },
      });
    });

    test('registro-E09 — con 429 dice que hay demasiados intentos', async () => {
      fetchFalso.mockResolvedValue(respuesta(429, { message: 'Demasiados intentos.' }));

      const resultado = await iniciarSesion({ correo: 'persona@ejemplo.com', password: 'otra' });

      expect(resultado).toEqual({ ok: false, motivo: 'demasiados-intentos', mensaje: 'Demasiados intentos.' });
    });

    test('registro-E09 — con 401 dice que no hay sesión, con el mensaje del servidor', async () => {
      fetchFalso.mockResolvedValue(respuesta(401, { message: 'Las credenciales no son válidas.' }));

      const resultado = await iniciarSesion({ correo: 'persona@ejemplo.com', password: 'otra' });

      expect(resultado).toEqual({ ok: false, motivo: 'sin-sesion', mensaje: 'Las credenciales no son válidas.' });
    });

    test('registro-E09 — una respuesta que no es del contrato se entrega como error del servidor', async () => {
      fetchFalso.mockResolvedValue(respuesta(500));

      const resultado = await iniciarSesion({ correo: 'persona@ejemplo.com', password: 'secreta123' });

      expect(resultado).toEqual({ ok: false, motivo: 'servidor', mensaje: undefined });
    });
  });

  describe('registro-E11: la propia cuenta se consulta con el token', () => {
    test('registro-E11 — manda el token como Bearer a la ruta de la cuenta', async () => {
      fetchFalso.mockResolvedValue(respuesta(200, { usuario: USUARIO }));

      const resultado = await consultarCuenta('token-3');

      const { url, opciones } = llamada();
      expect(url).toBe(`${BASE}/cuenta`);
      expect(opciones.method).toBe('GET');
      expect(encabezados(opciones)).toMatchObject({ Authorization: 'Bearer token-3', Accept: 'application/json' });
      expect(resultado).toEqual({ ok: true, datos: { usuario: USUARIO } });
    });

    test('registro-E11 — con 401 dice que el servidor ya no reconoce la sesión', async () => {
      fetchFalso.mockResolvedValue(respuesta(401, { message: 'Unauthenticated.' }));

      const resultado = await consultarCuenta('token-revocado');

      expect(resultado).toEqual({ ok: false, motivo: 'sin-sesion', mensaje: 'Unauthenticated.' });
    });
  });

  describe('registro-E13: cerrar la sesión pide al servidor revocar el token', () => {
    test('registro-E13 — manda DELETE a la ruta de sesión con el token como Bearer', async () => {
      fetchFalso.mockResolvedValue(respuesta(204));

      const resultado = await cerrarSesionEnServidor('token-4');

      const { url, opciones } = llamada();
      expect(url).toBe(`${BASE}/sesion`);
      expect(opciones.method).toBe('DELETE');
      expect(encabezados(opciones)).toMatchObject({ Authorization: 'Bearer token-4' });
      expect(resultado).toEqual({ ok: true, datos: undefined });
    });
  });

  describe('registro-E19: el idToken de Google va al servidor', () => {
    test('registro-E19 — manda el idToken a la ruta de Google y entrega la sesión', async () => {
      fetchFalso.mockResolvedValue(respuesta(200, { token: 'token-5', usuario: USUARIO }));

      const resultado = await iniciarSesionConGoogle('id-token-de-google');

      const { url, opciones, cuerpo } = llamada();
      expect(url).toBe(`${BASE}/sesion/google`);
      expect(opciones.method).toBe('POST');
      expect(cuerpo).toEqual({ id_token: 'id-token-de-google' });
      expect(resultado).toEqual({ ok: true, datos: { token: 'token-5', usuario: USUARIO } });
    });
  });

  // Si el servidor acepta la conexión y no contesta, en Android la petición no tiene límite: la app esperaría para
  // siempre al abrir con sesión (registro-E11) o al cerrar la sesión (registro-E13). La app la cancela al vencer el plazo.
  describe('registro-E11 y registro-E13: ninguna petición espera para siempre', () => {
    const plazoOriginal = process.env.EXPO_PUBLIC_API_PLAZO_MS;

    beforeEach(() => {
      jest.useFakeTimers();
      delete process.env.EXPO_PUBLIC_API_PLAZO_MS;
    });

    afterEach(() => {
      jest.useRealTimers();
      if (plazoOriginal === undefined) {
        delete process.env.EXPO_PUBLIC_API_PLAZO_MS;
      } else {
        process.env.EXPO_PUBLIC_API_PLAZO_MS = plazoOriginal;
      }
    });

    // El servidor acepta y no contesta: la petición solo termina si la app la cancela con su señal.
    function servidorQueNoContesta() {
      fetchFalso.mockImplementation(
        (_url: string, opciones: RequestInit = {}) =>
          new Promise((_resolver, rechazar) => {
            opciones.signal?.addEventListener('abort', () =>
              rechazar(Object.assign(new Error('Aborted'), { name: 'AbortError' })),
            );
          }),
      );
    }

    // Deja correr la petición y devuelve una función que dice cómo va: undefined mientras espera.
    function seguir<T>(peticion: Promise<T>): () => T | undefined {
      let resultado: T | undefined;
      peticion.then((valor) => (resultado = valor));
      return () => resultado;
    }

    test('registro-E11 — si el servidor no contesta GET /cuenta, a los 15 s la app deja de esperar y lo trata como sin conexión', async () => {
      servidorQueNoContesta();

      const resultado = seguir(consultarCuenta('token-3'));
      await jest.advanceTimersByTimeAsync(14_999);
      expect(resultado()).toBeUndefined();
      await jest.advanceTimersByTimeAsync(1);

      expect(resultado()).toEqual({ ok: false, motivo: 'sin-conexion' });
    });

    test('registro-E13 — el plazo sale de la configuración: con EXPO_PUBLIC_API_PLAZO_MS=5000, DELETE /sesion espera 5 s', async () => {
      process.env.EXPO_PUBLIC_API_PLAZO_MS = '5000';
      servidorQueNoContesta();

      const resultado = seguir(cerrarSesionEnServidor('token-4'));
      await jest.advanceTimersByTimeAsync(4_999);
      expect(resultado()).toBeUndefined();
      await jest.advanceTimersByTimeAsync(1);

      expect(resultado()).toEqual({ ok: false, motivo: 'sin-conexion' });
    });

    test('registro-E11 — si el servidor contesta a tiempo, la respuesta llega y no queda ningún plazo corriendo', async () => {
      fetchFalso.mockResolvedValue(respuesta(200, { usuario: USUARIO }));

      const resultado = await consultarCuenta('token-3');

      expect(resultado).toEqual({ ok: true, datos: { usuario: USUARIO } });
      expect(llamada().opciones.signal).toBeDefined();
      expect(jest.getTimerCount()).toBe(0);
    });

    test('registro-E11 — sin EXPO_PUBLIC_API_PLAZO_MS, el plazo es de 15000 ms', () => {
      expect(plazoDeLaApi()).toBe(15_000);
    });

    test.each(['0', '-5', 'quince', '1.5', '15s'])(
      'registro-E11 — un plazo "%s" no es válido: la configuración no inventa uno y nombra la variable',
      (valor) => {
        process.env.EXPO_PUBLIC_API_PLAZO_MS = valor;

        expect(() => plazoDeLaApi()).toThrow('EXPO_PUBLIC_API_PLAZO_MS');
      },
    );
  });

  describe('registro-E26: la petición del enlace de recuperación va al servidor', () => {
    test('registro-E26 — manda el correo a la ruta de olvido y entrega el mensaje del servidor', async () => {
      fetchFalso.mockResolvedValue(respuesta(200, { message: 'Si hay una cuenta, te llegará un enlace.' }));

      const resultado = await pedirRecuperacion('persona@ejemplo.com');

      const { url, opciones, cuerpo } = llamada();
      expect(url).toBe(`${BASE}/contrasena/olvido`);
      expect(opciones.method).toBe('POST');
      expect(cuerpo).toEqual({ correo: 'persona@ejemplo.com' });
      expect(resultado).toEqual({ ok: true, datos: { message: 'Si hay una cuenta, te llegará un enlace.' } });
    });
  });
});
