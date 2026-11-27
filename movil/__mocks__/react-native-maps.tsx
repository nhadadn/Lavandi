// Simulación de react-native-maps para Jest: en las pruebas no hay mapa nativo, ni MapKit ni Google Maps. El mapa y sus
// pines se dibujan como View con las mismas propiedades, para que la prueba lea qué recibió cada uno: el proveedor, la
// región inicial y la ubicación de cada pin. Tocar un pin llama a su onPress. Jest la usa en todas las pruebas porque
// está en __mocks__/ junto a node_modules. Que cada plataforma muestre su mapa (Apple en iPhone, Google en Android) se
// prueba en un teléfono real (ADR-0009).
//
// Para los mapas de la administración (ADR-0022, decisión 13): el círculo y el polígono del mapa de calor se dibujan
// igual, como View con sus propiedades (el centro, el radio, los vértices y los colores), y el mapa acepta una ref con
// fitToCoordinates y animateToRegion, el encuadre del monitoreo. Son funciones simuladas que la prueba lee en
// __referenciaDelMapa, las mismas para todos los mapas, y que __reiniciar() deja sin llamadas.
import { useImperativeHandle, type Ref } from 'react';
import { View, type ViewProps } from 'react-native';

type Propiedades = ViewProps & Record<string, unknown>;

/** Lo que la app puede pedirle al mapa por su ref. */
export interface ReferenciaDelMapa {
  fitToCoordinates: jest.Mock;
  animateToRegion: jest.Mock;
}

/** Solo para las pruebas: lo que la app le pidió al mapa por su ref. */
export const __referenciaDelMapa: ReferenciaDelMapa = {
  fitToCoordinates: jest.fn(),
  animateToRegion: jest.fn(),
};

/** Solo para las pruebas: la ref del mapa sin llamadas guardadas. */
export function __reiniciar(): void {
  __referenciaDelMapa.fitToCoordinates.mockClear();
  __referenciaDelMapa.animateToRegion.mockClear();
}

function MapView({ ref, ...propiedades }: Propiedades & { ref?: Ref<ReferenciaDelMapa> }) {
  useImperativeHandle(ref, () => __referenciaDelMapa, []);
  return <View {...propiedades} />;
}

function Marker(propiedades: Propiedades) {
  return <View {...propiedades} />;
}

function Circle(propiedades: Propiedades) {
  return <View {...propiedades} />;
}

function Polygon(propiedades: Propiedades) {
  return <View {...propiedades} />;
}

// Los valores de la librería: sin proveedor (undefined), cada plataforma usa el suyo.
export const PROVIDER_DEFAULT = undefined;
export const PROVIDER_GOOGLE = 'google';

export { Circle, MapView, Marker, Polygon };
export default MapView;
