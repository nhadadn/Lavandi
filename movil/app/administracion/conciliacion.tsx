// Conciliación, la conciliación con la pasarela de los reportes financieros (F-D06; NSV-2026-001-v8.pdf:19:ANEXO A,
// Sección A.3, Módulo D #6, "conciliación con Conekta"; administracion-app-E38): se abre con "Conciliación" en el inicio
// del módulo (administracion-app-E03). La administración escribe el periodo, toca "Conciliar un periodo" y lo confirma;
// el servidor lee la pasarela, guarda sus movimientos y responde el reporte, con las reglas y las clases del panel
// (administracion-E20 y E21; ADR-0022, decisión 9). Antes de conciliar no pide nada, como el panel. No se pone al día
// sola.
//
// La composición es la de las imágenes 32 a 35 de la Propuesta 06, con la confirmación y los textos del panel de
// respaldo (servidor/app/Filament/Pages/Conciliacion.php):
// - el periodo, con "Desde" y "Hasta" en DD/MM/AAAA y el 422 del servidor junto a su campo, como "La fecha de fin no
//   puede ser anterior a la de inicio." (32). Antes de confirmar, cada fecha tiene que ser una DD/MM/AAAA que existe,
//   como en el filtro de "Mis pedidos" (src/historial/FiltroPorFecha.tsx): si está vacía, a medias o no existe, lo dice
//   junto a su campo y no se confirma ni se manda nada. El servidor no impone el formato: una fecha así le llegaría con
//   un mensaje en inglés o, peor, conciliaría otro periodo (el contrato, en reportes-financieros/api.ts). El rango al
//   revés sí es su regla;
// - el reporte: el periodo, cuántas filas hay de cada resultado, en el orden de la imagen 33, con el lugar de la
//   comisión de la pasarela en ámbar (administracion-app-E41; DD-013), "Descargar" y "Otro periodo", y debajo cada
//   diferencia con su tipo y después las conciliadas (33 y 34). "Descargar" pide el reporte de la conciliación con el
//   periodo conciliado por omisión, sin granularidad, y el servidor lo arma con los movimientos ya guardados, sin volver
//   a leer la pasarela (administracion-app-E56, E57 y E60; tarea 20.1; src/administracion/reportes-financieros/
//   DescargaDelReporte.tsx);
// - si la pasarela no responde, "No se pudo leer la pasarela" bajo el botón, sin reporte nuevo (35).
//
// Con un 401, el cliente de la administración ya volvió al inicio de sesión (administracion-app-E05).
import { useState } from 'react';
import { StyleSheet, Text, View } from 'react-native';

import { errorDeCampo } from '../../src/acceso/mensajes';
import { confirmar } from '../../src/administracion/confirmar';
import { conDiagonales, fechaDeTexto } from '../../src/administracion/rango/rango';
import type {
  ConteoDeLaConciliacion,
  FallaDeLaConciliacion,
  FilaDeLaConciliacion,
  ReporteDeLaConciliacion,
  ResultadoDeLaConciliacion,
} from '../../src/administracion/reportes-financieros/api';
import { DescargaDelReporte } from '../../src/administracion/reportes-financieros/DescargaDelReporte';
import { TarjetaDeLaFila } from '../../src/administracion/reportes-financieros/TarjetaDeLaFila';
import { TarjetaDeRenglones } from '../../src/administracion/reportes-financieros/TarjetaDeRenglones';
import {
  DD_DE_LOS_REPORTES,
  mensajeDeLosReportes,
  TEXTOS_DE_LA_CONCILIACION as TEXTOS,
} from '../../src/administracion/reportes-financieros/textos';
import { useConciliacion } from '../../src/administracion/reportes-financieros/useConciliacion';
import { RUTA_DEL_INICIO_DE_LA_ADMINISTRACION } from '../../src/administracion/rutas';
import { useRegresar } from '../../src/navegacion/regresar';
import { Boton } from '../../src/piezas/Boton';
import { CampoDeTexto } from '../../src/piezas/CampoDeTexto';
import { MensajeDeError } from '../../src/piezas/MensajeDeError';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { TituloDeSeccion } from '../../src/piezas/TituloDeSeccion';
import { letra } from '../../src/tema/letra';
import { color, espaciado, tipografia } from '../../src/tema/theme';

type Campo = 'desde' | 'hasta';

const CAMPOS: readonly Campo[] = ['desde', 'hasta'];

// El orden de los renglones del resumen, el de la imagen 33: lo conciliado y después cada diferencia. El servidor los
// manda en el de App\Finanzas\ResultadoDeConciliacion; la app no cambia ningún texto ni ninguna cuenta.
const ORDEN_DEL_RESUMEN: readonly ResultadoDeLaConciliacion[] = [
  'conciliado',
  'monto_distinto',
  'movimiento_sin_pedido',
  'pedido_sin_movimiento',
];

function enElOrdenDelResumen(resumen: readonly ConteoDeLaConciliacion[]): ConteoDeLaConciliacion[] {
  return [...resumen].sort(
    (uno, otro) => ORDEN_DEL_RESUMEN.indexOf(uno.resultado) - ORDEN_DEL_RESUMEN.indexOf(otro.resultado),
  );
}

export default function Conciliacion() {
  const regresar = useRegresar(RUTA_DEL_INICIO_DE_LA_ADMINISTRACION);
  const { reporte, falla, conciliando, conciliar, otroPeriodo } = useConciliacion();
  const [periodo, setPeriodo] = useState<Record<Campo, string>>({ desde: '', hasta: '' });
  // Las fechas que la app no manda, con su error junto al campo.
  const [errores, setErrores] = useState<Partial<Record<Campo, string>>>({});

  function pedirConfirmacion() {
    const desde = fechaDeTexto(periodo.desde);
    const hasta = fechaDeTexto(periodo.hasta);
    setErrores({
      desde: desde === null ? TEXTOS.fechaInvalida : undefined,
      hasta: hasta === null ? TEXTOS.fechaInvalida : undefined,
    });
    if (desde === null || hasta === null) {
      return;
    }
    confirmar({
      titulo: TEXTOS.confirmarTitulo,
      texto: TEXTOS.confirmarTexto,
      accion: TEXTOS.confirmarAccion,
      alConfirmar: () => void conciliar({ desde, hasta }),
    });
  }

  // El error de cada campo: el de la app, o el que dio el servidor con el último periodo.
  function errorDe(campo: Campo): string | undefined {
    return errores[campo] ?? errorDeCampo(falla?.motivo === 'invalido' ? falla : undefined, campo);
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.titulo, alRegresar: regresar }}>
      {reporte ? (
        <ElReporte reporte={reporte} alPedirOtroPeriodo={otroPeriodo} />
      ) : (
        <>
          <Parrafo alineacion="inicio">{TEXTOS.instrucciones}</Parrafo>
          {CAMPOS.map((campo) => (
            <CampoDeTexto
              key={campo}
              nombre={campo}
              etiqueta={TEXTOS[campo]}
              tipo="fecha"
              valor={periodo[campo]}
              alCambiar={(texto) => setPeriodo((anterior) => ({ ...anterior, [campo]: conDiagonales(texto) }))}
              error={errorDe(campo)}
            />
          ))}
          <Boton onPress={pedirConfirmacion} ocupado={conciliando}>
            {TEXTOS.conciliarUnPeriodo}
          </Boton>
          {falla ? <LoQueNoSalio falla={falla} /> : null}
        </>
      )}
    </Pantalla>
  );
}

// Lo que no salió, bajo el botón: la pasarela que no respondió, con el aviso del panel, o el mensaje general de la app.
// Los errores de "Desde" y "Hasta" ya van junto a su campo.
function LoQueNoSalio({ falla }: { falla: FallaDeLaConciliacion }) {
  if (falla.motivo !== 'sin-pasarela') {
    return <MensajeDeError texto={mensajeDeLosReportes(falla, CAMPOS)} />;
  }
  return (
    <View role="alert" accessibilityLiveRegion="polite" style={estilos.sinPasarela}>
      <Text style={[estilos.error, letra('cuerpo', 'fuerte')]}>{TEXTOS.sinPasarela}</Text>
      <Text style={[estilos.error, letra('cuerpo', 'regular')]}>{TEXTOS.pasarelaNoDisponible}</Text>
    </View>
  );
}

// El reporte del periodo conciliado: el periodo, el resumen, "Descargar" y "Otro periodo", y las filas, primero las
// diferencias.
function ElReporte({
  reporte,
  alPedirOtroPeriodo,
}: {
  reporte: ReporteDeLaConciliacion;
  alPedirOtroPeriodo: () => void;
}) {
  const diferencias = reporte.filas.filter((fila) => fila.resultado !== 'conciliado');
  const conciliados = reporte.filas.filter((fila) => fila.resultado === 'conciliado');
  return (
    <>
      <Text style={[estilos.periodo, letra('cuerpo', 'fuerte')]}>{reporte.rango.descripcion}</Text>
      <TarjetaDeRenglones
        testID="resumen-de-la-conciliacion"
        renglones={[
          ...enElOrdenDelResumen(reporte.resumen).map((conteo) => ({
            clave: conteo.resultado,
            etiqueta: conteo.resultado_texto,
            valor: String(conteo.cuantos),
          })),
          {
            clave: 'comision-de-la-pasarela',
            etiqueta: TEXTOS.comisionDeLaPasarela,
            esperaA: DD_DE_LOS_REPORTES.comisionDeLaPasarela,
          },
        ]}
      />
      <DescargaDelReporte
        reporte="conciliacion-con-la-pasarela"
        desde={reporte.rango.desde}
        hasta={reporte.rango.hasta}
        junto={
          <Boton variante="secundario" onPress={alPedirOtroPeriodo}>
            {TEXTOS.otroPeriodo}
          </Boton>
        }
      />
      {reporte.filas.length === 0 ? <Parrafo alineacion="inicio">{TEXTOS.sinPagos}</Parrafo> : null}
      <Filas titulo={TEXTOS.diferencias} testID="diferencias" filas={diferencias} />
      <Filas titulo={TEXTOS.conciliados} testID="conciliados" filas={conciliados} />
    </>
  );
}

// Las filas bajo su título, en el orden del servidor, de la más antigua a la más nueva; sin filas, nada.
function Filas({ titulo, testID, filas }: { titulo: string; testID: string; filas: FilaDeLaConciliacion[] }) {
  if (filas.length === 0) {
    return null;
  }
  return (
    <View style={estilos.filas} testID={testID}>
      <TituloDeSeccion>{titulo}</TituloDeSeccion>
      {filas.map((fila) => (
        <TarjetaDeLaFila key={fila.orden} fila={fila} />
      ))}
    </View>
  );
}

const estilos = StyleSheet.create({
  periodo: {
    color: color.semantico.texto,
    fontSize: tipografia.escala.cuerpo_grande,
  },
  filas: {
    gap: espaciado[3],
  },
  sinPasarela: {
    gap: espaciado[1],
  },
  error: {
    color: color.estado.error,
    fontSize: tipografia.escala.cuerpo,
    lineHeight: tipografia.escala.cuerpo * tipografia.interlineado.normal,
  },
});
