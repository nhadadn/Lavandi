## Context

- ADR-0012 fija tres entornos y a nombre de quién va cada cuenta: local y prueba pueden arrancar en cuentas de EL
  PRESTADOR; producción va siempre en cuentas de EL CLIENTE, con Arthur como colaborador revocable. **Mientras DD-070
  siga abierta, solo existe el entorno local** (C-154).
- ADR-0002: la app se compila y se envía a las tiendas en la nube, con EAS Build y EAS Submit, sin Mac.
- ADR-0008: un proyecto de Firebase por entorno; en iPhone los avisos necesitan la clave de la cuenta de Apple.
- ADR-0009: la llave de Google Maps de Android va restringida a la app y es una por entorno.
- ADR-0010: el pago se da por bueno solo con el webhook de la pasarela; la firma se valida en su modo de prueba antes de
  producción. ADR-0010 sigue vigente y nombra a Conekta, que es la pasarela en producción mientras el cliente no firme
  el cambio (`CLAUDE.md` §2-bis, punto 3). Stripe y Mercado Pago son candidatas (C-192); el cliente dio un OK expreso y
  la firma sigue pendiente (C-216, DD-079). Si se firma, el ADR de la elegida reemplaza a ADR-0010.
- Los trámites del B.10 no han empezado (DD-084) y no se sabe si las cuentas de las tiendas serán de persona física o de
  sociedad (DD-017). Grado E: una cuenta personal nueva de Google Play pide una prueba cerrada de 14 días con 12
  personas antes de publicar (R-029, `vault/arquitectura/verificaciones-fase-6.md`, fila 1).
- En las dos semanas antes del envío no entran funciones nuevas (`vault/formas-de-trabajo/sprint-y-sesion-diaria.md`).

## Goals / Non-Goals

**Goals:**

- Prueba y producción existen y no se mezclan.
- Todo viaja cifrado (SEG-1).
- Compilaciones firmadas, enviadas y liberadas a la vez en las dos tiendas (A43-1).
- La pasarela cobra en producción (A43-4): Conekta, o la elegida si se firma el cambio (DD-079).
- El hito alterno del pago 2 tiene dónde acreditarse (`NSV-2026-001-v8.pdf:7:Cláusula IV`).

**Non-Goals:**

- Los tiempos y los criterios de revisión de las tiendas, que no dependen de EL PRESTADOR.
- Más de 2 intentos de corrección de rechazos sin cargo (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`).
- El mantenimiento posterior (`NSV-2026-001-v8.pdf:40:ANEXO C, Sección C.5`), incluida la vigilancia de disponibilidad.
- Abrir o pagar las cuentas del cliente.
- Funciones nuevas.

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0002, ADR-0004, ADR-0008, ADR-0009, ADR-0010 y ADR-0012, sin
repetirlos.

## Tablas y migración

No crea tablas: ninguna tabla de `vault/arquitectura/modelo-de-datos.md` nace aquí (ADR-0016). En prueba y en producción
corren las migraciones de los demás cambios, las mismas que la integración continua ya revisó con la prueba de esquema
de `modelo-de-datos` (modelo-E01 a modelo-E17) después de `migrate:fresh`; este cambio no agrega ninguna. Los triggers
`impedir_cambio()` de `pedidos`, `pedido_eventos`, `pagos` y `reembolsos` llegan con esas migraciones.

Usa, sin cambiar su forma:

| Tabla | Columnas que toca | Para qué | Nace en |
|---|---|---|---|
| `pagos` | `pasarela`, `id_pasarela_orden`, `estado`, `pagado_en` | publicacion-E14 y publicacion-E15: la orden en modo producción y el pago confirmado | `pago` |
| `eventos_pasarela` | `pasarela`, `id_pasarela_evento`, `procesado_en` | publicacion-E14 y publicacion-E15: el webhook de producción, procesado una sola vez | `pago` |
| `pedidos`, `pedido_eventos` | las del recorrido | publicacion-E15 y publicacion-E16: el pedido real y su seguimiento | `resumen-y-confirmacion` |
| `dispositivos` | `token_fcm`, `plataforma` | publicacion-E01: el aviso con el Firebase de cada entorno | `avisos` |

El valor de la columna `pasarela` en producción es `conekta`, la del contrato, o el de la elegida si se firma el cambio
(DD-079). La transacción real de publicacion-E15 queda en `pedidos`
y `pagos` como cualquier otra, y la base no deja borrarla (trigger `impedir_cambio()`). Si se devuelve, es una fila de
`reembolsos` (`pago`); cuándo se reembolsa sigue en DD-012, DD-016 y DD-103.

## Risks / Trade-offs

- [DD-070 sigue abierta] → no hay entorno de prueba, así que el hito alterno del pago 2 no se puede acreditar.
- [Sin cuenta de Apple no hay compilación de iPhone ni avisos en iPhone] → DD-017, DD-084 y DD-069. El D-U-N-S debía
  pedirse a más tardar el 2027-01-11 (DD-084).
- [Cuenta personal de Google Play] → la prueba cerrada de 14 días con 12 personas tiene que empezar al menos tres
  semanas antes del envío (R-029, grado E).
- [El identificador de la app no se puede cambiar después de publicar] → publicacion-E08 detiene la compilación con el
  marcador; el definitivo espera a la marca (DD-023).
- [Los revisores de Apple necesitan una cuenta de demostración y una app con contenido; el B.10 advierte rechazos por
  "información insuficiente sobre el modelo de negocio"] → DD-121.
- [Google Play pide además una página web para solicitar el borrado de la cuenta (grado E, verificaciones, fila 4)] →
  ADR-0005 solo cubre el borrado desde la app: DD-109.
- [Pasar de las cuentas de prueba de EL PRESTADOR a las del cliente antes de producción] → es la consecuencia negativa
  que ya acepta ADR-0012.
- [El plan gratuito de EAS da 15 compilaciones por plataforma, con cola lenta] → ADR-0002; el plan de pago es costo de
  desarrollo.

## Open Questions

1. **Cómo se despliega a prueba y a producción:** desde la integración continua, con un panel para el servidor o con el
   servicio administrado que se elija. Depende de DD-070 y **necesita ADR, o ampliar ADR-0012**.
2. **Los respaldos de la base.** El B.4 los presupuesta como "Respaldos periódicos de la base de datos"
   (`NSV-2026-001-v8.pdf:29:ANEXO B, Sección B.4`) y DD-070 los menciona, pero no están en el inventario funcional ni en
   ningún cambio. ¿Entran aquí? **Pregunta para Arthur.**
3. **La página web para pedir el borrado de la cuenta** (DD-109): ¿se amplía ADR-0005 y va en
   `perfil-y-soporte` o en `textos-legales`?
4. **La ficha de cada tienda y las declaraciones de datos** (DD-121).
5. **¿El entorno de prueba pide no ser indexado por los buscadores?** La misma pregunta está en
   `medicion-de-la-landing`.
