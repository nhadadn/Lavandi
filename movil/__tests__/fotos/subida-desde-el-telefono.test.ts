// Las fotos que sube la app, como salen del teléfono (perfil-y-soporte, design.md, "Cómo sale la foto del teléfono"): la
// foto de perfil (perfil-usuario-E03), el registro y la edición de la lavandería (alta-lavanderias-E03 y E19), el
// registro del repartidor (repartidor-E01) y la evidencia de entrega (repartidor-E23).
//
// En el teléfono, fetch es expo/fetch (expo/src/winter/runtime.native.ts) y arma el cuerpo multipart con
// convertFormDataAsync (expo/src/winter/fetch/convertFormData.ts): una parte que no sabe mandar la rechaza, la petición
// no sale y la app dice que no hubo conexión. Las pruebas de cada pantalla simulan fetch; esta toma el formulario que arma
// cada subida, con el FormData del teléfono, y lo pasa por el convertFormDataAsync real, como en el teléfono
// (__tests__/apoyo/formulario.ts). La foto es un archivo de la simulación en memoria que expo-file-system trae para
// Jest. Que el servidor guarde la foto que llega así se prueba en el servidor; la lectura real de la foto en el teléfono,
// en un teléfono real.
import { File, Paths } from 'expo-file-system';

import { editarLavanderia, registrarLavanderia } from '../../src/alta-lavanderias/api';
import { subirFoto } from '../../src/perfil-usuario/api';
import { entregarPedidoDelRepartidor, registrarRepartidor } from '../../src/repartidor/api';
import { contenidoDelArchivo, esFormulario, FormDataDelTelefono, leerFormulario } from '../apoyo/formulario';

const BASE = 'http://api.prueba.invalid/subida-de-fotos';
const TOKEN = 'token-subida-de-fotos';
/** La foto como la deja la compresión en el teléfono: un JPEG en el caché, con un nombre al azar. */
const ARCHIVO = new File(Paths.cache, 'ImageManipulator', '3f9c2a71-5d4e.jpg');
const FOTO = { uri: ARCHIVO.uri };

const direccionOriginal = process.env.EXPO_PUBLIC_API_URL;
const fetchOriginal = global.fetch;
const formDataOriginal = global.FormData;
let fetchFalso: jest.Mock;

beforeEach(async () => {
  ARCHIVO.create({ intermediates: true, overwrite: true });
  await ARCHIVO.write('los bytes del JPEG comprimido');
  process.env.EXPO_PUBLIC_API_URL = BASE;
  // El servidor no importa aquí: solo el cuerpo que la app le manda.
  fetchFalso = jest.fn(async () => ({ ok: false, status: 500, json: async () => ({}) }));
  global.fetch = fetchFalso as unknown as typeof fetch;
  global.FormData = FormDataDelTelefono;
});

afterEach(() => {
  if (direccionOriginal === undefined) {
    delete process.env.EXPO_PUBLIC_API_URL;
  } else {
    process.env.EXPO_PUBLIC_API_URL = direccionOriginal;
  }
  global.fetch = fetchOriginal;
  global.FormData = formDataOriginal;
});

// El cuerpo de la única petición que hizo la app.
function cuerpoMandado(): unknown {
  expect(fetchFalso).toHaveBeenCalledTimes(1);
  const [, opciones] = fetchFalso.mock.calls[0] as [string, RequestInit];
  return opciones.body;
}

interface Subida {
  escenario: string;
  que: string;
  subir: () => Promise<unknown>;
  /** Los campos de texto que la subida manda junto a la foto, en orden. */
  textos: [string, string][];
}

const SUBIDAS: Subida[] = [
  {
    escenario: 'perfil-usuario-E03',
    que: 'la foto de perfil',
    subir: () => subirFoto(TOKEN, FOTO),
    textos: [],
  },
  {
    escenario: 'alta-lavanderias-E03',
    que: 'el registro de la lavandería',
    subir: () =>
      registrarLavanderia(TOKEN, {
        nombre: 'Lavandería Centro',
        direccion: { calle: 'Calle Hidalgo', numero: '210', colonia: 'Centro', codigo_postal: '34000', municipio: '10005' },
        foto: FOTO,
        servicios: [{ tipo: 'estandar', precio: '18.50' }],
        ventanas: [{ dia_semana: 4, hora_inicio: '10:00', hora_fin: '11:00' }],
      }),
    textos: [
      ['nombre', 'Lavandería Centro'],
      ['direccion[calle]', 'Calle Hidalgo'],
      ['direccion[numero]', '210'],
      ['direccion[colonia]', 'Centro'],
      ['direccion[codigo_postal]', '34000'],
      ['direccion[municipio]', '10005'],
      ['servicios[0][tipo]', 'estandar'],
      ['servicios[0][precio]', '18.50'],
      ['ventanas[0][dia_semana]', '4'],
      ['ventanas[0][hora_inicio]', '10:00'],
      ['ventanas[0][hora_fin]', '11:00'],
    ],
  },
  {
    escenario: 'alta-lavanderias-E19',
    que: 'la edición de la lavandería con foto nueva',
    subir: () => editarLavanderia(TOKEN, '01jd0000000000000000000lv1', { nombre: 'Lavandería Norte', foto: FOTO }),
    textos: [
      ['_method', 'PATCH'],
      ['nombre', 'Lavandería Norte'],
    ],
  },
  {
    escenario: 'repartidor-E01',
    que: 'el registro del repartidor',
    subir: () =>
      registrarRepartidor({
        nombre: 'Rosa Prueba',
        telefono: '6180000001',
        correo: 'rosa@ejemplo.com',
        password: 'una-contrasena-larga',
        tipo_vehiculo: 'motocicleta',
        foto: FOTO,
      }),
    textos: [
      ['nombre', 'Rosa Prueba'],
      ['telefono', '6180000001'],
      ['correo', 'rosa@ejemplo.com'],
      ['password', 'una-contrasena-larga'],
      ['tipo_vehiculo', 'motocicleta'],
    ],
  },
  {
    escenario: 'repartidor-E23',
    que: 'la evidencia de entrega',
    subir: () => entregarPedidoDelRepartidor(TOKEN, '01jd0000000000000000000pd1', FOTO),
    textos: [['estado', 'entregado']],
  },
];

describe('las fotos salen del teléfono como las arma expo/fetch', () => {
  test.each(SUBIDAS)(
    '$escenario — $que: convertFormDataAsync arma el cuerpo sin fallar, la foto va como foto.jpg en image/jpeg con los bytes del archivo, y los textos van igual',
    async ({ subir, textos }) => {
      await subir();

      const cuerpo = cuerpoMandado();
      expect(esFormulario(cuerpo)).toBe(true);
      await expect(leerFormulario(cuerpo as FormData)).resolves.toEqual([
        ...textos.map(([campo, texto]) => ({ campo, texto })),
        { campo: 'foto', archivo: { nombre: 'foto.jpg', tipo: 'image/jpeg', contenido: contenidoDelArchivo(FOTO.uri) } },
      ]);
    },
  );
});
