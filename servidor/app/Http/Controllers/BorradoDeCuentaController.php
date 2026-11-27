<?php

namespace App\Http\Controllers;

use App\Landing\MediosDeContacto;
use Illuminate\Contracts\View\View;

/**
 * La página pública para pedir el borrado de la cuenta, en su dirección fija y sin iniciar sesión
 * (NSV-2026-001-v8.pdf:21:ANEXO A, Sección A.4.3; adenda del 2026-11-24 de ADR-0005). Es el recurso web que pide Google
 * Play además del borrado desde la app (grado E, consultado el 2026-11-24).
 *
 *     GET /borrado-de-cuenta     cómo pedir el borrado de la cuenta     (textos-legales.borrado-de-cuenta)
 *
 * Responde 200. Dice cómo borrar la cuenta desde la app, con las etiquetas de "Mi perfil" (perfil-usuario-E15), y cómo
 * pedirlo por el correo de contacto y el WhatsApp de soporte de la configuración (App\Landing\MediosDeContacto, los
 * mismos de "Contacto & Soporte" de la portada). Sin uno de los dos, en producción no lo muestra y no pone ninguno de
 * ejemplo (textos-legales-E07); fuera de producción pone un marcador de contenido de prueba, como la portada.
 *
 * Solo informa: no tiene formulario, no abre sesión y no borra nada. No dice qué datos se borran ni cuáles se conservan,
 * que es de EL CLIENTE (DD-109). No es el borrado de la API, App\Http\Controllers\Api\BorradoDeCuentaController.
 *
 * Escenarios: textos-legales-E06 y textos-legales-E07 · Spec: openspec/changes/textos-legales/specs/textos-legales/spec.md
 * · Tarea 1.4
 */
class BorradoDeCuentaController extends Controller
{
    public function __invoke(MediosDeContacto $medios): View
    {
        return view('publico.borrado-de-cuenta', [
            // La página, para su descripción para buscadores (medicion-E07).
            'pagina' => 'borrado-de-cuenta',
            'correo' => $medios->correo(),
            'whatsapp' => $medios->whatsapp(),
            'enProduccion' => app()->isProduction(),
        ]);
    }
}
