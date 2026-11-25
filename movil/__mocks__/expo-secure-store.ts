// Simulación de expo-secure-store para las pruebas: guarda en memoria lo que en el teléfono va al almacenamiento
// cifrado del sistema (Keychain en iPhone, Keystore en Android; ADR-0018). Jest la usa en todas las pruebas porque está
// en __mocks__/ junto a node_modules. Lo que se guarda de verdad en un teléfono se prueba en un teléfono real.
const datos = new Map<string, string>();

export const getItemAsync = jest.fn(async (clave: string): Promise<string | null> => datos.get(clave) ?? null);

export const setItemAsync = jest.fn(async (clave: string, valor: string): Promise<void> => {
  datos.set(clave, valor);
});

export const deleteItemAsync = jest.fn(async (clave: string): Promise<void> => {
  datos.delete(clave);
});

export const isAvailableAsync = jest.fn(async (): Promise<boolean> => true);

/** Solo para las pruebas: deja el almacén vacío, como en una app recién instalada. */
export function __vaciar(): void {
  datos.clear();
}
