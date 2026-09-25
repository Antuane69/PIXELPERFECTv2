<?php

use App\Http\Controllers\Admin\EmpresaController as AdminEmpresaController;
use App\Http\Controllers\Admin\EmpresaModuloController;
use App\Http\Controllers\Admin\ModuloController as AdminModuloController;
use App\Http\Controllers\Admin\PermissionController as AdminPermissionController;
use App\Http\Controllers\Admin\PlanController as AdminPlanController;
use App\Http\Controllers\Admin\PlatformUserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DownloadEmpleadoDocumentoController;
use App\Http\Controllers\EmpleadoCarpetaController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\EmpleadoDocumentoCatalogoController;
use App\Http\Controllers\EmpleadoDocumentoImpresionController;
use App\Http\Controllers\EmpresaContextController;
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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

Route::get('/', static fn (): RedirectResponse => to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('seleccionar-empresa', [EmpresaContextController::class, 'create'])
        ->name('empresa-contexto.create');
    Route::post('seleccionar-empresa', [EmpresaContextController::class, 'store'])
        ->name('empresa-contexto.store');
    Route::delete('seleccionar-empresa', [EmpresaContextController::class, 'destroy'])
        ->name('empresa-contexto.destroy');

    Route::prefix('admin')->name('platform.')->middleware('platform.admin')->group(function (): void {
        Route::patch('planes/{plan}/restaurar', [AdminPlanController::class, 'restore'])
            ->withTrashed()
            ->name('planes.restore');
        Route::resource('planes', AdminPlanController::class)
            ->parameters(['planes' => 'plan'])
            ->only(['index', 'store', 'update', 'destroy']);
        Route::resource('empresas', AdminEmpresaController::class)->only(['index', 'store']);
        Route::put('empresas/{empresa:id}/modulos', EmpresaModuloController::class)
            ->name('empresas.modulos.update');
        Route::get('usuarios', PlatformUserController::class)->name('usuarios.index');
        Route::post('usuarios', [PlatformUserController::class, 'store'])->name('usuarios.store');
        Route::put('usuarios/{user}', [PlatformUserController::class, 'update'])->name('usuarios.update');
        Route::delete('usuarios/{user}', [PlatformUserController::class, 'destroy'])->name('usuarios.destroy');
        Route::resource('modulos', AdminModuloController::class)
            ->only(['index', 'store', 'update', 'destroy']);
        Route::resource('permisos', AdminPermissionController::class)
            ->parameters(['permisos' => 'permission'])
            ->only(['index', 'store', 'update', 'destroy']);
    });

    Route::name('empresas.')
        ->middleware('empresa.activa')
        ->group(function (): void {
            Route::get('inicio', EmpresaDashboardController::class)->name('inicio');
            Route::scopeBindings()->group(function (): void {
                Route::middleware('modulo.habilitado:usuarios')->group(function (): void {
                    Route::resource('usuarios', UserController::class)
                        ->parameters(['usuarios' => 'user'])
                        ->names('users')
                        ->only(['index', 'store', 'update', 'destroy']);
                    Route::patch(
                        'usuarios/{user}/two-factor',
                        [UserController::class, 'updateTwoFactor'],
                    )->name('users.two-factor');
                    Route::post(
                        'usuarios/{user}/password-reset',
                        [UserController::class, 'sendPasswordReset'],
                    )->name('users.password-reset');
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
                    Route::patch(
                        'empleados/catalogos/documentos/{empleadoDocumentoCatalogo}/restaurar',
                        [EmpleadoDocumentoCatalogoController::class, 'restore'],
                    )->withTrashed()->name('empleados.documentos-catalogo.restore');
                    Route::get(
                        'empleados/catalogos/documentos/{empleadoDocumentoCatalogo}/descargar',
                        [EmpleadoDocumentoCatalogoController::class, 'download'],
                    )->name('empleados.documentos-catalogo.download');
                    Route::get(
                        'empleados/catalogos/documentos/{empleadoDocumentoCatalogo}',
                        [EmpleadoDocumentoCatalogoController::class, 'show'],
                    )->name('empleados.documentos-catalogo.show');
                    Route::post(
                        'empleados/catalogos/documentos/previsualizar',
                        [EmpleadoDocumentoCatalogoController::class, 'preview'],
                    )->name('empleados.documentos-catalogo.preview');
                    Route::post(
                        'empleados/catalogos/documentos/consultar',
                        [EmpleadoDocumentoCatalogoController::class, 'listDocuments'],
                    )->name('empleados.documentos-catalogo.list');
                    Route::get(
                        'empleados/catalogos/documentos',
                        [EmpleadoDocumentoCatalogoController::class, 'index'],
                    )->name('empleados.documentos-catalogo.index');
                    Route::post(
                        'empleados/catalogos/documentos',
                        [EmpleadoDocumentoCatalogoController::class, 'store'],
                    )->name('empleados.documentos-catalogo.store');
                    Route::put(
                        'empleados/catalogos/documentos/{empleadoDocumentoCatalogo}',
                        [EmpleadoDocumentoCatalogoController::class, 'update'],
                    )->name('empleados.documentos-catalogo.update');
                    Route::delete(
                        'empleados/catalogos/documentos/{empleadoDocumentoCatalogo}',
                        [EmpleadoDocumentoCatalogoController::class, 'destroy'],
                    )->name('empleados.documentos-catalogo.destroy');
                    Route::get(
                        'empleados/{empleado}/imprimir-documentos',
                        [EmpleadoDocumentoImpresionController::class, 'seleccionar'],
                    )->name('empleados.documentos-catalogo.seleccionar');
                    Route::get(
                        'empleados/{empleado}/imprimir-documentos/descargar',
                        [EmpleadoDocumentoImpresionController::class, 'descargar'],
                    )->name('empleados.documentos-catalogo.imprimir');
                    Route::patch(
                        'empleados/carpetas/{empleadoCarpeta}/restaurar',
                        [EmpleadoCarpetaController::class, 'restore'],
                    )->withTrashed()->name('empleados.carpetas.restore');
                    Route::get(
                        'empleados/carpetas',
                        [EmpleadoCarpetaController::class, 'index'],
                    )->name('empleados.carpetas.index');
                    Route::post(
                        'empleados/carpetas',
                        [EmpleadoCarpetaController::class, 'store'],
                    )->name('empleados.carpetas.store');
                    Route::put(
                        'empleados/carpetas/{empleadoCarpeta}',
                        [EmpleadoCarpetaController::class, 'update'],
                    )->name('empleados.carpetas.update');
                    Route::delete(
                        'empleados/carpetas/{empleadoCarpeta}',
                        [EmpleadoCarpetaController::class, 'destroy'],
                    )->name('empleados.carpetas.destroy');
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
                    Route::post(
                        'reportes/tipos-documento-empleados/exportar',
                        [ExportController::class, 'exportarTiposDocumentoEmpleado'],
                    )->name('reportes.tipos-documento-empleados.exportar');
                    Route::patch(
                        'documentos/{tipoDocumentoEmpleado}/restaurar',
                        [TipoDocumentoEmpleadoController::class, 'restore'],
                    )->withTrashed()->name('tipos-documento-empleados.restore');
                    Route::resource('documentos', TipoDocumentoEmpleadoController::class)
                        ->parameters(['documentos' => 'tipoDocumentoEmpleado'])
                        ->names('tipos-documento-empleados')
                        ->only(['index', 'store', 'update', 'destroy']);
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

    Route::middleware('platform.admin')->group(function (): void {
        Route::get('logs', static fn (): SymfonyResponse => Inertia::location(route('log-viewer.index')))
            ->name('logs.index');
    });

    Route::middleware('empresa.activa')->group(function (): void {
        Route::post('reportes/{reporte}/exportar', [ExportController::class, 'exportar'])
            ->name('reportes.exportar');
    });
});

require __DIR__.'/settings.php';
