<?php

namespace App\Autorizacion;

use App\Exportacion\Exportacion;
use App\Models\AvisoMasivo;
use App\Models\Direccion;
use App\Models\Dispositivo;
use App\Models\EstadoPedido;
use App\Models\Lavanderia;
use App\Models\Pago;
use App\Models\Pedido;
use App\Models\PedidoAsignacion;
use App\Models\Plaza;
use App\Models\Repartidor;
use App\Models\ReporteSoporte;
use App\Models\Rol;
use App\Models\Testimonio;
use App\Models\Usuario;
use App\Repartidores\RepartidorSinAcceso;
use Filament\Pages\Dashboard;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;

/**
 * El componente único de autorización: decide si una cuenta puede ver o tocar algo (vault/arquitectura/c3-api.md;
 * CLAUDE.md §9.5; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario).
 *
 * Toda ruta protegida de la API pasa por él con el middleware autorizar:<rol> (App\Http\Middleware\Autorizar), y el
 * panel de Filament le pregunta a través de Usuario::canAccessPanel(). Ninguna otra clase decide accesos. Las rutas que
 * no piden sesión y las piezas del panel que no muestran datos también se declaran aquí (autorizacion-E01).
 *
 * Conoce el rol (registro-E14 y registro-E15), la tenencia de las direcciones (direcciones-E14), de los pedidos del
 * usuario (confirmacion-E12) y de los de la lavandería, con su plaza (pedidos-lavanderia-E02 y E09), qué teléfonos
 * reciben los avisos de un pedido (avisos-E04 y avisos-E12), qué ve la administración en el panel (las plazas, las
 * lavanderías, los usuarios y los pedidos de todas las plazas: autorizacion-E03, administracion-E01, E06 y E11) y quién
 * abre sesión o bloquea (administracion-E03), quién fija la comisión global (administracion-E11), quién ve los
 * reportes de Soporte, también los que no tienen plaza (perfil-usuario-E17), y quién edita los testimonios de la portada
 * (landing-E19). Del repartidor
 * (hito H8; ADR-0019, decisión 1), conoce su rol en la app, qué repartidor opera (repartidor-E03 y E06) y, con su tarea,
 * sus pedidos, de su plaza y con el pago acreditado.
 *
 * Del cobro (openspec/changes/pago/), conoce qué pedidos paga la cuenta y qué cobros consulta: solo los suyos, con
 * pedidosVisibles() y pagosVisibles(). La ruta del aviso de la pasarela, POST api/pasarela/avisos, no pide sesión y lo
 * declara rutasSinSesion(): no hay cuenta detrás, y solo un aviso que la pasarela configurada reconoce como suyo cambia
 * algo (App\Cobro\RecepcionDeAvisos; openspec/changes/pago/design.md, decisión 5).
 */
final class Autorizacion
{
    /**
     * Las rutas que no piden sesión, por "MÉTODO uri" y sin HEAD, cada una con la razón por la que no la pide
     * (autorizacion-E01). Toda otra ruta de la API pide la sesión de Sanctum y pasa por autorizar:<rol>, y toda otra
     * ruta del panel pasa por su puerta, que pregunta a puedeEntrarAlPanel(). Una ruta que no hace nada de eso hace
     * fallar tests/Feature/Autorizacion/CoberturaDeReglasTest.php, que la nombra. La misma uri con otro método no
     * hereda el permiso.
     *
     * @return array<string, string>
     */
    public function rutasSinSesion(): array
    {
        // Livewire arma su prefijo con la llave de la aplicación, así que cambia en cada instalación.
        $livewire = ltrim(Livewire::getUriPrefix(), '/');

        $firmada = 'Solo con una URL firmada que vence, que el servidor arma después de autorizar.';
        $deLivewire = 'Un archivo de Livewire, sin datos.';

        return [...$this->rutasSinSesionDeDesarrollo(),
            // Las de la app que abren una sesión o recuperan la contraseña (registro-E14; ADR-0005 y ADR-0018).
            'POST api/registro' => 'Crea la cuenta de usuario y abre su sesión (registro-e-inicio-de-sesion).',
            'POST api/sesion' => 'Abre la sesión con correo y contraseña (registro-e-inicio-de-sesion).',
            'POST api/sesion/google' => 'Abre la sesión con Google (registro-e-inicio-de-sesion).',
            'POST api/contrasena/olvido' => 'Pide el enlace para recuperar la contraseña, con su límite (registro-E31).',
            'POST api/repartidor/registro' => 'Crea la cuenta del repartidor, pendiente de alta, y abre su sesión (repartidor-E01).',

            // El aviso (webhook) de la pasarela de pago: lo manda la pasarela, no una cuenta (pago; ADR-0010).
            'POST api/pasarela/avisos' => 'El aviso de la pasarela de pago: sin cuenta detrás; solo uno que la pasarela autentica cambia algo (pago-E07).',

            // La portada y sus formularios, que son públicos (ADR-0011; openspec/changes/landing/).
            'GET /' => 'La portada pública de la landing.',
            'POST formularios/interes' => 'El formulario de interés de lavanderías de la portada.',
            'POST formularios/contacto' => 'El formulario de contacto de la portada.',
            // Los textos legales, públicos como la portada (textos-legales-E01).
            'GET aviso-de-privacidad' => 'El aviso de privacidad, sin datos de ninguna cuenta (textos-legales-E01).',
            'GET terminos' => 'Los términos y condiciones, sin datos de ninguna cuenta (textos-legales-E01).',
            // Lo que leen los buscadores: las direcciones de las páginas públicas, sin datos (medicion-E08).
            'GET sitemap.xml' => 'El mapa del sitio, con las direcciones de las páginas públicas (medicion-E08).',
            'GET robots.txt' => 'El archivo de robots, que declara el mapa del sitio (medicion-E08).',

            // La página para crear la contraseña nueva: la llave es el token del enlace del correo (ADR-0018, decisión 4).
            'GET contrasena/restablecer/{token}' => 'Muestra el formulario; la llave es el token del enlace del correo.',
            'POST contrasena/restablecer' => 'Guarda la contraseña nueva solo con el token válido del enlace del correo.',

            // La entrada al panel: después, toda ruta del panel pasa por su puerta (registro-E15 y autorizacion-E03).
            'GET admin/login' => 'La página de inicio de sesión del panel; lo que sigue pasa por la puerta del panel.',

            // La infraestructura de Laravel, de Sanctum y de Filament.
            'GET up' => 'La salud del servidor, sin datos (base-E01).',
            'GET sanctum/csrf-cookie' => 'Solo pone la cookie CSRF de Sanctum, sin datos.',
            'GET storage/{path}' => $firmada,
            'PUT storage/{path}' => $firmada,
            // Las fotos privadas en local y en las pruebas; en producción viven en el bucket privado (ADR-0006).
            'GET archivos/fotos/{path}' => $firmada,
            'PUT archivos/fotos/{path}' => $firmada,
            'GET filament/exports/{export}/download' => 'De Filament: la descarga de un reporte del panel. Su controlador exige sesión (401 sin ella) y PoliticaDeExportacion le pregunta a puedeDescargarLaExportacion(): solo la baja la cuenta que la pidió; a otra le responde 404 (exportacion-E11).',
            'GET filament/imports/{import}/failed-rows/download' => 'De Filament: el panel no importa; su controlador exige sesión y que el archivo sea de la cuenta.',

            // Livewire, con el que corre el panel.
            "POST {$livewire}/update" => 'Las acciones de los componentes de Livewire: las del panel vuelven a pasar por su puerta, que Filament hace persistente.',
            "POST {$livewire}/upload-file" => $firmada,
            "GET {$livewire}/preview-file/{filename}" => $firmada,
            "GET {$livewire}/livewire.js" => $deLivewire,
            "GET {$livewire}/livewire.min.js.map" => $deLivewire,
            "GET {$livewire}/livewire.csp.min.js.map" => $deLivewire,
            "GET {$livewire}/js/{component}.js" => $deLivewire,
            "GET {$livewire}/css/{component}.css" => $deLivewire,
            "GET {$livewire}/css/{component}.global.css" => $deLivewire,
        ];
    }

    /**
     * Las rutas de las herramientas de desarrollo que no piden sesión: solo existen en local y en las pruebas, así que solo
     * se declaran ahí (routes/web.php).
     *
     * @return array<string, string>
     */
    private function rutasSinSesionDeDesarrollo(): array
    {
        if (! app()->environment(['local', 'testing'])) {
            return [];
        }

        $simulada = 'La página de checkout de la pasarela simulada, solo en local y en las pruebas: la llave es la orden de la pasarela simulada, que el servidor da al dueño del pedido (openspec/changes/pago/design.md).';

        return [
            'GET desarrollo/pasarela/checkout/{orden}' => $simulada,
            'POST desarrollo/pasarela/checkout/{orden}' => $simulada,
        ];
    }

    /**
     * Las piezas del panel que no muestran datos de la operación y por eso no piden otra regla que la puerta del panel,
     * cada una con su razón (autorizacion-E01). Todo otro recurso, página o widget del panel le niega la entrada a una
     * cuenta que no es de administración aunque pasara la puerta: lo comprueba
     * tests/Feature/Autorizacion/CoberturaDeReglasTest.php.
     *
     * @return array<class-string, string>
     */
    public function panelSinDatos(): array
    {
        return [
            Dashboard::class => 'La página de inicio de Filament: solo muestra los widgets que cada uno deja ver.',
            AccountWidget::class => 'El nombre de la propia cuenta y el botón de salir.',
            FilamentInfoWidget::class => 'La versión de Filament y sus enlaces.',
        ];
    }

    /**
     * Si la cuenta tiene el rol que pide la ruta. Una cuenta bloqueada no pasa ninguna (administracion-E03): al
     * bloquearla se revocan sus tokens, y si le quedara uno, tampoco serviría.
     */
    public function permite(Usuario $usuario, string $rol): bool
    {
        return $this->puedeIniciarSesion($usuario) && $usuario->roles->contains('codigo', $rol);
    }

    /**
     * Si la cuenta puede abrir una sesión, por cualquier vía: con correo y contraseña, con Google y, cuando exista, con
     * Apple (DD-069). Una cuenta bloqueada desde el panel no puede hasta que se levante el bloqueo (administracion-E03 y
     * E04; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3), ni la de un repartidor dado de baja
     * (repartidor-E07; Módulo D #2). Toda entrada pregunta aquí.
     */
    public function puedeIniciarSesion(Usuario $usuario): bool
    {
        return $usuario->estado !== Usuario::BLOQUEADO && ! $this->esRepartidorDeBaja($usuario);
    }

    /**
     * Si la cuenta es la de un repartidor dado de baja. Solo pregunta a la base si la cuenta tiene el rol de repartidor.
     */
    private function esRepartidorDeBaja(Usuario $usuario): bool
    {
        return $usuario->roles->contains('codigo', Rol::REPARTIDOR)
            && Repartidor::onlyTrashed()
                ->where('id_usuarios', $usuario->getKey())
                ->where('estado', Repartidor::BAJA)
                ->exists();
    }

    /**
     * Si la cuenta puede bloquear a otra o levantar su bloqueo desde el panel: solo la administración, y solo a una
     * cuenta de usuario, de las que ve en la gestión de usuarios (administracion-E03 y E04). Las cuentas de los socios se
     * suspenden con su lavandería y su repartidor (Módulo D #1 y #2).
     */
    public function puedeBloquear(Usuario $quien, Usuario $cuenta): bool
    {
        return $this->usuariosDelPanel($quien)->whereKey($cuenta->getKey())->exists();
    }

    /**
     * Si la cuenta usa la app: la de un rol con módulo en ella, usuario, socio lavandería o socio repartidor. La
     * administración entra por el panel y la app no le da token (registro-E33).
     */
    public function usaLaApp(Usuario $usuario): bool
    {
        return $this->permite($usuario, Rol::USUARIO)
            || $this->permite($usuario, Rol::LAVANDERIA)
            || $this->permite($usuario, Rol::REPARTIDOR);
    }

    /**
     * El repartidor de la cuenta, en cualquier estado salvo de baja: el que consulta su perfil, también mientras espera
     * su alta o está suspendido (repartidor-E01 y E08). Ninguno si la cuenta no es de un repartidor.
     */
    public function repartidorDeLaCuenta(Usuario $usuario): ?Repartidor
    {
        return Repartidor::query()->where('id_usuarios', $usuario->getKey())->first();
    }

    /**
     * El repartidor de la cuenta, si opera: solo uno activo, con su plaza, ve y mueve pedidos, y su historial y sus
     * estadísticas (repartidor-E03, E05 y E06). Toda ruta de pedidos del Módulo C pasa por aquí, con el middleware
     * repartidor-opera (App\Http\Middleware\RepartidorQueOpera).
     *
     * @throws RepartidorSinAcceso con el estado del repartidor: pendiente, suspendido, o ninguno
     */
    public function repartidorQueOpera(Usuario $usuario): Repartidor
    {
        $repartidor = $this->permite($usuario, Rol::REPARTIDOR) ? $this->repartidorDeLaCuenta($usuario) : null;

        if ($repartidor === null || ! $this->repartidorOpera($repartidor)) {
            throw new RepartidorSinAcceso($repartidor?->estado);
        }

        return $repartidor;
    }

    /**
     * Si el repartidor opera: activo, dado de alta en una plaza y no dado de baja (repartidor-E05 y E06).
     */
    public function repartidorOpera(Repartidor $repartidor): bool
    {
        return $repartidor->estado === Repartidor::ACTIVO
            && $repartidor->id_plazas !== null
            && ! $repartidor->trashed();
    }

    /**
     * Si la cuenta entra al panel de administración: solo con el rol de administración (ADR-0005; base-tecnica,
     * "Panel de administración cerrado"). Una cuenta de usuario nunca entra (registro-E15).
     */
    public function puedeEntrarAlPanel(Usuario $usuario): bool
    {
        return $this->permite($usuario, Rol::ADMINISTRACION);
    }

    /**
     * Si la cuenta ve el panel de indicadores: solo la administración, porque vive dentro del panel de administración
     * (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; indicadores-E01). La página y cada uno de sus widgets preguntan
     * aquí. Los indicadores cuentan los datos de todas las plazas: filtrarlos por plaza está fuera del contrato
     * (openspec/changes/indicadores/proposal.md).
     */
    public function puedeVerLosIndicadores(Usuario $usuario): bool
    {
        return $this->puedeEntrarAlPanel($usuario);
    }

    /**
     * Si la cuenta ve los reportes financieros del panel, los ingresos por periodo y la conciliación con la pasarela:
     * solo la administración (NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #6; administracion-E17, E20 y
     * E21). Sus páginas, sus widgets y la acción de conciliar preguntan aquí. Cuentan los pagos de todas las plazas, como
     * el resto del panel: la administración las ve todas.
     */
    public function puedeVerLosReportesFinancieros(Usuario $usuario): bool
    {
        return $this->puedeEntrarAlPanel($usuario);
    }

    /**
     * Las direcciones que la cuenta ve y usa: solo las suyas (direcciones-E14). Toda consulta de direcciones de la API
     * parte de aquí.
     *
     * @return Builder<Direccion>
     */
    public function direccionesVisibles(Usuario $usuario): Builder
    {
        return Direccion::query()->where('id_usuarios', $usuario->getKey());
    }

    /**
     * Los pedidos que la cuenta ve: solo los que hizo (confirmacion-E12 e historial-E02; NSV-2026-001-v8.pdf:20:ANEXO A,
     * Sección A.3). Toda consulta de pedidos de la API para el usuario parte de aquí, también su historial y la
     * calificación del servicio, que solo se hace sobre un pedido propio (historial-E09). Los de la lavandería y el
     * repartidor, con su tenencia y su plaza, llegan con autorizacion-por-rol (H5).
     *
     * @return Builder<Pedido>
     */
    public function pedidosVisibles(Usuario $usuario): Builder
    {
        return Pedido::query()->where('id_usuarios', $usuario->getKey());
    }

    /**
     * Los pagos que la cuenta consulta: solo los de los pedidos que hizo (pago-E08; NSV-2026-001-v8.pdf:20:ANEXO A,
     * Sección A.3). Parte de pedidosVisibles(): el de un pedido de otra cuenta responde igual que uno que no existe
     * (ADR-0019, decisión 3).
     *
     * @return Builder<Pago>
     */
    public function pagosVisibles(Usuario $usuario): Builder
    {
        return Pago::query()->whereIn('id_pedidos', $this->pedidosVisibles($usuario)->select('id_pedidos'));
    }

    /**
     * Los pedidos que ve y responde la cuenta de un socio lavandería: los hechos a una lavandería que esa cuenta
     * administra, de la misma plaza que la lavandería, y solo con el pago acreditado (NSV-2026-001-v8.pdf:20:ANEXO A,
     * Sección A.3; C-188). Toda consulta y toda acción de la lavandería sobre sus pedidos parte de aquí: uno de otra
     * lavandería, de otra plaza o sin pago responde igual que uno que no existe (ADR-0019, decisión 3).
     *
     * Una lavandería borrada en suave ya no tiene pedidos que atender. El estado de la lavandería (pendiente,
     * suspendida, de baja) no se mira aquí: qué puede hacer cada una lo fija alta-de-lavanderias (autorizacion-E05).
     *
     * @return Builder<Pedido>
     */
    public function pedidosDeLaLavanderia(Usuario $usuario): Builder
    {
        return Pedido::query()
            ->confirmados()
            ->whereExists(fn ($lavanderias) => $lavanderias->selectRaw('1')
                ->from('lavanderias')
                ->whereColumn('lavanderias.id_lavanderias', 'pedidos.id_lavanderias')
                ->whereColumn('lavanderias.id_plazas', 'pedidos.id_plazas')
                ->where('lavanderias.id_usuarios', $usuario->getKey())
                ->whereNull('lavanderias.deleted_at'));
    }

    /**
     * Los pedidos que ve y responde el repartidor que opera: los que se le ofrecieron y todavía no rechazó, o que aceptó,
     * de su misma plaza, con el pago acreditado y que no tomó otro repartidor (NSV-2026-001-v8.pdf:18:ANEXO A, Sección
     * A.3, Módulo C #2; :20, Separación por tipo de usuario; C-188). Toda consulta y toda acción del repartidor sobre sus
     * pedidos parte de aquí: uno de otro repartidor, de otra plaza, que rechazó o sin pago responde igual que uno que no
     * existe (repartidor-E12, E16 y E30; ADR-0019, decisión 3).
     *
     * Recibe el repartidor que ya dio repartidorQueOpera(); uno que no opera no ve ninguno (repartidor-E03 y E06).
     *
     * @return Builder<Pedido>
     */
    public function pedidosDelRepartidor(Repartidor $repartidor): Builder
    {
        $consulta = Pedido::query()->confirmados();

        if (! $this->repartidorOpera($repartidor)) {
            return $consulta->whereRaw('false');
        }

        return $consulta
            ->where('pedidos.id_plazas', $repartidor->id_plazas)
            ->where(fn (Builder $suyo) => $suyo
                ->whereNull('pedidos.id_repartidores')
                ->orWhere('pedidos.id_repartidores', $repartidor->getKey()))
            ->whereExists(fn ($asignaciones) => $asignaciones->selectRaw('1')
                ->from('pedido_asignaciones')
                ->whereColumn('pedido_asignaciones.id_pedidos', 'pedidos.id_pedidos')
                ->whereColumn('pedido_asignaciones.id_plazas', 'pedidos.id_plazas')
                ->where('pedido_asignaciones.id_repartidores', $repartidor->getKey())
                ->whereIn('pedido_asignaciones.estado', [PedidoAsignacion::OFRECIDO, PedidoAsignacion::ACEPTADO]));
    }

    /**
     * Las entregas completadas del repartidor que opera: sus pedidos (pedidosDelRepartidor()) cuyo paso a Entregado marcó
     * él (openspec/changes/repartidor/design.md, lectura 5: "una entrega es completada cuando el repartidor marca
     * Entregado"). Su historial y sus estadísticas parten de aquí (repartidor-E25 y E28); la entrega de otro repartidor no
     * aparece (NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario).
     *
     * @return Builder<Pedido>
     */
    public function entregasDelRepartidor(Repartidor $repartidor): Builder
    {
        return $this->pedidosDelRepartidor($repartidor)
            ->where('pedidos.id_repartidores', $repartidor->getKey())
            ->whereExists(fn ($eventos) => $eventos->selectRaw('1')
                ->from('pedido_eventos')
                ->join('estados_pedido', 'estados_pedido.id_estados_pedido', '=', 'pedido_eventos.id_estados_pedido')
                ->whereColumn('pedido_eventos.id_pedidos', 'pedidos.id_pedidos')
                ->where('estados_pedido.codigo', EstadoPedido::ENTREGADO)
                ->where('pedido_eventos.id_usuarios', $repartidor->id_usuarios));
    }

    /**
     * Los teléfonos que reciben los avisos de un pedido: solo los de la cuenta que lo hizo (avisos-E04 y avisos-E12;
     * NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3). Todo envío de un aviso del pedido parte de aquí, también al correr
     * en la cola. Los avisos a la lavandería y al repartidor esperan DD-054 (avisos-E19 y avisos-E20).
     *
     * @return Builder<Dispositivo>
     */
    public function telefonosQueRecibenAvisosDe(Pedido $pedido): Builder
    {
        return Dispositivo::query()->where('id_usuarios', $pedido->id_usuarios);
    }

    /**
     * Las lavanderías que la cuenta del socio administra y puede ver y editar: solo las suyas, en su plaza
     * (alta-lavanderias-E20; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, "un socio lavandería no puede consultar datos
     * de otro socio"). Toda consulta de la lavandería desde el Módulo B parte de aquí; una ajena no aparece, y la ruta
     * responde 404 como si no existiera (ADR-0019, decisión 3).
     *
     * @return Builder<Lavanderia>
     */
    public function lavanderiasDeLaCuenta(Usuario $usuario): Builder
    {
        return Lavanderia::query()->where('id_usuarios', $usuario->getKey());
    }

    /**
     * Las lavanderías que la cuenta ve y gestiona en el panel: todas, de todas las plazas, si es de administración
     * (autorizacion-E03 y E13; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #1); ninguna si no lo es. El
     * recurso de lavanderías del panel parte de aquí, además de que el panel ya no deja entrar a otra cuenta.
     *
     * @return Builder<Lavanderia>
     */
    public function lavanderiasDelPanel(Usuario $usuario): Builder
    {
        $consulta = Lavanderia::query();

        return $this->puedeEntrarAlPanel($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Los repartidores que la cuenta ve y gestiona en el panel: todos, de todas las plazas y en cualquier estado, también
     * los pendientes sin plaza y los de baja, si es de administración (repartidor-E05 a E09; NSV-2026-001-v8.pdf:19:ANEXO
     * A, Sección A.3, Módulo D #2); ninguno si no lo es. El recurso de repartidores del panel parte de aquí.
     *
     * @return Builder<Repartidor>
     */
    public function repartidoresDelPanel(Usuario $usuario): Builder
    {
        $consulta = Repartidor::query()->withTrashed();

        return $this->puedeEntrarAlPanel($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Las plazas que la cuenta ve en el panel, para dar de alta a un repartidor en una de ellas: todas, si es de
     * administración (repartidor-E05; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #2); ninguna si no lo es.
     *
     * @return Builder<Plaza>
     */
    public function plazasDelPanel(Usuario $usuario): Builder
    {
        $consulta = Plaza::query();

        return $this->puedeEntrarAlPanel($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Si la cuenta fija el porcentaje de comisión global, el que aplica a toda lavandería sin porcentaje propio, de
     * cualquier plaza (administracion-E11; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #5, "por socio o
     * global"): solo la administración, que es una para todas las plazas. El contrato no nombra una administración por
     * plaza (openspec/changes/autorizacion-por-rol/design.md, Non-Goals): si llegara, por Orden de Cambio, esta regla es
     * la que le niega el global. La página y su acción preguntan aquí.
     */
    public function puedeFijarLaComisionGlobal(Usuario $usuario): bool
    {
        return $this->puedeEntrarAlPanel($usuario);
    }

    /**
     * Las cuentas de usuario que la cuenta ve en la gestión de usuarios del panel: todas las que tienen el rol de usuario,
     * si es de administración (administracion-E01; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3); ninguna si
     * no lo es. Las de los socios se gestionan con su lavandería y su repartidor (Módulo D #1 y #2), y las de la
     * administración no se listan.
     *
     * @return Builder<Usuario>
     */
    public function usuariosDelPanel(Usuario $usuario): Builder
    {
        $consulta = Usuario::query()->whereHas('roles', fn (Builder $roles) => $roles->where('codigo', Rol::USUARIO));

        return $this->puedeEntrarAlPanel($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Los pedidos que la cuenta ve en el panel: todos los confirmados, de todas las plazas, si es de administración
     * (administracion-E01 y E06; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #3 y #4); ninguno si no lo es. Uno
     * que espera su pago no entró al flujo y no tiene folio (C-188). El historial de la ficha del usuario y el monitoreo
     * parten de aquí.
     *
     * @return Builder<Pedido>
     */
    public function pedidosDelPanel(Usuario $usuario): Builder
    {
        $consulta = Pedido::query()->confirmados();

        return $this->puedeEntrarAlPanel($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Los pedidos activos que la cuenta ve en el monitoreo del panel: los de todas las plazas, si es de administración
     * (administracion-E06; NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #4); ninguno si no lo es. Cuáles están
     * activos lo dice el modelo del pedido (Pedido::activosDelMonitoreo()).
     *
     * @return Builder<Pedido>
     */
    public function pedidosActivosDelPanel(Usuario $usuario): Builder
    {
        return $this->pedidosDelPanel($usuario)->activosDelMonitoreo();
    }

    /**
     * Los reportes de Soporte que la cuenta ve: todos, de todas las plazas y también los que no tienen plaza, si es de
     * administración, que es una para todas las plazas; ninguno si no lo es, tampoco a un socio de una plaza
     * (perfil-usuario-E17; NSV-2026-001-v8.pdf:20:ANEXO A, Sección A.3, Separación por tipo de usuario). A quién le llega
     * cada reporte y cómo se atiende es DD-108 (perfil-usuario-E14): ninguna pantalla los lista todavía, y la que llegue
     * con esa respuesta parte de aquí.
     *
     * @return Builder<ReporteSoporte>
     */
    public function reportesDeSoporteDelPanel(Usuario $usuario): Builder
    {
        $consulta = ReporteSoporte::query();

        return $this->puedeEntrarAlPanel($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Si la cuenta edita el contenido de la portada que vive en el panel, los testimonios y su interruptor de activo:
     * solo la administración (landing-E19; NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, sección 8; ADR-0011). Ese
     * contenido no es de una plaza: la portada es una para todo el sitio, así que no hay tenencia ni plaza que mirar.
     */
    public function puedeEditarLaPortada(Usuario $usuario): bool
    {
        return $this->puedeEntrarAlPanel($usuario);
    }

    /**
     * Los testimonios que la cuenta ve y edita en el panel: todos, si puede editar la portada (landing-E15 y E19);
     * ninguno si no. El recurso de testimonios del panel parte de aquí.
     *
     * @return Builder<Testimonio>
     */
    public function testimoniosDelPanel(Usuario $usuario): Builder
    {
        $consulta = Testimonio::query();

        return $this->puedeEditarLaPortada($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Los teléfonos que la cuenta puede borrar: solo los suyos (avisos-E05). Al cerrar la sesión, el token de otra
     * cuenta no se toca aunque la app lo mande.
     *
     * @return Builder<Dispositivo>
     */
    public function telefonosDeLaCuenta(Usuario $usuario): Builder
    {
        return Dispositivo::query()->where('id_usuarios', $usuario->getKey());
    }

    /**
     * Si la cuenta descarga los reportes del panel en CSV y en Excel: solo la administración, porque son los indicadores
     * y los reportes financieros que ve en el panel (NSV-2026-001-v8.pdf:22:ANEXO A, Sección A.5; exportacion-E01). El
     * botón de descarga y el trabajo de la cola que escribe el archivo preguntan aquí.
     */
    public function puedeDescargarReportes(Usuario $usuario): bool
    {
        return $this->puedeVerLosIndicadores($usuario);
    }

    /**
     * Si la cuenta baja el archivo de una descarga: solo la que la pidió, mientras siga pudiendo bajar reportes
     * (exportacion-E11). Para cualquier otra, el archivo responde como si no existiera (ADR-0019, decisión 3). La ruta
     * de descarga de Filament pregunta aquí a través de App\Exportacion\PoliticaDeExportacion.
     */
    public function puedeDescargarLaExportacion(Usuario $usuario, Exportacion $exportacion): bool
    {
        return (int) $exportacion->id_usuarios === (int) $usuario->getKey() && $this->puedeDescargarReportes($usuario);
    }

    /**
     * Si la cuenta escribe y manda avisos masivos desde el panel: solo la administración (administracion-E23;
     * NSV-2026-001-v8.pdf:19:ANEXO A, Sección A.3, Módulo D #7).
     */
    public function puedeMandarAvisosMasivos(Usuario $usuario): bool
    {
        return $this->puedeEntrarAlPanel($usuario);
    }

    /**
     * Los avisos masivos que la cuenta ve en el panel: todos, si los puede mandar; ninguno si no. La lista de avisos del
     * panel parte de aquí.
     *
     * @return Builder<AvisoMasivo>
     */
    public function avisosMasivosDelPanel(Usuario $usuario): Builder
    {
        $consulta = AvisoMasivo::query();

        return $this->puedeMandarAvisosMasivos($usuario) ? $consulta : $consulta->whereRaw('false');
    }

    /**
     * Los teléfonos que reciben un aviso masivo (administracion-E23; ADR-0008). Todo envío del aviso parte de aquí,
     * también al correr en la cola.
     *
     * - El aviso global, sin plaza ni segmento, va a los teléfonos registrados de todas las cuentas de la app que pueden
     *   usarla: no a una bloqueada desde el panel ni a la de un repartidor dado de baja (puedeIniciarSesion()), ni a una
     *   borrada. Una cuenta sin teléfono no tiene a dónde recibirlo.
     * - Un aviso con plaza o con segmento no va a ningún teléfono mientras DD-113 no defina los segmentos
     *   (administracion-E24).
     *
     * @return Builder<Dispositivo>
     */
    public function telefonosQueRecibenElAvisoMasivo(AvisoMasivo $aviso): Builder
    {
        $consulta = Dispositivo::query();

        if ($aviso->id_plazas !== null || $aviso->segmento !== null) {
            return $consulta->whereRaw('false');
        }

        return $consulta
            ->whereHas('usuario', fn (Builder $cuenta) => $cuenta->where('usuarios.estado', '<>', Usuario::BLOQUEADO))
            ->whereNotExists(fn ($deBaja) => $deBaja->selectRaw('1')
                ->from('repartidores')
                ->whereColumn('repartidores.id_usuarios', 'dispositivos.id_usuarios')
                ->where('repartidores.estado', Repartidor::BAJA));
    }
}
