// Spec: openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md, alta-lavanderias-E19 (tarea 4.2). El
// perfil y horarios del Módulo B (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B #6: "Edición de información de
// la lavandería y ventanas de disponibilidad"): la lavandería registrada abre su pantalla desde sus pedidos, ve su
// información y sus ventanas de disponibilidad actuales (GET /api/socio/lavanderias/{id_publico}), y puede editarlas y
// guardarlas (PATCH, solo con lo que cambió), con cada error del servidor junto a su campo. Con una foto nueva, la
// edición va en multipart por POST con _method=PATCH (design.md del cambio, "El contrato de la API"). La descripción y
// la lista de fotos del perfil tienen sus propias pruebas (descripcion-y-fotos.test.tsx, alta-lavanderias-E26 a E30).
//
// Se abre la app real con la sesión de una cuenta de socio lavandería con su lavandería, en iPhone y en Android, con el
// servidor simulado (__tests__/apoyo/lavanderia.ts). Que una lavandería no edite la de otra (alta-lavanderias-E20) y
// que una ventana dure una hora (E22) los prueba el servidor; aquí, cada prueba pone el 422 que necesita.
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import {
  abrirLaAppConSesion,
  LAVANDERIA_DEL_SOCIO,
  PLATAFORMAS,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  TOKEN,
  usarElServidorDeLaLavanderia,
  type Plataforma,
} from '../apoyo/lavanderia';
import { contenidoDelArchivo } from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaLavanderia('alta-perfil');

const RUTA_DEL_PERFIL = '/lavanderia/perfil';
const API_DE_LA_LAVANDERIA = `/socio/lavanderias/${LAVANDERIA_DEL_SOCIO.id_publico}`;

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

const FOTO_NUEVA = { uri: 'file:///galeria/fachada-nueva.jpg', width: 4032, height: 3024, mimeType: 'image/jpeg' };

/** La lavandería de la cuenta en el servidor, para cambiarla antes de abrir la pantalla. */
function lavanderia() {
  return servidor().lavanderias[0];
}

/** Abre los pedidos de la lavandería y, desde ahí, su perfil y horarios, ya con su información. */
async function abrirElPerfil(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos activos' });
  await fireEvent.press(screen.getByRole('link', { name: 'Perfil y horarios' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_PERFIL));
  await screen.findByRole('header', { name: 'Perfil y horarios' });
  await screen.findByLabelText('Nombre');
}

async function escribir(etiqueta: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText(etiqueta), texto);
}

async function marcar(etiqueta: string, valor = true) {
  await fireEvent(screen.getByRole('switch', { name: etiqueta }), 'valueChange', valor);
}

async function elegirDia(dia: string) {
  await fireEvent.press(screen.getByRole('button', { name: 'Día' }));
  await fireEvent.press(within(screen.getByTestId('hoja-dia')).getByRole('radio', { name: new RegExp(`^${dia}`) }));
}

async function guardar() {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' }));
}

/** Lo que la app mandó para editar la lavandería. */
function ediciones() {
  return servidor()
    .envios()
    .filter(({ ruta }) => ruta === API_DE_LA_LAVANDERIA);
}

async function errorDe(campo: string) {
  return within(screen.getByTestId(`campo-${campo}`)).findByRole('alert');
}

/** Contesta la edición con ese 422 de Laravel, sin cambiar nada. */
function rechazarLaEdicion(cuerpo: Record<string, unknown>) {
  servidor().interceptar = ({ metodo, ruta }) =>
    metodo !== 'GET' && ruta === API_DE_LA_LAVANDERIA ? respuesta(422, cuerpo) : undefined;
}

describe('alta-lavanderias-E19: la pantalla de perfil y horarios', () => {
  describe('la lavandería ve su información y sus ventanas actuales', () => {
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) desde sus pedidos abre su perfil y ve su nombre, su dirección, su foto y sus servicios con su precio',
      async (plataforma) => {
        await abrirElPerfil(plataforma);

        expect(screen.getByLabelText('Nombre')).toHaveDisplayValue('Lavandería de prueba del socio');
        expect(screen.getByLabelText('Calle')).toHaveDisplayValue('Calle de prueba');
        expect(screen.getByLabelText('Número')).toHaveDisplayValue('123');
        expect(screen.getByLabelText('Colonia')).toHaveDisplayValue('Centro');
        expect(screen.getByLabelText('Código postal')).toHaveDisplayValue('34000');
        expect(screen.getByRole('button', { name: 'Municipio' })).toHaveAccessibilityValue({ text: 'Durango, Durango' });
        // La foto del registro es la primera de la lista de fotos del perfil (alta-lavanderias-E27 a E29).
        expect(screen.getByLabelText('Foto 1 de la lavandería')).toHaveProp('source', {
          uri: LAVANDERIA_DEL_SOCIO.foto?.url,
        });
        expect(screen.getByRole('switch', { name: 'Lavado estándar' })).toBeChecked();
        expect(screen.getByLabelText('Precio por kilo de lavado estándar')).toHaveDisplayValue('18.50');
        expect(screen.getByRole('switch', { name: 'Lavado con planchado' })).not.toBeChecked();
        expect(screen.queryByLabelText('Precio por kilo de lavado con planchado')).not.toBeOnTheScreen();
      },
    );

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) ve sus ventanas de disponibilidad: abre en el primer día con ventanas, los jueves de 10:00 a 11:00',
      async (plataforma) => {
        await abrirElPerfil(plataforma);

        expect(screen.getByRole('button', { name: 'Día' })).toHaveAccessibilityValue({ text: 'Jueves' });
        expect(screen.getByRole('switch', { name: '10:00 – 11:00' })).toBeChecked();
        expect(screen.getByRole('switch', { name: '11:00 – 12:00' })).not.toBeChecked();
        await elegirDia('Lunes');
        expect(screen.getByRole('switch', { name: '10:00 – 11:00' })).not.toBeChecked();
      },
    );

    test('alta-lavanderias-E19 — la información es la actual: la pide al servidor con la sesión al abrir la pantalla', async () => {
      // Cambió después de entrar al módulo, por ejemplo desde el panel (alta-lavanderias-E13).
      await abrirLaAppConSesion('ios', RUTA_DE_LOS_PEDIDOS);
      await screen.findByRole('header', { name: 'Pedidos activos' });
      lavanderia().nombre = 'Nombre cambiado en el panel';

      await fireEvent.press(screen.getByRole('link', { name: 'Perfil y horarios' }));

      expect(await screen.findByDisplayValue('Nombre cambiado en el panel')).toBeOnTheScreen();
      const lecturas = servidor()
        .peticiones()
        .filter(({ metodo, ruta }) => metodo === 'GET' && ruta === API_DE_LA_LAVANDERIA);
      expect(lecturas.map(({ autorizacion }) => autorizacion)).toEqual([`Bearer ${TOKEN}`]);
    });

    test('alta-lavanderias-E19 — una franja larga guardada, de 09:00 a 14:00, se ve como sus cinco ventanas de una hora', async () => {
      lavanderia().ventanas = [{ dia_semana: 1, hora_inicio: '09:00', hora_fin: '14:00' }];

      await abrirElPerfil('android');

      expect(screen.getByRole('button', { name: 'Día' })).toHaveAccessibilityValue({ text: 'Lunes' });
      for (const ventana of ['09:00 – 10:00', '10:00 – 11:00', '11:00 – 12:00', '12:00 – 13:00', '13:00 – 14:00']) {
        expect(screen.getByRole('switch', { name: ventana })).toBeChecked();
      }
      expect(screen.getByRole('switch', { name: '08:00 – 09:00' })).not.toBeChecked();
      expect(screen.getByRole('switch', { name: '14:00 – 15:00' })).not.toBeChecked();
    });

    test('alta-lavanderias-E19 — una lavandería de prueba sin municipio lo pide en el campo, sin elegir uno', async () => {
      lavanderia().direccion.municipio = null;

      await abrirElPerfil('ios');

      await waitFor(() => expect(screen.getByRole('button', { name: 'Municipio' })).toBeEnabled());
      expect(screen.getByRole('button', { name: 'Municipio' })).toHaveAccessibilityValue({ text: 'Elige el municipio' });
    });

    // Sin escenario propio: si no llega, los mensajes generales de la app, sin formulario.
    test('alta-lavanderias-E19 — si su lavandería no llega, lo dice con el mensaje general y no deja guardar', async () => {
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo === 'GET' && ruta === API_DE_LA_LAVANDERIA) {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaAppConSesion('android', RUTA_DE_LOS_PEDIDOS);
      await screen.findByRole('header', { name: 'Pedidos activos' });

      await fireEvent.press(screen.getByRole('link', { name: 'Perfil y horarios' }));

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.queryByLabelText('Nombre')).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Guardar cambios' })).not.toBeOnTheScreen();
    });

    test('alta-lavanderias-E19 — "Regresar" vuelve a los pedidos', async () => {
      await abrirElPerfil('ios');

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    });
  });

  describe('la lavandería edita y guarda', () => {
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) cambia su nombre y agrega una ventana de los jueves: la app manda solo eso, con el horario completo',
      async (plataforma) => {
        await abrirElPerfil(plataforma);

        await escribir('Nombre', 'Lavandería renovada');
        await marcar('11:00 – 12:00');
        await guardar();

        await waitFor(() => expect(ediciones()).toHaveLength(1));
        expect(ediciones()[0]).toEqual({
          metodo: 'PATCH',
          ruta: API_DE_LA_LAVANDERIA,
          multipart: false,
          cuerpo: {
            nombre: 'Lavandería renovada',
            ventanas: [
              { dia_semana: 4, hora_inicio: '10:00', hora_fin: '11:00' },
              { dia_semana: 4, hora_inicio: '11:00', hora_fin: '12:00' },
            ],
          },
        });
        expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
        expect(lavanderia().nombre).toBe('Lavandería renovada');
      },
    );

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) con una foto nueva, la edición va en multipart por POST con _method=PATCH, con la foto comprimida',
      async (plataforma) => {
        await abrirElPerfil(plataforma);
        camara.__fotoDeLaGaleria(FOTO_NUEVA);

        // La foto nueva va en la lista de fotos del perfil, al final (alta-lavanderias-E27).
        await fireEvent.press(screen.getByRole('button', { name: 'Elegir de la galería' }));
        await waitFor(() =>
          expect(screen.getByLabelText('Foto 2 de la lavandería')).toHaveProp('source', {
            uri: manipulador.__preparada(FOTO_NUEVA.uri),
          }),
        );
        await guardar();

        await waitFor(() => expect(ediciones()).toHaveLength(1));
        expect(ediciones()[0]).toEqual({
          metodo: 'POST',
          ruta: API_DE_LA_LAVANDERIA,
          multipart: true,
          cuerpo: {
            _method: 'PATCH',
            fotos: [
              '0',
              {
                nombre: 'foto.jpg',
                tipo: 'image/jpeg',
                contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_NUEVA.uri)),
              },
            ],
          },
        });
        // Al guardar, la foto es la que devolvió el servidor, con su URL firmada.
        expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
        expect(screen.getByLabelText('Foto 2 de la lavandería')).toHaveProp('source', { uri: lavanderia().fotos[1].url });
      },
    );

    test('alta-lavanderias-E19 — cambia el precio y agrega el lavado con planchado: van los servicios completos', async () => {
      await abrirElPerfil('android');

      await escribir('Precio por kilo de lavado estándar', '20');
      await marcar('Lavado con planchado');
      await escribir('Precio por kilo de lavado con planchado', '25.50');
      await guardar();

      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0].cuerpo).toEqual({
        servicios: [
          { tipo: 'estandar', precio: '20' },
          { tipo: 'planchado', precio: '25.50' },
        ],
      });
    });

    test('alta-lavanderias-E19 — cambia la calle: la dirección va completa, con su municipio', async () => {
      await abrirElPerfil('ios');

      await escribir('Calle', 'Calle Nueva');
      await guardar();

      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0].cuerpo).toEqual({
        direccion: { calle: 'Calle Nueva', numero: '123', colonia: 'Centro', codigo_postal: '34000', municipio: '10005' },
      });
    });

    test('alta-lavanderias-E19 — la franja larga sin tocar el horario no se manda: solo lo que cambió', async () => {
      lavanderia().ventanas = [{ dia_semana: 1, hora_inicio: '09:00', hora_fin: '14:00' }];
      await abrirElPerfil('ios');

      await escribir('Nombre', 'Otro nombre');
      await guardar();

      await waitFor(() => expect(ediciones()).toHaveLength(1));
      expect(ediciones()[0].cuerpo).toEqual({ nombre: 'Otro nombre' });
    });

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) mientras guarda, el botón se bloquea: un doble toque manda una sola edición',
      async (plataforma) => {
        await abrirElPerfil(plataforma);
        let soltar: () => void = () => {};
        servidor().interceptar = ({ metodo, ruta }) => {
          if (metodo !== 'PATCH' || ruta !== API_DE_LA_LAVANDERIA) {
            return undefined;
          }
          return new Promise((resolver) => {
            soltar = () => resolver(respuesta(200, { lavanderia: { ...lavanderia(), nombre: 'Lavandería renovada' } }));
          });
        };
        await escribir('Nombre', 'Lavandería renovada');

        await guardar();
        await guardar();

        expect(ediciones()).toHaveLength(1);
        expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeDisabled();
        soltar();
        expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
        expect(ediciones()).toHaveLength(1);
      },
    );
  });

  describe('cada error del servidor junto a su campo', () => {
    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) el nombre vacío, el precio y la ventana rechazados: cada uno junto a su campo, y nada se guarda',
      async (plataforma) => {
        await abrirElPerfil(plataforma);
        rechazarLaEdicion({
          message: 'Falta el nombre de la lavandería. (y 2 errores más)',
          errors: {
            nombre: ['Falta el nombre de la lavandería.'],
            'servicios.0.precio': ['El precio va en pesos, sin signo y con hasta dos decimales.'],
            'ventanas.1': ['Cada ventana dura exactamente una hora.'],
          },
        });

        await escribir('Nombre', '');
        await escribir('Precio por kilo de lavado estándar', '$20');
        await marcar('11:00 – 12:00');
        await guardar();

        expect(await errorDe('nombre')).toHaveTextContent('Falta el nombre de la lavandería.');
        expect(await errorDe('precio-estandar')).toHaveTextContent(
          'El precio va en pesos, sin signo y con hasta dos decimales.',
        );
        expect(await errorDe('ventanas')).toHaveTextContent('Jueves 11:00 – 12:00: Cada ventana dura exactamente una hora.');
        expect(screen.queryByText('Guardamos los cambios.')).not.toBeOnTheScreen();
        expect(lavanderia().nombre).toBe('Lavandería de prueba del socio');
        // Lo escrito se queda para corregirlo.
        expect(screen.getByLabelText('Precio por kilo de lavado estándar')).toHaveDisplayValue('$20');
      },
    );

    test.each(PLATAFORMAS)(
      'alta-lavanderias-E19 — (%s) la dirección en una zona de otra plaza se dice en la dirección, con su motivo',
      async (plataforma) => {
        await abrirElPerfil(plataforma);
        rechazarLaEdicion({
          message: 'Texto del servidor.',
          errors: { direccion: ['Texto del servidor.'] },
          motivo: 'en_otra_plaza',
        });

        await escribir('Calle', 'Calle en Torreón');
        await guardar();

        expect(await errorDe('direccion')).toHaveTextContent(
          'La dirección nueva queda en otra plaza de servicio. Ese cambio todavía no se puede hacer.',
        );
      },
    );

    test('alta-lavanderias-E19 — sin municipio, al cambiar la dirección el error del municipio va junto a él', async () => {
      lavanderia().direccion.municipio = null;
      await abrirElPerfil('android');
      rechazarLaEdicion({ message: 'Falta el municipio.', errors: { 'direccion.municipio': ['Falta el municipio.'] } });

      await escribir('Calle', 'Calle Nueva');
      await guardar();

      expect(await errorDe('direccion.municipio')).toHaveTextContent('Falta el municipio.');
      expect(ediciones()[0].cuerpo).toEqual({
        direccion: { calle: 'Calle Nueva', numero: '123', colonia: 'Centro', codigo_postal: '34000', municipio: '' },
      });
    });

    test('alta-lavanderias-E19 — al guardar de nuevo, los errores anteriores se van', async () => {
      await abrirElPerfil('ios');
      rechazarLaEdicion({ message: 'Falta el nombre de la lavandería.', errors: { nombre: ['Falta el nombre de la lavandería.'] } });
      await escribir('Nombre', '');
      await guardar();
      await errorDe('nombre');

      servidor().interceptar = null;
      await escribir('Nombre', 'Nombre corregido');
      await guardar();

      expect(await screen.findByText('Guardamos los cambios.')).toBeOnTheScreen();
      expect(within(screen.getByTestId('campo-nombre')).queryByRole('alert')).not.toBeOnTheScreen();
    });

    // Sin escenario propio: sin conexión, los mensajes generales de la app; lo escrito se queda.
    test('alta-lavanderias-E19 — sin conexión lo dice con el mensaje general y conserva lo escrito', async () => {
      await abrirElPerfil('android');
      servidor().interceptar = ({ metodo }) => {
        if (metodo === 'PATCH') {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };

      await escribir('Nombre', 'Lavandería renovada');
      await guardar();

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.getByLabelText('Nombre')).toHaveDisplayValue('Lavandería renovada');
      expect(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Guardar cambios' })).toBeEnabled();
    });
  });
});
