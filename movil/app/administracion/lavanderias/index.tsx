// Lavanderías, la gestión de lavanderías de la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1,
// "Alta, baja, suspensión y edición de socios."; administracion-app-E13; tarea 4.1; design.md del cambio, F-D01). Se
// abre desde el inicio del módulo, en "Gestión" (administracion-app-E03). Lista todas las lavanderías, de todas las
// plazas y en cualquier estado, como las da GET /api/administracion/lavanderias, cada una con su nombre, su estado y su
// plaza; la búsqueda por nombre la hace el servidor. Cada fila abre su ficha.
//
// El alta espera DD-053 (administracion-app-E17): la pantalla lo dice en ámbar, sin botón, como el panel, que tampoco la
// ofrece. La composición es la de las imágenes 05 y 06 de la Propuesta 06: el encabezado con regreso, la búsqueda, el
// alta en espera y una fila por lavandería. Del mock no entra el marcador "Nombre de la lavandería" dentro de la
// búsqueda: CampoDeTexto no lleva marcador. Si la lista no llega, los mensajes generales de la app con "Intentar de
// nuevo" (imagen 05); con un 401, el cliente del módulo cierra la sesión (administracion-app-E05).
import { useRouter } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { FilaDeLavanderia } from '../../../src/administracion/lavanderias/FilaDeLavanderia';
import { PorConfirmar } from '../../../src/administracion/lavanderias/PorConfirmar';
import { rutaDeLaFicha } from '../../../src/administracion/lavanderias/rutas';
import {
  DECISIONES,
  mensajeDeLaAdministracion,
  TEXTOS_DE_LAS_LAVANDERIAS as TEXTOS,
} from '../../../src/administracion/lavanderias/textos';
import { useLavanderias } from '../../../src/administracion/lavanderias/useLavanderias';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../../src/administracion/rutas';
import { TEXTOS_DE_LA_ADMINISTRACION } from '../../../src/administracion/textos';
import { useRegresar } from '../../../src/navegacion/regresar';
import { Boton } from '../../../src/piezas/Boton';
import { CampoDeTexto } from '../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../src/piezas/Pantalla';
import { Parrafo } from '../../../src/piezas/Parrafo';
import { letra } from '../../../src/tema/letra';
import { color, espaciado, tipografia } from '../../../src/tema/theme';

export default function Lavanderias() {
  const router = useRouter();
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { buscar, setBuscar, estado, reintentar } = useLavanderias();

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS_DE_LA_ADMINISTRACION.secciones.lavanderias, alRegresar: regresar }}
    >
      <CampoDeTexto nombre="buscar" etiqueta={TEXTOS.buscarPorNombre} valor={buscar} alCambiar={setBuscar} />

      {estado.fase === 'cargando' ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaLista}</Text>
        </View>
      ) : null}

      {estado.fase === 'error' ? (
        <>
          <MensajeDeError texto={mensajeDeLaAdministracion(estado.falla)} />
          <Boton variante="secundario" onPress={reintentar}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}

      {estado.fase === 'lista' ? (
        <>
          <PorConfirmar
            enTarjeta
            etiqueta={TEXTOS.darDeAltaUnaLavanderia}
            dd={DECISIONES.alta}
            texto={TEXTOS.sinAlta}
            testID="alta-en-espera"
          />
          {estado.lavanderias.length === 0 ? (
            <Parrafo alineacion="inicio">
              {buscar.trim() === '' ? TEXTOS.sinLavanderias : TEXTOS.sinCoincidencias}
            </Parrafo>
          ) : (
            <View style={estilos.lista} testID="lista-de-lavanderias">
              {estado.lavanderias.map((lavanderia) => (
                <FilaDeLavanderia
                  key={lavanderia.id_publico}
                  lavanderia={lavanderia}
                  alTocar={() => router.push(rutaDeLaFicha(lavanderia.id_publico))}
                />
              ))}
            </View>
          )}
        </>
      ) : null}
    </Pantalla>
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
  lista: {
    gap: espaciado[3],
  },
});
