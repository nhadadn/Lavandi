<?php

namespace App\Http\Controllers;

use App\Http\Requests\FormularioDeContactoRequest;
use App\Http\Requests\FormularioDeInteresRequest;
use App\Http\Requests\FormularioDeLaPortadaRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Los formularios de la portada: el de interés de lavanderías, de "Lavanderías partner", y el de contacto, de
 * "Contacto & Soporte" (NSV-2026-001-v8.pdf:15:ANEXO A, Sección A.2, secciones 5 y 11).
 *
 * Un envío incompleto o con el correo mal escrito lo rechaza su validación, que vuelve a la sección del formulario con
 * el error junto a cada campo (landing-E21).
 *
 * Un envío completo todavía no tiene a dónde llegar: el destino de cada formulario, sus campos y el aviso de datos
 * personales que lo acompaña son DD-119, que abre BR-41, y bloquean landing-E22 (tarea 5.2). Mientras tanto no se
 * entrega, no se guarda ni se registra en la bitácora, y la portada le dice a quien lo envió que no se entregó, en vez
 * de una confirmación que no sería cierta.
 *
 * Escenarios: landing-E10, landing-E21 y landing-E23 · Spec: openspec/changes/landing/specs/landing/spec.md · Tareas
 * 2.4, 5.1 y 5.3
 */
class FormularioDeLaPortadaController extends Controller
{
    /**
     * POST /formularios/interes: el formulario de interés de lavanderías.
     */
    public function interes(FormularioDeInteresRequest $request): RedirectResponse
    {
        return $this->sinDestino($request);
    }

    /**
     * POST /formularios/contacto: el formulario de contacto.
     */
    public function contacto(FormularioDeContactoRequest $request): RedirectResponse
    {
        return $this->sinDestino($request);
    }

    /**
     * Un envío completo, mientras DD-119 no diga su destino: vuelve a su sección con el aviso de que no se entregó.
     *
     * Con formulario_enviado, la portada de vuelta pone en la medición el evento del formulario, con solo su nombre
     * (medicion-E05; resources/views/publico/medicion.blade.php). Un envío rechazado no llega aquí, así que no lo pone.
     */
    private function sinDestino(FormularioDeLaPortadaRequest $request): RedirectResponse
    {
        return redirect()
            ->to(route('portada').'#'.$request::seccion())
            ->with('envio_sin_destino', $request::formulario())
            ->with('formulario_enviado', $request::formulario());
    }
}
