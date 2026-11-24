// La entrega de un pedido en la app del repartidor, con su foto de evidencia (openspec/changes/repartidor,
// repartidor-E23; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Almacenamiento de imágenes: "las evidencias de entrega";
// ADR-0006). Se abre con "Entregado" en la tarjeta del pedido En tránsito, por su id_publico. Muestra el pedido con la
// tarjeta del componente 5, sin sus botones, y la foto de evidencia, que se toma con la cámara, no se elige de la galería:
// es de lo que pasó en la entrega. La app la comprime con los parámetros de configuración y "Marcar entregado" manda
// Entregado y la foto juntos (src/repartidor/api.ts). Al marcarlo, el pedido sale de su lista y la app vuelve a ella. Lo
// que el servidor no acepta de la foto va junto a ella.
//
// Si la evidencia es obligatoria, en qué momentos y si es foto o firma espera BR-29 y DD-110 (repartidor-E24): hoy el
// servidor la acepta opcional, y "Marcar entregado" se puede tocar sin foto. Es la composición del rechazo: el título,
// el pedido, la foto y el botón; "Regresar" vuelve a la lista sin marcar nada. No tiene mock ni componente en el design
// system: se arma con las piezas y los tokens (ADR-0013).
import { useLocalSearchParams } from 'expo-router';
import { useState } from 'react';

import { useRegresar } from '../../../../src/navegacion/regresar';
import { Boton } from '../../../../src/piezas/Boton';
import { CampoDeFoto } from '../../../../src/piezas/CampoDeFoto';
import { MensajeDeError } from '../../../../src/piezas/MensajeDeError';
import { Pantalla } from '../../../../src/piezas/Pantalla';
import { Parrafo } from '../../../../src/piezas/Parrafo';
import { TarjetaDePedidoDeRepartidor } from '../../../../src/piezas/TarjetaDePedidoDeRepartidor';
import { RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR } from '../../../../src/repartidor/rutas';
import { TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../../../src/repartidor/textos';
import { useEntregaDelRepartidor } from '../../../../src/repartidor/useEntregaDelRepartidor';

export default function EntregaDelPedido() {
  const regresar = useRegresar(RUTA_DE_LOS_PEDIDOS_DEL_REPARTIDOR);
  const { id } = useLocalSearchParams<{ id: string }>();
  const { pedido, enviando, entregar, errorDeLaFoto, errorGeneral } = useEntregaDelRepartidor(id);
  const [foto, setFoto] = useState<string | null>(null);

  async function confirmar() {
    if (await entregar(foto)) {
      regresar();
    }
  }

  return (
    <Pantalla desplazable encabezado={{ titulo: TEXTOS.tituloDeLaEntrega, alRegresar: regresar }}>
      {pedido ? <TarjetaDePedidoDeRepartidor pedido={pedido} /> : null}
      <Parrafo alineacion="inicio">{TEXTOS.paraLaEvidencia}</Parrafo>
      <CampoDeFoto
        nombre="foto"
        soloCamara
        textos={{
          titulo: TEXTOS.fotoDeEvidencia,
          laFoto: TEXTOS.laFotoDeLaEvidencia,
          tomarFoto: TEXTOS.tomarFoto,
          sinPermisoDeLaCamara: TEXTOS.sinPermisoParaLaEvidencia,
          fotoNoPreparada: TEXTOS.fotoNoPreparada,
        }}
        foto={foto}
        alCambiar={setFoto}
        error={errorDeLaFoto}
        deshabilitado={enviando}
      />
      <MensajeDeError texto={errorGeneral} />
      <Boton ocupado={enviando} onPress={() => void confirmar()}>
        {TEXTOS.marcarEntregado}
      </Boton>
    </Pantalla>
  );
}
