// Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md, registro-E04 y registro-E09.
// Las piezas de formulario de las pantallas de entrada, en src/piezas/ junto a las de la entrada: el campo con su
// error, el botón (el de src/piezas, con su estado ocupado) y el enlace. Sus colores y medidas salen de theme.ts
// (ADR-0013); el área táctil mínima, de los tokens de accesibilidad.
import { fireEvent, render, screen, userEvent, within } from '@testing-library/react-native';
import { StyleSheet } from 'react-native';

import { Boton } from '../../src/piezas/Boton';
import { CampoDeTexto } from '../../src/piezas/CampoDeTexto';
import { Enlace } from '../../src/piezas/Enlace';
import { tema } from '../../src/tema/theme';

// La primera escritura con userEvent tarda más mientras Jest prepara el entorno.
jest.setTimeout(30_000);

const AREA_TACTIL = tema.accesibilidad.area_tactil_minima_dp;

function estiloPlano(elemento: { props: { style?: unknown } }) {
  return StyleSheet.flatten(elemento.props.style as never) ?? {};
}

describe('registro-E04: el campo muestra su error junto a él', () => {
  test('registro-E04 — el campo se encuentra por su etiqueta y recibe lo que se escribe', async () => {
    const alCambiar = jest.fn();
    const usuario = userEvent.setup();
    await render(<CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={alCambiar} />);

    await usuario.type(screen.getByLabelText('Correo electrónico'), 'a');

    expect(alCambiar).toHaveBeenCalledWith('a');
  });

  test('registro-E04 — el error se muestra dentro del campo al que pertenece, en el color de error', async () => {
    await render(
      <>
        <CampoDeTexto
          nombre="correo"
          etiqueta="Correo electrónico"
          valor=""
          alCambiar={() => {}}
          error="El correo ya tiene cuenta."
        />
        <CampoDeTexto nombre="password" etiqueta="Contraseña" valor="" alCambiar={() => {}} tipo="contrasena" />
      </>,
    );

    const error = within(screen.getByTestId('campo-correo')).getByText('El correo ya tiene cuenta.');
    expect(error).toBeOnTheScreen();
    expect(error).toHaveStyle({ color: tema.color.estado.error });
    expect(within(screen.getByTestId('campo-password')).queryByText('El correo ya tiene cuenta.')).not.toBeOnTheScreen();
  });

  test('registro-E04 — sin error no se muestra ningún mensaje y el borde es el de control', async () => {
    await render(<CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} />);

    const entrada = screen.getByLabelText('Correo electrónico');
    expect(estiloPlano(entrada).borderColor).toBe(tema.color.semantico.borde_fuerte);
    expect(estiloPlano(entrada).minHeight).toBeGreaterThanOrEqual(AREA_TACTIL);
  });

  test('registro-E04 — con error, el borde del campo toma el color de error', async () => {
    await render(
      <CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} error="Inválido." />,
    );

    expect(estiloPlano(screen.getByLabelText('Correo electrónico')).borderColor).toBe(tema.color.estado.error);
  });

  // "Foco visible siempre", con el token foco (vault/design-system/componentes.md, reglas transversales).
  test('registro-E04 — con el foco, el borde toma el color de foco y es más grueso; al salir, vuelve al de control', async () => {
    await render(<CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} />);
    const sinFoco = estiloPlano(screen.getByLabelText('Correo electrónico'));

    await fireEvent(screen.getByLabelText('Correo electrónico'), 'focus');

    const conFoco = estiloPlano(screen.getByLabelText('Correo electrónico'));
    expect(conFoco.borderColor).toBe(tema.color.semantico.foco);
    expect(conFoco.borderWidth).toBeGreaterThan(sinFoco.borderWidth as number);

    await fireEvent(screen.getByLabelText('Correo electrónico'), 'blur');

    expect(estiloPlano(screen.getByLabelText('Correo electrónico'))).toMatchObject({
      borderColor: tema.color.semantico.borde_fuerte,
      borderWidth: sinFoco.borderWidth,
    });
  });

  test('registro-E04 — con error, mientras tiene el foco se ve el foco, y al salir vuelve el color de error', async () => {
    await render(
      <CampoDeTexto nombre="correo" etiqueta="Correo electrónico" valor="" alCambiar={() => {}} error="Inválido." />,
    );

    await fireEvent(screen.getByLabelText('Correo electrónico'), 'focus');
    expect(estiloPlano(screen.getByLabelText('Correo electrónico')).borderColor).toBe(tema.color.semantico.foco);

    await fireEvent(screen.getByLabelText('Correo electrónico'), 'blur');
    expect(estiloPlano(screen.getByLabelText('Correo electrónico')).borderColor).toBe(tema.color.estado.error);
  });

  test('registro-E04 — el campo secreto oculta lo que se escribe', async () => {
    await render(<CampoDeTexto nombre="password" etiqueta="Contraseña" valor="x" alCambiar={() => {}} tipo="contrasena" />);

    expect(screen.getByLabelText('Contraseña')).toHaveProp('secureTextEntry', true);
  });
});

describe('registro-E04: el botón que envía el formulario', () => {
  test('registro-E04 — se toca por su texto, con el color de acción y el área táctil mínima', async () => {
    const alTocar = jest.fn();
    const usuario = userEvent.setup();
    await render(<Boton onPress={alTocar}>Crear cuenta</Boton>);

    const boton = screen.getByRole('button', { name: 'Crear cuenta' });
    await usuario.press(boton);

    expect(alTocar).toHaveBeenCalledTimes(1);
    expect(estiloPlano(boton).backgroundColor).toBe(tema.color.semantico.accion);
    expect(estiloPlano(boton).minHeight).toBeGreaterThanOrEqual(AREA_TACTIL);
    expect(screen.getByText('Crear cuenta')).toHaveStyle({ color: tema.color.semantico.accion_texto });
  });

  test('registro-E04 — mientras se envía, el botón está ocupado y no se puede volver a tocar', async () => {
    const alTocar = jest.fn();
    const usuario = userEvent.setup();
    await render(
      <Boton onPress={alTocar} ocupado>
        Crear cuenta
      </Boton>,
    );

    const boton = screen.getByRole('button', { name: 'Crear cuenta' });
    await usuario.press(boton);

    expect(boton).toBeBusy();
    expect(boton).toBeDisabled();
    expect(alTocar).not.toHaveBeenCalled();
  });

  test('registro-E04 — sin enviar, el botón no está ocupado', async () => {
    await render(<Boton onPress={() => {}}>Crear cuenta</Boton>);

    const boton = screen.getByRole('button', { name: 'Crear cuenta' });
    expect(boton).not.toBeBusy();
    expect(boton).toBeEnabled();
  });
});

describe('registro-E09: el enlace entre pantallas', () => {
  test('registro-E09 — se toca por su texto, con el color de acción y el área táctil mínima', async () => {
    const alTocar = jest.fn();
    const usuario = userEvent.setup();
    await render(<Enlace texto="¿Olvidaste tu contraseña?" alTocar={alTocar} />);

    const enlace = screen.getByRole('link', { name: '¿Olvidaste tu contraseña?' });
    await usuario.press(enlace);

    expect(alTocar).toHaveBeenCalledTimes(1);
    expect(estiloPlano(enlace).minHeight).toBeGreaterThanOrEqual(AREA_TACTIL);
    expect(screen.getByText('¿Olvidaste tu contraseña?')).toHaveStyle({ color: tema.color.semantico.accion });
  });
});
