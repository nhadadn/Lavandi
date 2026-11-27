// Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/spec.md, requisito "Gestión de usuarios
// en la app": administracion-app-E24 (la lista y la ficha con su historial) y administracion-app-E25 (bloquear y
// levantar el bloqueo), con administracion-app-E03 (la entrada desde el inicio del módulo), administracion-app-E05 (un
// 401 vuelve al inicio de sesión) y administracion-app-E12 (un usuario que no existe). Tareas 8.1 y 8.2 de tasks.md.
//
// El Módulo D #3 pide "Consulta de usuarios activos, historial y bloqueo" (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3).
// La app no da de alta, no edita ni borra usuarios; el filtro de usuarios activos espera DD-111 (administracion-app-E26)
// y lo que pasa con un pedido activo al bloquear, DD-016 (administracion-app-E27). Las reglas son las del panel
// (administracion-E01, E03 y E04): la app muestra lo que responde la API, con el contrato del comentario de
// servidor/app/Http/Controllers/Api/Administracion/UsuariosController.php.
//
// Se abre la app real, con las rutas de movil/app/ (ADR-0017), en iPhone y en Android, con el servidor simulado de la
// administración (__tests__/apoyo/administracion.ts) y las cuentas de __tests__/apoyo/administracion/usuarios.ts.
import { act, fireEvent, screen, waitFor, within } from 'expo-router/testing-library';
import { Alert, type AlertButton } from 'react-native';

import { leerRol, leerToken } from '../../src/sesion/almacen';
import {
  abrirLaAppConLaAdministracion,
  PLATAFORMAS,
  respuesta,
  RUTA_DEL_INICIO,
  TOKEN_DE_LA_ADMINISTRACION,
  usarElServidorDeLaAdministracion,
  type Plataforma,
} from '../apoyo/administracion';
import {
  agregarLosUsuarios,
  ANA,
  BETO,
  MENSAJE_DE_NO_EXISTE,
  SIN_DATOS,
  variosUsuarios,
} from '../apoyo/administracion/usuarios';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDeLaAdministracion('administracion-usuarios');

const RUTA_DE_LOS_USUARIOS = '/administracion/usuarios';

function rutaDeLaFicha(idPublico: string): string {
  return `${RUTA_DE_LOS_USUARIOS}/${idPublico}`;
}

let dialogo: jest.SpyInstance;

beforeEach(() => {
  // Alert.alert puede ser ya una simulación de react-native que guarda las llamadas de todo el archivo.
  dialogo = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  dialogo.mockClear();
});

/** Las peticiones a la gestión de usuarios: "GET /administracion/usuarios?buscar=ana". */
function peticionesDeUsuarios(): string[] {
  return servidor()
    .peticiones()
    .filter(({ ruta }) => ruta.startsWith(RUTA_DE_LOS_USUARIOS))
    .map(({ metodo, ruta }) => `${metodo} ${ruta}`);
}

/** Abre la app con la sesión de la administración en la lista de usuarios, y espera a que llegue. */
async function abrirLaLista(plataforma: Plataforma) {
  await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LOS_USUARIOS);
  await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_USUARIOS));
  expect(await screen.findByRole('header', { name: 'Usuarios' })).toBeOnTheScreen();
}

/** Abre la app con la sesión de la administración en la ficha de esa cuenta. */
async function abrirLaFicha(plataforma: Plataforma, idPublico: string) {
  await abrirLaAppConLaAdministracion(plataforma, rutaDeLaFicha(idPublico));
  await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(idPublico)));
  expect(await screen.findByRole('header', { name: 'Usuario' })).toBeOnTheScreen();
}

/** Los correos de las filas de la lista, en su orden. */
function correosDeLaLista(): string[] {
  return screen.getAllByTestId(/^usuario-/).map((fila) => {
    const correo = within(fila).getByTestId('correo-del-usuario');
    return String(correo.props.children);
  });
}

/** Los folios del historial de la ficha, en su orden. */
function foliosDelHistorial(): string[] {
  return screen.getAllByTestId(/^pedido-/).map((tarjeta) => tarjeta.props.testID.replace(/^pedido-/, ''));
}

/** Los botones del diálogo de confirmación que se abrió, y su título y su texto. */
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

/** La app no ofrece dar de alta, editar ni borrar usuarios: el Módulo D #3 dice consulta, historial y bloqueo. */
function sinAltaEdicionNiBorrado() {
  for (const nombre of [/alta/i, /editar/i, /borrar/i, /eliminar/i, /nuevo/i]) {
    expect(screen.queryByRole('button', { name: nombre })).not.toBeOnTheScreen();
    expect(screen.queryByRole('link', { name: nombre })).not.toBeOnTheScreen();
  }
}

describe('administracion-app-E03: la entrada a Usuarios desde el inicio del módulo', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E03 — (%s) "Usuarios" del inicio abre la lista dentro de la app, y cada cuenta abre su ficha',
    async (plataforma) => {
      agregarLosUsuarios(servidor());
      await abrirLaAppConLaAdministracion(plataforma);
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));

      await fireEvent.press(await screen.findByRole('link', { name: 'Usuarios' }));

      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_USUARIOS));
      expect(await screen.findByRole('header', { name: 'Usuarios' })).toBeOnTheScreen();
      await fireEvent.press(await screen.findByTestId(`usuario-${ANA.id_publico}`));
      await waitFor(() => expect(screen).toHavePathname(rutaDeLaFicha(ANA.id_publico)));
      expect(await screen.findByRole('header', { name: 'Ana Prueba' })).toBeOnTheScreen();

      // "Regresar" vuelve a la lista, y de ahí al inicio del módulo.
      await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_USUARIOS));
      await fireEvent.press(await screen.findByRole('button', { name: 'Regresar' }));
      await waitFor(() => expect(screen).toHavePathname(RUTA_DEL_INICIO));
    },
  );
});

describe('administracion-app-E24: la lista de usuarios, con búsqueda y en páginas', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E24 — (%s) la lista trae cada cuenta de usuario con su nombre, su estado y su correo, como llega',
    async (plataforma) => {
      agregarLosUsuarios(servidor());

      await abrirLaLista(plataforma);

      await waitFor(() => expect(correosDeLaLista()).toEqual([ANA.correo, BETO.correo, SIN_DATOS.correo]));
      expect(peticionesDeUsuarios()).toEqual([`GET ${RUTA_DE_LOS_USUARIOS}`]);
      const ana = screen.getByTestId(`usuario-${ANA.id_publico}`);
      expect(ana).toHaveAccessibleName('Ana Prueba, Activo, ana@ejemplo.com');
      expect(within(ana).getByText('Ana Prueba')).toBeOnTheScreen();
      expect(within(ana).getByText('Activo')).toBeOnTheScreen();
      expect(within(screen.getByTestId(`usuario-${BETO.id_publico}`)).getByText('Bloqueado')).toBeOnTheScreen();
      // Una cuenta que todavía no escribe su nombre lo dice, como el panel.
      expect(within(screen.getByTestId(`usuario-${SIN_DATOS.id_publico}`)).getByText('Sin nombre')).toBeOnTheScreen();
      expect(screen.getByText('Página 1 de 1')).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Siguiente página' })).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Página anterior' })).not.toBeOnTheScreen();
      expect(
        servidor()
          .peticiones()
          .find(({ ruta }) => ruta === RUTA_DE_LOS_USUARIOS)?.autorizacion,
      ).toBe(`Bearer ${TOKEN_DE_LA_ADMINISTRACION}`);
    },
  );

  // El filtro de usuarios activos es el de administracion-app-E26, que espera DD-111 (tarea 22.3): esta prueba no lo
  // cubre, solo comprueba que la lista no lo ofrece mientras tanto.
  test.each(PLATAFORMAS)(
    'administracion-app-E24 — (%s) el filtro de usuarios activos no se ofrece: espera DD-111, y la lista no da de alta, no edita ni borra',
    async (plataforma) => {
      agregarLosUsuarios(servidor());

      await abrirLaLista(plataforma);

      const enEspera = await screen.findByTestId('activos-de-un-periodo');
      expect(within(enEspera).getByText('Solo los usuarios activos de un periodo')).toBeOnTheScreen();
      expect(within(enEspera).getByText('Por confirmar · DD-111')).toBeOnTheScreen();
      expect(within(enEspera).queryByRole('button')).not.toBeOnTheScreen();
      expect(within(enEspera).queryByRole('switch')).not.toBeOnTheScreen();
      sinAltaEdicionNiBorrado();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E24 — (%s) busca por correo y por nombre: la app pide al servidor el texto sin los espacios de las orillas y muestra lo que encuentra',
    async (plataforma) => {
      agregarLosUsuarios(servidor());
      await abrirLaLista(plataforma);
      await waitFor(() => expect(correosDeLaLista()).toHaveLength(3));
      const busqueda = screen.getByLabelText('Buscar por correo o nombre');

      // Por nombre: los dos que se llaman "Prueba", y no la cuenta sin nombre.
      await fireEvent.changeText(busqueda, ' Prueba ');
      await waitFor(() => expect(correosDeLaLista()).toEqual([ANA.correo, BETO.correo]));
      expect(peticionesDeUsuarios()).toContain(`GET ${RUTA_DE_LOS_USUARIOS}?buscar=Prueba`);

      // Por correo.
      await fireEvent.changeText(busqueda, 'cuenta-nueva@');
      await waitFor(() => expect(correosDeLaLista()).toEqual([SIN_DATOS.correo]));
      expect(peticionesDeUsuarios()).toContain(`GET ${RUTA_DE_LOS_USUARIOS}?buscar=cuenta-nueva%40`);

      // Sin coincidencias, lo dice.
      await fireEvent.changeText(busqueda, 'nadie');
      expect(await screen.findByText('Ningún usuario coincide con la búsqueda.')).toBeOnTheScreen();
      expect(screen.queryAllByTestId(/^usuario-/)).toEqual([]);

      // Sin texto, la lista completa otra vez.
      await fireEvent.changeText(busqueda, '');
      await waitFor(() => expect(correosDeLaLista()).toHaveLength(3));
      expect(peticionesDeUsuarios()[peticionesDeUsuarios().length - 1]).toBe(`GET ${RUTA_DE_LOS_USUARIOS}`);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E24 — (%s) en páginas: "Siguiente página" y "Página anterior" piden la de al lado, y buscar vuelve a la primera',
    async (plataforma) => {
      // 30 cuentas: 25 en la primera página, 5 en la segunda.
      agregarLosUsuarios(servidor(), variosUsuarios(30));
      await abrirLaLista(plataforma);
      await waitFor(() => expect(correosDeLaLista()).toHaveLength(25));
      expect(screen.getByText('Página 1 de 2')).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Página anterior' })).not.toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Siguiente página' }));

      await waitFor(() =>
        expect(correosDeLaLista()).toEqual([
          'usuario-26@ejemplo.com',
          'usuario-27@ejemplo.com',
          'usuario-28@ejemplo.com',
          'usuario-29@ejemplo.com',
          'usuario-30@ejemplo.com',
        ]),
      );
      expect(screen.getByText('Página 2 de 2')).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Siguiente página' })).not.toBeOnTheScreen();
      expect(peticionesDeUsuarios()).toContain(`GET ${RUTA_DE_LOS_USUARIOS}?pagina=2`);

      await fireEvent.press(screen.getByRole('button', { name: 'Página anterior' }));
      await waitFor(() => expect(correosDeLaLista()).toHaveLength(25));
      expect(screen.getByText('Página 1 de 2')).toBeOnTheScreen();

      // Una búsqueda nueva empieza en la primera página.
      await fireEvent.press(screen.getByRole('button', { name: 'Siguiente página' }));
      await waitFor(() => expect(screen.getByText('Página 2 de 2')).toBeOnTheScreen());
      await fireEvent.changeText(screen.getByLabelText('Buscar por correo o nombre'), 'usuario-0');
      await waitFor(() => expect(correosDeLaLista()).toHaveLength(9));
      expect(screen.getByText('Página 1 de 1')).toBeOnTheScreen();
      expect(peticionesDeUsuarios()[peticionesDeUsuarios().length - 1]).toBe(
        `GET ${RUTA_DE_LOS_USUARIOS}?buscar=usuario-0`,
      );
    },
  );

  test('administracion-app-E24 — sin cuentas de usuario, la lista lo dice', async () => {
    agregarLosUsuarios(servidor(), []);

    await abrirLaLista('android');

    expect(await screen.findByText('No hay usuarios.')).toBeOnTheScreen();
    expect(screen.getByText('Página 1 de 1')).toBeOnTheScreen();
  });

  test('administracion-app-E24 — sin conexión, la lista lo dice y "Intentar de nuevo" la vuelve a pedir', async () => {
    agregarLosUsuarios(servidor());
    let sinConexion = true;
    servidor().interceptar = ({ ruta }) => {
      if (sinConexion && ruta === RUTA_DE_LOS_USUARIOS) {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await abrirLaLista('android');

    expect(
      await screen.findByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    sinConexion = false;
    await fireEvent.press(screen.getByRole('button', { name: 'Intentar de nuevo' }));
    await waitFor(() => expect(correosDeLaLista()).toHaveLength(3));
  });
});

describe('administracion-app-E24: la ficha del usuario con su historial', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E24 — (%s) la ficha trae su correo, su nombre, su teléfono, su estado y sus pedidos del más reciente al más antiguo, con folio, fecha, lavandería, estado y monto',
    async (plataforma) => {
      agregarLosUsuarios(servidor());

      await abrirLaFicha(plataforma, ANA.id_publico);

      expect(await screen.findByRole('header', { name: 'Ana Prueba' })).toBeOnTheScreen();
      expect(peticionesDeUsuarios()).toEqual([`GET ${rutaDeLaFicha(ANA.id_publico)}`]);
      expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Activo');
      expect(screen.getByTestId('dato-correo')).toHaveAccessibleName('Correo: ana@ejemplo.com');
      expect(screen.getByTestId('dato-telefono')).toHaveAccessibleName('Teléfono: 6180000101');
      expect(screen.getByRole('header', { name: 'Historial de pedidos' })).toBeOnTheScreen();

      expect(foliosDelHistorial()).toEqual(['7K2M9QXA', '3HT8W2QZ', 'M4P7R2KD']);
      const reciente = screen.getByTestId('pedido-7K2M9QXA');
      expect(within(reciente).getByText('Pedido 7K2M9QXA')).toBeOnTheScreen();
      expect(within(reciente).getByText('En proceso')).toBeOnTheScreen();
      expect(within(reciente).getByText('Lavandería de prueba Durango')).toBeOnTheScreen();
      // Sin monto todavía (DD-012, DD-010), lo dice como el panel, sin cifra.
      expect(within(reciente).getByText('24/11/2026 · Sin monto')).toBeOnTheScreen();
      // El monto en pesos, como el panel: con su separador de miles y sus dos decimales.
      expect(within(screen.getByTestId('pedido-3HT8W2QZ')).getByText('12/11/2026 · $1,180.50')).toBeOnTheScreen();
      const antiguo = screen.getByTestId('pedido-M4P7R2KD');
      expect(within(antiguo).getByText('Entregado')).toBeOnTheScreen();
      expect(within(antiguo).getByText('Lavandería de prueba Dos')).toBeOnTheScreen();
      expect(within(antiguo).getByText('02/11/2026 · $250.00')).toBeOnTheScreen();
      expect(reciente).toHaveAccessibleName(
        'Pedido 7K2M9QXA, En proceso, Lavandería de prueba Durango, 24/11/2026, Sin monto',
      );

      // Nada del pedido de la otra cuenta. La app pinta lo que responde la ficha: que una cuenta no reciba el historial
      // de otra lo prueba el servidor (A07: test_administracion_app_e24_la_ficha_trae_el_historial_del_usuario de
      // AdministracionEnLaApp/UsuariosTest.php, BarridoContraFugasTest.php y UsuarioAisladoTest.php), no esta prueba.
      expect(screen.queryByText(`Pedido ${BETO.historial[0].folio}`)).not.toBeOnTheScreen();
      expect(screen.queryByText('Lavandería de prueba Torreón')).not.toBeOnTheScreen();
      sinAltaEdicionNiBorrado();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E24 — (%s) una cuenta sin nombre, sin teléfono y sin pedidos lo dice, sin inventar nada',
    async (plataforma) => {
      agregarLosUsuarios(servidor());

      await abrirLaFicha(plataforma, SIN_DATOS.id_publico);

      expect(await screen.findByRole('header', { name: 'Sin nombre' })).toBeOnTheScreen();
      expect(screen.getByTestId('dato-correo')).toHaveAccessibleName('Correo: cuenta-nueva@ejemplo.com');
      expect(screen.getByTestId('dato-telefono')).toHaveAccessibleName('Teléfono: Sin teléfono');
      expect(screen.getByText('Sin pedidos.')).toBeOnTheScreen();
      expect(screen.queryAllByTestId(/^pedido-/)).toEqual([]);
    },
  );
});

describe('administracion-app-E25: bloquear a un usuario y levantar el bloqueo', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E25 — (%s) "Bloquear" con su confirmación pide POST …/bloqueo y la ficha queda con el estado nuevo y "Levantar el bloqueo"',
    async (plataforma) => {
      const usuarios = agregarLosUsuarios(servidor());
      await abrirLaFicha(plataforma, ANA.id_publico);
      await screen.findByRole('header', { name: 'Ana Prueba' });
      expect(screen.queryByRole('button', { name: 'Levantar el bloqueo' })).not.toBeOnTheScreen();
      // Lo que pasa con un pedido en curso al bloquear espera DD-016 (administracion-app-E27): la ficha lo marca.
      const enEspera = screen.getByTestId('bloqueo-con-pedido-en-curso');
      expect(within(enEspera).getByText('Un pedido en curso al bloquear')).toBeOnTheScreen();
      expect(within(enEspera).getByText('Por confirmar · DD-016')).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
      const { titulo, texto, botones } = ultimoDialogo();
      expect(titulo).toBe('Bloquear al usuario');
      // El texto del panel (AccionesDeBloqueo.php), sin el número de la DD: no fija qué pasa con un pedido en curso.
      expect(texto).toBe(
        'Se cierran sus sesiones abiertas y no podrá entrar a la app con su correo ni con Google hasta que se levante el bloqueo. Sus pedidos no cambian: qué pasa con uno en curso todavía no está definido.',
      );
      expect(botones.map(({ text, style }) => [text, style])).toEqual([
        ['Cancelar', 'cancel'],
        ['Bloquear', undefined],
      ]);
      await tocar(botones, 'Bloquear');

      await waitFor(() => expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Bloqueado'));
      expect(screen.getByRole('button', { name: 'Levantar el bloqueo' })).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Bloquear' })).not.toBeOnTheScreen();
      expect(screen.getByText('Al levantarlo, vuelve a estar activo y puede iniciar sesión otra vez.')).toBeOnTheScreen();
      expect(screen.queryByTestId('bloqueo-con-pedido-en-curso')).not.toBeOnTheScreen();
      expect(
        servidor()
          .peticiones()
          .filter(({ metodo }) => metodo === 'POST')
          .map(({ ruta, autorizacion }) => `${ruta} ${autorizacion}`),
      ).toEqual([`${rutaDeLaFicha(ANA.id_publico)}/bloqueo Bearer ${TOKEN_DE_LA_ADMINISTRACION}`]);
      expect(usuarios.cuenta(ANA.id_publico)?.estado).toBe('bloqueado');
      // Su historial sigue igual.
      expect(foliosDelHistorial()).toEqual(['7K2M9QXA', '3HT8W2QZ', 'M4P7R2KD']);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E25 — (%s) si no confirma el bloqueo, no se pide nada y la cuenta sigue activa',
    async (plataforma) => {
      const usuarios = agregarLosUsuarios(servidor());
      await abrirLaFicha(plataforma, ANA.id_publico);
      await screen.findByRole('header', { name: 'Ana Prueba' });

      await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
      await tocar(ultimoDialogo().botones, 'Cancelar');

      expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Activo');
      expect(screen.getByRole('button', { name: 'Bloquear' })).toBeOnTheScreen();
      expect(peticionesDeUsuarios()).toEqual([`GET ${rutaDeLaFicha(ANA.id_publico)}`]);
      expect(usuarios.cuenta(ANA.id_publico)?.estado).toBe('activo');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E25 — (%s) "Levantar el bloqueo" con su confirmación pide DELETE …/bloqueo y la ficha queda activa con "Bloquear"',
    async (plataforma) => {
      const usuarios = agregarLosUsuarios(servidor());
      await abrirLaFicha(plataforma, BETO.id_publico);
      await screen.findByRole('header', { name: 'Beto Prueba' });
      expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Bloqueado');
      expect(screen.queryByRole('button', { name: 'Bloquear' })).not.toBeOnTheScreen();
      expect(screen.getByText('Al levantarlo, vuelve a estar activo y puede iniciar sesión otra vez.')).toBeOnTheScreen();

      await fireEvent.press(screen.getByRole('button', { name: 'Levantar el bloqueo' }));
      const { titulo, texto, botones } = ultimoDialogo();
      expect(titulo).toBe('Levantar el bloqueo');
      expect(texto).toBe('El usuario vuelve a estar activo y puede iniciar sesión otra vez.');
      expect(botones.map(({ text }) => text)).toEqual(['Cancelar', 'Levantar el bloqueo']);
      await tocar(botones, 'Levantar el bloqueo');

      await waitFor(() => expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Activo'));
      expect(screen.getByRole('button', { name: 'Bloquear' })).toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Levantar el bloqueo' })).not.toBeOnTheScreen();
      expect(
        servidor()
          .peticiones()
          .filter(({ metodo }) => metodo === 'DELETE')
          .map(({ ruta, autorizacion }) => `${ruta} ${autorizacion}`),
      ).toEqual([`${rutaDeLaFicha(BETO.id_publico)}/bloqueo Bearer ${TOKEN_DE_LA_ADMINISTRACION}`]);
      expect(usuarios.cuenta(BETO.id_publico)?.estado).toBe('activo');
    },
  );

  test('administracion-app-E25 — al volver de la ficha, la lista ya trae el estado nuevo', async () => {
    agregarLosUsuarios(servidor());
    await abrirLaLista('android');
    await fireEvent.press(await screen.findByTestId(`usuario-${ANA.id_publico}`));
    await screen.findByRole('header', { name: 'Ana Prueba' });

    await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
    await tocar(ultimoDialogo().botones, 'Bloquear');
    await waitFor(() => expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Bloqueado'));
    await fireEvent.press(screen.getByRole('button', { name: 'Regresar' }));

    await waitFor(() => expect(screen).toHavePathname(RUTA_DE_LOS_USUARIOS));
    await waitFor(() =>
      expect(within(screen.getByTestId(`usuario-${ANA.id_publico}`)).getByText('Bloqueado')).toBeOnTheScreen(),
    );
  });

  test('administracion-app-E25 — mientras se manda el bloqueo, el botón se bloquea: un doble toque manda un solo POST', async () => {
    const usuarios = agregarLosUsuarios(servidor());
    await abrirLaFicha('ios', ANA.id_publico);
    await screen.findByRole('header', { name: 'Ana Prueba' });
    // El servidor se queda con el bloqueo hasta que la prueba lo suelte; entonces lo contesta como siempre.
    let soltar: () => void = () => {};
    servidor().interceptar = (peticion) => {
      if (peticion.metodo !== 'POST') {
        return undefined;
      }
      return new Promise((resolver) => {
        soltar = () => resolver(usuarios.contestar(peticion));
      });
    };

    await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
    const bloquear = ultimoDialogo().botones.find(({ text }) => text === 'Bloquear');
    await act(async () => {
      bloquear?.onPress?.();
      bloquear?.onPress?.();
    });

    const posts = () => servidor().peticiones().filter(({ metodo }) => metodo === 'POST');
    expect(posts()).toHaveLength(1);
    expect(screen.getByRole('button', { name: 'Bloquear' })).toBeDisabled();
    // Tocarlo otra vez no abre otra confirmación.
    const dialogosAbiertos = dialogo.mock.calls.length;
    await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
    expect(dialogo.mock.calls).toHaveLength(dialogosAbiertos);

    await act(async () => {
      soltar();
    });
    await waitFor(() => expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Bloqueado'));
    expect(screen.getByRole('button', { name: 'Levantar el bloqueo' })).toBeEnabled();
    expect(posts()).toHaveLength(1);
    expect(usuarios.cuenta(ANA.id_publico)?.estado).toBe('bloqueado');
  });

  test('administracion-app-E25 — sin conexión al bloquear, lo dice y la ficha sigue como estaba', async () => {
    const usuarios = agregarLosUsuarios(servidor());
    await abrirLaFicha('android', ANA.id_publico);
    await screen.findByRole('header', { name: 'Ana Prueba' });
    servidor().interceptar = ({ metodo }) => {
      if (metodo === 'POST') {
        throw new TypeError('Network request failed');
      }
      return undefined;
    };

    await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
    await tocar(ultimoDialogo().botones, 'Bloquear');

    expect(
      await screen.findByText('No se pudo conectar con el servidor. Revisa tu conexión e intenta de nuevo.'),
    ).toBeOnTheScreen();
    expect(screen.getByTestId('estado-del-usuario')).toHaveTextContent('Activo');
    expect(screen.getByRole('button', { name: 'Bloquear' })).toBeOnTheScreen();
    expect(usuarios.cuenta(ANA.id_publico)?.estado).toBe('activo');
  });
});

describe('administracion-app-E12: un usuario que no existe', () => {
  test.each(PLATAFORMAS)(
    'administracion-app-E12 — (%s) la ficha de una cuenta que no existe muestra el mensaje del 404, sin ningún dato ni botón',
    async (plataforma) => {
      agregarLosUsuarios(servidor());
      const inexistente = '01jd8x6m3v2c9q4w5e7r8t0zzz';

      await abrirLaFicha(plataforma, inexistente);

      expect(await screen.findByText(MENSAJE_DE_NO_EXISTE)).toBeOnTheScreen();
      expect(peticionesDeUsuarios()).toEqual([`GET ${rutaDeLaFicha(inexistente)}`]);
      expect(screen.queryByTestId('estado-del-usuario')).not.toBeOnTheScreen();
      expect(screen.queryByRole('header', { name: 'Historial de pedidos' })).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Bloquear' })).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Levantar el bloqueo' })).not.toBeOnTheScreen();
      expect(screen.queryByRole('button', { name: 'Intentar de nuevo' })).not.toBeOnTheScreen();
    },
  );

  test('administracion-app-E12 — si la cuenta ya no existe al bloquearla, la ficha muestra el mensaje del 404 y ya no ofrece nada', async () => {
    const usuarios = agregarLosUsuarios(servidor());
    await abrirLaFicha('android', ANA.id_publico);
    await screen.findByRole('header', { name: 'Ana Prueba' });
    // Mientras tanto, la cuenta deja de estar.
    usuarios.usuarios.splice(usuarios.usuarios.indexOf(usuarios.cuenta(ANA.id_publico)!), 1);

    await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
    await tocar(ultimoDialogo().botones, 'Bloquear');

    expect(await screen.findByText(MENSAJE_DE_NO_EXISTE)).toBeOnTheScreen();
    expect(screen.queryByTestId('estado-del-usuario')).not.toBeOnTheScreen();
    expect(screen.queryByRole('button', { name: 'Bloquear' })).not.toBeOnTheScreen();
  });
});

describe('administracion-app-E05: un 401 en la gestión de usuarios vuelve al inicio de sesión', () => {
  /** Desde ahora, esa ruta responde 401, como con el token vencido o revocado. */
  function vencerEn(ruta: string) {
    servidor().interceptar = (peticion) =>
      peticion.ruta === ruta ? respuesta(401, { message: 'Unauthenticated.' }) : undefined;
  }

  async function esperarElInicioDeSesion() {
    await waitFor(() => expect(screen).toHavePathname('/inicio-de-sesion'));
    expect(await screen.findByLabelText('Contraseña')).toBeOnTheScreen();
    await expect(leerToken()).resolves.toBeNull();
    await expect(leerRol()).resolves.toBeNull();
  }

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 de la lista, la app borra la sesión y abre el inicio de sesión',
    async (plataforma) => {
      agregarLosUsuarios(servidor());
      vencerEn(RUTA_DE_LOS_USUARIOS);

      await abrirLaAppConLaAdministracion(plataforma, RUTA_DE_LOS_USUARIOS);

      await esperarElInicioDeSesion();
      expect(peticionesDeUsuarios()).toEqual([`GET ${RUTA_DE_LOS_USUARIOS}`]);
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 de la ficha, la app borra la sesión y abre el inicio de sesión',
    async (plataforma) => {
      agregarLosUsuarios(servidor());
      vencerEn(rutaDeLaFicha(ANA.id_publico));

      await abrirLaAppConLaAdministracion(plataforma, rutaDeLaFicha(ANA.id_publico));

      await esperarElInicioDeSesion();
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 al bloquear, la app borra la sesión y abre el inicio de sesión',
    async (plataforma) => {
      const usuarios = agregarLosUsuarios(servidor());
      await abrirLaFicha(plataforma, ANA.id_publico);
      await screen.findByRole('header', { name: 'Ana Prueba' });
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('button', { name: 'Bloquear' }));
      await tocar(ultimoDialogo().botones, 'Bloquear');

      await esperarElInicioDeSesion();
      expect(usuarios.cuenta(ANA.id_publico)?.estado).toBe('activo');
    },
  );

  test.each(PLATAFORMAS)(
    'administracion-app-E05 — (%s) con el 401 al levantar el bloqueo, la app borra la sesión y abre el inicio de sesión',
    async (plataforma) => {
      const usuarios = agregarLosUsuarios(servidor());
      await abrirLaFicha(plataforma, BETO.id_publico);
      await screen.findByRole('header', { name: 'Beto Prueba' });
      servidor().vencerLaSesion();

      await fireEvent.press(screen.getByRole('button', { name: 'Levantar el bloqueo' }));
      await tocar(ultimoDialogo().botones, 'Levantar el bloqueo');

      await esperarElInicioDeSesion();
      expect(peticionesDeUsuarios()).toEqual([
        `GET ${rutaDeLaFicha(BETO.id_publico)}`,
        `DELETE ${rutaDeLaFicha(BETO.id_publico)}/bloqueo`,
      ]);
      expect(usuarios.cuenta(BETO.id_publico)?.estado).toBe('bloqueado');
    },
  );
});
