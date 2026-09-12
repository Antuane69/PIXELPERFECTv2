<?php

use App\Http\Controllers\Admin\EmpresaController as AdminEmpresaController;
use App\Http\Controllers\Admin\EmpresaModuloController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadEmpleadoDocumentoController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\EmpresaDashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\PuestoController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShowEmpleadoAvatarController;
use App\Http\Controllers\ShowEmpleadoDocumentoController;
use App\Http\Controllers\TipoDocumentoEmpleadoController;
use App\Http\Controllers\UserController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

Route::get('/', static function (): Response|RedirectResponse {
    if (auth()->check()) {
        return to_route('dashboard');
    }

    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::prefix('admin')->name('platform.')->group(function (): void {
        Route::patch('planes/{plan}/restaurar', [AdminPlanController::class, 'restore'])
            ->withTrashed()
            ->name('planes.restore');
        Route::resource('planes', AdminPlanController::class)
            ->parameters(['planes' => 'plan'])
            ->only(['index', 'store', 'update', 'destroy']);
        Route::resource('empresas', AdminEmpresaController::class)->only(['index', 'store']);
        Route::put('empresas/{empresa:slug}/modulos', EmpresaModuloController::class)
            ->name('empresas.modulos.update');
        Route::post(
            'reportes/tipos-documento-empleados/exportar',
            [ExportController::class, 'exportarTiposDocumentoEmpleado'],
        )->name('reportes.tipos-documento-empleados.exportar');
        Route::patch(
            'tipos-documento-empleados/{tipoDocumentoEmpleado}/restaurar',
            [TipoDocumentoEmpleadoController::class, 'restore'],
        )->withTrashed()->name('tipos-documento-empleados.restore');
        Route::resource('tipos-documento-empleados', TipoDocumentoEmpleadoController::class)
            ->parameters(['tipos-documento-empleados' => 'tipoDocumentoEmpleado'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::prefix('app/{empresa:slug}')
        ->name('empresas.')
        ->middleware('empresa.activa')
        ->group(function (): void {
            Route::get('/', EmpresaDashboardController::class)->name('inicio');
            Route::scopeBindings()->group(function (): void {
                Route::middleware('modulo.habilitado:usuarios')->group(function (): void {
                    Route::resource('users', UserController::class)
                        ->only(['index', 'store', 'update', 'destroy']);
                });
                Route::middleware('modulo.habilitado:roles')->group(function (): void {
                    Route::resource('roles', RoleController::class)
                        ->only(['index', 'store', 'update', 'destroy']);
                });
                Route::middleware('modulo.habilitado:puestos')->group(function (): void {
                    Route::patch('puestos/{puesto}/restaurar', [PuestoController::class, 'restore'])
                        ->withTrashed()
                        ->name('puestos.restore');
                    Route::resource('puestos', PuestoController::class)
                        ->only(['index', 'store', 'update', 'destroy']);
                });
                Route::middleware('modulo.habilitado:empleados')->group(function (): void {
                    Route::patch('empleados/{empleado}/restaurar', [EmpleadoController::class, 'restore'])
                        ->withTrashed()
                        ->name('empleados.restore');
                    Route::resource('empleados', EmpleadoController::class)
                        ->only(['index', 'store', 'update', 'destroy']);
                    Route::get('empleados/{empleado}/avatar', ShowEmpleadoAvatarController::class)
                        ->name('empleados.avatar');
                    Route::get(
                        'empleados/{empleado}/documentos/{documento}/preview',
                        ShowEmpleadoDocumentoController::class,
                    )->name('empleados.documentos.preview');
                    Route::get(
                        'empleados/{empleado}/documentos/{documento}/download',
                        DownloadEmpleadoDocumentoController::class,
                    )->name('empleados.documentos.download');
                });
            });

            Route::middleware('modulo.habilitado:puestos')
                ->post('reportes/puestos/exportar', [ExportController::class, 'exportarPuestos'])
                ->name('reportes.puestos.exportar');
            Route::middleware('modulo.habilitado:usuarios')
                ->post('reportes/usuarios/exportar', [ExportController::class, 'exportarUsuarios'])
                ->name('reportes.usuarios.exportar');
            Route::middleware('modulo.habilitado:roles')
                ->post('reportes/roles/exportar', [ExportController::class, 'exportarRoles'])
                ->name('reportes.roles.exportar');
            Route::middleware('modulo.habilitado:empleados')
                ->post('reportes/empleados/exportar', [ExportController::class, 'exportarEmpleados'])
                ->name('reportes.empleados.exportar');
        });

    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('logs', static fn (): SymfonyResponse => Inertia::location(route('log-viewer.index')))
        ->name('logs.index');

    Route::middleware('empresa.inicial')->group(function (): void {
        Route::post('reportes/{reporte}/exportar', [ExportController::class, 'exportar'])
            ->name('reportes.exportar');
    });
});

require __DIR__.'/settings.php';
