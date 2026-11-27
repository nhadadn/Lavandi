// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Avisos masivos en
// la app": administracion-app-E42 (mandar un aviso global) y E43 (los avisos enviados), con E03 (cada entrada abre su
// pantalla dentro de la app) y E05 (el 401 en cada pantalla). Tarea 16.1 de
// openspec/changes/administracion-en-la-app/tasks.md.
//
// El Módulo D #7 pide "Envío segmentado o global para promociones o mantenimiento." (NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3). Arthur decidió que la administración lo hace en la app (C-247); cómo, lo decide ADR-0022 (decisiones 9
// y 16). La regla es la del panel (administracion-E23): la app manda el título y el texto y muestra los errores 422 que
// da el servidor, sin validar por su cuenta. El aviso a un segmento espera DD-113 (administracion-app-E44): la pantalla
// no lo ofrece.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y los avisos del contrato de AvisosMasivosController
// (__tests__/apoyo/administracion/avisos.ts).
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

import { MENSAJES as MENSAJES_DE_LA_APP } from '../../src/acceso/mensajes';
import { leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  respuesta,
  RUTA_DEL_INICIO,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  avisosMandados,
  consultasDeLaLista,
  CONTADO,
  EL_PRIMERO,
  EN_COLA,
  MENSAJES,
  RUTA_DE_LOS_AVISOS,
  usarLosAvisos,
  type AvisoGuardado,
} from '../apoyo/administracion/avisos';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-avisos');
const avisos = usarLosAvisos(servidor);

const RUTA_DE_LA_LISTA = '/administracion/avisos';
const RUTA_DEL_AVISO_NUEVO = '/administracion/avisos/nuevo';

// Los textos de la imagen 37 de la Propuesta 06: 30 y 43 caracteres.
const TITULO = 'Aviso de prueba: mantenimiento';
const TEXTO = 'Texto de prueba del aviso de mantenimiento.';

let dialogo: jest.SpyInstance;

beforeEach(() => {
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

async function abrirLaLista(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_LISTA);
  await screen.findByRole('header', { name: 'Avisos masivos' });
}

async function abrirElAvisoNuevo(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_AVISO_NUEVO);
  await screen.findByRole('header', { name: 'Escribir aviso' });
}

/** Lo que dice cada tarjeta de la lista, en su orden: el título, el texto, cuándo y quién, y a cuántos salió. */
function tarjetasDeLaLista(): string[][] {
  return screen.getAllByTestId('aviso').map((tarjeta) =>
    within(tarjeta)
      .getAllByText(/.+/)
      .map((texto) => texto.props.children as string),
  );
}

/** El título, el texto y los botones del último diálogo de confirmación. */
function ultimoDialogo(): { titulo: string; texto: string | undefined; botones: AlertButton[] } {
  const [titulo, texto, botones] = dialogo.mock.calls[dialogo.mock.calls.length - 1] as [
    string,
    string | undefined,
    AlertButton[],
  ];
  return { titulo, texto, botones };
}

async function tocar(botones: AlertButton[], texto: string) {
  const boton = botones.find((opcion) => opcion.text === texto);
  expect(boton).toBeDefined();
  await act(async () => {
    boton?.onPress?.();
  });
}

async function escribir(titulo: string, texto: string) {
  await fireEvent.changeText(screen.getByLabelText('Título'), titulo);
  await fireEvent.changeText(screen.getByLabelText('Texto'), texto);
}

/** Toca "Mandar aviso" y responde el diálogo con ese botón. */
async function mandarY(respuestaDelDialogo: 'Mandar' | 'Cancelar') {
  await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Mandar aviso' }));
  const { botones } = ultimoDialogo();
  await tocar(botones, respuestaDelDialogo);
}

function errorJuntoA(campo: 'titulo' | 'mensaje', texto: string) {
  return within(screen.getByTestId(`campo-${campo}`)).getByText(texto);
}

describe('administracion-app-E03: cada entrada abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Avisos masivos" del inicio abre la lista, "Escribir aviso" abre el aviso nuevo y "Regresar" vuelve a la lista y al inicio',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });

      await fireEvent.press(screen.getByRole('link', { name: 'Avisos masivos' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
      expect(await screen.findByRole('header', { name: 'Avisos masivos' })).toBeOnTheScreen();
      await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(3));

      await fireEvent.press(screen.getByRole('button', { name: 'Escribir aviso' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_AVISO_NUEVO));
      expect(await screen.findByRole('header', { name: 'Escribir aviso' })).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
      // La lista se pide de nuevo al volver a verse.
      await waitFor(() => expect(consultasDeLaLista(servidor())).toBe(2));

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
    },
  );
});

describe('administracion-app-E43: los avisos enviados', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E43 — (%s) del más reciente al más antiguo, cada uno con su título, su texto, cuándo y quién lo mandó, y a cuántos teléfonos salió; el que sigue en la cola dice "En cola"',
    async (plataforma) => {
      await abrirLaLista(plataforma);
      await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(3));

      // Cuándo, en la hora del teléfono y con el formato del panel (d/m/Y H:i), como la imagen 36.
      expect(tarjetasDeLaLista()).toEqual([
        [EN_COLA.titulo, EN_COLA.mensaje, '24/11/2026, 10:30 · lo mandaste tú', 'En cola'],
        [
          CONTADO.titulo,
          CONTADO.mensaje,
          '20/11/2026, 18:05 · lo mandó otra cuenta de administración',
          'Salió a 3 teléfonos',
        ],
        [EL_PRIMERO.titulo, EL_PRIMERO.mensaje, '12/11/2026, 09:15 · lo mandaste tú', 'Salió a 2 teléfonos'],
      ]);
      // "Escribir aviso", arriba de la lista.
      expect(screen.getByRole('button', { name: 'Escribir aviso' })).toBeOnTheScreen();
      expect(servidor().peticiones().filter(({ ruta }) => ruta.startsWith('/administracion'))).toEqual([
        expect.objectContaining({ metodo: 'GET', ruta: RUTA_DE_LOS_AVISOS }),
      ]);
    },
  );

  test('administracion-app-E43 — el orden es el del servidor: la app no los reordena', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'GET' && ruta === RUTA_DE_LOS_AVISOS ? respuestaConLosAvisos([CONTADO, EN_COLA]) : undefined;

    await abrirLaLista('android');
    await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(2));

    expect(tarjetasDeLaLista().map(([titulo]) => titulo)).toEqual([CONTADO.titulo, EN_COLA.titulo]);
  });

  test('administracion-app-E43 — un aviso que salió a un solo teléfono lo dice en singular', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'GET' && ruta === RUTA_DE_LOS_AVISOS
        ? respuestaConLosAvisos([{ ...CONTADO, destinatarios: 1 }])
        : undefined;

    await abrirLaLista('ios');

    expect(await screen.findByText('Salió a 1 teléfono')).toBeOnTheScreen();
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E43 — (%s) sin avisos, la lista lo dice con el texto del panel y ofrece escribir el primero',
    async (plataforma) => {
      servidor().interceptar = ({ metodo, ruta }) =>
        metodo === 'GET' && ruta === RUTA_DE_LOS_AVISOS ? respuestaConLosAvisos([]) : undefined;

      await abrirLaLista(plataforma);

      expect(await screen.findByText('Todavía no se ha mandado ningún aviso.')).toBeOnTheScreen();
      expect(screen.queryAllByTestId('aviso')).toHaveLength(0);
      expect(screen.getByRole('button', { name: 'Escribir aviso' })).toBeOnTheScreen();
    },
  );

  test('administracion-app-E43 — si la lista no llega, lo dice e "Intentar de nuevo" la pide otra vez', async () => {
    let sinConexion = true;
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'GET' && ruta === RUTA_DE_LOS_AVISOS && sinConexion) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLaLista('android');

    expect(await screen.findByText(MENSAJES_DE_LA_APP.sinConexion)).toBeOnTheScreen();
    sinConexion = false;
    await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

    await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(3));
    expect(screen.queryByText(MENSAJES_DE_LA_APP.sinConexion)).not.toBeOnTheScreen();
  });

  test('administracion-app-E43 — una respuesta fuera del contrato no se pinta a medias', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'GET' && ruta === RUTA_DE_LOS_AVISOS
        ? respuestaConLosAvisos([EN_COLA, { ...CONTADO, destinatarios: 'muchos' }])
        : undefined;

    await abrirLaLista('ios');

    expect(await screen.findByText(MENSAJES_DE_LA_APP.servidor)).toBeOnTheScreen();
    expect(screen.queryAllByTestId('aviso')).toHaveLength(0);
  });
});

describe('administracion-app-E42: mandar un aviso global', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) "Escribir aviso" lleva el título y el texto con cuántos caracteres lleva cada uno de su tope, a quién le llega y el aviso a un segmento en ámbar, sin ofrecerlo',
    async (plataforma) => {
      await abrirElAvisoNuevo(plataforma);

      expect(screen.getByTestId('contador-titulo')).toHaveTextContent('0 de 255 caracteres');
      expect(screen.getByTestId('contador-mensaje')).toHaveTextContent('0 de 1000 caracteres');

      await escribir(TITULO, TEXTO);

      expect(screen.getByTestId('contador-titulo')).toHaveTextContent('30 de 255 caracteres');
      expect(screen.getByTestId('contador-mensaje')).toHaveTextContent('43 de 1000 caracteres');
      expect(screen.getByText('A quién le llega')).toBeOnTheScreen();
      expect(
        screen.getByText('A todas las cuentas de la app con un teléfono registrado para avisos, salvo las bloqueadas.'),
      ).toBeOnTheScreen();
      const segmento = screen.getByTestId('aviso-a-un-segmento');
      expect(within(segmento).getByText('Aviso a un segmento')).toBeOnTheScreen();
      expect(within(segmento).getByText('Por confirmar · DD-113')).toBeOnTheScreen();
      expect(within(segmento).getByText('La app no ofrece segmentos.')).toBeOnTheScreen();
      // Sin segmentos que elegir (administracion-app-E44 espera DD-113): ni opciones, ni interruptores, ni más botones
      // que "Regresar" y "Mandar aviso".
      expect(screen.queryAllByRole('radio')).toHaveLength(0);
      expect(screen.queryAllByRole('switch')).toHaveLength(0);
      expect(screen.getAllByRole('button')).toHaveLength(2);
      expect(screen.getByRole('button', { name: 'Regresar' })).toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Mandar aviso' })).toBeOnTheScreen();
      // Escribir no manda nada.
      expect(avisosMandados(servidor())).toEqual([]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) "Mandar aviso" pide confirmar que sale a todos los teléfonos registrados; al confirmarlo, se manda el título y el texto y la app vuelve a la lista, con el aviso arriba, "En cola"',
    async (plataforma) => {
      await abrirLaLista(plataforma);
      await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(3));
      await fireEvent.press(screen.getByRole('button', { name: 'Escribir aviso' }));
      await screen.findByRole('header', { name: 'Escribir aviso' });
      await escribir(TITULO, TEXTO);

      await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button', { name: 'Mandar aviso' }));

      const { titulo, texto, botones } = ultimoDialogo();
      expect(titulo).toBe('¿Mandar el aviso?');
      expect(texto).toBe('Sale a todos los teléfonos registrados.');
      expect(botones.map(({ text }) => text)).toEqual(['Cancelar', 'Mandar']);
      // Antes de confirmar, no sale nada.
      expect(avisosMandados(servidor())).toEqual([]);

      await tocar(botones, 'Mandar');

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
      expect(avisosMandados(servidor())).toEqual([{ titulo: TITULO, mensaje: TEXTO }]);
      expect(avisos().cuantos()).toBe(4);
      // La lista se pide otra vez y el nuevo sale arriba, en cola.
      await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(4));
      const [nuevo] = tarjetasDeLaLista();
      expect(nuevo[0]).toBe(TITULO);
      expect(nuevo[1]).toBe(TEXTO);
      expect(nuevo[2]).toMatch(/ · lo mandaste tú$/);
      expect(nuevo[3]).toBe('En cola');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) si no confirma, no sale nada y sigue en "Escribir aviso" con lo que escribió',
    async (plataforma) => {
      await abrirElAvisoNuevo(plataforma);
      await escribir(TITULO, TEXTO);

      await mandarY('Cancelar');

      expect(avisosMandados(servidor())).toEqual([]);
      expect(avisos().cuantos()).toBe(3);
      expect(screen).toHavePathname(RUTA_DEL_AVISO_NUEVO);
      expect(screen.getByLabelText('Título').props.value).toBe(TITULO);
      expect(screen.getByLabelText('Texto').props.value).toBe(TEXTO);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) sin título, el servidor responde 422, "Escribe el título." va junto al título y no sale nada',
    async (plataforma) => {
      await abrirElAvisoNuevo(plataforma);
      await escribir('', TEXTO);

      await mandarY('Mandar');

      expect(await screen.findByText(MENSAJES.sinTitulo)).toBeOnTheScreen();
      expect(errorJuntoA('titulo', MENSAJES.sinTitulo)).toBeOnTheScreen();
      expect(screen.getByTestId('contador-titulo')).toHaveTextContent('0 de 255 caracteres');
      // El error va junto a su campo, no repetido arriba del botón.
      expect(within(screen.getByTestId('acciones')).queryByText(MENSAJES.sinTitulo)).not.toBeOnTheScreen();
      expect(avisosMandados(servidor())).toEqual([{ titulo: '', mensaje: TEXTO }]);
      expect(avisos().cuantos()).toBe(3);
      expect(screen).toHavePathname(RUTA_DEL_AVISO_NUEVO);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) sin texto, o con solo espacios, el error del texto va junto a él y no sale nada',
    async (plataforma) => {
      await abrirElAvisoNuevo(plataforma);
      await escribir(TITULO, '   ');

      await mandarY('Mandar');

      expect(await screen.findByText(MENSAJES.sinTexto)).toBeOnTheScreen();
      expect(errorJuntoA('mensaje', MENSAJES.sinTexto)).toBeOnTheScreen();
      expect(within(screen.getByTestId('campo-titulo')).queryByRole('alert')).not.toBeOnTheScreen();
      expect(avisos().cuantos()).toBe(3);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) con un título de más de 255 caracteres y un texto de más de 1000, cada error va junto a su campo, cada contador dice cuántos lleva y no sale nada',
    async (plataforma) => {
      await abrirElAvisoNuevo(plataforma);
      // La app no corta lo que se escribe: el tope lo aplica el servidor. Los acentos cuentan uno, como en mb_strlen.
      await escribir('á'.repeat(256), 'é'.repeat(1001));

      expect(screen.getByTestId('contador-titulo')).toHaveTextContent('256 de 255 caracteres');
      expect(screen.getByTestId('contador-mensaje')).toHaveTextContent('1001 de 1000 caracteres');

      await mandarY('Mandar');

      expect(await screen.findByText(MENSAJES.tituloLargo)).toBeOnTheScreen();
      expect(errorJuntoA('titulo', MENSAJES.tituloLargo)).toBeOnTheScreen();
      expect(errorJuntoA('mensaje', MENSAJES.textoLargo)).toBeOnTheScreen();
      expect(avisos().cuantos()).toBe(3);
      expect(screen).toHavePathname(RUTA_DEL_AVISO_NUEVO);
    },
  );

  test('administracion-app-E42 — con los topes justos, 255 y 1000 caracteres, el aviso sale', async () => {
    await abrirElAvisoNuevo('android');
    await escribir('t'.repeat(255), 'm'.repeat(1000));

    expect(screen.getByTestId('contador-titulo')).toHaveTextContent('255 de 255 caracteres');
    expect(screen.getByTestId('contador-mensaje')).toHaveTextContent('1000 de 1000 caracteres');

    await mandarY('Mandar');

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
    expect(avisos().cuantos()).toBe(4);
  });

  test('administracion-app-E42 — después de un 422, al corregir y mandar otra vez, el aviso sale y la app vuelve a la lista', async () => {
    await abrirElAvisoNuevo('ios');
    await escribir('', TEXTO);
    await mandarY('Mandar');
    await screen.findByText(MENSAJES.sinTitulo);

    await fireEvent.changeText(screen.getByLabelText('Título'), TITULO);
    await mandarY('Mandar');

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
    expect(avisosMandados(servidor())).toEqual([
      { titulo: '', mensaje: TEXTO },
      { titulo: TITULO, mensaje: TEXTO },
    ]);
    expect(avisos().cuantos()).toBe(4);
  });

  test('administracion-app-E42 — sin conexión, lo dice arriba del botón, no sale nada y lo escrito se queda', async () => {
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'POST' && ruta === RUTA_DE_LOS_AVISOS) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };
    await abrirElAvisoNuevo('android');
    await escribir(TITULO, TEXTO);

    await mandarY('Mandar');

    expect(await within(screen.getByTestId('acciones')).findByText(MENSAJES_DE_LA_APP.sinConexion)).toBeOnTheScreen();
    expect(avisos().cuantos()).toBe(3);
    expect(screen).toHavePathname(RUTA_DEL_AVISO_NUEVO);
    expect(screen.getByLabelText('Título').props.value).toBe(TITULO);
  });

  test('administracion-app-E42 — mientras manda, otro toque a "Mandar aviso" no pide otra confirmación ni manda otro aviso', async () => {
    let soltar: (valor: unknown) => void = () => {};
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'POST' && ruta === RUTA_DE_LOS_AVISOS
        ? new Promise((resolver) => {
            soltar = resolver;
          })
        : undefined;
    await abrirElAvisoNuevo('ios');
    await escribir(TITULO, TEXTO);

    await mandarY('Mandar');
    await fireEvent.press(within(screen.getByTestId('acciones')).getByRole('button'));

    expect(dialogo).toHaveBeenCalledTimes(1);
    expect(avisosMandados(servidor())).toHaveLength(1);
    await act(async () => {
      soltar(respuestaCreada());
    });
    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
  });

  test.each(PLATAFORMAS)(
    'administracion-app-E42 — (%s) si regresa a la lista mientras manda, el aviso sale igual y el 201 que llega después no la saca de la lista',
    async (plataforma) => {
      // El servidor guarda el aviso, pero su 201 no llega hasta que la prueba lo suelta.
      let soltar: () => void = () => {};
      servidor().interceptar = (peticion) =>
        peticion.metodo === 'POST' && peticion.ruta === RUTA_DE_LOS_AVISOS
          ? new Promise((resolver) => {
              soltar = () => resolver(avisos().manejador(peticion));
            })
          : undefined;
      // Desde el inicio del módulo, como se llega: bajo la lista queda el inicio, a donde no debe volver.
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      await fireEvent.press(screen.getByRole('link', { name: 'Avisos masivos' }));
      await waitFor(() => expect(screen.getAllByTestId('aviso')).toHaveLength(3));
      await fireEvent.press(screen.getByRole('button', { name: 'Escribir aviso' }));
      await screen.findByRole('header', { name: 'Escribir aviso' });
      await escribir(TITULO, TEXTO);
      await mandarY('Mandar');

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
      await act(async () => {
        soltar();
        // El 201 se lee y la pantalla del aviso termina de mandarlo (los relojes de expo-router en pruebas son falsos).
        await jest.advanceTimersByTimeAsync(0);
      });

      expect(screen).toHavePathname(RUTA_DE_LA_LISTA);
      expect(await screen.findByRole('header', { name: 'Avisos masivos' })).toBeOnTheScreen();
      expect(avisosMandados(servidor())).toEqual([{ titulo: TITULO, mensaje: TEXTO }]);
      expect(avisos().cuantos()).toBe(4);
    },
  );
});

describe('administracion-app-E05: la sesión vence o se revoca', () => {
  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, la lista recibe 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('link', { name: 'Avisos masivos' }));

      await esperarElInicioDeSesion();
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({ metodo: 'GET', ruta: RUTA_DE_LOS_AVISOS }),
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, mandar el aviso recibe 401, no sale nada y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirElAvisoNuevo(plataforma);
      await escribir(TITULO, TEXTO);
      servidor().vencerLaSesion();

      await mandarY('Mandar');

      await esperarElInicioDeSesion();
      expect(avisosMandados(servidor())).toEqual([{ titulo: TITULO, mensaje: TEXTO }]);
      expect(avisos().cuantos()).toBe(3);
    },
  );
});

// La respuesta de GET avisos-masivos con estos avisos, en este orden.
function respuestaConLosAvisos(lista: unknown[]) {
  return respuesta(200, { avisos: lista });
}

// El 201 de POST avisos-masivos con el aviso de la prueba, en cola.
function respuestaCreada() {
  const aviso: AvisoGuardado = {
    titulo: TITULO,
    mensaje: TEXTO,
    enviado_en: new Date().toISOString(),
    enviado_por_esta_cuenta: true,
    destinatarios: null,
  };
  return respuesta(201, { aviso });
}
