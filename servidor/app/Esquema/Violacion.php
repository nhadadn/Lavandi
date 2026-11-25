<?php

namespace App\Esquema;

use Stringable;

/**
 * Una regla del modelo de datos que una tabla, una columna, una llave foránea o un modelo de Eloquent no cumple.
 */
final readonly class Violacion implements Stringable
{
    /**
     * @param  string  $tabla  La tabla, tal como está en la base.
     * @param  string|null  $columna  La columna o las columnas, separadas por coma; null si la regla es de la tabla.
     * @param  Regla  $regla  La regla que no se cumple.
     * @param  string  $esperado  Lo que la regla pide: el nombre, el tipo o la declaración correcta.
     * @param  string|null  $llave  El nombre de la llave foránea, si la regla es de una llave.
     * @param  string|null  $modelo  La clase del modelo de Eloquent, si la regla es de un modelo.
     */
    public function __construct(
        public string $tabla,
        public ?string $columna,
        public Regla $regla,
        public string $esperado,
        public ?string $llave = null,
        public ?string $modelo = null,
    ) {}

    public function __toString(): string
    {
        $donde = $this->columna === null ? $this->tabla : "{$this->tabla}.{$this->columna}";

        $mensaje = "[{$this->regla->value}] {$donde}: se espera {$this->esperado}";

        if ($this->llave !== null) {
            $mensaje .= " (llave {$this->llave})";
        }

        if ($this->modelo !== null) {
            $mensaje .= " (modelo {$this->modelo})";
        }

        return $mensaje;
    }
}
