## Context

- El Módulo D vive en el panel web hecho con Filament (ADR-0003; DD-040 cerrada por C-140). La app trae además una
  vista reducida para el administrador, cuyo contenido no está definido (`c2-contenedores.md`). Este cambio no la toca.
- Cubre F-D03 a F-D07. F-D01 y F-D02 viven en `alta-de-lavanderias` y en `repartidor`.
- Se apoya en piezas de otros cambios, que llegan antes en el plan: las plazas y zonas, el rol de administración y la
  entrada al panel (ADR-0005), los estados del pedido, el pago confirmado por el webhook de la pasarela (ADR-0010), los
  dispositivos registrados para avisos (ADR-0008) y las direcciones con coordenadas (ADR-0009).
- Lo que más falta es del cliente: el modelo del dinero (DD-013) y la comisión (DD-020).
- La pasarela es Conekta, la del contrato, mientras el cliente no firme el cambio (DD-079, C-192, C-216). Las tablas
  de este cambio no nombran proveedor; la conciliación es con Conekta y espera DD-112, no la firma.
- Las tablas siguen `vault/arquitectura/modelo-de-datos.md` y ADR-0016 (C-193).

## Goals / Non-Goals

**Goals:**

- Las cinco funciones con lo mínimo que cumple el A.4.3: cuatro módulos funcionales y datos reales de pedidos y
  usuarios activos.
- Las comisiones configurables desde ya, sin valores de fábrica, para que DD-020 solo llene datos.
- Una sola fuente para ingresos y comisiones, que usan también `indicadores` y `exportacion`.

**Non-Goals:**

- Mover dinero: ni liquidar ni dispersar a los socios (`NSV-2026-001-v8.pdf:18:ANEXO A, Sección A.3`, Módulo C).
- Rastrear al repartidor en el mapa.
- Dar de alta, editar o borrar usuarios.
- La vista reducida de administración en la app.
- Asignar repartidores (DD-014, cambio `repartidor`).

## Decisions

No hay decisión técnica nueva. Se aplican los ADR vigentes:

- **Panel:** recursos y páginas de Filament, con sesión web y rol de administración (ADR-0003, ADR-0005).
- **Refresco del monitoreo:** consulta periódica de 30 s (ADR-0007).
- **Mapa del monitoreo:** Google Maps JavaScript, con plugin de Filament o componente propio (ADR-0009).
- **Avisos masivos:** FCM desde `api` con `laravel-notification-channels/fcm` (ADR-0008).
- **Ingresos:** solo cuentan los pagos que la pasarela confirmó por webhook, los de `pagos.estado = 'pagado'`
  (ADR-0010).
- **Plaza y horas:** toda fila de operación lleva su plaza; las horas se guardan como instante absoluto y se presentan
  en la zona de la plaza (ADR-0004, `modelo-multi-plaza.md`).

**Lecturas del contrato que hizo el especificador.** No son decisiones técnicas, y Arthur las confirma al aprobar:

1. **Pedido activo** es el confirmado que no se ha entregado ni cancelado. El A.5 separa activos, completados y
   cancelados (`NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5`).
2. **"Por socio o global"**: el porcentaje propio de un socio prevalece sobre el global.
3. **"Bloqueo"** incluye levantarlo.
4. **"Ingresos"** es lo cobrado con pago confirmado; las comisiones van aparte, como las enumera el Módulo D #6.
5. **El mapa** pone un marcador en la dirección de recolección y otro en la de entrega si es distinta (BR-30).

Con C-188, "confirmado" es pagado: el pedido llena `confirmado_en` solo cuando la pasarela confirma el pago. Un pedido
sin pago acreditado no está activo y no entra al monitoreo (lectura 1).

## Tablas y migración

Una sola migración, hacia adelante (ADR-0016, regla 9), en la tarea 0.1. Pasa la prueba de esquema de
`modelo-de-datos` (modelo-E01 a modelo-E17). Ninguna tabla de este cambio lleva el trigger `impedir_cambio()`, que es
solo de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos`. Todas las llaves foráneas llevan su índice y
`ON DELETE RESTRICT`.

**Crea:**

1. `comisiones` (E11 a E16 y E19):
   - Columnas: `id_comisiones`, `id_plazas`, `id_lavanderias`, `porcentaje`, `vigente_desde`, `vigente_hasta`.
   - Llaves foráneas: `id_plazas` → `plazas.id_plazas`; `(id_lavanderias, id_plazas)` →
     `lavanderias(id_lavanderias, id_plazas)`, compuesta con la plaza.
   - `id_lavanderias` vacío es la comisión global de la plaza (E11); con valor, la propia de esa lavandería (E12).
   - `porcentaje` es `numeric(5,2)`, con `CHECK` de 0 a 100 (E13).
   - `vigente_desde` y `vigente_hasta` son `timestamptz`, con `CHECK` de que la vigencia termina después de empezar
     (la regla general de integridad del modelo: "ventana que termina después de empezar").
     Un cambio de porcentaje es una fila nueva y la anterior queda como historial (`modelo-de-datos.md`); qué pasa con
     los pedidos ya cobrados es DD-020 (E16).
   - La migración no siembra ningún porcentaje: los captura EL CLIENTE.
2. `avisos_masivos` (E23 y E24):
   - Columnas: `id_avisos_masivos`, `id_plazas`, `segmento`, `titulo`, `mensaje`, `id_usuarios`, `enviado_en`,
     `destinatarios`.
   - Llaves foráneas: `id_plazas` → `plazas.id_plazas`; `id_usuarios` → `usuarios.id_usuarios`, la cuenta de la
     administración que lo envió.
   - `id_plazas` es **opcional**: vacío es un aviso global (E23). Por eso `avisos_masivos` entra a la lista de "plaza
     opcional" de la prueba de esquema de `modelo-de-datos`. Con valor, su llave foránea a `plazas` se revisa como en
     cualquier otra tabla.
   - `segmento` es `jsonb`; sus criterios son DD-113 (E24).
   - `enviado_en` es `timestamptz`. `destinatarios` guarda a cuántos dispositivos se mandó (propuesta: el modelo no
     dice su tipo).

Las dos tablas llevan `created_at` y `updated_at` en `timestamptz`, como todas las tablas del negocio con llave propia.

**En espera (⛔):** `movimientos_pasarela` (E20 a E22) toma la forma que fije DD-112, según de dónde salgan los datos
de Conekta (de la elegida, si se firma el cambio, DD-079). Es la tarea 0.2, bloqueada por DD-112. No nombra a ningún proveedor (modelo-E14).

**Los modelos de Eloquent** (`Comision` y `AvisoMasivo`) heredan de `App\Models\Modelo` y declaran su `$table` y su
`$primaryKey` (modelo-E16).

**Solo usa** tablas que crean otros cambios:

| Tabla | Para qué | La crea |
|---|---|---|
| `usuarios` | La lista y la ficha (E01); el bloqueo escribe `estado = 'bloqueado'` y `bloqueado_en`, y levantarlo vuelve a `activo` (E03 y E04) | `registro-e-inicio-de-sesion` |
| `rol_usuario`, `roles` y `cuentas_externas` | La entrada al panel con rol de administración; rechazar la entrada con Google y Apple de una cuenta bloqueada (E03) | `registro-e-inicio-de-sesion` |
| `personal_access_tokens` | Revocar los tokens de Sanctum al bloquear (E03). Es del framework | `base-tecnica` |
| `actividad_usuarios` | Los usuarios activos del periodo (E02, bloqueado por DD-111) | `indicadores`, que llega después en el plan |
| `pedidos` y `estados_pedido` | El historial del usuario (E01) y los pedidos activos (E06 a E08) | `resumen-y-confirmacion` |
| `pedido_eventos` | Los cambios de estado que refresca el monitoreo (E07) | `resumen-y-confirmacion` (base) y `estados-del-pedido` |
| `pedido_direcciones` | Los marcadores de recolección y de entrega, de su `ubicacion` (E09 y E10) | `resumen-y-confirmacion` |
| `lavanderias` | El porcentaje propio por socio (E12) y la lavandería del historial (E01) | `perfil-de-lavanderia` y `alta-de-lavanderias` |
| `plazas` | La plaza de cada pedido y la `zona_horaria` de los reportes (E06 y E17) | `plazas-y-zonas` |
| `pagos` | Los ingresos: `estado = 'pagado'`, `monto` y `pagado_en` (E17) | `pago` |
| `reembolsos` | Los reembolsos en los ingresos (E18, bloqueado) | `pago` |
| `dispositivos` | Los `token_fcm` a los que va un aviso masivo (E23) | `avisos` |
| `repartidores` | La comisión de un repartidor (E14, bloqueado por DD-019) | `repartidor` |

## Risks / Trade-offs

- [El plugin de mapas de Filament está en beta] → se hace como componente propio (ADR-0009). Si el mapa sin código a
  la medida pasa de 8 h, se reabre el ADR-0003, según su criterio de reversión.
- [DD-079: el cliente firma el cambio de Conekta por Stripe o Mercado Pago (C-192, C-216)] → la conciliación se hace
  con Conekta y espera DD-112; si se firma el cambio, la lectura de la elegida entra con su ADR. No se construye nada
  de Stripe ni de Mercado Pago mientras tanto. El
  resto del cambio no depende del proveedor: lee `pagos`, que no lo nombra.
- [DD-056: el efectivo sin OXXO] → la conciliación de los pagos en efectivo espera a que se cierre.
- [La comisión no tiene base (DD-020)] → el porcentaje se configura desde ya; el cálculo, el reporte de comisiones y
  el indicador F-I10 quedan bloqueados.
- [Un aviso global a muchos dispositivos] → el envío va en cola para no detener el panel. Cuántos destinos acepta FCM
  por lote es grado E, `SIN VERIFICAR`.
- [Bloquear a alguien con un pedido activo] → el bloqueo solo cierra la entrada y no cambia el estado de ningún
  pedido. Lo demás lo dice DD-016 (administracion-E05).

## Open Questions

1. **Qué entra en la vista reducida de administración de la app** (C-140). Pregunta para Arthur; no se diseña aquí.
2. **De dónde salen los movimientos de la pasarela para conciliar:** su API o el reporte que se descarga de su panel.
   Necesita ADR. Es DD-112, con Conekta (con la elegida, si se firma el cambio, DD-079). Con el modelo de datos,
   `movimientos_pasarela` no nace hasta que se responda DD-112, así que E20 y E21 quedan bloqueados, como ya dice la
   fila DD-112.
3. **Avisos masivos: temas de FCM o lista de tokens.** Condiciona cómo se arman los segmentos. Necesita ADR, o
   ampliar el ADR-0008.
4. **El porcentaje global es por plaza, según el modelo de datos** (ADR-0016, C-193): `comisiones` lleva `id_plazas`,
   e `id_lavanderias` vacío es la comisión global de la plaza. Si el "global" del contrato es uno solo para todo el
   territorio, se guarda la misma cifra en cada plaza y E11 se lee como el global de la plaza. Arthur lo confirma.
5. **Un aviso masivo y la preferencia de avisos del perfil** (F-A15, cambio `perfil-y-soporte`): si quien apagó los
   avisos recibe uno de mantenimiento. Depende de cómo defina ese cambio "notificaciones".
6. **La entrada al panel con rol de administración** (ADR-0005) se espera de `autorizacion-por-rol` o de
   `alta-de-lavanderias`, que llegan antes (sprints 11 y 12). Si ninguno la trae, hay que agregarla aquí.
7. **`actividad_usuarios` nace después.** La crea `indicadores` (sprints 24 a 29) y la lee administracion-E02 (sprint
   21). E02 ya está bloqueado por DD-111; cuando se libere, necesita esa tabla.
8. **El aviso global y la plaza: resuelta.** `avisos_masivos.id_plazas` es opcional y la tabla entra a la lista de
   "plaza opcional" de la prueba de esquema de `modelo-de-datos`.
9. **La comisión del repartidor (E14).** `comisiones` solo tiene `id_lavanderias`. Si DD-019 dice que la plataforma le
   retiene algo al repartidor, falta una columna en el modelo; entraría como opcional, hacia adelante (ADR-0016,
   regla 9).
10. **La plaza de `movimientos_pasarela`.** No está en la lista de tablas sin plaza de `servidor/config/modelo.php`.
    Si los movimientos de la pasarela no traen plaza, como `eventos_pasarela`, hay que agregarla a esa lista al
    responder DD-112.
