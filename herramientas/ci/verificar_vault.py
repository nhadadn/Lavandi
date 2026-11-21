"""Verifica el frontmatter de las notas del vault. Lo corre la integración continua de este repositorio.

Uso: python herramientas/ci/verificar_vault.py

Falla (código de salida 1) si alguna nota de `vault/`:
- no tiene frontmatter;
- no tiene alguna de las claves obligatorias: estado, grado, fuente, fecha, dueño (CLAUDE.md §4);
- tiene un `estado` fuera del vocabulario: borrador, vigente o superseded (meta/convenciones.md).

Regla de `vault/meta/convenciones.md`: sin frontmatter, la nota no cuenta.
"""
import io
import os
import re
import sys

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
VAULT = os.path.join(RAIZ, 'vault')
CLAVES = ('estado', 'grado', 'fuente', 'fecha', 'dueño')
# Notas de carpeta que explican el contenido, sin valor de evidencia; igual que en herramientas/validar-vault/validar.py.
EXENTAS = {'README.md'}
ESTADOS = ('borrador', 'vigente', 'superseded')


def claves_del_frontmatter(texto):
    """Devuelve el diccionario del frontmatter, o None si la nota no tiene."""
    m = re.match(r'\A---\r?\n(.*?)\r?\n---\r?\n', texto, re.S)
    if not m:
        return None
    claves = {}
    for linea in m.group(1).splitlines():
        mm = re.match(r'^([^\s:#][^:]*):\s*(.*)$', linea)
        if mm:
            claves[mm.group(1).strip()] = mm.group(2).strip()
    return claves


def main():
    errores = []
    total = 0
    for carpeta, subcarpetas, archivos in os.walk(VAULT):
        subcarpetas[:] = [s for s in subcarpetas if not s.startswith('.')]
        for nombre in sorted(archivos):
            if not nombre.endswith('.md') or nombre in EXENTAS:
                continue
            ruta = os.path.join(carpeta, nombre)
            rel = os.path.relpath(ruta, RAIZ).replace(os.sep, '/')
            total += 1
            claves = claves_del_frontmatter(io.open(ruta, encoding='utf-8').read())
            if claves is None:
                errores.append('%s: no tiene frontmatter' % rel)
                continue
            faltan = [c for c in CLAVES if c not in claves]
            if faltan:
                errores.append('%s: le falta %s' % (rel, ', '.join(faltan)))
            elif claves['estado'] not in ESTADOS:
                errores.append('%s: estado "%s" fuera del vocabulario (%s)'
                               % (rel, claves['estado'], ', '.join(ESTADOS)))
    for e in errores:
        print(e)
    print('%d notas revisadas, %d con problema' % (total, len(errores)))
    sys.exit(1 if errores else 0)


if __name__ == '__main__':
    main()
