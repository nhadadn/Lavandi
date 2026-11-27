<?php

namespace Tests\Feature\AdministracionEnLaApp;

use App\Models\EstadoPedido;
use App\Pedidos\NombresDelEstado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * Los nombres operativos del estado del pedido, ya fuera de Filament (ADR-0022, decisión 9; design.md, sección 3): la
 * clase de dominio App\Pedidos\NombresDelEstado nombra cada estado como lo nombraba App\Filament\EstadosDelPedido, por
 * su estado operativo (ADR-0021 y su adenda) y no por el paso que ve el usuario. La llaman el monitoreo del panel, sus
 * marcadores y la ficha del usuario, y la llamarán la ficha y el monitoreo de la API (lotes 3 y 4).
 *
 * Que el panel no cambió lo prueban, sin tocarlas, MonitoreoDePedidosTest, MapaDelMonitoreoTest y GestionDeUsuariosTest
 * (tests/Feature/Administracion/).
 *
 * Escenario: administracion-app-E11 · Spec: openspec/changes/administracion-en-la-app/specs/administracion-en-la-app/
 * spec.md · Tarea 1.7
 */
class NombresDelEstadoTest extends TestCase
{
    use RefreshDatabase;

    /** Los nombres que daba App\Filament\EstadosDelPedido, estado por estado (ADR-0021 y su adenda). */
    private const LOS_DEL_PANEL = [
        'por_pagar' => 'Por pagar',
        'confirmado' => 'Confirmado',
        'aceptado' => 'Aceptado',
        'rechazado' => 'Rechazado',
        'recolectado' => 'Recolectado',
        'en_proceso' => 'En proceso',
        'listo' => 'Listo',
        'en_entrega' => 'En proceso de entrega',
        'entregado' => 'Entregado',
    ];

    #[TestDox('administracion-app-E11: cada estado del catálogo se nombra como lo nombraba el panel, Aceptado y En proceso de entrega por su estado operativo y no por el paso que ve el usuario')]
    public function test_administracion_app_e11_cada_estado_se_nombra_como_lo_nombraba_el_panel(): void
    {
        $this->assertEquals(self::LOS_DEL_PANEL, NombresDelEstado::NOMBRES);

        // El catálogo tiene justo esos estados: si una migración siembra uno nuevo, esta lista se revisa.
        $this->assertEqualsCanonicalizing(array_keys(self::LOS_DEL_PANEL), EstadoPedido::query()->pluck('codigo')->all());

        foreach (self::LOS_DEL_PANEL as $codigo => $nombre) {
            $estado = EstadoPedido::query()->where('codigo', $codigo)->firstOrFail();

            $this->assertSame($nombre, NombresDelEstado::nombre($estado), $codigo);
        }
    }

    #[TestDox('administracion-app-E11: un estado sin nombre operativo propio se nombra como lo ve el usuario, como lo nombraba el panel')]
    public function test_administracion_app_e11_un_estado_sin_nombre_propio_se_nombra_como_lo_ve_el_usuario(): void
    {
        $estado = new EstadoPedido(['codigo' => 'sin_nombre_propio', 'nombre_usuario' => 'Como lo ve el usuario']);

        $this->assertSame('Como lo ve el usuario', NombresDelEstado::nombre($estado));
    }
}
