<?php

namespace Tests\Feature\Indicadores;

use App\Filament\Pages\Indicadores;
use App\Filament\Widgets\Indicadores\EntregasPorRepartidorTabla;
use App\Filament\Widgets\Indicadores\RankingDeLavanderiasTabla;
use App\Indicadores\CortesDelSemaforo;
use App\Indicadores\EntregasPorRepartidor;
use App\Indicadores\Rango;
use App\Indicadores\Semaforo;
use App\Models\Calificacion;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Repartidor;
use App\Models\Rol;
use App\Models\Usuario;
use Database\Factories\RepartidorFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\AltaLavanderias\Soporte\PanelDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\Indicadores\Soporte\IndicadoresDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * Los indicadores de socios (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5, categoría Socios): la calificación promedio
 * de cada lavandería en un ranking con semáforo.
 *
 * El promedio es el de todas las calificaciones de la lavandería, las filas de calificaciones con su id_lavanderias,
 * con un decimal, como lo ve su perfil. Entran todas las lavanderías que no se borraron, de todas las plazas; las que no
 * tienen calificaciones van al final.
 *
 * Los dos cortes del semáforo son datos de configuración (config/indicadores.php). Sus valores los da EL CLIENTE
 * (indicadores-E14; DD-114): aquí la prueba carga los suyos, ficticios.
 *
 * Las entregas completadas por repartidor van en una tabla que los pone lado a lado (indicadores-E15; design.md,
 * lectura 5). Una entrega es del repartidor que marcó su paso a Entregado (openspec/changes/repartidor/design.md,
 * lectura 5), y cuenta en el rango por el día de ese paso, en la hora de la plaza del pedido.
 *
 * Las lavanderías activas frente a las registradas esperan DD-083 (indicadores-E11).
 *
 * Escenarios: indicadores-E12, indicadores-E13 e indicadores-E15 · Spec: openspec/changes/indicadores/specs/indicadores/
 * spec.md · Tareas 3.2, 3.3 y 3.5
 */
class IndicadoresDeSociosTest extends TestCase
{
    use AgendaDePrueba;
    use EstadosDePrueba;
    use IndicadoresDePrueba;
    use LavanderiasDePrueba;
    use PanelDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    /** La semana del lunes 30 de noviembre al domingo 6 de diciembre de 2026, por día. */
    private const SEMANA = ['desde' => '2026-11-30', 'hasta' => '2026-12-06', 'granularidad' => 'dia'];

    #[TestDox('indicadores-E12: con promedios de 4.8, 3.9 y 4.2, y una sin calificaciones, el ranking las pone en orden 4.8, 4.2 y 3.9, y la que no tiene al final')]
    public function test_indicadores_e12_el_ranking_ordena_por_promedio_y_deja_al_final_la_que_no_tiene(): void
    {
        $lavanderias = $this->sembrarElRanking();
        $this->comoAdministracion();

        $tabla = $this->widget(RankingDeLavanderiasTabla::class);

        $this->assertSame(
            [
                $lavanderias['alta']->id_publico,
                $lavanderias['media']->id_publico,
                $lavanderias['baja']->id_publico,
                $lavanderias['sin']->id_publico,
            ],
            $this->clavesDeLaTabla($tabla),
        );

        $tabla->assertTableColumnStateSet('promedio', 4.8, $lavanderias['alta']->id_publico)
            ->assertTableColumnStateSet('promedio', 4.2, $lavanderias['media']->id_publico)
            ->assertTableColumnStateSet('promedio', 3.9, $lavanderias['baja']->id_publico)
            ->assertTableColumnStateSet('promedio', null, $lavanderias['sin']->id_publico)
            ->assertTableColumnStateSet('lugar', 1, $lavanderias['alta']->id_publico)
            ->assertTableColumnStateSet('lugar', 3, $lavanderias['baja']->id_publico)
            ->assertTableColumnStateSet('lugar', null, $lavanderias['sin']->id_publico)
            ->assertSeeInOrder([
                'Lavandería de cuatro ocho', '4.8',
                'Lavandería de cuatro dos', '4.2',
                'Lavandería de tres nueve', '3.9',
                'Lavandería sin calificar', 'Sin calificaciones',
            ]);
    }

    #[TestDox('indicadores-E12: el ranking cuenta solo las calificaciones de cada lavandería y está en la página de indicadores')]
    public function test_indicadores_e12_el_ranking_cuenta_solo_las_calificaciones_de_cada_lavanderia(): void
    {
        $lavanderias = $this->sembrarElRanking();
        // Una calificación de 1 a otra lavandería no mueve los promedios de las demás.
        $otra = Lavanderia::factory()->create(['nombre' => 'Otra lavandería']);
        $this->calificar($otra, [1]);
        $this->comoAdministracion();

        $this->widget(RankingDeLavanderiasTabla::class)
            ->assertSee('Calificación promedio por lavandería')
            ->assertTableColumnStateSet('promedio', 4.8, $lavanderias['alta']->id_publico)
            ->assertTableColumnStateSet('promedio', 1.0, $otra->id_publico)
            ->assertTableColumnStateSet('lugar', 4, $otra->id_publico);

        $this->assertContains(RankingDeLavanderiasTabla::class, (new Indicadores)->getWidgets());
    }

    #[TestDox('indicadores-E13: con dos cortes cargados, la que está arriba del alto se marca verde, la de en medio amarillo y la de abajo del bajo rojo, cada una con su texto')]
    public function test_indicadores_e13_el_semaforo_marca_verde_amarillo_y_rojo_con_su_texto(): void
    {
        $this->cortes('4.5', '4.0');
        $lavanderias = $this->sembrarElRanking();
        $this->comoAdministracion();

        $this->widget(RankingDeLavanderiasTabla::class)
            ->assertTableColumnStateSet('semaforo', Semaforo::Verde, $lavanderias['alta']->id_publico)
            ->assertTableColumnStateSet('semaforo', Semaforo::Amarillo, $lavanderias['media']->id_publico)
            ->assertTableColumnStateSet('semaforo', Semaforo::Rojo, $lavanderias['baja']->id_publico)
            ->assertTableColumnStateSet('semaforo', null, $lavanderias['sin']->id_publico)
            // Cada color con su texto, no solo con el color.
            ->assertSeeInOrder([
                'Lavandería de cuatro ocho', 'Verde',
                'Lavandería de cuatro dos', 'Amarillo',
                'Lavandería de tres nueve', 'Rojo',
            ]);

        $this->assertSame(['Verde', 'Amarillo', 'Rojo'], array_map(fn (Semaforo $color): string => $color->getLabel(), Semaforo::cases()));
        $this->assertSame(['success', 'warning', 'danger'], array_map(fn (Semaforo $color): string => $color->getColor(), Semaforo::cases()));
    }

    #[TestDox('indicadores-E13: los cortes salen de la configuración: con otros cortes, la misma lavandería cambia de color sin cambiar el código')]
    public function test_indicadores_e13_los_cortes_salen_de_la_configuracion(): void
    {
        $lavanderias = $this->sembrarElRanking();
        $this->comoAdministracion();

        $this->cortes('4.9', '4.0');
        $this->widget(RankingDeLavanderiasTabla::class)
            ->assertTableColumnStateSet('semaforo', Semaforo::Amarillo, $lavanderias['alta']->id_publico);

        // Un promedio igual al corte alto alcanza el verde; uno igual al corte bajo, el amarillo.
        $this->cortes('4.8', '4.2');
        $this->widget(RankingDeLavanderiasTabla::class)
            ->assertTableColumnStateSet('semaforo', Semaforo::Verde, $lavanderias['alta']->id_publico)
            ->assertTableColumnStateSet('semaforo', Semaforo::Amarillo, $lavanderias['media']->id_publico)
            ->assertTableColumnStateSet('semaforo', Semaforo::Rojo, $lavanderias['baja']->id_publico);
    }

    #[TestDox('indicadores-E13: sin los dos cortes, o con cortes que no sirven, el ranking no pinta semáforo y lo dice')]
    public function test_indicadores_e13_sin_cortes_validos_no_hay_semaforo_y_lo_dice(): void
    {
        $lavanderias = $this->sembrarElRanking();
        $this->comoAdministracion();

        foreach ([[null, null], ['4.5', null], ['4.0', '4.5'], ['alto', 'bajo'], ['6', '4'], ['4', '0']] as [$alto, $bajo]) {
            $this->cortes($alto, $bajo);

            $this->widget(RankingDeLavanderiasTabla::class)
                ->assertTableColumnStateSet('semaforo', null, $lavanderias['alta']->id_publico)
                ->assertSee('Sin cortes configurados')
                ->assertDontSee('Verde');
        }

        $this->assertNull(CortesDelSemaforo::deLaConfiguracion());
    }

    #[TestDox('indicadores-E15: con dos repartidores, uno con cuatro entregas marcadas Entregado en el rango y otro con dos, la tabla los pone lado a lado con 4 y 2')]
    public function test_indicadores_e15_la_tabla_pone_lado_a_lado_las_entregas_de_cada_repartidor(): void
    {
        $this->prepararPedido();
        $cuatro = $this->repartidorLlamado('Repartidor de prueba con cuatro');
        $dos = $this->repartidorLlamado('Repartidor de prueba con dos');

        foreach (['2026-11-30', '2026-12-01', '2026-12-03', '2026-12-05'] as $dia) {
            $this->entregaDe($cuatro, $this->pedidoConfirmadoEl("{$dia} 08:00:00"), "{$dia} 12:00");
        }
        foreach (['2026-12-02', '2026-12-04'] as $dia) {
            $this->entregaDe($dos, $this->pedidoConfirmadoEl("{$dia} 08:00:00"), "{$dia} 12:00");
        }
        $this->comoAdministracion();

        $tabla = $this->widget(EntregasPorRepartidorTabla::class, self::SEMANA);

        $this->assertSame([$cuatro->id_publico, $dos->id_publico], $this->clavesDeLaTabla($tabla));
        $tabla->assertTableColumnStateSet('entregas', 4, $cuatro->id_publico)
            ->assertTableColumnStateSet('entregas', 2, $dos->id_publico)
            ->assertTableColumnStateSet('repartidor', 'Repartidor de prueba con cuatro', $cuatro->id_publico)
            ->assertTableColumnStateSet('plaza', $this->plaza->nombre, $dos->id_publico)
            ->assertSee('Entregas completadas por repartidor')
            ->assertSeeInOrder(['Repartidor de prueba con cuatro', '4', 'Repartidor de prueba con dos', '2']);

        $this->assertContains(EntregasPorRepartidorTabla::class, (new Indicadores)->getWidgets());
    }

    #[TestDox('indicadores-E15: solo cuenta las entregas marcadas Entregado en el rango, en el día de la plaza: ni las de fuera, ni el pedido que el repartidor sigue llevando')]
    public function test_indicadores_e15_solo_cuenta_las_entregas_del_rango_en_el_dia_de_la_plaza(): void
    {
        $this->prepararPedido();
        $repartidor = $this->repartidorLlamado('Repartidor de prueba');

        // Dentro: el domingo 6 a las 23:30 de la plaza, que en UTC ya es el lunes 7.
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl('2026-12-06 08:00:00'), '2026-12-06 23:30');
        // Fuera: el domingo 29 a las 23:30 de la plaza, aunque en UTC ya sea el lunes 30; y el lunes 7.
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl('2026-11-29 08:00:00'), '2026-11-29 23:30');
        $this->entregaDe($repartidor, $this->pedidoConfirmadoEl('2026-12-07 08:00:00'), '2026-12-07 12:00');
        // El que sigue llevando: aceptado por él y recolectado, sin Entregado.
        $enCurso = $this->pedidoConfirmadoEl('2026-12-03 08:00:00');
        $this->aceptarComoLaLavanderia($enCurso);
        $this->repartidorQueLoAcepta($enCurso, $repartidor);
        $this->marcarComoLaLavanderia($enCurso, EstadoPedido::RECOLECTADO);

        $filas = app(EntregasPorRepartidor::class)->delRango(Rango::deLosFiltros(self::SEMANA));

        $this->assertSame(1, $filas[$repartidor->id_publico]['entregas']);
    }

    #[TestDox('indicadores-E15: un repartidor sin entregas en el rango sale con 0, y uno dado de baja conserva las suyas')]
    public function test_indicadores_e15_sin_entregas_sale_con_cero_y_el_de_baja_conserva_las_suyas(): void
    {
        $this->prepararPedido();
        $sinEntregas = $this->repartidorLlamado('Repartidor de prueba sin entregas');
        $deBaja = $this->repartidorLlamado('Repartidor de prueba de baja');
        $this->entregaDe($deBaja, $this->pedidoConfirmadoEl('2026-12-01 08:00:00'), '2026-12-01 12:00');
        $deBaja->forceFill(['estado' => Repartidor::BAJA])->save();
        $deBaja->delete();
        // Uno de baja sin entregas en el rango ya no sale.
        $otroDeBaja = $this->repartidorLlamado('Repartidor de prueba de baja sin entregas');
        $otroDeBaja->forceFill(['estado' => Repartidor::BAJA])->save();
        $otroDeBaja->delete();
        $this->comoAdministracion();

        $tabla = $this->widget(EntregasPorRepartidorTabla::class, self::SEMANA);

        $this->assertSame([$deBaja->id_publico, $sinEntregas->id_publico], $this->clavesDeLaTabla($tabla));
        $tabla->assertTableColumnStateSet('entregas', 1, $deBaja->id_publico)
            ->assertTableColumnStateSet('entregas', 0, $sinEntregas->id_publico);
    }

    #[TestDox('indicadores-E15: una cuenta que no es de administración no ve la tabla de entregas por repartidor')]
    public function test_indicadores_e15_otra_cuenta_no_ve_las_entregas_por_repartidor(): void
    {
        foreach ([Rol::USUARIO, Rol::LAVANDERIA, Rol::REPARTIDOR] as $rol) {
            $this->actingAs(Usuario::factory()->conRol($rol)->create(), 'web');

            $this->assertFalse(EntregasPorRepartidorTabla::canView(), $rol);
            $this->widget(EntregasPorRepartidorTabla::class, self::SEMANA)->assertForbidden();
        }
    }

    private function cortes(?string $alto, ?string $bajo): void
    {
        config(['indicadores.semaforo.corte_alto' => $alto, 'indicadores.semaforo.corte_bajo' => $bajo]);
    }

    /**
     * Tres lavanderías con promedio de 4.8, 3.9 y 4.2, y una sin calificaciones, cada una en su plaza de prueba.
     *
     * @return array{alta: Lavanderia, baja: Lavanderia, media: Lavanderia, sin: Lavanderia}
     */
    private function sembrarElRanking(): array
    {
        $lavanderias = [
            'alta' => Lavanderia::factory()->create(['nombre' => 'Lavandería de cuatro ocho']),
            'baja' => Lavanderia::factory()->create(['nombre' => 'Lavandería de tres nueve']),
            'media' => Lavanderia::factory()->create(['nombre' => 'Lavandería de cuatro dos']),
            'sin' => Lavanderia::factory()->create(['nombre' => 'Lavandería sin calificar']),
        ];

        $this->calificar($lavanderias['alta'], [5, 5, 5, 5, 4]);
        $this->calificar($lavanderias['baja'], [4, 4, 4, 4, 4, 4, 4, 4, 4, 3]);
        $this->calificar($lavanderias['media'], [5, 4, 4, 4, 4]);

        return $lavanderias;
    }

    /**
     * Una calificación de una cuenta de usuario nueva por cada número de estrellas.
     *
     * @param  list<int>  $estrellas
     */
    private function calificar(Lavanderia $lavanderia, array $estrellas): void
    {
        foreach ($estrellas as $numero) {
            Calificacion::create([
                'id_usuarios' => Usuario::factory()->conRol(Rol::USUARIO)->create()->getKey(),
                'id_lavanderias' => $lavanderia->getKey(),
                'estrellas' => $numero,
            ]);
        }
    }

    /**
     * Un repartidor activo de la plaza de prueba, con ese nombre en su cuenta.
     */
    private function repartidorLlamado(string $nombre): Repartidor
    {
        return Repartidor::factory()
            ->en($this->plaza)
            ->deLaCuenta(RepartidorFactory::cuentaDeRepartidor(['nombre' => $nombre]))
            ->create();
    }
}
