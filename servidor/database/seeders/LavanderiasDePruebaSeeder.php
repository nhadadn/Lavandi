<?php

namespace Database\Seeders;

use App\Fotos\AlmacenDeFotos;
use App\Models\Lavanderia;
use App\Models\LavanderiaServicio;
use App\Models\Rol;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
use App\Models\Usuario;
use App\Plazas\ResolucionDePunto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Las lavanderías de prueba del hito H2: ficticias, con nombres que lo dicen, cada una con su plaza, su ubicación, su
 * estado, una cuenta de prueba que la administra, sus servicios con precios de prueba y sus calificaciones de prueba
 * (openspec/changes/perfil-de-lavanderia/design.md, "Tablas y migración"; lavanderias-cercanas, tareas 1.1 y 1.3). "Aún
 * no hay precios" (C-131): ninguno de estos es un precio real.
 *
 * Cada punto cae dentro de una zona de las plazas de prueba, en el océano Pacífico, y la plaza sale de resolver ese
 * punto, como saldrá la de una lavandería real. Si las plazas de prueba no están sembradas, la lavandería no se crea.
 *
 * Todas llevan el mismo horario de prueba (HORARIO), para la agenda (agenda-de-recoleccion, tarea 1.1). Se siembra solo
 * si la lavandería todavía no tiene horario.
 *
 * El lavado en seco no se siembra: su unidad espera DD-010 (perfil-lavanderia-E06). Las calificaciones son todas de la
 * lavandería, sin pedido, cada una de una cuenta de prueba de usuario: dejan los promedios 4.8, 4.2 y 3.5 para probar el
 * filtro de calificación (cercanas-E17), y la lavandería de prueba 4 sin ninguna. Solo se siembran si la lavandería
 * todavía no tiene calificaciones.
 *
 * Cada una lleva tres fotos de prueba, en orden (perfil-lavanderia-E03, tarea 2.1): imágenes neutras, sin marca, hechas
 * en el momento con GD, de un gris liso con el nombre de la lavandería y su número. Van al almacenamiento de fotos del
 * entorno (config/fotos.php), como las reales; no se descarga ni se versiona ninguna imagen. Solo se siembran si la
 * lavandería todavía no tiene fotos: así no se pisa la que el socio haya subido desde la app.
 *
 * Las tres de Durango, en local, las administra una cuenta de socio lavandería que sí entra a la app, con su correo de
 * CUENTA y la contraseña CONTRASENA_DE_PRUEBA, para probar el Módulo B en un teléfono (pedidos-de-la-lavanderia y
 * estados-del-pedido). Fuera de local, como las demás, la administra una cuenta sin contraseña. No son secretos: son
 * cuentas ficticias que solo existen en la máquina de desarrollo.
 *
 * Solo se siembran en local y en prueba, nunca en producción (ADR-0012; cercanas-E22). Sembrar dos veces no las
 * duplica: la lavandería se reconoce por su plaza y su nombre, y la cuenta por su correo.
 */
class LavanderiasDePruebaSeeder extends Seeder
{
    /**
     * Las lavanderías de prueba. Las tres primeras quedan en línea recta a unos 2 y 5 km entre sí, en la zona de
     * prueba 1 Centro; la suspendida, a unos 2 km al norte de la primera; la última, en la plaza de prueba 2.
     */
    private const LAVANDERIAS = [
        [
            'nombre' => 'Lavandería de prueba 1',
            'latitud' => 10.5,
            'longitud' => -119.5,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '10.00', TipoServicio::PLANCHADO => '15.00'],
            'estrellas' => [5, 5, 5, 5, 4],
        ],
        [
            'nombre' => 'Lavandería de prueba 2',
            'latitud' => 10.5,
            'longitud' => -119.48,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '11.00'],
            'estrellas' => [5, 4, 4, 4, 4],
        ],
        [
            'nombre' => 'Lavandería de prueba 3',
            'latitud' => 10.5,
            'longitud' => -119.45,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '12.00', TipoServicio::PLANCHADO => '17.00'],
            'estrellas' => [4, 3],
        ],
        [
            'nombre' => 'Lavandería de prueba suspendida',
            'latitud' => 10.52,
            'longitud' => -119.5,
            'estado' => Lavanderia::SUSPENDIDA,
            'servicios' => [TipoServicio::ESTANDAR => '10.00'],
            'estrellas' => [5],
        ],
        [
            'nombre' => 'Lavandería de prueba 4',
            'latitud' => 10.5,
            'longitud' => -115.5,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '10.00'],
            'estrellas' => [],
        ],
        // Tres más en la zona de la plaza de prueba de Durango (plazas-E16), para probar la app desde un teléfono o
        // el emulador con la ubicación en la ciudad. Son tan ficticias como las del océano y no son el territorio
        // inicial (DD-093, DD-094).
        [
            'nombre' => 'Lavandería de prueba Durango Centro',
            'cuenta' => 'lavanderia.centro@ejemplo.com',
            'latitud' => 24.0277,
            'longitud' => -104.6532,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '10.00', TipoServicio::PLANCHADO => '15.00'],
            'estrellas' => [5, 5, 5, 4],
        ],
        [
            'nombre' => 'Lavandería de prueba Durango Norte',
            'cuenta' => 'lavanderia.norte@ejemplo.com',
            'latitud' => 24.0450,
            'longitud' => -104.6600,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '11.00'],
            'estrellas' => [4, 4, 3],
        ],
        [
            'nombre' => 'Lavandería de prueba Durango Sur',
            'cuenta' => 'lavanderia.sur@ejemplo.com',
            'latitud' => 24.0100,
            'longitud' => -104.6400,
            'estado' => Lavanderia::ACTIVA,
            'servicios' => [TipoServicio::ESTANDAR => '12.00', TipoServicio::PLANCHADO => '16.00'],
            'estrellas' => [],
        ],
    ];

    /**
     * El horario de prueba, ficticio, en la hora de la plaza: de lunes a viernes de 9:00 a 14:00 y de 16:00 a 19:30, el
     * sábado de 9:00 a 13:30 y el domingo sin servicio. Deja ver en la app las ventanas de una hora, la última ventana
     * completa de una franja que no cierra en punto (agenda-E03) y el día sin servicio (agenda-E04). No es el horario de
     * ninguna lavandería real: ese lo edita cada una en F-B06 (alta-de-lavanderias).
     *
     * @var list<array{int, string, string}> Día (1, el lunes, a 7, ISO 8601), hora de inicio y hora de fin.
     */
    private const HORARIO = [
        [1, '09:00', '14:00'], [1, '16:00', '19:30'],
        [2, '09:00', '14:00'], [2, '16:00', '19:30'],
        [3, '09:00', '14:00'], [3, '16:00', '19:30'],
        [4, '09:00', '14:00'], [4, '16:00', '19:30'],
        [5, '09:00', '14:00'], [5, '16:00', '19:30'],
        [6, '09:00', '13:30'],
    ];

    /**
     * Los colores de las fotos de prueba, uno por foto y en su orden: grises lisos, sin la paleta de ninguna marca
     * (CLAUDE.md §5).
     *
     * @var list<array{int, int, int}>
     */
    private const COLORES_DE_LAS_FOTOS = [[0xD9, 0xDC, 0xDF], [0xC4, 0xC8, 0xCC], [0xAE, 0xB3, 0xB8]];

    /** El ancho y el alto de las fotos de prueba, en píxeles. */
    private const TAMANO_DE_LAS_FOTOS = [800, 600];

    /** El dominio reservado para pruebas (RFC 2606): ningún correo de estas cuentas llega a nadie. */
    private const DOMINIO_DE_PRUEBA = 'prueba.test';

    /**
     * La contraseña de prueba de las cuentas de las lavanderías de Durango, solo en local. Cumple el mínimo de
     * config/acceso.php.
     */
    public const CONTRASENA_DE_PRUEBA = 'lavanderia-de-prueba';

    public function run(ResolucionDePunto $resolucion, AlmacenDeFotos $almacen): void
    {
        // Aunque alguien fuerce el sembrado, y aunque ya haya plazas que cubran los puntos (cercanas-E22).
        if (app()->isProduction()) {
            $this->command?->warn('Las lavanderías de prueba no se siembran en producción (ADR-0012).');

            return;
        }

        DB::transaction(function () use ($resolucion, $almacen): void {
            foreach (self::LAVANDERIAS as $numero => $datos) {
                $punto = $resolucion->resolver($datos['latitud'], $datos['longitud']);

                if ($punto === null) {
                    $this->command?->warn("{$datos['nombre']}: su punto no cae en ninguna plaza activa; no se siembra.");

                    continue;
                }

                $lavanderia = Lavanderia::withTrashed()->updateOrCreate(
                    ['id_plazas' => $punto->plaza->getKey(), 'nombre' => $datos['nombre']],
                    [
                        'id_usuarios' => $this->administradorDe($datos, $numero)->getKey(),
                        'descripcion' => 'Lavandería ficticia para probar la app. No es una lavandería real.',
                        'calle' => 'Calle de prueba',
                        'numero' => (string) ($numero + 1),
                        'colonia' => 'Colonia de prueba',
                        'codigo_postal' => '00000',
                        'ubicacion' => ['latitud' => $datos['latitud'], 'longitud' => $datos['longitud']],
                        'estado' => $datos['estado'],
                        'aprobada_en' => now(),
                    ],
                );

                if ($lavanderia->trashed()) {
                    $lavanderia->restore();
                }

                $this->sembrarServicios($lavanderia, $datos['servicios']);
                $this->sembrarHorario($lavanderia);
                $this->sembrarCalificaciones($lavanderia, $datos['estrellas']);
                $this->sembrarFotos($lavanderia, $almacen);
            }
        });
    }

    /**
     * @param  array<string, string>  $servicios  Código del tipo y precio de prueba, por kilo.
     */
    private function sembrarServicios(Lavanderia $lavanderia, array $servicios): void
    {
        $kilo = UnidadMedida::where('codigo', UnidadMedida::KILO)->sole();

        foreach ($servicios as $tipo => $precio) {
            LavanderiaServicio::updateOrCreate(
                [
                    'id_lavanderias' => $lavanderia->getKey(),
                    'id_tipos_servicio' => TipoServicio::where('codigo', $tipo)->sole()->getKey(),
                ],
                ['id_unidades_medida' => $kilo->getKey(), 'precio' => $precio, 'activo' => true],
            );
        }
    }

    private function sembrarHorario(Lavanderia $lavanderia): void
    {
        if ($lavanderia->horarios()->exists()) {
            return;
        }

        foreach (self::HORARIO as [$dia, $inicio, $fin]) {
            $lavanderia->horarios()->create(['dia_semana' => $dia, 'hora_inicio' => $inicio, 'hora_fin' => $fin]);
        }
    }

    /**
     * @param  list<int>  $estrellas  Una calificación por número, cada una de una cuenta de prueba distinta.
     */
    private function sembrarCalificaciones(Lavanderia $lavanderia, array $estrellas): void
    {
        if ($lavanderia->calificaciones()->exists()) {
            return;
        }

        foreach ($estrellas as $numero => $cantidad) {
            $lavanderia->calificaciones()->create([
                'id_usuarios' => $this->cuenta('calificador-de-prueba-'.($numero + 1), Rol::USUARIO)->getKey(),
                'estrellas' => $cantidad,
            ]);
        }
    }

    /**
     * Las tres fotos de prueba, con orden 1, 2 y 3, en la carpeta de la lavandería en el almacenamiento del entorno.
     */
    private function sembrarFotos(Lavanderia $lavanderia, AlmacenDeFotos $almacen): void
    {
        if ($lavanderia->fotos()->exists()) {
            return;
        }

        foreach (self::COLORES_DE_LAS_FOTOS as $indice => $color) {
            $orden = $indice + 1;
            $ruta = "lavanderias/{$lavanderia->id_publico}/prueba-{$orden}.png";

            $almacen->guardarContenido($ruta, $this->fotoDePrueba("{$lavanderia->nombre} - foto {$orden}", $color));
            $lavanderia->fotos()->create(['ruta' => $ruta, 'orden' => $orden]);
        }
    }

    /**
     * Una imagen PNG de un color liso con el texto al centro. La fuente de GD solo tiene letras sin acento: el texto va
     * sin ellos.
     *
     * @param  array{int, int, int}  $color
     */
    private function fotoDePrueba(string $texto, array $color): string
    {
        [$ancho, $alto] = self::TAMANO_DE_LAS_FOTOS;
        $fuente = 5;
        $texto = Str::ascii($texto);

        $imagen = imagecreatetruecolor($ancho, $alto);
        imagefill($imagen, 0, 0, (int) imagecolorallocate($imagen, ...$color));
        imagestring(
            $imagen,
            $fuente,
            max(0, intdiv($ancho - imagefontwidth($fuente) * strlen($texto), 2)),
            intdiv($alto - imagefontheight($fuente), 2),
            $texto,
            (int) imagecolorallocate($imagen, 0x33, 0x33, 0x33),
        );

        ob_start();
        imagepng($imagen);

        return (string) ob_get_clean();
    }

    /**
     * La cuenta de socio lavandería que administra la lavandería de prueba: en local, la que entra a la app, si tiene
     * una en CUENTA; si no, una sin contraseña del dominio reservado para pruebas.
     *
     * @param  array<string, mixed>  $datos
     */
    private function administradorDe(array $datos, int $numero): Usuario
    {
        if (! isset($datos['cuenta']) || ! app()->environment('local')) {
            return $this->cuenta('lavanderia-de-prueba-'.($numero + 1), Rol::LAVANDERIA);
        }

        $cuenta = Usuario::withTrashed()->firstOrCreate(['correo' => $datos['cuenta']]);
        // Siempre la de prueba: así el correo y la contraseña que se documentan sirven aunque alguien la haya cambiado.
        $cuenta->forceFill(['password' => self::CONTRASENA_DE_PRUEBA, 'deleted_at' => null])->save();
        $cuenta->roles()->syncWithoutDetaching([Rol::where('codigo', Rol::LAVANDERIA)->sole()->getKey()]);

        return $cuenta;
    }

    /**
     * Una cuenta de prueba con su rol, sin contraseña: no sirve para entrar, solo para ser dueña de los datos.
     */
    private function cuenta(string $nombre, string $rol): Usuario
    {
        $cuenta = Usuario::withTrashed()->firstOrCreate(['correo' => "{$nombre}@".self::DOMINIO_DE_PRUEBA]);
        $cuenta->roles()->syncWithoutDetaching([Rol::where('codigo', $rol)->sole()->getKey()]);

        return $cuenta;
    }
}
