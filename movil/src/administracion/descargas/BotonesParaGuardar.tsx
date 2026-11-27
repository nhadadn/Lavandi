// "Guardar .csv" y "Guardar .xlsx" de una descarga lista, en un renglón, como en las imágenes 53 y 54 de la Propuesta 06
// (administracion-app-E57 y E58). Los dos formatos se pueden bajar: el Excel lo arma el servidor de los CSV (ADR-0022,
// decisión 15). Mientras se guarda uno, ese trabaja y ningún otro de la pantalla se puede tocar, tampoco los de otra
// descarga. Los usan la pieza que pide la descarga y cada tarjeta de la pantalla Descargas.
import { StyleSheet, View } from 'react-native';

import { Boton } from '../../piezas/Boton';
import { espaciado } from '../../tema/theme';
import { FORMATOS, type Descarga } from './api';
import { TEXTOS_DE_LAS_DESCARGAS as TEXTOS } from './textos';
import type { GuardadoDeArchivos } from './useGuardarElArchivo';

interface Propiedades {
  descarga: Descarga;
  archivo: GuardadoDeArchivos;
}

export function BotonesParaGuardar({ descarga, archivo }: Propiedades) {
  const guardando = archivo.guardandoDe(descarga);
  return (
    <View style={estilos.fila}>
      {FORMATOS.map((formato) => (
        <View key={formato} style={estilos.boton}>
          <Boton
            variante="secundario"
            ocupado={guardando === formato}
            deshabilitado={archivo.ocupado}
            onPress={() => void archivo.guardar(descarga, formato)}
          >
            {TEXTOS.guardar[formato]}
          </Boton>
        </View>
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    gap: espaciado[2],
  },
  boton: {
    flex: 1,
  },
});
