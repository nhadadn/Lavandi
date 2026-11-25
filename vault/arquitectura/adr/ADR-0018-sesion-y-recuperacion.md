---
estado: vigente
grado: D
fuente: acta-2026-11-20-modelo-y-pasarela.md C-223 (C); ADR-0005 (D); openspec/changes/registro-e-inicio-de-sesion/design.md, preguntas abiertas 1, 2, 4, 5 y 6 (B)
fecha: 2026-11-20
dueño: Arthur Naranjo
adr: ADR-0018
supersede:
---
# ADR-0018 — Dónde guarda la app la sesión y cómo se recupera la contraseña

## Contexto

- **El contrato pide** guardar la sesión "de forma segura" y sin pedir la contraseña cada vez
  (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`, Control de acceso); contraseñas que nadie pueda leer (misma página,
  Protección de contraseñas); el correo "al recuperar una contraseña" (`NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`);
  y "medidas de seguridad técnicas razonables" (`NSV-2026-001-v8.pdf:10:Cláusula X`).
- **ADR-0005 decide el mecanismo:** tokens de Sanctum para la app y recuperación por correo con un enlace que vence.
  No decide dónde vive el token en el teléfono, la regla de la contraseña, el límite de intentos ni dónde se abre el
  enlace. Son las preguntas abiertas 1, 2, 4, 5 y 6 del diseño de `registro-e-inicio-de-sesion`.

## Criterios y pesos

Los de la matriz `vigente` (C-142): familiaridad (30), velocidad (25), proveedor (15), tiempo real (10), costo (10) y
multi-plaza (10). Puntajes de 1 a 5; el total es la suma ponderada sobre 100. Los puntajes son de Claude y los aprobó
Arthur (C-223).

## Alternativas evaluadas

**Dónde guarda la app el token:**

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Almacenamiento cifrado del sistema** (Keychain en iPhone, Keystore en Android) con `expo-secure-store` | 4 | 5 | 5 | 5 | 5 | 5 | **94** |
| B. Almacenamiento local sin cifrar (`AsyncStorage`) | 4 | 5 | 4 | 5 | 5 | 5 | **91**, pero se descarta |

**B se descarta** porque guarda el token en texto legible: no es "de forma segura".

**Dónde se abre el enlace de recuperación:**

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. Una página web del servidor,** con el formulario de la contraseña nueva | 5 | 5 | 5 | 5 | 5 | 5 | **100** |
| B. Un enlace profundo que abre la app | 3 | 2 | 4 | 5 | 4 | 5 | **69** |

- **B** necesita el dominio comprado (DD-023) y la configuración de enlaces en las dos tiendas, que todavía no existen.
- **A** es Laravel puro, que Arthur ya usa, y funciona desde cualquier buzón.

## Decisión

Lo decidió Arthur el 2026-11-20 (C-223):

1. **El token de la sesión** se guarda en el almacenamiento cifrado del sistema con `expo-secure-store`. Al cerrar la
   sesión, o si el servidor ya no lo reconoce, se borra.
2. **La contraseña** pide un mínimo de **8 caracteres**. El valor vive en la configuración del servidor.
3. **El límite de intentos** es de **5 por minuto**, por correo y por dirección, en el inicio de sesión y al pedir la
   recuperación. Al pasarlo, el servidor responde que hay demasiados intentos. El valor vive en configuración.
4. **El enlace de recuperación** abre una página web del servidor, vence a los **60 minutos** y sirve una sola vez. El
   plazo vive en configuración.
5. **Pedir la recuperación responde igual** exista o no la cuenta. Solo la cuenta que existe recibe el correo.
6. **Crear la contraseña nueva cierra las sesiones abiertas** de la cuenta: se revocan todos sus tokens.

## Consecuencias

- **Positivas:**
  - el token no queda legible en el teléfono;
  - nadie puede averiguar qué correos tienen cuenta a través de la recuperación;
  - quien recupera su contraseña saca a cualquiera que tuviera su sesión.
- **Negativas:**
  - la página de recuperación es web, fuera de la app;
  - mientras no haya dominio ni HTTPS, en local se prueba con el túnel de `cloudflared` (diseño del cambio). SEG-1 se
    acredita en `publicacion`.
- **Condiciona:** registro-E03, E11, E13 y E22 a E25, y los escenarios nuevos registro-E30 y E31 (límite de intentos).

## Criterio de reversión

- **El enlace profundo (B):** se reabre si EL CLIENTE pide que la recuperación ocurra dentro de la app, cuando exista
  el dominio.
- **La contraseña y el límite:** se ajustan en configuración si una revisión de seguridad lo pide. Sin código nuevo.

## Costo estimado de revertir

- **Pasar la recuperación a un enlace profundo:** de 6 a 10 horas, más el dominio y la configuración de las tiendas.
- **Cambiar el almacenamiento del token:** de 1 a 2 horas; las personas tendrían que volver a iniciar sesión una vez.

## Trazabilidad

Satisface F-T07, F-T09 y F-T13 (`NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3`; `:30:ANEXO B, Sección B.5`) y la
Cláusula X (p. 10) · Respeta ADR-0005 y ADR-0014 · Dependen de ella `registro-e-inicio-de-sesion` y, para cerrar
sesiones, `perfil-y-soporte`.
