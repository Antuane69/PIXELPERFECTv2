<?php

namespace App\Http\Controllers\Vacaciones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Vacaciones\StoreDiaFestivoRequest;
use App\Http\Requests\Vacaciones\UpdateDiaFestivoRequest;
use App\Models\DiaFestivo;
use App\Services\Empresas\EmpresaContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class DiaFestivoController extends Controller
{
    public function __construct(private readonly EmpresaContext $empresaContext) {}

    public function store(StoreDiaFestivoRequest $request): RedirectResponse
    {
        $empresa = $this->empresaContext->empresaRequerida();
        Gate::authorize('create', DiaFestivo::class);
        $empresa->diasFestivos()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Día festivo agregado al catálogo.']);

        return to_route('empresas.vacaciones.index');
    }

    public function update(UpdateDiaFestivoRequest $request, DiaFestivo $diaFestivo): RedirectResponse
    {
        Gate::authorize('update', $diaFestivo);
        $diaFestivo->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Día festivo actualizado.']);

        return to_route('empresas.vacaciones.index');
    }

    public function destroy(DiaFestivo $diaFestivo): RedirectResponse
    {
        Gate::authorize('delete', $diaFestivo);
        $diaFestivo->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Día festivo eliminado del catálogo.']);

        return to_route('empresas.vacaciones.index');
    }
}
