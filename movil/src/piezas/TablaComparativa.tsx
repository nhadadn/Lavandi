// La tabla comparativa (ADR-0022, alternativa 5): una fila por periodo, por mes de registro o por repartidor, con sus
// cifras en columnas. La usan los pedidos totales, con cada periodo junto al anterior (administracion-app-E46;
// indicadores-E01 y E02); quiénes vuelven a pedir por mes de registro (administracion-app-E47; indicadores-E08); y las
// entregas completadas por repartidor, con su plaza debajo del nombre (administracion-app-E48; indicadores-E15). Con la
// forma de la maqueta de la Propuesta 06 de la administración (41, 44, 45 y 47).
//
// - Los títulos de las columnas arriba, en el texto suave; las cifras a la derecha, en números tabulares para que no
//   bailen entre filas (design system, reglas transversales), y una línea entre filas.
// - Cada columna de cifras tiene un solo ancho, el de su título y el de sus cifras, así cada cifra queda bajo su título:
//   al menos ANCHO_DE_COLUMNA, como las columnas de la maqueta, o lo que mida su cifra más ancha. Un título más largo
//   se parte en renglones ("Con primer pedido", 44). El ancho no se encoge mientras la tabla está en pantalla: al
//   ponerse al día, las columnas no bailan.
// - Cada fila se anuncia en un solo anuncio, con su nombre y cada cifra con el título de su columna: "Semana del
//   26/10/2026. Pedidos: 3. Anterior: 2". Por eso la fila de títulos no se anuncia sola.
//
// No trae cifras propias: todas llegan por propiedades, ya escritas. No es uno de los seis componentes del design system:
// se arma con los tokens (ADR-0013), como TarjetaDeCifras.
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { letra } from '../tema/letra';
import { color, espaciado, grosor, tipografia } from '../tema/theme';

/** Una columna de cifras: la clave de su cifra en cada fila y su título. */
export interface ColumnaDeLaTabla {
  clave: string;
  titulo: string;
}

/** Una fila: su nombre, un detalle opcional debajo (la plaza del repartidor) y su cifra de cada columna, ya escrita. */
export interface FilaDeLaTabla {
  clave: string;
  nombre: string;
  detalle?: string;
  cifras: Readonly<Record<string, string>>;
}

interface Propiedades {
  /** El título de la columna de los nombres: "Periodo", "Mes de registro", "Repartidor". */
  primeraColumna: string;
  columnas: readonly ColumnaDeLaTabla[];
  filas: readonly FilaDeLaTabla[];
  testID?: string;
}

/** El ancho mínimo de una columna de cifras: el de las columnas de la maqueta (41, 44 y 47: de 64 a 80). */
export const ANCHO_DE_COLUMNA = espaciado[8] + espaciado[3];

export function TablaComparativa({ primeraColumna, columnas, filas, testID = 'tabla-comparativa' }: Propiedades) {
  const [cifrasMasAnchas, setCifrasMasAnchas] = useState<Record<string, number>>({});
  const anchoDe = (clave: string) => Math.max(ANCHO_DE_COLUMNA, cifrasMasAnchas[clave] ?? 0);

  function anotarAncho(clave: string, ancho: number) {
    setCifrasMasAnchas((anteriores) =>
      ancho > (anteriores[clave] ?? 0) ? { ...anteriores, [clave]: ancho } : anteriores,
    );
  }

  return (
    <View testID={testID}>
      <View
        style={[estilos.fila, estilos.titulos]}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
      >
        <Text style={[estilos.primera, estilos.titulo, letra('cuerpo', 'semifuerte')]}>{primeraColumna}</Text>
        {columnas.map((columna) => (
          <Text
            key={columna.clave}
            testID={`titulo-${columna.clave}`}
            style={[estilos.cifra, estilos.titulo, letra('cuerpo', 'semifuerte'), { width: anchoDe(columna.clave) }]}
          >
            {columna.titulo}
          </Text>
        ))}
      </View>
      {filas.map((fila) => (
        <View
          key={fila.clave}
          accessible
          accessibilityLabel={anuncioDe(fila, columnas)}
          testID={`fila-${fila.clave}`}
          style={[estilos.fila, estilos.separada]}
        >
          <View style={estilos.nombres}>
            <Text style={[estilos.nombre, letra('cuerpo', 'regular')]}>{fila.nombre}</Text>
            {fila.detalle ? <Text style={[estilos.detalle, letra('cuerpo', 'regular')]}>{fila.detalle}</Text> : null}
          </View>
          {columnas.map((columna) => (
            <Text
              key={columna.clave}
              testID={`cifra-${fila.clave}-${columna.clave}`}
              onLayout={(evento) => anotarAncho(columna.clave, evento.nativeEvent.layout.width)}
              style={[estilos.cifra, estilos.valor, letra('cuerpo', 'regular'), { minWidth: anchoDe(columna.clave) }]}
            >
              {fila.cifras[columna.clave] ?? ''}
            </Text>
          ))}
        </View>
      ))}
    </View>
  );
}

function anuncioDe(fila: FilaDeLaTabla, columnas: readonly ColumnaDeLaTabla[]): string {
  const nombre = fila.detalle ? `${fila.nombre}, ${fila.detalle}` : fila.nombre;
  return [nombre, ...columnas.map((columna) => `${columna.titulo}: ${fila.cifras[columna.clave] ?? ''}`)].join('. ');
}

// Las medidas de la maqueta (41): ocho entre columnas y ocho arriba y abajo de cada fila; cuatro en la de los títulos.
const estilos = StyleSheet.create({
  fila: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
    paddingVertical: espaciado[2],
  },
  titulos: {
    paddingVertical: espaciado[1],
  },
  separada: {
    borderTopWidth: grosor.control,
    borderTopColor: color.semantico.borde,
  },
  primera: {
    flex: 1,
  },
  nombres: {
    flex: 1,
    gap: espaciado[1],
  },
  titulo: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  detalle: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  // Las cifras a la derecha, con el ancho de su columna, para que se alineen entre filas.
  cifra: {
    flexShrink: 0,
    textAlign: 'right',
  },
  // Los números tabulares, para que las cifras no bailen (design system, reglas transversales).
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
    fontVariant: ['tabular-nums'],
  },
});
