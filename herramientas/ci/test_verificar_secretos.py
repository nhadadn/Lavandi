"""Pruebas de verificar_secretos.py: que detecte las llaves de Conekta antes de que existan en la máquina (DD-102,
punto 3; openspec/changes/pago/design.md, "Open Questions").

Uso: python -m unittest discover -s herramientas/ci -p "test_*.py"

La forma de las llaves es grado E, SIN VERIFICAR (developers.conekta.com, "Authentication" y "Create Api Key",
consultadas el 2026-11-22): las privadas y las públicas, de prueba y de producción, empiezan igual, con key_, y lo que
las distingue lo guarda Conekta (role y livemode), no la llave. Los ejemplos de la documentación traen 16 y 23 letras y
números después de key_. La tarea 3.3 lo confirma con las llaves del sandbox.

Ninguna llave de prueba está escrita en este archivo: se arman al correr, para que el verificador no encuentre una aquí.
"""
import random
import string
import unittest

import verificar_secretos

PREFIJO = 'key' + '_'
CARACTERES = string.ascii_letters + string.digits


def llave_de_mentira(largo, semilla):
    """Una llave con la forma de las de Conekta, armada al correr y que no sirve para nada."""
    azar = random.Random(semilla)
    return PREFIJO + ''.join(azar.choice(CARACTERES) for _ in range(largo))


class LlavesDeConekta(unittest.TestCase):

    def test_detecta_las_cuatro_llaves_de_conekta(self):
        """Privada y pública, de prueba y de producción: comparten la forma, así que las cuatro se detectan."""
        llaves = {
            'privada de prueba': llave_de_mentira(16, 1),
            'pública de prueba': llave_de_mentira(16, 2),
            'privada de producción': llave_de_mentira(23, 3),
            'pública de producción': llave_de_mentira(23, 4),
        }
        for cual, llave in llaves.items():
            with self.subTest(cual):
                self.assertEqual(['contiene una llave de Conekta'], verificar_secretos.hallazgos_en(llave))

    def test_detecta_la_llave_donde_suele_quedar_escrita(self):
        llave = llave_de_mentira(23, 5)
        textos = [
            'PASARELA_CONEKTA_LLAVE=%s\n' % llave,
            "'llave' => '%s',\n" % llave,
            '-H "Authorization: Bearer %s"\n' % llave,
            'curl -u %s: https://api.conekta.io/orders\n' % llave,
            '{"authentication_token": "%s"}\n' % llave,
        ]
        for texto in textos:
            with self.subTest(texto):
                self.assertEqual(['contiene una llave de Conekta'], verificar_secretos.hallazgos_en(texto))

    def test_no_confunde_nombres_ni_ejemplos_con_llaves(self):
        textos = [
            # Nombres de variables, de llaves de arreglos y de columnas.
            'PASARELA_CONEKTA_LLAVE=\n',
            "config('pasarela.conekta.llave')\n",
            'api_key_de_la_pasarela_conekta\n',
            'key_privada_de_conekta\n',
            # El prefijo que muestra la API, sin la llave.
            '"prefix": "%srp"\n' % PREFIJO,
            # Una llave demasiado corta para ser una de Conekta.
            llave_de_mentira(15, 6),
            # Los marcadores de la documentación, todos con la misma letra.
            PREFIJO + 'x' * 24,
            # Identificadores de Conekta que no son llaves.
            '"id": "ord_2iUhePPDGgdmsptBF"\n',
        ]
        for texto in textos:
            with self.subTest(texto):
                self.assertEqual([], verificar_secretos.hallazgos_en(texto))

    def test_sigue_detectando_las_llaves_privadas(self):
        self.assertEqual(['contiene una llave privada'],
                         verificar_secretos.hallazgos_en('-----BEGIN ' + 'PRIVATE KEY-----\n'))

    def test_una_llave_privada_y_una_de_conekta_en_el_mismo_archivo(self):
        texto = '-----BEGIN ' + 'PRIVATE KEY-----\n' + llave_de_mentira(20, 7)
        self.assertEqual(['contiene una llave privada', 'contiene una llave de Conekta'],
                         verificar_secretos.hallazgos_en(texto))


if __name__ == '__main__':
    unittest.main()
