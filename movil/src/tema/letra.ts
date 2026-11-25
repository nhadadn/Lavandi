// La letra de la marca en la app (adenda del 2026-11-21 de ADR-0013; DD-131, punto 1): las familias de tokens.json,
// Nunito para titulares y Source Sans 3 para cuerpo (vault/marca/decision-visual.md), se cargan con expo-font desde los
// paquetes de @expo-google-fonts, con licencia SIL Open Font License.
//
// - Se carga cada peso de tokens.json de las dos familias, cada uno con su nombre: "Nunito_700", "SourceSans3_400".
// - Las piezas piden su letra con letra(uso, peso) al dibujarse. Si esa letra cargó, el estilo la nombra sin pedir además
//   el peso: el archivo ya es el de ese peso, y pedirlo otra vez hace que Android lo engrose de más. Si no cargó, la
//   letra del sistema con el peso de tokens.json.
// - La raíz no muestra la primera pantalla hasta que la carga termina, bien o mal (useLetraLista).
// - La monoespaciada de tokens.json empieza con ui-monospace, que React Native no conoce: en la app es la del sistema.
import { Nunito_400Regular } from '@expo-google-fonts/nunito/400Regular';
import { Nunito_500Medium } from '@expo-google-fonts/nunito/500Medium';
import { Nunito_600SemiBold } from '@expo-google-fonts/nunito/600SemiBold';
import { Nunito_700Bold } from '@expo-google-fonts/nunito/700Bold';
import { SourceSans3_400Regular } from '@expo-google-fonts/source-sans-3/400Regular';
import { SourceSans3_500Medium } from '@expo-google-fonts/source-sans-3/500Medium';
import { SourceSans3_600SemiBold } from '@expo-google-fonts/source-sans-3/600SemiBold';
import { SourceSans3_700Bold } from '@expo-google-fonts/source-sans-3/700Bold';
import { isLoaded, useFonts } from 'expo-font';
import { Platform, type TextStyle } from 'react-native';

import { tipografia } from './theme';

type Peso = keyof typeof tipografia.peso;
type Uso = 'titulares' | 'cuerpo' | 'monoespaciada';

// Los archivos de cada familia de tokens.json, por peso: los de su paquete. Solo se importan los pesos que se cargan.
const ARCHIVOS: Record<string, Record<number, number>> = {
  Nunito: { 400: Nunito_400Regular, 500: Nunito_500Medium, 600: Nunito_600SemiBold, 700: Nunito_700Bold },
  'Source Sans 3': {
    400: SourceSans3_400Regular,
    500: SourceSans3_500Medium,
    600: SourceSans3_600SemiBold,
    700: SourceSans3_700Bold,
  },
};

// La monoespaciada del sistema en cada plataforma.
const MONOESPACIADA_DEL_SISTEMA = { ios: 'Menlo', otra: 'monospace' } as const;

/** El nombre con el que se carga una familia en un peso: la familia sin espacios y el peso ("SourceSans3_400"). */
export function nombreDeLaLetra(familia: string, peso: number): string {
  return `${familia.replace(/\s+/g, '')}_${peso}`;
}

/** Lo que la raíz le pasa a useFonts: cada peso de tokens.json de las familias de titulares y de cuerpo. */
export const LETRAS_DE_LA_MARCA: Record<string, number> = Object.fromEntries(
  [...new Set([tipografia.familia.titulares, tipografia.familia.cuerpo])].flatMap((familia) =>
    Object.values(tipografia.peso).flatMap((peso) => {
      const archivo = ARCHIVOS[familia]?.[peso];
      return archivo === undefined ? [] : [[nombreDeLaLetra(familia, peso), archivo]];
    }),
  ),
);

/** Carga la letra de la marca. Devuelve true cuando la carga terminó, bien o mal: entonces ya se puede mostrar la app. */
export function useLetraLista(): boolean {
  const [cargada, error] = useFonts(LETRAS_DE_LA_MARCA);
  return cargada || error !== null;
}

/** El estilo de la letra de un uso y un peso de tokens.json, según lo que haya cargado al dibujarse. */
export function letra(uso: Uso, peso: Peso): TextStyle {
  const valor = tipografia.peso[peso];
  if (uso === 'monoespaciada') {
    const familia = Platform.OS === 'ios' ? MONOESPACIADA_DEL_SISTEMA.ios : MONOESPACIADA_DEL_SISTEMA.otra;
    return { fontFamily: familia, fontWeight: valor };
  }
  const nombre = nombreDeLaLetra(tipografia.familia[uso], valor);
  return isLoaded(nombre) ? { fontFamily: nombre } : { fontWeight: valor };
}
