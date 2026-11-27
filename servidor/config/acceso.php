<?php

/*
|--------------------------------------------------------------------------
| Acceso: la contraseña, el límite de intentos y el vencimiento del token de la administración
|--------------------------------------------------------------------------
|
| Los valores que decidió Arthur en ADR-0018 (C-223), y el vencimiento del token de la administración de ADR-0022.
| Viven en configuración para ajustarse sin código nuevo si una revisión de seguridad lo pide.
|
*/

return [

    /*
     * El mínimo de caracteres de una contraseña (ADR-0018, decisión 2). Lo aplica App\Validacion\Contrasena, la regla
     * del registro y de la contraseña nueva.
     */
    'contrasena_minima' => (int) env('ACCESO_CONTRASENA_MINIMA', 8),

    /*
     * Los intentos por minuto, por correo y por dirección (ADR-0018, decisión 3). Lo aplican el limitador
     * inicio-de-sesion de App\Providers\AppServiceProvider en POST /api/sesion (registro-E30) y el limitador
     * recuperacion de App\Providers\RecuperacionServiceProvider en POST /api/contrasena/olvido (registro-E31), cada
     * uno con su contador.
     */
    'intentos_por_minuto' => (int) env('ACCESO_INTENTOS_POR_MINUTO', 5),

    /*
     * Los días que dura el token de la app de una cuenta de administración, contados desde que abre la sesión; los de los
     * demás roles no vencen. Lo aplica App\Identidad\TokensDeLaApp, con el expires_at que Sanctum ya revisa
     * (administracion-app-E01 y E05). La cifra es técnica, de ADR-0022 (decisión 2): no viene del contrato ni del
     * negocio. La variable vacía, como en .env.example, o en cero, también son 30: un token que nace vencido no abre
     * ninguna sesión.
     */
    'dias_del_token_de_administracion' => (int) (env('ACCESO_DIAS_DEL_TOKEN_DE_ADMINISTRACION') ?: 30),

];
