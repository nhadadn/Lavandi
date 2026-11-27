// Editar una lavandería, desde su ficha (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1, "edición";
// administracion-app-E14; tarea 4.3; design.md del cambio, F-D01). En cualquier estado, como el panel. Con el formulario
// del perfil y horarios del socio (src/alta-lavanderias/FormularioDeLaLavanderia.tsx), en su orden: los datos y la
// dirección, el municipio y las fotos, los servicios y precios, y las ventanas de disponibilidad. Sus instrucciones
// hablan de la lavandería y no de tú (design.md, sección 5), y el lavado en seco va en ámbar en su lugar, sin
// interruptor, mientras DD-010 no fije su unidad (alta-lavanderias-E07).
//
// "Guardar cambios", fijo abajo, manda solo lo que cambió (PATCH /api/administracion/lavanderias/{id_publico}); con una
// foto nueva, en multipart por POST con _method=PATCH. Las reglas son las del registro y las aplica el servidor: cada
// error del 422 va junto a su campo, nada cambia y la pantalla se queda. Al guardar, regresa a la ficha, que vuelve a
// pedir la lavandería y muestra los datos nuevos, como "Guardar cambios" en las imágenes 12, 60 y 61 (lleva a la 07); su
// socio los ve en su perfil. Si la lavandería no llega, los mensajes generales con "Intentar de nuevo"; con un 401, el
// cliente del módulo cierra la sesión (administracion-app-E05).
//
// La composición es la de las imágenes 12, 60, 13 y 61 de la Propuesta 06, salvo la dirección. La imagen 12 la pone en
// este orden: Calle; Número y Código postal en un renglón; Colonia. Aquí va en el orden del formulario del socio
// (FormularioDeLaLavanderia.tsx: PARTES_DE_LA_DIRECCION y, después, el código postal): Calle, Número, Colonia y Código
// postal, cada uno en su renglón. Manda el formulario: administracion-app-E14 y design.md, sección 4 (F-D01), piden su
// orden, y el formulario no cambia. El municipio se elige de GET /api/catalogos/municipios, que también es de la
// administración (adenda del 2026-11-26 de ADR-0022, decisión 6).
import { useLocalSearchParams } from 'expo-router';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import { PorConfirmar } from '../../../../src/administracion/lavanderias/PorConfirmar';
import { rutaDeLaFicha } from '../../../../src/administracion/lavanderias/rutas';
import {
  DECISIONES,
  mensajeDeLaAdministracion,
  TEXTOS_DE_LAS_LAVANDERIAS as TEXTOS,
  TEXTOS_DEL_FORMULARIO,
} from '../../../../src/administracion/lavanderias/textos';
import { useEdicionDeLavanderia } from '../../../../src/administracion/lavanderias/useEdicionDeLavanderia';
import { FormularioDeLaLavanderia } from '../../../../src/alta-lavanderias/FormularioDeLaLavanderia';
import { useRegresar } from '../../../../src/navegacion/regresar';
import { Boton } from '../../../../src/piezas/Boton';
import { MensajeDeError } from '../../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../../src/piezas/Pantalla';
import { letra } from '../../../../src/tema/letra';
import { color, espaciado, tipografia } from '../../../../src/tema/theme';

export default function EdicionDeLaLavanderia() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const regresar = useRegresar(rutaDeLaFicha(id));
  const { edicion, fallaDeLaLectura, errores, guardando, cambiar, guardar, reintentar } = useEdicionDeLavanderia(id);

  async function guardarYRegresar() {
    if (await guardar()) {
      regresar();
    }
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.editarLavanderia, alRegresar: regresar }}
      acciones={
        edicion ? (
          <Boton ocupado={guardando} onPress={() => void guardarYRegresar()}>
            {TEXTOS.guardarCambios}
          </Boton>
        ) : undefined
      }
    >
      {!edicion && !fallaDeLaLectura ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaFicha}</Text>
        </View>
      ) : null}
      {fallaDeLaLectura ? (
        <>
          <MensajeDeError texto={mensajeDeLaAdministracion(fallaDeLaLectura)} />
          {fallaDeLaLectura.motivo !== 'no-existe' ? (
            <Boton variante="secundario" onPress={reintentar}>
              {TEXTOS.intentarDeNuevo}
            </Boton>
          ) : null}
        </>
      ) : null}
      {edicion ? (
        <FormularioDeLaLavanderia
          formulario={edicion.actual}
          alCambiar={cambiar}
          errores={errores.campos}
          edicion={{ maximoDeFotos: edicion.maximoDeFotos }}
          textos={TEXTOS_DEL_FORMULARIO}
          alFinalDeLosServicios={
            <PorConfirmar
              enTarjeta
              etiqueta={TEXTOS.lavadoEnSeco}
              dd={DECISIONES.lavadoEnSeco}
              texto={TEXTOS.sinLavadoEnSeco}
              testID="lavado-en-seco"
            />
          }
        />
      ) : null}
      <MensajeDeError texto={errores.general} />
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
});
