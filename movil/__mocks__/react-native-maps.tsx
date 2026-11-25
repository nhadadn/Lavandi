// Simulación de react-native-maps para Jest: en las pruebas no hay mapa nativo, ni MapKit ni Google Maps. El mapa y sus
// pines se dibujan como View con las mismas propiedades, para que la prueba lea qué recibió cada uno: el proveedor, la
// región inicial y la ubicación de cada pin. Tocar un pin llama a su onPress. Jest la usa en todas las pruebas porque
// está en __mocks__/ junto a node_modules. Que cada plataforma muestre su mapa (Apple en iPhone, Google en Android) se
// prueba en un teléfono real (ADR-0009).
import { View, type ViewProps } from 'react-native';

type Propiedades = ViewProps & Record<string, unknown>;

function MapView(propiedades: Propiedades) {
  return <View {...propiedades} />;
}

function Marker(propiedades: Propiedades) {
  return <View {...propiedades} />;
}

// Los valores de la librería: sin proveedor (undefined), cada plataforma usa el suyo.
export const PROVIDER_DEFAULT = undefined;
export const PROVIDER_GOOGLE = 'google';

export { MapView, Marker };
export default MapView;
