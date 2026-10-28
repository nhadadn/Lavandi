---
estado: borrador
grado: E
fuente: GitHub (repositorios y su contenido) y CodeCanyon, consultados el 2026-10-28
fecha: 2026-10-28
dueño: Nadir
---

# Proyectos base: complemento del 2026-10-28

Complementa la sección "Construir, adaptar o comprar" de `arquitectura/planteamiento-fase-6.md`
(investigación del 2026-10-09, opciones OP-01 a OP-07, puertas G-1 a G-5) y alimenta DD-063 / `ADR-0015`.
**No la sustituye ni decide nada.** Los veredictos de allá siguen en pie salvo lo que se dice aquí.

## Lo nuevo

| Hallazgo | Grado | Efecto |
|---|---|---|
| **Enatega** ([repo](https://github.com/enatega/food-delivery-multivendor), MIT): el repo trae admin, app de cliente, repartidor, tienda y web, pero **ninguna carpeta de servidor** | B en GitHub (listado de la raíz, 2026-10-28) | Confirma OP-06: solo referencia de lectura. Lo que se baja gratis no funciona solo |
| **Shita** ([repo](https://github.com/ferasshita/Shita-Multivendor-Food-Delivery), MIT): mismo esquema que Enatega (Expo + React + Node/GraphQL/MongoDB) **y sí trae carpeta de servidor**, además de Terraform | B en GitHub (listado de la raíz) | **Candidato nuevo** que no estaba en OP-01 a OP-07. Ver abajo |
| **Fleetbase** ([repo](https://github.com/fleetbase/fleetbase)): licencia AGPL-3.0 | B en GitHub (licencia del repo) | Falla G-5 sin licencia comercial |
| Plantillas de lavandería en CodeCanyon: además de ReadyLaundry y FastLaundry, existen **UltraWash** y **GoLaundry** (Flutter) | E, `SIN VERIFICAR` | No cambian el veredicto de OP-02: la licencia de Envato falla G-5 |
| **6amMart** y **Demandium** (6amTech): sin módulo de lavandería; licencia extendida citada en USD 399 | E, `SIN VERIFICAR` | No cambian el veredicto de OP-03 |

## Shita contra las puertas (preliminar, sin haberlo arrancado)

- **G-1** pasa en principio: una sola base Expo para iPhone y Android.
- **G-2** incierto: los mapas de Expo suelen usar Apple en iPhone y Google en Android, que es justo lo pedido; falta ver qué librería usa el repo.
- **G-3** falla: no trae Conekta; habría que agregarla.
- **G-4** incierto: MongoDB y el despliegue en Terraform tienen costo por ver.
- **G-5** pasa: MIT permite entregar y modificar, conservando el aviso.
- **Riesgo propio:** un solo autor, creado en agosto de 2026, 24 estrellas, parece derivado de Enatega. Nadie lo ha probado en producción, que sepamos.

## Stack de Shita, leído en GitHub el 2026-10-28 (grado B en GitHub)

- **App de cliente**: Expo 53, React Native 0.79, React 19, Apollo Client, `react-native-maps` 1.20 (Apple en
  iPhone y Google en Android por defecto, a confirmar en el código), Google Sign-In, inicio con Apple, Sentry.
  Se llama `shita-full-app`, versión 5.0.0, y sus scripts son los de Enatega: **son las apps de Enatega renombradas**.
- **Servidor**: Node con TypeScript, Express 5, Apollo Server 5 (GraphQL), MongoDB con Mongoose 9, JWT,
  Stripe y PayPal instalados. Los scripts `lint` y `test` son textos de relleno: **no tiene pruebas**.
- **Panel y web**: Next.js 14.
- El informe de salud del repo (`PROJECT_HEALTH_REPORT.md`) lo firmó "GitHub Copilot Workspace" y dice que los
  pagos, correos, avisos y subida de archivos están "estructurados pero no implementados".

**Lectura:** Shita es Enatega con un servidor nuevo, escrito con ayuda de IA para sustituir el que Enatega vende
aparte. Las apps son maduras; el servidor tiene meses, no tiene pruebas y nadie garantiza que cubra todo lo que
las apps le piden. Lo valioso sería el servidor, y es justo lo menos confiable.

## Búsqueda de bases con servidor incluido (2026-10-28)

Datos de GitHub (licencia, estrellas y último cambio, grado B en GitHub). Lo demás, grado E.

| Proyecto | Servidor | Apps | Licencia | Lectura |
|---|---|---|---|---|
| [fleetbase/fleetbase](https://github.com/fleetbase/fleetbase) | Sí, completo y maduro (PHP/Laravel + panel) | Conductor y tienda (React Native) | AGPL-3.0; vende licencia comercial (precio no consultado) | El más sólido con servidor. Pasa G-5 solo con licencia comercial |
| [WanyueKJ/Food-delivery-uniapp](https://github.com/WanyueKJ/Food-delivery-uniapp) | Sí (PHP) | Cliente, negocio, repartidor, panel (uni-app) | **Sin licencia declarada** = todos los derechos reservados | Falla G-5. Además documentado en chino y "versión abierta" de un producto de pago |
| [blinks32/Complete-Taxi-Booking-Parcel-Delivery-Solution](https://github.com/blinks32/Complete-Taxi-Booking-Parcel-Delivery-Solution) | Firebase | Ionic/Angular: pasajero, conductor, negocio, panel | Sin licencia declarada | Falla G-5 |
| [mehdihadeli/food-delivery-modular-monolith](https://github.com/mehdihadeli/food-delivery-modular-monolith) y versiones en Go/.NET | Sí, solo servidor, ejemplo didáctico (.NET/Go) | Ninguna | MIT | Referencia de diseño, no producto |
| 6amMart (pago) | Sí, Laravel incluido | Flutter: cliente, repartidor, tienda | Envato | Ya evaluado como OP-03: falla G-5 |
| Enatega, servidor de pago | Se vende aparte | — | No se encontró el precio ni los términos | Hay que pedirlo a Enatega por escrito |

## Tercera ronda (2026-10-28): Fleetbase descartado

Nadir descartó Fleetbase al saber que su licencia comercial se cita en unos USD 25,000 al año (grado E,
sitios agregadores, `SIN VERIFICAR`; queda en acta cuando se asiente la sesión).

| Hallazgo | Grado | Lectura |
|---|---|---|
| **Enatega vende el servidor con pago único**, "Pay once, own the code". Su FAQ dice que las API son propias y se adquieren con licencia de pago; otra página dice que el código entregado es propiedad del comprador. Rangos citados en sus guías: USD 15,000–30,000 (no son su precio, se confirma en llamada) | E, [enatega.com](https://enatega.com/), [guía de costos](https://enatega.com/white-label-delivery-software-cost/) | Podría pasar G-5 si la propiedad es real. Probablemente también fuera de presupuesto. Solo se sabe pidiéndolo por escrito |
| Repos de lavandería con servidor ([washWagon](https://github.com/kosionyia/washWagon), [freshfold](https://github.com/Kosiasiko/freshfold-laundry-pickup-delivery-management-system), [Quick_Laundry_Backend](https://github.com/binita07vasita-pixel/Quick_Laundry_Backend)) | B en GitHub: 0 estrellas, 2026 | Proyectos escolares. No sirven |
| **Medusa** ([repo](https://github.com/medusajs/medusa), ~37,000 estrellas; GitHub marca la licencia como "otra", a leer) y **Saleor** ([repo](https://github.com/saleor/saleor), BSD-3, ~23,500 estrellas): motores de comercio maduros, solo servidor. Medusa tiene un complemento de varios vendedores ([Tech-Labi](https://github.com/Tech-Labi/medusa-marketplace-plugin)) | B en GitHub | No son de reparto, pero resuelven catálogo, pedidos, cobros por módulos y panel. Sobre ellos faltaría lo de repartidor y lavandería |

**Conclusión de la búsqueda:** no apareció ninguna base gratuita, con servidor completo y licencia que permita
cederla. Quedan tres caminos para DD-063:

1. **OP-01**: arranque limpio (ya propuesto el 2026-10-09).
2. **Híbrido**: tomar las apps de Enatega (MIT, maduras) y escribir el servidor propio, solo o sobre Medusa o Saleor.
3. **Comprar el servidor de Enatega**, si el precio y la cesión por escrito lo permiten.

## Siguiente paso propuesto

1. Arrancar Shita en local y ver si servidor y apps funcionan de verdad (pasa a grado B).
2. Si funciona, entra como opción **OP-08** en el planteamiento, junto a OP-01 (arranque abierto: Ignite o Very Good CLI con Supabase), y DD-063 se decide entre las dos.
