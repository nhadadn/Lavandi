<?php

namespace App\Lavanderias;

use App\Models\Lavanderia;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;

/**
 * Escribe los servicios con su precio y las ventanas del horario de una lavandería, ya validados
 * (App\Lavanderias\ReglasDeLaLavanderia). Lo usan el registro y la edición, de la app y del panel.
 *
 * - Servicios (lavanderia_servicios): una fila por tipo, con su precio y su unidad. Lo que se manda es la lista completa:
 *   un tipo que ya no viene queda inactivo, sin borrarse, y el perfil deja de mostrarlo.
 * - Ventanas (lavanderia_horarios): una fila por ventana de una hora, con su día y sus horas, que se leen en la zona
 *   horaria de la plaza. Lo que se manda es el horario completo de la semana y reemplaza al anterior
 *   (openspec/changes/alta-de-lavanderias/design.md, "Horario y ventanas en una sola tabla").
 */
final class ServiciosYVentanas
{
    /**
     * La unidad en que se cobra cada tipo de servicio que se puede registrar: "precios por kilo"
     * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5; alta-lavanderias-E05). El lavado en seco no está:
     * entra con la unidad que fije DD-010 (alta-lavanderias-E07, BR-16), y mientras tanto App\Lavanderias\
     * ServicioAdmitido no lo deja registrar.
     */
    public const UNIDAD_POR_TIPO = [
        TipoServicio::ESTANDAR => UnidadMedida::KILO,
        TipoServicio::PLANCHADO => UnidadMedida::KILO,
    ];

    /**
     * @param  list<array{tipo: string, precio: string|int|float}>  $servicios
     */
    public function guardarServicios(Lavanderia $lavanderia, array $servicios): void
    {
        $tipos = TipoServicio::query()->whereIn('codigo', array_column($servicios, 'tipo'))->pluck('id_tipos_servicio', 'codigo');
        $unidades = UnidadMedida::query()->whereIn('codigo', self::UNIDAD_POR_TIPO)->pluck('id_unidades_medida', 'codigo');

        foreach ($servicios as $servicio) {
            $lavanderia->servicios()->updateOrCreate(
                ['id_tipos_servicio' => $tipos[$servicio['tipo']]],
                [
                    'id_unidades_medida' => $unidades[self::UNIDAD_POR_TIPO[$servicio['tipo']]],
                    'precio' => (string) $servicio['precio'],
                    'activo' => true,
                ],
            );
        }

        $lavanderia->servicios()->whereNotIn('id_tipos_servicio', $tipos->values())->update(['activo' => false]);
    }

    /**
     * @param  list<array{dia_semana: int|string, hora_inicio: string, hora_fin: string}>  $ventanas
     */
    public function guardarVentanas(Lavanderia $lavanderia, array $ventanas): void
    {
        $lavanderia->horarios()->delete();

        foreach ($ventanas as $ventana) {
            $lavanderia->horarios()->create([
                'dia_semana' => (int) $ventana['dia_semana'],
                'hora_inicio' => $ventana['hora_inicio'],
                'hora_fin' => $ventana['hora_fin'],
            ]);
        }
    }
}
