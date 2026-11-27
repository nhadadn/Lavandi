<?php

use App\Exportacion\Exportacion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Las descargas del panel se borran con sus archivos después de sus días de vida (config/exportacion.php;
// openspec/changes/exportacion/design.md, pregunta abierta 2).
Schedule::command('model:prune', ['--model' => [Exportacion::class]])->daily();
