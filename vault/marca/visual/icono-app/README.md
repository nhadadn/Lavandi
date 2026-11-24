---
estado: vigente
grado: B
fuente: generado el 2026-11-22 desde lavandi-logotipo-color.png y lavandi-icono-app-1024.png de esta misma carpeta de marca (C-63); pedido de Arthur Naranjo del 2026-11-21 ("el icono de la app debe ser el de LAVANDI que tenemos")
fecha: 2026-11-22
dueño: Arthur Naranjo
---
# Ícono de la app

Las capas del ícono de la app, tomadas del logotipo de la marca. El repositorio de código (`movil/assets/`) conserva el
marcador neutro de Expo, porque la marca no va en el código (`CLAUDE.md` §5). La compilación local
(`herramientas/apk-local/compilar.ps1`) copia estas capas a su copia desechable antes del prebuild. La compilación de
las tiendas las tomará igual en `publicacion`.

| Archivo | Para qué |
|---|---|
| `icon.png` | El ícono completo, 1024×1024: iPhone y Android viejo. Es el que entregó el diseñador. |
| `android-icon-foreground.png` | La figura del ícono adaptable de Android, 512×512 con transparencia. Va dentro de la zona segura (el 72/108 central), para que ninguna máscara la recorte. |
| `android-icon-background.png` | El fondo del ícono adaptable: el hueso `#F7F4EF` de la paleta. |
| `android-icon-monochrome.png` | La silueta en blanco, 432×432, que Android 13 y más nuevos tiñen con el tema. |

La figura se generó quitando el cuadro hueso del logotipo, porque el fondo lo pone su propia capa. Así no queda una
raya en el borde al reducirla.
