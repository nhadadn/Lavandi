<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * El identificador público de una tabla que expone la API: id_publico, un ULID que Eloquent asigna al crear la fila
 * (ADR-0016, regla 5; openspec/changes/modelo-de-datos/design.md, decisión 8).
 *
 * La llave interna sigue siendo el bigint id_<tabla>. Las rutas reciben el ULID y nunca el bigint, así que nadie
 * adivina la fila de otro cambiando un número (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3). Un valor que no es un
 * ULID no llega a la base: la ruta responde 404.
 *
 * Lo usan los modelos de usuarios, direcciones, lavanderias, repartidores, pedidos y pagos. La tabla lleva
 * $table->ulid('id_publico')->unique().
 */
trait TieneIdPublico
{
    use HasUlids;

    /**
     * Las columnas que reciben un ULID al crear la fila: solo el identificador público, no la llave primaria.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['id_publico'];
    }

    public function getRouteKeyName(): string
    {
        return 'id_publico';
    }
}
