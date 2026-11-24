<?php

namespace Tests\Feature\Autorizacion\Soporte;

use App\Models\Testimonio;
use Filament\Resources\Resource;

/**
 * Un recurso del panel que no declara su regla en el componente: no sobrescribe canAccess() ni su consulta, así que
 * Filament lo deja ver a quien pase la puerta del panel. Solo existe para comprobar que la prueba de cobertura lo nombra
 * (autorizacion-E01); no se registra en el panel.
 */
class RecursoSinRegla extends Resource
{
    protected static ?string $model = Testimonio::class;
}
