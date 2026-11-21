---
estado: vigente
grado: mixto
fuente: NSV-2026-001-v8.pdf:20-21:ANEXO A, Sección A.4 y :20:ANEXO A, Sección A.3 (Medidas de seguridad) (A); ADR-0003, ADR-0007, ADR-0009 y ADR-0010 (D); deuda-de-decision.md (B); marca/entregables/ (B, páginas contadas el 2026-11-17)
fecha: 2026-11-17
dueño: Arthur Naranjo
---
# Trazabilidad: criterio de aceptación → test

Cada criterio de aceptación del ANEXO A.4 tiene aquí cómo se acredita. Los criterios de software se acreditan con un
test automático **y** con evidencia en el entorno de prueba o en producción. Los de branding y de lanzamiento no son
software: se acreditan con archivos o con actos, y se marcan `fuera del código`.

**Estados**, en orden: `sin spec` · `especificado` · `test en rojo` · `implementado` · `acreditado en prueba` ·
`acreditado en producción` · `fuera del código`.

**La columna Test** se llena al implementar, con la ruta del test en este repositorio: `servidor/tests/...` o `movil/__tests__/...`.
Ninguna fila pasa a `implementado` sin una ruta de test en verde (CLAUDE.md §3, regla 7).

## A.4.1 Branding

| ID | Criterio (A, p. 20-21) | Cómo se acredita | Estado y evidencia hoy (B) |
|---|---|---|---|
| A41-1 | Logotipo entregado en los 4 formatos y variaciones especificados en A.1 | Cotejo de los archivos contra la lista del A.1 | `fuera del código`. Los archivos están en `marca/visual/logotipo/`. **Falta el cotejo contra el A.1** |
| A41-2 | Manual de identidad visual de mínimo 15 páginas | El PDF y su número de páginas | `fuera del código`. `marca/entregables/manual-de-identidad.pdf` tiene 18 páginas. Hay rondas de corrección abiertas (DD-045, DD-059) |
| A41-3 | 12 plantillas de redes sociales en Canva o Figma, accesibles para EL CLIENTE | Los enlaces a las 12 plantillas en una cuenta del cliente | `fuera del código`. **No existen** (DD-050) |
| A41-4 | Guía de tono de voz entregada en PDF | El PDF | `fuera del código`. `marca/entregables/guia-de-tono-de-voz.pdf`, 3 páginas |

Ninguno está notificado como entregado: `cliente/notificaciones-de-entrega/` está vacía (DD-050).

## A.4.2 Landing page

| ID | Criterio (A, p. 21) | Prueba automática prevista | Evidencia para acreditar | Bloqueos | Test | Estado |
|---|---|---|---|---|---|---|
| A42-1 | Las 12 secciones del A.2 presentes y bien renderizadas en Chrome, Safari y Firefox | Playwright con Chromium, WebKit y Firefox: cada sección existe y es visible | Capturas en los tres navegadores reales | — | — | `sin spec` |
| A42-2 | GA4 y Meta Pixel registran eventos en su panel | Playwright intercepta las llamadas a GA4 y al Pixel en los eventos clave | Captura del tiempo real de GA4 y del administrador de eventos de Meta | Cuentas a nombre del cliente (B.11) | — | `sin spec` |
| A42-3 | Sitio publicado en la dirección acordada, con candado activo | Prueba de que `http` redirige a `https` con certificado válido | Captura del sitio con el candado | Dominio (DD-023) y hosting (DD-070) | — | `sin spec` |
| A42-4 | 70 puntos o más en PageSpeed Insights, en teléfono | Lighthouse CI con umbral de 0.70 en rendimiento, como alerta temprana | Captura de PageSpeed Insights sobre la dirección publicada, con fecha | Dominio y hosting | — | `sin spec` |

## A.4.3 App móvil

| ID | Criterio (A, p. 21) | Prueba automática prevista | Evidencia para acreditar | Bloqueos | Test | Estado |
|---|---|---|---|---|---|---|
| A43-1 | App compilada y funcional, compatible con la gran mayoría de los teléfonos, entregada para publicarse en las dos tiendas | Test que lee la configuración y comprueba los mínimos iOS 18 y Android 11 (ADR-0009); compilación de producción de EAS para las dos plataformas | Las compilaciones y su envío a App Store Connect y a Play Console | Cuentas de las tiendas (DD-017, B.10) | — | `sin spec` |
| A43-2 | El recorrido completo (elegir lavandería, agendar, pagar y dar seguimiento) se completa sin errores que lo impidan | Servidor: test del ciclo completo del pedido. Teléfono: flujo de Maestro en Android y en iPhone, con pago en sandbox | Video del recorrido en cada plataforma | Modelo de cobro (DD-012) y asignación (DD-014) | — | `sin spec` |
| A43-3 | El cambio de estado de la lavandería o del repartidor se ve en la app del usuario en 60 s o menos | Servidor: el cambio de estado dispara el aviso (FCM simulado) y se ve en la API. App: la consulta corre cada 30 s (ADR-0007) | 10 cambios cronometrados en teléfonos reales; el máximo, 60 s o menos | — | — | `sin spec` |
| A43-4 | Conekta procesa transacciones en producción | Webhooks idempotentes contra el sandbox (ADR-0010) | Una transacción real con la cuenta del cliente, con su comprobante | Alta del cliente en Conekta (B.10) y efectivo sin OXXO (DD-056) | — | `sin spec` |
| A43-5 | Los avisos llegan en iPhone y en Android en los cinco momentos del pedido | Servidor: un test por momento que comprueba el envío (FCM simulado) | Captura de cada aviso en cada plataforma | **SIN RESPUESTA — bloquea la spec:** cuáles son los cinco momentos (DD-011) | — | `sin spec` |
| A43-6 | Los cuatro módulos accesibles y funcionales conforme al A.3 | Una spec por módulo, con al menos un escenario por función del inventario funcional | La lista de funciones de cada módulo, cada una con su test | Acceso por rol (DD-053) | — | `sin spec` |
| A43-7 | El panel de administración muestra datos reales de pedidos y de usuarios activos | Tests con datos sembrados | Captura del panel en el entorno de prueba o en producción, con datos reales | Lavanderías reales (DD-021) | — | `sin spec` |

## A.4.4 Panel de indicadores

| ID | Criterio (A, p. 21) | Prueba automática prevista | Evidencia para acreditar | Bloqueos | Test | Estado |
|---|---|---|---|---|---|---|
| A44-1 | Muestra en tiempo real los indicadores del A.5 | Un test por indicador sobre datos sembrados, y el intervalo de refresco del panel | Captura del panel con los 11 indicadores | — | — | `sin spec` |
| A44-2 | Reportes exportables en CSV o Excel | Test de la exportación: archivo generado, columnas y protección contra fórmulas inyectadas (ADR-0003) | Un archivo exportado de cada reporte | — | — | `sin spec` |

## A.4.5 Acompañamiento de lanzamiento digital

| ID | Criterio (A, p. 21) | Cómo se acredita | Estado |
|---|---|---|---|
| A45-1 | Perfiles de marca configurados y accesibles para EL CLIENTE en las plataformas del A.8 | La entrega de los accesos, asentada en un acta | `fuera del código` |
| A45-2 | Sesión de capacitación impartida y materiales entregados | El acta de la sesión y los materiales | `fuera del código` |

El contrato dice que este entregable nunca se acredita con alcance, seguidores ni interacción (A, p. 21).

## Medidas de seguridad del A.3

No son criterio del A.4, pero son obligación del contrato (A, p. 20). Cada una lleva su test.

| ID | Medida (A, p. 20) | Prueba automática prevista | Test | Estado |
|---|---|---|---|---|
| SEG-1 | Comunicaciones cifradas | En producción, la API y el panel solo responden por `https` | — | `sin spec` |
| SEG-2 | Contraseñas no legibles, ni para quien administra | La contraseña guardada no es el texto y solo se valida con su hash | — | `sin spec` |
| SEG-3 | Cada tipo de usuario ve solo lo suyo: una lavandería no ve los datos de otra ni los de usuarios que no le han pedido | Tests de permisos por rol, tenencia y plaza | — | `sin spec` |
| SEG-4 | Los números de tarjeta nunca se guardan en la plataforma | Test de esquema: ninguna tabla guarda números de tarjeta, solo la referencia de Conekta (DD-055) | — | `sin spec` |
