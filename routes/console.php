<?php

use App\Jobs\Incapacidades\VerificarIncapacidadesVencidas;
use App\Jobs\PermisosLaborales\VerificarPermisosLaboralesVencidos;
use App\Jobs\VerificarVacacionesVencidas;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new VerificarVacacionesVencidas)->everyThirtyMinutes()->withoutOverlapping();
Schedule::job(new VerificarPermisosLaboralesVencidos)->everyThirtyMinutes()->withoutOverlapping();
Schedule::job(new VerificarIncapacidadesVencidas)->everyThirtyMinutes()->withoutOverlapping();
