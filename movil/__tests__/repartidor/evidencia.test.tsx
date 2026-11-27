// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E23 (tarea 2.5). Al marcar Entregado, la app
// deja al repartidor tomar una foto como evidencia de entrega (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3,
// Almacenamiento de imágenes: "las evidencias de entrega"; ADR-0006): "Entregado" abre la entrega, con el pedido, la
// foto de evidencia, que se toma con la cámara y se comprime con los parámetros de configuración, y "Marcar entregado",
// que manda el botón y la foto juntos, en multipart (POST /api/repartidor/pedidos/{id_publico}/estado con
// estado=entregado y foto). El servidor la guarda en el almacenamiento privado, ligada al pedido y sin URL pública: la
// respuesta solo dice que el pedido tiene evidencia.
//
// Si la evidencia es obligatoria, en qué momentos y si es foto o firma espera BR-29 y DD-110 (repartidor-E24): hoy el
// servidor la acepta opcional, y la app también deja marcar Entregado sin foto. Se abre la app real con la sesión de una
// cuenta de repartidor activo, en iPhone y en Android, con el servidor simulado (__tests__/apoyo/repartidor.ts) y la
// cámara y la compresión simuladas (__mocks__/). La cámara real y el peso de la foto se prueban en un teléfono real.
import * as ImageManipulator from 'expo-image-manipulator';
import * as ImagePicker from 'expo-image-picker';
import { fireEvent, screen, waitFor, within } from 'expo-router/testing-library';

import { MENSAJES } from '../../src/acceso/mensajes';
import permisosDeLaFoto from '../../src/alta-lavanderias/permisos-de-la-foto.json';
import { COMPRESION_DE_FOTOS } from '../../src/configuracion/fotos';
import {
  abrirLaAppConSesion,
  pedidoDelRepartidor,
  PLATAFORMAS,
  PRIMERO,
  respuesta,
  RUTA_DE_LOS_PEDIDOS,
  SEGUNDO,
  usarElServidorDelRepartidor,
  type Plataforma,
} from '../apoyo/repartidor';
import { contenidoDelArchivo } from '../apoyo/formulario';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDelRepartidor('modulo-c-evidencia');

// Las simulaciones de __mocks__/, la misma instancia que importa la app.
const camara = ImagePicker as unknown as typeof import('../../__mocks__/expo-image-picker');
const manipulador = ImageManipulator as unknown as typeof import('../../__mocks__/expo-image-manipulator');

const RUTA_DE_LA_ENTREGA = `${RUTA_DE_LOS_PEDIDOS}/entrega/${PRIMERO.id_publico}`;
const ESTADO_DEL_PRIMERO = `/repartidor/pedidos/${PRIMERO.id_publico}/estado`;

/** La foto de la entrega, vertical, más grande que el lado máximo de la configuración. */
const FOTO_DE_LA_ENTREGA = { uri: 'file:///camara/entrega.jpg', width: 3024, height: 4032, mimeType: 'image/jpeg' };

/** Abre la lista con el primero En tránsito, toca "Entregado" y espera la entrega. */
async function abrirLaEntrega(plataforma: Plataforma) {
  servidor().poner('en_entrega', PRIMERO);
  servidor().poner('listo', SEGUNDO);
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  const tarjeta = within(await screen.findByTestId(`pedido-${PRIMERO.id_publico}`));
  await fireEvent.press(tarjeta.getByRole('button', { name: 'Entregado' }));
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_ENTREGA));
  await screen.findByRole('header', { name: 'Entregar pedido' });
}

async function tomarLaFoto() {
  camara.__fotoDeLaCamara(FOTO_DE_LA_ENTREGA);
  await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));
  await screen.findByLabelText('La foto de la evidencia');
}

async function marcarEntregado() {
  await fireEvent.press(screen.getByRole('button', { name: 'Marcar entregado' }));
}

describe('repartidor-E23: el repartidor toma la evidencia al entregar', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E23 — (%s) "Entregado" abre la entrega; con la foto de la cámara, manda Entregado y la foto juntos y el pedido sale de su lista',
    async (plataforma) => {
      await abrirLaEntrega(plataforma);
      // El pedido que se entrega, con la tarjeta del componente 5, sin sus botones.
      const pedido = within(screen.getByTestId(`pedido-${PRIMERO.id_publico}`));
      expect(pedido.getByText('Pedido 7KQ2M9XA')).toBeOnTheScreen();
      expect(pedido.queryByRole('button', { name: 'Entregado' })).not.toBeOnTheScreen();

      await tomarLaFoto();
      await marcarEntregado();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(servidor().acciones()).toEqual([
        {
          ruta: ESTADO_DEL_PRIMERO,
          multipart: true,
          cuerpo: {
            estado: 'entregado',
            foto: {
              nombre: 'foto.jpg',
              tipo: 'image/jpeg',
              contenido: contenidoDelArchivo(manipulador.__preparada(FOTO_DE_LA_ENTREGA.uri)),
            },
          },
        },
      ]);
      expect(servidor().estadoDe(PRIMERO)).toBe('entregado');
      // La foto queda ligada al pedido: el servidor solo dice su tipo y su hora, sin ruta ni URL.
      expect(servidor().evidenciasDe(PRIMERO)).toEqual([{ tipo: 'entrega', tomada_en: expect.any(String) }]);
      expect(screen.queryByTestId(`pedido-${PRIMERO.id_publico}`)).not.toBeOnTheScreen();
      expect(await screen.findByTestId(`pedido-${SEGUNDO.id_publico}`)).toBeOnTheScreen();
    },
  );

  test('repartidor-E23 — la foto se comprime antes de subirla, con el lado máximo y la calidad de la configuración, en JPEG', async () => {
    await abrirLaEntrega('ios');

    await tomarLaFoto();

    expect(manipulador.__preparaciones()).toEqual([
      {
        fuente: FOTO_DE_LA_ENTREGA.uri,
        redimension: { height: COMPRESION_DE_FOTOS.ladoMaximoPx },
        guardado: { compress: COMPRESION_DE_FOTOS.calidad, format: 'jpeg' },
        resultado: manipulador.__preparada(FOTO_DE_LA_ENTREGA.uri),
      },
    ]);
  });

  // La evidencia es de lo que pasó en la entrega: se toma con la cámara, no se elige de la galería.
  test.each(PLATAFORMAS)('repartidor-E23 — (%s) la evidencia solo se toma con la cámara', async (plataforma) => {
    await abrirLaEntrega(plataforma);

    expect(screen.getByRole('button', { name: 'Tomar foto' })).toBeEnabled();
    expect(screen.queryByRole('button', { name: 'Elegir de la galería' })).not.toBeOnTheScreen();
  });

  // repartidor-E24 espera BR-29 y DD-110: mientras, como el servidor, la app no la exige.
  test('repartidor-E23 — sin foto, marca Entregado igual, sin multipart y sin evidencia', async () => {
    await abrirLaEntrega('android');

    await marcarEntregado();

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(servidor().acciones()).toEqual([
      { ruta: ESTADO_DEL_PRIMERO, multipart: false, cuerpo: { estado: 'entregado' } },
    ]);
    expect(servidor().estadoDe(PRIMERO)).toBe('entregado');
    expect(servidor().evidenciasDe(PRIMERO)).toEqual([]);
  });

  // Sin escenario propio: sin permiso de la cámara, lo dice; la entrega se puede marcar igual (repartidor-E24).
  test('repartidor-E23 — sin permiso de la cámara, lo dice y "Marcar entregado" sigue a la mano', async () => {
    camara.__respuestaAlPedirLaCamara(camara.PermissionStatus.DENIED);
    await abrirLaEntrega('ios');

    await fireEvent.press(screen.getByRole('button', { name: 'Tomar foto' }));

    expect(await within(screen.getByTestId('campo-foto')).findByRole('alert')).toHaveTextContent(
      'Sin permiso de la cámara no podemos tomar la foto. Puedes darlo en los ajustes del teléfono.',
    );
    expect(screen.getByRole('button', { name: 'Marcar entregado' })).toBeEnabled();
  });

  test.each(PLATAFORMAS)(
    'repartidor-E23 — (%s) si el servidor rechaza la foto (422), su error va junto a la foto y el pedido no cambia',
    async (plataforma) => {
      await abrirLaEntrega(plataforma);
      await tomarLaFoto();
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'POST' && ruta === ESTADO_DEL_PRIMERO
          ? respuesta(422, { message: 'La foto pesa más de 5120 KB.', errors: { foto: ['La foto pesa más de 5120 KB.'] } })
          : undefined;

      await marcarEntregado();

      expect(await within(screen.getByTestId('campo-foto')).findByRole('alert')).toHaveTextContent(
        'La foto pesa más de 5120 KB.',
      );
      expect(screen).toHavePathname(RUTA_DE_LA_ENTREGA);
      expect(servidor().estadoDe(PRIMERO)).toBe('en_entrega');
      // La foto sigue a la vista para intentarlo con otra.
      expect(screen.getByLabelText('La foto de la evidencia')).toBeOnTheScreen();
    },
  );

  // Sin escenario propio: ya no es el paso siguiente (409), como si ya lo hubiera marcado desde otro teléfono.
  test('repartidor-E23 — si Entregado ya no es el paso siguiente, muestra el mensaje del servidor y no guarda nada', async () => {
    await abrirLaEntrega('android');
    await tomarLaFoto();
    servidor().poner('entregado', PRIMERO);

    await marcarEntregado();

    expect(await screen.findByText('Ese no es el paso siguiente del pedido.')).toBeOnTheScreen();
    expect(screen).toHavePathname(RUTA_DE_LA_ENTREGA);
    expect(servidor().evidenciasDe(PRIMERO)).toEqual([]);
  });

  // Sin escenario propio: sin conexión, los mensajes generales de la app, y la foto se queda.
  test('repartidor-E23 — sin conexión, lo dice con el mensaje general y la foto se queda', async () => {
    await abrirLaEntrega('ios');
    await tomarLaFoto();
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await marcarEntregado();

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.getByLabelText('La foto de la evidencia')).toBeOnTheScreen();
    expect(servidor().estadoDe(PRIMERO)).toBe('en_entrega');
  });

  test.each(PLATAFORMAS)(
    'repartidor-E23 — (%s) mientras manda, el botón se bloquea: un doble toque entrega una sola vez',
    async (plataforma) => {
      await abrirLaEntrega(plataforma);
      await tomarLaFoto();
      let soltar: () => void = () => {};
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo !== 'POST' || ruta !== ESTADO_DEL_PRIMERO) {
          return undefined;
        }
        return new Promise((resolver) => {
          soltar = () => {
            servidor().poner('entregado', PRIMERO);
            servidor().interceptar = null;
            resolver(
              respuesta(200, {
                pedido: pedidoDelRepartidor('entregado', 'aceptado', PRIMERO, [
                  { tipo: 'entrega', tomada_en: '2026-12-03T17:45:00Z' },
                ]),
              }),
            );
          };
        });
      };

      await marcarEntregado();
      await marcarEntregado();

      expect(servidor().acciones()).toHaveLength(1);
      expect(screen.getByRole('button', { name: 'Marcar entregado' })).toBeDisabled();
      expect(screen.getByRole('button', { name: 'Tomar foto' })).toBeDisabled();

      soltar();

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
      expect(servidor().acciones()).toHaveLength(1);
    },
  );

  // Sin escenario propio: "Regresar" vuelve a la lista sin marcar nada.
  test('repartidor-E23 — "Regresar" vuelve a la lista sin marcar Entregado', async () => {
    await abrirLaEntrega('android');
    await tomarLaFoto();

    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_PEDIDOS));
    expect(servidor().acciones()).toEqual([]);
    expect(servidor().estadoDe(PRIMERO)).toBe('en_entrega');
  });

  // El texto llega al Info.plist (alta-lavanderias-E03 lo compara): en iPhone, el permiso dice para qué es la cámara.
  test('repartidor-E23 — en iPhone, el permiso de la cámara dice que también es para la foto de una entrega', () => {
    expect(permisosDeLaFoto.camara).toMatch(/foto de una entrega/);
  });
});
