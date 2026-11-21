"""Falla si la marca o el dominio aparecen escritos a mano en el código. Lo corre la integración continua.

Uso: python herramientas/ci/verificar_marca.py

Regla (CLAUDE.md §5 del repositorio del vault): en código, la marca y el dominio salen siempre de una variable de
configuración, nunca de un literal. Revisa los archivos versionados de movil/ y servidor/ contra los términos de
herramientas/ci/terminos-de-marca.txt, sin distinguir mayúsculas.
"""
import io
import os
import subprocess
import sys

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
CARPETAS = ('movil/', 'servidor/')
TERMINOS = os.path.join(RAIZ, 'herramientas', 'ci', 'terminos-de-marca.txt')


def terminos():
    with io.open(TERMINOS, encoding='utf-8') as f:
        return [l.strip().lower() for l in f if l.strip() and not l.lstrip().startswith('#')]


def archivos_versionados():
    salida = subprocess.run(['git', 'ls-files', '-z', '--', *CARPETAS], cwd=RAIZ, capture_output=True, check=True)
    return [r for r in salida.stdout.decode('utf-8').split('\0') if r]


def main():
    buscar = terminos()
    hallazgos = []
    for rel in archivos_versionados():
        try:
            texto = io.open(os.path.join(RAIZ, rel), encoding='utf-8').read()
        except (UnicodeDecodeError, FileNotFoundError):
            continue  # binarios: imágenes, fuentes
        for n, linea in enumerate(texto.splitlines(), 1):
            baja = linea.lower()
            for t in buscar:
                if t in baja:
                    hallazgos.append('%s:%d: "%s"' % (rel, n, t))
    for h in hallazgos:
        print(h)
    print('%d apariciones de la marca o el dominio en el código' % len(hallazgos))
    sys.exit(1 if hallazgos else 0)


if __name__ == '__main__':
    main()
