"""Falla si el repositorio versiona un secreto. Lo corre la integración continua.

Uso: python herramientas/ci/verificar_secretos.py

Los secretos viven en las variables del hosting y en los secretos de EAS, nunca en el repositorio (ADR-0012).
Revisa los archivos versionados de dos formas:
- por nombre: .env (salvo .env.example), llaves .pem, .p8 y .p12, almacenes de firma de Android y los archivos
  de configuración de Firebase;
- por contenido: bloques de llave privada, el campo "private_key" de una cuenta de servicio y las llaves de Conekta.
Los patrones son una red mínima, no un escáner completo de secretos. Sus pruebas: test_verificar_secretos.py.

Las llaves de Conekta (DD-102, punto 3) se detectan antes de que existan en la máquina. Su forma es grado E, SIN
VERIFICAR (developers.conekta.com, "Authentication" y "Create Api Key", consultadas el 2026-11-22): las privadas y las
públicas, de prueba y de producción, empiezan con key_ seguido de letras y números (16 y 23 en los ejemplos de la
documentación), y lo que las distingue lo guarda Conekta, no la llave. Así que un solo patrón las cubre a las cuatro.
No cuenta un marcador de la documentación hecho de una sola letra repetida. La tarea 3.3 de
openspec/changes/pago/tasks.md lo confirma con las llaves del sandbox.
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
LLAVES_PRIVADAS = (
    re.compile(r'-----BEGIN (?:RSA |EC |OPENSSH |)PRIVATE KEY-----'),
    re.compile(r'"private_key"\s*:\s*"-----BEGIN'),
)
# key_ al principio de una palabra, seguido de 16 o más letras y números, y nada más de la palabra.
LLAVE_DE_CONEKTA = re.compile(r'\bkey_([A-Za-z0-9]{16,})\b')


def archivos_versionados():
    salida = subprocess.run(['git', 'ls-files', '-z'], cwd=RAIZ, capture_output=True, check=True)
    return [r for r in salida.stdout.decode('utf-8').split('\0') if r]


def es_llave_de_conekta(coincidencia):
    """Una de verdad no es una sola letra repetida, como los marcadores key_xxxx… de la documentación."""
    return len(set(coincidencia.group(1))) > 1


def hallazgos_en(texto):
    """Lo que el texto de un archivo versionado tiene de secreto, sin repetir: un hallazgo por clase de secreto."""
    hallazgos = []
    if any(patron.search(texto) for patron in LLAVES_PRIVADAS):
        hallazgos.append('contiene una llave privada')
    if any(es_llave_de_conekta(c) for c in LLAVE_DE_CONEKTA.finditer(texto)):
        hallazgos.append('contiene una llave de Conekta')
    return hallazgos


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
        hallazgos.extend('%s: %s' % (rel, h) for h in hallazgos_en(texto))
    for h in hallazgos:
        print(h)
    print('%d posibles secretos versionados' % len(hallazgos))
    sys.exit(1 if hallazgos else 0)


if __name__ == '__main__':
    main()
