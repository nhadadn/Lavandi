---
estado: vigente
grado: D
fuente: acta-2026-11-13-cierre-de-la-fase-6.md C-152; restricciones-no-negociables.md RN-05 (A); brecha-ejecutivo-tecnico.md BR-23 (B); c2-contenedores.md (B); verificaciones-fase-6.md, sección del 2026-11-12 (E); ADR-0002 y ADR-0003 (D); adenda del 2026-11-22: acta-2026-11-20-modelo-y-pasarela.md, decisión 33, por delegación de C-236 (C); NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4 (A)
fecha: 2026-11-13
dueño: Arthur Naranjo
adr: ADR-0007
supersede:
---
# ADR-0007 — Tiempo real: consulta cada 30 segundos más un aviso al cambiar el estado

## Contexto

- **Qué pide el contrato (A: RN-05, A.4.3):** un cambio de estado que haga la lavandería o el repartidor se tiene
  que ver en la app del usuario **en 60 segundos o menos**. El método advierte "sin sobreingeniería" (BR-23).
- **El panel (A: O-16):** monitorea los pedidos activos "en tiempo real".
- **Dónde vive la responsabilidad (B, C2):** el cambio de estado entra por `api`, que es el único que lo autoriza y lo
  propaga. Ningún cliente notifica a otro directamente.
- **El stack (D):** app en React Native con Expo (`ADR-0002`) y backend en Laravel (`ADR-0003`).
  - Laravel Reverb no tiene cliente oficial para React Native (E).

## Criterios y pesos

Los de la matriz `vigente` (C-142), con la escala del `ADR-0002`: de 1 a 5, total sobre 100.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Consulta cada 30 s en las pantallas del pedido, más un aviso FCM al cambiar el estado** | 5 | 5 | 5 | 5 | 5 | 5 | **100** |
| B. Laravel Reverb (websockets) | 3 | 3 | 5 | 4 | 4 | 5 | **74** |
| C. Pusher o Ably | 3 | 4 | 2 | 4 | 2 | 5 | **66** |

**Por qué cada puntaje:**

- **A** son peticiones HTTP normales a la API y un aviso que el ADR-0008 ya exige. No suma piezas nuevas.
- **B** pide un proceso más en el servidor, y su cliente para React Native es de la comunidad.
- **C** suma un proveedor más. Pusher pasa de su plan gratuito a 49 USD al mes (E), y Ably es gratis solo hasta
  500 usuarios activos al mes (E).
- **En tiempo real, A tiene la calificación más alta** porque cumple los 60 s sin construir de más. B y C dan
  segundos, cuando el contrato pide un minuto.

## Decisión

**Con la app abierta,** la pantalla del pedido consulta su estado a la API cada 30 segundos. Así, el peor caso, con
red normal, queda por debajo de 60 s.

**Al cambiar el estado,** `api` manda además un aviso FCM:

- con la app cerrada, lo ve el usuario;
- con la app abierta, provoca una consulta inmediata.

**El panel de Filament** refresca las tablas de pedidos activos con la misma consulta periódica de 30 s.

La aprobó Arthur Naranjo el 2026-11-13 (`acta-2026-11-13-cierre-de-la-fase-6.md`, C-152), con la opción que recomendó Claude.

## Consecuencias

- **Positivas:**
  - Cuesta cero.
  - No agrega servicios ni procesos.
  - Funciona igual en las dos plataformas.
  - Se puede probar con un cronómetro.
- **Negativas:**
  - Genera más peticiones que un websocket: una cada 30 s por pantalla de pedido abierta. Con volumen de arranque
    es despreciable.
  - Si el usuario niega los avisos, igual se cumple con la app abierta. Con la app cerrada, ve el estado al volver.
- **Condiciona:** ADR-0008 (el aviso de cambio de estado) y la spec del pedido (la consulta solo corre con la
  pantalla del pedido visible).

## Criterio de reversión

Se reabre si en las pruebas de aceptación algún cambio de estado tarda **más de 60 s** en verse con la app
abierta y red normal. También se reabre si la carga de consultas supera lo que el hosting de DD-070 aguanta dentro
del ANEXO B.4.

## Costo estimado de revertir

Bajo. El cambio a Reverb solo toca el mecanismo de refresco de la pantalla del pedido y del panel. La API y los
avisos no cambian.

## Trazabilidad

- **Obligaciones:** O-16 y O-17.
- **Restricciones:** RN-05 y BR-23.
- **ADRs relacionados:** ADR-0002, ADR-0003 y ADR-0008.

## Adenda del 2026-11-22: cuánto es "en tiempo real" para los indicadores

**Contexto.**

- El A.4.4 pide que el panel muestre "en tiempo real los indicadores definidos en A.5"
  (`NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.4`). No dice con qué latencia (BR-33).
- Este ADR fijó 30 s para la pantalla del pedido y para las tablas de pedidos activos del panel, no para los
  indicadores. DD-116 le dejó a Arthur la cifra, con ADR.
- El panel ya lee el intervalo de `indicadores.refresco` (`INDICADORES_REFRESCO`), que estaba vacío a la espera de esta
  decisión.

**Decisión.**

- **Los widgets de indicadores se refrescan cada 30 s, la misma consulta periódica de este ADR.** El valor por omisión
  de `indicadores.refresco` es `30s`; cada entorno lo puede cambiar sin tocar el código.
- **Por qué 30 s.** Es la latencia que ya acepta el resto del panel. Los indicadores leen agregados de la base, no un
  pedido, y un intervalo más corto multiplica las consultas sin que la administración note la diferencia. No se agrega
  un websocket solo para esto: el criterio de reversión de este ADR sigue igual.

**La tomó** Claude, por delegación de Arthur (C-236), en la decisión 33 de `acta-2026-11-20-modelo-y-pasarela.md`.
Cierra DD-116 y la parte de latencia de BR-33. De BR-33 siguen abiertos los umbrales del semáforo del ranking.
