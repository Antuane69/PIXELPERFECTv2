<?php

namespace App\Http\Controllers;

use App\Models\TipoDocumentoEmpleado;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the management dashboard.
     */
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'users' => Gate::allows('viewAny', User::class)
                    ? User::query()->count()
                    : null,
                'tiposDocumentoActivos' => Gate::allows('viewAny', TipoDocumentoEmpleado::class)
                    ? TipoDocumentoEmpleado::query()->where('activo', true)->count()
                    : null,
            ],
        ]);
    }
}
