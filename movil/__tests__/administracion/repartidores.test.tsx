// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Gestión de
// repartidores en la app": administracion-app-E19 (la lista y la ficha), E20 (dar de alta en una plaza), E21 (suspender y
// levantar la suspensión) y E22 (dar de baja), con E03 (cada entrada abre su pantalla dentro de la app), E05 (el 401 en
// cada pantalla) y E12 (el 404 y el 409). Tareas 6.1 y 6.2 de openspec/changes/administracion-en-la-app/tasks.md.
//
// El Módulo D #2 pide "Alta, baja, suspensión y edición de socios repartidores" (NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3), y el Módulo C #1, que "El alta, validación y autorización del repartidor corresponden a EL CLIENTE"
// (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3). Arthur decidió que la administración lo hace en la app (C-247); cómo, lo
// decide ADR-0022 (decisiones 9 y 11). Las reglas son las del panel (repartidor-E05 a E07 y E31): la app muestra los
// botones que el servidor dice que el estado permite y no decide ninguno.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y los repartidores del contrato de RepartidoresController
// (__tests__/apoyo/administracion/repartidores.ts).
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

import { MENSAJES } from '../../src/acceso/mensajes';
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
  accionesMandadas,
  ACTIVO,
  comoSale,
  consultas,
  DE_BAJA,
  NO_EXISTE,
  OTRO_PENDIENTE,
  PENDIENTE,
  PLAZAS,
  QUE_NO_EXISTE,
  SUSPENDIDO,
  transicionNoPermitida,
  usarLosRepartidores,
  type RepartidorGuardado,
} from '../apoyo/administracion/repartidores';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-repartidores');
const repartidores = usarLosRepartidores(servidor);

const RUTA_DE_LA_LISTA = '/administracion/repartidores';
const DURANGO = PLAZAS[0].nombre;
const TORREON = PLAZAS[1].nombre;

let dialogo: jest.SpyInstance;

beforeEach(() => {
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

function rutaDeLaFicha(repartidor: Pick<RepartidorGuardado, 'id_publico'>): string {
  return `${RUTA_DE_LA_LISTA}/${repartidor.id_publico}`;
}

async function abrirLaLista(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LA_LISTA);
  await screen.findByRole('link', { name: /^Repartidor de prueba 3,/ });
}

async function abrirLaFicha(plataforma: Plataforma, repartidor: RepartidorGuardado) {
  await abrirLaAppConLaAdministracion(plataforma, rutaDeLaFicha(repartidor));
  await screen.findByRole('header', { name: repartidor.nombre as string });
}

/** Lo que dice cada fila de la lista, como la lee el lector de pantalla, en su orden. */
function filasDeLaLista(): string[] {
  return screen.getAllByRole('link').map((fila) => fila.props.accessibilityLabel as string);
}

/** Los botones fijos abajo de la ficha, en su orden. */
function botonesDeLaFicha(): string[] {
  return within(screen.getByTestId('acciones'))
    .getAllByRole('button')
    .map((boton) => within(boton).getByText(/.+/).props.children as string);
}

function botonDeLaFicha(nombre: string) {
  return within(screen.getByTestId('acciones')).getByRole('button', { name: nombre });
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

/** Toca el botón de la ficha y confirma en el diálogo; devuelve el título y el texto del diálogo. */
async function hacerYConfirmar(boton: string, confirmacion = boton) {
  await fireEvent.press(botonDeLaFicha(boton));
  const { titulo, texto, botones } = ultimoDialogo();
  await tocar(botones, confirmacion);
  return { titulo, texto };
}

/** Elige la plaza del alta en la hoja del Selector, con las plazas que dio el servidor. */
async function elegirPlaza(nombre: string) {
  await waitFor(() => expect(screen.getByRole('button', { name: 'Plaza para el alta' })).toBeEnabled());
  await fireEvent.press(screen.getByRole('button', { name: 'Plaza para el alta' }));
  await fireEvent.press(within(screen.getByTestId('hoja-plaza')).getByRole('radio', { name: nombre }));
}

describe('administracion-app-E03: cada entrada abre su pantalla dentro de la app', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Repartidores" del inicio abre la lista, una fila abre su ficha y "Regresar" vuelve a la lista, que se pide de nuevo',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, RUTA_DEL_INICIO);
      await screen.findByRole('header', { name: 'Administración' });
      // El inicio pide la misma lista para su conteo de pendientes (administracion-app-E03).
      await waitFor(() => expect(consultas(servidor(), RUTA_DE_LA_LISTA)).toBe(1));

      await fireEvent.press(screen.getByRole('link', { name: 'Repartidores' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
      expect(await screen.findByRole('header', { name: 'Repartidores' })).toBeOnTheScreen();
      await fireEvent.press(await screen.findByRole('link', { name: /^Repartidor de prueba 1,/ }));

      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ACTIVO)));
      expect(await screen.findByRole('header', { name: ACTIVO.nombre as string })).toBeOnTheScreen();
      expect(consultas(servidor(), rutaDeLaFicha(ACTIVO))).toBe(1);
      expect(consultas(servidor(), RUTA_DE_LA_LISTA)).toBe(2);

      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LA_LISTA));
      await waitFor(() => expect(consultas(servidor(), RUTA_DE_LA_LISTA)).toBe(3));
    },
  );
});

describe('administracion-app-E19: la lista y la ficha de los repartidores', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E19 — (%s) la lista trae a todos, pendientes sin plaza, activos, suspendidos y de baja, con su nombre, su estado y su plaza o "Sin plaza"; los pendientes, primero',
    async (plataforma) => {
      await abrirLaLista(plataforma);

      expect(filasDeLaLista()).toEqual([
        `${PENDIENTE.nombre}, Pendiente, Sin plaza`,
        `${OTRO_PENDIENTE.nombre}, Pendiente, Sin plaza`,
        `${ACTIVO.nombre}, Activo, ${DURANGO}`,
        `${SUSPENDIDO.nombre}, Suspendido, ${TORREON}`,
        `${DE_BAJA.nombre}, Dado de baja, ${DURANGO}`,
      ]);
      // Lo mismo, a la vista: el nombre, la insignia y la plaza.
      const pendiente = screen.getByRole('link', { name: /^Repartidor de prueba 3,/ });
      expect(within(pendiente).getByText(PENDIENTE.nombre as string)).toBeOnTheScreen();
      expect(within(pendiente).getByText('Pendiente')).toBeOnTheScreen();
      expect(within(pendiente).getByText('Sin plaza')).toBeOnTheScreen();
      const suspendido = screen.getByRole('link', { name: /^Repartidor de prueba 2,/ });
      expect(within(suspendido).getByText('Suspendido')).toBeOnTheScreen();
      expect(within(suspendido).getByText(TORREON)).toBeOnTheScreen();
      expect(servidor().peticiones().filter(({ ruta }) => ruta.startsWith('/administracion'))).toEqual([
        expect.objectContaining({ metodo: 'GET', ruta: RUTA_DE_LA_LISTA }),
      ]);
    },
  );

  const FICHAS: [RepartidorGuardado, string, string[]][] = [
    [PENDIENTE, 'Pendiente', ['Elige una plaza para darlo de alta', 'Editar', 'Dar de baja']],
    [ACTIVO, 'Activo', ['Editar', 'Suspender', 'Dar de baja']],
    [SUSPENDIDO, 'Suspendido', ['Levantar la suspensión', 'Editar', 'Dar de baja']],
    [DE_BAJA, 'Dado de baja', ['Editar']],
  ];

  test.each(
    PLATAFORMAS.flatMap((plataforma) =>
      FICHAS.map(([repartidor, estado, botones]) => [plataforma, estado, repartidor, botones] as const),
    ),
  )(
    'administracion-app-E19 — (%s) la ficha de uno %s trae su nombre, su teléfono, su correo, su foto y su tipo de vehículo, y solo los botones que su estado permite',
    async (plataforma, estado, repartidor, botones) => {
      await abrirLaFicha(plataforma, repartidor);

      expect(screen.getByRole('header', { name: 'Repartidor' })).toBeOnTheScreen();
      expect(screen.getByText(estado)).toBeOnTheScreen();
      expect(screen.getByText(repartidor.plaza?.nombre ?? 'Sin plaza')).toBeOnTheScreen();
      expect(screen.getByLabelText(`Teléfono: ${repartidor.telefono}`)).toBeOnTheScreen();
      expect(screen.getByLabelText(`Correo: ${repartidor.correo}`)).toBeOnTheScreen();
      expect(screen.getByLabelText(`Tipo de vehículo: ${repartidor.tipo_vehiculo}`)).toBeOnTheScreen();
      // La foto se anuncia "Foto de perfil", como en las imágenes 15 a 19; sin foto, el círculo vacío no se anuncia.
      if (repartidor.foto) {
        expect(screen.getByLabelText('Foto de perfil').props.source).toEqual({ uri: repartidor.foto.url });
      } else {
        expect(screen.queryByLabelText('Foto de perfil')).not.toBeOnTheScreen();
      }
      expect(botonesDeLaFicha()).toEqual(botones);
      // Su posición no sale (DD-077), y la ficha no pide más que al repartidor y, si se puede dar de alta, las plazas.
      expect(
        servidor()
          .peticiones()
          .filter(({ ruta }) => ruta.startsWith('/administracion'))
          .map(({ metodo, ruta }) => `${metodo} ${ruta}`)
          .sort(),
      ).toEqual(
        [`GET ${rutaDeLaFicha(repartidor)}`, ...(repartidor.estado === 'pendiente' ? ['GET /administracion/plazas'] : [])].sort(),
      );
    },
  );

  test('administracion-app-E19 — los botones salen de las transiciones que da el servidor, no del estado: la app no decide ninguna regla', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'GET' && ruta === rutaDeLaFicha(ACTIVO)
        ? respuesta(200, { repartidor: { ...comoSale(ACTIVO), transiciones: ['dar_de_baja'] } })
        : undefined;

    await abrirLaFicha('android', ACTIVO);

    expect(screen.getByText('Activo')).toBeOnTheScreen();
    expect(botonesDeLaFicha()).toEqual(['Editar', 'Dar de baja']);
  });

  test('administracion-app-E19 — sin ningún repartidor, la lista lo dice como el panel con su tabla vacía', async () => {
    servidor().interceptar = ({ metodo, ruta }) =>
      metodo === 'GET' && ruta === RUTA_DE_LA_LISTA ? respuesta(200, { repartidores: [] }) : undefined;

    await abrirLaAppConLaAdministracion('android', RUTA_DE_LA_LISTA);

    expect(await screen.findByText('No se encontraron registros')).toBeOnTheScreen();
    expect(screen.queryAllByRole('link')).toEqual([]);
  });

  test('administracion-app-E19 — sin conexión, la lista lo dice con el mensaje de la app y "Intentar de nuevo" la vuelve a pedir', async () => {
    servidor().interceptar = ({ metodo, ruta }) => {
      if (metodo === 'GET' && ruta === RUTA_DE_LA_LISTA) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };
    await abrirLaAppConLaAdministracion('ios', RUTA_DE_LA_LISTA);

    expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
    expect(screen.queryAllByRole('link')).toEqual([]);

    servidor().interceptar = null;
    await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

    expect(await screen.findByRole('link', { name: /^Repartidor de prueba 3,/ })).toBeOnTheScreen();
    expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
  });
});

describe('administracion-app-E20: dar de alta a un repartidor en una plaza', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E20 — (%s) elige la plaza de las que da el servidor, el botón la nombra, lo confirma y la ficha lo muestra activo en esa plaza',
    async (plataforma) => {
      await abrirLaFicha(plataforma, PENDIENTE);

      await waitFor(() => expect(screen.getByRole('button', { name: 'Plaza para el alta' })).toBeEnabled());
      await fireEvent.press(screen.getByRole('button', { name: 'Plaza para el alta' }));
      const opciones = within(screen.getByTestId('hoja-plaza'))
        .getAllByRole('radio')
        .map((opcion) => opcion.props.accessibilityLabel as string);
      expect(opciones).toEqual(PLAZAS.map(({ nombre }) => nombre));
      await fireEvent.press(within(screen.getByTestId('hoja-plaza')).getByRole('radio', { name: DURANGO }));

      const { titulo, texto } = await hacerYConfirmar(`Dar de alta en ${DURANGO}`, 'Dar de alta');

      expect(titulo).toBe('Dar de alta al repartidor');
      expect(texto).toBe(
        `Queda activo en ${DURANGO} y ve los pedidos que se le asignen en ella. La validación de sus datos ocurre fuera de la plataforma.`,
      );
      await waitFor(() => expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']));
      expect(screen.getByText('Activo')).toBeOnTheScreen();
      expect(screen.getByText(DURANGO)).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Plaza para el alta' })).not.toBeOnTheScreen();
      expect(accionesMandadas(servidor())).toEqual([
        { metodo: 'POST', ruta: `${rutaDeLaFicha(PENDIENTE)}/alta`, cuerpo: { plaza: DURANGO } },
      ]);
      expect(repartidores().de(PENDIENTE.id_publico)).toMatchObject({ estado: 'activo', plaza: { nombre: DURANGO } });
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E20 — (%s) sin plaza elegida, el botón del alta no se puede tocar y dice qué falta, y la app no lo manda',
    async (plataforma) => {
      await abrirLaFicha(plataforma, PENDIENTE);
      await waitFor(() => expect(screen.getByRole('button', { name: 'Plaza para el alta' })).toBeEnabled());

      const alta = botonDeLaFicha('Elige una plaza para darlo de alta');
      expect(alta).toBeDisabled();
      await fireEvent.press(alta);

      expect(dialogo).not.toHaveBeenCalled();
      expect(accionesMandadas(servidor())).toEqual([]);
      expect(repartidores().de(PENDIENTE.id_publico).estado).toBe('pendiente');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E20 — (%s) sin conexión, las plazas lo dicen con el mensaje de la app y "Intentar de nuevo" las vuelve a pedir',
    async (plataforma) => {
      servidor().interceptar = ({ metodo, ruta }) => {
        if (metodo === 'GET' && ruta === '/administracion/plazas') {
          throw new TypeError('Network request failed');
        }
        return undefined;
      };
      await abrirLaFicha(plataforma, PENDIENTE);

      expect(await screen.findByText(MENSAJES.sinConexion)).toBeOnTheScreen();
      expect(screen.getByRole('button', { name: 'Plaza para el alta' })).toBeDisabled();

      servidor().interceptar = null;
      await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));

      await elegirPlaza(DURANGO);
      expect(screen.queryByText(MENSAJES.sinConexion)).not.toBeOnTheScreen();
      expect(botonDeLaFicha(`Dar de alta en ${DURANGO}`)).toBeEnabled();
      expect(consultas(servidor(), '/administracion/plazas')).toBe(2);
    },
  );

  test('administracion-app-E20 — con "Cancelar" no manda nada y sigue pendiente', async () => {
    await abrirLaFicha('android', PENDIENTE);
    await elegirPlaza(TORREON);

    await hacerYConfirmar(`Dar de alta en ${TORREON}`, 'Cancelar');

    expect(accionesMandadas(servidor())).toEqual([]);
    expect(screen.getByText('Pendiente')).toBeOnTheScreen();
    expect(botonDeLaFicha(`Dar de alta en ${TORREON}`)).toBeEnabled();
  });

  test('administracion-app-E20 — si el servidor la rechaza con 422, su error va junto a la plaza y nada cambia', async () => {
    await abrirLaFicha('ios', PENDIENTE);
    await elegirPlaza(TORREON);
    // Mientras tanto, la plaza deja de estar entre las del alta.
    repartidores().plazas = [{ nombre: DURANGO }];

    await hacerYConfirmar(`Dar de alta en ${TORREON}`, 'Dar de alta');

    expect(
      await within(screen.getByTestId('campo-plaza')).findByText('Esa plaza no está entre las del alta.'),
    ).toBeOnTheScreen();
    expect(screen.getByText('Pendiente')).toBeOnTheScreen();
    expect(repartidores().de(PENDIENTE.id_publico)).toMatchObject({ estado: 'pendiente', plaza: null });
  });
});

describe('administracion-app-E21: suspender a un repartidor y levantar la suspensión', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E21 — (%s) lo suspende con su confirmación y la ficha lo muestra suspendido; al levantar la suspensión, vuelve a activo',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVO);

      const suspension = await hacerYConfirmar('Suspender');

      expect(suspension).toEqual({
        titulo: 'Suspender al repartidor',
        texto: 'Mientras esté suspendido, no ve ni mueve pedidos. Sigue entrando a la app, que le dice que está suspendido.',
      });
      await waitFor(() => expect(botonesDeLaFicha()).toEqual(['Levantar la suspensión', 'Editar', 'Dar de baja']));
      expect(screen.getByText('Suspendido')).toBeOnTheScreen();
      expect(
        screen.getByText('Al levantar la suspensión, vuelve a estar activo, en su misma plaza, y recupera sus pedidos.'),
      ).toBeOnTheScreen();
      expect(repartidores().de(ACTIVO.id_publico).estado).toBe('suspendido');

      const levantamiento = await hacerYConfirmar('Levantar la suspensión');

      expect(levantamiento).toEqual({
        titulo: 'Levantar la suspensión',
        texto: 'El repartidor vuelve a estar activo, en su misma plaza, y recupera sus pedidos.',
      });
      await waitFor(() => expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']));
      expect(screen.getByText('Activo')).toBeOnTheScreen();
      expect(screen.getByText(DURANGO)).toBeOnTheScreen();
      expect(accionesMandadas(servidor())).toEqual([
        { metodo: 'POST', ruta: `${rutaDeLaFicha(ACTIVO)}/suspension`, cuerpo: undefined },
        { metodo: 'DELETE', ruta: `${rutaDeLaFicha(ACTIVO)}/suspension`, cuerpo: undefined },
      ]);
    },
  );

  test('administracion-app-E21 — con "Cancelar" no lo suspende', async () => {
    await abrirLaFicha('ios', ACTIVO);

    await hacerYConfirmar('Suspender', 'Cancelar');

    expect(accionesMandadas(servidor())).toEqual([]);
    expect(botonesDeLaFicha()).toEqual(['Editar', 'Suspender', 'Dar de baja']);
  });
});

describe('administracion-app-E22: dar de baja a un repartidor', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E22 y repartidor-E07 — (%s) uno activo queda de baja, en su plaza, y su ficha ya no ofrece ninguna acción de estado',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVO);

      const baja = await hacerYConfirmar('Dar de baja');

      expect(baja).toEqual({
        titulo: 'Dar de baja al repartidor',
        texto:
          'Ya no podrá iniciar sesión y se cierran sus sesiones abiertas. Sus entregas completadas se quedan en la base. La baja no se deshace desde la app.',
      });
      await waitFor(() => expect(botonesDeLaFicha()).toEqual(['Editar']));
      expect(screen.getByText('Dado de baja')).toBeOnTheScreen();
      expect(screen.getByText(DURANGO)).toBeOnTheScreen();
      expect(accionesMandadas(servidor())).toEqual([
        { metodo: 'POST', ruta: `${rutaDeLaFicha(ACTIVO)}/baja`, cuerpo: undefined },
      ]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E22 y repartidor-E31 — (%s) uno pendiente que nunca se dio de alta queda de baja sin plaza, y su ficha ya no ofrece ninguna acción de estado',
    async (plataforma) => {
      await abrirLaFicha(plataforma, PENDIENTE);

      await hacerYConfirmar('Dar de baja');

      await waitFor(() => expect(botonesDeLaFicha()).toEqual(['Editar']));
      expect(screen.getByText('Dado de baja')).toBeOnTheScreen();
      expect(screen.getByText('Sin plaza')).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Plaza para el alta' })).not.toBeOnTheScreen();
      expect(repartidores().de(PENDIENTE.id_publico)).toMatchObject({ estado: 'baja', plaza: null });
    },
  );
});

describe('administracion-app-E12: un registro que no existe y una acción que ya no se puede', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) la ficha de un repartidor que no existe dice el mensaje del 404, sin datos ni botones',
    async (plataforma) => {
      await abrirLaAppConLaAdministracion(plataforma, `${RUTA_DE_LA_LISTA}/${QUE_NO_EXISTE}`);

      expect(await screen.findByText(NO_EXISTE)).toBeOnTheScreen();
      expect(screen.queryByTestId('acciones')).not.toBeOnTheScreen();
      expect(screen.queryByTestId('datos-del-repartidor')).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Intentar de nuevo' })).not.toBeOnTheScreen();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) si otra sesión ya lo suspendió, el 409 muestra el mensaje del servidor, nada cambia y la app vuelve a pedir la ficha',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVO);
      // Otra sesión lo suspende mientras la ficha está abierta.
      repartidores().cambiar(ACTIVO.id_publico, { estado: 'suspendido' });

      await hacerYConfirmar('Suspender');

      expect(await screen.findByText(transicionNoPermitida('suspendido', 'suspender'))).toBeOnTheScreen();
      await waitFor(() => expect(botonesDeLaFicha()).toEqual(['Levantar la suspensión', 'Editar', 'Dar de baja']));
      expect(screen.getByText('Suspendido')).toBeOnTheScreen();
      expect(consultas(servidor(), rutaDeLaFicha(ACTIVO))).toBe(2);
      expect(repartidores().de(ACTIVO.id_publico).estado).toBe('suspendido');
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

      await fireEvent.press(screen.getByRole('link', { name: 'Repartidores' }));

      await esperarElInicioDeSesion();
      expect(servidor().peticiones()).toContainEqual(expect.objectContaining({ metodo: 'GET', ruta: RUTA_DE_LA_LISTA }));
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, la ficha recibe 401 y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaLista(plataforma);
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('link', { name: /^Repartidor de prueba 1,/ }));

      await esperarElInicioDeSesion();
      expect(servidor().peticiones()).toContainEqual(
        expect.objectContaining({ metodo: 'GET', ruta: rutaDeLaFicha(ACTIVO) }),
      );
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el token vencido, una acción de la ficha recibe 401, nada cambia y la app vuelve al inicio de sesión',
    async (plataforma) => {
      await abrirLaFicha(plataforma, ACTIVO);
      servidor().vencerLaSesion();

      await hacerYConfirmar('Suspender');

      await esperarElInicioDeSesion();
      expect(accionesMandadas(servidor())).toEqual([
        { metodo: 'POST', ruta: `${rutaDeLaFicha(ACTIVO)}/suspension`, cuerpo: undefined },
      ]);
      expect(repartidores().de(ACTIVO.id_publico).estado).toBe('activo');
    },
  );
});
