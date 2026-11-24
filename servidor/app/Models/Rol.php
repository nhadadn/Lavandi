<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Un rol de la plataforma, del catálogo roles. La migración siembra los cuatro: usuario, lavanderia, repartidor y
 * administracion (openspec/changes/registro-e-inicio-de-sesion/design.md, "Tablas y migración").
 *
 * En la v1 una cuenta tiene un solo rol, y eso lo impone el código, no la tabla (DD-053).
 */
class Rol extends Modelo
{
    /** El rol de quien pide el servicio desde la app (Módulo A). */
    public const USUARIO = 'usuario';

    /** El rol del socio lavandería (Módulo B): la cuenta que administra una lavandería. */
    public const LAVANDERIA = 'lavanderia';

    /** El rol del socio repartidor (Módulo C): la cuenta de un repartidor, con su fila en repartidores. */
    public const REPARTIDOR = 'repartidor';

    /** El rol de quien entra al panel de administración (Módulo D). */
    public const ADMINISTRACION = 'administracion';

    protected $table = 'roles';

    protected $primaryKey = 'id_roles';

    protected $fillable = ['codigo', 'nombre'];

    /**
     * @return BelongsToMany<Usuario, $this>
     */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'rol_usuario', 'id_roles', 'id_usuarios');
    }
}
