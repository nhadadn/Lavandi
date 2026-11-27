<?php

namespace Tests\Feature\PerfilUsuario;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\PerfilUsuario\Soporte\PerfilDePrueba;
use Tests\TestCase;

/**
 * El perfil de la cuenta: leer y cambiar el nombre, sin pedir, guardar ni devolver jamás un número de tarjeta
 * (NSV-2026-001-v8.pdf:17:ANEXO A, Sección A.3, Módulo A #15, "Nombre" y "métodos de pago"; :20, Datos de tarjeta;
 * openspec/changes/perfil-y-soporte/).
 *
 * Cada cuenta ve y cambia solo su propio perfil: el de la sesión. La ruta no recibe a quién, así que no hay forma de
 * pedir el de otra.
 *
 * Escenarios: perfil-usuario-E01, perfil-usuario-E02 y perfil-usuario-E06 · Spec:
 * openspec/changes/perfil-y-soporte/specs/perfil-usuario/spec.md · Tarea 1.1 de
 * openspec/changes/perfil-y-soporte/tasks.md
 */
class PerfilTest extends TestCase
{
    use PerfilDePrueba;
    use RefreshDatabase;

    /** Un número de tarjeta de prueba de las pasarelas: pasa el algoritmo de Luhn y no es de nadie. */
    private const NUMERO_DE_TARJETA = '4242424242424242';

    private const NOMBRE_VACIO = 'Escribe tu nombre.';

    #[TestDox('perfil-usuario-E01: la cuenta cambia su nombre, la respuesta trae el nuevo y la siguiente consulta también')]
    public function test_perfil_usuario_e01_la_cuenta_cambia_su_nombre_y_la_siguiente_consulta_lo_devuelve(): void
    {
        $ana = $this->cuentaDeUsuario(['correo' => 'ana@ejemplo.com']);

        // El registro no pide el nombre (C-225): nace vacío.
        $this->comoCuenta($ana)->getJson('/api/perfil')
            ->assertOk()
            ->assertExactJson(['perfil' => [
                'id_publico' => $ana->id_publico,
                'nombre' => null,
                'correo' => 'ana@ejemplo.com',
                // Sin foto hasta que la sube (perfil-usuario-E03, FotoDePerfilTest).
                'foto' => null,
            ]]);

        $this->comoCuenta($ana)->patchJson('/api/perfil', ['nombre' => 'Ana Prueba'])
            ->assertOk()
            ->assertExactJson(['perfil' => [
                'id_publico' => $ana->id_publico,
                'nombre' => 'Ana Prueba',
                'correo' => 'ana@ejemplo.com',
                'foto' => null,
            ]]);

        $this->comoCuenta($ana)->getJson('/api/perfil')
            ->assertOk()
            ->assertJsonPath('perfil.nombre', 'Ana Prueba');
        $this->assertSame('Ana Prueba', $ana->fresh()->nombre);

        // Lo vuelve a cambiar, y se guarda sin los espacios de los lados.
        $this->comoCuenta($ana)->patchJson('/api/perfil', ['nombre' => '  Ana María Prueba  '])
            ->assertOk()
            ->assertJsonPath('perfil.nombre', 'Ana María Prueba');
        $this->comoCuenta($ana)->getJson('/api/perfil')->assertJsonPath('perfil.nombre', 'Ana María Prueba');
    }

    #[TestDox('perfil-usuario-E01: cada cuenta ve y cambia solo su propio perfil')]
    public function test_perfil_usuario_e01_cada_cuenta_ve_y_cambia_solo_su_propio_perfil(): void
    {
        $ana = $this->cuentaDeUsuario(['correo' => 'ana@ejemplo.com']);
        $beto = $this->cuentaDeUsuario(['correo' => 'beto@ejemplo.com']);
        $beto->forceFill(['nombre' => 'Beto Prueba'])->save();

        $respuesta = $this->comoCuenta($ana)->patchJson('/api/perfil', ['nombre' => 'Ana Prueba'])->assertOk();
        $respuesta->assertJsonPath('perfil.id_publico', $ana->id_publico);

        $this->assertSame('Beto Prueba', $beto->fresh()->nombre);

        $deBeto = $this->comoCuenta($beto)->getJson('/api/perfil')->assertOk();
        $deBeto->assertJsonPath('perfil.nombre', 'Beto Prueba');
        foreach (['Ana Prueba', 'ana@ejemplo.com', $ana->id_publico] as $deAna) {
            $this->assertStringNotContainsString($deAna, $deBeto->getContent());
        }
    }

    #[TestDox('perfil-usuario-E01: un nombre de más de 255 caracteres se rechaza y el anterior se conserva')]
    public function test_perfil_usuario_e01_un_nombre_demasiado_largo_se_rechaza(): void
    {
        $ana = $this->cuentaDeUsuario();
        $ana->forceFill(['nombre' => 'Ana Prueba'])->save();

        $this->comoCuenta($ana)->patchJson('/api/perfil', ['nombre' => str_repeat('a', 256)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre' => 'El nombre es demasiado largo.']);
        $this->assertSame('Ana Prueba', $ana->fresh()->nombre);

        $this->comoCuenta($ana)->patchJson('/api/perfil', ['nombre' => str_repeat('a', 255)])->assertOk();
    }

    #[TestDox('perfil-usuario-E02: un nombre vacío, solo con espacios, nulo o que no viene se rechaza, y el anterior se conserva')]
    public function test_perfil_usuario_e02_un_nombre_vacio_se_rechaza_y_el_anterior_se_conserva(): void
    {
        $ana = $this->cuentaDeUsuario();
        $ana->forceFill(['nombre' => 'Ana Prueba'])->save();

        foreach ([['nombre' => ''], ['nombre' => '   '], ['nombre' => null], []] as $cuerpo) {
            $this->comoCuenta($ana)->patchJson('/api/perfil', $cuerpo)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['nombre' => self::NOMBRE_VACIO]);

            $this->assertSame('Ana Prueba', $ana->fresh()->nombre, json_encode($cuerpo));
        }

        $this->comoCuenta($ana)->getJson('/api/perfil')->assertJsonPath('perfil.nombre', 'Ana Prueba');
    }

    #[TestDox('perfil-usuario-E02: un nombre que no es texto se rechaza, y el anterior se conserva')]
    public function test_perfil_usuario_e02_un_nombre_que_no_es_texto_se_rechaza(): void
    {
        $ana = $this->cuentaDeUsuario();
        $ana->forceFill(['nombre' => 'Ana Prueba'])->save();

        foreach ([123, ['Ana'], true] as $nombre) {
            $this->comoCuenta($ana)->patchJson('/api/perfil', ['nombre' => $nombre])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('nombre');
        }

        $this->assertSame('Ana Prueba', $ana->fresh()->nombre);
    }

    #[TestDox('perfil-usuario-E06: una petición al perfil con un número de tarjeta no lo guarda en ninguna tabla, y ninguna respuesta lo devuelve')]
    public function test_perfil_usuario_e06_el_perfil_no_guarda_ni_devuelve_un_numero_de_tarjeta(): void
    {
        $ana = $this->cuentaDeUsuario();

        $cambio = $this->comoCuenta($ana)->patchJson('/api/perfil', [
            'nombre' => 'Ana Prueba',
            'numero_tarjeta' => self::NUMERO_DE_TARJETA,
            'tarjeta' => ['numero' => self::NUMERO_DE_TARJETA, 'cvv' => '123', 'vencimiento' => '12/30'],
            'card_number' => self::NUMERO_DE_TARJETA,
            'metodos_pago' => [['numero' => self::NUMERO_DE_TARJETA]],
        ]);

        $cambio->assertOk();
        $cambio->assertJsonPath('perfil.nombre', 'Ana Prueba');
        $consulta = $this->comoCuenta($ana)->getJson('/api/perfil')->assertOk();

        foreach ([$cambio, $consulta] as $respuesta) {
            $this->assertStringNotContainsString(self::NUMERO_DE_TARJETA, $respuesta->getContent());
            // Solo los campos del perfil; la foto, de la tarea 1.2, es su URL firmada o null.
            $this->assertSame(['id_publico', 'nombre', 'correo', 'foto'], array_keys($respuesta->json('perfil')));
        }

        $this->assertSame([], $this->tablasQueGuardan(self::NUMERO_DE_TARJETA));
    }

    #[TestDox('perfil-usuario-E06: la prueba encuentra el número si alguna tabla lo guardara')]
    public function test_perfil_usuario_e06_la_busqueda_encuentra_el_numero_si_una_tabla_lo_guarda(): void
    {
        // La búsqueda no pasa en vacío: un número escrito en una fila sí aparece.
        $ana = $this->cuentaDeUsuario();
        $ana->forceFill(['telefono' => self::NUMERO_DE_TARJETA])->save();

        $this->assertSame(['usuarios'], $this->tablasQueGuardan(self::NUMERO_DE_TARJETA));
    }

    /**
     * Las tablas del esquema public con alguna fila que, leída como texto, contiene el valor.
     *
     * @return list<string>
     */
    private function tablasQueGuardan(string $valor): array
    {
        $tablas = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name');

        $this->assertContains('usuarios', $tablas);
        $this->assertContains('personal_access_tokens', $tablas);

        return $tablas
            ->filter(fn (string $tabla): bool => DB::scalar(
                'select exists (select 1 from "'.str_replace('"', '""', $tabla).'" fila where fila::text like ?)',
                ['%'.$valor.'%'],
            ))
            ->values()
            ->all();
    }
}
