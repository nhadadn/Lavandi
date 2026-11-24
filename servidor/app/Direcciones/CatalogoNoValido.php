<?php

namespace App\Direcciones;

use RuntimeException;

/**
 * Un archivo del catálogo de Correos de México que no se puede cargar: no se puede leer, le falta una columna, una
 * fila no tiene la forma del archivo, o un nombre no coincide con config/territorio.php. La carga no deja nada de
 * ningún archivo (direcciones-E16).
 *
 * El mensaje es para quien corre el comando: dice qué archivo, qué columna o qué línea.
 */
final class CatalogoNoValido extends RuntimeException {}
