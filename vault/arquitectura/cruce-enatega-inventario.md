---
estado: borrador
grado: E
fuente: inventario funcional (`contrato/inventario-funcional.md`) contra el árbol de código de enatega/food-delivery-multivendor, rama main, leído el 2026-10-28
fecha: 2026-10-28
dueño: Nadir
---

# Cruce: cuánto de nuestra app aporta Enatega

Insumo para DD-063 (construir, adaptar o comprar) y para el camino "híbrido" de
`arquitectura/investigacion-proyectos-base.md`. **No es una decisión.**

## El resultado, en corto

1. **Enatega aporta cerca del 20 % del trabajo total** (rango 12 % a 27 %). En horas, sobre las ~390 del
   inventario, son **unas 80 horas** (entre 47 y 105). Antes de restar lo que cuesta adoptarlo.
2. **En la app y el panel sube a cerca del 31 %** (20 % a 43 %). El panel de administración es lo más
   aprovechable (38 %).
3. **No aporta nada** al servidor, la base de datos, la landing ni los correos. Casi nada a los indicadores.
4. **Lo más caro de adoptarlo** es juntar sus tres apps en una sola (F-T12) y quitarle la lógica de comida.
5. **La lectura:** el ahorro neto es real pero modesto, quizá 40 a 70 horas una vez pagado el costo de
   adopción. No resuelve la brecha de la sección 7 del inventario (390 h pedidas contra 135 a 270
   disponibles). Ayuda; no la cierra.

## Qué trae Enatega (grado B en GitHub, árbol del repo)

| Pieza | Tecnología | Pantallas |
|---|---|---|
| App de cliente | Expo 53, React Native 0.79, Apollo, `react-native-maps`, Stripe | 40: login, registro, OTP, contraseña olvidada, direcciones y mapa, búsqueda, negocio, carrito, pago, pedidos, detalle con seguimiento, calificación, perfil, soporte, chat con repartidor |
| App de repartidor | Expo 53, `expo-task-manager` (ubicación en segundo plano) | ~12: pedidos en proceso y entregados, detalle, ganancias, cartera, horario, vehículo, perfil, chat |
| App de tienda | **Expo 54**, React Native 0.81 | ~12: pedidos en proceso y entregados, ganancias, cartera, horario, perfil |
| Panel web | Next.js | ~45: tiendas, vendedores, repartidores, usuarios, personal, pedidos, despacho, zonas, comisiones, cupones, avisos masivos, cartera, configuración, bitácora |
| Servidor | **No viene** | — |

## Cómo se midió

- Cada función del inventario se calificó por cuánto de **su trabajo total** (pantalla y servidor) ya
  resuelve Enatega: **Alto** 45 % · **Medio** 25 % · **Bajo** 10 % · **Nada** 0 %. El rango usa 30/15/5/0
  (pesimista) y 60/35/15/0 (optimista). Ningún "Alto" pasa de la mitad: el servidor y el diseño de
  {{MARCA}} siempre quedan por hacer.
- Peso por tamaño del inventario: S = 1, M = 2, L = 3.
- Son 73 funciones: F-T01, F-T11 y F-T15 ya están contadas en otras.
- **Los porcentajes son estimación de Claude** sobre nombres de pantallas y dependencias, sin haber
  abierto ni corrido el código. Grado E hasta probarlo.

## Función por función

| Módulo | Alto | Medio | Bajo / Nada |
|---|---|---|---|
| A · Usuario | A02 registro e inicio de sesión · A04 búsqueda · A08 direcciones y mapa · A13 historial · A14 calificación · A15 perfil · A16 soporte | A01 bienvenida · A03 principal · A05 perfil de lavandería · A06 servicio · A09 resumen · A11 confirmación · A12 seguimiento · A17 avisos | A07 programar recolección (B) · **A10 pago: Stripe y PayPal, no Conekta ni OXXO (B)** |
| B · Lavandería | B04 historial · B06 perfil y horarios | B02 pedidos activos · B03 estado · B05 estadísticas | B01 registro (B): la app solo tiene inicio de sesión |
| C · Repartidor | C04 historial | C02 pedidos asignados · C03 estado · C05 estadísticas | C01 registro (B) |
| D · Administración | D01 lavanderías · D02 repartidores · D03 usuarios · D05 comisiones · D07 avisos masivos | D04 monitoreo · D06 reportes financieros | — |
| Landing | — | — | Las 12 (N): Enatega trae una web de pedidos, no una landing |
| Indicadores | — | — | Los 11 (B): solo hay un tablero de inicio genérico |
| Transversales | — | T02 avisos · T03 mapas · T18 aviso de privacidad | T04 servidor y T05 base de datos (N) · T06 imágenes, T07 acceso, T10 separación por rol, **T12 una sola app**, T14 exportación (B) · T08, T09, T13, T16, T17 (N) |

Resultado por módulo, en escenario medio: usuario 31 % · lavandería 28 % · repartidor 26 % ·
administración 38 % · landing 0 % · indicadores 10 % · transversales 9 %.

## Lo que costaría adoptarlo

1. **Juntar tres apps en una** (F-T12, BR-48). Usan versiones distintas: cliente y repartidor en Expo 53,
   tienda en Expo 54. Hay que alinearlas y armar la navegación por rol.
2. **Quitar la comida**: menús, complementos, cocinas, propinas y cupones, que el contrato no pide.
3. **Quitar la ubicación en segundo plano** del repartidor. El contrato no la exige (RN-12, BR-30) y es lo
   que más revisan las tiendas.
4. **Cambiar Stripe y PayPal por Conekta**, con OXXO (G-3).
5. **Confirmar los mapas** (G-2): `react-native-maps` usa Apple en iPhone por defecto, salvo que el código
   fuerce Google. No se ha revisado.
6. **Escribir el servidor completo** para las consultas que las apps ya hacen, o reescribir las consultas.
7. **El panel en web.** Enatega lo hace en web; adoptarlo empuja DD-040 hacia "administración en web".

## Escenario ampliado: Enatega + Supabase + Superset + plantilla de landing

Mismo método, con estas piezas sumadas. Licencias citadas de memoria, `SIN VERIFICAR`: Supabase Apache 2.0,
Superset Apache 2.0; la plantilla de landing, por elegir.

| | Pesimista | Medio | Optimista |
|---|---|---|---|
| **Total** | 17 % | **28 %** | 39 % |
| Horas brutas sobre ~390 | 68 | **109** | 150 |
| App y panel (A–D) | 20 % | 31 % | 43 % |
| Landing | 12 % | 21 % | 29 % |
| Indicadores | 15 % | 25 % | 35 % |
| Transversales | 16 % | 25 % | 35 % |

Cambios de calificación: Supabase sube servidor y base de datos a Medio; fotos, control de acceso,
cifrado y contraseñas a Alto; separación por rol a Medio. Superset sube los 11 indicadores a Medio y la
exportación a Medio. La plantilla sube las secciones fijas de la landing a Medio.

**Costo que no está restado:** las apps de Enatega hablan con su servidor por GraphQL. Con Supabase,
cada consulta se reescribe. Conteo en la sección siguiente.

## Cuántas consultas habría que reescribir (grado B en GitHub, 2026-10-28)

Se descargaron los 110 archivos GraphQL de las cuatro piezas (sin la versión de un solo negocio) y se
contaron las operaciones declaradas:

| Pieza | Operaciones | Lecturas | Escrituras | Tiempo real |
|---|---|---|---|---|
| Panel | 176 | 77 | 94 | 5 |
| App de cliente | 68 | 34 | 29 | 5 |
| Repartidor | 47 | 14 | 26 | 7 |
| Tienda | 22 | 10 | 10 | 2 |
| **Total** | **313** (260 nombres distintos) | | | |

Unas **100** tocan cosas que no pedimos (comida, complementos, cupones, propinas, carteras, chat,
versiones, bitácora): se **borran**, no se reescriben. Quedan **unas 160** que hay que llevar a Supabase.

Costo estimado (Claude, grado E): entre 15 minutos y 1 hora cada una con ayuda de agentes, contando
ajustar la pantalla que la usa: **de 40 a 160 horas**.

**Ahorro neto del escenario ampliado:** 109 horas brutas (medio) menos ~80 de reescritura ≈ **30 horas**.
En el escenario pesimista **se pierde tiempo** (68 − 160). En el optimista se ganan unas 110 (150 − 40).
**El resultado depende casi entero de cuánto cuesta reescribir una consulta.** Eso es lo que debe medir
la prueba de dos o tres días.

## Lo que queda 100 % en nuestras manos

Nada de lo de arriba lo resuelve, ni en parte:

1. **La lógica de la lavandería**: el ciclo recoger → lavar → devolver con dos viajes, los cinco estados
   del contrato y qué pasa con cancelados y rechazados (F-A12, BR-22).
2. **Programar la recolección** (F-A07) y sus reglas de horario y capacidad.
3. **El precio**: por kilo o por prenda, envío mínimo de $35 (C-31), comisiones y cómo se reparte el dinero.
4. **Conekta con tarjeta y OXXO** (F-A10, G-3), incluido el aviso de pago confirmado.
5. **Juntar todo en una sola app con cuatro roles** (F-T12, BR-48).
6. **El registro de lavanderías y repartidores** dentro de la app (F-B01, F-C01).
7. **Las reglas de permisos por rol** en Supabase: la herramienta existe, las reglas se escriben.
8. **Las definiciones de los 11 indicadores**: qué se mide y con qué consulta.
9. **El diseño de {{MARCA}}** aplicado a todo: el design system de la Fase 4.
10. **El contenido de la landing** y su medición (GA4, Meta Pixel, GTM, Search Console).
11. **Los requisitos de las tiendas**: publicación, aviso de privacidad, permisos de ubicación y fotos.
12. **Pruebas, despliegue y operación** a nombre del cliente (RN-20).

## Qué podría mover este dictamen

Hacia **más** ahorro:
- Que las consultas de Enatega se puedan mantener casi iguales (por ejemplo, con la capa GraphQL que
  ofrece Supabase, `pg_graphql`, `SIN VERIFICAR` que encaje).
- Que el código de Enatega resulte limpio al abrirlo: pantallas separadas de la lógica de comida.
- Que DD-040 se cierre como "administración en web": el panel de Enatega se aprovecha tal cual.
- Que los agentes reescriban consultas y pantallas más rápido de lo estimado. Se mide en las dos primeras
  semanas (S-006).

Hacia **menos** ahorro, o a descartarlo:
- Que el contrato exija la administración **dentro** de la app (DD-040): el panel web de Enatega deja de servir.
- Que juntar las tres apps cueste más que hacerlas: versiones distintas de Expo, navegación y estado
  incompatibles.
- Que `react-native-maps` esté forzado a Google en iPhone y cambiarlo rompa pantallas (G-2).
- Que el código tenga dependencias viejas o rotas al compilar. Sus propias carpetas traen auditorías
  de seguridad y limpieza: hay que leerlas.
- Que Superset o la plantilla resulten con licencia incompatible con la cesión (G-5).
- Que Enatega deje de mantener el repo abierto o cambie la licencia. Lo ya descargado conserva la MIT.

Cómo se resuelve: **una prueba de dos o tres días** que compile las apps, cambie una pantalla de punta a
punta a Supabase (por ejemplo, historial de pedidos) y mida cuánto tardó. Con ese dato real, el dictamen
deja de ser estimación.

## Lo que se cumple con la adopción

- G-1 (una base de código para las dos plataformas): sí, React Native con Expo.
- G-5 (ceder el código): sí, licencia MIT, conservando el aviso.
- G-2 y G-3: no de entrada; ver puntos 4 y 5.
- Adoptarlo **elige React Native** sin pasar por la matriz. Debe entrar a la Fase 6 como opción explícita.
