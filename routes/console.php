<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Verificación periódica de cumplimiento de SLA. Se ejecuta cada hora para
// detectar y escalar tickets cuyo tiempo de atención excedió el umbral de su
// prioridad, y dispara notificaciones automáticas a supervisores y agentes.
Schedule::command('tickets:check-slas')->hourly();
