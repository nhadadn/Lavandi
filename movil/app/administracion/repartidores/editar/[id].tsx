// La edición de un repartidor en la gestión de repartidores (F-D02; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo
// D #2, "edición"; administracion-app-E23): se abre con "Editar" en su ficha. Lleva sus cinco datos del registro
// (repartidor-E08): su nombre, su teléfono, su correo, su tipo de vehículo y su foto, que se toma con la cámara o se
// elige de la galería y la app prepara en el teléfono con los parámetros de configuración (ADR-0006), con la pieza de la
// app (src/piezas/CampoDeFoto.tsx). La contraseña no se edita.
//
// "Guardar cambios" manda solo los datos que cambiaron, como se escribieron; con una foto nueva, en multipart
// (PATCH /api/administracion/repartidores/{id_publico}). La app no valida por su cuenta: si el servidor rechaza un dato,
// o el correo ya es de otra cuenta, responde 422 y su error va junto a su campo, y nada cambia. Con el 200, la app vuelve
// a la ficha, que se pide de nuevo y muestra los datos nuevos; si la persona ya regresó mientras se mandaba, se queda
// donde está. Un repartidor que no existe responde 404 y la pantalla lo dice. Con un 401, el cliente de la
// administración ya volvió al inicio de sesión (administracion-app-E05).
//
// La composición es la de la imagen 20 de la Propuesta 06: los campos en el orden del registro, la foto de perfil al
// final y "Guardar cambios" fijo abajo. Es un formulario propio de la administración: no parte el registro del
// repartidor (app/registro-de-repartidor.tsx), que es del Módulo C.
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import {
  CAMPOS_DE_LA_EDICION,
  CONTRATO_DE_LOS_REPARTIDORES,
  type CambiosDelRepartidor,
  type CampoDeLaEdicion,
  type RepartidorDeLaAdministracion,
} from '../../../../src/administracion/repartidores/api';
import { RUTA_DE_LOS_REPARTIDORES, rutaDelRepartidor } from '../../../../src/administracion/repartidores/rutas';
import {
  mensajeDeLosRepartidores,
  TEXTOS_DE_LOS_REPARTIDORES as TEXTOS,
} from '../../../../src/administracion/repartidores/textos';
import {
  useEdicionDelRepartidor,
  type EdicionDelRepartidor,
} from '../../../../src/administracion/repartidores/useEdicionDelRepartidor';
import { useRegresar } from '../../../../src/navegacion/regresar';
import { Boton } from '../../../../src/piezas/Boton';
import { CampoDeFoto } from '../../../../src/piezas/CampoDeFoto';
import { CampoDeTexto, type TipoDeCampo } from '../../../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../../src/piezas/Pantalla';
import { letra } from '../../../../src/tema/letra';
import { color, espaciado, tipografia } from '../../../../src/tema/theme';

// Los datos de texto, en el orden de la imagen 20.
const CAMPOS: { campo: CampoDeLaEdicion; etiqueta: string; tipo: TipoDeCampo }[] = [
  { campo: 'nombre', etiqueta: TEXTOS.nombre, tipo: 'texto' },
  { campo: 'telefono', etiqueta: TEXTOS.telefono, tipo: 'telefono' },
  { campo: 'correo', etiqueta: TEXTOS.correoElectronico, tipo: 'correo' },
  { campo: 'tipo_vehiculo', etiqueta: TEXTOS.tipoDeVehiculo, tipo: 'texto' },
];

export default function EdicionDelRepartidorEnLaAdministracion() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const regresar = useRegresar(id ? rutaDelRepartidor(id) : RUTA_DE_LOS_REPARTIDORES);
  const edicion = useEdicionDelRepartidor(id);
  const { datos: repartidor, falla, consultarDeNuevo } = edicion.repartidor;

  if (repartidor) {
    return <Formulario key={repartidor.id_publico} repartidor={repartidor} edicion={edicion} regresar={regresar} />;
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.tituloDeLaEdicion, alRegresar: regresar }}>
      {falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargandoLaFicha}</Text>
        </View>
      ) : (
        <>
          <MensajeDeError texto={mensajeDeLosRepartidores(falla)} />
          {falla.motivo !== 'no-existe' ? (
            <Boton variante="secundario" onPress={consultarDeNuevo}>
              {TEXTOS.intentarDeNuevo}
            </Boton>
          ) : null}
        </>
      )}
    </Pantalla>
  );
}

// El formulario, lleno con los datos que tenía al abrirse. Lo que se escribe se queda aunque la ficha se vuelva a pedir.
function Formulario({
  repartidor,
  edicion,
  regresar,
}: {
  repartidor: RepartidorDeLaAdministracion;
  edicion: EdicionDelRepartidor;
  /** El regreso a la ficha: el del encabezado, y el que sigue a guardar. */
  regresar: () => void;
}) {
  const [originales] = useState(() => datosDe(repartidor));
  const [datos, setDatos] = useState(originales);
  const [fotoNueva, setFotoNueva] = useState<string | null>(null);

  // Solo lo que cambió: un dato que no viaja no cambia (repartidor-E08).
  function guardar() {
    const cambios: CambiosDelRepartidor = {};
    for (const campo of CAMPOS_DE_LA_EDICION) {
      if (datos[campo] !== originales[campo]) {
        cambios[campo] = datos[campo];
      }
    }
    if (fotoNueva) {
      cambios.foto = { uri: fotoNueva };
    }
    edicion.guardar(cambios, regresar);
  }

  return (
    <Pantalla
      desplazable
      encabezado={{ titulo: TEXTOS.tituloDeLaEdicion, alRegresar: regresar }}
      acciones={
        <>
          <MensajeDeError texto={edicion.aviso} />
          <Boton ocupado={edicion.enviando} onPress={guardar}>
            {TEXTOS.guardarCambios}
          </Boton>
        </>
      }
    >
      {CAMPOS.map(({ campo, etiqueta, tipo }) => (
        <CampoDeTexto
          key={campo}
          nombre={campo}
          etiqueta={etiqueta}
          tipo={tipo}
          valor={datos[campo]}
          alCambiar={(texto) => setDatos((anteriores) => ({ ...anteriores, [campo]: texto }))}
          error={edicion.errorDe(campo)}
        />
      ))}
      <CampoDeFoto
        nombre={CONTRATO_DE_LOS_REPARTIDORES.foto}
        forma="circulo"
        textos={{
          titulo: TEXTOS.fotoDePerfil,
          laFoto: TEXTOS.fotoDePerfil,
          tomarFoto: TEXTOS.tomarFoto,
          elegirDeLaGaleria: TEXTOS.elegirDeLaGaleria,
          sinPermisoDeLaCamara: TEXTOS.sinPermisoDeLaCamara,
          fotoNoPreparada: TEXTOS.fotoNoPreparada,
        }}
        foto={fotoNueva ?? repartidor.foto?.url ?? null}
        alCambiar={setFotoNueva}
        error={edicion.errorDe(CONTRATO_DE_LOS_REPARTIDORES.foto)}
        deshabilitado={edicion.enviando}
      />
    </Pantalla>
  );
}

// Los datos de texto como los muestra el formulario: el que falta, vacío.
function datosDe(repartidor: RepartidorDeLaAdministracion): Record<CampoDeLaEdicion, string> {
  return {
    nombre: repartidor.nombre ?? '',
    telefono: repartidor.telefono ?? '',
    correo: repartidor.correo,
    tipo_vehiculo: repartidor.tipo_vehiculo ?? '',
  };
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
