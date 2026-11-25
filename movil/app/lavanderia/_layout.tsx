// El Módulo B de la app, lo que ve y hace el socio lavandería (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo B).
// Sus pantallas solo se ven con la sesión de una cuenta de socio lavandería (app/_layout.tsx; registro-E33). Una pila
// sin encabezado nativo, como la raíz: cada pantalla lleva el suyo. La lista de pedidos se comparte entre la pantalla
// de pedidos y la del rechazo (src/pedidos-lavanderia/ProveedorDePedidos.tsx), y se va con la sesión.
//
// Al entrar, el módulo lee la lavandería de la cuenta (src/alta-lavanderias/ProveedorDeLaLavanderia.tsx): sin ella, la
// única pantalla es el registro (alta-lavanderias-E03); con ella, sus pedidos, su historial y sus estadísticas
// (historial-de-la-lavanderia) y su perfil y horarios (alta-lavanderias-E19), y el registro ya no se abre. Las pantallas de una y otra llevan a la que corresponde.
// Mientras se lee no se muestra ninguna; si no llega, sin escenario propio, lo dice con los mensajes generales de la
// app, deja intentarlo de nuevo y cerrar la sesión. Cómo llega la persona a tener la cuenta es DD-053
// (alta-lavanderias-E04).
import { Stack } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { TEXTOS_DE_LA_LAVANDERIA_DEL_SOCIO as TEXTOS } from '../../src/alta-lavanderias/textos';
import {
  ProveedorDeLaLavanderia,
  useLavanderiaDeLaCuenta,
} from '../../src/alta-lavanderias/ProveedorDeLaLavanderia';
import { RUTA_DEL_REGISTRO_DE_LA_LAVANDERIA } from '../../src/alta-lavanderias/rutas';
import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import { ProveedorDePedidos } from '../../src/pedidos-lavanderia/ProveedorDePedidos';
import { RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA } from '../../src/pedidos-lavanderia/rutas';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { CerrarSesion } from '../../src/sesion/CerrarSesion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function ModuloDeLaLavanderia() {
  return (
    <ProveedorDeLaLavanderia>
      <ProveedorDePedidos>
        <PantallasDelModulo />
      </ProveedorDePedidos>
    </ProveedorDeLaLavanderia>
  );
}

function PantallasDelModulo() {
  const { estado, leerDeNuevo } = useLavanderiaDeLaCuenta();

  if (estado.fase === 'leyendo') {
    return (
      <Pantalla justificar="center" testID="leyendo-la-lavanderia">
        <View style={estilos.leyendo}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.leyendo}</Text>
        </View>
      </Pantalla>
    );
  }
  if (estado.fase === 'error') {
    return (
      <Pantalla
        justificar="center"
        acciones={
          <>
            <Boton onPress={leerDeNuevo}>{TEXTOS.intentarDeNuevo}</Boton>
            <CerrarSesion />
          </>
        }
      >
        <MensajeDeError texto={mensajeDeLaFalla(estado.falla)} />
      </Pantalla>
    );
  }

  const registrada = estado.lavanderia !== null;
  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: estilos.fondo }}>
      <Stack.Protected guard={registrada} redirectTo={RUTA_DEL_REGISTRO_DE_LA_LAVANDERIA}>
        <Stack.Screen name="pedidos/index" />
        <Stack.Screen name="pedidos/rechazo/[id]" />
        <Stack.Screen name="historial" />
        <Stack.Screen name="estadisticas" />
        <Stack.Screen name="perfil" />
      </Stack.Protected>
      <Stack.Protected guard={!registrada} redirectTo={RUTA_DE_LOS_PEDIDOS_DE_LA_LAVANDERIA}>
        <Stack.Screen name="registro" />
      </Stack.Protected>
    </Stack>
  );
}

const estilos = StyleSheet.create({
  fondo: {
    flex: 1,
    backgroundColor: color.semantico.fondo,
  },
  leyendo: {
    alignItems: 'center',
    gap: espaciado[3],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
});
