<?php

namespace App\Models;

use App\Models\Concerns\HeredaLaPlazaDeLaLavanderia;

/**
 * Una franja del horario de una lavandería: un día de la semana, del 1, el lunes, al 7 (ISO 8601), con su hora de
 * inicio y de fin, que se leen en la zona horaria de la plaza. Puede haber varias por día. La base rechaza un día fuera
 * de rango o una franja que termina antes de empezar (perfil-lavanderia-E14 y E15).
 *
 * En este cambio el horario es de prueba y el perfil no lo muestra. El cupo entra con DD-099; los festivos, con DD-078.
 */
class LavanderiaHorario extends Modelo
{
    use HeredaLaPlazaDeLaLavanderia;

    protected $table = 'lavanderia_horarios';

    protected $primaryKey = 'id_lavanderia_horarios';

    protected $fillable = ['id_plazas', 'dia_semana', 'hora_inicio', 'hora_fin'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
        ];
    }
}
