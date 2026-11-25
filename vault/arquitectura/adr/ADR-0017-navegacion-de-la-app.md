---
estado: vigente
grado: D
fuente: acta-2026-11-20-modelo-y-pasarela.md C-222 (C); ADR-0002 (D); movil/package.json (B)
fecha: 2026-11-20
dueño: Arthur Naranjo
adr: ADR-0017
supersede:
---
# ADR-0017 — La app navega entre pantallas con expo-router

## Contexto

- **La app es una sola, con Expo** (ADR-0002). `base-tecnica` la dejó con una sola pantalla, en `movil/App.tsx` (B).
- **Ya hacen falta varias pantallas:** la bienvenida y sus tres informativas (`NSV-2026-001-v8.pdf:16:ANEXO A,
  Sección A.3`, Módulo A #1) y el registro, el inicio de sesión y la recuperación de la contraseña (Módulo A #2;
  `NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5`). Después vienen los cuatro módulos.
- **Ningún ADR decidía cómo se navega** entre pantallas. La pregunta salió al aplicar `bienvenida` y
  `registro-e-inicio-de-sesion` (2026-11-20).

## Criterios y pesos

Los de la matriz `vigente` (C-142): familiaridad (30), velocidad (25), proveedor (15), tiempo real (10), costo (10) y
multi-plaza (10). Puntajes de 1 a 5; el total es la suma ponderada sobre 100.

## Alternativas evaluadas

| Alternativa | Familiaridad (30) | Velocidad (25) | Proveedor (15) | Tiempo real (10) | Costo (10) | Multi-plaza (10) | **Total** |
|---|---|---|---|---|---|---|---|
| **A. expo-router:** una pantalla por archivo en `movil/app/` | 4 | 5 | 5 | 5 | 5 | 5 | **94** |
| B. React Navigation configurada a mano, con pilas y pestañas | 3 | 3 | 4 | 5 | 5 | 5 | **75** |
| C. Cambiar de pantalla con estado propio, sin librería | 4 | 2 | 5 | 5 | 5 | 5 | **79**, pero se descarta |

Justificación de cada puntaje (los puntajes son de Claude y los aprobó Arthur, C-222):

- **Familiaridad:** las rutas por archivo se parecen a las rutas web de Laravel, el servidor del proyecto (ADR-0003);
  React Navigation pide aprender su configuración de pilas.
- **Velocidad:** con expo-router, agregar una pantalla es agregar un archivo. B pide declarar cada pantalla en una
  pila. C crece mal con cuatro módulos y no resuelve el botón de regreso de Android.
- **Proveedor:** expo-router lo mantiene Expo y se instala en la versión que corresponde al SDK (grado E, de la
  documentación de Expo, `SIN VERIFICAR` en esta sesión). B es de la comunidad, sobre la que se apoya expo-router.
- **Tiempo real, costo y multi-plaza:** ninguna de las tres los afecta; las tres son gratuitas.

**C empata en tiempo real, costo y multi-plaza, y pierde en velocidad.** Se descarta porque cada módulo nuevo
obligaría a reescribir el control de pantallas.

## Decisión

La app navega con **expo-router**, en la versión que corresponde al SDK de Expo instalado (`npx expo install
expo-router`). Las pantallas viven en `movil/app/`. Lo decidió Arthur el 2026-11-20 (C-222).

## Consecuencias

- **Positivas:**
  - una pantalla por archivo;
  - el botón de regreso de Android funciona sin código propio;
  - los enlaces profundos vienen incluidos, por si un día la recuperación de la contraseña abre la app (ADR-0018 hoy la
    lleva a una página web).
- **Negativas:**
  - la entrada de la app cambia de `App.tsx` a la de expo-router;
  - los tests de pantallas necesitan las utilidades de prueba de expo-router.
- **Condiciona:** todas las specs de `movil/`, que ubican sus pantallas en `movil/app/`.

## Criterio de reversión

Se reabre si expo-router impide una función del contrato, o si una pantalla no se puede expresar con él después de 2
sesiones de trabajo. En ese caso se pasa a B, React Navigation, sobre la que expo-router ya se apoya.

## Costo estimado de revertir

Con las pantallas de H1, de 4 a 6 horas: mover los archivos de `movil/app/` a pilas declaradas y cambiar la entrada.
Crece con cada pantalla nueva. No se pierden datos.

## Trazabilidad

Satisface la navegación de F-A01 y F-A02 (`NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3`, Módulo A #1 y #2) · Respeta
ADR-0002 · Dependen de ella `bienvenida` y `registro-e-inicio-de-sesion`, y después todas las pantallas de `movil/`.
