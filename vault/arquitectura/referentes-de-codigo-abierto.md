---
estado: borrador
grado: E
fuente: API de GitHub (búsqueda y metadatos de repos) y README, LICENSE y package.json/pubspec.yaml de cada repo, consultados el 2026-10-28, SIN VERIFICAR; revisión de GH-03 contra el repo en vivo el 2026-11-12 (E); puertas G-1 a G-5 de `arquitectura/planteamiento-fase-6.md` (B); experiencia de Nadir, `acta-2026-09-25-analisis-funcional.md:C-89` (C), y de Arthur, `acta-2026-11-12-traspaso-del-proyecto.md:C-135` (C)
fecha: 2026-11-12
dueño: Arthur Naranjo
---
# Referentes de código abierto en GitHub

> **Recuperada el 2026-11-12.** Esta nota se escribió el 2026-10-28 y nunca llegó a un commit. Se perdió cuando se
> borró la copia local del repo. Se reconstruyó desde el registro de esa sesión de Claude Code, que guarda la
> escritura original y sus siete correcciones. Los PDF que se mencionan abajo también se perdieron: si hacen falta,
> se regeneran desde esta nota. La revisión del 2026-11-12 está al final.

**Para qué es esta nota.** Arthur Naranjo la pidió en la sesión del 2026-10-28: buscar en GitHub proyectos
parecidos a Lavandi para ver qué se puede aprovechar y revisarlos con Nadir. Amplía **OP-06** ("Código abierto
de reparto") de `planteamiento-fase-6.md`, que el 2026-10-09 solo nombró a Enatega y Fleetbase.

**Versiones para compartir, en PDF:** `notas-locales/referentes-github-lavanderias.pdf` (primera búsqueda) y
`notas-locales/referentes-github-servicios-con-recoleccion.pdf` (segunda búsqueda, sección "Ampliación" al final).
`notas-locales/` está excluida de git: los PDF viven solo en la máquina donde se generaron. Se eligió PDF porque
la página publicada en claude.ai (<https://claude.ai/artifact/VQB3B4SbpMPjJxtwJfUc39>, privada, solo la primera
búsqueda) no se puede compartir fuera de la organización de quien la publicó. Los PDF son espejo de esta nota: si
la nota cambia, se regeneran después, nunca al revés.

**Qué se hizo y qué no.** Se hicieron 17 búsquedas en la API de GitHub (inglés y español; lavandería, reparto
multi-negocio, combinaciones con Flutter, React Native, Expo y Supabase) y se revisaron a fondo 21 repos:
metadatos, estructura de carpetas, dependencias declaradas, README y licencia. **No se descargó, compiló ni
ejecutó ninguno**, así que la calidad real del código no está inspeccionada. Las estrellas miden popularidad,
no calidad. Todo es grado E y `SIN VERIFICAR`.

## Veredicto

**No hay un repo que se pueda tomar como base y "solo mejorar".** Ninguno pasa las cinco puertas tal como está:
los que tienen apps móviles cobran con Stripe, PayPal o Razorpay (G-3), o no entregan su backend (G-5), o tienen
licencia AGPL (G-5). Confirma con nombres concretos lo que OP-06 ya decía: **referencia de lectura, no base.**

Lo que sí hay, y no había en la nota del 2026-10-09:

- **Un referente del ciclo del pedido de lavandería** que modela la inspección y la factura después de recoger,
  que es el problema del peso estimado contra el peso real (GH-03).
- **Un referente de arquitectura con la misma forma que Lavandi**: cuatro superficies, permisos por negocio y
  tiempo real sobre PostgreSQL (GH-02).
- **Apps de tres lados con licencia MIT** cuyas pantallas se pueden estudiar y, con su aviso de copyright,
  copiar (GH-01).
- **Una trampa**: un repo que parece Enatega con backend incluido y cuyo origen no se puede verificar (descartado).

## Advertencia de método

Cada referente trae su stack (Expo, Flutter, Laravel, NestJS). Leerlos para entender **el dominio** (ciclo del
pedido, roles, cobertura, permisos) no sesga la matriz. Usarlos para **elegir el framework** antes de fijar los
pesos (P-18) sí: es elegir primero y justificar después. Lo que esta nota dice de mapas y librerías es dato de
candidatos y se lee en el paso 6, junto con `verificaciones-fase-6.md`.

## Resumen

Puertas: G-1 una sola base de código · G-2 mapa de Google en Android y de Apple en iPhone · G-3 Conekta ·
G-4 costo del B.4 · G-5 el código se cede al cliente. `pasa` · `falla` · `incierto` · `n/a`.

| ID | Repo | Licencia | ★ | Último push | Stack | G-1 | G-2 | G-3 | G-4 | G-5 | Sirve para |
|---|---|---|---|---|---|---|---|---|---|---|---|
| GH-01 | `enatega/food-delivery-multivendor` | MIT (sin backend) | 1,409 | 2026-10-07 | Expo/React Native, Next.js, GraphQL | incierto | pasa en lo revisado | falla | incierto | falla en el backend | Pantallas de tres lados y panel |
| GH-02 | `JasonTM17/FoodDelivery_App` (FoodFlow) | MIT | 2 | 2026-10-08 | NestJS, Prisma, Supabase, Flutter | pasa | falla | falla | incierto | pasa | Arquitectura de cuatro superficies |
| GH-03 | `ashfakhm/laundry-ease` | MIT | 3 | 2026-07-09 | Next.js, Socket.IO | falla | n/a | falla | incierto | pasa | Ciclo del pedido de lavandería |
| GH-04 | `fleetbase/fleetbase` + `storefront-app` + `navigator-app` | AGPL-3.0 | 4,415 | 2026-10-28 | Laravel, React Native | incierto | incierto | falla | incierto | falla | Logística madura |
| GH-05 | `andes2912/laundry` (E-Laundry) | contradictoria | 166 | 2026-06-23 | Laravel | falla | n/a | falla | incierto | falla | Mostrador de la lavandería y reportes |

## GH-01 · Enatega

- **Fuente:** https://github.com/enatega/food-delivery-multivendor (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** plataforma de reparto multi-negocio y multi-región. App de cliente, app de tienda y app de
  repartidor en Expo 53 / React Native 0.79.5; panel de administración y web de pedidos en Next.js; datos por
  GraphQL (Apollo). Inicio de sesión con Google y Apple, avisos con Firebase, pagos con Stripe y PayPal.
- **Lo que no trae:** el backend. README, líneas 59 y 229: *"the backend and API are proprietary and can be
  licensed as part of a paid package"*. Las apps son MIT ("Copyright (c) 2023 Ninjas Code").
- **Mapas:** `react-native-maps` 1.20.1. En las 6 vistas revisadas, 5 usan `PROVIDER_DEFAULT`, que en iPhone es
  Apple Maps y en Android Google; una vista del modo de un solo negocio fuerza `PROVIDER_GOOGLE`. Solo se
  revisaron archivos con "map" en la ruta.
- **Puertas:** G-1 incierto: son tres apps separadas y el contrato pide una sola; fusionarlas es trabajo ·
  G-2 pasa en lo revisado · G-3 falla: Conekta se integra desde cero · G-4 incierto: sin backend no hay costo
  que calcular · G-5 falla para el backend (de pago, no se cede); las apps sí se pueden entregar.
- **Para qué sirve:** es el referente completo de tres lados más maduro, y está en JavaScript/TypeScript con
  un panel en Next.js, que Nadir conoce (C-89). Sus pantallas y componentes se pueden copiar conservando el
  aviso de copyright.
- **Veredicto:** **referente principal de lectura, no base.** Adaptar sus apps obliga a escribir un backend que
  imite su API GraphQL: es construir el backend igual, pero atado a un esquema ajeno.

## GH-02 · FoodFlow

- **Fuente:** https://github.com/JasonTM17/FoodDelivery_App (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** su README lo llama *"multi-tenant food-delivery learning project"*. API en NestJS con Prisma;
  Supabase para PostgreSQL/PostGIS, tiempo real y archivos; panel de administración y panel de restaurante en
  Vercel; app de cliente y repartidor en Flutter (un solo `mobile/pubspec.yaml` en el árbol), con
  `google_maps_flutter` y Firebase Messaging.
- **Lo valioso:** tiene la misma forma que Lavandi: administración, negocio, cliente y repartidor. Autoriza
  por negocio (*"tenant-scoped authorization"*), que es el problema de RN-09; da credenciales de tiempo real de
  corta vida por canal privado (RN-05); usa PostGIS para ubicación.
- **Puertas:** G-1 pasa · G-2 falla tal como está: `google_maps_flutter` usa Google también en iPhone ·
  G-3 falla · G-4 incierto: Supabase, Railway y Vercel sin costo calculado · G-5 pasa: MIT.
- **Cuidado:** creado el 2026-06-04, un solo autor, 2 estrellas. Su propio README deja fuera de su
  certificación los teléfonos físicos y los avisos FCM.
- **Veredicto:** **referente de arquitectura, no base.** Es el más parecido en forma y el menos probado.

## GH-03 · LaundryEase

- **Fuente:** https://github.com/ashfakhm/laundry-ease (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** mercado de lavanderías en web (Next.js, NextAuth, Socket.IO, Google Maps). Sin app móvil.
- **Lo valioso, que es justo lo propio de Lavandi (P-12):**
  - la lavandería solo aparece si su radio de servicio cubre al usuario;
  - la lavandería **inspecciona y factura después de recoger**: el mismo problema que el peso estimado contra
    el peso real;
  - estados explícitos: lavado → planchado → listo → en camino → entregado;
  - reprogramar la recolección es un estado propio (`reschedule_requested`), no un mensaje;
  - la entrega se confirma con un código;
  - queja dentro de 24 horas, con el administrador como mediador;
  - borrar la cuenta desde la app, bloqueado si hay pedidos activos (regla de las tiendas, inventario
    funcional §4).
- **Lo que choca con el contrato:** el código por SMS (RN-24 lo deja fuera), el dinero retenido y la
  dispersión automática con RazorpayX (RN-12 excluye la liquidación automática a repartidores), y Razorpay,
  que es de India (G-3).
- **Puertas:** G-1 falla: no hay app · G-2 n/a · G-3 falla · G-4 incierto · G-5 pasa: MIT.
- **Veredicto:** **el mejor referente del ciclo del pedido.** Conviene leer su máquina de estados antes de
  escribir la de Lavandi (BR-22). No es base.

## GH-04 · Fleetbase

- **Fuente:** https://github.com/fleetbase/fleetbase, https://github.com/fleetbase/storefront-app,
  https://github.com/fleetbase/navigator-app (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** plataforma de logística activa (último push el día de la consulta). API en Laravel, consola web,
  servidor de sockets; app de pedidos (Storefront, 192 ★) y app de conductor (Navigator, 117 ★) en React
  Native 0.86 con `react-native-maps`, Stripe y acceso con Google y Apple.
- **Puertas:** G-1 incierto: dos apps · G-2 incierto: no se revisó qué proveedor de mapa usa · G-3 falla ·
  G-4 incierto: OP-06 registró que pide un servidor de 4 GB · G-5 falla: con AGPL las modificaciones se
  publican con la misma licencia, o se compra la comercial.
- **Además:** Navigator trae rastreo en segundo plano (`react-native-background-geolocation`), que el Módulo C
  excluye (RN-12).
- **Veredicto:** **referente de logística madura, no base.**

## GH-05 · E-Laundry

- **Fuente:** https://github.com/andes2912/laundry (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** sistema web en Laravel 12 para operar el mostrador de una lavandería: pedido con varias prendas,
  cambio de estado con confirmación, factura en PDF, roles (superadministrador, administrador, empleado,
  cliente), reportes con exportación a Excel e ingresos por sucursal.
- **Licencia contradictoria:** la API de GitHub la marca MIT; el README dice *"Proprietary"* y que es un
  repositorio de muestra de una versión vieja, con la versión activa cerrada y por suscripción. No se encontró
  el archivo LICENSE en la raíz.
- **Puertas:** G-1 falla: es web · G-2 n/a · G-3 falla · G-4 incierto · G-5 falla mientras la licencia no se
  aclare.
- **Veredicto:** **referente funcional para el Módulo B y los reportes (RN-13). Mirar, no copiar.**

## Piezas sueltas con licencia MIT

- `tvler/laundry-symbols` (137 ★): los símbolos de cuidado de prendas en SVG de 24×24. Se pueden usar con su
  aviso de copyright si alguna pantalla los necesita; el contrato no lo pide.
- `Maadhav/flutter-laundry-app-concept` (79 ★, último push 2020): concepto de interfaz. Inspiración visual; el
  código es viejo.

## Revisados y descartados

| Repo | Por qué no |
|---|---|
| `ferasshita/Shita-Multivendor-Food-Delivery` (MIT, 24 ★) | Mismo README y misma estructura que Enatega, más un backend que Enatega declara propietario. Su LICENSE dice "Copyright (c) 2023 Feras Shita", con el mismo año y texto que la de Enatega ("2023 Ninjas Code"), aunque el repo se creó el 2026-08-15 y tiene 10 commits. El README no menciona a Enatega. Quitar el aviso original incumple la propia MIT, y el origen del backend no se puede verificar. **No se toca.** |
| `sravan-dev/laundry-pro`, `Mahaselvan/pressneat-mern`, `bantoinese83/LaundryPad`, `labkin/React-Native-Laundry-App-`, `ifnzx/laundry-on-demand`, `PrioxisTechnologies/LaundryApp` | Sin licencia: todos los derechos reservados, no se puede copiar nada. Además pequeños (0 a 38 ★) y recientes o abandonados |
| `versatility519/choreless-boilerplate` (MIT, 17 ★) | Su README es el del starter `mickasmt/next-saas-stripe-starter`; no trae lógica de lavandería que valga la pena |
| `openfoodfoundation/openfoodnetwork` (AGPL, 1,300 ★) | Mercado de productores de alimentos en Rails; otro dominio y AGPL |
| `Suffixdigital/Zomato-Style-Food-Delivery-App` (MIT, 11 ★) | Una app de Flutter con Supabase, sin panel ni backend propio en el árbol; no aporta sobre GH-02 |
| `mohamedebrahim96/Justclean`, `Malvin555/HydroWash`, `HashJProgramming/LMS-Laundry-Management-System-with-QRCode` | Solo Android (Kotlin, 2020) o solo web en PHP; pequeños |
| `zipprrofficial/uber-for-laundry`, `sangvishtechnologies/uber-for-laundry`, `Laundryncart/laundryscript` | Repos de publicidad de agencias, sin código. Ya cubiertos por OP-05 |

## Cómo se podrían usar (propuesta de Claude; decide Nadir)

1. **Ciclo del pedido:** leer la máquina de estados de GH-03 antes de escribir BR-22. Es la lectura con más
   retorno.
2. **Arquitectura:** leer la documentación de GH-02 sobre permisos por negocio y tiempo real, como contraste
   para C2 y C3.
3. **Pantallas:** mirar las capturas y demos de GH-01 para los flujos de tres lados.
4. **Tope de horas por lectura.** Las horas son lo que falta (P-08): cada lectura con un límite fijado antes.
5. **Nada entra al código sin `ADR-0015`** (construir, adaptar o comprar), y el framework sigue saliendo de la
   matriz con los pesos fijados.

## Preguntas para Nadir

1. ¿Confirmas que ninguno se toma como base, igual que OP-06?
2. ¿Cuáles vale la pena leer, y con cuántas horas de tope cada uno?
3. ¿Se permite copiar pantallas o componentes MIT (por ejemplo de GH-01) conservando su aviso de copyright? Ese
   código seguiría siendo de su autor: el cliente lo recibiría con licencia MIT, no con los derechos que cede la
   Cláusula IX. Es la misma pregunta que P-15 y la de cualquier librería de terceros. No es asesoría legal.

## Ampliación: servicios con recolección y entrega (2026-10-28)

**Por qué.** Arthur Naranjo aclaró en la misma sesión que no buscaba solo lavanderías, sino apps con el mismo
patrón: se recoge algo del dueño, se le da seguimiento, se cobra y se le devuelve. Se hicieron 19 búsquedas más
(mensajería y paquetería, recolección y entrega, servicios bajo demanda, servicios a domicilio, apps tipo Uber,
logística, reparación, autolavado, calzado) y se revisaron a fondo 10 repos: 8 con la API de GitHub y 2
(CoopCycle) leyendo sus archivos directamente, porque la API llegó a su límite de consultas. Mismo método y
mismas reservas que arriba.

### Veredicto de la ampliación

**Ningún proyecto abierto hace completo el viaje redondo de Lavandi** (recoger en casa, trabajar en el negocio,
devolver, cobrar) con madurez. Cada familia cubre unos tramos:

| Familia | Ejemplos | Recoger en casa | Seguimiento en vivo | Trabajo en el negocio con estados | Devolver | Cobro en la app |
|---|---|---|---|---|---|---|
| Reparto multi-negocio | Enatega (GH-01), Ever Demand (GH-09) | no: recoge en la tienda | sí | parcial: preparación del pedido | sí | sí |
| Logística de última milla | Fleetbase (GH-04), CoopCycle (GH-06), OwnFleet (GH-07), WityLogix (GH-08) | sí, como tarea de recolección | sí | no | sí | parcial |
| Movilidad tipo Uber | Locomotion, tutoriales | sí: recoge al pasajero | sí | no | sí: lo deja en destino | sí |
| Servicios a domicilio de nicho | tintorería, sastrería, reparación, autolavado | en el papel | en el papel | en el papel | en el papel | en el papel |
| Lavandería | LaundryEase (GH-03) | sí | parcial: estados, sin mapa del repartidor | sí | sí | sí, pero solo web |

Lectura de Claude a partir de README y dependencias; no se probó ninguno. **Confirma P-12:** lo común (mapa,
seguimiento, cobro, avisos, prueba de entrega) está resuelto en piezas; el viaje redondo con el trabajo en medio
es lo propio de Lavandi y hay que escribirlo.

### GH-06 · CoopCycle

- **Fuente:** https://github.com/coopcycle/coopcycle-web y https://github.com/coopcycle/coopcycle-app
  (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** plataforma de reparto en bicicleta para cooperativas de repartidores, pensada para operar ciudad
  por ciudad. `coopcycle-web` (606 ★, push 2026-10-27, PHP) trae la API, la web y el despacho; `coopcycle-app` es
  la app en React Native 0.81.5 con `react-native-maps`, Stripe, Firebase y geolocalización en segundo plano.
  Tiene demo pública: https://demo.coopcycle.org
- **Lo valioso:** es una plataforma real de última milla, con tareas para el repartidor. Su backend integra
  **MercadoPago** (`mercadopago/dx-php` en `composer.json`), además de Stripe y Paygreen: un ejemplo de pasarela
  latinoamericana integrada, útil para ver cómo se haría con Conekta.
- **Licencia:** la web usa la "CoopCycle License": cualquiera puede estudiarla y modificarla, pero el uso
  comercial solo se concede a entidades de economía social con modelo cooperativo de trabajadores (artículo 3).
  El LICENSE de la app es MIT ("Copyright (c) 2015 Zhen Wang"), pero la app es cliente de esa plataforma.
- **Puertas:** G-1 incierto · G-2 incierto: no se revisó el proveedor del mapa · G-3 falla · G-4 incierto ·
  G-5 falla: la licencia excluye el uso comercial de una empresa.
- **Veredicto:** **referente para ver una plataforma real funcionando** (la demo) y la integración de una
  pasarela latinoamericana. No es base.

### GH-07 · OwnFleet

- **Fuente:** https://github.com/Volodymyr4K/ownfleet (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** gestión de repartidores para negocios que reparten **con su propia gente**. API en NestJS con
  Prisma sobre PostgreSQL/PostGIS, panel en Next.js y app del repartidor en Expo con `react-native-maps`. MIT,
  1 ★, creado el 2026-03-21, último push el 2026-06-12, un autor.
- **Lo valioso:**
  - es el modelo de una de las respuestas posibles a P-03: la lavandería reparte con su gente (DD-022);
  - cada tabla lleva el negocio (`establishment_id`) y el aislamiento entre negocios tiene pruebas propias
    (RN-09, modelo multi-plaza);
  - máquinas de estado explícitas para pedidos, entregas y turnos, con transiciones protegidas (BR-22);
  - prueba de entrega verificada por ubicación (radio de 300 m) con foto opcional: sirve igual para la
    recolección;
  - liga de seguimiento para el cliente final;
  - su README declara 555 pruebas.
  - Está en TypeScript, Next.js y PostgreSQL: terreno conocido para Nadir (C-89).
- **Lo que choca:** la app manda la ubicación del repartidor cada 15 segundos y la plataforma tiene despacho
  automático; RN-12 excluye la asignación automática y el GPS continuo fuera de un pedido activo (tiene modo
  manual). No cobra. No tiene app de cliente.
- **Puertas:** G-1 incierto: solo trae el módulo del repartidor · G-2 incierto · G-3 falla: no hay cobro ·
  G-4 incierto: PostgreSQL, Redis y almacenamiento S3 · G-5 pasa: MIT.
- **Veredicto:** **el referente más útil de esta ampliación** para el lado de la lavandería y el repartidor.
  Nuevo y con un solo autor: lectura, no base.

### GH-08 · WityLogix

- **Fuente:** https://github.com/wityliti/witylogix (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** gestión de entregas de última milla para tiendas en línea: API en Fastify con Prisma, panel y
  portal del cliente en Next.js, página de seguimiento y app del repartidor en Expo. 5 ★, creado el 2026-03-06.
- **Lo valioso:** **precio por zona** con polígonos de PostGIS dibujados en un mapa (el mismo problema que el
  precio y el envío por plaza); seguimiento en vivo con mapa para el cliente; prueba de entrega; varias tiendas
  bajo una organización con repartidores y zonas compartidas.
- **Licencia:** el README la marca AGPL-3.0; la API de GitHub no la reconoce (`NOASSERTION`).
- **Puertas:** G-1 incierto · G-2 incierto · G-3 falla · G-4 incierto · G-5 falla: AGPL.
- **Veredicto:** **leer su modelo de zonas y precios.** No es base.

### GH-09 · Ever Demand

- **Fuente:** https://github.com/ever-co/ever-demand (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** plataforma de comercio bajo demanda: API en NestJS con GraphQL; app de compra en Expo; app del
  repartidor y **tableta del negocio** en Ionic; administración en Angular; Stripe. AGPL-3.0, 1,869 ★, desde
  2018, último push el 2026-09-27.
- **Lo valioso:** madura, y con una idea aplicable: el negocio opera los pedidos desde una tableta en el
  mostrador.
- **Puertas:** G-1 falla: apps en dos frameworks distintos · G-2 falla tal como está: el repartidor usa el plugin
  de Google Maps de Ionic · G-3 falla · G-4 incierto · G-5 falla: AGPL.
- **Veredicto:** **referente de producto maduro, no base.** Frameworks viejos (Ionic con Cordova).

### GH-10 · Material de aprendizaje: "Uber clone" de JavaScript Mastery

- **Fuente:** https://github.com/adrianhajdin/uber (consultado 2026-10-28) SIN VERIFICAR
- **Qué es:** el código de un tutorial en video, paso a paso: Expo, Google Maps con rutas, Stripe, PostgreSQL
  sin servidor y Clerk. 2,205 ★, último push 2024-10-29, **sin licencia**.
- **Para qué sirve:** es la forma más directa de ver mapa, ubicación y cobro en una app móvil para quien nunca
  ha hecho una (C-89). Sin licencia: se estudia, no se copia.
- **Cuidado de método:** es Expo. Sirve si el paso 6 elige Expo; verlo antes de fijar los pesos empuja hacia
  ese framework.

### Descartados en la ampliación

| Repo | Por qué no |
|---|---|
| `Autofleet/locomotion` (MIT, 55 ★) | App de pasajero abierta, pero funciona contra el motor de viajes de Autofleet, que se obtiene por convenio. Mismo problema que el backend de Enatega |
| `KanishJebaMathewM/Truxify` (MIT, 53 ★) | Creado el 2026-05-11, con blockchain, eBPF, Kubernetes y un servicio de aprendizaje automático: mucho más de lo que pide el contrato. Mapa con `flutter_map` (OpenStreetMap): falla G-2 |
| `VadimNotJustDev/UberClone` (MIT, 314 ★) | Último push en 2024; dos apps separadas |
| `offfahad/uber-clone` (76 ★) | Sin licencia; Flutter con Firebase y Google Maps en las dos plataformas |
| Clones de Uber Eats y kits de mensajería sin licencia (`HuzaifaChaudary/UBER_EATS_APP_CLONE`, `abdulazizahwan/flutter-parcel-app` y otros) | Sin licencia: no se puede copiar nada |
| `BlondelSeumo/Grocery-Pharmacy-Courier-Delivery-App-Backend-Driver-Vendor-app`, `BlondelSeumo/Delivery-Boy-for-Groceries-Foods-Pharmacies-Stores-Flutter-App` | Sin licencia, y parecen apps comerciales completas republicadas. Origen no verificable |
| Servicios a domicilio de nicho: `amisha-srivastavaa/ServeNow` (planchado, tintorería, calzado, sastrería), `Vikrantkaramore/threadify` (sastrería), `anudeepmuppalla1729/AAMigos` (reparaciones), `MMXV16/Scruba-Car-Wash` y `MarianMocanu/washworld-mobile-app` (autolavado) | De 0 a 3 ★, recientes. Confirman que el patrón existe, pero no aportan código maduro. Solo vistos en la búsqueda |
| `VROOM-Project/vroom`, `PyVRP/PyVRP`, `EijunnN/better-route` | Optimizan rutas: el Módulo C lo excluye (RN-12) |

### Cómo se combinan las dos búsquedas (propuesta de Claude; decide Nadir)

| Para… | Leer |
|---|---|
| El ciclo del pedido con el trabajo en medio | GH-03 LaundryEase, y las máquinas de estado de GH-07 OwnFleet |
| La lavandería que reparte con su gente (P-03) | GH-07 OwnFleet |
| Zonas, cobertura y precio por plaza | GH-08 WityLogix |
| Ver una plataforma real funcionando | La demo pública de GH-06 CoopCycle |
| Integrar una pasarela latinoamericana | El backend de GH-06 CoopCycle (MercadoPago) |
| Pantallas de tres lados | GH-01 Enatega |

### Preguntas para Nadir de la ampliación

4. Si la respuesta a P-03 es que la lavandería reparte con su gente, ¿tomamos GH-07 como referente del Módulo C?
5. ¿Vemos juntos la demo pública de CoopCycle para recorrer un pedido real de punta a punta?

Desde el traspaso del 2026-11-12 (C-132), estas preguntas las responde Arthur, no Nadir.

## Revisión del 2026-11-12

### Shita choca con `investigacion-proyectos-base.md`

La nota de Nadir del 2026-10-28 (`arquitectura/investigacion-proyectos-base.md`) propone a Shita como
**candidato nuevo** (OP-08), por ser "Enatega con servidor". Esta nota lo había descartado el mismo día (fila de
`ferasshita/Shita-Multivendor-Food-Delivery` en "Revisados y descartados"): su LICENSE quitó el aviso original de
Enatega, y eso incumple la propia MIT. Además, el origen del backend no se puede verificar.

Las dos notas no se vieron entre sí, porque esta nunca llegó al repo. **Recomendación:** no arrancar Shita en local
ni sumarlo como OP-08 hasta resolver el origen de su código. Va en el issue del 2026-11-12 para Nadir.

### GH-03 LaundryEase, contra el repo en vivo

Consulta del 2026-11-12, grado E, `SIN VERIFICAR`. No se descargó ni se ejecutó nada.

**El repo:**

- **Licencia y actividad.** MIT, agregada el 2026-07-09, que es también el último push. Tiene 3 ★ y 0 forks.
- **Tecnologías.** TypeScript con Next.js (App Router), MongoDB, Socket.IO, NextAuth, Razorpay y Cloudinary.
- **Tamaño.** 496 archivos y 90 endpoints de API.
- **Pruebas.** Hay 125 archivos de prueba: casi todos los endpoints traen el suyo (Vitest), y además hay 6
  recorridos de punta a punta con Playwright. Su autoevaluación (`docs/HONEST_ASSESSMENT.md`, del 2026-03-17) dice "616 pruebas en 116 archivos" y
  que todo pasa. Es una afirmación del autor: no se comprobó.
- **Solo web.** No hay app móvil.

**El veredicto de arriba se sostiene: el mejor referente del ciclo del pedido, no base.** Está en otro stack
(Next.js y MongoDB), es solo web, y su manejo del dinero choca con el contrato: dinero retenido, liquidación
automática con RazorpayX (RN-12) y verificación del teléfono (RN-24).

Lo que vale la pena llevarse como **idea para las specs**, no como código:

| Pieza (archivo) | Qué hace | Para qué sirve en Lavandi |
|---|---|---|
| `types/bookings.ts` | Reserva: `requested` → `accepted` / `rejected` → `pickup_proposed` / `reschedule_requested` → `confirmed` → `invoice_created` → `completed` / `cancelled` | El tramo antes de pesar: estimado, recolección y cobro ajustado (DD-012) |
| `lib/orders/status-machine.ts` | Proceso: `invoiced` → `processing` → `washing` → `ironing` → `ready` → `out_for_delivery` → `delivered`. Las transiciones viven en un solo lugar del servidor | La máquina de estados del pedido (BR-22) y el criterio de los 60 s |
| `lib/bookings/cancellation-policy.ts` | Política de cancelación como función pura. Hay una ventana gratis de 2 h. Si cancela el proveedor, se reembolsa todo. Si la factura ya existe, el usuario pierde lo pagado por la reserva | El molde de DD-016, con las reglas que marcó el cliente el 2026-11-05 |
| `lib/utils/delivery-charge.ts` | Envío gratis dentro de un radio y cobro por km fuera de él | Una opción para DD-019 y DD-025 |
| `lib/services/provider-search.ts`, `lib/services/provider-availability.ts` | Búsqueda por radio de servicio y horarios disponibles | Módulos A y B |
| `components/seeker/delivery-otp-form.tsx`, `lib/services/account-deletion.ts` | Confirma la entrega con un código. No deja borrar la cuenta si hay pedidos activos | Reglas de operación y de las tiendas |
| `e2e/booking-lifecycle-journey.spec.ts` y las pruebas por endpoint | Recorrido completo probado de punta a punta | El modelo para la tabla criterio → prueba de la Fase 7 |

**Cómo usarlo:** una sesión con tope de tiempo para leer esos archivos y pasar sus reglas a las specs de Lavandi.
Si alguna vez se copia algo literal, se conserva su aviso MIT. Eso choca con la cesión de la Cláusula IX (P-15),
igual que con cualquier librería de terceros.

**Para el perfil de Arthur (C-135):** dos referentes de esta nota usan Laravel, pero ninguno se puede copiar.
Fleetbase (GH-04) tiene su API en Laravel con licencia AGPL, y E-Laundry (GH-05) tiene una licencia contradictoria.
Sirven para mirar.
