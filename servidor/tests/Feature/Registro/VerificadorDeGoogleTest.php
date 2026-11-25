<?php

namespace Tests\Feature\Registro;

use App\Acceso\Google\IdTokenDeGoogleNoValido;
use App\Acceso\Google\VerificadorDeGoogle;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\Registro\Soporte\ImitaAGoogle;
use Tests\TestCase;

/**
 * El verificador del idToken de Google, aislado de las cuentas: valida la firma contra las llaves públicas de Google,
 * guardadas en caché, y comprueba el emisor, el cliente (aud), el vencimiento y el correo, que Google debe dar por
 * verificado (ADR-0005; ADR-0012, los clientes OAuth en configuración; C-228, DD-128 cerrada).
 *
 * Las pruebas no salen a la red: la Google de prueba firma con sus propias llaves RSA (Soporte/ImitaAGoogle).
 *
 * Escenarios: registro-E16 y registro-E17 · Spec: openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class VerificadorDeGoogleTest extends TestCase
{
    use ImitaAGoogle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->imitarAGoogle();
    }

    #[TestDox('registro-E16: un idToken válido del cliente configurado, con el correo verificado, da el sujeto y el correo')]
    public function test_registro_e16_un_id_token_valido_del_cliente_configurado_con_el_correo_verificado_da_el_sujeto_y_el_correo(): void
    {
        $identidad = $this->verificador()->verificar($this->idTokenDeGoogle(['email_verified' => true]));

        $this->assertSame(self::SUJETO_DE_PRUEBA, $identidad->sujeto);
        $this->assertSame('persona@ejemplo.com', $identidad->correo);

        // En otras respuestas de Google el reclamo llega como texto; también vale.
        $comoTexto = $this->verificador()->verificar($this->idTokenDeGoogle(['email_verified' => 'true']));

        $this->assertSame(self::SUJETO_DE_PRUEBA, $comoTexto->sujeto);
    }

    #[TestDox('registro-E16: acepta cualquiera de los clientes configurados y el emisor con o sin https')]
    public function test_registro_e16_acepta_cualquiera_de_los_clientes_configurados_y_el_emisor_con_o_sin_https(): void
    {
        $identidad = $this->verificador()->verificar($this->idTokenDeGoogle([
            'aud' => self::OTRO_CLIENTE_CONFIGURADO,
            'iss' => 'accounts.google.com',
        ]));

        $this->assertSame(self::SUJETO_DE_PRUEBA, $identidad->sujeto);
    }

    #[TestDox('registro-E17: un idToken con un correo que Google no da por verificado se rechaza')]
    public function test_registro_e17_un_id_token_con_un_correo_que_google_no_da_por_verificado_se_rechaza(): void
    {
        $this->assertRechazado($this->idTokenDeGoogle(['email_verified' => false]), 'no da el correo por verificado');
        $this->assertRechazado($this->idTokenDeGoogle(['email_verified' => null]), 'no da el correo por verificado');
        $this->assertRechazado($this->idTokenDeGoogle(['email_verified' => 'false']), 'no da el correo por verificado');
    }

    #[TestDox('registro-E16: las llaves de Google se piden una vez y se guardan en caché el plazo que dice Google')]
    public function test_registro_e16_las_llaves_de_google_se_piden_una_vez_y_se_guardan_en_cache_el_plazo_que_dice_google(): void
    {
        $this->verificador()->verificar($this->idTokenDeGoogle());
        $this->verificador()->verificar($this->idTokenDeGoogle(['sub' => 'otro-sujeto']));

        Http::assertSentCount(1);

        // Google las publica con max-age=21600, seis horas. Pasado ese plazo se piden otra vez.
        $this->travel(6)->hours();
        $this->travel(1)->minutes();
        $this->verificador()->verificar($this->idTokenDeGoogle());

        Http::assertSentCount(2);
    }

    #[TestDox('registro-E17: un idToken vencido se rechaza')]
    public function test_registro_e17_un_id_token_vencido_se_rechaza(): void
    {
        $vencido = $this->idTokenDeGoogle([
            'iat' => now()->subHours(2)->getTimestamp(),
            'exp' => now()->subHour()->getTimestamp(),
        ]);

        $this->assertRechazado($vencido, 'Expired token');
    }

    #[TestDox('registro-E17: un idToken con firma no válida se rechaza')]
    public function test_registro_e17_un_id_token_con_firma_no_valida_se_rechaza(): void
    {
        // Firmado con una llave que Google no publica, aunque diga ser la llave de Google.
        $this->assertRechazado($this->idTokenDeGoogle(firmante: 'impostor'), 'Signature verification failed');

        // Firmado por Google, pero con la carga cambiada después.
        [$cabecera, , $firma] = explode('.', $this->idTokenDeGoogle());
        [, $cargaAjena] = explode('.', $this->idTokenDeGoogle(['sub' => 'sujeto-ajeno']));

        $this->assertRechazado("{$cabecera}.{$cargaAjena}.{$firma}", 'Signature verification failed');
    }

    #[TestDox('registro-E17: un idToken emitido para otro cliente se rechaza')]
    public function test_registro_e17_un_id_token_emitido_para_otro_cliente_se_rechaza(): void
    {
        $otroCliente = $this->idTokenDeGoogle(['aud' => self::CLIENTE_AJENO, 'azp' => self::CLIENTE_AJENO]);

        $this->assertRechazado($otroCliente, 'otro cliente');
        $this->assertRechazado($this->idTokenDeGoogle(['aud' => null]), 'otro cliente');
    }

    #[TestDox('registro-E17: un idToken de otro emisor se rechaza')]
    public function test_registro_e17_un_id_token_de_otro_emisor_se_rechaza(): void
    {
        $this->assertRechazado($this->idTokenDeGoogle(['iss' => 'https://emisor-ajeno.prueba.test']), 'no lo emitió Google');
        $this->assertRechazado($this->idTokenDeGoogle(['iss' => null]), 'no lo emitió Google');
    }

    #[TestDox('registro-E17: un token sin firma se rechaza')]
    public function test_registro_e17_un_token_sin_firma_se_rechaza(): void
    {
        $this->assertRechazado($this->idTokenSinFirma(), 'Algorithm not supported');
    }

    #[TestDox('registro-E17: un texto que no es un idToken se rechaza')]
    public function test_registro_e17_un_texto_que_no_es_un_id_token_se_rechaza(): void
    {
        $this->assertRechazado('', 'no es un JWT');
        $this->assertRechazado('esto-no-es-un-token', 'no es un JWT');
        $this->assertRechazado('a.b.c', 'no es un JWT');
        Http::assertNothingSent();
    }

    #[TestDox('registro-E17: un idToken sin sujeto o sin correo se rechaza')]
    public function test_registro_e17_un_id_token_sin_sujeto_o_sin_correo_se_rechaza(): void
    {
        $this->assertRechazado($this->idTokenDeGoogle(['sub' => null]), 'no trae el sujeto o el correo');
        $this->assertRechazado($this->idTokenDeGoogle(['email' => null]), 'no trae el sujeto o el correo');
    }

    #[TestDox('registro-E17: sin clientes configurados, ningún idToken pasa')]
    public function test_registro_e17_sin_clientes_configurados_ningun_id_token_pasa(): void
    {
        config(['services.google.clientes' => []]);

        $this->assertRechazado($this->idTokenDeGoogle(), 'no hay clientes');
    }

    #[TestDox('registro-E17: una llave que Google no publica se busca una vez más y, si no aparece, se rechaza')]
    public function test_registro_e17_una_llave_que_google_no_publica_se_busca_una_vez_mas_y_si_no_aparece_se_rechaza(): void
    {
        $this->verificador()->verificar($this->idTokenDeGoogle());
        Http::assertSentCount(1);

        // Google cambia sus llaves cada cierto tiempo: una llave desconocida obliga a pedirlas otra vez, una sola.
        $this->assertRechazado($this->idTokenDeGoogle(cabecera: ['kid' => 'llave-desconocida']), '"kid" invalid');

        Http::assertSentCount(2);

        // Otra llave desconocida dentro del mismo minuto no vuelve a pedirlas: nadie satura a Google desde aquí.
        $this->assertRechazado($this->idTokenDeGoogle(cabecera: ['kid' => 'otra-llave-desconocida']), '"kid" invalid');

        Http::assertSentCount(2);
    }

    #[TestDox('registro-E16: si pedir las llaves por una llave desconocida falla, las llaves que había siguen sirviendo')]
    public function test_registro_e16_si_pedir_las_llaves_por_una_llave_desconocida_falla_las_llaves_que_habia_siguen_sirviendo(): void
    {
        $this->verificador()->verificar($this->idTokenDeGoogle());
        Http::assertSentCount(1);

        // Llega una llave desconocida justo cuando Google no responde: se piden las llaves y la petición falla.
        $this->googleNoEntregaSusLlaves();
        $this->assertRechazado($this->idTokenDeGoogle(cabecera: ['kid' => 'llave-desconocida']), 'no es válido');
        Http::assertSentCount(2);

        // Un idToken válido, firmado con la llave que ya estaba en la caché, sigue entrando sin pedir nada a Google.
        $identidad = $this->verificador()->verificar($this->idTokenDeGoogle(['sub' => 'otro-sujeto']));

        $this->assertSame('otro-sujeto', $identidad->sujeto);
        Http::assertSentCount(2);
    }

    #[TestDox('registro-E17: si no se pueden obtener las llaves de Google, el idToken se rechaza')]
    public function test_registro_e17_si_no_se_pueden_obtener_las_llaves_de_google_el_id_token_se_rechaza(): void
    {
        $this->googleNoEntregaSusLlaves();

        $this->assertRechazado($this->idTokenDeGoogle(), 'respondieron 500');
    }

    private function verificador(): VerificadorDeGoogle
    {
        return $this->app->make(VerificadorDeGoogle::class);
    }

    /**
     * El verificador rechaza el idToken, y por el motivo esperado: el mensaje lo dice, con el de la librería de JWT
     * cuando es ella la que lo rechaza.
     */
    private function assertRechazado(string $idToken, string $motivo): void
    {
        try {
            $identidad = $this->verificador()->verificar($idToken);
        } catch (IdTokenDeGoogleNoValido $rechazo) {
            $this->assertStringContainsString($motivo, $rechazo->getMessage());

            return;
        }

        $this->fail("El idToken debió rechazarse, y dio el sujeto {$identidad->sujeto}.");
    }
}
