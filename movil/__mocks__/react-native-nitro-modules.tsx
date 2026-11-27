// Simulación de react-native-nitro-modules para Jest: en las pruebas no hay código nativo.
// Las librerías de Nitro, como react-native-nitro-google-signin, corren su JavaScript real sobre estos objetos falsos.
// Cada objeto híbrido es el mismo para un mismo nombre, y cada método es un jest.fn que la prueba programa:
//   const google = NitroModules.createHybridObject('NitroGoogleSignin');
//   google.signIn.mockResolvedValue({ type: 'cancelled', data: null });
import { View, type ViewProps } from 'react-native';

type ObjetoHibrido = Record<string, jest.Mock>;

const objetos = new Map<string, ObjetoHibrido>();

function objetoHibrido(nombre: string): ObjetoHibrido {
  let objeto = objetos.get(nombre);
  if (!objeto) {
    const metodos: ObjetoHibrido = {};
    // Un Proxy da un jest.fn por cada método que se pida, sin listar los de cada librería.
    objeto = new Proxy(metodos, {
      get(destino, propiedad: string) {
        if (!(propiedad in destino)) {
          destino[propiedad] = jest.fn();
        }
        return destino[propiedad];
      },
    });
    objetos.set(nombre, objeto);
  }
  return objeto;
}

export const NitroModules = {
  createHybridObject: (nombre: string) => objetoHibrido(nombre),
  hasHybridObject: () => true,
};

/** Las vistas nativas de Nitro se dibujan como una View. En React 19, ref llega como una propiedad más. */
export function getHostComponent(nombre: string) {
  function Componente(propiedades: ViewProps) {
    return <View {...propiedades} testID={propiedades.testID ?? nombre} />;
  }
  Componente.displayName = nombre;
  return Componente;
}

export function callback<T>(funcion: T): T {
  return funcion;
}

/**
 * Borra lo programado en los métodos de todos los objetos híbridos. Para usar en beforeEach. Los objetos se conservan:
 * las librerías los guardan al cargarse.
 */
export function reiniciarObjetosHibridos(): void {
  for (const objeto of objetos.values()) {
    for (const metodo of Object.values(objeto)) {
      metodo.mockReset();
    }
  }
}
