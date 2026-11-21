## Context

- La landing vive en el mismo Laravel que la API y el panel: vistas Blade con Tailwind, servidas en `lavandi.mx`
  (ADR-0011, sobre ADR-0003). Consume las variables CSS que genera el adaptador de tokens (ADR-0013), solo en modo
  claro (C-141).
- Testimonios, logos de prensa y su interruptor de activo se editan desde Filament (ADR-0011).
- El mapa de cobertura se dibuja con polígonos propios, sin teselas de pago (ADR-0009, decisión 5;
  `vault/arquitectura/presupuesto-de-mapas.md`, decisión 1). Las zonas y el estado de cada plaza los carga
  `plazas-y-zonas` con PostGIS (ADR-0004; `vault/arquitectura/modelo-multi-plaza.md`). Cómo se trazan las zonas
  (BR-02) lo resuelve ese cambio.
- No hay mocks de la landing (`mocks/LEEME.md`, "Qué falta") ni componentes del design system para sus secciones
  (`vault/contrato/inventario-funcional.md`, sección 8). Rigen las reglas transversales de
  `vault/design-system/componentes.md`: el color nunca solo, el foco visible y el movimiento reducido.
- Los textos y las imágenes son insumo de EL CLIENTE (`NSV-2026-001-v8.pdf:6:Cláusula III`, inciso B). Hoy no existen.

## Goals / Non-Goals

**Goals:**

- Las 12 secciones del A.2 presentes y bien renderizadas en los tres motores, primero con contenido de prueba (A42-1).
- Que el contenido del cliente entre sin programar: el A.7 cubre "Ajustes menores de contenido —textos, colores e
  imágenes— que no requieran programación nueva" (`NSV-2026-001-v8.pdf:23:ANEXO A, Sección A.7`).
- Un mapa de cobertura que no genere costo por visita.

**Non-Goals:**

- La medición, los buscadores, la velocidad y el candado (`medicion-de-la-landing`).
- El texto de los documentos legales (`textos-legales`).
- Cargar o trazar las zonas (`plazas-y-zonas`).
- Diseñar las pantallas de las secciones: es una pregunta abierta, abajo.
- Páginas por ciudad, ficha de Google Maps o cualquier contenido fuera del territorio inicial
  (`NSV-2026-001-v8.pdf:4:Cláusula I`).

## Decisions

No hay decisión técnica nueva: el cambio aplica ADR-0003, ADR-0004, ADR-0009, ADR-0011 y ADR-0013, sin repetirlos.

La spec toma cuatro lecturas mínimas del A.2. No son decisiones técnicas ni vienen del contrato; Arthur las puede
cambiar sin tocar los requisitos:

- El botón principal del encabezado lleva a la sección "Descarga la app", porque los enlaces de las tiendas no existen
  hasta publicar (landing-E04).
- Con más de 5 testimonios activos se muestran 5, en el orden del panel; con menos de 3, se completan con marcadores
  de posición (landing-E15 y landing-E16).
- La sección de prensa sigue presente sin logos activos, para que las 12 secciones estén siempre (landing-E18).
- Un botón de tienda sin enlace se muestra sin enlace, nunca con uno vacío (landing-E09).

## Tablas y migración

Este cambio no crea ninguna tabla del modelo: en `vault/arquitectura/modelo-de-datos.md` ninguna tabla tiene `landing`
en "Nace en" (ADR-0016). No trae migración ni trigger `impedir_cambio()`.

Lee, sin escribir:

| Tabla | Columnas que usa | Para qué | Nace en |
|---|---|---|---|
| `plazas` | `id_plazas`, `activa` | landing-E14: solo se dibujan las zonas de las plazas activas | `plazas-y-zonas` |
| `zonas` | `id_zonas`, `id_plazas`, `nombre`, `poligono`, `activa` | landing-E12: el polígono simplificado y el nombre de cada zona activa | `plazas-y-zonas` |
| `usuarios`, `rol_usuario`, `roles` | `rol_usuario.id_usuarios`, `rol_usuario.id_roles`, `roles.codigo` | landing-E19: solo `administracion` edita, con el componente de `autorizacion-por-rol` | `registro-e-inicio-de-sesion` |

Lo que esta spec guarda y el modelo todavía no tiene:

- **Testimonios y logos de prensa** (landing-E15 a landing-E19). ADR-0011 los hace editables desde Filament, con su
  interruptor de activo y su orden, así que necesitan dónde guardarse. El modelo los deja fuera a propósito: "los
  textos de la landing (ADR-0011): entran cuando se decidan" (sección "Lo que no tiene tabla todavía"). Esta spec no
  fija sus nombres ni sus columnas: primero tienen que entrar a `modelo-de-datos.md`, con su "Nace en", antes de las
  tareas 4.1 y 4.2. Su migración pasará la prueba de esquema de `modelo-de-datos` (modelo-E01 a modelo-E17): o llevan
  `id_plazas`, o entran a la lista cerrada de tablas sin plaza de `servidor/config/modelo.php`, como decisión visible en
  el diff (modelo-E05). No llevan el trigger `impedir_cambio()`.
- **Los formularios de interés y de contacto** (landing-E22). Si van por correo, como recomienda DD-119, no guardan
  nada. Si se guardan para verlos en el panel, la tabla no existe en el modelo (pregunta abierta 5).
- **Preguntas frecuentes y demás textos.** Si se vuelven editables en Filament (pregunta abierta 4), tampoco tienen
  tabla en el modelo.
- **Los enlaces de las tiendas, el correo, el número de WhatsApp Business y el titular del aviso de derechos de autor**
  salen de la configuración, no de una tabla.

## Risks / Trade-offs

- [La landing comparte servidor con la API] → si el servidor cae, caen las dos. Lo aceptó ADR-0011.
- [Los polígonos del Estado de Durango completo pesan y bajan la velocidad en teléfono] → se simplifica la geometría en
  el servidor antes de mandarla; el umbral de 70 lo vigila `medicion-de-la-landing` (A42-4).
- ["Cero gestión de cobranza" puede prometer algo que el modelo de dinero no haga] → el título es del contrato; el
  texto que lo explica espera a DD-013.
- [Un marcador de posición puede leerse como una opinión real] → su texto lo decide EL CLIENTE (DD-117).
- [Los formularios recaban datos personales] → el aviso de privacidad y los consentimientos son de EL CLIENTE
  (`NSV-2026-001-v8.pdf:10:Cláusula X`); qué aviso acompaña al formulario va en DD-119.
- [El cliente marcó "No" en el mapa de cobertura en el formulario en papel del 2026-11-05, sin confirmar] → se
  construye porque el contrato lo pide; quitarlo sería Orden de Cambio.

## Open Questions

1. **¿Quién diseña las 12 secciones?** No hay mocks ni componentes del design system para ellas. Lo decide Arthur.
2. **¿Se compra una plantilla?** ADR-0011 lo permite solo si su licencia completa, leída con sesión iniciada, permite
   entregar el sitio, y esa licencia se archiva en el vault. Si no, se sigue sin plantilla.
3. **Cómo se dibujan los polígonos.** Un SVG propio a partir del GeoJSON de las zonas no agrega dependencias y cabe en
   ADR-0009. Una librería de mapas para web o un fondo de calles de un tercero, aunque sea gratuito, es una dependencia
   nueva: **necesita ADR**.
4. **Preguntas frecuentes y demás textos: ¿archivo de contenido o editables en Filament?** ADR-0011 solo hace
   editables los testimonios y la prensa. Hacer editable lo demás **necesita que Arthur amplíe ADR-0011**.
5. **El destino de los formularios** (BR-41, DD-119). Si van por correo, usan el proveedor de ADR-0014, que
   sigue abierto (DD-071). Si se guardan para verlos en el panel, es una vista que el Módulo D no enumera
   (`NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3`).
6. **Protección contra envíos automáticos en los formularios.** El contrato no la nombra. Si se agrega, **necesita
   ADR**.
7. **Las pruebas de navegador.** Playwright desde Pest o desde Node. Las tareas usan `servidor/tests/Browser/Landing/`;
   la ruta se ajusta al confirmarlo (`vault/formas-de-trabajo/ciclo-openspec-tdd.md`, "Herramientas de prueba", que es
   propuesta).
