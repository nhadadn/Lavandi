"""Falla si el repositorio versiona un secreto. Lo corre la integración continua.

Uso: python herramientas/ci/verificar_secretos.py

Los secretos viven en las variables del hosting y en los secretos de EAS, nunca en el repositorio (ADR-0012).
Revisa los archivos versionados de dos formas:
- por nombre: .env (salvo .env.example), llaves .pem, .p8 y .p12, almacenes de firma de Android y los archivos
  de configuración de Firebase;
- por contenido: bloques de llave privada y el campo "private_key" de una cuenta de servicio.
Los patrones son una red mínima, no un escáner completo de secretos.
"""
import fnmatch
import io
import os
import re
import subprocess
import sys

RAIZ = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
NOMBRES = ('.env', '.env.*', '*.pem', '*.p8', '*.p12', '*.jks', '*.keystore',
           'google-services.json', 'GoogleService-Info.plist')
PERMITIDOS = ('.env.example',)
CONTENIDO = (
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH |)PRIVATE KEY-----'),
    re.compile(r'"private_key"\s*:\s*"-----BEGIN'),
)


def archivos_versionados():
    salida = subprocess.run(['git', 'ls-files', '-z'], cwd=RAIZ, capture_output=True, check=True)
    return [r for r in salida.stdout.decode('utf-8').split('\0') if r]


def main():
    hallazgos = []
    for rel in archivos_versionados():
        nombre = os.path.basename(rel)
        if nombre not in PERMITIDOS and any(fnmatch.fnmatch(nombre, p) for p in NOMBRES):
            hallazgos.append('%s: archivo que no se versiona' % rel)
            continue
        try:
            texto = io.open(os.path.join(RAIZ, rel), encoding='utf-8').read()
        except (UnicodeDecodeError, FileNotFoundError):
            continue
        for patron in CONTENIDO:
            if patron.search(texto):
                hallazgos.append('%s: contiene una llave privada' % rel)
                break
    for h in hallazgos:
        print(h)
    print('%d posibles secretos versionados' % len(hallazgos))
    sys.exit(1 if hallazgos else 0)


if __name__ == '__main__':
    main()
