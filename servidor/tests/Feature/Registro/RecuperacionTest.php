<?php

namespace Tests\Feature\Registro;

use App\Models\Rol;
use App\Models\Usuario;
use App\Notifications\EnlaceDeRecuperacion;
use Database\Factories\UsuarioFactory;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\TestCase;

/**
 * La recuperación de la contraseña por correo: el enlace que vence y abre una página web del servidor, la misma
 * respuesta exista o no la cuenta, la contraseña nueva que cierra las sesiones y el límite de peticiones
 * (NSV-2026-001-v8.pdf:30:ANEXO B, Sección B.5; ADR-0018).
 *
 * Cada petición de una prueba olvida la cuenta de la anterior (forgetGuards), como dos peticiones reales al servidor.
 *
 * Escenarios: registro-E22, registro-E23, registro-E24, registro-E25 y registro-E31 · Spec:
 * openspec/changes/registro-e-inicio-de-sesion/specs/registro/spec.md
 */
class RecuperacionTest extends TestCase
{
    use RefreshDatabase;

    private const NOMBRE_DE_PRUEBA = 'Producto de prueba';

    private const CORREO = 'persona@ejemplo.com';

    private const CORREO_SIN_CUENTA = 'nadie@ejemplo.com';

    private const TOKEN_DE_PRUEBA = 'token-de-prueba';

    private const CONTRASENA_NUEVA = 'contrasena-nueva-de-prueba';

    private const ENLACE_NO_VALIDO = 'El enlace ya no sirve';

    private const DEMASIADOS_INTENTOS = 'Demasiados intentos. Espera un minuto y vuelve a intentarlo.';

    // registro-E22 — Pide el enlace

    #[TestDox('registro-E22: quien tiene cuenta pide el enlace y le llega un correo con el enlace a la página')]
    public function test_registro_e22_quien_tiene_cuenta_pide_el_enlace_y_le_llega_un_correo_con_el_enlace_a_la_pagina(): void
    {
        Notification::fake();
        $usuario = $this->cuenta();

        $this->pedirEnlace(self::CORREO)
            ->assertOk()
            ->assertJsonStructure(['message']);

        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 1);
        Notification::assertSentOnDemand(
            EnlaceDeRecuperacion::class,
            function (EnlaceDeRecuperacion $aviso, array $canales, AnonymousNotifiable $destino) use ($usuario): bool {
                return $canales === ['mail']
                    && $destino->routes['mail'] === self::CORREO
                    && $aviso->correo === self::CORREO
                    // El token del correo es el que guardó el broker para esta cuenta.
                    && $this->app['auth.password']->broker()->tokenExists($usuario, $aviso->token);
            },
        );
    }

    #[TestDox('registro-E22: el correo se reconoce sin distinguir mayúsculas')]
    public function test_registro_e22_el_correo_se_reconoce_sin_distinguir_mayusculas(): void
    {
        Notification::fake();
        $this->cuenta();

        $this->pedirEnlace('Persona@Ejemplo.COM')->assertOk();

        Notification::assertSentOnDemand(
            EnlaceDeRecuperacion::class,
            fn (EnlaceDeRecuperacion $aviso, array $canales, AnonymousNotifiable $destino): bool => $destino->routes['mail'] === self::CORREO,
        );
    }

    #[TestDox('registro-E22: el correo lleva el nombre del producto de la configuración, el enlace y el plazo configurado')]
    public function test_registro_e22_el_correo_lleva_el_nombre_del_producto_de_la_configuracion_el_enlace_y_el_plazo_configurado(): void
    {
        config(['app.name' => self::NOMBRE_DE_PRUEBA, 'auth.passwords.users.expire' => 45]);

        $mensaje = (new EnlaceDeRecuperacion(self::TOKEN_DE_PRUEBA, self::CORREO))
            ->toMail(new AnonymousNotifiable);

        $enlace = route('contrasena.formulario', ['token' => self::TOKEN_DE_PRUEBA, 'correo' => self::CORREO]);

        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $mensaje->subject);

        $html = (string) $mensaje->render();
        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $html);
        $this->assertStringContainsString(e($enlace), $html);
        $this->assertStringContainsString('45 minutos', $html);

        $texto = view($mensaje->view['text'], $mensaje->data())->render();
        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $texto);
        $this->assertStringContainsString($enlace, $texto);
        $this->assertStringContainsString('45 minutos', $texto);
    }

    #[TestDox('registro-E22: el correo sale por el transporte configurado, que en local lo captura sin enviarlo')]
    public function test_registro_e22_el_correo_sale_por_el_transporte_configurado_que_en_local_lo_captura_sin_enviarlo(): void
    {
        config(['app.name' => self::NOMBRE_DE_PRUEBA]);
        $this->cuenta();

        $this->pedirEnlace(self::CORREO)->assertOk();

        // Las pruebas usan el transporte array, que guarda el correo en memoria y no lo envía.
        $capturados = $this->app['mailer']->getSymfonyTransport()->messages();
        $this->assertCount(1, $capturados);

        $correo = $capturados[0]->getOriginalMessage();
        $this->assertSame(self::CORREO, $correo->getTo()[0]->getAddress());
        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $correo->getSubject());
        $this->assertStringContainsString(url('/contrasena/restablecer').'/', $correo->getHtmlBody());
        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $correo->getTextBody());

        // En local, el ejemplo del entorno manda el correo al registro (log), sin enviarlo (ADR-0014).
        $this->assertSame('log', $this->variableDelEjemplo('MAIL_MAILER'));
    }

    #[TestDox('registro-E22: el enlace vence a los 60 minutos, o a los que diga el entorno')]
    public function test_registro_e22_el_enlace_vence_a_los_60_minutos_o_a_los_que_diga_el_entorno(): void
    {
        $this->assertSame(60, $this->configuracionDeAuthConEntorno([])['passwords']['users']['expire']);
        $this->assertSame(
            30,
            $this->configuracionDeAuthConEntorno(['RECUPERACION_VENCE_MINUTOS' => '30'])['passwords']['users']['expire'],
        );
    }

    // registro-E23 — Un correo sin cuenta no recibe nada

    #[TestDox('registro-E23: un correo sin cuenta no recibe nada y la respuesta es la misma que con cuenta')]
    public function test_registro_e23_un_correo_sin_cuenta_no_recibe_nada_y_la_respuesta_es_la_misma_que_con_cuenta(): void
    {
        Notification::fake();
        $this->cuenta();

        $sinCuenta = $this->pedirEnlace(self::CORREO_SIN_CUENTA);

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());

        $conCuenta = $this->pedirEnlace(self::CORREO);

        $this->assertSame(200, $sinCuenta->status());
        $this->assertSame($conCuenta->status(), $sinCuenta->status());
        $this->assertSame($conCuenta->json(), $sinCuenta->json());
        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 1);
    }

    #[TestDox('registro-E23: la respuesta es la misma aunque el freno propio del broker detenga un segundo enlace')]
    public function test_registro_e23_la_respuesta_es_la_misma_aunque_el_freno_propio_del_broker_detenga_un_segundo_enlace(): void
    {
        // Si alguien vuelve a prender el freno del broker, la cuenta que existe no debe responder distinto.
        config(['auth.passwords.users.throttle' => 60]);
        Notification::fake();
        $this->cuenta();

        $primera = $this->pedirEnlace(self::CORREO);
        $frenada = $this->pedirEnlace(self::CORREO);
        $sinCuenta = $this->pedirEnlace(self::CORREO_SIN_CUENTA);

        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 1);

        foreach ([$frenada, $sinCuenta] as $respuesta) {
            $this->assertSame($primera->status(), $respuesta->status());
            $this->assertSame($primera->json(), $respuesta->json());
        }
    }

    #[TestDox('registro-E23: dos peticiones casi a la vez para una cuenta que existe dan la misma respuesta que sin cuenta, no un error')]
    public function test_registro_e23_dos_peticiones_casi_a_la_vez_para_una_cuenta_que_existe_dan_la_misma_respuesta_que_sin_cuenta(): void
    {
        Notification::fake();
        $this->cuenta();

        $sinCuenta = $this->pedirEnlace(self::CORREO_SIN_CUENTA);

        // La otra petición guarda su token justo después de que esta borra el anterior y antes de que guarde el suyo:
        // el insert de esta choca con la llave de password_reset_tokens.
        $chocado = false;
        DB::listen(function (QueryExecuted $consulta) use (&$chocado): void {
            if ($chocado || ! preg_match('~^delete from "password_reset_tokens"~', $consulta->sql)) {
                return;
            }

            $chocado = true;
            DB::table('password_reset_tokens')->insert([
                'email' => self::CORREO,
                'token' => Hash::make('token-de-la-otra-peticion'),
                'created_at' => now(),
            ]);
        });

        $conCuenta = $this->pedirEnlace(self::CORREO);

        $this->assertTrue($chocado, 'La prueba debe provocar el choque.');
        $conCuenta->assertOk();
        $this->assertSame($sinCuenta->json(), $conCuenta->json());
        Notification::assertNothingSent();
    }

    #[TestDox('registro-E23: una petición sin un correo válido se rechaza y no manda nada')]
    public function test_registro_e23_una_peticion_sin_un_correo_valido_se_rechaza_y_no_manda_nada(): void
    {
        Notification::fake();
        $this->cuenta();

        $this->postJson(route('contrasena.olvido'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'Escribe tu correo.']);

        $this->pedirEnlace('no-es-un-correo')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['correo' => 'El correo no es válido.']);

        Notification::assertNothingSent();
    }

    // registro-E24 — Crea su contraseña nueva

    #[TestDox('registro-E24: el enlace abre una página del servidor con el formulario de la contraseña nueva')]
    public function test_registro_e24_el_enlace_abre_una_pagina_del_servidor_con_el_formulario_de_la_contrasena_nueva(): void
    {
        config(['app.name' => self::NOMBRE_DE_PRUEBA]);

        $respuesta = $this->get(route('contrasena.formulario', [
            'token' => self::TOKEN_DE_PRUEBA,
            'correo' => self::CORREO,
        ]));

        $respuesta->assertOk();
        $html = (string) $respuesta->getContent();

        preg_match('~<title>(.*?)</title>~s', $html, $titulo);
        $this->assertStringContainsString(self::NOMBRE_DE_PRUEBA, $titulo[1] ?? '');

        $this->assertStringContainsString('action="'.route('contrasena.restablecer').'"', $html);
        $this->assertMatchesRegularExpression('~name="token"[^>]*value="'.self::TOKEN_DE_PRUEBA.'"~', $html);
        $this->assertMatchesRegularExpression('~name="correo"[^>]*value="'.preg_quote(self::CORREO, '~').'"~', $html);
        $this->assertMatchesRegularExpression('~type="password"[^>]*name="password"~', $html);
        $this->assertMatchesRegularExpression('~type="password"[^>]*name="password_confirmation"~', $html);
    }

    #[TestDox('registro-E24: con el enlace vigente, la contraseña nueva queda con hash y la anterior deja de servir')]
    public function test_registro_e24_con_el_enlace_vigente_la_contrasena_nueva_queda_con_hash_y_la_anterior_deja_de_servir(): void
    {
        $usuario = $this->cuenta();
        $token = $this->tokenDelCorreo();

        $this->guardarContrasena($token, self::CORREO, self::CONTRASENA_NUEVA)
            ->assertOk()
            ->assertSee('Tu contraseña nueva quedó guardada');

        $guardada = (string) DB::table('usuarios')->where('id_usuarios', $usuario->id_usuarios)->value('password');
        $this->assertNotSame(self::CONTRASENA_NUEVA, $guardada);
        $this->assertTrue(Hash::check(self::CONTRASENA_NUEVA, $guardada));

        $this->iniciarSesion(UsuarioFactory::CONTRASENA)->assertUnprocessable();
        $this->iniciarSesion(self::CONTRASENA_NUEVA)->assertOk();
    }

    /**
     * Un costo igual al de las pruebas y uno mayor que el de cualquier entorno: el cast hashed guardaba tal cual el
     * primero y rechazaba con error el segundo.
     *
     * @return array<string, array{0: int}>
     */
    public static function costosDeUnHash(): array
    {
        return [
            'hash de costo 4' => [4],
            'hash de costo 13' => [13],
        ];
    }

    #[DataProvider('costosDeUnHash')]
    #[TestDox('registro-E24: una contraseña nueva con forma de hash se guarda con su propio hash, no tal cual ($_dataName)')]
    public function test_registro_e24_una_contrasena_nueva_con_forma_de_hash_se_guarda_con_su_propio_hash_no_tal_cual(int $costo): void
    {
        $usuario = $this->cuenta();
        $token = $this->tokenDelCorreo();

        // P parece un hash de bcrypt de la contraseña "x", que no cumple la regla (ADR-0018, decisión 2).
        $contrasena = Hash::make('x', ['rounds' => $costo]);

        $this->guardarContrasena($token, self::CORREO, $contrasena)->assertOk();

        $guardada = (string) DB::table('usuarios')->where('id_usuarios', $usuario->id_usuarios)->value('password');
        $this->assertNotSame($contrasena, $guardada);
        $this->assertTrue(Hash::check($contrasena, $guardada));

        // Entra con P, no con la contraseña de la que P es hash.
        $this->iniciarSesion('x')->assertUnprocessable();
        $this->iniciarSesion($contrasena)->assertOk();
    }

    #[TestDox('registro-E24 y E25: el enlace ya usado no sirve otra vez y la contraseña no cambia')]
    public function test_registro_e24_y_e25_el_enlace_ya_usado_no_sirve_otra_vez_y_la_contrasena_no_cambia(): void
    {
        $usuario = $this->cuenta();
        $token = $this->tokenDelCorreo();

        $this->guardarContrasena($token, self::CORREO, self::CONTRASENA_NUEVA)->assertOk();

        $this->guardarContrasena($token, self::CORREO, 'otra-contrasena-nueva')
            ->assertUnprocessable()
            ->assertSee(self::ENLACE_NO_VALIDO);

        $this->assertTrue(Hash::check(self::CONTRASENA_NUEVA, $usuario->fresh()->password));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    #[TestDox('registro-E24: la contraseña nueva cierra las sesiones abiertas de la cuenta, y solo las de esa cuenta')]
    public function test_registro_e24_la_contrasena_nueva_cierra_las_sesiones_abiertas_de_la_cuenta_y_solo_las_de_esa_cuenta(): void
    {
        $usuario = $this->cuenta();
        $telefono = $usuario->createToken('app')->plainTextToken;
        $tableta = $usuario->createToken('app')->plainTextToken;
        $otraCuenta = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'otra@ejemplo.com']);
        $deLaOtraCuenta = $otraCuenta->createToken('app')->plainTextToken;

        $token = $this->tokenDelCorreo();
        $this->guardarContrasena($token, self::CORREO, self::CONTRASENA_NUEVA)->assertOk();

        $this->assertSame(0, $usuario->tokens()->count());
        $this->consultarCuenta($telefono)->assertUnauthorized();
        $this->consultarCuenta($tableta)->assertUnauthorized();
        $this->consultarCuenta($deLaOtraCuenta)->assertOk();
    }

    #[TestDox('registro-E24: una contraseña nueva que no cumple la regla configurada se rechaza y el enlace sigue vigente')]
    public function test_registro_e24_una_contrasena_nueva_que_no_cumple_la_regla_configurada_se_rechaza_y_el_enlace_sigue_vigente(): void
    {
        config(['acceso.contrasena_minima' => 10]);
        $usuario = $this->cuenta();
        $token = $this->tokenDelCorreo();
        $formulario = route('contrasena.formulario', ['token' => $token, 'correo' => self::CORREO]);

        $this->guardarContrasena($token, self::CORREO, 'corta-123')
            ->assertRedirect($formulario)
            ->assertSessionHasErrors(['password' => 'La contraseña debe tener al menos 10 caracteres.']);

        $this->guardarContrasena($token, self::CORREO, self::CONTRASENA_NUEVA, 'no-es-la-misma-contrasena')
            ->assertRedirect($formulario)
            ->assertSessionHasErrors(['password' => 'Las dos contraseñas no coinciden.']);

        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $usuario->fresh()->password));
        $this->assertTrue($this->app['auth.password']->broker()->tokenExists($usuario, $token));

        // El formulario muestra el error junto a los campos.
        $this->get($formulario)->assertOk();
        $this->guardarContrasena($token, self::CORREO, 'corta-123');
        $this->get($formulario)->assertSee('La contraseña debe tener al menos 10 caracteres.');
    }

    // registro-E25 — El enlace vencido o ya usado se rechaza

    #[TestDox('registro-E25: el enlace vencido se rechaza y la contraseña no cambia')]
    public function test_registro_e25_el_enlace_vencido_se_rechaza_y_la_contrasena_no_cambia(): void
    {
        config(['auth.passwords.users.expire' => 60]);
        $usuario = $this->cuenta();
        $token = $this->tokenDelCorreo();

        $this->travel(61)->minutes();

        $this->guardarContrasena($token, self::CORREO, self::CONTRASENA_NUEVA)
            ->assertUnprocessable()
            ->assertSee(self::ENLACE_NO_VALIDO);

        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $usuario->fresh()->password));
    }

    #[TestDox('registro-E25: el enlace de una cuenta no sirve para otra, ni uno inventado, ni uno para un correo sin cuenta')]
    public function test_registro_e25_el_enlace_de_una_cuenta_no_sirve_para_otra_ni_uno_inventado_ni_uno_para_un_correo_sin_cuenta(): void
    {
        $usuario = $this->cuenta();
        $otraCuenta = Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'otra@ejemplo.com']);
        $token = $this->tokenDelCorreo();

        $intentos = [
            [$token, 'otra@ejemplo.com'],
            [self::TOKEN_DE_PRUEBA, self::CORREO],
            [$token, self::CORREO_SIN_CUENTA],
        ];

        foreach ($intentos as [$tokenDelIntento, $correoDelIntento]) {
            $this->guardarContrasena($tokenDelIntento, $correoDelIntento, self::CONTRASENA_NUEVA)
                ->assertUnprocessable()
                ->assertSee(self::ENLACE_NO_VALIDO);
        }

        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $usuario->fresh()->password));
        $this->assertTrue(Hash::check(UsuarioFactory::CONTRASENA, $otraCuenta->fresh()->password));
    }

    // registro-E31 — Demasiadas peticiones de recuperación

    #[TestDox('registro-E31: la sexta petición del mismo correo y la misma dirección en el minuto recibe 429 y no manda otro correo')]
    public function test_registro_e31_la_sexta_peticion_del_mismo_correo_y_la_misma_direccion_en_el_minuto_recibe_429_y_no_manda_otro_correo(): void
    {
        config(['acceso.intentos_por_minuto' => 5]);
        Notification::fake();
        $this->cuenta();

        for ($peticion = 1; $peticion <= 5; $peticion++) {
            $this->pedirEnlace(self::CORREO)->assertOk();
        }

        // Dentro del límite, cada petición manda su correo (registro-E22).
        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 5);

        $this->pedirEnlace(self::CORREO)
            ->assertTooManyRequests()
            ->assertJsonPath('message', self::DEMASIADOS_INTENTOS)
            ->assertHeader('Retry-After');

        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 5);

        // El límite es por minuto: pasado el minuto, vuelve a aceptar.
        $this->travel(61)->seconds();
        $this->pedirEnlace(self::CORREO)->assertOk();
        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 6);
    }

    #[TestDox('registro-E31: el límite es por correo y por dirección, como el del inicio de sesión: otro correo, u otra dirección, sigue pidiendo')]
    public function test_registro_e31_el_limite_es_por_correo_y_por_direccion_como_el_del_inicio_de_sesion(): void
    {
        config(['acceso.intentos_por_minuto' => 5]);
        Notification::fake();
        $this->cuenta();
        Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => 'otra@ejemplo.com']);

        for ($peticion = 1; $peticion <= 5; $peticion++) {
            $this->pedirEnlace(self::CORREO)->assertOk();
        }

        $this->pedirEnlace('otra@ejemplo.com')->assertOk();
        $this->pedirEnlace(self::CORREO, '203.0.113.9')->assertOk();

        // El mismo correo con otras mayúsculas es el mismo correo.
        $this->pedirEnlace('Persona@Ejemplo.COM')->assertTooManyRequests();

        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 7);
    }

    #[TestDox('registro-E22 y E31: un correo que no es texto no rompe el límite: responde con el error del correo y no manda nada')]
    public function test_registro_e22_y_e31_un_correo_que_no_es_texto_no_rompe_el_limite_y_responde_con_el_error_del_correo(): void
    {
        Notification::fake();
        $this->cuenta();

        $datos = ['correo' => [self::CORREO]];

        // En JSON y como formulario (correo[]=…): las dos llegan al limitador como un arreglo.
        foreach ([$this->postJson(route('contrasena.olvido'), $datos), $this->post(route('contrasena.olvido'), $datos)] as $respuesta) {
            $respuesta->assertUnprocessable();
            $respuesta->assertJsonValidationErrors(['correo' => 'El correo no es válido.']);
        }

        Notification::assertNothingSent();
    }

    #[TestDox('registro-E24: el formulario con un correo que no es texto se muestra sin correo, sin error del servidor')]
    public function test_registro_e24_el_formulario_con_un_correo_que_no_es_texto_se_muestra_sin_correo(): void
    {
        $respuesta = $this->get(route('contrasena.formulario', ['token' => self::TOKEN_DE_PRUEBA]).'?correo[]='.self::CORREO);

        $respuesta->assertOk();
        $this->assertMatchesRegularExpression('~name="correo"[^>]*value=""~', (string) $respuesta->getContent());
    }

    #[TestDox('registro-E31: el límite sale de la configuración de acceso, el mismo valor del inicio de sesión')]
    public function test_registro_e31_el_limite_sale_de_la_configuracion_de_acceso_el_mismo_valor_del_inicio_de_sesion(): void
    {
        // ADR-0018, decisión 3: un solo valor para el inicio de sesión y la petición del enlace.
        config(['acceso.intentos_por_minuto' => 2]);
        Notification::fake();
        $this->cuenta();

        $this->pedirEnlace(self::CORREO)->assertOk();
        $this->pedirEnlace(self::CORREO)->assertOk();
        $this->pedirEnlace(self::CORREO)->assertTooManyRequests();

        Notification::assertSentOnDemandTimes(EnlaceDeRecuperacion::class, 2);
    }

    private function cuenta(): Usuario
    {
        return Usuario::factory()->conRol(Rol::USUARIO)->create(['correo' => self::CORREO]);
    }

    private function pedirEnlace(string $correo, string $direccion = '127.0.0.1'): TestResponse
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $direccion])
            ->postJson(route('contrasena.olvido'), ['correo' => $correo]);
    }

    /**
     * Pide el enlace por la API, como la app, y devuelve el token que llegó en el correo.
     */
    private function tokenDelCorreo(string $correo = self::CORREO): string
    {
        Notification::fake();
        $this->pedirEnlace($correo)->assertOk();

        $token = null;
        Notification::assertSentOnDemand(
            EnlaceDeRecuperacion::class,
            function (EnlaceDeRecuperacion $aviso) use ($correo, &$token): bool {
                $token = $aviso->token;

                return $aviso->correo === $correo;
            },
        );

        return (string) $token;
    }

    private function guardarContrasena(
        string $token,
        string $correo,
        string $contrasena,
        ?string $confirmacion = null,
    ): TestResponse {
        $this->app['auth']->forgetGuards();

        return $this->from(route('contrasena.formulario', ['token' => $token, 'correo' => $correo]))
            ->post(route('contrasena.restablecer'), [
                'token' => $token,
                'correo' => $correo,
                'password' => $contrasena,
                'password_confirmation' => $confirmacion ?? $contrasena,
            ]);
    }

    private function iniciarSesion(string $contrasena): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->postJson(route('sesion.iniciar'), ['correo' => self::CORREO, 'password' => $contrasena]);
    }

    private function consultarCuenta(string $token): TestResponse
    {
        $this->app['auth']->forgetGuards();

        return $this->getJson(route('cuenta'), ['Authorization' => 'Bearer '.$token]);
    }

    /**
     * El valor de una variable de .env.example, sin comillas.
     */
    private function variableDelEjemplo(string $nombre): ?string
    {
        foreach (file(base_path('.env.example'), FILE_IGNORE_NEW_LINES) as $linea) {
            if (preg_match('/^\s*'.preg_quote($nombre, '/').'\s*=(.*)$/', $linea, $partes)) {
                return trim(trim($partes[1]), '"\'');
            }
        }

        return null;
    }

    /**
     * Lee config/auth.php de nuevo con unas variables de entorno puestas solo durante la lectura.
     *
     * @param  array<string, string>  $variables
     * @return array<string, mixed>
     */
    private function configuracionDeAuthConEntorno(array $variables): array
    {
        $nombres = ['RECUPERACION_VENCE_MINUTOS'];
        $antes = [];

        foreach ($nombres as $nombre) {
            $antes[$nombre] = [$_SERVER[$nombre] ?? null, $_ENV[$nombre] ?? null];
            unset($_SERVER[$nombre], $_ENV[$nombre]);
        }

        foreach ($variables as $nombre => $valor) {
            $_SERVER[$nombre] = $_ENV[$nombre] = $valor;
        }

        try {
            return require config_path('auth.php');
        } finally {
            foreach ($antes as $nombre => [$servidor, $entorno]) {
                unset($_SERVER[$nombre], $_ENV[$nombre]);

                if ($servidor !== null) {
                    $_SERVER[$nombre] = $servidor;
                }

                if ($entorno !== null) {
                    $_ENV[$nombre] = $entorno;
                }
            }
        }
    }
}
