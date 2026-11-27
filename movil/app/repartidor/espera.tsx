// La espera del repartidor que todavía no opera (openspec/changes/repartidor, repartidor-E01 y E03): el que se acaba de
// registrar, o que inició sesión antes de su alta, ve que su registro espera la autorización, que corresponde a EL CLIENTE
// (NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3, Módulo C #1); el suspendido (repartidor-E06), que su cuenta está
// suspendida. Los dos mensajes son los que da el servidor en el 403 de sus pedidos
// (servidor/app/Repartidores/RepartidorSinAcceso.php). Ninguno ve sus pedidos ni la app los pide.
//
// "Revisar de nuevo" vuelve a pedir su perfil: si ya lo dieron de alta, el módulo abre sus pedidos
// (app/repartidor/_layout.tsx). Sin escenario propio: no se consulta solo, porque el contrato pide la consulta periódica
// para la lista de pedidos (ADR-0007), no para esta pantalla. "Cerrar sesión" va abajo, con la confirmación de la app
// (registro-E13). No tiene mock ni componente en el design system: se arma con las piezas y los tokens (ADR-0013).
import { Boton } from '../../src/piezas/Boton';
import { Pantalla } from '../../src/piezas/Pantalla';
import { Parrafo } from '../../src/piezas/Parrafo';
import { Titulo } from '../../src/piezas/Titulo';
import { useRepartidorDeLaCuenta } from '../../src/repartidor/ProveedorDelRepartidor';
import { TEXTOS_DEL_REPARTIDOR as TEXTOS } from '../../src/repartidor/textos';
import { CerrarSesion } from '../../src/sesion/CerrarSesion';

export default function EsperaDelRepartidor() {
  const { estado, releyendo, leerDeNuevo } = useRepartidorDeLaCuenta();
  const suspendido = estado.fase === 'listo' && estado.repartidor.estado === 'suspendido';

  return (
    <Pantalla
      justificar="center"
      acciones={
        <>
          <Boton ocupado={releyendo} onPress={leerDeNuevo}>
            {TEXTOS.revisarDeNuevo}
          </Boton>
          <CerrarSesion />
        </>
      }
    >
      <Titulo alineacion="inicio">{suspendido ? TEXTOS.cuentaSuspendida : TEXTOS.registroEnviado}</Titulo>
      <Parrafo alineacion="inicio">{suspendido ? TEXTOS.estaSuspendida : TEXTOS.esperaLaAutorizacion}</Parrafo>
      <Parrafo alineacion="inicio">{suspendido ? TEXTOS.mientrasSuspendida : TEXTOS.cuandoTeDenDeAlta}</Parrafo>
    </Pantalla>
  );
}
