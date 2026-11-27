<?php

namespace Tests\Feature\Repartidor;

use App\Models\EstadoPedido;
use App\Models\Evidencia;
use App\Models\Pedido;
use App\Models\Repartidor;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Filesystem\Filesystem as Disco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Agenda\Soporte\AgendaDePrueba;
use Tests\Feature\Confirmacion\Soporte\PedidosDePrueba;
use Tests\Feature\Estados\Soporte\EstadosDePrueba;
use Tests\Feature\PedidosLavanderia\Soporte\PedidosDeLaLavanderiaDePrueba;
use Tests\Feature\PerfilLavanderia\Soporte\DiscoLocalDeFotos;
use Tests\Feature\PerfilLavanderia\Soporte\LavanderiasDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\Feature\Repartidor\Soporte\RepartidorDePrueba;
use Tests\TestCase;

/**
 * La foto de evidencia al marcar Entregado: POST /api/repartidor/pedidos/{id_publico}/estado con "entregado" y la foto,
 * en multipart. La foto queda en el almacenamiento privado de fotos, ligada al pedido en evidencias, y no tiene URL
 * pública (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Almacenamiento de imágenes, "las evidencias de entrega";
 * ADR-0006).
 *
 * Hoy es opcional y solo al entregar: si es obligatoria, si también se toma al recolectar y si es foto o firma espera
 * BR-29 y DD-110 (repartidor-E24). Usa el disco local privado de desarrollo, que solo sirve un archivo con URL firmada,
 * como el de producción.
 *
 * Escenario: repartidor-E23 · Spec: openspec/changes/repartidor/specs/repartidor/spec.md · Tarea 1.6
 */
class EvidenciaDeEntregaTest extends TestCase
{
    use AgendaDePrueba;
    use DiscoLocalDeFotos;
    use EstadosDePrueba;
    use LavanderiasDePrueba;
    use PedidosDeLaLavanderiaDePrueba;
    use PedidosDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;
    use RepartidorDePrueba;

    private Disco $disco;

    private Repartidor $repartidor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disco = $this->usarDiscoLocalDeFotos();
        $this->prepararPedido();
        $this->repartidor = $this->repartidorActivo();
    }

    #[TestDox('repartidor-E23: con el pedido En tránsito, el repartidor marca Entregado y toma una foto; la foto queda en el almacenamiento privado, ligada al pedido con quién la tomó y cuándo, y no tiene URL pública')]
    public function test_repartidor_e23_la_foto_de_evidencia_queda_privada_y_ligada_al_pedido(): void
    {
        $pedido = $this->pedidoEnTransito();
        $foto = $this->fotoDePrueba('evidencia.jpg');
        $this->travelTo(CarbonImmutable::parse(self::JUEVES_DE_PRUEBA.' 16:45', self::ZONA_HORARIA));

        $respuesta = $this->entregar($pedido, ['estado' => EstadoPedido::ENTREGADO, 'foto' => $foto])
            ->assertOk()
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::ENTREGADO)
            ->assertJsonPath('pedido.evidencias', [['tipo' => Evidencia::ENTREGA, 'tomada_en' => '2026-12-03T22:45:00Z']]);

        // Ligada al pedido, con quién la tomó y cuándo; en la base, solo la ruta.
        $evidencia = Evidencia::query()->sole();
        $this->assertTrue($pedido->is($evidencia->pedido));
        $this->assertSame($pedido->id_plazas, $evidencia->id_plazas);
        $this->assertSame(Evidencia::ENTREGA, $evidencia->tipo);
        $this->assertSame($this->repartidor->id_usuarios, $evidencia->id_usuarios);
        $this->assertSame('2026-12-03T22:45:00Z', $evidencia->tomada_en->utc()->toIso8601ZuluString());
        $this->assertStringStartsWith("evidencias/{$pedido->id_publico}/", $evidencia->ruta);

        // En el almacenamiento privado, tal como la mandó la app.
        $this->assertSame([$evidencia->ruta], $this->disco->allFiles());
        $this->assertSame($foto->getContent(), $this->disco->get($evidencia->ruta));

        // Sin URL pública: la respuesta no trae la ruta ni ninguna URL, y el archivo no se sirve sin firma.
        $this->assertStringNotContainsString($evidencia->ruta, $respuesta->getContent());
        $this->assertStringNotContainsString('archivos/fotos', $respuesta->getContent());
        $respuesta->assertJsonMissingPath('pedido.evidencias.0.url')->assertJsonMissingPath('pedido.evidencias.0.ruta');
        $this->get('/archivos/fotos/'.$evidencia->ruta)->assertForbidden();

        // El pedido trae su evidencia también después.
        $this->pedidoComoRepartidor($this->repartidor, $pedido->id_publico)
            ->assertJsonPath('pedido.evidencias.0.tipo', Evidencia::ENTREGA);
    }

    #[TestDox('repartidor-E23: la foto es opcional mientras BR-29 siga abierta: sin foto, el pedido también pasa a Entregado y no queda evidencia')]
    public function test_repartidor_e23_sin_foto_tambien_se_entrega(): void
    {
        $pedido = $this->pedidoEnTransito();

        $this->entregar($pedido, ['estado' => EstadoPedido::ENTREGADO])
            ->assertOk()
            ->assertJsonPath('pedido.estado.codigo', EstadoPedido::ENTREGADO)
            ->assertJsonPath('pedido.evidencias', []);

        $this->assertSame(0, Evidencia::count());
        $this->assertSame([], $this->disco->allFiles());
    }

    #[TestDox('repartidor-E23: la foto solo se toma al marcar Entregado; con Recolectado o En tránsito se rechaza con 422, sin guardar nada ni mover el pedido')]
    public function test_repartidor_e23_la_foto_solo_al_entregar(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->repartidorQueLoAcepta($pedido, $this->repartidor);
        $eventos = $this->eventosDe($pedido);

        $this->entregar($pedido, ['estado' => EstadoPedido::RECOLECTADO, 'foto' => $this->fotoDePrueba()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => 'La foto de evidencia se toma al marcar Entregado.']);

        $this->assertPedidoSinCambio($pedido, EstadoPedido::ACEPTADO, $eventos);
        $this->assertSame(0, Evidencia::count());
        $this->assertSame([], $this->disco->allFiles());
    }

    #[TestDox('repartidor-E23: un archivo que no es imagen, o más pesado que el máximo, se rechaza con 422; el pedido sigue En tránsito y no se guarda nada')]
    public function test_repartidor_e23_una_foto_que_no_vale_se_rechaza(): void
    {
        $pedido = $this->pedidoEnTransito();
        $eventos = $this->eventosDe($pedido);
        config(['fotos.peso_maximo_kb' => 100]);

        $this->entregar($pedido, ['estado' => EstadoPedido::ENTREGADO, 'foto' => UploadedFile::fake()->create('nota.pdf', 20, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => 'La foto debe ser una imagen: jpg, jpeg, png, webp.']);
        $this->entregar($pedido, ['estado' => EstadoPedido::ENTREGADO, 'foto' => $this->fotoDePrueba(kilobytes: 101)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['foto' => 'La foto pesa más de 100 KB.']);

        $this->assertPedidoSinCambio($pedido, EstadoPedido::EN_ENTREGA, $eventos);
        $this->assertSame(0, Evidencia::count());
        $this->assertSame([], $this->disco->allFiles());
    }

    #[TestDox('repartidor-E23: si Entregado no es el paso siguiente, el 409 no deja la foto en el almacenamiento ni la evidencia en la base')]
    public function test_repartidor_e23_si_no_se_entrega_no_queda_la_foto(): void
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->repartidorQueLoAcepta($pedido, $this->repartidor);
        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO] as $boton) {
            $this->marcarComoLaLavanderia($pedido, $boton);
        }

        $this->entregar($pedido, ['estado' => EstadoPedido::ENTREGADO, 'foto' => $this->fotoDePrueba()])
            ->assertConflict()
            ->assertJsonPath('message', 'Ese no es el paso siguiente del pedido.');

        $this->assertSame(EstadoPedido::LISTO, $pedido->fresh()->estado->codigo);
        $this->assertSame(0, Evidencia::count());
        $this->assertSame([], $this->disco->allFiles());
    }

    #[TestDox('repartidor-E23: otro repartidor no deja una evidencia en un pedido que no es suyo: 404 y nada se guarda')]
    public function test_repartidor_e23_otro_repartidor_no_deja_evidencia(): void
    {
        $pedido = $this->pedidoEnTransito();

        $this->comoRepartidor($this->repartidorActivo())
            ->postJson("/api/repartidor/pedidos/{$pedido->id_publico}/estado", ['estado' => EstadoPedido::ENTREGADO, 'foto' => $this->fotoDePrueba()])
            ->assertNotFound();

        $this->assertSame(EstadoPedido::EN_ENTREGA, $pedido->fresh()->estado->codigo);
        $this->assertSame(0, Evidencia::count());
        $this->assertSame([], $this->disco->allFiles());
    }

    /**
     * Un pedido que el repartidor aceptó, que la lavandería llevó hasta Listo y que él marcó En tránsito.
     */
    private function pedidoEnTransito(): Pedido
    {
        $pedido = $this->pedidoAceptadoDe($this->cuentaDeUsuario());
        $this->repartidorQueLoAcepta($pedido, $this->repartidor);

        foreach ([EstadoPedido::RECOLECTADO, EstadoPedido::EN_PROCESO, EstadoPedido::LISTO] as $boton) {
            $this->marcarComoLaLavanderia($pedido, $boton);
        }

        $this->comoRepartidor($this->repartidor)
            ->postJson("/api/repartidor/pedidos/{$pedido->id_publico}/estado", ['estado' => EstadoPedido::EN_ENTREGA])
            ->assertOk();

        return $pedido->fresh();
    }

    /**
     * La app marca el botón con la sesión del repartidor, en multipart cuando lleva la foto.
     *
     * @param  array<string, mixed>  $datos
     */
    private function entregar(Pedido $pedido, array $datos): TestResponse
    {
        return $this->comoRepartidor($this->repartidor)->postJson("/api/repartidor/pedidos/{$pedido->id_publico}/estado", $datos);
    }
}
