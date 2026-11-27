<?php

namespace App\Filament;

use App\Validacion\RangoDeFechas;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;

/**
 * Los dos campos de fecha de un rango en un formulario del panel, con la regla de dominio App\Validacion\RangoDeFechas:
 * la conciliación (App\Filament\Pages\Conciliacion) y la descarga de los reportes
 * (App\Exportacion\ExportadorDeIndicador) validan con la misma clase que usarán también las rutas de la API de la
 * administración de las tareas 13.2 y 19.2 (ADR-0022, decisión 9; openspec/changes/administracion-en-la-app/design.md,
 * sección 3; administracion-app-E11; E38 y E60). Esas rutas no pasarán por aquí, que es de Filament: validarán con
 * RangoDeFechas::reglas() y mensajes().
 *
 * Cómo se aplica la regla a un campo de Filament, sin cambiarla:
 * - "required" va con required(), que además marca el campo como obligatorio en el formulario;
 * - "date" ya la pone el DatePicker por su cuenta;
 * - las demás van como reglas del campo. Filament valida cada campo por su ruta completa en el formulario
 *   ("mountedActions.0.data.hasta"), así que la fecha de fin se compara con la ruta del campo de inicio, como lo hace
 *   afterOrEqual();
 * - los mensajes de la regla van como mensajes del campo, que Filament pone bajo su ruta.
 */
final class CamposDelRango
{
    /**
     * Las reglas que el campo de Filament ya pone por su cuenta: required() y el DatePicker. Si cambian las reglas de
     * RangoDeFechas::reglas(), esta lista se revisa en el mismo cambio.
     */
    private const DEL_CAMPO = ['required', 'date'];

    public static function desde(): DatePicker
    {
        return self::campo(RangoDeFechas::INICIO)->label('Desde');
    }

    public static function hasta(): DatePicker
    {
        return self::campo(RangoDeFechas::FIN)->label('Hasta');
    }

    private static function campo(string $nombre): DatePicker
    {
        $mensajes = [];

        foreach (RangoDeFechas::mensajes() as $clave => $mensaje) {
            if (str_starts_with($clave, "{$nombre}.")) {
                $mensajes[substr($clave, strlen($nombre) + 1)] = $mensaje;
            }
        }

        return DatePicker::make($nombre)
            ->required(in_array('required', RangoDeFechas::reglas()[$nombre], true))
            ->rules(static fn (Field $component): array => array_values(array_diff(
                RangoDeFechas::reglas($component->resolveRelativeStatePath(RangoDeFechas::INICIO))[$nombre],
                self::DEL_CAMPO,
            )))
            ->validationMessages($mensajes);
    }
}
