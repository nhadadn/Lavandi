// "Ayuda", el soporte del Módulo A (openspec/changes/perfil-y-soporte; NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3,
// Módulo A #16). Se abre desde "Ayuda" en la pantalla principal y pide el número de soporte al servidor con la sesión.
//
// - "Abrir WhatsApp" abre el chat de WhatsApp Business con el número de la configuración del servidor
//   (perfil-usuario-E09). Sin número, la opción aparece como no disponible y la app no abre un enlace vacío
//   (perfil-usuario-E10).
// - "Reportar un problema" manda el reporte con su descripción y, si la pantalla se abrió desde un pedido
//   (/ayuda?pedido=<id_publico>&folio=<folio>), con ese pedido (perfil-usuario-E11 y E12).
//
// La composición sigue la imagen a16-soporte de la Propuesta 04 en lo que tiene escenario. Del mock no entra la barra
// inferior.
import { useLocalSearchParams } from 'expo-router';
import { StyleSheet, View } from 'react-native';

import { useRegresar } from '../src/navegacion/regresar';
import { FormularioDeReporte } from '../src/perfil-usuario/FormularioDeReporte';
import { SoporteWhatsApp } from '../src/perfil-usuario/SoporteWhatsApp';
import { TEXTOS_DEL_PERFIL_DE_USUARIO as TEXTOS } from '../src/perfil-usuario/textos';
import { useSoporte } from '../src/perfil-usuario/useSoporte';
import { Pantalla } from '../src/piezas/Pantalla';
import { espaciado } from '../src/tema/theme';

export default function Ayuda() {
  const regresar = useRegresar('/inicio');
  const soporte = useSoporte();
  const { pedido, folio } = useLocalSearchParams<{ pedido?: string; folio?: string }>();

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.ayuda, alRegresar: regresar }}>
      <View style={estilos.secciones}>
        <SoporteWhatsApp soporte={soporte} />
        <FormularioDeReporte pedido={textoDelParametro(pedido)} folio={textoDelParametro(folio)} />
      </View>
    </Pantalla>
  );
}

// Un parámetro de la ruta con texto; vacío o repetido, no cuenta.
function textoDelParametro(valor: unknown): string | null {
  return typeof valor === 'string' && valor.trim() !== '' ? valor.trim() : null;
}

const estilos = StyleSheet.create({
  secciones: {
    gap: espaciado[5],
  },
});
