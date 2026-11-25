// Spec: openspec/changes/repartidor/specs/repartidor/spec.md, repartidor-E08 (tarea 2.8). "Y el repartidor lo ve en su
// módulo": los datos del repartidor que edita la administración en el panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección
// A.3, Módulo D #2, "edición"; :18, Módulo C #1: nombre, teléfono, correo, foto de perfil y tipo de vehículo) se ven en
// la cabecera de Pedidos asignados, con el perfil que el módulo ya lee al entrar (GET /api/repartidor/perfil). Lo
// decidió el concilio del 2026-11-24, por delegación de Arthur (C-245; acta-2026-11-20-modelo-y-pasarela.md, decisión
// 55): sin pantalla, ruta ni petición nuevas, y el repartidor no edita sus datos.
//
// Se abre la app real con la sesión de una cuenta de repartidor activo, en iPhone y en Android, con el servidor simulado
// (__tests__/apoyo/repartidor.ts), que deja cambiar el perfil como lo cambia la administración. Volver a entrar al
// módulo es cerrar la app y abrirla de nuevo: el módulo vuelve a pedir el perfil.
import { screen, within } from 'expo-router/testing-library';

import { color, radio } from '../../src/tema/theme';
import {
  abrirLaAppConSesion,
  DATOS_DEL_REPARTIDOR,
  PLATAFORMAS,
  PRIMERO,
  RUTA_DE_LOS_PEDIDOS,
  usarElServidorDelRepartidor,
  type Plataforma,
} from '../apoyo/repartidor';

// La primera pantalla tarda más mientras Jest prepara el entorno.
jest.setTimeout(60_000);

const servidor = usarElServidorDelRepartidor('modulo-c-perfil');

/** Lo que pide el módulo al entrar a sus pedidos: la cuenta, el perfil y la lista. Nada más. */
const PETICIONES_DEL_MODULO = ['GET /cuenta', 'GET /repartidor/perfil', 'GET /repartidor/pedidos'];

/** La cabecera con sus datos, en Pedidos asignados. */
async function abrirLosPedidos(plataforma: Plataforma) {
  await abrirLaAppConSesion(plataforma, RUTA_DE_LOS_PEDIDOS);
  await screen.findByRole('header', { name: 'Pedidos asignados' });
  return within(await screen.findByTestId('datos-del-repartidor'));
}

function peticionesHechas(): string[] {
  return [...new Set(servidor().peticiones().map(({ metodo, ruta }) => `${metodo} ${ruta}`))];
}

describe('repartidor-E08: el repartidor ve en su módulo los datos que le editó la administración', () => {
  test.each(PLATAFORMAS)(
    'repartidor-E08 — (%s) Pedidos asignados muestra arriba el nombre, la foto, el teléfono, el correo y el tipo de vehículo de su perfil',
    async (plataforma) => {
      servidor().ofrecer(PRIMERO);

      const cabecera = await abrirLosPedidos(plataforma);

      expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.nombre)).toBeOnTheScreen();
      // La foto, desde su URL firmada.
      expect(cabecera.getByRole('image', { name: 'Tu foto de perfil' })).toHaveProp('source', {
        uri: DATOS_DEL_REPARTIDOR.foto.url,
      });
      // Cada dato con lo que es, en un solo anuncio.
      expect(cabecera.getByLabelText(`Teléfono: ${DATOS_DEL_REPARTIDOR.telefono}`)).toBeOnTheScreen();
      expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.telefono)).toBeOnTheScreen();
      expect(cabecera.getByLabelText(`Correo electrónico: ${DATOS_DEL_REPARTIDOR.correo}`)).toBeOnTheScreen();
      expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.correo)).toBeOnTheScreen();
      expect(cabecera.getByLabelText(`Tipo de vehículo: ${DATOS_DEL_REPARTIDOR.tipo_vehiculo}`)).toBeOnTheScreen();
      expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.tipo_vehiculo)).toBeOnTheScreen();

      // Arriba: bajo el título, antes de las entradas y de sus pedidos.
      const enOrden = screen
        .getAllByText(/^(Pedidos asignados|Rubén Repartidor|Historial de entregas|Pedido 7KQ2M9XA)$/)
        .map((texto) => texto.props.children as string);
      expect(enOrden).toEqual(['Pedidos asignados', 'Rubén Repartidor', 'Historial de entregas', 'Pedido 7KQ2M9XA']);

      // Lo trae el perfil que el módulo ya lee al entrar: ni una petición nueva.
      expect(servidor().consultasDelPerfil()).toBe(1);
      expect(peticionesHechas().filter((peticion) => !PETICIONES_DEL_MODULO.includes(peticion))).toEqual([]);
    },
  );

  test.each(PLATAFORMAS)(
    'repartidor-E08 — (%s) con el perfil cambiado por la administración, al volver a entrar al módulo muestra los datos nuevos',
    async (plataforma) => {
      const antes = await abrirLosPedidos(plataforma);
      expect(antes.getByText(DATOS_DEL_REPARTIDOR.nombre)).toBeOnTheScreen();

      // La administración cambia sus cinco datos en el panel.
      const fotoNueva = { url: 'https://fotos.prueba.invalid/repartidor-nueva.jpg?firma=3', vence_en: '2026-12-03T20:00:00Z' };
      servidor().editarElPerfil({
        nombre: 'Rubén Editado',
        telefono: '871 765 4321',
        correo: 'ruben.nuevo@ejemplo.com',
        tipo_vehiculo: 'Bicicleta',
        foto: fotoNueva,
      });
      await screen.unmount();

      const despues = await abrirLosPedidos(plataforma);

      expect(despues.getByText('Rubén Editado')).toBeOnTheScreen();
      expect(despues.getByText('871 765 4321')).toBeOnTheScreen();
      expect(despues.getByText('ruben.nuevo@ejemplo.com')).toBeOnTheScreen();
      expect(despues.getByText('Bicicleta')).toBeOnTheScreen();
      expect(despues.getByRole('image', { name: 'Tu foto de perfil' })).toHaveProp('source', { uri: fotoNueva.url });
      for (const anterior of [
        DATOS_DEL_REPARTIDOR.nombre,
        DATOS_DEL_REPARTIDOR.telefono,
        DATOS_DEL_REPARTIDOR.correo,
        DATOS_DEL_REPARTIDOR.tipo_vehiculo,
      ]) {
        expect(screen.queryByText(anterior)).not.toBeOnTheScreen();
      }
      // Una consulta del perfil por cada entrada al módulo.
      expect(servidor().consultasDelPerfil()).toBe(2);
    },
  );

  test('repartidor-E08 — sin foto de perfil, la cabecera no pone nada en su lugar y muestra los otros cuatro datos', async () => {
    servidor().editarElPerfil({ foto: null });

    const cabecera = await abrirLosPedidos('android');

    expect(cabecera.queryByRole('image')).not.toBeOnTheScreen();
    expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.nombre)).toBeOnTheScreen();
    expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.telefono)).toBeOnTheScreen();
    expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.correo)).toBeOnTheScreen();
    expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.tipo_vehiculo)).toBeOnTheScreen();
  });

  test('repartidor-E08 — la cabecera usa los tokens del design system: la foto en círculo y cada etiqueta en el tono suave', async () => {
    const cabecera = await abrirLosPedidos('ios');

    expect(cabecera.getByRole('image', { name: 'Tu foto de perfil' })).toHaveStyle({ borderRadius: radio.pildora });
    expect(cabecera.getByText(DATOS_DEL_REPARTIDOR.nombre)).toHaveStyle({ color: color.semantico.texto });
    for (const etiqueta of ['Teléfono', 'Correo electrónico', 'Tipo de vehículo']) {
      expect(cabecera.getByText(etiqueta)).toHaveStyle({ color: color.semantico.texto_suave });
    }
  });

  test('repartidor-E08 — el repartidor no edita sus datos: la cabecera no ofrece cambiarlos', async () => {
    const cabecera = await abrirLosPedidos('android');

    expect(cabecera.queryByRole('button')).not.toBeOnTheScreen();
    expect(cabecera.queryByRole('link')).not.toBeOnTheScreen();
    expect(screen.queryByDisplayValue(DATOS_DEL_REPARTIDOR.nombre)).not.toBeOnTheScreen();
  });
});
