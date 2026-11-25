<?php

namespace Tests\Feature\Publicacion\Soporte;

/**
 * Lo que comparten las pruebas de publicacion: simular el .env de otro entorno. Pone las variables en el entorno del
 * proceso, como las pondría el hosting (ADR-0012), vuelve a leer el archivo de configuración con ellas y deja todo como
 * estaba al terminar.
 */
trait VariablesDelEntorno
{
    /**
     * Lee de nuevo config/<archivo>.php con esas variables en el entorno y lo deja como la configuración del servidor.
     *
     * @param  array<string, string>  $variables
     */
    protected function configuracionDelEntorno(string $archivo, array $variables): void
    {
        config([$archivo => $this->conVariables($variables, fn (): array => require config_path("{$archivo}.php"))]);
    }

    /**
     * Corre lo que se pasa con esas variables en el entorno del proceso, y luego las deja como estaban.
     *
     * @template T
     *
     * @param  array<string, string>  $variables
     * @param  callable(): T  $hacer
     * @return T
     */
    protected function conVariables(array $variables, callable $hacer): mixed
    {
        $antes = [];

        foreach ($variables as $nombre => $valor) {
            $antes[$nombre] = [$_SERVER[$nombre] ?? null, $_ENV[$nombre] ?? null, getenv($nombre)];
            $_SERVER[$nombre] = $_ENV[$nombre] = $valor;
            putenv("{$nombre}={$valor}");
        }

        try {
            return $hacer();
        } finally {
            foreach ($antes as $nombre => [$servidor, $variable, $proceso]) {
                if ($servidor === null) {
                    unset($_SERVER[$nombre]);
                } else {
                    $_SERVER[$nombre] = $servidor;
                }

                if ($variable === null) {
                    unset($_ENV[$nombre]);
                } else {
                    $_ENV[$nombre] = $variable;
                }

                putenv($proceso === false ? $nombre : "{$nombre}={$proceso}");
            }
        }
    }
}
