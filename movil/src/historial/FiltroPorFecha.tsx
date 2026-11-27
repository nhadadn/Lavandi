// El filtro por fecha de "Mis pedidos" (historial-E03 y E04; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #13):
// "Elegir fechas" lo abre con dos campos, "Desde" y "Hasta", en DD/MM/AAAA con el teclado de números y las diagonales que
// pone la app; "Aplicar" manda el rango, los dos días incluidos, y "Quitar filtro" lo cierra y vuelve a la lista
// completa. Antes de mandarlo, cada fecha tiene que existir y "Hasta" no puede ser anterior a "Desde": si no, lo dice
// junto al campo. Si el servidor rechaza una fecha (422), su mensaje va junto a su campo.
//
// Es lo mínimo que filtra por un rango sin dependencia nativa nueva: React Native no trae el selector de fechas del
// sistema (design.md de historial-y-calificacion, Open Questions). Como el mock A13 y la imagen a13-historial de la
// Propuesta 04, del que no entran los atajos "Últimos 30 días" y "Este año": son otra forma de presentar el mismo filtro
// (proposal.md, Fuera del contrato) y el rango basta. No es uno de los seis componentes del design system: se arma con
// las piezas y los tokens (ADR-0013).
import { useState } from 'react';
import { StyleSheet, View } from 'react-native';

import type { ErroresPorCampo } from '../configuracion/api';
import { Boton } from '../piezas/Boton';
import { CampoDeTexto } from '../piezas/CampoDeTexto';
import { color, espaciado, grosor, radio } from '../tema/theme';
import { CONTRATO_DEL_HISTORIAL, type RangoDeFechas } from './api';
import { conDiagonales, fechaDeTexto } from './fechas';
import { TEXTOS_DEL_HISTORIAL } from './textos';

interface Propiedades {
  /** Recibe el rango que se aplica. */
  alAplicar: (rango: RangoDeFechas) => void;
  /** Vuelve a la lista sin filtro. */
  alQuitar: () => void;
  /** Los errores del servidor (422) con el último rango que se aplicó. */
  erroresDelServidor?: ErroresPorCampo;
}

type Campo = 'desde' | 'hasta';

export function FiltroPorFecha({ alAplicar, alQuitar, erroresDelServidor }: Propiedades) {
  const [abierto, setAbierto] = useState(false);
  const [escrito, setEscrito] = useState<Record<Campo, string>>({ desde: '', hasta: '' });
  const [errores, setErrores] = useState<Partial<Record<Campo, string>>>({});

  if (!abierto) {
    return (
      <Boton variante="secundario" onPress={() => setAbierto(true)}>
        {TEXTOS_DEL_HISTORIAL.elegirFechas}
      </Boton>
    );
  }

  function escribir(campo: Campo, texto: string) {
    setEscrito((anterior) => ({ ...anterior, [campo]: conDiagonales(texto) }));
  }

  function aplicar() {
    const desde = fechaDeTexto(escrito.desde);
    const hasta = fechaDeTexto(escrito.hasta);
    const alReves = desde !== null && hasta !== null && hasta < desde;
    const siNoExiste = (fecha: string | null) => (fecha === null ? TEXTOS_DEL_HISTORIAL.fechaInvalida : undefined);
    setErrores({
      desde: siNoExiste(desde),
      hasta: alReves ? TEXTOS_DEL_HISTORIAL.rangoAlReves : siNoExiste(hasta),
    });
    if (desde !== null && hasta !== null && !alReves) {
      alAplicar({ desde, hasta });
    }
  }

  function quitar() {
    setAbierto(false);
    setEscrito({ desde: '', hasta: '' });
    setErrores({});
    alQuitar();
  }

  // El error de cada campo: el de la app, o el que dio el servidor con el último rango.
  function errorDe(campo: Campo): string | undefined {
    return errores[campo] ?? erroresDelServidor?.[CONTRATO_DEL_HISTORIAL[campo]]?.[0];
  }

  return (
    <View style={estilos.filtro} testID="filtro-por-fecha">
      <View style={estilos.campos}>
        {(['desde', 'hasta'] as const).map((campo) => (
          <View key={campo} style={estilos.campo}>
            <CampoDeTexto
              nombre={campo}
              etiqueta={TEXTOS_DEL_HISTORIAL[campo]}
              tipo="fecha"
              valor={escrito[campo]}
              alCambiar={(texto) => escribir(campo, texto)}
              error={errorDe(campo)}
            />
          </View>
        ))}
      </View>
      <Boton onPress={aplicar}>{TEXTOS_DEL_HISTORIAL.aplicar}</Boton>
      <Boton variante="secundario" onPress={quitar}>
        {TEXTOS_DEL_HISTORIAL.quitarFiltro}
      </Boton>
    </View>
  );
}

const estilos = StyleSheet.create({
  // Como la tarjeta del rango en el mock A13.
  filtro: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  campos: {
    flexDirection: 'row',
    gap: espaciado[2],
  },
  campo: {
    flex: 1,
  },
});
