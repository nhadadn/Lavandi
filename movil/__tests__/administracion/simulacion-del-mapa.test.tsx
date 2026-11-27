// La simulación de react-native-maps (__mocks__/react-native-maps.tsx) para los mapas de la administración (ADR-0022,
// decisión 13): el círculo y el polígono del mapa de calor (administracion-app-E50) y la ref del mapa con el encuadre del
// monitoreo (administracion-app-E28). Es la base de las pruebas de esas pantallas, que la usan sin editarla: aquí se
// comprueba que dibuja lo que recibe, que la ref expone sus dos funciones y que __reiniciar() las deja sin llamadas.
// Que cada plataforma muestre su mapa se prueba en un teléfono real (ADR-0009).
import { render, screen } from '@testing-library/react-native';
import { createRef } from 'react';
import * as Mapas from 'react-native-maps';

import type { ReferenciaDelMapa } from '../../__mocks__/react-native-maps';

// La simulación, la misma instancia que importaría la app.
const simulacion = Mapas as unknown as typeof import('../../__mocks__/react-native-maps');
const { Circle, MapView, Polygon } = simulacion;

const REGION = { latitude: 24.0277, longitude: -104.6532, latitudeDelta: 0.1, longitudeDelta: 0.1 };
const VERTICES = [
  { latitude: 24.02, longitude: -104.66 },
  { latitude: 24.03, longitude: -104.65 },
  { latitude: 24.01, longitude: -104.64 },
];

beforeEach(() => {
  simulacion.__reiniciar();
});

test('administracion-app-E50 (base) — el círculo y el polígono se dibujan con sus propiedades', async () => {
  await render(
    <MapView testID="mapa" initialRegion={REGION}>
      <Circle
        testID="circulo"
        center={{ latitude: 24.0277, longitude: -104.6532 }}
        radius={300}
        fillColor="rgba(0, 0, 0, 0.4)"
      />
      <Polygon testID="poligono" coordinates={VERTICES} fillColor="rgba(0, 0, 0, 0.2)" strokeColor="#000000" />
    </MapView>,
  );

  expect(screen.getByTestId('mapa').props.initialRegion).toEqual(REGION);
  expect(screen.getByTestId('circulo').props).toMatchObject({
    center: { latitude: 24.0277, longitude: -104.6532 },
    radius: 300,
    fillColor: 'rgba(0, 0, 0, 0.4)',
  });
  expect(screen.getByTestId('poligono').props).toMatchObject({
    coordinates: VERTICES,
    fillColor: 'rgba(0, 0, 0, 0.2)',
    strokeColor: '#000000',
  });
});

test('administracion-app-E28 (base) — la ref del mapa expone fitToCoordinates y animateToRegion, y la prueba lee sus llamadas', async () => {
  const ref = createRef<ReferenciaDelMapa>();
  await render(<MapView ref={ref} />);

  ref.current?.fitToCoordinates(VERTICES, { animated: false });
  ref.current?.animateToRegion(REGION);

  expect(simulacion.__referenciaDelMapa.fitToCoordinates).toHaveBeenCalledWith(VERTICES, { animated: false });
  expect(simulacion.__referenciaDelMapa.animateToRegion).toHaveBeenCalledWith(REGION);
});

test('administracion-app-E28 (base) — __reiniciar() deja la ref sin llamadas', async () => {
  const ref = createRef<ReferenciaDelMapa>();
  await render(<MapView ref={ref} />);
  ref.current?.fitToCoordinates(VERTICES);
  ref.current?.animateToRegion(REGION);

  simulacion.__reiniciar();

  expect(simulacion.__referenciaDelMapa.fitToCoordinates).not.toHaveBeenCalled();
  expect(simulacion.__referenciaDelMapa.animateToRegion).not.toHaveBeenCalled();
  // La misma ref sigue respondiendo después.
  ref.current?.animateToRegion(REGION);
  expect(simulacion.__referenciaDelMapa.animateToRegion).toHaveBeenCalledTimes(1);
});
