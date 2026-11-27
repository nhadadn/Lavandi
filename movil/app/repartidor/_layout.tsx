// El Módulo C de la app, lo que ve y hace el socio repartidor (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C;
// openspec/changes/repartidor). Sus pantallas solo se ven con la sesión de una cuenta de repartidor (app/_layout.tsx;
// registro-E33). Una pila sin encabezado nativo, como la raíz: cada pantalla lleva el suyo. La lista de pedidos se
// comparte entre la pantalla de pedidos y las de una acción con su propia pantalla
// (src/repartidor/ProveedorDePedidos.tsx), y se va con la sesión.
//
// Al entrar, el módulo lee el perfil del repartidor de la cuenta (src/repartidor/ProveedorDelRepartidor.tsx): si opera,
// activo y con su plaza, ve sus pedidos asignados; si no, pendiente de alta o suspendido, la única pantalla es la espera
// (repartidor-E01 y E03), y sus pedidos no se abren ni se piden. Las pantallas de uno y otro llevan a la que
// corresponde. Mientras se lee no se muestra ninguna; si no llega, sin escenario propio, lo dice con los mensajes
// generales de la app, deja intentarlo de nuevo y cerrar la sesión.
import { Stack } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { mensajeDeLaFalla } from '../../src/cercanas/textos';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { ProveedorDePedidosDelRepartidor } from '../../src/repartidor/ProveedorDePedidos';
import { ProveedorDelRepartidor, useRepartidorDeLaCuenta } from '../../src/repartidor/ProveedorDelRepartidor';
import {
  RUTA_DE_LA_ESPERA_DEL_REPARTIDOR,
  RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR,
} from '../../src/repartidor/rutas';
import { TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../src/repartidor/textos';
import { CerrarSesion } from '../../src/sesion/CerrarSesion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

export default function ModuloDelRepartidor() {
  return (
    <ProveedorDelRepartidor>
      <ProveedorDePedidosDelRepartidor>
        <PantallasDelModulo />
      </ProveedorDePedidosDelRepartidor>
    </ProveedorDelRepartidor>
  );
}

function PantallasDelModulo() {
  const { estado, leerDeNuevo } = useRepartidorDeLaCuenta();

  if (estado.fase === 'leyendo') {
    return (
      <Pantalla justificar="center" testID="leyendo-el-repartidor">
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

  const opera = estado.repartidor.puede_operar;
  return (
    <Stack screenOptions={{ headerShown: false, contentStyle: estilos.fondo }}>
      <Stack.Protected guard={opera} redirectTo={RUTA_DE_LA_ESPERA_DEL_REPARTIDOR}>
        <Stack.Screen name="pedidos/index" />
        <Stack.Screen name="pedidos/rechazo/[id]" />
        <Stack.Screen name="pedidos/entrega/[id]" />
        <Stack.Screen name="historial" />
        <Stack.Screen name="estadisticas" />
      </Stack.Protected>
      <Stack.Protected guard={!opera} redirectTo={RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR}>
        <Stack.Screen name="espera" />
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
