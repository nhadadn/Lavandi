// Comisiones, la configuración de comisiones de la administración (F-D05; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3,
// Módulo D #5, "Porcentaje de comisión por socio o global."; administracion-app-E32 a E34): se abre con "Comisiones" en
// el inicio del módulo (administracion-app-E03).
//
// - Arriba, el porcentaje global y desde cuándo rige, en UTC, con "Fijar porcentaje". Sin global, "Sin porcentaje" y
//   ningún número (administracion-app-E32).
// - Debajo, "Por lavandería": cada una, de todas las plazas, con su plaza y, en una frase, su porcentaje propio y el que
//   le aplica, con de dónde sale (propio o global), como los da GET /api/administracion/comisiones. Cada una lleva
//   "Fijar porcentaje propio" y, si tiene uno, "Quitar porcentaje propio", que pide confirmarlo.
// - El porcentaje se escribe dentro de su tarjeta, vacío: la app no propone ninguno, porque los captura EL CLIENTE. Al
//   guardar, la pantalla pone las comisiones que responde el servidor (administracion-app-E33); con un 422, su mensaje
//   va junto al campo y el porcentaje vigente sigue a la vista, sin cambio (administracion-app-E34).
// - Al final, la comisión de los socios repartidores, que espera DD-019 (administracion-app-E35): solo su lugar, en
//   ámbar, con el renglón reservado del resumen del pedido, sin cifra ni botón (design.md, sección 5). La de cada pedido
//   espera DD-020 y DD-013 y no se calcula aquí.
//
// Si las comisiones no llegan, sin escenario propio: los mensajes generales de la app e "Intentar de nuevo", como la
// imagen 05 de la Propuesta 06. Con un 401, el cliente de la administración ya volvió al inicio de sesión
// (administracion-app-E05).
//
// La composición es la de las imágenes 28, 29, 30, 67 y 68 de la Propuesta 06: el encabezado con el regreso al inicio
// del módulo, la tarjeta del global, el título "Por lavandería" y una tarjeta por lavandería, con sus acciones como
// enlaces. El formulario lleva "Guardar" y "Cancelar" en una fila.
import { ActivityIndicator, StyleSheet, Text, View } from 'react-native';

import type { ComisionDeLaLavanderia, PorcentajeGlobal } from '../../src/administracion/comisiones/api';
import {
  deLaLavanderia,
  globalEnTexto,
  mensajeDeLasComisiones,
  queLeAplica,
  TEXTOS_DE_LAS_COMISIONES as TEXTOS,
  vigenteDesdeEnTexto,
} from '../../src/administracion/comisiones/textos';
import {
  esElDestino,
  useComisiones,
  type ComisionesDeLaPantalla,
  type FormularioDelPorcentaje,
} from '../../src/administracion/comisiones/useComisiones';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../src/administracion/rutas';
import { useRegresar } from '../../src/navegacion/regresar';
import { RenglonReservado } from '../../src/pedido/ResumenDelPedido';
import { Boton } from '../../src/piezas/Boton';
import { CampoDeTexto } from '../../src/piezas/CampoDeTexto';
import { Enlace } from '../../src/piezas/Enlace';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { TituloDeSeccion } from '../../src/piezas/TituloDeSeccion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, grosor, radio, tipografia } from '../../src/tema/theme';

export default function ComisionesDeLaAdministracion() {
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const pantalla = useComisiones();
  const { comisiones, falla, consultarDeNuevo } = pantalla;

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      {comisiones === null && falla === null ? (
        <View style={estilos.cargando}>
          <ActivityIndicator color={color.semantico.accion} />
          <Text style={[estilos.suave, letra('cuerpo', 'regular')]}>{TEXTOS.cargando}</Text>
        </View>
      ) : null}
      {falla ? (
        <>
          <MensajeDeError texto={mensajeDeLasComisiones(falla)} />
          <Boton variante="secundario" onPress={consultarDeNuevo}>
            {TEXTOS.intentarDeNuevo}
          </Boton>
        </>
      ) : null}
      {comisiones ? (
        <>
          <TarjetaDelGlobal global={comisiones.global} pantalla={pantalla} />
          <TituloDeSeccion>{TEXTOS.porLavanderia}</TituloDeSeccion>
          {comisiones.lavanderias.length === 0 ? <Parrafo alineacion="inicio">{TEXTOS.sinLavanderias}</Parrafo> : null}
          {comisiones.lavanderias.map((lavanderia) => (
            <TarjetaDeLaLavanderia key={lavanderia.id_publico} lavanderia={lavanderia} pantalla={pantalla} />
          ))}
          <EsperaDeLosRepartidores />
        </>
      ) : null}
    </Pantalla>
  );
}

// El global: su porcentaje, o "Sin porcentaje", y desde cuándo rige; debajo, "Fijar porcentaje" o su formulario
// (imágenes 28, 29 y 67).
function TarjetaDelGlobal({ global, pantalla }: { global: PorcentajeGlobal | null; pantalla: ComisionesDeLaPantalla }) {
  const destino = { tipo: 'global' } as const;
  const { formulario } = pantalla;
  return (
    <View style={estilos.tarjeta} testID="comision-global">
      <View style={estilos.cabecera}>
        <Text accessibilityRole="header" style={[estilos.tituloDeLaTarjeta, letra('titulares', 'fuerte')]}>
          {TEXTOS.porcentajeGlobal}
        </Text>
        <Text style={[estilos.valorDelGlobal, letra('cuerpo', 'semifuerte')]}>{globalEnTexto(global)}</Text>
      </View>
      {global ? (
        <View style={estilos.dato}>
          <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{TEXTOS.vigenteDesde}</Text>
          <Text style={[estilos.valor, letra('cuerpo', 'regular')]}>{vigenteDesdeEnTexto(global.vigente_desde)}</Text>
        </View>
      ) : null}
      {formulario && esElDestino(formulario, destino) ? (
        <Formulario etiqueta={TEXTOS.porcentaje} formulario={formulario} pantalla={pantalla} />
      ) : (
        <Boton variante="secundario" onPress={() => pantalla.abrir(destino)}>
          {TEXTOS.fijarPorcentaje}
        </Boton>
      )}
    </View>
  );
}

// Una lavandería: su nombre, su plaza y qué le aplica; debajo, sus acciones como enlaces, o el formulario de su
// porcentaje propio con lo que hace (imágenes 28, 30, 67 y 68).
function TarjetaDeLaLavanderia({
  lavanderia,
  pantalla,
}: {
  lavanderia: ComisionDeLaLavanderia;
  pantalla: ComisionesDeLaPantalla;
}) {
  const { id_publico: idPublico, nombre, plaza, propio } = lavanderia;
  const destino = { tipo: 'propio', idPublico } as const;
  const { formulario, quitando, fallaAlQuitar } = pantalla;
  const editando = formulario !== null && esElDestino(formulario, destino);
  return (
    <View style={estilos.tarjeta} testID={`comision-${idPublico}`}>
      <View style={estilos.dato}>
        <Text style={[estilos.nombre, letra('titulares', 'fuerte')]}>{nombre}</Text>
        <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{plaza.nombre}</Text>
        {editando ? (
          <Text style={[estilos.etiqueta, letra('cuerpo', 'regular')]}>{TEXTOS.queHaceElPropio}</Text>
        ) : (
          <Text style={[estilos.linea, letra('cuerpo', 'regular')]}>{queLeAplica(lavanderia)}</Text>
        )}
      </View>
      {editando ? (
        <Formulario etiqueta={TEXTOS.porcentajePropio} formulario={formulario} pantalla={pantalla} />
      ) : (
        <View style={estilos.enlaces}>
          <Enlace
            menor
            texto={TEXTOS.fijarPorcentajePropio}
            paraLeer={deLaLavanderia(TEXTOS.fijarPorcentajePropio, nombre)}
            alTocar={() => pantalla.abrir(destino)}
          />
          {propio !== null ? (
            <Enlace
              menor
              texto={TEXTOS.quitarPorcentajePropio}
              paraLeer={deLaLavanderia(TEXTOS.quitarPorcentajePropio, nombre)}
              alTocar={() => pantalla.quitar(lavanderia)}
            />
          ) : null}
          {quitando === idPublico ? <ActivityIndicator color={color.semantico.accion} /> : null}
        </View>
      )}
      {fallaAlQuitar?.idPublico === idPublico ? <MensajeDeError texto={fallaAlQuitar.mensaje} /> : null}
    </View>
  );
}

// El porcentaje en edición, dentro de su tarjeta: el campo, vacío al abrir, con el error del servidor junto a él, y
// "Guardar" y "Cancelar" en una fila (imágenes 29 y 30). El teclado es el de números con el punto decimal, el mismo del
// precio de un servicio.
function Formulario({
  etiqueta,
  formulario,
  pantalla,
}: {
  etiqueta: string;
  formulario: FormularioDelPorcentaje;
  pantalla: ComisionesDeLaPantalla;
}) {
  return (
    <View style={estilos.formulario}>
      <CampoDeTexto
        nombre="porcentaje"
        etiqueta={etiqueta}
        valor={formulario.texto}
        alCambiar={pantalla.escribir}
        error={formulario.error}
        tipo="precio"
      />
      <MensajeDeError texto={formulario.mensaje} />
      <View style={estilos.botones}>
        <View style={estilos.boton}>
          <Boton onPress={pantalla.guardar} ocupado={formulario.guardando}>
            {TEXTOS.guardar}
          </Boton>
        </View>
        <View style={estilos.boton}>
          <Boton variante="secundario" onPress={pantalla.cerrar}>
            {TEXTOS.cancelar}
          </Boton>
        </View>
      </View>
    </View>
  );
}

// El lugar de la comisión de los socios repartidores, que espera DD-019, en su tarjeta: el renglón reservado del
// resumen del pedido, con su nombre y la marca en ámbar "Por confirmar · DD-019", sin cifra ni botón.
function EsperaDeLosRepartidores() {
  return (
    <View style={estilos.tarjeta}>
      <RenglonReservado
        testID="comision-de-los-repartidores"
        etiqueta={TEXTOS.comisionDeLosRepartidores}
        dd={TEXTOS.decisionDeLosRepartidores}
      />
    </View>
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
  // La tarjeta de las imágenes 28 y 67.
  tarjeta: {
    gap: espaciado[3],
    padding: espaciado[4],
    borderWidth: grosor.control,
    borderColor: color.semantico.borde,
    borderRadius: radio.grande,
    backgroundColor: color.semantico.superficie,
  },
  cabecera: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: espaciado[3],
  },
  tituloDeLaTarjeta: {
    flexShrink: 1,
    color: color.semantico.texto,
    fontSize: tipografia.escala.titulo_menor,
    lineHeight: tipografia.escala.titulo_menor * tipografia.interlineado.apretado,
  },
  valorDelGlobal: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  dato: {
    gap: espaciado[1],
  },
  etiqueta: {
    color: color.semantico.texto_suave,
    fontSize: tipografia.escala.menor,
  },
  valor: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo,
  },
  nombre: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
    lineHeight: tipografia.escala.cuerpo_grande * tipografia.interlineado.apretado,
  },
  linea: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.menor,
  },
  enlaces: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    columnGap: espaciado[5],
  },
  formulario: {
    gap: espaciado[3],
  },
  botones: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: espaciado[2],
  },
  boton: {
    flex: 1,
  },
});
