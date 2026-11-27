<?php

namespace App\Plazas;

use RuntimeException;

/**
 * El archivo de una plaza no se puede leer: no existe o no es JSON. Lo que el archivo dice mal (la ficha, las zonas)
 * sale como ValidationException.
 */
final class ArchivoDePlazaNoValido extends RuntimeException {}
