// La espera "Procesando pago" del cobro (openspec/changes/pago, pago-E08), por el id_publico del cobro que dio el
// checkout. La abre la pantalla de pago al volver la persona del checkout, o el enlace de regreso si abre la app
// (app/+native-intent.tsx). Regresar del checkout no confirma nada (pago-E07): lo confirma el servidor.
//
// - Mientras el cobro sigue pendiente, "Procesando pago", y lo consulta cada 5 segundos (src/pago/useEsperaDelCobro.ts).
// - Cuando el servidor lo da por pagado, abre la confirmación del pedido con su folio (confirmacion-E09) en su lugar.
// - Cuando informa que el intento se rechazó, vuelve a la pantalla de pago del pedido, que lo dice y vuelve a intentar
//   con el mismo cobro (pago-E04).
// - A los 3 minutos sin respuesta, deja de esperar: el pago sigue en revisión y "Volver a consultar" espera de nuevo.
// - Si el cobro no existe o ya no se puede pagar, lo dice y deja de consultar.
//
// La composición es la de la espera del mock A10, sin el nombre de la pasarela (DD-079).
import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';

import { mensajeDeLaFalla } from '../../../../src/cercanas/textos';
import type { Falla } from '../../../../src/configuracion/api';
import { useRegresar } from '../../../../src/navegacion/regresar';
import { rutaDelPagoRechazado } from '../../../../src/pago/rutas';
import { TEXTOS_DE_LA_ESPERA, TEXTOS_DEL_PAGO } from '../../../../src/pago/textos';
import { useEsperaDelCobro, type FaseDeLaEspera } from '../../../../src/pago/useEsperaDelCobro';
import { rutaDeLaConfirmacion } from '../../../../src/pedido/rutas';
import { Boton } from '../../../../src/piezas/Boton';
import { MensajeDeError } from '../../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../../src/piezas/Pantalla';
import { Presentacion } from '../../../../src/piezas/Presentacion';
import { color, espaciado } from '../../../../src/tema/theme';

export default function ProcesandoPago() {
  const { cobro } = useLocalSearchParams<{ cobro: string }>();
  const router = useRouter();
  const regresar = useRegresar('/inicio');
  const { espera, falla, volverAConsultar } = useEsperaDelCobro(cobro);

  // El resultado lo muestra la pantalla que sigue, en lugar de la espera.
  useEffect(() => {
    if (espera.fase === 'pagado') {
      router.replace(rutaDeLaConfirmacion(espera.pedido));
    } else if (espera.fase === 'rechazado') {
      router.replace(rutaDelPagoRechazado(espera.pedido));
    }
  }, [espera, router]);

  const enRevision = espera.fase === 'en-revision';
  const procesando = espera.fase === 'esperando' || espera.fase === 'pagado' || espera.fase === 'rechazado';

  return (
    <Pantalla
      encabezado={{ titulo: TEXTOS_DEL_PAGO.encabezado, alRegresar: regresar }}
      acciones={
        enRevision ? <Boton onPress={volverAConsultar}>{TEXTOS_DE_LA_ESPERA.volverAConsultar}</Boton> : undefined
      }
    >
      {procesando ? (
        <>
          <Presentacion
            titulo={TEXTOS_DE_LA_ESPERA.procesando}
            texto={TEXTOS_DE_LA_ESPERA.esperamos}
            alineacion="inicio"
          />
          <View style={estilos.indicador}>
            <ActivityIndicator color={color.semantico.accion} />
          </View>
        </>
      ) : null}
      {enRevision ? (
        <Presentacion titulo={TEXTOS_DE_LA_ESPERA.enRevision} texto={TEXTOS_DE_LA_ESPERA.todaviaNo} alineacion="inicio" />
      ) : null}
      <MensajeDeError texto={mensajeDeLaEspera(espera, falla)} />
    </Pantalla>
  );
}

// El cobro que no existe o que ya no se paga; mientras se espera, la falla de la última consulta.
function mensajeDeLaEspera({ fase }: FaseDeLaEspera, falla: Falla | null): string | undefined {
  if (fase === 'no-encontrado') {
    return TEXTOS_DE_LA_ESPERA.noEncontrado;
  }
  if (fase === 'ya-no-se-puede') {
    return TEXTOS_DE_LA_ESPERA.yaNoSePuedePagar;
  }
  return falla ? mensajeDeLaFalla(falla) : undefined;
}

const estilos = StyleSheet.create({
  indicador: {
    alignItems: 'flex-start',
    paddingVertical: espaciado[3],
  },
});
