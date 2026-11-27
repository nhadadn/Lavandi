// El Módulo D de la app, lo que ve y hace la administración (NSV-2026-001-v8.pdf:3:Cláusula I, "panel de
// administración" dentro de la app única; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D; C-247; ADR-0022,
// decisión 11). Sus pantallas solo se ven con la sesión de una cuenta de administración (app/_layout.tsx;
// administracion-app-E02). Una pila sin encabezado nativo, como la raíz y los módulos de la lavandería y del repartidor:
// cada pantalla lleva el suyo.
//
// No lleva la lista de sus pantallas: expo-router toma cada archivo de esta carpeta como una pantalla de la pila, así
// cada función del módulo agrega la suya sin tocar este archivo (openspec/changes/administracion-en-la-app/design.md,
// sección 5).
import { Stack } from 'expo-router';
import { StyleSheet } from 'react-native';

import { color } from '../../src/tema/theme';

export default function ModuloDeLaAdministracion() {
  return <Stack screenOptions={{ headerShown: false, contentStyle: estilos.fondo }} />;
}

const estilos = StyleSheet.create({
  fondo: {
    flex: 1,
    backgroundColor: color.semantico.fondo,
  },
});
