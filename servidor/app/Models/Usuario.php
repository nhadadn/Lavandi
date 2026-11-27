<?php

namespace App\Models;

use App\Autorizacion\Autorizacion;
use App\Models\Concerns\TieneIdPublico;
use Database\Factories\UsuarioFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

/**
 * Una cuenta del sistema, de cualquier rol: la tabla usuarios, en lugar de la users de Laravel (ADR-0016; ADR-0005).
 *
 * Usa los traits de autenticación que arma Illuminate\Foundation\Auth\User, salvo MustVerifyEmail: la verificación
 * del correo está fuera del contrato (proposal.md, "Fuera del contrato"). Los tokens de la app son de Sanctum y la
 * cuenta se borra en suave. No lleva plaza: la persona no pertenece a una plaza, su domicilio sí. Con Notifiable, sus
 * avisos del panel viven en notifications, como el de archivo listo de una descarga (exportacion-E10).
 *
 * El correo se guarda en minúsculas y se busca sin distinguir mayúsculas, como lo exige el único sobre lower(correo).
 * La contraseña se guarda con el hash de Laravel y nunca sale en JSON (registro-E05 y registro-E06).
 */
class Usuario extends Modelo implements AuthenticatableContract, AuthorizableContract, CanResetPasswordContract, FilamentUser, HasName
{
    use Authenticatable;
    use Authorizable;
    use CanResetPassword;
    use HasApiTokens;

    /** @use HasFactory<UsuarioFactory> */
    use HasFactory;

    use Notifiable;
    use SoftDeletes;
    use TieneIdPublico;

    /** La cuenta entra y usa la app: el valor por omisión de usuarios.estado. */
    public const ACTIVO = 'activo';

    /** La administración bloqueó la cuenta desde el panel (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3). */
    public const BLOQUEADO = 'bloqueado';

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuarios';

    protected $fillable = ['correo', 'password'];

    /** La ruta de la foto tampoco sale: la foto se ve con su URL firmada (perfil-usuario-E03; ADR-0006). */
    protected $hidden = ['password', 'foto_ruta'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'bloqueado_en' => 'datetime',
        ];
    }

    /**
     * El correo como se guarda: sin espacios a los lados y en minúsculas. Quien busque una cuenta por el proveedor de
     * autenticación de Laravel, que compara la columna tal cual, pasa el correo por aquí.
     */
    public static function normalizarCorreo(string $correo): string
    {
        return Str::lower(trim($correo));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function correo(): Attribute
    {
        return Attribute::make(set: fn (string $correo): string => self::normalizarCorreo($correo));
    }

    /**
     * La cuenta con ese correo, sin distinguir mayúsculas. Usa el índice único sobre lower(correo).
     *
     * @param  Builder<Usuario>  $consulta
     */
    public function scopeConCorreo(Builder $consulta, string $correo): void
    {
        $consulta->whereRaw('lower(correo) = lower(?)', [trim($correo)]);
    }

    /**
     * El rol de la cuenta vive en rol_usuario (ADR-0016).
     *
     * @return BelongsToMany<Rol, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_usuario', 'id_usuarios', 'id_roles');
    }

    /**
     * @return HasMany<CuentaExterna, $this>
     */
    public function cuentasExternas(): HasMany
    {
        return $this->hasMany(CuentaExterna::class, 'id_usuarios');
    }

    /**
     * El correo al que llega la recuperación de la contraseña. La tabla no tiene email: tiene correo.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    /**
     * Quién entra al panel lo decide el componente de autorización, también en local (registro-E15).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return app(Autorizacion::class)->puedeEntrarAlPanel($this);
    }

    /**
     * El nombre con que el panel muestra la cuenta. La tabla no tiene name, y nombre está vacío hasta el perfil
     * (C-225): entonces va el correo.
     */
    public function getFilamentName(): string
    {
        return filled($this->nombre) ? (string) $this->nombre : $this->correo;
    }
}
