<?php

namespace App\Agenda;

use App\Models\ParametroPlaza;
use App\Models\Plaza;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

/**
 * La configuración de la agenda de una plaza: la anticipación mínima y los días hacia adelante, dos claves de
 * parametros_plaza con su vigencia (agenda-E12; openspec/changes/agenda-de-recoleccion/design.md, decisión 3;
 * ADR-0016, alternativa C). Abrir una plaza o cambiar estos valores es cargar filas: ni código ni migraciones.
 *
 * Ningún valor está escrito en el código: el de producción lo fija EL CLIENTE (DD-099, el resto de BR-17). Si a la
 * plaza le falta una de las dos claves vigentes, la agenda no ofrece nada (AgendaSinConfigurar).
 *
 *     agenda_anticipacion_minima   {"minutos": 120}   entero de 0 en adelante
 *     agenda_dias_hacia_adelante   {"dias": 3}        entero de 0 en adelante; 0 es solo hoy
 *
 * Las dos se validan al guardarse (ParametroPlaza) y otra vez al leerse, por si una fila entró sin el modelo.
 */
final readonly class ParametrosDeAgenda
{
    public const ANTICIPACION_MINIMA = 'agenda_anticipacion_minima';

    public const DIAS_HACIA_ADELANTE = 'agenda_dias_hacia_adelante';

    public function __construct(
        public int $anticipacionMinutos,
        public int $diasHaciaAdelante,
    ) {}

    /**
     * La configuración vigente de la plaza en ese instante. Si dos vigencias de la misma clave se encimaran, rige la
     * que empezó después: impedir que se encimen espera su propio escenario (plazas-E15).
     *
     * @throws AgendaSinConfigurar
     */
    public static function deLaPlaza(Plaza $plaza, CarbonImmutable $ahora): self
    {
        $ahora = $ahora->utc();

        $vigentes = $plaza->parametros()
            ->whereIn('clave', [self::ANTICIPACION_MINIMA, self::DIAS_HACIA_ADELANTE])
            ->where('vigente_desde', '<=', $ahora)
            ->where(fn (Builder $vigencia) => $vigencia
                ->whereNull('vigente_hasta')
                ->orWhere('vigente_hasta', '>', $ahora))
            ->orderByDesc('vigente_desde')
            ->get()
            ->unique('clave')
            ->keyBy('clave');

        $anticipacion = $vigentes->get(self::ANTICIPACION_MINIMA);
        $dias = $vigentes->get(self::DIAS_HACIA_ADELANTE);

        if (! $anticipacion instanceof ParametroPlaza || ! $dias instanceof ParametroPlaza
            || ! self::valorValido($anticipacion) || ! self::valorValido($dias)) {
            throw new AgendaSinConfigurar("La plaza {$plaza->getKey()} no tiene vigentes las dos claves de su agenda.");
        }

        return new self($anticipacion->valor['minutos'], $dias->valor['dias']);
    }

    /**
     * Las reglas del valor de una clave de la agenda, o null si la clave no es de la agenda.
     *
     * @return array{reglas: array<string, list<string>>, mensajes: array<string, string>}|null
     */
    public static function validacion(string $clave): ?array
    {
        return match ($clave) {
            self::ANTICIPACION_MINIMA => self::entero('minutos', 'La anticipación mínima va en minutos: un número entero de 0 en adelante.'),
            self::DIAS_HACIA_ADELANTE => self::entero('dias', 'Los días hacia adelante van como un número entero de 0 en adelante.'),
            default => null,
        };
    }

    private static function valorValido(ParametroPlaza $parametro): bool
    {
        $validacion = self::validacion($parametro->clave);

        return $validacion !== null
            && Validator::make(['valor' => $parametro->valor], $validacion['reglas'])->passes();
    }

    /**
     * Un objeto con una sola propiedad, un entero de cero en adelante; un texto con un número no cuenta.
     *
     * @return array{reglas: array<string, list<string>>, mensajes: array<string, string>}
     */
    private static function entero(string $propiedad, string $mensaje): array
    {
        $forma = "El valor va como {\"{$propiedad}\": número entero}, sin otras propiedades.";

        return [
            'reglas' => [
                'valor' => ['required', "array:{$propiedad}"],
                "valor.{$propiedad}" => ['bail', 'required', 'integer:strict', 'min:0'],
            ],
            'mensajes' => [
                'valor.required' => $forma,
                'valor.array' => $forma,
                "valor.{$propiedad}.required" => $mensaje,
                "valor.{$propiedad}.integer" => $mensaje,
                "valor.{$propiedad}.min" => $mensaje,
            ],
        ];
    }
}
