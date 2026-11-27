<?php

namespace App\Validacion;

/**
 * La regla del rango de fechas de un reporte: las dos fechas, y la de fin no anterior a la de inicio, con su mensaje
 * (administracion-E20; exportacion-E06). Un rango de un solo día vale, y no hay tope de duración (exportacion-E04 y
 * E05).
 *
 * Las dos fechas van en AAAA-MM-DD, el único formato que lee el rango del reporte (App\Indicadores\Rango): una fecha que
 * "date" da por buena en otro formato, como "December 5, 2026", haría el reporte de otro rango sin avisar
 * (administracion-app-E60). El campo de fecha del panel ya la manda así.
 *
 * Es la regla de la conciliación y de la descarga de los reportes en el panel, y la que usarán también las rutas de la
 * API de la administración de las tareas 13.2 y 19.2 (ADR-0022, decisión 9;
 * openspec/changes/administracion-en-la-app/design.md, sección 3; administracion-app-E11; E38 y E60). Salió del
 * panel, donde vivía repetida en el formulario de la conciliación (App\Filament\Pages\Conciliacion) y en el de la
 * descarga (App\Exportacion\ExportadorDeIndicador); los dos la toman ahora de aquí, por App\Filament\CamposDelRango.
 *
 *     $request->validate(RangoDeFechas::reglas(), RangoDeFechas::mensajes());
 */
final class RangoDeFechas
{
    /** El campo de la fecha de inicio. */
    public const INICIO = 'desde';

    /** El campo de la fecha de fin. */
    public const FIN = 'hasta';

    public const MENSAJE_FIN_ANTERIOR = 'La fecha de fin no puede ser anterior a la de inicio.';

    /**
     * Las reglas de las dos fechas, por campo.
     *
     * La fecha de fin se compara con el campo de la de inicio por su nombre. Un formulario que valida sus campos por su
     * ruta completa, como los del panel, pasa la ruta del campo de inicio.
     *
     * @return array{desde: list<string>, hasta: list<string>}
     */
    public static function reglas(string $campoDeInicio = self::INICIO): array
    {
        return [
            self::INICIO => ['required', 'date', 'date_format:Y-m-d'],
            self::FIN => ['required', 'date', 'date_format:Y-m-d', "after_or_equal:{$campoDeInicio}"],
        ];
    }

    /**
     * Los mensajes propios de la regla, para pasarlos al validador.
     *
     * @return array<string, string>
     */
    public static function mensajes(): array
    {
        return [
            self::FIN.'.after_or_equal' => self::MENSAJE_FIN_ANTERIOR,
        ];
    }
}
