<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadEmpleadoDocumentoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PuestoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShowEmpleadoAvatarController;
use App\Http\Controllers\TipoDocumentoEmpleadoController;
use App\Http\Controllers\UserController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

Route::get('/', static function (): Response|RedirectResponse {
    if (auth()->check()) {
        return to_route('dashboard');
    }

    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::post('reportes/{reporte}/exportar', [ExportController::class, 'exportar'])
        ->name('reportes.exportar');

    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('roles', RoleController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch('puestos/{puesto}/restaurar', [PuestoController::class, 'restore'])
        ->withTrashed()
        ->name('puestos.restore');
    Route::resource('puestos', PuestoController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::patch(
        'tipos-documento-empleados/{tipoDocumentoEmpleado}/restaurar',
        [TipoDocumentoEmpleadoController::class, 'restore'],
    )->withTrashed()->name('tipos-documento-empleados.restore');
    Route::resource('tipos-documento-empleados', TipoDocumentoEmpleadoController::class)
        ->parameters(['tipos-documento-empleados' => 'tipoDocumentoEmpleado'])
        ->only(['index', 'store', 'update', 'destroy']);
    Route::patch('empleados/{empleado}/restaurar', [EmpleadoController::class, 'restore'])
        ->withTrashed()
        ->name('empleados.restore');
    Route::resource('empleados', EmpleadoController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::get('empleados/{empleado}/avatar', ShowEmpleadoAvatarController::class)
        ->name('empleados.avatar');

    Route::get(
        'empleados/{empleado}/documentos/{documento}/download',
        DownloadEmpleadoDocumentoController::class,
    )->scopeBindings()->name('empleados.documentos.download');
});

require __DIR__.'/settings.php';
