<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Planes\StorePlanRequest;
use App\Http\Requests\Admin\Planes\UpdatePlanRequest;
use App\IconoPlan;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    private const INDEX_QUERY_PARAMETERS = [
        'search',
        'activo',
        'archivados',
        'per_page',
        'page',
    ];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Plan::class);

        $search = $request->string('search')->squish()->toString();
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $archivados = $request->boolean('archivados');

        $planes = Plan::query()
            ->select([
                'id',
                'nombre',
                'precio_mensual',
                'color',
                'icono',
                'modulos_incluidos',
                'limite_usuarios',
                'periodo_gracia_dias',
                'activo',
                'deleted_at',
            ])
            ->when($archivados, fn (Builder $query) => $query->onlyTrashed())
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $searchQuery) => $searchQuery
                    ->where('nombre', 'like', "%{$search}%")
                    ->orWhere('modulos_incluidos', 'like', "%{$search}%"),
            ))
            ->when(
                $request->has('activo'),
                fn (Builder $query) => $query->where('activo', $request->boolean('activo')),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString()
            ->through(static fn (Plan $plan): array => [
                'id' => $plan->id,
                'nombre' => $plan->nombre,
                'precio_mensual' => $plan->precio_mensual,
                'color' => $plan->color,
                'icono' => $plan->icono->value,
                'modulos_incluidos' => $plan->modulos_incluidos,
                'limite_usuarios' => $plan->limite_usuarios,
                'periodo_gracia_dias' => $plan->periodo_gracia_dias,
                'activo' => $plan->activo,
                'deleted_at' => $plan->deleted_at?->toISOString(),
            ]);

        return Inertia::render('admin/planes/index', [
            'planes' => $planes,
            'iconos' => IconoPlan::values(),
            'filters' => [
                'search' => $search,
                'activo' => $request->has('activo') ? $request->boolean('activo') : null,
                'archivados' => $archivados,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function store(StorePlanRequest $request): RedirectResponse
    {
        Plan::query()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan creado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'platform.planes.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function update(UpdatePlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan actualizado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'platform.planes.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function destroy(Request $request, Plan $plan): RedirectResponse
    {
        Gate::authorize('delete', $plan);
        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan archivado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'platform.planes.index',
            self::INDEX_QUERY_PARAMETERS,
        );
    }

    public function restore(Request $request, Plan $plan): RedirectResponse
    {
        Gate::authorize('restore', $plan);
        $plan->restore();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan restaurado correctamente.']);

        return $this->redirectToResourceIndex(
            $request,
            'platform.planes.index',
            self::INDEX_QUERY_PARAMETERS,
            ['archivados' => true],
        );
    }
}
