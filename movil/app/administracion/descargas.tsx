// La pantalla Descargas del Módulo D (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, "Descarga de reportes en formato CSV
// y Excel para cualquier rango de fechas"; administracion-app-E59; tarea 20.3; ADR-0022, decisión 15): las descargas de
// la cuenta mientras viven, de la más nueva a la más vieja, como las da GET /api/administracion/descargas, cada una con
// su reporte, el rango de su archivo, cuándo se pidió, su estado y, si está lista, "Guardar .csv" y "Guardar .xlsx"
// (administracion-app-E58); la que no se pudo generar, con "Pedirla otra vez" (administracion-app-E57). Se abre desde el
// inicio del módulo, en "Estado del negocio" (administracion-app-E03). Las descargas se piden en la pantalla de cada
// reporte, con su "Descargar" (src/administracion/descargas/PedirDescarga.tsx).
//
// La composición es la de las imágenes 54 y 55 de la Propuesta 06 de los mocks: el encabezado con regreso, el texto de
// los días que viven, con los que da el servidor, arriba dónde quedó el último archivo que se guardó, y una tarjeta por
// descarga; sin ninguna, una tarjeta que dice dónde se piden. La que se pide otra vez entra arriba, como la más nueva.
// Si la lista no llega, sin escenario propio: los mensajes generales de la app e "Intentar de nuevo", como la imagen 05
// de la Propuesta 06 y las demás listas del módulo. Con un 401, el cliente del módulo cierra la sesión
// (administracion-app-E05).
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../src/administracion/rutas';
import { TarjetaDeDescarga } from '../../src/administracion/descargas/TarjetaDeDescarga';
import {
  mensajeDeLaFalla,
  nombreDeLaDescarga,
  seGuardoDe,
  TEXTOS_DE_LAS_DESCARGAS as TEXTOS,
  vigencia,
} from '../../src/administracion/descargas/textos';
import { useDescargas } from '../../src/administracion/descargas/useDescargas';
import { useGuardarElArchivo } from '../../src/administracion/descargas/useGuardarElArchivo';
import { useRegresar } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { letra } from '../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../src/tema/theme';

export default function Descargas() {
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { descargas, diasDeVida, falla, actualizar, agregar, consultarDeNuevo } = useDescargas();
  const archivo = useGuardarElArchivo(actualizar);
  const { guardado } = archivo;

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      {diasDeVida !== null ? <Parrafo alineacion="inicio">{vigencia(diasDeVida)}</Parrafo> : null}
      {guardado ? (
        <Text style={[estilos.guardado, letra('cuerpo', 'semifuerte')]}>
          {seGuardoDe(guardado.formato, nombreDeLaDescarga(guardado.descarga), guardado.carpeta)}
        </Text>
      ) : null}
      {descargas === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDeLaFalla(falla)} />
          <Boton variante="secundario" onPress={consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {descargas?.length === 0 ? (
        <View style={estilos.tarjeta}>
          <Parrafo alineacion="inicio">{TEXTOS.sinDescargas}</Parrafo>
        </View>
      ) : null}
      {descargas?.map((descarga) => (
        <TarjetaDeDescarga
          key={descarga.id_publico}
          descarga={descarga}
          alCambiar={actualizar}
          alPedirOtra={agregar}
          archivo={archivo}
        />
      ))}
    </Pantalla>
  );
}

const estilos = StyleSheet.create({
  guardado: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  cargando: {
    alignItems: 'center',
    gap: espaciado[3],
    paddingVertical: espaciado[5],
  },
  suave: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.cuerpo,
  },
  tarjeta: {
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
});
