// "Mis pedidos", el historial de pedidos del Módulo A (openspec/changes/historial-y-calificacion, historial-E01, E03 y
// E04; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #13). Pide al servidor, con la sesión, la lista de los
// pedidos del usuario y la muestra del más reciente al más antiguo, como llega: cada pedido con su folio, su fecha, su
// lavandería y la insignia de su estado, la del componente 3 del design system. Al tocar un pedido se abre su
// seguimiento (estados-E02). Un pedido Entregado sin calificar ofrece "Calificar", que abre su calificación, y uno ya
// calificado dice sus estrellas (historial-E06). Al volver de calificar, la lista se pide de nuevo y lo muestra. Sin ningún pedido, un mensaje neutro. Si la lista no llega, sin escenario propio: los mensajes generales
// de la app, como el seguimiento, la agenda y el perfil.
//
// Arriba, el filtro por fecha (historial-E03): con un rango, la lista muestra lo que el servidor devuelve para esos días,
// y si no queda ninguno lo dice (historial-E04). "Quitar filtro" vuelve a la lista completa. Si el servidor rechaza una
// fecha del rango (422), el filtro lo dice junto a su campo.
//
// La composición es la del mock A13 y la imagen a13-historial de la Propuesta 04, con el encabezado "Mis pedidos" y
// "Regresar" de las demás pantallas: la barra inferior de pestañas todavía no existe.
import { useRouter } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import type { Falla } from '../../src/configuracion/api';
import { CONTRATO_DEL_HISTORIAL, type RangoDeFechas } from '../../src/historial/api';
import { FiltroPorFecha } from '../../src/historial/FiltroPorFecha';
import { rutaDeLaCalificacion, rutaDelSeguimiento } from '../../src/historial/rutas';
import { TarjetaDePedido } from '../../src/historial/TarjetaDePedido';
import { TEXTOS_DEL_HISTORIAL } from '../../src/historial/textos';
import { useHistorial } from '../../src/historial/useHistorial';
import { useRegresar } from '../../src/navegacion/regresar';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function MisPedidos() {
  const router = useRouter();
  const regresar = useRegresar('/inicio');
  const [rango, setRango] = useState<RangoDeFechas | null>(null);
  const estado = useHistorial(rango);
  const falla = estado.fase === 'error' ? estado.falla : null;
  const erroresDelRango = falla && erroresDeLasFechas(falla);

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS_DEL_HISTORIAL.encabezado, alRegresar: regresar }}>
      <FiltroPorFecha
        alAplicar={setRango}
        alQuitar={() => setRango(null)}
        erroresDelServidor={erroresDelRango ?? undefined}
      />
      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS_DEL_HISTORIAL.cargando}</Text>
        </View>
      ) : null}
      {estado.fase === 'lista' && estado.pedidos.length === 0 ? (
        <Parrafo alineacion="inicio">
          {rango ? TEXTOS_DEL_HISTORIAL.sinPedidosEnLasFechas : TEXTOS_DEL_HISTORIAL.sinPedidos}
        </Parrafo>
      ) : null}
      {estado.fase === 'lista'
        ? estado.pedidos.map((pedido) => (
            <TarjetaDePedido
              key={pedido.id_publico}
              pedido={pedido}
              alTocar={() => router.push(rutaDelSeguimiento(pedido.id_publico))}
              alCalificar={() => router.push(rutaDeLaCalificacion(pedido))}
            />
          ))
        : null}
      {/* Lo que rechazó el servidor del rango va junto a su campo, en el filtro. */}
      <MensajeDeError texto={falla && !erroresDelRango ? mensajeDeLaFalla(falla) : undefined} />
    </Pantalla>
  );
}

/** Los errores de las fechas del rango en un 422 (errors.desde o errors.hasta), o null si la falla es otra. */
function erroresDeLasFechas(falla: Falla) {
  if (falla.motivo !== 'invalido') {
    return null;
  }
  const { desde, hasta } = CONTRATO_DEL_HISTORIAL;
  return falla.errores[desde] || falla.errores[hasta] ? falla.errores : null;
}

const estilos = StyleSheet.create({
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
