// Repartidores, la lista de la gestión de repartidores (F-D02; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D
// #2; administracion-app-E19): se abre con "Repartidores" en el inicio del módulo (administracion-app-E03). Trae a todos,
// de todas las plazas y en cualquier estado, como los da GET /api/administracion/repartidores, cada uno con su nombre, su
// estado y su plaza, o "Sin plaza" si no la tiene; los pendientes, primero. Tocar uno abre su ficha. Se pide al abrir la
// pantalla y al volver a ella, para que el estado que cambió en la ficha se vea aquí.
//
// Si la lista no llega, sin escenario propio: los mensajes generales de la app e "Intentar de nuevo", como la imagen 05
// de la Propuesta 06. Con un 401, el cliente de la administración ya volvió al inicio de sesión (administracion-app-E05).
//
// La composición es la de las imágenes 14 y 62 de la Propuesta 06: el encabezado con el regreso al inicio del módulo y
// una fila tocable por repartidor, con el nombre arriba, la insignia y la plaza debajo, y la flecha a la derecha.
import { useRouter } from 'expo-router';
import { ActivityIndicator, Pressable, StyleSheet, Text, View } from 'react-native';

import type { RepartidorEnLaLista } from '../../../src/administracion/repartidores/api';
import { estadoYPlazaEnTexto, InsigniaYPlaza } from '../../../src/administracion/repartidores/InsigniaYPlaza';
import { rutaDelRepartidor } from '../../../src/administracion/repartidores/rutas';
import {
  mensajeDeLosRepartidores,
  TEXTOS_DE_LOS_REPARTIDORES as TEXTOS,
} from '../../../src/administracion/repartidores/textos';
import { useRepartidores } from '../../../src/administracion/repartidores/useRepartidores';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../../src/administracion/rutas';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { letra } from '../../../src/tema/letra';
import { accesibilidad, color, espaciado, grosor, radio, tipografia } from '../../../src/tema/theme';

export default function Repartidores() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { datos: repartidores, falla, consultarDeNuevo } = useRepartidores();

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      {repartidores === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDeLosRepartidores(falla)} />
          <Boton variante="secundario" onPress={consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {repartidores?.length === 0 ? <Parrafo alineacion="inicio">{TEXTOS.sinRepartidores}</Parrafo> : null}
      {repartidores?.map((repartidor) => (
        <FilaDelRepartidor
          key={repartidor.id_publico}
          repartidor={repartidor}
          alTocar={() => router.push(rutaDelRepartidor(repartidor.id_publico))}
        />
      ))}
    </Pantalla>
  );
}

// Un repartidor de la lista: toda la fila se toca y se anuncia como un solo enlace, "Repartidor de prueba 3, Pendiente,
// Sin plaza".
function FilaDelRepartidor({ repartidor, alTocar }: { repartidor: RepartidorEnLaLista; alTocar: () => void }) {
  const nombre = repartidor.nombre ?? TEXTOS.sinNombre;
  return (
    <Pressable
      role="link"
      accessibilityLabel={`${nombre}, ${estadoYPlazaEnTexto(repartidor)}`}
      onPress={alTocar}
      style={({ pressed }) => [estilos.fila, pressed ? estilos.filaPresionada : null]}
    >
      <View style={estilos.textos}>
        <Text style={[estilos.nombre, letra('cuerpo', 'semifuerte')]}>{nombre}</Text>
        <InsigniaYPlaza estado={repartidor.estado} plaza={repartidor.plaza} />
      </View>
      {/* La flecha es adorno: la fila ya se anuncia como enlace. Es un carácter, sin librería de íconos. */}
      <Text style={estilos.flecha} accessibilityElementsHidden importantForAccessibility="no-hide-descendants">
        ›
      </Text>
    </Pressable>
  );
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
  // La tarjeta tocable de las imágenes 14 y 62.
  fila: {
    minHeight: accesibilidad.area_tactil_minima_dp,
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  filaPresionada: {
    backgroundColor: color.semantico.superficie_hundida,
  },
  textos: {
    flex: 1,
    gap: espaciado[2],
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  flecha: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.titulo,
  },
});
