<?php

namespace App\Repartidores;

use App\Autorizacion\Autorizacion;
use App\Models\Plaza;
use App\Models\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Las plazas en que la administración da de alta a un repartidor pendiente: las activas, de las que la cuenta ve en el
 * panel (repartidor-E05; openspec/changes/repartidor/design.md, "Dar de alta", "con la plaza que elige la administración
 * entre las plazas activas"; Autorizacion::plazasDelPanel()). Una plaza que no está activa no da servicio al público
 * (openspec/changes/plazas-y-zonas/design.md, decisión 4).
 *
 * La regla vivía en el botón del panel (App\Filament\Resources\Repartidores\Actions\AccionesDeEstado). Aquí la usan el
 * panel, que ofrece estas plazas en el alta, y la API de la administración, que las da en GET /api/administracion/plazas
 * y revisa la que llega al alta (administracion-app-E20; ADR-0022, decisión 9).
 *
 * La API nombra la plaza por su nombre, que es único (plazas-E08), como el perfil del repartidor: la plaza no tiene
 * identificador público, y su llave interna no sale por la API (ADR-0016, regla 5).
 */
final class PlazasDelAlta
{
    public const SIN_PLAZA = 'Elige la plaza.';

    public const NO_ES_DEL_ALTA = 'Esa plaza no está entre las del alta.';

    public function __construct(private readonly Autorizacion $autorizacion) {}

    /**
     * @return Builder<Plaza>
     */
    public function de(Usuario $cuenta): Builder
    {
        return $this->autorizacion->plazasDelPanel($cuenta)->where('activa', true);
    }

    /**
     * La plaza del alta que eligió la cuenta, por su nombre: obligatoria, y una de las de de().
     *
     * @throws ValidationException En plaza, sin cambiar nada.
     */
    public function laElegida(Usuario $cuenta, mixed $nombre): Plaza
    {
        if ($nombre === null || (is_string($nombre) && trim($nombre) === '')) {
            throw ValidationException::withMessages(['plaza' => self::SIN_PLAZA]);
        }

        $plaza = is_string($nombre) ? $this->de($cuenta)->where('nombre', $nombre)->first() : null;

        return $plaza ?? throw ValidationException::withMessages(['plaza' => self::NO_ES_DEL_ALTA]);
    }
}
