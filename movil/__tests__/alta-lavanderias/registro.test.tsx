// Spec: openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md, alta-lavanderias-E03 (tarea 4.1). El
// registro de la lavandería en la app (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #1: "Nombre, dirección,
// foto, servicios, precios y horario"): la persona llena los datos, toma una foto o la elige de su galería, y envía; la
// app comprime la foto con los parámetros de configuración (ADR-0006) y manda el registro en multipart
// (POST /api/socio/lavanderias); si el servidor rechaza un dato, la app lo muestra junto al campo que corresponde, y la
// dirección con su motivo (alta-lavanderias-E10).
//
// La app abre el registro en lugar de los pedidos cuando la cuenta de socio lavandería todavía no tiene lavandería: lo
// dice GET /api/socio/lavanderias (design.md del cambio, "El contrato de la API"). Cómo llega la persona a tener esa
// cuenta es DD-053 (alta-lavanderias-E04): aquí la cuenta ya existe.
//
// Se abre la app real con la sesión de una cuenta de socio lavandería, en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/lavanderia.ts) y la cámara, la galería y la compresión simuladas (__mocks__/). Las validaciones las
// prueba el servidor (alta-lavanderias-E01, E02, E05, E06, E09 y E10); aquí, cada prueba pone el 422 que necesita.
import { spawnSync } from 'node:child_process';
import path from 'node:path';

import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import permisosDeLaFoto from '../../src/alta-lavanderias/permisos-de-la-foto.json';
import { COMPRESION_DE_FOTOS } from '../../src/configuracion/fotos';
import {
  abrirLaAppConSesion,
  copia,
  ID_DE_LA_REGISTRADA,
  LAVANDERIA_DEL_SOCIO,
  PLATAFORMAS,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  TOKEN,
  usarElServidorDeLaLavanderia,
  type Peticion,
  type Plataforma,
} from '../apoyo/lavanderia';
import { contenidoDelArchivo } from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('alta-registro');

const RUTA_DEL_REGISTRO = '/lavanderia/registro';
const API_DEL_REGISTRO = '/socio/lavanderias';

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

/** Una foto de la cámara de un teléfono, horizontal, más grande que el lado máximo de la configuración. */
const FOTO_DE_LA_CAMARA = { uri: 'file:///camara/foto-1.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };
/** Una foto vertical de la galería, en el formato de iPhone, que el servidor no acepta tal cual. */
const FOTO_DE_LA_GALERIA = { uri: 'file:///galeria/foto-2.heic', width: 3024, height: 4032, mimeType: 'image/heic' };

/** Abre la app con la cuenta de socio lavandería que todavía no registra la suya: llega al registro. */
async function abrirElRegistro(plataforma: Plataforma) {
  servidor().lavanderias = [];
  await abrirLaAppConSesion(plataforma);
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_REGISTRO));
  await screen.findByRole('header', { name: 'Registrar lavandería' });
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

async function marcar(etiqueta: string, valor = true) {
  await fireEvent(screen.getByRole('switch', { name: etiqueta }), 'valueChange', valor);
}

/** Espera la lista del territorio y elige el municipio. */
async function elegirMunicipio(texto: string) {
  await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
  await fireEvent.press(screen.getByRole('button', { name: 'Municipio' }));
  await fireEvent.press(within(screen.getByTestId('hoja-direccion.municipio')).getByRole('radio', { name: texto }));
}

/** Elige el día de la semana cuyas ventanas se marcan. */
async function elegirDia(dia: string) {
  await fireEvent.press(screen.getByRole('button', { name: 'Día' }));
  await fireEvent.press(within(screen.getByTestId('hoja-dia')).getByRole('radio', { name: new RegExp(`^${dia}`) }));
}

/** Todo menos la foto: nombre, dirección, los dos servicios con su precio y dos ventanas de los jueves. */
async function llenarLosDatos() {
  await escribir('Nombre', 'Lavandería Centro');
  await escribir('Calle', 'Calle Hidalgo');
  await escribir('Número', '210');
  await escribir('Colonia', 'Centro');
  await escribir('Código postal', '34000');
  await elegirMunicipio('Durango, Durango');
  await marcar('Lavado estándar');
  await escribir('Precio por kilo de lavado estándar', '18.50');
  await marcar('Lavado con planchado');
  await escribir('Precio por kilo de lavado con planchado', '22');
  await elegirDia('Jueves');
  await marcar('10:00 – 11:00');
  await marcar('11:00 – 12:00');
}

async function tomarLaFoto() {
  camara.__fotoDeLaCamara(FOTO_DE_LA_CAMARA);
  await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));
  await screen.findByLabelText('Foto de la lavandería');
}

async function elegirLaFoto() {
  camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);
  await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
  await screen.findByLabelText('Foto de la lavandería');
}

async function enviar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Enviar registro' }));
}

/** Los registros que la app mandó. */
function registros() {
  return servidor()
    .envios()
    .filter(({ metodo, ruta }) => metodo === 'POST' && ruta === API_DEL_REGISTRO);
}

/** El error que muestra un campo, por su nombre en la API. */
async function errorDe(campo: string) {
  return within(screen.getByTestId(`campo-${campo}`)).findByRole('alert');
}

/** Contesta el registro con ese 422 de Laravel, sin guardar nada. */
function rechazarElRegistro(cuerpo: Record<string, unknown>) {
  servidor().interceptar = ({ metodo, ruta }: Peticion) =>
    metodo === 'POST' && ruta === API_DEL_REGISTRO ? respuesta(422, cuerpo) : undefined;
}

describe('alta-lavanderias-E03: el registro en la app', () => {
  describe('a dónde entra la cuenta de socio lavandería', () => {
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) sin lavandería registrada, la app abre el registro en lugar de los pedidos',
      async (plataforma) => {
        await abrirElRegistro(plataforma);

        expect(screen.queryByRole('header', { name: 'Pedidos activos' })).not.toBeOnTheScreen();
        const lecturas = servidor()
          .peticiones()
          .filter(({ metodo, ruta }) => metodo === 'GET' && ruta === API_DEL_REGISTRO);
        expect(lecturas.map(({ autorizacion }) => autorizacion)).toEqual([`Bearer ${TOKEN}`]);
        // No pide los pedidos de una lavandería que no existe.
        expect(servidor().consultasDeLaLista()).toBe(0);
      },
    );

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) con su lavandería registrada, la app abre los pedidos y el registro no se puede abrir',
      async (plataforma) => {
        await abrirLaAppConSesion(plataforma, RUTA_DEL_REGISTRO);

        await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
        expect(await screen.findByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
        expect(screen.queryByRole('header', { name: 'Registrar lavandería' })).not.toBeOnTheScreen();
      },
    );

    // Sin escenario propio: si no llega la lavandería de la cuenta, la app no adivina a dónde entra.
    test('alta-lavanderias-E03 — si no llega la lavandería de la cuenta, lo dice y deja intentarlo de nuevo', async () => {
      servidor().lavanderias = [];
      let sinConexion = true;
      servidor().interceptar = ({ ruta }) => {
        if (sinConexion && ruta === API_DEL_REGISTRO) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaAppConSesion('android');

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.queryByRole('header', { name: 'Registrar lavandería' })).not.toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Cerrar sesión' })).toBeOnTheScreen();

      sinConexion = false;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_REGISTRO));
      expect(await screen.findByRole('header', { name: 'Registrar lavandería' })).toBeOnTheScreen();
    });
  });

  describe('la persona llena los datos, toma o elige la foto y envía', () => {
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) con la foto de la cámara, la app la comprime con la configuración y manda el registro completo en multipart',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        await llenarLosDatos();

        await tomarLaFoto();
        await enviar();

        await waitFor(() => expect(registros()).toHaveLength(1));
        expect(registros()[0]).toEqual({
          metodo: 'POST',
          ruta: API_DEL_REGISTRO,
          multipart: true,
          cuerpo: {
            nombre: 'Lavandería Centro',
            direccion: {
              calle: 'Calle Hidalgo',
              numero: '210',
              colonia: 'Centro',
              codigo_postal: '34000',
              municipio: '10005',
            },
            foto: {
              nombre: 'foto.jpg',
              tipo: 'image/jpeg',
              contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_CAMARA.uri)),
            },
            servicios: [
              { tipo: 'estandar', precio: '18.50' },
              { tipo: 'planchado', precio: '22' },
            ],
            ventanas: [
              { dia_semana: '4', hora_inicio: '10:00', hora_fin: '11:00' },
              { dia_semana: '4', hora_inicio: '11:00', hora_fin: '12:00' },
            ],
          },
        });
        // La foto se comprimió una vez, con los parámetros de configuración: el lado mayor al máximo y JPEG.
        expect(manipulador.__preparaciones()).toEqual([
          {
            fuente: FOTO_DE_LA_CAMARA.uri,
            redimension: { width: COMPRESION_DE_FOTOS.ladoMaximoPx },
            guardado: { compress: COMPRESION_DE_FOTOS.calidad, format: 'jpeg' },
            resultado: manipulador.__preparada(FOTO_DE_LA_CAMARA.uri),
          },
        ]);
        expect(camara.launchCameraAsync).toHaveBeenCalledWith(expect.objectContaining({ mediaTypes: ['images'] }));
      },
    );

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) con el registro guardado, la app abre los pedidos de la lavandería',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        await llenarLosDatos();
        await tomarLaFoto();

        await enviar();

        await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
        expect(await screen.findByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
        expect(servidor().lavanderias.map(({ id_publico }) => id_publico)).toEqual([ID_DE_LA_REGISTRADA]);
      },
    );

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) con la foto de la galería, la app la muestra y la manda comprimida en JPEG',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        await llenarLosDatos();

        await elegirLaFoto();
        expect(screen.getByLabelText('Foto de la lavandería')).toHaveProp('source', {
          uri: manipulador.__preparada(FOTO_DE_LA_GALERIA.uri),
        });
        await enviar();

        await waitFor(() => expect(registros()).toHaveLength(1));
        expect((registros()[0].cuerpo as Record<string, unknown>).foto).toEqual({
          nombre: 'foto.jpg',
          tipo: 'image/jpeg',
          contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_GALERIA.uri)),
        });
        // Vertical: el lado mayor es el alto.
        expect(manipulador.__preparaciones()[0].redimension).toEqual({ height: COMPRESION_DE_FOTOS.ladoMaximoPx });
        // La galería del sistema no pide permiso; la cámara no se abrió.
        expect(camara.requestCameraPermissionsAsync).not.toHaveBeenCalled();
        expect(camara.launchCameraAsync).not.toHaveBeenCalled();
      },
    );

    test('alta-lavanderias-E03 — una foto más chica que el máximo no se agranda: solo se comprime', async () => {
      await abrirElRegistro('ios');
      camara.__fotoDeLaGaleria({ uri: 'file:///galeria/chica.png', width: 800, height: 600, mimeType: 'image/png' });

      await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
      await screen.findByLabelText('Foto de la lavandería');

      expect(manipulador.__preparaciones()).toEqual([
        {
          fuente: 'file:///galeria/chica.png',
          redimension: null,
          guardado: { compress: COMPRESION_DE_FOTOS.calidad, format: 'jpeg' },
          resultado: manipulador.__preparada('file:///galeria/chica.png'),
        },
      ]);
    });

    test('alta-lavanderias-E03 — antes de abrir la cámara, la app pide su permiso', async () => {
      await abrirElRegistro('android');

      await tomarLaFoto();

      expect(camara.requestCameraPermissionsAsync).toHaveBeenCalledTimes(1);
      expect(camara.launchCameraAsync).toHaveBeenCalledTimes(1);
    });

    // Sin escenario propio: sin permiso de la cámara, lo dice y la galería sigue sirviendo, porque no pide permiso.
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) sin permiso de la cámara, lo dice junto a la foto y deja elegirla de la galería',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        camara.__respuestaAlPedirLaCamara(camara.PermissionStatus.DENIED);

        await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));

        expect(await errorDe('foto')).toHaveTextContent(
          'Sin permiso de la cámara no podemos tomar la foto. Puedes elegirla de tu galería.',
        );
        expect(camara.launchCameraAsync).not.toHaveBeenCalled();

        await elegirLaFoto();
        expect(within(screen.getByTestId('campo-foto')).queryByRole('alert')).not.toBeOnTheScreen();
      },
    );

    test('alta-lavanderias-E03 — si la persona cierra la cámara sin tomar la foto, no cambia nada', async () => {
      await abrirElRegistro('ios');

      await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));

      await waitFor(() => expect(camara.launchCameraAsync).toHaveBeenCalledTimes(1));
      expect(screen.queryByLabelText('Foto de la lavandería')).not.toBeOnTheScreen();
      expect(manipulador.__preparaciones()).toEqual([]);
      expect(within(screen.getByTestId('campo-foto')).queryByRole('alert')).not.toBeOnTheScreen();
    });

    // Sin escenario propio: una foto que no se puede preparar, como una imagen dañada.
    test('alta-lavanderias-E03 — si la foto no se puede preparar, lo dice junto a la foto', async () => {
      await abrirElRegistro('android');
      manipulador.__fallar(new Error('No se pudo leer la imagen.'));

      camara.__fotoDeLaGaleria(FOTO_DE_LA_GALERIA);
      await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));

      expect(await errorDe('foto')).toHaveTextContent('No pudimos preparar la foto. Intenta con otra.');
      expect(screen.queryByLabelText('Foto de la lavandería')).not.toBeOnTheScreen();
    });

    test('alta-lavanderias-E03 — los servicios son los dos que se registran hoy, cada uno con su precio por kilo; el lavado en seco espera DD-010', async () => {
      await abrirElRegistro('ios');

      expect(screen.getByRole('switch', { name: 'Lavado estándar' })).toBeOnTheScreen();
      expect(screen.getByRole('switch', { name: 'Lavado con planchado' })).toBeOnTheScreen();
      expect(screen.queryByRole('switch', { name: 'Lavado en seco' })).not.toBeOnTheScreen();
      // El precio se pide al ofrecer el servicio.
      expect(screen.queryByLabelText('Precio por kilo de lavado estándar')).not.toBeOnTheScreen();
      await marcar('Lavado estándar');
      expect(screen.getByLabelText('Precio por kilo de lavado estándar')).toBeOnTheScreen();
    });

    test('alta-lavanderias-E03 — las ventanas son de una hora, las 24 de cada día de la semana, y cada día guarda las suyas', async () => {
      await abrirElRegistro('android');

      expect(screen.getByRole('button', { name: 'Día' })).toHaveAccessibilityValue({ text: 'Lunes' });
      expect(screen.getAllByRole('switch', { name: /^\d\d:00 – \d\d:00$/ })).toHaveLength(24);
      expect(screen.getByRole('switch', { name: '00:00 – 01:00' })).toBeOnTheScreen();
      expect(screen.getByRole('switch', { name: '23:00 – 24:00' })).toBeOnTheScreen();

      await marcar('09:00 – 10:00');
      await elegirDia('Jueves');
      expect(screen.getByRole('switch', { name: '09:00 – 10:00' })).not.toBeChecked();
      await elegirDia('Lunes');
      expect(screen.getByRole('switch', { name: '09:00 – 10:00' })).toBeChecked();
    });

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) mientras manda, el botón se bloquea: un doble toque manda un solo registro',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        await llenarLosDatos();
        await tomarLaFoto();
        let soltar: () => void = () => {};
        servidor().interceptar = ({ metodo, ruta }) => {
          if (metodo !== 'POST' || ruta !== API_DEL_REGISTRO) {
            return undefined;
          }
          return new Promise((resolver) => {
            soltar = () => {
              const registrada = { ...copia(LAVANDERIA_DEL_SOCIO), id_publico: ID_DE_LA_REGISTRADA };
              servidor().lavanderias = [registrada];
              resolver(respuesta(201, { lavanderia: registrada }));
            };
          });
        };

        await enviar();
        await enviar();

        expect(registros()).toHaveLength(1);
        expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Enviar registro' })).toBeDisabled();
        soltar();
        await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
        expect(registros()).toHaveLength(1);
      },
    );
  });

  describe('si el servidor rechaza un dato, la app lo muestra junto al campo', () => {
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) sin nombre, sin calle, sin foto, sin servicios y sin horario: cada error junto a su campo',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        rechazarElRegistro({
          message: 'Falta el nombre de la lavandería. (y 4 errores más)',
          errors: {
            nombre: ['Falta el nombre de la lavandería.'],
            'direccion.calle': ['Falta la calle.'],
            foto: ['Falta la foto de la lavandería.'],
            servicios: ['Falta al menos un servicio con su precio.'],
            ventanas: ['Falta el horario: al menos una ventana de una hora.'],
          },
        });

        await enviar();

        expect(await errorDe('nombre')).toHaveTextContent('Falta el nombre de la lavandería.');
        expect(await errorDe('direccion.calle')).toHaveTextContent('Falta la calle.');
        expect(await errorDe('foto')).toHaveTextContent('Falta la foto de la lavandería.');
        expect(await errorDe('servicios')).toHaveTextContent('Falta al menos un servicio con su precio.');
        expect(await errorDe('ventanas')).toHaveTextContent('Falta el horario: al menos una ventana de una hora.');
        // Cada error está en su campo: el mensaje general del servidor no se repite arriba del botón.
        expect(screen.queryByText('Falta el nombre de la lavandería. (y 4 errores más)')).not.toBeOnTheScreen();
        expect(screen).toHavePathname(RUTA_DEL_REGISTRO);
      },
    );

    test('alta-lavanderias-E03 — el número, la colonia, el código postal y el municipio, cada uno con su error', async () => {
      await abrirElRegistro('ios');
      rechazarElRegistro({
        message: 'Falta el número.',
        errors: {
          'direccion.numero': ['Falta el número.'],
          'direccion.colonia': ['Falta la colonia.'],
          'direccion.codigo_postal': ['El código postal son cinco números.'],
          'direccion.municipio': ['Elige un municipio de la lista.'],
        },
      });

      await enviar();

      expect(await errorDe('direccion.numero')).toHaveTextContent('Falta el número.');
      expect(await errorDe('direccion.colonia')).toHaveTextContent('Falta la colonia.');
      expect(await errorDe('direccion.codigo_postal')).toHaveTextContent('El código postal son cinco números.');
      expect(await errorDe('direccion.municipio')).toHaveTextContent('Elige un municipio de la lista.');
    });

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) el precio rechazado va junto al precio de su servicio',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        await llenarLosDatos();
        await tomarLaFoto();
        rechazarElRegistro({
          message: 'El precio va en pesos, sin signo y con hasta dos decimales.',
          errors: { 'servicios.1.precio': ['El precio va en pesos, sin signo y con hasta dos decimales.'] },
        });

        await enviar();

        // El segundo servicio que se mandó es el lavado con planchado.
        expect(await errorDe('precio-planchado')).toHaveTextContent(
          'El precio va en pesos, sin signo y con hasta dos decimales.',
        );
        expect(within(screen.getByTestId('campo-precio-estandar')).queryByRole('alert')).not.toBeOnTheScreen();
      },
    );

    test('alta-lavanderias-E03 — la ventana rechazada se nombra en el horario, con su día y sus horas', async () => {
      await abrirElRegistro('android');
      await llenarLosDatos();
      await tomarLaFoto();
      rechazarElRegistro({
        message: 'Cada ventana dura exactamente una hora.',
        errors: { 'ventanas.1': ['Cada ventana dura exactamente una hora.'] },
      });

      await enviar();

      expect(await errorDe('ventanas')).toHaveTextContent('Jueves 11:00 – 12:00: Cada ventana dura exactamente una hora.');
    });

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E03 — (%s) la dirección fuera de las zonas de servicio se dice en la dirección, con su motivo',
      async (plataforma) => {
        await abrirElRegistro(plataforma);
        await llenarLosDatos();
        await tomarLaFoto();
        rechazarElRegistro({
          message: 'Texto del servidor.',
          errors: { direccion: ['Texto del servidor.'] },
          motivo: 'fuera_de_la_zona_de_servicio',
        });

        await enviar();

        expect(await errorDe('direccion')).toHaveTextContent('La dirección está fuera de las zonas de servicio.');
        expect(screen).toHavePathname(RUTA_DEL_REGISTRO);
      },
    );

    test('alta-lavanderias-E03 — la dirección que no se pudo ubicar se dice en la dirección, con su motivo', async () => {
      await abrirElRegistro('ios');
      rechazarElRegistro({
        message: 'Texto del servidor.',
        errors: { direccion: ['Texto del servidor.'] },
        motivo: 'direccion_no_encontrada',
      });

      await enviar();

      expect(await errorDe('direccion')).toHaveTextContent(
        'No encontramos la dirección. Revisa la calle, el número, la colonia y el código postal.',
      );
    });

    test('alta-lavanderias-E03 — si la geocodificación no responde (503), lo dice en la dirección', async () => {
      await abrirElRegistro('android');
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'POST' && ruta === API_DEL_REGISTRO
          ? respuesta(503, {
              message: 'No pudimos ubicar la dirección. Inténtalo más tarde.',
              motivo: 'geocodificacion_no_disponible',
            })
          : undefined;

      await enviar();

      expect(await errorDe('direccion')).toHaveTextContent('No pudimos ubicar la dirección. Inténtalo más tarde.');
    });

    test('alta-lavanderias-E03 — con un motivo que la app no conoce, la dirección lleva el mensaje del servidor', async () => {
      await abrirElRegistro('ios');
      rechazarElRegistro({
        message: 'Otro motivo del servidor.',
        errors: { direccion: ['Otro motivo del servidor.'] },
        motivo: 'otro_motivo',
      });

      await enviar();

      expect(await errorDe('direccion')).toHaveTextContent('Otro motivo del servidor.');
    });

    test('alta-lavanderias-E03 — un error de un campo que la pantalla no tiene va arriba del botón', async () => {
      await abrirElRegistro('android');
      rechazarElRegistro({ message: 'Algo no cuadra.', errors: { otra_cosa: ['Algo no cuadra.'] } });

      await enviar();

      expect(await screen.findByText('Algo no cuadra.')).toBeOnTheScreen();
    });

    // Sin escenario propio: sin conexión, los mensajes generales de la app, y lo escrito se queda.
    test('alta-lavanderias-E03 — sin conexión lo dice con el mensaje general y conserva lo escrito', async () => {
      await abrirElRegistro('ios');
      await escribir('Nombre', 'Lavandería Centro');
      servidor().interceptar = ({ metodo }) => {
        if (metodo === 'POST') {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };

      await enviar();

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.getByLabelText('Nombre')).toHaveDisplayValue('Lavandería Centro');
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Enviar registro' })).toBeEnabled();
    });

    // Sin escenario propio: la cuenta ya registró su lavandería, por ejemplo desde otro teléfono.
    test('alta-lavanderias-E03 — si la cuenta ya tiene lavandería (409), la app la vuelve a leer y abre sus pedidos', async () => {
      await abrirElRegistro('android');
      servidor().lavanderias = [copia(LAVANDERIA_DEL_SOCIO)];

      await enviar();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(await screen.findByRole('header', { name: 'Pedidos activos' })).toBeOnTheScreen();
      expect(registros()).toHaveLength(1);
    });
  });

  describe('el municipio, de la lista del territorio', () => {
    test('alta-lavanderias-E03 — la lista se pide con la sesión y se ofrece en el campo, sin texto libre', async () => {
      await abrirElRegistro('ios');

      await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
      const pedidas = servidor()
        .peticiones()
        .filter(({ metodo, ruta }) => metodo === 'GET' && ruta === '/catalogos/municipios');
      expect(pedidas.map(({ autorizacion }) => autorizacion)).toEqual([`Bearer ${TOKEN}`]);
      expect(screen.getAllByLabelText('Municipio')).toEqual([screen.getByRole('button', { name: 'Municipio' })]);
    });

    // Sin escenario propio: como en la dirección del pedido (direcciones-E17), sin lista lo dice y deja intentarlo.
    test('alta-lavanderias-E03 — si la lista no llega, lo dice junto al campo y deja intentarlo de nuevo', async () => {
      let sinLista = true;
      servidor().interceptar = ({ ruta }) =>
        sinLista && ruta === '/catalogos/municipios' ? respuesta(403, { message: 'Esta cuenta no tiene acceso.' }) : undefined;
      await abrirElRegistro('android');

      expect(await errorDe('direccion.municipio')).toHaveTextContent(
        'No pudimos cargar la lista de municipios. Intenta de nuevo.',
      );
      sinLista = false;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
    });
  });

  // La cámara y la galería de cada sistema piden su permiso con el texto de la app (ADR-0006).
  describe('los permisos de la foto en la configuración', () => {
    const RAIZ = path.resolve(__dirname, '..', '..');
    const CLI_DE_EXPO = path.join(RAIZ, 'node_modules', 'expo', 'bin', 'cli');

    interface ElementoDelManifiesto {
      $: Record<string, string>;
    }
    interface ConfiguracionLeida {
      _internal?: {
        modResults?: {
          ios?: { infoPlist?: Record<string, unknown> };
          android?: { manifest?: { manifest?: { 'uses-permission'?: ElementoDelManifiesto[] } } };
        };
      };
    }

    let infoPlist: Record<string, unknown>;
    let permisosDeAndroid: ElementoDelManifiesto[];

    // La configuración como la arma Expo al compilar, con los complementos aplicados (modo introspect), sin leer ningún
    // .env de la máquina.
    beforeAll(() => {
      const env: NodeJS.ProcessEnv = {
        ...process.env,
        EXPO_NO_DOTENV: '1',
        EXPO_NO_TELEMETRY: '1',
        EXPO_PUBLIC_NOMBRE_APP: 'Nombre de prueba alta-lavanderias-E03',
        EXPO_PUBLIC_API_URL: 'http://api.prueba.invalid/alta-lavanderias-e03',
        APP_IDENTIFIER: 'mx.prueba.altalavanderiase03',
      };
      const leida = spawnSync(process.execPath, [CLI_DE_EXPO, 'config', '--type', 'introspect', '--json'], {
        cwd: RAIZ,
        env,
        encoding: 'utf8',
      });
      if (leida.status !== 0) {
        throw new Error(`Expo no pudo leer la configuración: ${leida.stderr}`);
      }
      const configuracion = JSON.parse(leida.stdout) as ConfiguracionLeida;
      infoPlist = configuracion._internal?.modResults?.ios?.infoPlist ?? {};
      permisosDeAndroid = configuracion._internal?.modResults?.android?.manifest?.manifest?.['uses-permission'] ?? [];
    }, 180_000);

    test('alta-lavanderias-E03 — en iPhone, la cámara y las fotos piden permiso con el texto de la app, que dice para qué', () => {
      expect(permisosDeLaFoto.camara).toMatch(/foto de tu lavandería/);
      expect(permisosDeLaFoto.fotos).toMatch(/foto de tu lavandería/);
      expect(infoPlist.NSCameraUsageDescription).toBe(permisosDeLaFoto.camara);
      expect(infoPlist.NSPhotoLibraryUsageDescription).toBe(permisosDeLaFoto.fotos);
    });

    test('alta-lavanderias-E03 — la app no graba video: no declara el micrófono en ninguna de las dos plataformas', () => {
      expect(infoPlist).not.toHaveProperty('NSMicrophoneUsageDescription');
      const declarados = permisosDeAndroid
        .filter((permiso) => permiso.$['tools:node'] !== 'remove')
        .map((permiso) => permiso.$['android:name']);
      expect(declarados).not.toContain('android.permission.RECORD_AUDIO');
      const quitados = permisosDeAndroid
        .filter((permiso) => permiso.$['tools:node'] === 'remove')
        .map((permiso) => permiso.$['android:name']);
      expect(quitados).toContain('android.permission.RECORD_AUDIO');
    });
  });
});
