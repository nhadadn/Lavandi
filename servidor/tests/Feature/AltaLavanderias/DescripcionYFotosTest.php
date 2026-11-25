<?php

namespace Tests\Feature\AltaLavanderias;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\Feature\AltaLavanderias\Soporte\AltaDePrueba;
use Tests\Feature\Plazas\Soporte\PlazasDePrueba;
use Tests\TestCase;

/**
 * La descripción y las fotos del perfil de la lavandería, desde su Perfil y horarios de la app
 * (NSV-2026-001-v8.pdf:16:ANEXO A, Sección A.3, Módulo A #5, "Fotos, descripción"; :17:Módulo B #6, "Edición de
 * información de la lavandería"): la descripción es opcional, y las fotos son una lista completa y ordenada que
 * reemplaza a la anterior, de una al máximo de la configuración. La primera es la principal. El registro sigue con una
 * sola foto (Módulo B #1). Lo decidió el concilio del 2026-11-24 (acta-2026-11-20-modelo-y-pasarela.md, decisión 44;
 * DD-097).
 *
 * Una foto que ya tiene se nombra por su lugar en "fotos" de la última lectura, nunca por su ruta ni por su llave
 * (perfil-lavanderia-E03; ADR-0016, regla 5). Lo que ve el usuario sale de su perfil (perfil-lavanderia-E01).
 *
 * Escenarios: alta-lavanderias-E26 a alta-lavanderias-E30, alta-lavanderias-E18 y alta-lavanderias-E20 · Tarea 2.4 ·
 * Spec: openspec/changes/alta-de-lavanderias/specs/alta-lavanderias/spec.md
 */
class DescripcionYFotosTest extends TestCase
{
    use AltaDePrueba;
    use PlazasDePrueba;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepararAlta();
    }

    #[TestDox('alta-lavanderias-E26: la lavandería escribe su descripción, el servidor la guarda y el perfil del usuario la muestra; vacía, el perfil queda sin descripción')]
    public function test_alta_lavanderias_e26_la_lavanderia_escribe_su_descripcion_y_el_usuario_la_ve(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->assertNull($lavanderia->descripcion);
        $this->consultar($cuenta, $lavanderia->id_publico)->assertJsonPath('lavanderia.descripcion', null);

        $this->editar($cuenta, $lavanderia->id_publico, ['descripcion' => 'Lavandería de prueba con entrega el mismo día.'])
            ->assertOk()
            ->assertJsonPath('lavanderia.descripcion', 'Lavandería de prueba con entrega el mismo día.')
            // Lo que no se mandó sigue igual.
            ->assertJsonPath('lavanderia.nombre', 'Lavandería de prueba Alta');

        $this->assertSame('Lavandería de prueba con entrega el mismo día.', $lavanderia->fresh()->descripcion);
        $this->perfilDelUsuario($lavanderia)->assertJsonPath('lavanderia.descripcion', 'Lavandería de prueba con entrega el mismo día.');

        $this->editar($cuenta, $lavanderia->id_publico, ['descripcion' => ''])
            ->assertOk()
            ->assertJsonPath('lavanderia.descripcion', null);

        $this->assertNull($lavanderia->fresh()->descripcion);
        $this->perfilDelUsuario($lavanderia)->assertJsonPath('lavanderia.descripcion', null);
    }

    #[TestDox('alta-lavanderias-E26: una descripción más larga que el máximo técnico se rechaza en su campo y no cambia nada')]
    public function test_alta_lavanderias_e26_una_descripcion_demasiado_larga_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $this->editar($cuenta, $lavanderia->id_publico, ['descripcion' => 'Descripción de prueba.'])->assertOk();

        $this->editar($cuenta, $lavanderia->id_publico, ['descripcion' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['descripcion' => 'La descripción puede tener hasta 1000 caracteres.']);

        $this->assertSame('Descripción de prueba.', $lavanderia->fresh()->descripcion);
    }

    #[TestDox('alta-lavanderias-E27: la lavandería agrega una foto, que queda al final de su orden, y el perfil del usuario la muestra en ese lugar')]
    public function test_alta_lavanderias_e27_agregar_una_foto_queda_al_final(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $antes = $this->conFotos($lavanderia, 2);

        $respuesta = $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'fotos' => [0, 1, $this->fotoDePrueba('nueva.jpg')],
        ])->assertOk();

        $rutas = $this->rutasEnOrden($lavanderia);
        $this->assertCount(3, $rutas);
        $this->assertSame($antes, array_slice($rutas, 0, 2));
        $this->assertSame([1, 2, 3], $lavanderia->fotos()->orderBy('orden')->pluck('orden')->all());
        $this->assertEqualsCanonicalizing($rutas, $this->archivosDeFotos());

        // La app recibe la lista en su orden, solo con la URL firmada de cada una, y el máximo de la configuración.
        $fotos = $respuesta->json('lavanderia.fotos');
        $this->assertCount(3, $fotos);
        $this->assertSame(['url', 'vence_en'], array_keys($fotos[2]));
        $this->assertSame($rutas, $this->rutasDe($fotos));
        $respuesta->assertJsonPath('lavanderia.maximo_de_fotos', (int) config('fotos.maximo_por_lavanderia'));

        // El usuario la ve en el mismo lugar.
        $this->assertSame($rutas, $this->rutasDe($this->perfilDelUsuario($lavanderia)->json('lavanderia.fotos')));
    }

    #[TestDox('alta-lavanderias-E27: una foto que no tiene, una repetida o un archivo que no es foto se rechazan en su lugar de la lista, y nada cambia')]
    public function test_alta_lavanderias_e27_una_foto_que_no_tiene_repetida_o_que_no_es_foto_se_rechaza(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $antes = $this->conFotos($lavanderia, 2);

        $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [0, 7]])
            ->assertUnprocessable()->assertJsonValidationErrors(['fotos.1']);
        $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [1, 1]])
            ->assertUnprocessable()->assertJsonValidationErrors(['fotos.1']);
        $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [0, 'portada.jpg']])
            ->assertUnprocessable()->assertJsonValidationErrors(['fotos.1']);
        $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'fotos' => [0, $this->fotoDePrueba('pesada.jpg', kilobytes: (int) config('fotos.peso_maximo_kb') + 1)],
        ])->assertUnprocessable()->assertJsonValidationErrors(['fotos.1']);
        $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'fotos' => [9, $this->fotoDePrueba('valida.jpg')],
        ])->assertUnprocessable()->assertJsonValidationErrors(['fotos.0']);

        $this->assertSame($antes, $this->rutasEnOrden($lavanderia));
        $this->assertEqualsCanonicalizing($antes, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E28: la lavandería quita una foto: deja de estar en su perfil y su archivo se borra del almacenamiento')]
    public function test_alta_lavanderias_e28_quitar_una_foto_la_borra_del_perfil_y_del_almacenamiento(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);

        $respuesta = $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [0, 2]])->assertOk();

        $this->assertSame([$primera, $tercera], $this->rutasEnOrden($lavanderia));
        $this->assertSame([1, 2], $lavanderia->fotos()->orderBy('orden')->pluck('orden')->all());
        Storage::disk(config('fotos.disco'))->assertMissing($segunda);
        $this->assertEqualsCanonicalizing([$primera, $tercera], $this->archivosDeFotos());
        $this->assertSame([$primera, $tercera], $this->rutasDe($respuesta->json('lavanderia.fotos')));
        $this->assertSame([$primera, $tercera], $this->rutasDe($this->perfilDelUsuario($lavanderia)->json('lavanderia.fotos')));
    }

    #[TestDox('alta-lavanderias-E28: la última foto no se puede quitar, porque el registro pide una, y se queda con su archivo')]
    public function test_alta_lavanderias_e28_la_ultima_foto_no_se_puede_quitar(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $ultima = $lavanderia->fotos()->sole()->ruta;

        $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fotos' => 'La lavandería necesita al menos una foto.']);

        $this->assertSame([$ultima], $this->rutasEnOrden($lavanderia));
        $this->assertSame([$ultima], $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E29: la lavandería pasa la tercera foto antes de la segunda y el perfil del usuario las muestra en el orden nuevo; la primera es la principal')]
    public function test_alta_lavanderias_e29_cambiar_el_orden_de_las_fotos(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [$primera, $segunda, $tercera] = $this->conFotos($lavanderia, 3);

        $respuesta = $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [0, 2, 1]])->assertOk();

        $this->assertSame([$primera, $tercera, $segunda], $this->rutasEnOrden($lavanderia));
        $this->assertSame([$primera, $tercera, $segunda], $this->rutasDe($this->perfilDelUsuario($lavanderia)->json('lavanderia.fotos')));
        // Ningún archivo se borra: solo cambia el orden.
        $this->assertEqualsCanonicalizing([$primera, $segunda, $tercera], $this->archivosDeFotos());
        // La principal es la primera.
        $this->assertSame([$primera], $this->rutasDe([$respuesta->json('lavanderia.foto')]));
    }

    #[TestDox('alta-lavanderias-E29: la que queda primera, ya guardada o nueva, es la principal')]
    public function test_alta_lavanderias_e29_la_primera_es_la_principal(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [$primera, $segunda] = $this->conFotos($lavanderia, 2);

        $respuesta = $this->editar($cuenta, $lavanderia->id_publico, ['fotos' => [1, 0]])->assertOk();

        $this->assertSame([$segunda, $primera], $this->rutasEnOrden($lavanderia));
        $this->assertSame([$segunda], $this->rutasDe([$respuesta->json('lavanderia.foto')]));

        // Una nueva al frente, en multipart: el archivo y las posiciones llegan por separado y la lista manda el orden.
        $respuesta = $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'fotos' => [$this->fotoDePrueba('portada.jpg'), 0, 1],
        ])->assertOk();

        $rutas = $this->rutasEnOrden($lavanderia);
        $this->assertCount(3, $rutas);
        $this->assertSame([$segunda, $primera], array_slice($rutas, 1));
        $this->assertSame([$rutas[0]], $this->rutasDe([$respuesta->json('lavanderia.foto')]));
    }

    #[TestDox('alta-lavanderias-E30: con el máximo configurable de fotos, el servidor rechaza una lista con más, con un mensaje que lo dice, y nada cambia')]
    public function test_alta_lavanderias_e30_el_servidor_rechaza_pasar_del_maximo(): void
    {
        config(['fotos.maximo_por_lavanderia' => 3]);
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        $antes = $this->conFotos($lavanderia, 3);
        $this->consultar($cuenta, $lavanderia->id_publico)->assertJsonPath('lavanderia.maximo_de_fotos', 3);

        $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'fotos' => [0, 1, 2, $this->fotoDePrueba('otra.jpg')],
        ])->assertUnprocessable()->assertJsonValidationErrors(['fotos' => 'La lavandería puede tener hasta 3 fotos.']);

        $this->assertSame($antes, $this->rutasEnOrden($lavanderia));
        $this->assertEqualsCanonicalizing($antes, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E18: la foto sola sigue reemplazando la principal; con la lista de fotos a la vez se rechaza y nada cambia')]
    public function test_alta_lavanderias_e18_la_foto_sola_sigue_y_no_va_junto_con_la_lista(): void
    {
        $cuenta = $this->cuentaDeLavanderia();
        $lavanderia = $this->registrada($cuenta);
        [, $segunda] = $this->conFotos($lavanderia, 2);

        $this->editarConFoto($cuenta, $lavanderia->id_publico, [
            'foto' => $this->fotoDePrueba('principal.jpg'),
            'fotos' => [1, 0],
        ])->assertUnprocessable()->assertJsonValidationErrors(['fotos' => 'Manda la foto principal o la lista de fotos, no las dos.']);
        $antes = $this->rutasEnOrden($lavanderia);
        $this->assertCount(2, $this->archivosDeFotos());

        $this->editarConFoto($cuenta, $lavanderia->id_publico, ['foto' => $this->fotoDePrueba('principal.jpg')])->assertOk();

        $rutas = $this->rutasEnOrden($lavanderia);
        $this->assertNotSame($antes[0], $rutas[0]);
        $this->assertSame($segunda, $rutas[1]);
        $this->assertEqualsCanonicalizing($rutas, $this->archivosDeFotos());
    }

    #[TestDox('alta-lavanderias-E20: una lavandería no cambia la descripción ni las fotos de otra, y las de la otra no cambian')]
    public function test_alta_lavanderias_e20_una_lavanderia_no_cambia_la_descripcion_ni_las_fotos_de_otra(): void
    {
        $primera = $this->cuentaDeLavanderia();
        $this->registrada($primera);
        $segunda = $this->cuentaDeLavanderia();
        $deLaSegunda = $this->registrada($segunda, ['nombre' => 'Lavandería de prueba Segunda']);
        $fotos = $this->conFotos($deLaSegunda, 2);
        $archivos = $this->archivosDeFotos();

        $this->editar($primera, $deLaSegunda->id_publico, ['descripcion' => 'Descripción ajena', 'fotos' => [1]])
            ->assertNotFound()->assertJsonMissingPath('lavanderia');
        $this->editarConFoto($primera, $deLaSegunda->id_publico, ['fotos' => [0, $this->fotoDePrueba('ajena.jpg')]])
            ->assertNotFound();

        $this->assertNull($deLaSegunda->fresh()->descripcion);
        $this->assertSame($fotos, $this->rutasEnOrden($deLaSegunda));
        $this->assertSame($archivos, $this->archivosDeFotos());
    }
}
