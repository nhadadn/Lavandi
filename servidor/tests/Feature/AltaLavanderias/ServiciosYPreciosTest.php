<?php

namespace Tests\Feature\AltaLavanderias;

use App\Models\LavanderiaServicio;
use App\Models\TipoServicio;
use App\Models\UnidadMedida;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * Los servicios del registro: cuáles de los tres tipos del contrato ofrece la lavandería, cada uno con su precio por
 * kilo, y el rechazo de cualquier otro tipo (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5 y #6; :17:Módulo
 * B #1). Un tipo fuera de los tres, como el calzado, es Orden de Cambio (DD-046).
 *
 * El lavado en seco es uno de los tres, pero su unidad espera DD-010 (alta-lavanderias-E07, bloqueado): mientras tanto
 * el servidor no lo registra, en lugar de suponerle un precio por kilo.
 *
 * Escenarios: alta-lavanderias-E05 y alta-lavanderias-E06 · Tarea 1.2 · Spec:
 * openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class ServiciosYPreciosTest extends TestCase
{
    use AltaDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
    }

    #[TestDox('alta-lavanderias-E05: el lavado estándar y el lavado con planchado se guardan cada uno con su precio por kilo')]
    public function test_alta_lavanderias_e05_estandar_y_planchado_se_guardan_con_su_precio_por_kilo(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $this->registrar($cuenta, $this->registroCompleto(['servicios' => [
            ['tipo' => 'estandar', 'precio' => '18.50'],
            ['tipo' => 'planchado', 'precio' => 25],
        ]]))->assertCreated()->assertJsonPath('lavanderia.servicios.*.precio', ['18.50', '25.00']);

        $lavanderia = $this->lavanderiaDe($cuenta);
        $this->assertSame(
            [
                [TipoServicio::ESTANDAR, '18.50', UnidadMedida::KILO],
                [TipoServicio::PLANCHADO, '25.00', UnidadMedida::KILO],
            ],
            $lavanderia->servicios()->with(['tipo', 'unidad'])->orderBy('id_tipos_servicio')->get()
                ->map(fn (LavanderiaServicio $servicio): array => [$servicio->tipo->codigo, $servicio->precio, $servicio->unidad->codigo])
                ->all(),
        );
    }

    #[TestDox('alta-lavanderias-E06: un servicio fuera de los tres, como el lavado de calzado, se rechaza nombrándolo, y no se guarda nada')]
    public function test_alta_lavanderias_e06_un_servicio_fuera_de_los_tres_se_rechaza_nombrandolo(): void
    {
        $cuenta = $this->cuentaDeLavanderia();

        $respuesta = $this->registrar($cuenta, $this->registroCompleto(['servicios' => [
            ['tipo' => 'estandar', 'precio' => '18.50'],
            ['tipo' => 'calzado', 'precio' => '90.00'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['servicios.1.tipo']);

        $mensaje = $respuesta->json('errors')['servicios.1.tipo'][0];
        $this->assertStringContainsString('calzado', $mensaje);
        // Dice cuáles sí se admiten: los tres del contrato.
        foreach (['Lavado estándar', 'Lavado con planchado', 'Lavado en seco'] as $nombre) {
            $this->assertStringContainsString($nombre, $mensaje);
        }

        $this->assertSinLavanderias();
    }

    #[TestDox('alta-lavanderias-E06: tampoco entra un tipo del catálogo que no está activo')]
    public function test_alta_lavanderias_e06_tampoco_entra_un_tipo_del_catalogo_que_no_esta_activo(): void
    {
        TipoServicio::query()->where('codigo', TipoServicio::PLANCHADO)->update(['activo' => false]);

        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto(['servicios' => [
            ['tipo' => 'planchado', 'precio' => '25.00'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['servicios.0.tipo']);

        $this->assertSinLavanderias();
    }

    #[TestDox('alta-lavanderias-E06 y E07: el lavado en seco todavía no se registra, porque su unidad espera DD-010')]
    public function test_alta_lavanderias_e06_y_e07_el_lavado_en_seco_todavia_no_se_registra(): void
    {
        $respuesta = $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto(['servicios' => [
            ['tipo' => 'seco', 'precio' => '30.00'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['servicios.0.tipo']);

        $this->assertStringContainsString('Lavado en seco', $respuesta->json('errors')['servicios.0.tipo'][0]);
        $this->assertSinLavanderias();
    }

    #[TestDox('alta-lavanderias-E05: el mismo servicio no va dos veces')]
    public function test_alta_lavanderias_e05_el_mismo_servicio_no_va_dos_veces(): void
    {
        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto(['servicios' => [
            ['tipo' => 'estandar', 'precio' => '18.50'],
            ['tipo' => 'estandar', 'precio' => '20.00'],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['servicios.1.tipo']);

        $this->assertSinLavanderias();
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function preciosNoValidos(): array
    {
        return [
            'negativo' => ['-5.00'],
            'con tres decimales' => ['18.505'],
            'que no es número' => ['dieciocho'],
            'vacío' => [''],
            'que no cabe en numeric(12,2)' => ['12345678901.00'],
        ];
    }

    #[DataProvider('preciosNoValidos')]
    #[TestDox('alta-lavanderias-E05: un precio $precio se rechaza en su campo')]
    public function test_alta_lavanderias_e05_un_precio_no_valido_se_rechaza(mixed $precio): void
    {
        $this->registrar($this->cuentaDeLavanderia(), $this->registroCompleto(['servicios' => [
            ['tipo' => 'estandar', 'precio' => $precio],
        ]]))->assertUnprocessable()->assertJsonValidationErrors(['servicios.0.precio']);

        $this->assertSinLavanderias();
    }

    private function assertSinLavanderias(): void
    {
        foreach (['lavanderias', 'lavanderia_servicios', 'lavanderia_horarios', 'lavanderia_fotos'] as $tabla) {
            $this->assertSame(0, DB::table($tabla)->count(), "{$tabla} tiene filas.");
        }

        $this->assertSame([], $this->archivosDeFotos());
    }
}
